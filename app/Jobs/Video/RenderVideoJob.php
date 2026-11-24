<?php

namespace App\Jobs\Video;

use App\Enums\RenderJobStatus;
use App\Models\VideoRenderJob;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\VideoProduction\RemotionVideoRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RenderVideoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 660;

    /** @var array<int, int> */
    public array $backoff = [30, 90];

    public function __construct(
        public readonly int $renderJobId,
    ) {}

    public function handle(RemotionVideoRenderer $renderer, ActivityLogger $logger): void
    {
        $renderJob = VideoRenderJob::with('project')->find($this->renderJobId);
        if (! $renderJob || ! $renderJob->project || $renderJob->status === RenderJobStatus::CANCELLED->value) {
            return;
        }

        $lock = Cache::lock('remotion_active_render_lock', 660);
        if (! $lock->get()) {
            $this->release(20);

            return;
        }

        try {
            $project = $renderJob->project;
            $renderJob->update([
                'status' => RenderJobStatus::RENDERING->value,
                'stage' => 'rendering_frames',
                'progress_percent' => 18,
                'attempts' => $renderJob->attempts + 1,
            ]);
            $project->update(['status' => RenderJobStatus::RENDERING->value]);

            // Gunakan nama file deterministik per project + renderJob agar retry tidak membuat file duplikat tak terpakai
            $relOutput = "renders/videos/project-{$project->id}-job-{$renderJob->id}.mp4";
            Storage::disk('local')->makeDirectory('renders/videos');
            $absOutput = Storage::disk('local')->path($relOutput);

            $res = $renderer->render(
                $project->composition_id ?: 'PlantEducation',
                $project->composition_props ?? [],
                $absOutput,
                function (int $pct, string $stageLabel) use ($renderJob): void {
                    $renderJob->update([
                        'progress_percent' => $pct,
                        'stage' => $stageLabel,
                    ]);
                },
            );

            if (! ($res['ok'] ?? false)) {
                throw new \RuntimeException($res['message'] ?? 'Rendering Remotion gagal.');
            }

            $renderJob->update([
                'progress_percent' => 96,
                'stage' => 'finalizing',
                'output_file_path' => $relOutput,
                'render_metrics' => array_merge($renderJob->render_metrics ?? [], ['probe' => $res['metrics'] ?? []]),
            ]);

            FinalizeVideoJob::dispatch($renderJob->id);
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $e): void
    {
        $job = VideoRenderJob::with('project')->find($this->renderJobId);
        $safeErr = app(ActivityLogger::class)->redact($e->getMessage());
        if ($job) {
            $job->update([
                'status' => RenderJobStatus::FAILED->value,
                'stage' => 'render_failed',
                'error_message' => $safeErr,
                'completed_at' => now(),
            ]);
            $job->project?->update(['status' => RenderJobStatus::FAILED->value]);
            CleanupTemporaryAssetsJob::dispatch($job->id);
        }

        app(ActivityLogger::class)->log('video_render', 'render_failed', "RenderVideoJob #{$this->renderJobId} gagal: {$safeErr}", 'error', ['render_job_id' => $this->renderJobId]);
    }
}
