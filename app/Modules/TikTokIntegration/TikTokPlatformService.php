<?php

namespace App\Modules\TikTokIntegration;

use App\Contracts\SocialPlatformInterface;
use App\Models\SocialAccount;
use App\Models\User;

class TikTokPlatformService implements SocialPlatformInterface
{
    public function getPlatformSlug(): string
    {
        return 'tiktok';
    }

    public function isConfigured(): bool
    {
        return trim((string) config('affiliate_ai.tiktok.client_key', '')) !== ''
            && trim((string) config('affiliate_ai.tiktok.client_secret', '')) !== ''
            && trim((string) config('affiliate_ai.tiktok.redirect_uri', '')) !== '';
    }

    public function resolveAccountForUser(?User $user = null): ?SocialAccount
    {
        if (! $user) {
            return SocialAccount::where('platform', 'tiktok')->first();
        }

        return SocialAccount::where('platform', 'tiktok')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                if (in_array($user->role, ['owner', 'admin'], true)) {
                    $q->orWhereNull('user_id');
                }
            })
            ->orderByRaw('CASE WHEN user_id IS NOT NULL THEN 0 ELSE 1 END')
            ->first();
    }

    public function getAnalyticsAvailability(?SocialAccount $account): array
    {
        $isConnected = $account && $account->status === 'connected' && ! $account->isTokenExpired();
        $hasStatsScope = $account && $account->hasScope('user.info.stats');

        return [
            'basic_profile_stats_available' => $isConnected && $hasStatsScope && ! empty($account->profile_stats),
            'deep_studio_analytics_available' => false,
            'affiliate_commission_analytics_available' => false,
            'missing_requirements' => [
                'Statistik ringkas akun (followers, likes, video count) memerlukan koneksi OAuth aktif dengan scope user.info.stats.',
                'Analytics mendalam TikTok Studio (demografi audiens, waktu aktif, retention, average watch time) tidak tersedia melalui TikTok Display API standar.',
                'Analytics komisi, klik produk, dan GMV memerlukan persetujuan terpisah pada TikTok Shop Partner Center (Affiliate Creator API).',
            ],
        ];
    }

    public function getReadinessStatus(?User $user = null): array
    {
        $account = $this->resolveAccountForUser($user);

        return [
            'platform' => 'tiktok',
            'handle' => $account?->handle ?? '@plant.coaction',
            'account_status' => $account?->status ?? 'not_connected',
            'affiliate_status' => $account?->affiliate_status ?? 'unverified',
            'oauth_ready' => $this->isConfigured(),
            'affiliate_api_verified' => false,
            'phase_enabled' => true,
            'environment' => (string) config('affiliate_ai.tiktok.environment', 'sandbox'),
            'message' => $this->isConfigured()
                ? 'Kredensial TikTok Developer tersedia. Hubungkan akun melalui OAuth resmi pada menu Akun TikTok.'
                : 'Menunggu TIKTOK_CLIENT_KEY dan TIKTOK_CLIENT_SECRET di .env untuk mengaktifkan OAuth TikTok resmi.',
        ];
    }
}
