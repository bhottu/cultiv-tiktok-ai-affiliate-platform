<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\SystemHealthCheckJob;
use App\Models\ActivityLog;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\Settings\ApplicationSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GeneralSettingController extends Controller
{
    public function __construct(
        private readonly ApplicationSettingService $settings,
        private readonly ActivityLogger $logger,
    ) {}

    public function index(): View
    {
        Gate::authorize('manage-providers');

        return view('settings.general', [
            'brandName' => $this->settings->get('brand.name', 'Plant Coaction'),
            'tiktokHandle' => $this->settings->get('brand.tiktok_handle', '@plant.coaction'),
            'operationalMode' => $this->settings->get('automation.mode', 'semi_automatic'),
            'dailyTarget' => (int) $this->settings->get('automation.daily_target', 1),
            'timezone' => config('app.timezone'),
            'heartbeat' => $this->settings->get('system.last_scheduler_heartbeat'),
            'queueStats' => [
                'driver' => config('queue.default'),
                'pending' => DB::table('jobs')->count(),
                'failed' => DB::table('failed_jobs')->count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage-providers');

        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:120'],
            'operational_mode' => ['required', 'in:manual,semi_automatic'],
            'daily_target' => ['required', 'integer', 'min:1', 'max:3'],
        ], [
            'operational_mode.in' => 'Pada Fase 1, hanya mode Manual dan Semi-Automatic yang diizinkan.',
        ]);

        $this->settings->set('brand.name', $data['brand_name'], 'general');
        $this->settings->set('automation.mode', $data['operational_mode'], 'automation');
        $this->settings->set('automation.daily_target', (string) $data['daily_target'], 'automation');

        $this->logger->log('settings', 'update_general', 'Pengaturan umum dan mode operasional diperbarui.');

        return redirect()
            ->route('settings.general.index')
            ->with('status', 'Pengaturan umum berhasil disimpan.');
    }

    public function dispatchHealthCheck(): RedirectResponse
    {
        Gate::authorize('manage-providers');

        SystemHealthCheckJob::dispatch();
        $this->logger->log('scheduler', 'manual_health_dispatch', 'Job pemeriksaan kesehatan antrean dikirim ke queue.');

        return redirect()
            ->route('settings.general.index')
            ->with('status', 'Job pemeriksaan kesehatan antrean telah dikirim ke database queue.');
    }

    public function activityLogs(): View
    {
        return view('modules.activity-logs', [
            'logs' => ActivityLog::with('user')->latest('created_at')->paginate(25),
        ]);
    }
}
