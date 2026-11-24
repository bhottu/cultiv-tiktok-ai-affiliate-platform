<?php

namespace App\Jobs\Video;

use App\Models\VideoRenderJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;

class CleanupTemporaryAssetsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $renderJobId,
    ) {}

    public function handle(): void
    {
        $renderJob = VideoRenderJob::find($this->renderJobId);
        if (! $renderJob) {
            return;
        }

        // HANYA hapus direktori staging sementara; JANGAN PERNAH menghapus aset asli milik pengguna!
        $stagingDir = base_path("remotion/public/_staging/job-{$renderJob->id}");
        if (File::isDirectory($stagingDir)) {
            File::deleteDirectory($stagingDir);
        }
    }
}
