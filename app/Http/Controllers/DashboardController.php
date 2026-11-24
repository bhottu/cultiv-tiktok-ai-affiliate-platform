<?php

namespace App\Http\Controllers;

use App\Modules\Dashboard\DashboardMetricsService;
use App\Modules\Settings\ApplicationSettingService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardMetricsService $metrics,
        private readonly ApplicationSettingService $settings,
    ) {}

    public function index(): View
    {
        return view('dashboard.index', [
            'overview' => $this->metrics->getOverview(),
            'heartbeat' => $this->settings->get('system.last_scheduler_heartbeat'),
            'operationalMode' => $this->settings->get('automation.mode', 'semi_automatic'),
        ]);
    }
}
