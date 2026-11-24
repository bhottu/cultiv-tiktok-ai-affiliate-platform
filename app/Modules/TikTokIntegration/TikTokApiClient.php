<?php

namespace App\Modules\TikTokIntegration;

use App\Models\SocialAccount;
use App\Modules\ActivityLogs\ActivityLogger;
use Illuminate\Support\Facades\Http;

class TikTokApiClient
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * Determine allowed /v2/user/info/ fields strictly from granted OAuth scopes.
     *
     * @param  array<int, string>  $scopes
     * @return array<int, string>
     */
    public function allowedUserInfoFields(array $scopes): array
    {
        $fields = [];
        if (in_array('user.info.basic', $scopes, true)) {
            $fields = array_merge($fields, ['open_id', 'union_id', 'avatar_url', 'display_name']);
        }
        if (in_array('user.info.profile', $scopes, true)) {
            $fields = array_merge($fields, ['username', 'bio_description', 'profile_deep_link', 'is_verified']);
        }
        if (in_array('user.info.stats', $scopes, true)) {
            $fields = array_merge($fields, ['follower_count', 'following_count', 'likes_count', 'video_count']);
        }

        return array_values(array_unique($fields));
    }

    /**
     * Fetch official TikTok account info via Display API GET /v2/user/info/.
     *
     * @return array{ok: bool, status: string, data?: array<string, mixed>, message: string}
     */
    public function fetchAccountInfo(SocialAccount $account): array
    {
        $accessToken = $account->getDecryptedAccessToken();
        if (! $accessToken) {
            return [
                'ok' => false,
                'status' => 'not_connected',
                'message' => 'Access token TikTok tidak tersedia. Silakan hubungkan akun terlebih dahulu.',
            ];
        }

        if ($account->isTokenExpired()) {
            $account->update([
                'status' => 'token_expired',
                'last_error' => 'Access token TikTok telah kedaluwarsa. Lakukan refresh token atau hubungkan ulang akun.',
            ]);

            return [
                'ok' => false,
                'status' => 'token_expired',
                'message' => 'Access token TikTok telah kedaluwarsa. Silakan refresh token atau hubungkan ulang akun.',
            ];
        }

        $scopes = $account->scopes ?? ['user.info.basic'];
        $fields = $this->allowedUserInfoFields($scopes);
        if (empty($fields)) {
            return [
                'ok' => false,
                'status' => 'insufficient_scope',
                'message' => 'Tidak ada scope user.info.* yang diberikan untuk mengambil informasi profil TikTok.',
            ];
        }

        $baseUrl = rtrim((string) config('affiliate_ai.tiktok.api_base_url', 'https://open.tiktokapis.com/v2'), '/');

        try {
            $resp = Http::timeout(20)
                ->withToken($accessToken)
                ->get($baseUrl . '/user/info/', [
                    'fields' => implode(',', $fields),
                ]);

            if ($resp->status() === 401 || $resp->status() === 403) {
                $msg = 'Token TikTok tidak valid atau izin telah dicabut (HTTP ' . $resp->status() . '). Silakan hubungkan ulang akun.';
                $account->update([
                    'status' => 'revoked',
                    'last_error' => $msg,
                ]);
                $this->logger->log('tiktok_integration', 'fetch_account_unauthorized', $msg, 'warning', ['social_account_id' => $account->id]);

                return ['ok' => false, 'status' => 'revoked', 'message' => $msg];
            }

            if ($resp->status() === 429) {
                $msg = 'Batas rate limit TikTok Display API terlampaui (HTTP 429). Coba lagi beberapa menit lagi.';
                $account->update(['last_error' => $msg]);

                return ['ok' => false, 'status' => 'rate_limited', 'message' => $msg];
            }

            if ($resp->failed()) {
                $errMsg = $this->logger->redact((string) data_get($resp->json(), 'error.message', 'Gagal membaca profil TikTok.'));
                $msg = 'TikTok Display API mengembalikan error (HTTP ' . $resp->status() . '): ' . $errMsg;
                $account->update(['last_error' => $msg]);

                return ['ok' => false, 'status' => 'api_error', 'message' => $msg];
            }

            $apiErrorCode = (string) data_get($resp->json(), 'error.code', 'ok');
            if ($apiErrorCode !== '' && strtolower($apiErrorCode) !== 'ok') {
                $errMsg = $this->logger->redact((string) data_get($resp->json(), 'error.message', $apiErrorCode));
                $account->update(['last_error' => $errMsg]);

                return ['ok' => false, 'status' => 'api_error', 'message' => "TikTok API error ({$apiErrorCode}): {$errMsg}"];
            }

            $user = (array) data_get($resp->json(), 'data.user', []);

            $stats = [];
            foreach (['follower_count', 'following_count', 'likes_count', 'video_count', 'bio_description', 'profile_deep_link', 'is_verified', 'union_id'] as $key) {
                if (array_key_exists($key, $user)) {
                    $stats[$key] = $user[$key];
                }
            }

            $handle = ! empty($user['username'])
                ? '@' . ltrim((string) $user['username'], '@')
                : ($account->handle ?: '@plant.coaction');

            $account->update([
                'external_account_id' => $user['open_id'] ?? $account->external_account_id,
                'display_name' => $user['display_name'] ?? $account->display_name,
                'avatar_url' => $user['avatar_url'] ?? $account->avatar_url,
                'handle' => $handle,
                'profile_stats' => ! empty($stats) ? $stats : $account->profile_stats,
                'status' => 'connected',
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            $this->logger->log(
                'tiktok_integration',
                'sync_account_info',
                "Informasi akun TikTok {$handle} berhasil disinkronkan via Display API resmi.",
                'info',
                ['social_account_id' => $account->id, 'fields_requested' => $fields],
                SocialAccount::class,
                $account->id,
            );

            return [
                'ok' => true,
                'status' => 'connected',
                'data' => $user,
                'message' => 'Informasi akun TikTok berhasil diperbarui dari API resmi.',
            ];
        } catch (\Throwable $e) {
            $safeErr = 'Kesalahan jaringan saat menghubungi TikTok Display API: ' . $this->logger->redact($e->getMessage());
            $account->update(['last_error' => $safeErr]);
            $this->logger->log('tiktok_integration', 'sync_account_error', $safeErr, 'error', ['social_account_id' => $account->id]);

            return ['ok' => false, 'status' => 'network_error', 'message' => $safeErr];
        }
    }
}
