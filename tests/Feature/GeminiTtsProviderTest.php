<?php

namespace Tests\Feature;

use App\Contracts\TextToSpeechInterface;
use App\Jobs\SynthesizeVoiceoverJob;
use App\Models\ActivityLog;
use App\Models\AiProvider;
use App\Models\GeneratedContent;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\VideoProject;
use App\Modules\TextToSpeech\GeminiTTSProvider;
use App\Modules\TextToSpeech\TTSProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeminiTtsProviderTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $email = 'owner@plantcoaction.local', string $role = 'owner'): User
    {
        return User::factory()->create([
            'name' => $role === 'owner' ? 'Plant Coaction Owner' : 'Member User',
            'email' => $email,
            'password' => bcrypt('SecretPass123!'),
            'role' => $role,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }

    private function configureSharedGeminiKey(string $key = 'AIzaSySharedGeminiKeyForTtsTest123456'): AiProvider
    {
        $provider = AiProvider::firstOrCreate(
            ['slug' => 'google_gemini'],
            [
                'name' => 'Google Gemini',
                'driver' => 'google_gemini',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'is_active' => true,
                'is_default' => false,
            ]
        );
        $provider->setRawApiKey($key);
        $provider->save();

        return $provider;
    }

    /**
     * Generate 0.5 seconds of valid 16-bit signed little-endian PCM (24 kHz mono) sine wave for real FFmpeg test.
     */
    private function makeValidPcmBase64(float $seconds = 0.5, int $sampleRate = 24000): string
    {
        $samples = (int) round($seconds * $sampleRate);
        $binary = '';
        for ($i = 0; $i < $samples; $i++) {
            $val = (int) round(sin(2 * M_PI * 440.0 * $i / $sampleRate) * 12000);
            $binary .= pack('v', $val & 0xFFFF);
        }

        return base64_encode($binary);
    }

    public function test_1_2_3_21_provider_registration_credential_sharing_missing_key_and_selection(): void
    {
        $owner = $this->createUser();

        /** @var TTSProviderManager $manager */
        $manager = app(TTSProviderManager::class);
        $this->assertInstanceOf(GeminiTTSProvider::class, $manager->driver('google_gemini_tts'));
        $this->assertSame('google_cloud_tts', $manager->driver('google_cloud_tts')->getSlug());

        // 3. Tanpa API Key Gemini -> status not_configured & tidak memanggil HTTP eksternal
        Http::fake();
        $this->actingAs($owner)
            ->postJson('/settings/tts/gemini/test')
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'status' => 'not_configured']);
        Http::assertNothingSent();

        // 2. Credential sharing: simpan key di Gemini AI Provider, Gemini TTS otomatis terkonfigurasi
        $sharedKey = 'AIzaSySharedKeyOnlyStoredOnce999999';
        $this->configureSharedGeminiKey($sharedKey);
        $this->assertTrue($manager->driver('google_gemini_tts')->isConfigured());

        // 21. Pemilihan default TTS provider (Google Gemini TTS <-> Google Cloud TTS)
        $this->actingAs($owner)
            ->post('/settings/tts/provider', ['default_tts_provider' => 'google_cloud_tts'])
            ->assertRedirect();
        $this->assertSame('google_cloud_tts', app(TextToSpeechInterface::class)->getSlug());

        $this->actingAs($owner)
            ->post('/settings/tts/provider', ['default_tts_provider' => 'google_gemini_tts'])
            ->assertRedirect();
        $this->assertSame('google_gemini_tts', app(TextToSpeechInterface::class)->getSlug());

        // 22. Pastikan API key tidak bocor di halaman Settings TTS
        $this->actingAs($owner)
            ->get('/settings/tts?tab=google_gemini_tts')
            ->assertStatus(200)
            ->assertSee('Google Gemini TTS')
            ->assertDontSee($sharedKey);
    }

    public function test_6_7_9_10_validation_for_empty_too_long_unsupported_model_and_invalid_voice(): void
    {
        $this->configureSharedGeminiKey();
        Http::fake();

        /** @var GeminiTTSProvider $tts */
        $tts = app(GeminiTTSProvider::class);

        // 9. Input kosong
        $empty = $tts->synthesize('   ');
        $this->assertFalse($empty['ok']);

        // 10. Input terlalu panjang (>3500 karakter)
        $tooLong = $tts->synthesize(str_repeat('a', 3600));
        $this->assertFalse($tooLong['ok']);
        $this->assertStringContainsString('terlalu panjang', strtolower($tooLong['message']));

        // 6. Model teks biasa yang tidak mendukung TTS ditolak
        $badModel = $tts->synthesize('Halo tanaman hias.', ['model_id' => 'gemini-2.5-flash']);
        $this->assertFalse($badModel['ok']);
        $this->assertStringContainsString('tidak mendukung gemini tts', strtolower($badModel['message']));

        // 7. Voice fiktif / tidak tersedia ditolak
        $badVoice = $tts->synthesize('Halo tanaman hias.', ['voice_id' => 'FakeVoiceXYZ']);
        $this->assertFalse($badVoice['ok']);
        $this->assertStringContainsString('tidak termasuk', strtolower($badVoice['message']));

        Http::assertNothingSent();
    }

    public function test_4_15_invalid_key_returns_401_and_does_not_retry(): void
    {
        $key = 'AIzaSyInvalidTestKey88888';
        $this->configureSharedGeminiKey($key);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
                'error' => ['code' => 401, 'message' => 'API key invalid.'],
            ], 401),
        ]);

        /** @var GeminiTTSProvider $tts */
        $tts = app(GeminiTTSProvider::class);
        $res401 = $tts->synthesize('Halo Plant Coaction.', ['max_retries' => 2]);
        $this->assertFalse($res401['ok']);
        $this->assertSame(401, $res401['http_status']);
        Http::assertSentCount(1);
    }

    public function test_11_12_15_rate_limit_and_quota_exceeded_do_not_retry(): void
    {
        $key = 'AIzaSyQuotaTestKey88888';
        $this->configureSharedGeminiKey($key);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
                'error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded'],
            ], 429),
        ]);

        /** @var GeminiTTSProvider $tts */
        $tts = app(GeminiTTSProvider::class);
        $res429 = $tts->synthesize('Halo Plant Coaction.', ['max_retries' => 2]);
        $this->assertFalse($res429['ok']);
        $this->assertSame(429, $res429['http_status']);
        $this->assertStringContainsString('429', $res429['message']);
        Http::assertSentCount(1);
    }

    public function test_13_14_retry_on_5xx_and_handle_network_timeout(): void
    {
        $this->configureSharedGeminiKey();
        $pcmBase64 = $this->makeValidPcmBase64(0.3);

        // 14. Retry pada 503 -> percobaan ke-2 sukses
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::sequence()
                ->push(['error' => ['message' => 'Temporary 503']], 503)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [[
                            'inlineData' => [
                                'mimeType' => 'audio/L16;codec=pcm;rate=24000',
                                'data' => $pcmBase64,
                            ],
                        ]]],
                    ]],
                ], 200),
        ]);

        /** @var GeminiTTSProvider $tts */
        $tts = app(GeminiTTSProvider::class);
        $res = $tts->synthesize('Uji retry pada error sementara 503.', ['max_retries' => 1]);
        $this->assertTrue($res['ok']);
        Http::assertSentCount(2);

        // Cleanup generated test files
        if (! empty($res['file_path'])) {
            Storage::disk('local')->delete($res['file_path']);
        }
        if (! empty($res['master_wav_path'])) {
            Storage::disk('local')->delete($res['master_wav_path']);
        }
    }

    public function test_13_network_timeout_handled_cleanly(): void
    {
        $this->configureSharedGeminiKey();
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        /** @var GeminiTTSProvider $tts */
        $tts = app(GeminiTTSProvider::class);
        $res = $tts->synthesize('Uji timeout jaringan.', ['max_retries' => 0]);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('timeout', strtolower($res['message']));
    }

    public function test_5_8_16_17_18_19_22_preview_pcm_decode_real_ffmpeg_conversion_storage_playback_and_auth(): void
    {
        $owner = $this->createUser('owner@plantcoaction.local', 'owner');
        $otherUser = $this->createUser('member@plantcoaction.local', 'member');
        $secretKey = 'AIzaSyLiveMockSecretKeyNeverExpose7777';
        $this->configureSharedGeminiKey($secretKey);

        $pcmBase64 = $this->makeValidPcmBase64(0.6, 24000);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-tts:generateContent' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'inlineData' => [
                            'mimeType' => 'audio/L16;codec=pcm;rate=24000',
                            'data' => $pcmBase64,
                        ],
                    ]]],
                ]],
            ], 200),
            'https://generativelanguage.googleapis.com/v1beta/models*' => Http::response([
                'models' => [
                    ['name' => 'models/gemini-2.5-flash-preview-tts', 'displayName' => 'Gemini 2.5 Flash Preview TTS', 'supportedGenerationMethods' => ['generateContent']],
                ],
            ], 200),
        ]);

        // Test Connection sukses dengan model TTS terverifikasi
        $this->actingAs($owner)
            ->postJson('/settings/tts/gemini/test')
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'status' => 'connected']);

        // 5, 8, 16, 17, 18, 19. Preview suara Bahasa Indonesia + real FFmpeg PCM->WAV & MP3 conversion
        $resp = $this->actingAs($owner)
            ->postJson('/settings/tts/gemini/preview', [
                'text' => 'Halo, selamat datang di Plant Coaction. Hari ini kita akan membahas cara merawat tanaman hias agar tumbuh sehat dan subur.',
                'model_id' => 'gemini-2.5-flash-preview-tts',
                'voice_id' => 'Kore',
                'pace_preset' => 'normal',
            ])
            ->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'provider' => 'google_gemini_tts',
                'model_id' => 'gemini-2.5-flash-preview-tts',
                'voice_id' => 'Kore',
                'mime_type' => 'audio/mpeg',
            ]);

        $data = $resp->json();
        $this->assertGreaterThan(0.4, (float) $data['duration_seconds']);
        $this->assertSame(24000, (int) $data['sample_rate']);
        $this->assertNotEmpty($data['audio_base64']);

        $asset = MediaAsset::findOrFail($data['asset_id']);
        $this->assertTrue(Storage::disk('local')->exists($asset->file_path));
        $masterWav = data_get($asset->metadata, 'master_wav_path');
        $this->assertNotEmpty($masterWav);
        $this->assertTrue(Storage::disk('local')->exists($masterWav));

        // Pastikan file WAV valid (RIFF header) & MP3 playable (non-empty)
        $wavHeader = substr((string) Storage::disk('local')->get($masterWav), 0, 4);
        $this->assertSame('RIFF', $wavHeader);

        // Proteksi akses audio antar-user (otherUser -> 403, owner -> 200 download)
        $this->actingAs($otherUser)
            ->get("/settings/tts/audio/{$asset->id}")
            ->assertStatus(403);

        $this->actingAs($owner)
            ->get("/settings/tts/audio/{$asset->id}")
            ->assertStatus(200);

        // Integrasi pipeline melalui TextToSpeechInterface (SynthesizeVoiceoverJob)
        $content = GeneratedContent::create(['user_id' => $owner->id, 'title' => 'Perawatan Aroid', 'status' => 'draft']);
        $project = VideoProject::create(['user_id' => $owner->id, 'generated_content_id' => $content->id, 'title' => 'Video Aroid', 'status' => 'draft']);
        app()->call([new SynthesizeVoiceoverJob($project->id, 'Tips menyiram Anthurium pagi hari.'), 'handle']);
        $project->refresh();
        $this->assertNotNull($project->voiceover_asset_id);
        $voiceAsset = MediaAsset::findOrFail($project->voiceover_asset_id);
        $this->assertSame('audio/wav', $voiceAsset->mime_type);
        $this->assertTrue(Storage::disk('local')->exists($voiceAsset->file_path));

        // 22. Pastikan API key tidak bocor di response, metadata asset, maupun activity_logs
        $this->assertStringNotContainsString($secretKey, $resp->getContent());
        $this->assertStringNotContainsString($secretKey, json_encode($asset->metadata));
        foreach (ActivityLog::all() as $log) {
            $blob = $log->description . ' ' . json_encode($log->context);
            $this->assertStringNotContainsString($secretKey, $blob);
        }

        // Bersihkan file audio uji
        Storage::disk('local')->delete(array_filter([
            $asset->file_path,
            $masterWav,
            $voiceAsset->file_path,
            data_get($voiceAsset->metadata, 'preview_mp3_path'),
        ]));
    }
}
