<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\SocialAccount;
use App\Models\User;
use App\Modules\TikTokIntegration\TikTokOAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TikTokOAuthFixTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'name' => 'Owner TikTok OAuth',
            'email' => 'oauth-owner@plantcoaction.test',
            'password' => bcrypt('SecretPass123!'),
            'role' => 'owner',
            'is_active' => true,
        ]);
    }

    private function setTikTokConfig(string $platform = 'web', string $redirectUri = 'https://example-app.ngrok-free.app/dashboard/tiktok/callback'): void
    {
        config()->set('affiliate_ai.tiktok.oauth_platform', $platform);
        config()->set('affiliate_ai.tiktok.client_key', 'sbaw_test_client_key_999');
        config()->set('affiliate_ai.tiktok.client_secret', 'super_secret_tiktok_client_secret_xyz');
        config()->set('affiliate_ai.tiktok.redirect_uri', $redirectUri);
        config()->set('affiliate_ai.tiktok.scopes', 'user.info.basic,user.info.profile,user.info.stats');
        config()->set('affiliate_ai.tiktok.auth_base_url', 'https://www.tiktok.com/v2/auth/authorize/');
        config()->set('affiliate_ai.tiktok.api_base_url', 'https://open.tiktokapis.com/v2');
    }

    public function test_1_web_oauth_authorization_url_sends_required_params_without_pkce_and_exchanges_token(): void
    {
        $this->setTikTokConfig('web', 'https://example-app.ngrok-free.app/dashboard/tiktok/callback');

        $resp = $this->actingAs($this->owner)->post('/dashboard/tiktok/connect');
        $resp->assertRedirect();

        $location = (string) $resp->headers->get('Location');
        $this->assertStringStartsWith('https://www.tiktok.com/v2/auth/authorize/?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);
        $this->assertSame('sbaw_test_client_key_999', $params['client_key'] ?? null);
        $this->assertSame('code', $params['response_type'] ?? null);
        $this->assertSame('user.info.basic,user.info.profile,user.info.stats', $params['scope'] ?? null);
        $this->assertSame('https://example-app.ngrok-free.app/dashboard/tiktok/callback', $params['redirect_uri'] ?? null);
        $this->assertNotEmpty($params['state'] ?? '');
        // Web OAuth MUST NOT send Desktop PKCE parameters
        $this->assertArrayNotHasKey('code_challenge', $params);
        $this->assertArrayNotHasKey('code_challenge_method', $params);
        $this->assertNull(session(TikTokOAuthService::SESSION_CODE_VERIFIER_KEY));

        $rawCode = 'auth_code_web_secret_777';
        $rawAccess = 'act.web_access_token_secret_888';
        $rawRefresh = 'rft.web_refresh_token_secret_999';

        Http::fake([
            'https://open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'open_id' => 'open_id_web_001',
                'scope' => 'user.info.basic,user.info.profile,user.info.stats',
                'access_token' => $rawAccess,
                'refresh_token' => $rawRefresh,
                'expires_in' => 86400,
                'refresh_expires_in' => 31536000,
                'token_type' => 'Bearer',
                'log_id' => '20261122WEBTESTLOGID01',
            ], 200),
            'https://open.tiktokapis.com/v2/user/info/*' => Http::response([
                'data' => [
                    'user' => [
                        'open_id' => 'open_id_web_001',
                        'display_name' => 'Plant Coaction Web',
                        'username' => 'plant.coaction',
                    ],
                ],
                'error' => ['code' => 'ok', 'message' => ''],
            ], 200),
        ]);

        $this->actingAs($this->owner)
            ->get('/dashboard/tiktok/callback?code=' . urlencode($rawCode) . '&state=' . urlencode($params['state']))
            ->assertRedirect('/dashboard/tiktok')
            ->assertSessionHas('status');

        Http::assertSent(function ($req) use ($rawCode) {
            if (! str_contains($req->url(), '/oauth/token/')) {
                return false;
            }
            $data = $req->data();

            return ($data['grant_type'] ?? null) === 'authorization_code'
                && ($data['code'] ?? null) === $rawCode
                && ($data['redirect_uri'] ?? null) === 'https://example-app.ngrok-free.app/dashboard/tiktok/callback'
                && ! array_key_exists('code_verifier', $data);
        });

        // Verify encryption and safe diagnostic logging (ZERO secrets in logs!)
        $row = DB::table('social_accounts')->where('external_account_id', 'open_id_web_001')->first();
        $this->assertNotNull($row);
        $this->assertNotSame($rawAccess, $row->access_token_encrypted);
        $account = SocialAccount::findOrFail($row->id);
        $this->assertSame($rawAccess, $account->getDecryptedAccessToken());
        $this->assertSame($rawRefresh, $account->getDecryptedRefreshToken());

        $initLog = ActivityLog::where('action', 'authorize_initiated')->latest('id')->firstOrFail();
        $this->assertSame('web', $initLog->context['oauth_platform']);
        $this->assertFalse($initLog->context['pkce_used']);

        $connLog = ActivityLog::where('action', 'account_connected')->latest('id')->firstOrFail();
        $this->assertSame('success', $connLog->context['exchange_status']);
        $this->assertSame('20261122WEBTESTLOGID01', $connLog->context['tiktok_log_id']);

        foreach (ActivityLog::all() as $log) {
            $blob = $log->description . ' ' . json_encode($log->context);
            $this->assertStringNotContainsString('super_secret_tiktok_client_secret_xyz', $blob);
            $this->assertStringNotContainsString($rawCode, $blob);
            $this->assertStringNotContainsString($rawAccess, $blob);
            $this->assertStringNotContainsString($rawRefresh, $blob);
        }
    }

    public function test_2_desktop_oauth_generates_unique_pkce_hex_sha256_and_sends_verifier_on_token_exchange(): void
    {
        $this->setTikTokConfig('desktop', 'http://127.0.0.1:8001/dashboard/tiktok/callback');

        // First request -> unique verifier #1
        $resp1 = $this->actingAs($this->owner)->post('/dashboard/tiktok/connect');
        $resp1->assertRedirect();
        $verifier1 = (string) session(TikTokOAuthService::SESSION_CODE_VERIFIER_KEY);
        $this->assertGreaterThanOrEqual(43, strlen($verifier1));

        // Second request -> unique verifier #2 (NOT static)
        $resp2 = $this->actingAs($this->owner)->post('/dashboard/tiktok/connect');
        $resp2->assertRedirect();
        $verifier2 = (string) session(TikTokOAuthService::SESSION_CODE_VERIFIER_KEY);
        $this->assertNotSame($verifier1, $verifier2);

        $location = (string) $resp2->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

        // TikTok Desktop PKCE requires Hex-encoded SHA-256 + S256
        $this->assertSame('S256', $params['code_challenge_method'] ?? null);
        $this->assertSame(hash('sha256', $verifier2), $params['code_challenge'] ?? null);

        Http::fake([
            'https://open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'open_id' => 'open_id_desktop_001',
                'scope' => 'user.info.basic',
                'access_token' => 'act.desktop_secret_111',
                'refresh_token' => 'rft.desktop_secret_222',
                'expires_in' => 86400,
                'token_type' => 'Bearer',
            ], 200),
            'https://open.tiktokapis.com/v2/user/info/*' => Http::response([
                'data' => ['user' => ['open_id' => 'open_id_desktop_001', 'display_name' => 'Desktop Test']],
                'error' => ['code' => 'ok', 'message' => ''],
            ], 200),
        ]);

        $this->actingAs($this->owner)
            ->get('/dashboard/tiktok/callback?code=desktop_auth_code_123&state=' . urlencode($params['state']))
            ->assertRedirect('/dashboard/tiktok')
            ->assertSessionHas('status');

        Http::assertSent(function ($req) use ($verifier2) {
            if (! str_contains($req->url(), '/oauth/token/')) {
                return false;
            }
            $data = $req->data();

            return ($data['code_verifier'] ?? null) === $verifier2
                && ($data['grant_type'] ?? null) === 'authorization_code';
        });

        // Verifier must never be logged!
        foreach (ActivityLog::all() as $log) {
            $blob = $log->description . ' ' . json_encode($log->context);
            $this->assertStringNotContainsString($verifier1, $blob);
            $this->assertStringNotContainsString($verifier2, $blob);
        }
    }

    public function test_3_state_validation_tiktok_error_response_and_invalid_authorization_code(): void
    {
        $this->setTikTokConfig('web', 'https://example-app.ngrok-free.app/dashboard/tiktok/callback');

        // A. Invalid state rejected with zero HTTP calls
        Http::fake();
        $this->actingAs($this->owner)
            ->withSession([
                TikTokOAuthService::SESSION_STATE_KEY => 'expected_state_abc',
                TikTokOAuthService::SESSION_STATE_EXPIRES_KEY => now()->addMinutes(5)->timestamp,
                TikTokOAuthService::SESSION_USER_KEY => $this->owner->id,
            ])
            ->get('/dashboard/tiktok/callback?code=some_code&state=wrong_state')
            ->assertRedirect('/dashboard/tiktok')
            ->assertSessionHasErrors('tiktok_oauth');
        Http::assertNothingSent();

        // B. TikTok error response on callback (access_denied)
        $this->actingAs($this->owner)
            ->withSession([
                TikTokOAuthService::SESSION_STATE_KEY => 'expected_state_err',
                TikTokOAuthService::SESSION_STATE_EXPIRES_KEY => now()->addMinutes(5)->timestamp,
                TikTokOAuthService::SESSION_USER_KEY => $this->owner->id,
            ])
            ->get('/dashboard/tiktok/callback?error=access_denied&error_description=User+cancelled+login&state=expected_state_err')
            ->assertRedirect('/dashboard/tiktok')
            ->assertSessionHasErrors('tiktok_oauth');
        Http::assertNothingSent();

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'tiktok_oauth',
            'action' => 'callback_denied',
        ]);
    }

    public function test_4_invalid_authorization_code_logs_tiktok_error_and_log_id_safely(): void
    {
        $this->setTikTokConfig('web', 'https://example-app.ngrok-free.app/dashboard/tiktok/callback');

        Http::fake([
            'https://open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Authorization code is expired or invalid.',
                'log_id' => '20261122ERRINVALIDGRANT99',
            ], 400),
        ]);

        $this->actingAs($this->owner)
            ->withSession([
                TikTokOAuthService::SESSION_STATE_KEY => 'valid_state_invalid_code',
                TikTokOAuthService::SESSION_STATE_EXPIRES_KEY => now()->addMinutes(5)->timestamp,
                TikTokOAuthService::SESSION_USER_KEY => $this->owner->id,
                TikTokOAuthService::SESSION_PLATFORM_KEY => 'web',
            ])
            ->get('/dashboard/tiktok/callback?code=expired_bad_code_999&state=valid_state_invalid_code')
            ->assertRedirect('/dashboard/tiktok')
            ->assertSessionHasErrors('tiktok_oauth');

        $failLog = ActivityLog::where('action', 'token_exchange_failed')->latest('id')->firstOrFail();
        $this->assertSame('invalid_grant', $failLog->context['tiktok_error_code']);
        $this->assertSame('20261122ERRINVALIDGRANT99', $failLog->context['tiktok_log_id']);
        $this->assertSame(400, $failLog->context['http_status']);
        $this->assertStringNotContainsString('expired_bad_code_999', json_encode($failLog->context));
        $this->assertStringNotContainsString('super_secret_tiktok_client_secret_xyz', json_encode($failLog->context));
    }

    public function test_5_basic_scope_user_info_basic_authorization_url_format_and_no_extra_scopes_or_credential_leak(): void
    {
        $this->setTikTokConfig('web', 'https://example-app.ngrok-free.app/dashboard/tiktok/callback');
        config()->set('affiliate_ai.tiktok.scopes', 'user.info.basic');

        $resp = $this->actingAs($this->owner)->post('/dashboard/tiktok/connect');
        $resp->assertRedirect();

        $location = (string) $resp->headers->get('Location');
        $this->assertStringStartsWith('https://www.tiktok.com/v2/auth/authorize/?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

        // Exact parameter verification after URL decoding
        $this->assertSame('sbaw_test_client_key_999', $params['client_key'] ?? null);
        $this->assertSame('code', $params['response_type'] ?? null);
        $this->assertSame('user.info.basic', $params['scope'] ?? null);
        $this->assertSame('https://example-app.ngrok-free.app/dashboard/tiktok/callback', $params['redirect_uri'] ?? null);
        $this->assertNotEmpty($params['state'] ?? '');
        $this->assertSame($params['state'], session(TikTokOAuthService::SESSION_STATE_KEY));

        // No unintended extra scopes or PKCE params on Web OAuth
        $this->assertStringNotContainsString('user.info.profile', (string) ($params['scope'] ?? ''));
        $this->assertStringNotContainsString('user.info.stats', (string) ($params['scope'] ?? ''));
        $this->assertStringNotContainsString('video.list', (string) ($params['scope'] ?? ''));
        $this->assertArrayNotHasKey('code_challenge', $params);
        $this->assertArrayNotHasKey('code_challenge_method', $params);

        // No credential leak in redirect URL or activity logs
        $this->assertStringNotContainsString('super_secret_tiktok_client_secret_xyz', $location);
        $initLog = ActivityLog::where('action', 'authorize_initiated')->latest('id')->firstOrFail();
        $this->assertSame('user.info.basic', $initLog->context['scopes']);
        $this->assertStringNotContainsString('super_secret_tiktok_client_secret_xyz', json_encode($initLog->context));

        // Display API field resolver degrades gracefully to basic fields only
        $apiClient = app(\App\Modules\TikTokIntegration\TikTokApiClient::class);
        $fields = $apiClient->allowedUserInfoFields(['user.info.basic']);
        $this->assertSame(['open_id', 'union_id', 'avatar_url', 'display_name'], $fields);

        // Normalization & deduplication without auto-adding extra scopes
        $oauthService = app(TikTokOAuthService::class);
        $this->assertSame('user.info.basic', $oauthService->normalizeScopes('  user.info.basic , , user.info.basic  '));
        $this->assertSame('user.info.basic', $oauthService->normalizeScopes(''));
        $this->assertSame('user.info.basic,user.info.profile', $oauthService->normalizeScopes('user.info.basic, user.info.profile, user.info.basic'));
    }
}
