<?php

namespace App\Modules\TikTokIntegration;

use App\Models\SocialAccount;
use App\Models\User;
use App\Modules\ActivityLogs\ActivityLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TikTokOAuthService
{
    public const SESSION_STATE_KEY = 'tiktok_oauth_state';
    public const SESSION_STATE_EXPIRES_KEY = 'tiktok_oauth_state_expires_at';
    public const SESSION_USER_KEY = 'tiktok_oauth_user_id';
    public const SESSION_PLATFORM_KEY = 'tiktok_oauth_platform';
    public const SESSION_CODE_VERIFIER_KEY = 'tiktok_oauth_code_verifier';

    private const PKCE_UNRESERVED_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';

    public function __construct(
        private readonly TikTokApiClient $apiClient,
        private readonly ActivityLogger $logger,
    ) {}

    public function isConfigured(): bool
    {
        return trim((string) config('affiliate_ai.tiktok.client_key', '')) !== ''
            && trim((string) config('affiliate_ai.tiktok.client_secret', '')) !== ''
            && trim((string) config('affiliate_ai.tiktok.redirect_uri', '')) !== '';
    }

    /**
     * Resolve configured TikTok Login Kit platform ('web' default for Laravel web apps, or 'desktop').
     */
    public function getOAuthPlatform(): string
    {
        $platform = strtolower(trim((string) config('affiliate_ai.tiktok.oauth_platform', 'web')));

        return in_array($platform, ['web', 'desktop'], true) ? $platform : 'web';
    }

    public function usesPkce(?string $platform = null): bool
    {
        return ($platform ?? $this->getOAuthPlatform()) === 'desktop';
    }

    /**
     * Generate a cryptographically random RFC 7636 unreserved PKCE code_verifier (43-128 chars).
     */
    public function generateCodeVerifier(int $length = 64): string
    {
        $length = max(43, min(128, $length));
        $chars = self::PKCE_UNRESERVED_CHARS;
        $maxIdx = strlen($chars) - 1;
        $verifier = '';

        for ($i = 0; $i < $length; $i++) {
            $verifier .= $chars[random_int(0, $maxIdx)];
        }

        return $verifier;
    }

    /**
     * TikTok Desktop PKCE S256 uses Hex-encoded SHA-256 of code_verifier per TikTok Login Kit Desktop docs.
     */
    public function generateCodeChallenge(string $codeVerifier): string
    {
        return hash('sha256', $codeVerifier);
    }

    /**
     * Normalize comma-separated OAuth scopes: trim whitespace, drop empties, deduplicate.
     * Never injects extra scopes automatically.
     */
    public function normalizeScopes(?string $raw): string
    {
        $parts = array_map(
            static fn (string $s): string => trim($s),
            explode(',', (string) $raw),
        );
        $clean = array_values(array_unique(array_filter($parts, static fn (string $s): bool => $s !== '')));

        return ! empty($clean) ? implode(',', $clean) : 'user.info.basic';
    }

    /**
     * Build official TikTok OAuth v2 authorization URL with cryptographically random CSRF state.
     * - Web OAuth (default): client_key, response_type=code, scope, redirect_uri, state (NO PKCE).
     * - Desktop OAuth: adds per-request PKCE code_challenge (hex SHA-256) and code_challenge_method=S256.
     *
     * @return array{ok: bool, url?: string, state?: string, oauth_platform?: string, pkce_used?: bool, message: string}
     */
    public function createAuthorizationUrl(User $user): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Kredensial TikTok Developer (TIKTOK_CLIENT_KEY, TIKTOK_CLIENT_SECRET, TIKTOK_REDIRECT_URI) belum dikonfigurasi di .env.',
            ];
        }

        $platform = $this->getOAuthPlatform();
        $usesPkce = $this->usesPkce($platform);
        $authBase = (string) config('affiliate_ai.tiktok.auth_base_url', 'https://www.tiktok.com/v2/auth/authorize/');
        $redirectUri = trim((string) config('affiliate_ai.tiktok.redirect_uri', ''));
        $scopes = $this->normalizeScopes((string) config('affiliate_ai.tiktok.scopes', 'user.info.basic'));

        $state = Str::random(48);
        $sessionData = [
            self::SESSION_STATE_KEY => $state,
            self::SESSION_STATE_EXPIRES_KEY => now()->addMinutes(10)->timestamp,
            self::SESSION_USER_KEY => $user->id,
            self::SESSION_PLATFORM_KEY => $platform,
        ];

        $params = [
            'client_key' => trim((string) config('affiliate_ai.tiktok.client_key')),
            'response_type' => 'code',
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ];

        if ($usesPkce) {
            $codeVerifier = $this->generateCodeVerifier(64);
            $codeChallenge = $this->generateCodeChallenge($codeVerifier);
            $sessionData[self::SESSION_CODE_VERIFIER_KEY] = $codeVerifier;
            $params['code_challenge'] = $codeChallenge;
            $params['code_challenge_method'] = 'S256';
        } else {
            session()->forget(self::SESSION_CODE_VERIFIER_KEY);
        }

        session($sessionData);

        // Diagnostic logging aman: tidak pernah mencatat client_secret, code_verifier, atau token
        $safeParamsMeta = [
            'response_type' => $params['response_type'],
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
            'state_present' => true,
            'code_challenge_present' => isset($params['code_challenge']),
            'code_challenge_method' => $params['code_challenge_method'] ?? null,
        ];

        $isHttpsRedirect = str_starts_with(strtolower($redirectUri), 'https://');
        $logLevel = ($platform === 'web' && ! $isHttpsRedirect) ? 'warning' : 'info';

        $this->logger->log(
            'tiktok_oauth',
            'authorize_initiated',
            "Pengguna memulai alur TikTok OAuth v2 ({$platform}" . ($usesPkce ? ', PKCE S256' : ', tanpa PKCE') . ').',
            $logLevel,
            [
                'oauth_platform' => $platform,
                'authorization_endpoint' => $authBase,
                'redirect_uri' => $redirectUri,
                'redirect_uri_is_https' => $isHttpsRedirect,
                'scopes' => $scopes,
                'pkce_used' => $usesPkce,
                'parameters_sent' => array_keys($params),
                'parameters_summary' => $safeParamsMeta,
            ],
            null,
            null,
            $user->id,
        );

        return [
            'ok' => true,
            'url' => rtrim($authBase, '?') . '?' . http_build_query($params),
            'state' => $state,
            'oauth_platform' => $platform,
            'pkce_used' => $usesPkce,
            'message' => 'URL otorisasi TikTok berhasil dibuat.',
        ];
    }

    public function validateState(User $user, ?string $incomingState): bool
    {
        $expectedState = (string) session()->pull(self::SESSION_STATE_KEY, '');
        $expiresAt = (int) session()->pull(self::SESSION_STATE_EXPIRES_KEY, 0);
        $expectedUserId = (int) session()->pull(self::SESSION_USER_KEY, 0);

        if ($expectedState === '' || ! $incomingState) {
            return false;
        }

        if ($expiresAt < now()->timestamp) {
            return false;
        }

        if ($expectedUserId !== $user->id) {
            return false;
        }

        return hash_equals($expectedState, $incomingState);
    }

    /**
     * Handle TikTok OAuth callback: verify state, exchange code (with PKCE verifier only for desktop),
     * encrypt tokens, store account identity, and fetch profile.
     *
     * @return array{ok: bool, account?: SocialAccount, message: string}
     */
    public function handleCallback(
        User $user,
        ?string $code,
        ?string $state,
        ?string $error = null,
        ?string $errorDescription = null,
    ): array {
        $sessionPlatform = (string) session()->pull(self::SESSION_PLATFORM_KEY, $this->getOAuthPlatform());
        $codeVerifier = (string) session()->pull(self::SESSION_CODE_VERIFIER_KEY, '');
        $usesPkce = $this->usesPkce($sessionPlatform);

        if (! $this->validateState($user, $state)) {
            $msg = 'Validasi OAuth state tidak valid atau telah kedaluwarsa (proteksi CSRF). Silakan ulangi koneksi TikTok.';
            $this->logger->log(
                'tiktok_oauth',
                'callback_invalid_state',
                $msg,
                'warning',
                ['oauth_platform' => $sessionPlatform, 'pkce_used' => $usesPkce],
                null,
                null,
                $user->id,
            );

            return ['ok' => false, 'message' => $msg];
        }

        if ($error) {
            $safeErr = $this->logger->redact((string) $error);
            $safeDesc = $this->logger->redact((string) ($errorDescription ?: $error));
            $msg = "Otorisasi TikTok dibatalkan atau ditolak ({$safeErr}): {$safeDesc}";
            $this->logger->log(
                'tiktok_oauth',
                'callback_denied',
                $msg,
                'warning',
                [
                    'oauth_platform' => $sessionPlatform,
                    'pkce_used' => $usesPkce,
                    'tiktok_error_code' => $safeErr,
                ],
                null,
                null,
                $user->id,
            );

            return ['ok' => false, 'message' => $msg];
        }

        if (! $code) {
            return ['ok' => false, 'message' => 'Kode otorisasi TikTok tidak ditemukan pada callback.'];
        }

        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Kredensial TikTok Developer belum dikonfigurasi.'];
        }

        if ($usesPkce && $codeVerifier === '') {
            $msg = 'Sesi PKCE code_verifier tidak ditemukan atau sudah kedaluwarsa. Silakan ulangi koneksi TikTok.';
            $this->logger->log(
                'tiktok_oauth',
                'callback_missing_pkce_verifier',
                $msg,
                'warning',
                ['oauth_platform' => $sessionPlatform, 'pkce_used' => true],
                null,
                null,
                $user->id,
            );

            return ['ok' => false, 'message' => $msg];
        }

        $apiBase = rtrim((string) config('affiliate_ai.tiktok.api_base_url', 'https://open.tiktokapis.com/v2'), '/');
        $redirectUri = trim((string) config('affiliate_ai.tiktok.redirect_uri', ''));

        $tokenBody = [
            'client_key' => trim((string) config('affiliate_ai.tiktok.client_key')),
            'client_secret' => trim((string) config('affiliate_ai.tiktok.client_secret')),
            'code' => urldecode($code),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ];

        if ($usesPkce) {
            $tokenBody['code_verifier'] = $codeVerifier;
        }

        try {
            $resp = Http::timeout(25)
                ->asForm()
                ->post($apiBase . '/oauth/token/', $tokenBody);

            $payload = $resp->json() ?? [];
            $logId = (string) ($payload['log_id'] ?? data_get($payload, 'error.log_id') ?? $resp->header('x-tt-logid') ?? '');

            if ($resp->failed() || empty($payload['access_token'])) {
                $errCode = (string) ($payload['error'] ?? data_get($payload, 'error.code') ?? 'token_exchange_error');
                $errDescRaw = (string) ($payload['error_description'] ?? data_get($payload, 'error.message') ?? $errCode);
                $errDesc = $this->logger->redact($errDescRaw);
                $msg = 'Penukaran token OAuth TikTok gagal (HTTP ' . $resp->status() . '): ' . $errDesc;

                $this->logger->log(
                    'tiktok_oauth',
                    'token_exchange_failed',
                    $msg,
                    'error',
                    [
                        'oauth_platform' => $sessionPlatform,
                        'pkce_used' => $usesPkce,
                        'redirect_uri' => $redirectUri,
                        'http_status' => $resp->status(),
                        'exchange_status' => 'failed',
                        'tiktok_error_code' => $this->logger->redact($errCode),
                        'tiktok_log_id' => $logId !== '' ? $logId : null,
                    ],
                    null,
                    null,
                    $user->id,
                );

                return ['ok' => false, 'message' => $msg];
            }

            $scopes = array_values(array_filter(array_map('trim', explode(',', (string) ($payload['scope'] ?? '')))));
            $expiresIn = max(60, (int) ($payload['expires_in'] ?? 86400));
            $refreshExpiresIn = ! empty($payload['refresh_expires_in']) ? (int) $payload['refresh_expires_in'] : null;

            $account = SocialAccount::where('platform', 'tiktok')
                ->where(function ($q) use ($user, $payload) {
                    $q->where('user_id', $user->id);
                    if (! empty($payload['open_id'])) {
                        $q->orWhere('external_account_id', (string) $payload['open_id']);
                    }
                    $q->orWhere(function ($q2) {
                        $q2->whereNull('user_id')->where('handle', '@plant.coaction');
                    });
                })
                ->first();

            if (! $account) {
                $account = new SocialAccount([
                    'platform' => 'tiktok',
                    'handle' => '@plant.coaction',
                ]);
            }

            $account->user_id = $user->id;
            $account->external_account_id = (string) ($payload['open_id'] ?? $account->external_account_id);
            $account->status = 'connected';
            $account->scopes = $scopes;
            $account->token_expires_at = now()->addSeconds($expiresIn);
            $account->refresh_token_expires_at = $refreshExpiresIn ? now()->addSeconds($refreshExpiresIn) : null;
            $account->connected_at = now();
            $account->disconnected_at = null;
            $account->last_error = null;
            $account->oauth_metadata = [
                'token_type' => $payload['token_type'] ?? 'Bearer',
                'oauth_platform' => $sessionPlatform,
                'pkce_used' => $usesPkce,
                'environment' => config('affiliate_ai.tiktok.environment', 'sandbox'),
            ];
            $account->setEncryptedTokens(
                (string) $payload['access_token'],
                isset($payload['refresh_token']) ? (string) $payload['refresh_token'] : null,
            );
            $account->save();

            $this->logger->log(
                'tiktok_oauth',
                'account_connected',
                "Akun TikTok berhasil dihubungkan melalui OAuth v2 ({$sessionPlatform}, token disimpan terenkripsi).",
                'info',
                [
                    'social_account_id' => $account->id,
                    'oauth_platform' => $sessionPlatform,
                    'pkce_used' => $usesPkce,
                    'redirect_uri' => $redirectUri,
                    'exchange_status' => 'success',
                    'http_status' => $resp->status(),
                    'tiktok_log_id' => $logId !== '' ? $logId : null,
                    'scopes' => $scopes,
                ],
                SocialAccount::class,
                $account->id,
                $user->id,
            );

            // Ambil informasi akun sesuai scope yang benar-benar diberikan
            $this->apiClient->fetchAccountInfo($account);

            return [
                'ok' => true,
                'account' => $account->fresh(),
                'message' => 'Akun TikTok berhasil dihubungkan melalui OAuth resmi.',
            ];
        } catch (\Throwable $e) {
            $msg = 'Terjadi kesalahan saat memproses callback OAuth TikTok: ' . $this->logger->redact($e->getMessage());
            $this->logger->log(
                'tiktok_oauth',
                'callback_exception',
                $msg,
                'error',
                ['oauth_platform' => $sessionPlatform, 'pkce_used' => $usesPkce],
                null,
                null,
                $user->id,
            );

            return ['ok' => false, 'message' => $msg];
        }
    }

    /**
     * Refresh TikTok access token with refresh-token rotation support.
     *
     * @return array{ok: bool, status: string, message: string}
     */
    public function refreshToken(SocialAccount $account): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'status' => 'not_configured', 'message' => 'Kredensial TikTok Developer belum dikonfigurasi.'];
        }

        $refreshToken = $account->getDecryptedRefreshToken();
        if (! $refreshToken || $account->isRefreshTokenExpired()) {
            $account->update([
                'status' => 'token_expired',
                'last_error' => 'Refresh token tidak tersedia atau sudah kedaluwarsa. Silakan hubungkan ulang akun TikTok.',
            ]);

            return ['ok' => false, 'status' => 'token_expired', 'message' => 'Refresh token kedaluwarsa. Silakan hubungkan ulang akun TikTok.'];
        }

        $apiBase = rtrim((string) config('affiliate_ai.tiktok.api_base_url', 'https://open.tiktokapis.com/v2'), '/');

        try {
            $resp = Http::timeout(25)
                ->asForm()
                ->post($apiBase . '/oauth/token/', [
                    'client_key' => trim((string) config('affiliate_ai.tiktok.client_key')),
                    'client_secret' => trim((string) config('affiliate_ai.tiktok.client_secret')),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);

            $payload = $resp->json() ?? [];
            $logId = (string) ($payload['log_id'] ?? data_get($payload, 'error.log_id') ?? $resp->header('x-tt-logid') ?? '');

            if ($resp->failed() || empty($payload['access_token'])) {
                $errDesc = $this->logger->redact((string) ($payload['error_description'] ?? $payload['error'] ?? 'Refresh token ditolak.'));
                $account->clearTokens();
                $account->status = 'revoked';
                $account->last_error = "Refresh token gagal: {$errDesc}";
                $account->save();

                $this->logger->log(
                    'tiktok_oauth',
                    'refresh_failed',
                    $account->last_error,
                    'warning',
                    ['social_account_id' => $account->id, 'http_status' => $resp->status(), 'tiktok_log_id' => $logId !== '' ? $logId : null],
                    SocialAccount::class,
                    $account->id,
                );

                return ['ok' => false, 'status' => 'revoked', 'message' => 'Refresh token gagal atau telah dicabut. Silakan hubungkan ulang akun TikTok.'];
            }

            $expiresIn = max(60, (int) ($payload['expires_in'] ?? 86400));
            $refreshExpiresIn = ! empty($payload['refresh_expires_in']) ? (int) $payload['refresh_expires_in'] : null;

            $account->setEncryptedTokens(
                (string) $payload['access_token'],
                isset($payload['refresh_token']) ? (string) $payload['refresh_token'] : null,
            );
            $account->token_expires_at = now()->addSeconds($expiresIn);
            if ($refreshExpiresIn) {
                $account->refresh_token_expires_at = now()->addSeconds($refreshExpiresIn);
            }
            if (! empty($payload['scope'])) {
                $account->scopes = array_values(array_filter(array_map('trim', explode(',', (string) $payload['scope']))));
            }
            $account->status = 'connected';
            $account->last_error = null;
            $account->save();

            $this->logger->log(
                'tiktok_oauth',
                'token_refreshed',
                'Access token TikTok berhasil diperbarui (rotasi token terenkripsi).',
                'info',
                ['social_account_id' => $account->id, 'tiktok_log_id' => $logId !== '' ? $logId : null],
                SocialAccount::class,
                $account->id,
            );

            return ['ok' => true, 'status' => 'connected', 'message' => 'Access token TikTok berhasil diperbarui.'];
        } catch (\Throwable $e) {
            $safe = 'Gagal memperbarui token TikTok: ' . $this->logger->redact($e->getMessage());
            $account->update(['last_error' => $safe]);

            return ['ok' => false, 'status' => 'network_error', 'message' => $safe];
        }
    }

    /**
     * Securely revoke TikTok token and disconnect account locally.
     *
     * @return array{ok: bool, message: string}
     */
    public function disconnect(SocialAccount $account): array
    {
        $token = $account->getDecryptedAccessToken() ?: $account->getDecryptedRefreshToken();

        if ($token && $this->isConfigured()) {
            $apiBase = rtrim((string) config('affiliate_ai.tiktok.api_base_url', 'https://open.tiktokapis.com/v2'), '/');
            try {
                Http::timeout(15)->asForm()->post($apiBase . '/oauth/revoke/', [
                    'client_key' => trim((string) config('affiliate_ai.tiktok.client_key')),
                    'client_secret' => trim((string) config('affiliate_ai.tiktok.client_secret')),
                    'token' => $token,
                ]);
            } catch (\Throwable) {
                // Tetap lanjutkan pembersihan token lokal agar disconnect aman
            }
        }

        $account->clearTokens();
        $account->status = 'disconnected';
        $account->scopes = [];
        $account->disconnected_at = now();
        $account->last_error = null;
        $account->save();

        $this->logger->log('tiktok_oauth', 'account_disconnected', "Koneksi akun TikTok {$account->handle} telah diputus dan token lokal dihapus.", 'info', ['social_account_id' => $account->id], SocialAccount::class, $account->id);

        return ['ok' => true, 'message' => 'Akun TikTok berhasil diputus dan seluruh token lokal telah dihapus.'];
    }
}
