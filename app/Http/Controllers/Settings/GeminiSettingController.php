<?php

namespace App\Http\Controllers\Settings;

use App\Events\ProviderConnectionTested;
use App\Http\Controllers\Controller;
use App\Models\AiModelConfiguration;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\AIProviders\GeminiProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class GeminiSettingController extends Controller
{
    public function __construct(
        private readonly GeminiProvider $gemini,
        private readonly ActivityLogger $logger,
    ) {}

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage-providers');

        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'min:10', 'max:255'],
            'base_url' => ['required', 'url', 'max:255'],
            'timeout' => ['required', 'integer', 'min:5', 'max:120'],
            'max_retries' => ['required', 'integer', 'min:0', 'max:2'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_output_tokens' => ['required', 'integer', 'min:128', 'max:65536'],
        ], [
            'api_key.min' => 'Gemini API Key minimal 10 karakter.',
            'base_url.url' => 'Base URL Google Gemini harus berupa URL valid.',
            'max_retries.max' => 'Retry dibatasi maksimal 2 kali demi mengontrol biaya API.',
        ]);

        $record = $this->gemini->getRecord();
        $record->base_url = $data['base_url'];
        $record->settings = array_merge($record->settings ?? [], [
            'timeout' => (int) $data['timeout'],
            'max_retries' => (int) $data['max_retries'],
            'temperature' => (float) $data['temperature'],
            'max_output_tokens' => (int) $data['max_output_tokens'],
        ]);

        if (! empty($data['api_key'])) {
            $record->setRawApiKey($data['api_key']);
            $record->connection_status = 'configured_untested';
            $record->last_error = null;
            Cache::forget('google_gemini_models_catalog_v1');
        }

        $record->save();
        $this->logger->log('ai_providers', 'update_config', 'Pengaturan Google Gemini diperbarui (API Key terenkripsi).', 'info', ['provider' => 'google_gemini']);

        return redirect()
            ->route('settings.ai.index', ['tab' => 'google_gemini'])
            ->with('status', 'Pengaturan Google Gemini berhasil disimpan secara aman.');
    }

    public function removeKey(): RedirectResponse
    {
        Gate::authorize('manage-providers');

        $record = $this->gemini->getRecord();
        $record->setRawApiKey(null);
        $record->connection_status = 'not_configured';
        $record->last_error = null;
        $record->save();
        Cache::forget('google_gemini_models_catalog_v1');

        $this->logger->log('ai_providers', 'remove_api_key', 'API Key Google Gemini dihapus dari database.', 'info', ['provider' => 'google_gemini']);

        return redirect()
            ->route('settings.ai.index', ['tab' => 'google_gemini'])
            ->with('status', 'API Key Google Gemini berhasil dihapus.');
    }

    public function testConnection(): JsonResponse
    {
        Gate::authorize('manage-providers');

        $result = $this->gemini->testConnection();
        event(new ProviderConnectionTested('ai', 'google_gemini', $result['ok'], $result['message']));

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function models(Request $request): JsonResponse
    {
        Gate::authorize('manage-providers');

        $force = $request->boolean('refresh', false);
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $onlyGenerate = $request->boolean('only_generate_content', true);

        $res = $this->gemini->listModels($force);
        if (! $res['ok']) {
            return response()->json($res, 422);
        }

        $filtered = array_values(array_filter($res['models'], function (array $m) use ($search, $onlyGenerate) {
            if ($onlyGenerate && ! ($m['supports_text_generation'] ?? false)) {
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
            'input_token_limit' => ['nullable', 'integer'],
            'output_token_limit' => ['nullable', 'integer'],
            'supported_methods' => ['nullable', 'string', 'max:500'],
        ]);

        $record = $this->gemini->getRecord();
        $methods = $data['supported_methods']
            ? array_values(array_filter(array_map('trim', explode(',', $data['supported_methods']))))
            : ['generateContent'];

        if (! in_array('generateContent', $methods, true)) {
            return redirect()
                ->route('settings.ai.index', ['tab' => 'google_gemini'])
                ->withErrors(['model_id' => 'Model yang dipilih tidak mendukung generateContent untuk generasi teks.']);
        }

        $record->models()->update(['is_default' => false]);

        AiModelConfiguration::updateOrCreate(
            [
                'ai_provider_id' => $record->id,
                'model_id' => $data['model_id'],
                'task_role' => 'general',
            ],
            [
                'display_name' => $data['display_name'] ?: $data['model_id'],
                'is_free' => false,
                'is_default' => true,
                'is_fallback' => false,
                'context_length' => $data['input_token_limit'] ?? null,
                'pricing_meta' => [
                    'source' => 'google_gemini_api',
                    'pricing_info_available' => false,
                    'note' => 'API /v1beta/models tidak menyertakan tier harga; periksa billing di Google AI Studio.',
                    'supported_generation_methods' => $methods,
                    'output_token_limit' => $data['output_token_limit'] ?? null,
                ],
                'is_active' => true,
            ]
        );

        $this->logger->log('ai_providers', 'select_model', "Model default Google Gemini ditetapkan ke {$data['model_id']}.", 'info', ['provider' => 'google_gemini']);

        return redirect()
            ->route('settings.ai.index', ['tab' => 'google_gemini'])
            ->with('status', "Model default Google Gemini berhasil disimpan: {$data['model_id']}.");
    }
}
