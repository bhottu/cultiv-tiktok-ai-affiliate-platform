<?php

namespace Tests\Feature;

use App\Jobs\GenerateContentJob;
use App\Jobs\RenderRemotionVideoJob;
use App\Jobs\SynthesizeVoiceoverJob;
use App\Jobs\SystemHealthCheckJob;
use App\Models\AiProvider;
use App\Models\GeneratedContent;
use App\Models\User;
use App\Models\VideoProject;
use App\Models\VideoRenderJob;
use App\Models\VoiceProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Phase1FoundationTest extends TestCase
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

    public function test_postgresql_connection_and_timezone_configured(): void
    {
        $this->assertSame('pgsql', DB::getDefaultConnection());
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $version = DB::selectOne('SELECT version() AS v')->v;
        $this->assertStringContainsString('PostgreSQL', $version);
    }

    public function test_authentication_flow_and_guest_protection(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertStatus(200)->assertSee('Plant Coaction Affiliate AI');

        $owner = $this->createOwner();

        // Password salah ditolak
        $this->post('/login', [
            'email' => $owner->email,
            'password' => 'SalahPassword',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Login valid berhasil
        $this->post('/login', [
            'email' => $owner->email,
            'password' => 'SecretPass123!',
        ])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($owner);

        // Logout berhasil
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_dashboard_and_all_modular_pages_render_without_errors(): void
    {
        $owner = $this->createOwner();

        $routes = [
            '/dashboard',
            '/agents',
            '/products',
            '/strategy',
            '/content',
            '/video',
            '/publishing',
            '/analytics',
            '/activity-logs',
            '/settings/openrouter',
            '/settings/tts',
            '/settings/general',
        ];

        foreach ($routes as $uri) {
            $this->actingAs($owner)
                ->get($uri)
                ->assertStatus(200);
        }
    }

    public function test_unconfigured_providers_return_errors_without_calling_external_api(): void
    {
        $owner = $this->createOwner();
        Http::fake();

        $this->actingAs($owner)
            ->postJson('/settings/openrouter/test')
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'status' => 'not_configured']);

        $this->actingAs($owner)
            ->postJson('/settings/tts/test')
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'status' => 'not_configured']);

        Http::assertNothingSent();
    }

    public function test_openrouter_encryption_validation_error_handling_and_model_selection(): void
    {
        $owner = $this->createOwner();

        // 1. Uji validasi input
        $this->actingAs($owner)
            ->post('/settings/openrouter', [
                'api_key' => 'short',
                'base_url' => 'not-a-url',
                'timeout' => 999,
            ])
            ->assertSessionHasErrors(['api_key', 'base_url', 'timeout']);

        // 2. Simpan API key dan verifikasi terenkripsi di PostgreSQL (bukan plaintext!)
        $plainKey = 'sk-or-v1-test-secret-key-9876543210';
        $this->actingAs($owner)
            ->post('/settings/openrouter', [
                'api_key' => $plainKey,
                'base_url' => 'https://openrouter.ai/api/v1',
                'timeout' => 30,
            ])
            ->assertRedirect('/settings/openrouter');

        $rawDbValue = DB::table('ai_providers')->where('slug', 'openrouter')->value('api_key_encrypted');
        $this->assertNotEmpty($rawDbValue);
        $this->assertNotSame($plainKey, $rawDbValue);
        $this->assertStringNotContainsString($plainKey, $rawDbValue);

        $provider = AiProvider::where('slug', 'openrouter')->firstOrFail();
        $this->assertSame($plainKey, $provider->getDecryptedApiKey());
        $this->assertStringNotContainsString('9876543', (string) $provider->getMaskedApiKey());

        // Halaman HTML tidak boleh pernah menampilkan API key penuh
        $this->actingAs($owner)
            ->get('/settings/openrouter')
            ->assertStatus(200)
            ->assertDontSee($plainKey);

        // 4. Uji Test Connection, Search & Filter Free/Paid Model via HTTP fake
        Http::fake([
            'https://openrouter.ai/api/v1/auth/key' => Http::response(['data' => ['label' => 'ok']], 200),
            'https://openrouter.ai/api/v1/models' => Http::response([
                'data' => [
                    ['id' => 'google/gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash', 'context_length' => 1000000, 'pricing' => ['prompt' => '0.00000015', 'completion' => '0.0000006']],
                    ['id' => 'meta-llama/llama-3.3-70b:free', 'name' => 'Llama 3.3 70B Free', 'context_length' => 128000, 'pricing' => ['prompt' => '0', 'completion' => '0']],
                ],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->postJson('/settings/openrouter/test')
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'status' => 'connected', 'models_count' => 2]);

        $this->actingAs($owner)
            ->getJson('/settings/openrouter/models?tier=free')
            ->assertStatus(200)
            ->assertJsonPath('filtered_count', 1)
            ->assertJsonPath('models.0.id', 'meta-llama/llama-3.3-70b:free');

        $this->actingAs($owner)
            ->post('/settings/openrouter/select-model', [
                'model_id' => 'google/gemini-2.5-flash',
                'display_name' => 'Gemini 2.5 Flash',
                'is_free' => 0,
                'role_type' => 'default',
            ])
            ->assertRedirect('/settings/openrouter');

        $this->assertDatabaseHas('ai_model_configurations', [
            'model_id' => 'google/gemini-2.5-flash',
            'is_default' => true,
        ]);
    }

    public function test_google_cloud_tts_encryption_validation_error_handling_and_preview(): void
    {
        $owner = $this->createOwner();

        // 1. Simpan kredensial terenkripsi
        $ttsKey = 'AIzaSyFakeTestCredentialForEncryptionCheck123';
        $this->actingAs($owner)
            ->post('/settings/tts', [
                'api_key' => $ttsKey,
                'language_code' => 'id-ID',
                'voice_id' => 'id-ID-Wavenet-A',
                'speaking_rate' => 1.05,
                'pitch' => 0.0,
                'volume_gain_db' => 0.0,
            ])
            ->assertRedirect('/settings/tts');

        $rawEnc = DB::table('voice_profiles')->where('provider_slug', 'google_cloud_tts')->value('credentials_encrypted');
        $this->assertNotEmpty($rawEnc);
        $this->assertNotSame($ttsKey, $rawEnc);
        $this->assertSame($ttsKey, VoiceProfile::where('provider_slug', 'google_cloud_tts')->firstOrFail()->getDecryptedCredential());

        // 3. Test connection + voices + preview audio via HTTP fake
        Http::fake([
            'https://texttospeech.googleapis.com/v1/voices*' => Http::response([
                'voices' => [
                    ['name' => 'id-ID-Wavenet-A', 'ssmlGender' => 'FEMALE', 'languageCodes' => ['id-ID'], 'naturalSampleRateHertz' => 24000],
                    ['name' => 'id-ID-Wavenet-B', 'ssmlGender' => 'MALE', 'languageCodes' => ['id-ID'], 'naturalSampleRateHertz' => 24000],
                ],
            ], 200),
            'https://texttospeech.googleapis.com/v1/text:synthesize*' => Http::response([
                'audioContent' => base64_encode('FAKE_MP3_BYTES_FOR_UNIT_TEST'),
            ], 200),
        ]);

        $this->actingAs($owner)
            ->postJson('/settings/tts/test')
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'status' => 'connected', 'voices_count' => 2]);

        $this->actingAs($owner)
            ->postJson('/settings/tts/preview', ['text' => 'Uji suara Bahasa Indonesia untuk Plant Coaction.'])
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'mime_type' => 'audio/mpeg']);
    }

    public function test_queue_jobs_handle_missing_credentials_gracefully_and_health_check_runs(): void
    {
        $owner = $this->createOwner();

        // Dispatch check
        Queue::fake();
        $this->actingAs($owner)
            ->post('/settings/general/health-check')
            ->assertRedirect('/settings/general');
        Queue::assertPushed(SystemHealthCheckJob::class);

        // Jalankan langsung SystemHealthCheckJob (sync)
        app()->call([new SystemHealthCheckJob(), 'handle']);
        $this->assertDatabaseHas('application_settings', ['key' => 'system.last_scheduler_heartbeat']);

        // GenerateContentJob tanpa API key -> waiting_credentials (tidak crash)
        $content = GeneratedContent::create(['user_id' => $owner->id, 'title' => 'Tips Aroid', 'status' => 'draft']);
        app()->call([new GenerateContentJob($content->id), 'handle']);
        $this->assertSame('waiting_credentials', $content->fresh()->status);

        // SynthesizeVoiceoverJob tanpa TTS key -> waiting_credentials
        $video = VideoProject::create(['user_id' => $owner->id, 'generated_content_id' => $content->id, 'title' => 'Video Aroid', 'status' => 'draft']);
        app()->call([new SynthesizeVoiceoverJob($video->id, 'Halo pecinta tanaman.'), 'handle']);
        $this->assertSame('waiting_credentials', $video->fresh()->status);

        // RenderRemotionVideoJob di Fase 1 -> tidak mengklaim sukses palsu
        $renderJob = VideoRenderJob::create([
            'video_project_id' => $video->id,
            'renderer' => 'remotion',
            'idempotency_key' => 'render-test-1',
            'status' => 'pending',
        ]);
        app()->call([new RenderRemotionVideoJob($renderJob->id), 'handle']);
        $this->assertNotSame('completed', $renderJob->fresh()->status);
    }
}
