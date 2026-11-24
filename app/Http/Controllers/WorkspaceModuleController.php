<?php

namespace App\Http\Controllers;

use App\Modules\AffiliateProductResearch\ProductResearchService;
use App\Modules\AIAgents\AgentRegistryService;
use App\Modules\Analytics\AnalyticsService;
use App\Modules\CampaignManagement\CampaignService;
use App\Modules\ContentGeneration\ContentGenerationService;
use App\Modules\ContentStrategy\ContentStrategyService;
use App\Modules\PublishingWorkflow\PublishingWorkflowService;
use App\Modules\TikTokIntegration\TikTokPlatformService;
use App\Modules\VideoProduction\RemotionVideoRenderer;
use Illuminate\View\View;

class WorkspaceModuleController extends Controller
{
    public function agents(AgentRegistryService $agents): View
    {
        return view('modules.agents', ['agents' => $agents->all()]);
    }

    public function products(ProductResearchService $products): View
    {
        return view('modules.products', [
            'products' => $products->listProducts(),
            'logs' => $products->recentLogs(),
        ]);
    }

    public function strategy(ContentStrategyService $strategy): View
    {
        return view('modules.strategy', [
            'pillars' => $strategy->activePillars(),
            'ideas' => $strategy->listIdeas(),
        ]);
    }

    public function content(ContentGenerationService $content, CampaignService $campaigns): View
    {
        return view('modules.content', [
            'contents' => $content->listRecent(),
            'campaigns' => $campaigns->all(),
            'aiReady' => $content->isReady(),
        ]);
    }

    public function video(RemotionVideoRenderer $renderer): View
    {
        return view('modules.video', [
            'readiness' => $renderer->checkReadiness(),
        ]);
    }

    public function publishing(PublishingWorkflowService $publishing, TikTokPlatformService $tiktok): View
    {
        return view('modules.publishing', [
            'schedules' => $publishing->upcoming(),
            'tiktokStatus' => $tiktok->getReadinessStatus(),
        ]);
    }

    public function analytics(AnalyticsService $analytics): View
    {
        return view('modules.analytics', [
            'summary' => $analytics->getActualSummary(),
        ]);
    }
}
