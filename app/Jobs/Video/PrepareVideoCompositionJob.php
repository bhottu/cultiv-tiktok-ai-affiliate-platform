<?php

namespace App\Jobs\Video;

use App\Enums\ContentPipelineStatus;
use App\Enums\RenderJobStatus;
use App\Models\ProductCategory;
use App\Models\VideoRenderJob;
use App\Modules\ActivityLogs\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class PrepareVideoCompositionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        public readonly int $renderJobId,
    ) {}

    public function handle(ActivityLogger $logger): void
    {
        $renderJob = VideoRenderJob::with([
            'project.content.productCategory',
            'project.content.product',
            'project.productCategory',
            'project.scenes.mediaAsset',
            'project.subtitles',
            'project.voiceoverAsset',
            'project.bgMusicAsset',
        ])->find($this->renderJobId);

        if (! $renderJob || ! $renderJob->project || $renderJob->status === RenderJobStatus::CANCELLED->value) {
            return;
        }

        $project = $renderJob->project;
        $renderJob->update([
            'status' => RenderJobStatus::PREPARING->value,
            'stage' => 'preparing_assets',
            'progress_percent' => 10,
            'started_at' => now(),
            'error_message' => null,
        ]);
        $project->update(['status' => RenderJobStatus::PREPARING->value]);
        $project->content?->update(['status' => ContentPipelineStatus::RENDERING->value]);

        $stagingKey = 'job-' . $renderJob->id;
        $remotionPublicStaging = base_path("remotion/public/_staging/{$stagingKey}");
        File::ensureDirectoryExists($remotionPublicStaging);

        $disk = Storage::disk('local');
        $ffmpeg = (string) config('affiliate_ai.video.ffmpeg_binary', 'ffmpeg');

        // 1. Stage voiceover audio (normalize via FFmpeg loudnorm if available, fallback copy)
        $voiceoverRel = null;
        if ($project->voiceoverAsset && $disk->exists($project->voiceoverAsset->file_path)) {
            $srcVo = $disk->path($project->voiceoverAsset->file_path);
            $destVo = $remotionPublicStaging . DIRECTORY_SEPARATOR . 'voiceover.wav';
            $normProc = new Process([$ffmpeg, '-y', '-i', $srcVo, '-af', 'loudnorm=I=-16:TP=-1.5:LRA=11', '-c:a', 'pcm_s16le', $destVo]);
            $normProc->setTimeout(60);
            $normProc->run();
            if (! $normProc->isSuccessful() || ! is_file($destVo)) {
                File::copy($srcVo, $destVo);
            }
            $voiceoverRel = "_staging/{$stagingKey}/voiceover.wav";
        }

        // 2. Stage optional background music
        $bgMusicRel = null;
        if ($project->bgMusicAsset && $disk->exists($project->bgMusicAsset->file_path)) {
            $ext = pathinfo($project->bgMusicAsset->file_path, PATHINFO_EXTENSION) ?: 'mp3';
            $destBg = $remotionPublicStaging . DIRECTORY_SEPARATOR . 'bgmusic.' . $ext;
            File::copy($disk->path($project->bgMusicAsset->file_path), $destBg);
            $bgMusicRel = "_staging/{$stagingKey}/bgmusic.{$ext}";
        }

        // 3. Stage scene visual assets (transcode .mov to H.264 .mp4 safely for Chromium)
        $scenesPayload = [];
        $totalDuration = 0.0;

        foreach ($project->scenes->sortBy('scene_order')->values() as $idx => $scene) {
            $mediaPath = null;
            $mediaType = 'none';

            if ($scene->mediaAsset && $disk->exists($scene->mediaAsset->file_path)) {
                $srcAbs = $disk->path($scene->mediaAsset->file_path);
                $ext = strtolower(pathinfo($scene->mediaAsset->file_path, PATHINFO_EXTENSION) ?: 'jpg');

                if ($scene->mediaAsset->asset_type === 'video') {
                    $mediaType = 'video';
                    $destName = "scene-{$idx}.mp4";
                    $destAbs = $remotionPublicStaging . DIRECTORY_SEPARATOR . $destName;
                    if ($ext === 'mov') {
                        $conv = new Process([$ffmpeg, '-y', '-i', $srcAbs, '-c:v', 'libx264', '-preset', 'veryfast', '-an', '-pix_fmt', 'yuv420p', $destAbs]);
                        $conv->setTimeout(90);
                        $conv->run();
                        if (! $conv->isSuccessful()) {
                            File::copy($srcAbs, $destAbs);
                        }
                    } else {
                        File::copy($srcAbs, $destAbs);
                    }
                    $mediaPath = "_staging/{$stagingKey}/{$destName}";
                } elseif ($scene->mediaAsset->asset_type === 'image') {
                    $mediaType = 'image';
                    $destName = "scene-{$idx}.{$ext}";
                    File::copy($srcAbs, $remotionPublicStaging . DIRECTORY_SEPARATOR . $destName);
                    $mediaPath = "_staging/{$stagingKey}/{$destName}";
                }
            }

            $dur = max(1.0, (float) $scene->duration_seconds);
            $totalDuration += $dur;

            $scenesPayload[] = [
                'id' => $scene->id,
                'order' => $scene->scene_order,
                'title' => (string) ($scene->title ?? ''),
                'overlayText' => (string) ($scene->overlay_text ?? ''),
                'narrationSegment' => (string) ($scene->narration_segment ?? ''),
                'durationSeconds' => $dur,
                'mediaPath' => $mediaPath,
                'mediaType' => $mediaType,
                'transitionType' => (string) ($scene->transition_type ?? 'fade'),
                'motionEffect' => (string) ($scene->motion_effect ?? 'zoom_in'),
                'highlightKeywords' => (array) ($scene->highlight_keywords ?? []),
            ];
        }

        if (empty($scenesPayload)) {
            throw new \RuntimeException('Proyek video tidak memiliki scene. Tambahkan minimal 1 scene sebelum merender.');
        }

        $subtitlesPayload = $project->subtitles->sortBy('segment_order')->values()->map(fn ($sub) => [
            'id' => $sub->id,
            'order' => $sub->segment_order,
            'startSeconds' => (float) $sub->start_seconds,
            'endSeconds' => (float) $sub->end_seconds,
            'text' => (string) $sub->text,
            'highlightWords' => (array) ($sub->highlight_words ?? []),
        ])->all();

        $subStyle = $project->subtitle_style ?? [];
        $categoryModel = $project->productCategory ?: $project->content?->productCategory;
        $categoryLabel = $categoryModel?->name ?: ($project->content?->category ?: 'General Product');
        $preset = $project->visual_theme_preset
            ?: $categoryModel?->theme_preset
            ?: ProductCategory::resolvePresetFromName($categoryLabel);
        $themeTokens = ProductCategory::themeTokensForPreset($preset, $categoryModel?->accent_color);
        $product = $project->content?->product;
        $priceText = $product && $product->price !== null
            ? 'Rp' . number_format((float) $product->price, 0, ',', '.')
            : '';

        $props = [
            'title' => (string) $project->title,
            'category' => (string) $categoryLabel,
            'subcategory' => (string) ($project->content?->subcategory ?? ''),
            'productName' => (string) ($product?->name ?? ''),
            'priceText' => $priceText,
            'contentFormat' => (string) ($project->content?->content_format ?? 'product_showcase'),
            'experienceMode' => (string) ($project->content?->experience_mode ?? 'research_based'),
            'disclosureText' => (string) ($project->content?->disclosure_text ?? ''),
            'brandName' => (string) config('affiliate_ai.brand.tiktok_handle', '@plant.coaction'),
            'brandTagline' => (string) ($categoryLabel),
            'ctaText' => (string) ($project->cta_text ?? ''),
            'templateSlug' => (string) ($project->template_slug ?: 'product_showcase'),
            'categoryTheme' => $themeTokens,
            'fps' => (int) ($project->fps ?: 30),
            'totalDurationSeconds' => round(max(3.0, $totalDuration), 2),
            'voiceoverPath' => $voiceoverRel,
            'voiceoverVolume' => (float) ($project->voiceover_volume ?? 1.0),
            'bgMusicPath' => $bgMusicRel,
            'bgMusicVolume' => (float) ($project->bg_music_volume ?? 0.15),
            'scenes' => $scenesPayload,
            'subtitles' => $subtitlesPayload,
            'subtitleStyle' => [
                'enabled' => (bool) $project->subtitle_enabled,
                'position' => (string) ($subStyle['position'] ?? 'bottom'),
                'fontSize' => (int) ($subStyle['fontSize'] ?? 44),
                'highlightColor' => (string) ($subStyle['highlightColor'] ?? $themeTokens['accentColor']),
            ],
        ];

        $project->update(['composition_props' => $props]);
        $renderJob->update([
            'stage' => 'composition_ready',
            'progress_percent' => 15,
            'render_metrics' => array_merge($renderJob->render_metrics ?? [], ['staging_key' => $stagingKey]),
        ]);

        $logger->log('video_render', 'composition_prepared', "Komposisi Remotion untuk VideoProject #{$project->id} siap dirender.", 'info', ['render_job_id' => $renderJob->id], VideoRenderJob::class, $renderJob->id);

        RenderVideoJob::dispatch($renderJob->id);
    }

    public function failed(Throwable $e): void
    {
        $job = VideoRenderJob::with('project')->find($this->renderJobId);
        if ($job) {
            $job->update([
                'status' => RenderJobStatus::FAILED->value,
                'stage' => 'prepare_failed',
                'error_message' => app(ActivityLogger::class)->redact($e->getMessage()),
                'completed_at' => now(),
            ]);
            $job->project?->update(['status' => RenderJobStatus::FAILED->value]);
        }
    }
}
