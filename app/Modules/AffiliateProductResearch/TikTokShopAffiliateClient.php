<?php

namespace App\Modules\AffiliateProductResearch;

use App\Enums\ProductApprovalStatus;
use App\Models\AffiliateProduct;
use App\Models\ProductResearchLog;
use App\Models\SocialAccount;
use App\Models\User;
use App\Modules\ActivityLogs\ActivityLogger;
use Illuminate\Support\Facades\Http;

class TikTokShopAffiliateClient
{
    public const PLANT_COACTION_NICHES = [
        'Tanaman Hias',
        'Anthurium',
        'Aroid',
        'Media Tanam',
        'Pupuk Tanaman',
        'Peralatan Berkebun',
        'Pot & Perlengkapan Tanaman',
        'Greenhouse',
        'Perawatan Tanaman',
        'Peralatan Fotografi & Konten Tanaman',
    ];

    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function isApiConfigured(): bool
    {
        return trim((string) config('affiliate_ai.tiktok.shop_affiliate.app_key', '')) !== ''
            && trim((string) config('affiliate_ai.tiktok.shop_affiliate.app_secret', '')) !== ''
            && trim((string) config('affiliate_ai.tiktok.shop_affiliate.access_token', '')) !== '';
    }

    /**
     * Build honest TikTok Shop Affiliate readiness assessment (never infers eligibility by follower count).
     */
    public function getReadinessAssessment(?SocialAccount $account): array
    {
        $tiktokConnected = $account && $account->status === 'connected' && ! $account->isTokenExpired();
        $shopApiConfigured = $this->isApiConfigured();

        return [
            'affiliate_program_status' => 'unverified_via_api',
            'affiliate_program_message' => 'Belum dapat diverifikasi melalui integrasi API. Silakan periksa langsung melalui TikTok Shop Creator Center.',
            'creator_center_status' => 'manual_verification_required',
            'tiktok_account_connected' => $tiktokConnected,
            'tiktok_account_handle' => $account?->handle ?? '@plant.coaction',
            'tiktok_account_status' => $account?->status ?? 'not_connected',
            'affiliate_product_api_ready' => $shopApiConfigured,
            'affiliate_product_api_message' => $shopApiConfigured
                ? 'Kredensial TikTok Shop Affiliate API terdeteksi di konfigurasi.'
                : 'Menunggu akses TikTok Shop Affiliate API (pendaftaran terpisah di TikTok Shop Partner Center).',
            'required_permissions' => [
                'TikTok for Developers (Login Kit & Display API): user.info.basic, user.info.profile, user.info.stats',
                'TikTok Shop Partner Center (Affiliate Creator API): persetujuan aplikasi + izin Read Creator Affiliate Collaborations',
            ],
            'unmet_requirements' => array_values(array_filter([
                ! $tiktokConnected ? 'Akun TikTok @plant.coaction belum terhubung melalui OAuth resmi.' : null,
                'Status kelayakan TikTok Shop Affiliate & verifikasi identitas/rekening harus dikonfirmasi langsung oleh pemilik akun di TikTok Shop Creator Center (persyaratan dapat berbeda per akun dan wilayah Indonesia).',
                ! $shopApiConfigured ? 'Kredensial resmi TikTok Shop Affiliate Creator API (TIKTOK_SHOP_APP_KEY, TIKTOK_SHOP_APP_SECRET, TIKTOK_SHOP_ACCESS_TOKEN) belum tersedia.' : null,
            ])),
            'official_links' => [
                ['label' => 'TikTok for Developers Portal', 'url' => 'https://developers.tiktok.com/'],
                ['label' => 'TikTok Shop Partner Center (API)', 'url' => 'https://partner.tiktokshop.com/'],
                ['label' => 'TikTok Shop Seller / Creator Center Indonesia', 'url' => 'https://seller-id.tiktok.com/'],
            ],
        ];
    }

    /**
     * Search products strictly through official TikTok Shop Affiliate API when approved & configured.
     *
     * @return array{ok: bool, status: string, message: string, products: array<int, AffiliateProduct>}
     */
    public function searchOfficialProducts(
        User $user,
        ?SocialAccount $account,
        string $keyword,
        ?string $category = null,
    ): array {
        if (! $this->isApiConfigured()) {
            return [
                'ok' => false,
                'status' => 'awaiting_api_access',
                'message' => 'Menunggu akses TikTok Shop Affiliate API',
                'products' => [],
            ];
        }

        $log = ProductResearchLog::create([
            'user_id' => $user->id,
            'source' => 'tiktok_shop_affiliate_api',
            'query_term' => $keyword,
            'category' => $category,
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $baseUrl = rtrim((string) config('affiliate_ai.tiktok.shop_affiliate.api_base_url', 'https://open-api.tiktokglobalshop.com'), '/');
        $accessToken = (string) config('affiliate_ai.tiktok.shop_affiliate.access_token');

        try {
            $resp = Http::timeout(25)
                ->withHeaders([
                    'x-tts-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->post($baseUrl . '/affiliate_creator/202405/open_collaborations/products/search', [
                    'title_keyword' => $keyword,
                    'category_name' => $category,
                    'page_size' => 20,
                ]);

            if ($resp->failed()) {
                $err = $this->logger->redact((string) data_get($resp->json(), 'message', 'HTTP ' . $resp->status()));
                $log->update([
                    'status' => 'failed',
                    'summary' => "Pencarian produk TikTok Shop API gagal: {$err}",
                    'completed_at' => now(),
                ]);

                return [
                    'ok' => false,
                    'status' => 'api_error',
                    'message' => "TikTok Shop Affiliate API mengembalikan error: {$err}",
                    'products' => [],
                ];
            }

            $rawItems = (array) data_get($resp->json(), 'data.products', []);
            $saved = [];

            foreach ($rawItems as $item) {
                if (! is_array($item) || empty($item['id']) || empty($item['title'])) {
                    continue;
                }

                $product = AffiliateProduct::updateOrCreate(
                    ['external_product_id' => (string) $item['id']],
                    [
                        'user_id' => $user->id,
                        'social_account_id' => $account?->id,
                        'source_platform' => 'tiktok_shop_api',
                        'name' => (string) $item['title'],
                        'category' => (string) ($item['category_name'] ?? $category ?? 'Tidak tersedia'),
                        'seller_name' => isset($item['shop']['name']) ? (string) $item['shop']['name'] : null,
                        'price' => isset($item['price']['amount']) ? (float) $item['price']['amount'] : null,
                        'commission_amount' => isset($item['commission']['amount']) ? (float) $item['commission']['amount'] : null,
                        'commission_rate' => isset($item['commission']['rate']) ? (float) $item['commission']['rate'] : null,
                        'rating' => isset($item['rating']) ? (float) $item['rating'] : null,
                        'sales_count' => isset($item['units_sold']) ? (int) $item['units_sold'] : null,
                        'product_url' => isset($item['detail_link']) ? (string) $item['detail_link'] : null,
                        'image_url' => isset($item['main_image_url']) ? (string) $item['main_image_url'] : null,
                        'approval_status' => ProductApprovalStatus::DISCOVERED->value,
                        'metadata' => ['raw_source' => 'tiktok_shop_affiliate_api', 'synced_keyword' => $keyword],
                        'fetched_at' => now(),
                    ]
                );
                $saved[] = $product;
            }

            $log->update([
                'status' => 'completed',
                'candidates_found' => count($saved),
                'summary' => 'Sinkronisasi produk dari TikTok Shop Affiliate API selesai.',
                'completed_at' => now(),
            ]);

            $this->logger->log('affiliate_research', 'search_products', "Menemukan " . count($saved) . " produk dari TikTok Shop Affiliate API untuk kata kunci '{$keyword}'.", 'info', ['count' => count($saved)], null, null, $user->id);

            return [
                'ok' => true,
                'status' => 'completed',
                'message' => 'Berhasil menyinkronkan ' . count($saved) . ' produk dari API resmi TikTok Shop Affiliate.',
                'products' => $saved,
            ];
        } catch (\Throwable $e) {
            $safeErr = $this->logger->redact($e->getMessage());
            $log->update(['status' => 'failed', 'summary' => $safeErr, 'completed_at' => now()]);

            return [
                'ok' => false,
                'status' => 'network_error',
                'message' => "Kesalahan jaringan saat mengakses TikTok Shop Affiliate API: {$safeErr}",
                'products' => [],
            ];
        }
    }
}
