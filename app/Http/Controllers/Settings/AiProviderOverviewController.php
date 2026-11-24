<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\AICore\AIProviderManager;
use App\Modules\AIProviders\GeminiProvider;
use App\Modules\AIProviders\OpenRouterProvider;
use App\Modules\Settings\ApplicationSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AiProviderOverviewController extends Controller
{
    public function __construct(
        private readonly AIProviderManager $aiManager,
        private readonly OpenRouterProvider $openRouter,
        private readonly GeminiProvider $gemini,
        private readonly ApplicationSettingService $settings,
        private readonly ActivityLogger $logger,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('manage-providers');

        $orRecord = $this->openRouter->getRecord();
        $gemRecord = $this->gemini->getRecord();
        $activeTab = $request->query('tab', 'openrouter');
        if (! in_array($activeTab, ['openrouter', 'google_gemini'], true)) {
            $activeTab = 'openrouter';
        }

        return view('settings.ai-providers', [
            'activeTab' => $activeTab,
            'defaultProviderSlug' => $this->aiManager->getDefaultProviderSlug(),
            'fallbackEnabled' => $this->aiManager->isFallbackEnabled(),
            'fallbackProviderSlug' => $this->aiManager->getFallbackProviderSlug(),
            'openRouter' => [
                'record' => $orRecord,
                'hasKey' => $this->openRouter->isConfigured(),
                'maskedKey' => $orRecord->getMaskedApiKey(),
                'defaultModel' => $orRecord->models()->where('is_default', true)->first(),
                'fallbackModel' => $orRecord->models()->where('is_fallback', true)->first(),
            ],
            'gemini' => [
                'record' => $gemRecord,
                'hasKey' => $this->gemini->isConfigured(),
                'maskedKey' => $gemRecord->getMaskedApiKey(),
                'defaultModel' => $gemRecord->models()->where('is_default', true)->first(),
                'testHistory' => is_array($gemRecord->settings['test_history'] ?? null)
                    ? $gemRecord->settings['test_history']
                    : [],
            ],
        ]);
    }

    public function updateRouting(Request $request): RedirectResponse
    {
        Gate::authorize('manage-providers');

        $data = $request->validate([
            'default_provider' => ['required', 'in:openrouter,google_gemini'],
            'fallback_enabled' => ['nullable', 'boolean'],
        ], [
            'default_provider.in' => 'Provider utama harus OpenRouter atau Google Gemini.',
        ]);

        // Pastikan record kedua provider tersedia
        $this->openRouter->getRecord();
        $this->gemini->getRecord();

        AiProvider::query()->update(['is_default' => false]);
        AiProvider::where('slug', $data['default_provider'])->update(['is_default' => true]);

        $fallbackEnabled = $request->boolean('fallback_enabled');
        $fallbackSlug = $data['default_provider'] === 'openrouter' ? 'google_gemini' : 'openrouter';

        $this->settings->set('ai.default_provider', $data['default_provider'], 'ai', false, 'Provider AI utama aplikasi.');
        $this->settings->set('ai.fallback_enabled', $fallbackEnabled ? '1' : '0', 'ai', false, 'Aktifkan fallback otomatis ke provider alternatif (maks. 1 percobaan).');
        $this->settings->set('ai.fallback_provider', $fallbackSlug, 'ai', false, 'Provider AI alternatif saat fallback aktif.');

        $this->logger->log(
            'ai_core',
            'update_routing',
            "Routing AI diperbarui: Default={$data['default_provider']}, Fallback=" . ($fallbackEnabled ? "Aktif ({$fallbackSlug})" : 'Nonaktif') . '.',
        );

        return redirect()
            ->route('settings.ai.index', ['tab' => $data['default_provider']])
            ->with('status', 'Pengaturan Default AI Provider & Fallback berhasil disimpan.');
    }
}
