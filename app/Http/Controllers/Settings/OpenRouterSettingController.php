<?php

namespace App\Http\Controllers\Settings;

use App\Events\ProviderConnectionTested;
use App\Http\Controllers\Controller;
use App\Models\AiModelConfiguration;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\AIProviders\OpenRouterProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OpenRouterSettingController extends Controller
{
    public function __construct(
        private readonly OpenRouterProvider $openRouter,
        private readonly ActivityLogger $logger,
    ) {}

    public function index(Request $request, AiProviderOverviewController $overview): View
    {
        $request->merge(['tab' => 'openrouter']);

        return $overview->index($request);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage-providers');

        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'min:10', 'max:255'],
            'clear_api_key' => ['nullable', 'boolean'],
            'base_url' => ['required', 'url', 'max:255'],
            'timeout' => ['required', 'integer', 'min:5', 'max:120'],
        ], [
            'api_key.min' => 'API key OpenRouter minimal 10 karakter.',
            'base_url.url' => 'URL endpoint OpenRouter harus berupa URL valid.',
        ]);

        $provider = $this->openRouter->getRecord();
        $provider->base_url = $data['base_url'];
        $provider->settings = array_merge($provider->settings ?? [], [
            'timeout' => (int) $data['timeout'],
        ]);

        if ($request->boolean('clear_api_key')) {
            $provider->setRawApiKey(null);
            $provider->connection_status = 'not_configured';
            $provider->last_error = null;
        } elseif (! empty($data['api_key'])) {
            $provider->setRawApiKey($data['api_key']);
            $provider->connection_status = 'configured_untested';
            $provider->last_error = null;
        }

        $provider->save();
        $this->logger->log('ai_providers', 'update_config', 'Pengaturan OpenRouter diperbarui (API key terenkripsi).');

        return redirect()
            ->route('settings.openrouter.index')
            ->with('status', 'Pengaturan OpenRouter berhasil disimpan secara aman.');
    }

    public function testConnection(): JsonResponse
    {
        Gate::authorize('manage-providers');

        $result = $this->openRouter->testConnection();
        event(new ProviderConnectionTested('ai', 'openrouter', $result['ok'], $result['message']));

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function models(Request $request): JsonResponse
    {
        Gate::authorize('manage-providers');

        $force = $request->boolean('refresh', false);
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $tier = (string) $request->query('tier', 'all'); // all | free | paid

        $res = $this->openRouter->listModels($force);
        if (! $res['ok']) {
            return response()->json($res, 422);
        }

        $filtered = array_values(array_filter($res['models'], function (array $m) use ($search, $tier) {
            if ($tier === 'free' && ! $m['is_free']) {
                return false;
            }
            if ($tier === 'paid' && $m['is_free']) {
                return false;
            }
            if ($search !== '') {
                $haystack = mb_strtolower($m['id'] . ' ' . $m['name']);

                return str_contains($haystack, $search);
            }

            return true;
        }));

        return response()->json([
            'ok' => true,
            'total' => count($res['models']),
            'filtered_count' => count($filtered),
            'models' => array_slice($filtered, 0, 150),
        ]);
    }

    public function selectModel(Request $request): RedirectResponse
    {
        Gate::authorize('manage-providers');

        $data = $request->validate([
            'model_id' => ['required', 'string', 'max:180'],
            'display_name' => ['nullable', 'string', 'max:180'],
            'is_free' => ['nullable', 'boolean'],
            'role_type' => ['required', 'in:default,fallback'],
        ]);

        $provider = $this->openRouter->getRecord();

        if ($data['role_type'] === 'default') {
            $provider->models()->update(['is_default' => false]);
        } else {
            $provider->models()->update(['is_fallback' => false]);
        }

        AiModelConfiguration::updateOrCreate(
            [
                'ai_provider_id' => $provider->id,
                'model_id' => $data['model_id'],
                'task_role' => 'general',
            ],
            [
                'display_name' => $data['display_name'] ?: $data['model_id'],
                'is_free' => (bool) ($data['is_free'] ?? str_ends_with($data['model_id'], ':free')),
                'is_default' => $data['role_type'] === 'default',
                'is_fallback' => $data['role_type'] === 'fallback',
                'is_active' => true,
            ]
        );

        $label = $data['role_type'] === 'default' ? 'utama' : 'cadangan (fallback)';
        $this->logger->log('ai_providers', 'select_model', "Model {$label} OpenRouter ditetapkan ke {$data['model_id']}.");

        return redirect()
            ->route('settings.openrouter.index')
            ->with('status', "Model {$label} berhasil diperbarui ke {$data['model_id']}.");
    }
}
