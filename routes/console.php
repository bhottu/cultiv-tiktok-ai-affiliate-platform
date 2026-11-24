<?php

use App\Jobs\SystemHealthCheckJob;
use Illuminate\Support\Facades\Schedule;

// Fase 1: Scheduler hanya menjalankan pekerjaan internal sistem (tanpa publikasi TikTok nyata).
Schedule::job(new SystemHealthCheckJob())
    ->hourly()
    ->timezone('Asia/Jakarta')
    ->name('plant-coaction-internal-health-check')
    ->withoutOverlapping();
