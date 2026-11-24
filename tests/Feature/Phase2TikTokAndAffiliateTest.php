<?php

namespace Tests\Feature;

use App\Enums\ProductApprovalStatus;
use App\Models\ActivityLog;
use App\Models\AffiliateProduct;
use App\Models\AiModelConfiguration;
use App\Models\AiProvider;
use App\Models\SocialAccount;
use App\Models\User;
use App\Modules\TikTokIntegration\TikTokOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase2TikTokAndAffiliateTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $email = 'owner@plantcoaction.local', string $role = 'owner'): User
    {
        return User::factory()->create([
            'name' => $role === 'owner' ? 'Plant Coaction Owner' : 'Staff User',
            'email' => $email,
            'password' => bcrypt('SecretPass123!'),
            'role' => $role,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }

    private function configureTikTokEnv(): void
    {
        config()->set('affiliate_ai.tiktok.client_key', 'test_client_key_123');
        config()->set('affiliate_ai.tiktok.client_secret', 'test_client_secret_xyz');
        config()->set('affiliate_ai.tiktok.redirect_uri', 'http://127.0.0.1:8000/dashboard/tiktok/callback');
        config()->set('affiliate_ai.tiktok.scopes', 'user.info.basic,user.info.profile,user.info.stats');
    }

    public function test_1_2_3_5_6_10_oauth_redirect_state_validation_callback_encryption_and_account_info(): void
    {
        $owner = $this->createUser();
        $this->configureTikTokEnv();

        // 1. OAuth redirect
        $resp = $this->actingAs($owner)->post('/dashboard/tiktok/connect');
        $resp->assertRedirect();
        $location = (string) $resp->headers->get('Location');
        $this->assertStringStartsWith('https://www.tiktok.com/v2/auth/authorize/', $location);
        $this->assertStringContainsString('client_key=test_client_key_123', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $queryParams);
        $state = $queryParams['state'] ?? '';
        $this->assertNotEmpty($state);

        // 3. Callback sukses dengan mock HTTP resmi TikTok
        $rawAccessToken = 'act.mockAccessTokenSecretValue123456';
        $rawRefreshToken = 'rft.mockRefreshTokenSecretValue987654';

        Http::fake([
            'https://open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'open_id' => 'open_id_plant_001',
                'scope' => 'user.info.basic,user.info.profile,user.info.stats',
                'access_token' => $rawAccessToken,
                'expires_in' => 86400,
                'refresh_token' => $rawRefreshToken,
                'refresh_expires_in' => 31536000,
                'token_type' => 'Bearer',
            ], 200),
            'https://open.tiktokapis.com/v2/user/info/*' => Http::response([
                'data' => [
                    'user' => [
                        'open_id' => 'open_id_plant_001',
                        'display_name' => 'Plant Coaction',
                        'username' => 'plant.coaction',
                        'avatar_url' => 'https://example.com/avatar.jpg',
                        'follower_count' => 1250,
                        'following_count' => 50,
                        'likes_count' => 9800,
                        'video_count' => 34,
                    ],
                ],
                'error' => ['code' => 'ok', 'message' => ''],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->get('/dashboard/tiktok/callback?code=auth_code_valid&state=' . urlencode($state))
            ->assertRedirect('/dashboard/tiktok')
            ->assertSessionHas('status');

        // 5. Token tersimpan terenkripsi (bukan plaintext!)
        $row = DB::table('social_accounts')->where('external_account_id', 'open_id_plant_001')->first();
        $this->assertNotNull($row);
        $this->assertNotSame($rawAccessToken, $row->access_token_encrypted);
        $this->assertNotSame($rawRefreshToken, $row->refresh_token_encrypted);
        $this->assertStringNotContainsString($rawAccessToken, (string) $row->access_token_encrypted);

        $account = SocialAccount::findOrFail($row->id);
        $this->assertSame($rawAccessToken, $account->getDecryptedAccessToken());
        $this->assertSame($rawRefreshToken, $account->getDecryptedRefreshToken());

        // 6 & 10. Account info tampil di UI & token TIDAK pernah muncul di HTML maupun activity_logs
        $this->actingAs($owner)
            ->get('/dashboard/tiktok')
            ->assertStatus(200)
            ->assertSee('Plant Coaction')
            ->assertSee('@plant.coaction')
            ->assertSee('1,250')
            ->assertDontSee($rawAccessToken)
            ->assertDontSee($rawRefreshToken);

        foreach (ActivityLog::all() as $log) {
            $blob = $log->description . ' ' . json_encode($log->context);
            $this->assertStringNotContainsString($rawAccessToken, $blob);
            $this->assertStringNotContainsString($rawRefreshToken, $blob);
        }
    }

    public function test_4_callback_with_invalid_state_is_rejected_without_http_calls(): void
    {
        $owner = $this->createUser();
        $this->configureTikTokEnv();
        Http::fake();

        $this->actingAs($owner)
            ->withSession([
                TikTokOAuthService::SESSION_STATE_KEY => 'expected_valid_state_token',
                TikTokOAuthService::SESSION_STATE_EXPIRES_KEY => now()->addMinutes(5)->timestamp,
                TikTokOAuthService::SESSION_USER_KEY => $owner->id,
            ])
            ->get('/dashboard/tiktok/callback?code=abc&state=tampered_invalid_state')
            ->assertRedirect('/dashboard/tiktok')
            ->assertSessionHasErrors('tiktok_oauth');

        Http::assertNothingSent();
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'tiktok_oauth',
            'action' => 'callback_invalid_state',
        ]);
    }

    public function test_7_token_expiration_blocks_sync_without_http_call(): void
    {
        $owner = $this->createUser();
        $this->configureTikTokEnv();

        $account = SocialAccount::create([
            'user_id' => $owner->id,
            'platform' => 'tiktok',
            'handle' => '@plant.coaction',
            'external_account_id' => 'open_id_plant_002',
            'display_name' => 'Plant Coaction',
            'status' => 'connected',
            'scopes' => ['user.info.basic', 'user.info.stats'],
            'token_expires_at' => now()->subMinutes(10),
            'refresh_token_expires_at' => now()->addDays(30),
        ]);
        $account->setEncryptedTokens('act.expiredToken111', 'rft.validRefreshToken222');
        $account->save();

        Http::fake();
        $this->actingAs($owner)
            ->post("/dashboard/tiktok/{$account->id}/sync")
            ->assertRedirect('/dashboard/tiktok')
            ->assertSessionHasErrors('tiktok_api');
        $this->assertSame('token_expired', $account->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_8_9_18_refresh_rotation_disconnect_and_api_error_handling(): void
    {
        $owner = $this->createUser();
        $this->configureTikTokEnv();

        $account = SocialAccount::create([
            'user_id' => $owner->id,
            'platform' => 'tiktok',
            'handle' => '@plant.coaction',
            'external_account_id' => 'open_id_plant_003',
            'display_name' => 'Plant Coaction',
            'status' => 'token_expired',
            'scopes' => ['user.info.basic', 'user.info.stats'],
            'token_expires_at' => now()->subMinutes(10),
            'refresh_token_expires_at' => now()->addDays(30),
        ]);
        $account->setEncryptedTokens('act.expiredToken111', 'rft.validRefreshToken222');
        $account->save();

        // 8. Refresh token dengan rotasi token terenkripsi
        $rotatedAccess = 'act.freshAccessToken333';
        $rotatedRefresh = 'rft.freshRefreshToken444';

        Http::fake([
            'https://open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'access_token' => $rotatedAccess,
                'expires_in' => 86400,
                'refresh_token' => $rotatedRefresh,
                'refresh_expires_in' => 31536000,
                'scope' => 'user.info.basic,user.info.stats',
            ], 200),
            'https://open.tiktokapis.com/v2/user/info/*' => Http::response(['error' => ['code' => 'access_token_invalid']], 401),
            'https://open.tiktokapis.com/v2/oauth/revoke/' => Http::response([], 200),
        ]);

        $this->actingAs($owner)
            ->post("/dashboard/tiktok/{$account->id}/refresh")
            ->assertRedirect('/dashboard/tiktok')
            ->assertSessionHas('status');

        $fresh = $account->fresh();
        $this->assertSame('connected', $fresh->status);
        $this->assertSame($rotatedAccess, $fresh->getDecryptedAccessToken());
        $this->assertSame($rotatedRefresh, $fresh->getDecryptedRefreshToken());

        // 18. Error handling API TikTok (HTTP 401 -> status menjadi revoked)
        $this->actingAs($owner)
            ->post("/dashboard/tiktok/{$account->id}/sync")
            ->assertRedirect('/dashboard/tiktok')
            ->assertSessionHasErrors('tiktok_api');
        $this->assertSame('revoked', $account->fresh()->status);

        // 9. Disconnect account -> revoke & hapus seluruh token lokal
        $this->actingAs($owner)
            ->post("/dashboard/tiktok/{$account->id}/disconnect")
            ->assertRedirect('/dashboard/tiktok')
            ->assertSessionHas('status');

        $disconnected = $account->fresh();
        $this->assertSame('disconnected', $disconnected->status);
        $this->assertNull($disconnected->getDecryptedAccessToken());
        $this->assertNull($disconnected->getDecryptedRefreshToken());
        $this->assertDatabaseHas('activity_logs', ['module' => 'tiktok_oauth', 'action' => 'account_disconnected']);
    }

    public function test_11_12_13_unavailable_states_for_analytics_affiliate_readiness_and_product_discovery(): void
    {
        $owner = $this->createUser();

        // 11. Analytics unavailable state (zero dummy numbers)
        $this->actingAs($owner)
            ->get('/dashboard/tiktok/analytics')
            ->assertStatus(200)
            ->assertSee('Data Analytics TikTok Belum Tersedia Melalui Integrasi Resmi');

        // 12. Affiliate readiness unavailable state (exact required wording)
        $this->actingAs($owner)
            ->get('/dashboard/affiliate')
            ->assertStatus(200)
            ->assertSee('Belum dapat diverifikasi melalui integrasi API. Silakan periksa langsung melalui TikTok Shop Creator Center.');

        // 13. Product discovery unavailable state (zero fake products created)
        $this->actingAs($owner)
            ->get('/dashboard/affiliate/products')
            ->assertStatus(200)
            ->assertSee('Menunggu akses TikTok Shop Affiliate API');

        $this->actingAs($owner)
            ->post('/dashboard/affiliate/products/search', ['keyword' => 'media tanam aroid'])
            ->assertRedirect('/dashboard/affiliate/products')
            ->assertSessionHasErrors('shop_api');

        $this->assertSame(0, AffiliateProduct::count());
    }

    public function test_14_15_16_17_product_analysis_approval_workflow_authorization_and_activity_logs(): void
    {
        $owner = $this->createUser('owner@plantcoaction.local', 'owner');
        $otherUser = $this->createUser('other@plantcoaction.local', 'member');

        // Setup Gemini provider untuk AI Product Analysis
        $gem = AiProvider::firstOrCreate(
            ['slug' => 'google_gemini'],
            ['name' => 'Google Gemini', 'driver' => 'google_gemini', 'base_url' => 'https://generativelanguage.googleapis.com/v1beta', 'is_default' => true]
        );
        AiProvider::where('slug', '!=', 'google_gemini')->update(['is_default' => false]);
        $gem->setRawApiKey('AIzaSyProductAnalysisMockKey9999');
        $gem->is_default = true;
        $gem->save();

        AiModelConfiguration::create([
            'ai_provider_id' => $gem->id,
            'model_id' => 'gemini-2.5-flash',
            'display_name' => 'Gemini 2.5 Flash',
            'task_role' => 'general',
            'is_default' => true,
        ]);

        $product = AffiliateProduct::create([
            'user_id' => $owner->id,
            'external_product_id' => 'tts_prod_1001',
            'source_platform' => 'tiktok_shop_api',
            'name' => 'Media Tanam Aroid Premium Mix 2L',
            'category' => 'Media Tanam',
            'seller_name' => 'Nursery Resmi ID',
            'price' => 45000,
            'approval_status' => ProductApprovalStatus::DISCOVERED->value,
            'fetched_at' => now(),
        ]);

        // 16. Authorization antar-user: otherUser dilarang menganalisis / menyetujui produk milik owner
        $this->actingAs($otherUser)
            ->post("/dashboard/affiliate/products/{$product->id}/analyze")
            ->assertStatus(403);

        $this->actingAs($otherUser)
            ->post("/dashboard/affiliate/products/{$product->id}/status", ['approval_status' => 'approved'])
            ->assertStatus(403);

        $ownerAccount = SocialAccount::create([
            'user_id' => $owner->id,
            'platform' => 'tiktok',
            'handle' => '@plant.coaction',
            'status' => 'connected',
        ]);
        $this->actingAs($otherUser)
            ->post("/dashboard/tiktok/{$ownerAccount->id}/disconnect")
            ->assertStatus(403);

        // 14. AI Product Analysis via AIProviderManager (Google Gemini mock)
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode([
                            'relevansi_niche' => 'Sangat relevan untuk kolektor Anthurium dan aroid.',
                            'kesesuaian_audiens' => 'Cocok untuk audiens Plant Coaction yang sering repotting.',
                            'potensi_ide_konten' => ['Ciri media tanam aroid yang porous', 'Demo repotting Anthurium'],
                            'keunggulan' => ['Harga terjangkau (Rp45.000)'],
                            'kekurangan_atau_risiko' => ['Komisi dan rating belum tersedia dari sumber'],
                            'sudut_edukasi' => 'Pentingnya aerasi akar agar terhindar dari akar busuk.',
                            'ide_demonstrasi' => 'Uji siram air untuk menunjukkan drainase media.',
                            'potensi_storytelling' => 'Cerita perbandingan media padat vs porous.',
                            'informasi_belum_tersedia' => ['komisi_persen', 'rating', 'jumlah_ulasan'],
                            'rekomendasi_kelayakan' => 'pertimbangkan',
                            'alasan_rekomendasi' => 'Relevan dengan niche, namun perlu verifikasi rating & komisi.',
                            'faktor_penilaian' => [
                                'relevansi_niche_skor' => 95,
                                'nilai_edukasi_skor' => 90,
                                'kemudahan_demonstrasi_skor' => 85,
                                'kelengkapan_data_skor' => 50,
                                'catatan_transparansi' => 'Bukan prediksi penjualan.',
                            ],
                        ]),
                    ]]],
                ]],
            ], 200),
        ]);

        $this->actingAs($owner)
            ->post("/dashboard/affiliate/products/{$product->id}/analyze")
            ->assertRedirect('/dashboard/affiliate/products')
            ->assertSessionHas('status');

        $analyzed = $product->fresh();
        $this->assertSame('analyzed', $analyzed->approval_status);
        $this->assertNotNull($analyzed->analyzed_at);
        $this->assertSame(82.75, (float) $analyzed->quality_score);
        $this->assertStringContainsString('bukan prediksi penjualan', strtolower((string) data_get($analyzed->analysis_score_breakdown, 'disclaimer')));

        // 15 & 17. Product Approval Workflow + Activity Log
        $this->actingAs($owner)
            ->post("/dashboard/affiliate/products/{$product->id}/status", [
                'approval_status' => 'approved',
                'approval_notes' => 'Layak untuk konten edukasi repotting minggu depan.',
            ])
            ->assertRedirect();

        $approved = $product->fresh();
        $this->assertSame('approved', $approved->approval_status);
        $this->assertSame($owner->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame('Layak untuk konten edukasi repotting minggu depan.', $approved->approval_notes);

        $this->actingAs($owner)
            ->get('/dashboard/affiliate/products/approved')
            ->assertStatus(200)
            ->assertSee('Media Tanam Aroid Premium Mix 2L');

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'affiliate_analysis',
            'action' => 'product_analyzed',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'affiliate_approval',
            'action' => 'status_changed',
        ]);
    }
}
