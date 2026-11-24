<?php

namespace App\Http\Controllers\ContentStudio;

use App\Enums\RenderJobStatus;
use App\Enums\VideoTemplateType;
use App\Http\Controllers\Controller;
use App\Jobs\Video\PrepareVideoCompositionJob;
use App\Models\MediaAsset;
use App\Models\ProductCategory;
use App\Models\VideoProject;
use App\Models\VideoRenderJob;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\TextToSpeech\TTSProviderManager;
use App\Modules\VideoProduction\SubtitleGeneratorService;
use App\Modules\VideoProduction\VideoProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VideoEditorController extends Controller
{
    public const THEME_PRESETS = [
        'tech_dark' => 'Tech Dark (Electronics & Gadgets)',
        'fashion_editorial' => 'Editorial Warm (Fashion & Apparel)',
        'beauty_soft' => 'Soft Rose (Beauty & Skincare)',
        'health_clean' => 'Clean Teal (Health & Personal Care)',
        'home_warm' => 'Warm Sand (Home & Kitchen)',
        'auto_carbon' => 'Carbon Crimson (Automotive)',
        'sports_energy' => 'Energy Orange (Sports & Outdoor)',
        'food_terracotta' => 'Terracotta (Food & Beverage)',
        'botanical_green' => 'Botanical Green (Plants & Gardening)',
        'neutral_studio' => 'Neutral Studio (General / Other)',
    ];

    public function __construct(
        private readonly VideoProjectService $projectService,
        private readonly SubtitleGeneratorService $subtitleService,
        private readonly TTSProviderManager $ttsManager,
        private readonly ActivityLogger $logger,
    ) {}

    public function show(Request $request, VideoProject $project): View
    {
        Gate::authorize('view', $project);

        $project->load([
            'content.affiliateProduct',
            'content.productCategory',
            'productCategory',
            'scenes.mediaAsset',
            'subtitles',
            'voiceoverAsset',
            'bgMusicAsset',
            'renderJobs' => fn ($q) => $q->latest()->limit(8),
            'latestRenderJob',
        ]);

        $user = $request->user();
        $visualAssets = MediaAsset::query()
            ->where('user_id', $user->id)
            ->whereIn('asset_type', ['image', 'video'])
            ->orderBy('display_order')
            ->latest()
            ->get();

        $bgMusicAssets = MediaAsset::query()
            ->where('user_id', $user->id)
            ->where('asset_type', 'bg_music')
            ->latest()
            ->get();

        $activeTts = $this->ttsManager->driver();

        $templates = VideoTemplateType::universalCases();
        $currentTpl = VideoTemplateType::tryFrom((string) $project->template_slug);
        if ($currentTpl && $currentTpl->isLegacy()) {
            $templates[] = $currentTpl;
        }

        $categoryModel = $project->productCategory ?: $project->content?->productCategory;
        $preset = $project->visual_theme_preset
            ?: $categoryModel?->theme_preset
            ?: ProductCategory::resolvePresetFromName($project->content?->category);
        $themeTokens = ProductCategory::themeTokensForPreset($preset, $categoryModel?->accent_color);

        return view('content-studio.editor', [
            'project' => $project,
            'templates' => $templates,
            'themePresets' => self::THEME_PRESETS,
            'activeThemePreset' => $preset,
            'themeTokens' => $themeTokens,
            'visualAssets' => $visualAssets,
            'bgMusicAssets' => $bgMusicAssets,
            'activeTtsName' => $activeTts->getName(),
            'activeTtsConfigured' => $activeTts->isConfigured(),
        ]);
    }

    public function saveDraft(Request $request, VideoProject $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:220'],
            'template_slug' => ['required', 'string', 'in:' . implode(',', VideoTemplateType::values())],
            'visual_theme_preset' => ['nullable', 'string', 'in:' . implode(',', array_keys(self::THEME_PRESETS))],
            'cta_text' => ['nullable', 'string', 'max:300'],
            'subtitle_enabled' => ['nullable', 'boolean'],
            'subtitle_position' => ['nullable', 'in:top,center,bottom'],
            'subtitle_font_size' => ['nullable', 'integer', 'min:28', 'max:64'],
            'subtitle_highlight_color' => ['nullable', 'string', 'max:20'],
            'voiceover_volume' => ['nullable', 'numeric', 'min:0.1', 'max:1.5'],
            'bg_music_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
            'bg_music_volume' => ['nullable', 'numeric', 'min:0', 'max:0.5'],
            'scenes' => ['nullable', 'array'],
            'scenes.*.id' => ['nullable', 'integer'],
            'scenes.*.scene_order' => ['nullable', 'integer', 'min:1'],
            'scenes.*.title' => ['nullable', 'string', 'max:180'],
            'scenes.*.overlay_text' => ['nullable', 'string', 'max:400'],
            'scenes.*.narration_segment' => ['nullable', 'string', 'max:1500'],
            'scenes.*.media_asset_id' => ['nullable', 'integer'],
            'scenes.*.duration_seconds' => ['nullable', 'numeric', 'min:1', 'max:45'],
            'scenes.*.motion_effect' => ['nullable', 'in:zoom_in,zoom_out,pan_left,pan_right,static'],
            'scenes.*.transition_type' => ['nullable', 'in:fade,slide,zoom,cut'],
            'subtitles' => ['nullable', 'array'],
            'subtitles.*.id' => ['nullable', 'integer'],
            'subtitles.*.start_seconds' => ['nullable', 'numeric', 'min:0'],
            'subtitles.*.end_seconds' => ['nullable', 'numeric', 'min:0.2'],
            'subtitles.*.text' => ['nullable', 'string', 'max:250'],
        ]);

        $userId = $request->user()->id;
        if (! empty($data['bg_music_asset_id'])) {
            $bgAsset = MediaAsset::find($data['bg_music_asset_id']);
            if ($bgAsset && (int) $bgAsset->user_id !== $userId) {
                abort(403);
            }
        }
        if (! empty($data['scenes'])) {
            $assetIds = array_filter(array_column($data['scenes'], 'media_asset_id'));
            if (! empty($assetIds)) {
                $foreignCount = MediaAsset::whereIn('id', $assetIds)
                    ->where('user_id', '!=', $userId)
                    ->count();
                if ($foreignCount > 0) {
                    abort(403);
                }
            }
        }

        $data['subtitle_enabled'] = $request->boolean('subtitle_enabled');

        $this->projectService->saveEditorDraft($project, $data);

        return redirect()->route('content.editor.show', $project)
            ->with('status', 'Perubahan scene, subtitle, template, tema visual, dan audio berhasil disimpan.');
    }

    public function regenerateSubtitles(VideoProject $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        $narration = $project->content?->buildFullNarration() ?: $project->title;
        $duration = (float) ($project->voiceoverAsset?->duration_seconds ?: $project->duration_seconds ?: 30);

        $this->subtitleService->generateForProject($project, $narration, $duration);

        return redirect()->route('content.editor.show', $project)
            ->with('status', 'Subtitle otomatis diperbarui sesuai naskah dan estimasi durasi audio.');
    }

    public function dispatchRender(Request $request, VideoProject $project): RedirectResponse|JsonResponse
    {
        Gate::authorize('update', $project);

        if ($project->scenes()->count() === 0) {
            $msg = 'Proyek belum memiliki scene. Tambahkan minimal satu scene sebelum memulai render.';
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $msg], 422)
                : back()->withErrors(['render' => $msg]);
        }

        $activeJob = $project->renderJobs()
            ->whereIn('status', [
                RenderJobStatus::QUEUED->value,
                RenderJobStatus::PREPARING->value,
                RenderJobStatus::RENDERING->value,
                RenderJobStatus::FINALIZING->value,
            ])
            ->latest()
            ->first();

        if ($activeJob) {
            $msg = "Proses render #{$activeJob->id} sedang berjalan ({$activeJob->progress_percent}%). Tunggu selesai atau batalkan terlebih dahulu.";
            return $request->expectsJson()
                ? response()->json(['ok' => true, 'render_job_id' => $activeJob->id, 'status' => $activeJob->status, 'message' => $msg])
                : back()->with('status', $msg);
        }

        $renderJob = VideoRenderJob::create([
            'video_project_id' => $project->id,
            'idempotency_key' => 'render-' . $project->id . '-' . Str::uuid()->toString(),
            'status' => RenderJobStatus::QUEUED->value,
            'stage' => 'queued',
            'progress_percent' => 5,
            'attempts' => 1,
            'renderer' => 'remotion',
        ]);

        $project->update(['status' => RenderJobStatus::QUEUED->value]);

        PrepareVideoCompositionJob::dispatch($renderJob->id);

        $this->logger->log(
            'video_render',
            'render_dispatched',
            "Job render #{$renderJob->id} untuk VideoProject #{$project->id} masuk antrean queue.",
            'info',
            ['render_job_id' => $renderJob->id, 'template' => $project->template_slug],
            VideoRenderJob::class,
            $renderJob->id,
            $request->user()->id,
        );

        $msg = "Render video Remotion #{$renderJob->id} berhasil dijadwalkan di queue. Pastikan `php artisan queue:work` berjalan.";

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'render_job_id' => $renderJob->id, 'status' => $renderJob->status, 'message' => $msg])
            : redirect()->route('content.editor.show', $project)->with('status', $msg);
    }

    public function retryRender(Request $request, VideoProject $project, VideoRenderJob $renderJob): RedirectResponse
    {
        Gate::authorize('update', $project);

        if ((int) $renderJob->video_project_id !== $project->id) {
            abort(404);
        }

        $renderJob->update([
            'status' => RenderJobStatus::QUEUED->value,
            'stage' => 'retry_queued',
            'progress_percent' => 5,
            'attempts' => ((int) $renderJob->attempts) + 1,
            'error_message' => null,
            'completed_at' => null,
            'cancelled_at' => null,
        ]);

        $project->update(['status' => RenderJobStatus::QUEUED->value]);
        PrepareVideoCompositionJob::dispatch($renderJob->id);

        return redirect()->route('content.editor.show', $project)
            ->with('status', "Retry job render #{$renderJob->id} (percobaan #{$renderJob->attempts}) telah dikirim ke queue.");
    }

    public function cancelRender(VideoProject $project, VideoRenderJob $renderJob): RedirectResponse
    {
        Gate::authorize('update', $project);

        if ((int) $renderJob->video_project_id !== $project->id) {
            abort(404);
        }

        if (in_array($renderJob->status, [
            RenderJobStatus::QUEUED->value,
            RenderJobStatus::PREPARING->value,
            RenderJobStatus::RENDERING->value,
        ], true)) {
            $renderJob->update([
                'status' => RenderJobStatus::CANCELLED->value,
                'stage' => 'cancelled_by_user',
                'cancelled_at' => now(),
                'completed_at' => now(),
            ]);
            $project->update(['status' => 'draft']);
        }

        return redirect()->route('content.editor.show', $project)
            ->with('status', "Job render #{$renderJob->id} dibatalkan.");
    }

    public function renderStatus(VideoProject $project): JsonResponse
    {
        Gate::authorize('view', $project);

        $latest = $project->latestRenderJob()->first();

        return response()->json([
            'ok' => true,
            'project_status' => $project->status,
            'has_video' => (bool) $project->output_video_path,
            'video_stream_url' => $project->output_video_path ? route('content.video.stream', $project) : null,
            'video_export_url' => $project->output_video_path ? route('content.video.export', $project) : null,
            'job' => $latest ? [
                'id' => $latest->id,
                'status' => $latest->status,
                'stage' => $latest->stage,
                'progress_percent' => (int) $latest->progress_percent,
                'error_message' => $latest->error_message,
                'updated_at' => $latest->updated_at?->timezone('Asia/Jakarta')->format('H:i:s'),
            ] : null,
        ]);
    }
}
