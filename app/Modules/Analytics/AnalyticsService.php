<?php

namespace App\Modules\Analytics;

use App\Models\AffiliateProduct;
use App\Models\GeneratedContent;
use App\Models\PublishingSchedule;
use App\Models\VideoProject;

class AnalyticsService
{
    public function getActualSummary(): array
    {
        return [
            'published_posts' => PublishingSchedule::where('status', 'published')->count(),
            'completed_videos' => VideoProject::where('status', 'completed')->count(),
            'approved_contents' => GeneratedContent::where('status', 'approved')->count(),
            'verified_products' => AffiliateProduct::where('approval_status', 'approved')->count(),
            'tiktok_metrics_available' => false,
            'affiliate_commission_available' => false,
            'note' => 'Metrik views TikTok dan komisi TikTok Shop belum tersedia pada Fase 1 (belum terhubung ke OAuth TikTok maupun laporan Creator Center).',
        ];
    }
}
