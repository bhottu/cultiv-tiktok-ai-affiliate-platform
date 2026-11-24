<?php

namespace App\Jobs\Video;

use App\Enums\ContentPipelineStatus;
use App\Enums\RenderJobStatus;
use App\Models\VideoRenderJob;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\VideoProduction\RemotionVideoRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class FinalizeVideoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly int $renderJobId,
    ) {}

    public function handle(RemotionVideoRenderer $renderer, ActivityLogger $logger): void
    {
        $renderJob = VideoRenderJob::with('project.content')->find($this->renderJobId);
        if (! $renderJob || ! $renderJob->project || ! $renderJob->output_file_path) {
            return;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($renderJob->output_file_path)) {
            $renderJob->update([
                'status' => RenderJobStatus::FAILED->value,
                'error_message' => 'File MP4 hasil render tidak ditemukan di storage.',
                'completed_at' => now(),
            ]);
            CleanupTemporaryAssetsJob::dispatch($renderJob->id);

            return;
        }

        $absMp4 = $disk->path($renderJob->output_file_path);
        $probe = $renderer->probeRenderedVideo($absMp4);
        $fileSize = (int) filesize($absMp4);

        // Ekstrak thumbnail poster
        $thumbRel = "renders/thumbnails/project-{$renderJob->video_project_id}-job-{$renderJob->id}.jpg";
        $disk->makeDirectory('renders/thumbnails');
        $thumbOk = $renderer->extractThumbnail($absMp4, $disk->path($thumbRel));

        $renderJob->update([
            'status' => RenderJobStatus::COMPLETED->value,
            'stage' => 'completed',
            'progress_percent' => 100,
            'file_size_bytes' => $fileSize,
            'completed_at' => now(),
            'error_message' => null,
            'render_metrics' => array_merge($renderJob->render_metrics ?? [], ['probe' => $probe]),
        ]);

        $project = $renderJob->project;
        $project->update([
            'status' => RenderJobStatus::COMPLETED->value,
            'output_video_path' => $renderJob->output_file_path,
            'thumbnail_path' => $thumbOk ? $thumbRel : $project->thumbnail_path,
            'duration_seconds' => $probe['duration_seconds'] ?: $project->duration_seconds,
            'width' => $probe['width'] ?: 1080,
            'height' => $probe['height'] ?: 1920,
            'fps' => (int) round($probe['fps'] ?: 30),
        ]);

        $project->content?->update([
            'status' => ContentPipelineStatus::VIDEO_READY->value,
        ]);

        $logger->log(
            'video_render',
            'render_completed',
            "Video MP4 untuk '{$project->title}' selesai dirender ({$probe['width']}x{$probe['height']}, {$probe['duration_seconds']}s).",
            'info',
            ['render_job_id' => $renderJob->id, 'probe' => $probe],
            VideoRenderJob::class,
            $renderJob->id,
        );

        CleanupTemporaryAssetsJob::dispatch($renderJob->id);
    }
}
