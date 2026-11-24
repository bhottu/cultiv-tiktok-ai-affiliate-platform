<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AiModelConfiguration;
use App\Models\AiProvider;
use App\Models\User;
use App\Modules\AICore\AIProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiProviderTest extends TestCase
{
    use RefreshDatabase;

    private function createOwner(): User
    {
        return User::factory()->create([
            'name' => 'Plant Coaction Owner',
            'email' => 'owner@plantcoaction.local',
            'password' => bcrypt('SecretPass123!'),
            'role' => 'owner',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }

    public function test_gemini_without_api_key_shows_not_configured_and_makes_no_http_call(): void
    {
        $owner = $this->createOwner();
        Http::fake();

        $this->actingAs($owner)
            ->get('/settings/ai-providers?tab=google_gemini')
            ->assertStatus(200)
            ->assertSee('Google Gemini')
            ->assertSee('https://aistudio.google.com/apikey');

        $this->actingAs($owner)
            ->postJson('/settings/gemini/test')
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'status' => 'not_configured']);

        $this->actingAs($owner)
            ->getJson('/settings/gemini/models')
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        Http::assertNothingSent();
    }

    public function test_gemini_api_key_encryption_masking_update_remove_and_no_log_leak(): void
    {
        $owner = $this->createOwner();
        $plainKey = 'AIzaSyTestGeminiSecretKeyValue99887766';

        // Validasi input
        $this->actingAs($owner)
            ->post('/settings/gemini', [
                'api_key' => 'short',
                'base_url' => 'invalid-url',
                'timeout' => 999,
                'max_retries' => 9,
                'temperature' => 5,
                'max_output_tokens' => 10,
            ])
            ->assertSessionHasErrors(['api_key', 'base_url', 'timeout', 'max_retries', 'temperature', 'max_output_tokens']);

        // Simpan API Key
        $this->actingAs($owner)
            ->post('/settings/gemini', [
                'api_key' => $plainKey,
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'timeout' => 30,
                'max_retries' => 1,
                'temperature' => 0.7,
                'max_output_tokens' => 2048,
            ])
            ->assertRedirect('/settings/ai-providers?tab=google_gemini');

        $rawEnc = DB::table('ai_providers')->where('slug', 'google_gemini')->value('api_key_encrypted');
        $this->assertNotEmpty($rawEnc);
        $this->assertNotSame($plainKey, $rawEnc);
        $this->assertStringNotContainsString($plainKey, $rawEnc);

        $provider = AiProvider::where('slug', 'google_gemini')->firstOrFail();
        $this->assertSame($plainKey, $provider->getDecryptedApiKey());

        // Pastikan HTML & activity_logs tidak pernah memuat API key plaintext
        $this->actingAs($owner)
            ->get('/settings/ai-providers?tab=google_gemini')
            ->assertStatus(200)
            ->assertDontSee($plainKey);

        foreach (ActivityLog::all() as $log) {
            $this->assertStringNotContainsString($plainKey, $log->description);
            $this->assertStringNotContainsString($plainKey, json_encode($log->context));
        }

        // Remove API Key
        $this->actingAs($owner)
            ->delete('/settings/gemini/key')
            ->assertRedirect('/settings/ai-providers?tab=google_gemini');

        $this->assertNull($provider->fresh()->getDecryptedApiKey());
        $this->assertSame('not_configured', $provider->fresh()->connection_status);
    }

    public function test_gemini_test_connection_valid_invalid_model_discovery_and_selection(): void
    {
        $owner = $this->createOwner();
        $plainKey = 'AIzaSyValidMockGeminiKey1234567890';

        $this->actingAs($owner)->post('/settings/gemini', [
            'api_key' => $plainKey,
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'timeout' => 30,
            'max_retries' => 1,
            'temperature' => 0.7,
            'max_output_tokens' => 2048,
        ]);

        // 1. Test Connection sukses + Model Discovery dengan filter generateContent
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models*' => Http::response([
                'models' => [
                    [
                        'name' => 'models/gemini-2.5-flash',
                        'displayName' => 'Gemini 2.5 Flash',
                        'supportedGenerationMethods' => ['generateContent', 'countTokens'],
                        'inputTokenLimit' => 1048576,
                        'outputTokenLimit' => 65536,
                    ],
                    [
                        'name' => 'models/text-embedding-004',
                        'displayName' => 'Text Embedding 004',
                        'supportedGenerationMethods' => ['embedContent'],
                        'inputTokenLimit' => 2048,
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->postJson('/settings/gemini/test')
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'status' => 'connected', 'models_count' => 1]);

        // Pastikan header resmi x-goog-api-key dipakai (bukan query string)
        Http::assertSent(fn ($req) => $req->hasHeader('x-goog-api-key', $plainKey));

        // Daftar model default hanya mengembalikan model yang mendukung generateContent
        $this->actingAs($owner)
            ->getJson('/settings/gemini/models?only_generate_content=1')
            ->assertStatus(200)
            ->assertJsonPath('filtered_count', 1)
            ->assertJsonPath('models.0.id', 'gemini-2.5-flash');

        // Tolak pemilihan model yang tidak mendukung generateContent
        $this->actingAs($owner)
            ->post('/settings/gemini/select-model', [
                'model_id' => 'text-embedding-004',
                'display_name' => 'Text Embedding 004',
                'supported_methods' => 'embedContent',
            ])
            ->assertSessionHasErrors('model_id');

        // Simpan model valid ke database
        $this->actingAs($owner)
            ->post('/settings/gemini/select-model', [
                'model_id' => 'gemini-2.5-flash',
                'display_name' => 'Gemini 2.5 Flash',
                'input_token_limit' => 1048576,
                'output_token_limit' => 65536,
                'supported_methods' => 'generateContent,countTokens',
            ])
            ->assertRedirect('/settings/ai-providers?tab=google_gemini');

        $this->assertDatabaseHas('ai_model_configurations', [
            'model_id' => 'gemini-2.5-flash',
            'is_default' => true,
        ]);

        // Pastikan riwayat pengujian koneksi tercatat
        $rec = AiProvider::where('slug', 'google_gemini')->firstOrFail();
        $this->assertNotEmpty($rec->settings['test_history'] ?? []);
    }

    public function test_gemini_invalid_key_and_quota_exceeded_handling(): void
    {
        $owner = $this->createOwner();
        $badKey = 'AIzaSyInvalidKeyForErrorHandlingTest';

        $this->actingAs($owner)->post('/settings/gemini', [
            'api_key' => $badKey,
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'timeout' => 30,
            'max_retries' => 1,
            'temperature' => 0.7,
            'max_output_tokens' => 2048,
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models*' => Http::response([
                'error' => ['code' => 400, 'message' => 'API key not valid.', 'status' => 'INVALID_ARGUMENT'],
            ], 400),
        ]);

        $this->actingAs($owner)
            ->postJson('/settings/gemini/test')
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'status' => 'failed']);
    }

    public function test_gemini_generate_text_and_controlled_multi_provider_fallback(): void
    {
        $owner = $this->createOwner();

        // Konfigurasi kedua provider (OpenRouter & Gemini)
        $or = AiProvider::firstOrCreate(['slug' => 'openrouter'], ['name' => 'OpenRouter', 'driver' => 'openrouter', 'base_url' => 'https://openrouter.ai/api/v1', 'is_default' => true]);
        $or->setRawApiKey('sk-or-v1-primary-secret-key-0001');
        $or->save();
        AiModelConfiguration::create([
            'ai_provider_id' => $or->id,
            'model_id' => 'google/gemini-2.5-flash',
            'display_name' => 'Gemini 2.5 Flash (OR)',
            'task_role' => 'general',
            'is_default' => true,
        ]);

        $gem = AiProvider::firstOrCreate(['slug' => 'google_gemini'], ['name' => 'Google Gemini', 'driver' => 'google_gemini', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta', 'is_default' => false]);
        $gem->setRawApiKey('AIzaSyFallbackGeminiSecretKey0002');
        $gem->save();
        AiModelConfiguration::create([
            'ai_provider_id' => $gem->id,
            'model_id' => 'gemini-2.5-flash',
            'display_name' => 'Gemini 2.5 Flash',
            'task_role' => 'general',
            'is_default' => true,
        ]);

        // Simulasikan OpenRouter gagal (503) & Gemini sukses merespons generateContent
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => 'Service unavailable'], 503),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Tips merawat Anthurium agar daun tetap sehat.']]]],
                ],
                'usageMetadata' => ['promptTokenCount' => 18, 'candidatesTokenCount' => 12, 'totalTokenCount' => 30],
            ], 200),
        ]);

        /** @var AIProviderManager $manager */
        $manager = app(AIProviderManager::class);

        // 1. Generate teks langsung via Gemini
        $direct = $manager->driver('google_gemini')->chat([
            ['role' => 'system', 'content' => 'Ahli tanaman hias Plant Coaction.'],
            ['role' => 'user', 'content' => 'Tulis tips singkat Anthurium.'],
        ]);
        $this->assertTrue($direct['ok']);
        $this->assertStringContainsString('Anthurium', $direct['content']);

        // 2. Fallback NONAKTIF -> gagal tanpa diam-diam pindah provider
        $this->actingAs($owner)->post('/settings/ai-providers/routing', [
            'default_provider' => 'openrouter',
            'fallback_enabled' => 0,
        ])->assertRedirect();

        $noFallback = $manager->chatWithFallback([['role' => 'user', 'content' => 'Halo']]);
        $this->assertFalse($noFallback['ok']);
        $this->assertFalse($noFallback['fallback_used']);
        $this->assertSame('openrouter', $noFallback['provider_used']);

        // 3. Aktifkan Fallback -> OpenRouter gagal -> fallback 1x ke Google Gemini dan tercatat di ActivityLog
        $this->actingAs($owner)->post('/settings/ai-providers/routing', [
            'default_provider' => 'openrouter',
            'fallback_enabled' => 1,
        ])->assertRedirect();

        $withFallback = $manager->chatWithFallback([['role' => 'user', 'content' => 'Halo']]);
        $this->assertTrue($withFallback['ok']);
        $this->assertTrue($withFallback['fallback_used']);
        $this->assertSame('google_gemini', $withFallback['provider_used']);
        $this->assertNotEmpty($withFallback['fallback_reason']);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'ai_core',
            'action' => 'provider_fallback_triggered',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'ai_core',
            'action' => 'provider_fallback_succeeded',
        ]);

        // Pastikan tidak ada secret bocor di ActivityLog
        foreach (ActivityLog::all() as $log) {
            $blob = $log->description . ' ' . json_encode($log->context);
            $this->assertStringNotContainsString('sk-or-v1-primary-secret-key-0001', $blob);
            $this->assertStringNotContainsString('AIzaSyFallbackGeminiSecretKey0002', $blob);
        }
    }
}
