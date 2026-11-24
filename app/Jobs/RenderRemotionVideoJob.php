<?php

namespace App\Jobs;

use App\Contracts\VideoRendererInterface;
use App\Enums\WorkflowStatus;
use App\Models\VideoRenderJob;
use App\Modules\ActivityLogs\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RenderRemotionVideoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    /** @var array<int, int> */
    public array $backoff = [30, 90];

    public function __construct(
        public readonly int $renderJobId,
    ) {}

    public function handle(VideoRendererInterface $renderer, ActivityLogger $logger): void
    {
        $jobRecord = VideoRenderJob::with('project')->find($this->renderJobId);
        if (! $jobRecord || ! $jobRecord->project) {
            return;
        }

        $jobRecord->update([
            'status' => WorkflowStatus::PROCESSING->value,
            'attempts' => $jobRecord->attempts + 1,
            'started_at' => now(),
            'error_message' => null,
        ]);

        $outputPath = storage_path('app/renders/project-' . $jobRecord->video_project_id . '.mp4');
        $res = $renderer->render(
            $jobRecord->project->composition_id,
            $jobRecord->project->composition_props ?? [],
            $outputPath,
        );

        if (! ($res['ok'] ?? false)) {
            $status = $res['status'] ?? WorkflowStatus::FAILED->value;
            $jobRecord->update([
                'status' => $status,
                'error_message' => $res['message'] ?? 'Rendering video gagal.',
                'completed_at' => now(),
            ]);

            $logger->log(
                'video_production',
                'render_halted',
                $res['message'] ?? 'Render Remotion belum dijalankan.',
                'warning',
                ['render_job_id' => $jobRecord->id, 'status' => $status],
                VideoRenderJob::class,
                $jobRecord->id,
            );

            return;
        }

        $jobRecord->update([
            'status' => WorkflowStatus::COMPLETED->value,
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);
        $jobRecord->project->update([
            'status' => WorkflowStatus::COMPLETED->value,
            'output_video_path' => $res['output_path'] ?? $outputPath,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $jobRecord = VideoRenderJob::find($this->renderJobId);
        if ($jobRecord) {
            $jobRecord->update([
                'status' => WorkflowStatus::FAILED->value,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ]);
        }

        app(ActivityLogger::class)->log(
            'video_production',
            'render_failed',
            'Job rendering Remotion gagal: ' . $exception->getMessage(),
            'error',
            ['render_job_id' => $this->renderJobId],
            VideoRenderJob::class,
            $this->renderJobId,
        );
    }
}
