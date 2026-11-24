<?php

namespace App\Modules\Dashboard;

use App\Enums\WorkflowStatus;
use App\Models\ActivityLog;
use App\Models\AffiliateProduct;
use App\Models\ContentIdea;
use App\Models\GeneratedContent;
use App\Models\PublishingSchedule;
use App\Models\VideoProject;
use App\Modules\AICore\AIProviderManager;
use App\Modules\AIProviders\GeminiProvider;
use App\Modules\AIProviders\OpenRouterProvider;
use App\Modules\TextToSpeech\GeminiTTSProvider;
use App\Modules\TextToSpeech\GoogleCloudTTSProvider;
use App\Modules\TextToSpeech\TTSProviderManager;
use App\Modules\TikTokIntegration\TikTokPlatformService;
use App\Modules\VideoProduction\RemotionVideoRenderer;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    public function __construct(
        private readonly AIProviderManager $aiManager,
        private readonly OpenRouterProvider $openRouter,
        private readonly GeminiProvider $gemini,
        private readonly TTSProviderManager $ttsManager,
        private readonly GoogleCloudTTSProvider $googleTts,
        private readonly GeminiTTSProvider $geminiTts,
        private readonly RemotionVideoRenderer $videoRenderer,
        private readonly TikTokPlatformService $tiktokPlatform,
    ) {}

    public function getOverview(): array
    {
        $defaultAiSlug = $this->aiManager->getDefaultProviderSlug();
        $openRouterRec = $this->openRouter->getRecord();
        $geminiRec = $this->gemini->getRecord();

        $activeAiRec = $defaultAiSlug === 'google_gemini' ? $geminiRec : $openRouterRec;
        $activeAiDriver = $this->aiManager->driver($defaultAiSlug);
        $defaultModel = $activeAiRec->models()->where('is_default', true)->first();

        $defaultTtsSlug = $this->ttsManager->getDefaultProviderSlug();
        $activeTtsDriver = $this->ttsManager->driver($defaultTtsSlug);
        $ttsProfile = $defaultTtsSlug === 'google_gemini_tts'
            ? $this->geminiTts->getProfile()
            : $this->googleTts->getProfile();

        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();

        return [
            'counts' => [
                'content_ideas' => ContentIdea::count(),
                'draft_contents' => GeneratedContent::where('status', WorkflowStatus::DRAFT->value)->count(),
                'pending_approval' => GeneratedContent::where('status', WorkflowStatus::PENDING_APPROVAL->value)->count(),
                'rendered_videos' => VideoProject::where('status', WorkflowStatus::COMPLETED->value)->count(),
                'scheduled_publications' => PublishingSchedule::where('status', WorkflowStatus::SCHEDULED->value)->count(),
                'verified_products' => AffiliateProduct::where('approval_status', WorkflowStatus::APPROVED->value)->count(),
            ],
            'providers' => [
                'ai' => [
                    'slug' => $defaultAiSlug,
                    'name' => $activeAiRec->name,
                    'configured' => $activeAiDriver->isConfigured(),
                    'status' => $activeAiRec->connection_status,
                    'default_model' => $defaultModel?->model_id,
                    'last_tested_at' => $activeAiRec->last_tested_at,
                    'fallback_enabled' => $this->aiManager->isFallbackEnabled(),
                    'openrouter_status' => $openRouterRec->connection_status,
                    'gemini_status' => $geminiRec->connection_status,
                ],
                'tts' => [
                    'slug' => $defaultTtsSlug,
                    'name' => $activeTtsDriver->getName(),
                    'configured' => $activeTtsDriver->isConfigured(),
                    'status' => $ttsProfile->connection_status,
                    'voice_id' => $ttsProfile->voice_id,
                    'language_code' => $ttsProfile->language_code,
                    'last_tested_at' => $ttsProfile->last_tested_at,
                ],
                'queue' => [
                    'driver' => config('queue.default'),
                    'pending_jobs' => $pendingJobs,
                    'failed_jobs' => $failedJobs,
                    'timezone' => config('app.timezone'),
                ],
                'video' => $this->videoRenderer->checkReadiness(),
                'tiktok' => $this->tiktokPlatform->getReadinessStatus(),
            ],
            'recent_activities' => ActivityLog::latest('created_at')->limit(8)->get(),
        ];
    }
}
