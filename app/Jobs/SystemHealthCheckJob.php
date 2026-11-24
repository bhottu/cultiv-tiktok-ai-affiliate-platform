<?php

namespace App\Jobs;

use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\Settings\ApplicationSettingService;
use App\Modules\TextToSpeech\AudioConverterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SystemHealthCheckJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function handle(
        ApplicationSettingService $settings,
        ActivityLogger $logger,
        AudioConverterService $audioConverter,
    ): void {
        $cleanedPreviews = $audioConverter->cleanupOldPreviews(24);
        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();
        $nowWib = now('Asia/Jakarta')->toIso8601String();

        $settings->set(
            'system.last_scheduler_heartbeat',
            [
                'checked_at_wib' => $nowWib,
                'pending_jobs' => $pending,
                'failed_jobs' => $failed,
                'cleaned_tts_previews' => $cleanedPreviews,
                'timezone' => config('app.timezone'),
            ],
            'system',
            false,
            'Heartbeat internal Laravel Scheduler (tanpa publikasi TikTok).',
        );

        $logger->log(
            'scheduler',
            'health_check',
            "Pemeriksaan kesehatan sistem internal selesai ({$nowWib}).",
            'info',
            ['pending_jobs' => $pending, 'failed_jobs' => $failed],
        );
    }
}
