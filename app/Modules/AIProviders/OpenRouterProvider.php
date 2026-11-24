<?php

namespace App\Modules\AIProviders;

use App\Contracts\AIProviderInterface;
use App\Models\AiProvider;
use App\Modules\ActivityLogs\ActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OpenRouterProvider implements AIProviderInterface
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function getSlug(): string
    {
        return 'openrouter';
    }

    public function getName(): string
    {
        return 'OpenRouter';
    }

    public function getRecord(): AiProvider
    {
        return AiProvider::firstOrCreate(
            ['slug' => 'openrouter'],
            [
                'name' => 'OpenRouter',
                'driver' => 'openrouter',
                'base_url' => config('affiliate_ai.ai.openrouter.base_url', 'https://openrouter.ai/api/v1'),
                'is_active' => true,
                'is_default' => ! AiProvider::where('is_default', true)->exists(),
                'connection_status' => 'not_configured',
                'settings' => [
                    'timeout' => config('affiliate_ai.ai.openrouter.timeout', 30),
                ],
            ]
        );
    }

    public function resolveApiKey(): ?string
    {
        $record = $this->getRecord();
        $dbKey = $record->getDecryptedApiKey();
        if ($dbKey) {
            return $dbKey;
        }

        $envKey = trim((string) config('affiliate_ai.ai.openrouter.api_key', ''));

        return $envKey !== '' ? $envKey : null;
    }

    public function isConfigured(): bool
    {
        return $this->resolveApiKey() !== null;
    }

    public function testConnection(): array
    {
        $record = $this->getRecord();
        $apiKey = $this->resolveApiKey();

        if (! $apiKey) {
            $msg = 'API key OpenRouter belum dikonfigurasi. Masukkan API key terlebih dahulu sebelum menguji koneksi.';
            $record->update([
                'connection_status' => 'not_configured',
                'last_tested_at' => now(),
                'last_error' => $msg,
            ]);

            return [
                'ok' => false,
                'status' => 'not_configured',
                'message' => $msg,
            ];
        }

        $baseUrl = rtrim($record->base_url ?: 'https://openrouter.ai/api/v1', '/');
        $timeout = (int) ($record->settings['timeout'] ?? 30);
        $started = microtime(true);

        try {
            $keyResp = Http::timeout($timeout)
                ->withToken($apiKey)
                ->withHeaders([
                    'HTTP-Referer' => config('app.url', 'http://127.0.0.1:8000'),
                    'X-Title' => 'Plant Coaction Affiliate AI',
                ])
                ->get($baseUrl . '/auth/key');

            $latencyMs = (int) round((microtime(true) - $started) * 1000);

            if ($keyResp->failed()) {
                $status = $keyResp->status();
                $msg = match ($status) {
                    401, 403 => 'API key OpenRouter tidak valid atau ditolak (HTTP ' . $status . '). Periksa kembali API key Anda.',
                    429 => 'Batas permintaan OpenRouter terlampaui (HTTP 429). Coba lagi beberapa saat lagi.',
                    default => 'Gagal terhubung ke OpenRouter (HTTP ' . $status . ').',
                };
                $record->update([
                    'connection_status' => 'failed',
                    'last_tested_at' => now(),
                    'last_error' => $msg,
                ]);
                $this->logger->log('ai_providers', 'test_connection', $msg, 'warning', ['provider' => 'openrouter', 'http_status' => $status]);

                return [
                    'ok' => false,
                    'status' => 'failed',
                    'message' => $msg,
                    'latency_ms' => $latencyMs,
                ];
            }

            $modelsRes = $this->listModels(true);
            $modelsCount = count($modelsRes['models'] ?? []);

            $msg = "Koneksi OpenRouter berhasil ({$latencyMs} ms). Ditemukan {$modelsCount} model tersedia.";
            $record->update([
                'connection_status' => 'connected',
                'last_tested_at' => now(),
                'last_error' => null,
            ]);
            $this->logger->log('ai_providers', 'test_connection', $msg, 'info', ['provider' => 'openrouter', 'models_count' => $modelsCount]);

            return [
                'ok' => true,
                'status' => 'connected',
                'message' => $msg,
                'latency_ms' => $latencyMs,
                'models_count' => $modelsCount,
            ];
        } catch (\Throwable $e) {
            $safeMsg = 'Gagal menghubungi server OpenRouter: ' . $this->logger->redact($e->getMessage());
            $record->update([
                'connection_status' => 'failed',
                'last_tested_at' => now(),
                'last_error' => $safeMsg,
            ]);
            $this->logger->log('ai_providers', 'test_connection', $safeMsg, 'error', ['provider' => 'openrouter']);

            return [
                'ok' => false,
                'status' => 'failed',
                'message' => $safeMsg,
            ];
        }
    }

    public function listModels(bool $forceRefresh = false): array
    {
        $apiKey = $this->resolveApiKey();
        if (! $apiKey) {
            return [
                'ok' => false,
                'models' => [],
                'message' => 'Masukkan API key OpenRouter terlebih dahulu untuk memuat daftar model resmi.',
            ];
        }

        $cacheKey = 'openrouter_models_catalog_v1';
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        if (! $forceRefresh && Cache::has($cacheKey)) {
            return [
                'ok' => true,
                'models' => Cache::get($cacheKey, []),
            ];
        }

        $record = $this->getRecord();
        $baseUrl = rtrim($record->base_url ?: 'https://openrouter.ai/api/v1', '/');
        $timeout = (int) ($record->settings['timeout'] ?? 30);

        try {
            $resp = Http::timeout($timeout)
                ->withToken($apiKey)
                ->get($baseUrl . '/models');

            if ($resp->failed()) {
                return [
                    'ok' => false,
                    'models' => [],
                    'message' => 'Gagal mengambil daftar model OpenRouter (HTTP ' . $resp->status() . ').',
                ];
            }

            $rawList = $resp->json('data', []);
            $models = [];
            foreach ($rawList as $item) {
                if (! is_array($item) || empty($item['id'])) {
                    continue;
                }
                $pricing = $item['pricing'] ?? [];
                $promptPrice = isset($pricing['prompt']) ? (float) $pricing['prompt'] : null;
                $completionPrice = isset($pricing['completion']) ? (float) $pricing['completion'] : null;
                $isFree = ($promptPrice !== null && $completionPrice !== null && $promptPrice == 0.0 && $completionPrice == 0.0)
                    || str_ends_with((string) $item['id'], ':free');

                $models[] = [
                    'id' => (string) $item['id'],
                    'name' => (string) ($item['name'] ?? $item['id']),
                    'context_length' => isset($item['context_length']) ? (int) $item['context_length'] : null,
                    'is_free' => $isFree,
                    'pricing' => [
                        'prompt' => $pricing['prompt'] ?? null,
                        'completion' => $pricing['completion'] ?? null,
                    ],
                ];
            }

            usort($models, fn ($a, $b) => strcmp($a['id'], $b['id']));
            Cache::put($cacheKey, $models, now()->addMinutes(15));

            return [
                'ok' => true,
                'models' => $models,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'models' => [],
                'message' => 'Kesalahan jaringan saat mengambil model OpenRouter: ' . $this->logger->redact($e->getMessage()),
            ];
        }
    }

    public function chat(array $messages, ?string $model = null, array $options = []): array
    {
        $apiKey = $this->resolveApiKey();
        if (! $apiKey) {
            return [
                'ok' => false,
                'message' => 'OpenRouter belum dikonfigurasi. Tambahkan API key pada menu Pengaturan AI Provider.',
            ];
        }

        $record = $this->getRecord();
        $selectedModel = $model ?: $record->models()->where('is_default', true)->value('model_id');
        if (! $selectedModel) {
            return [
                'ok' => false,
                'message' => 'Model default OpenRouter belum dipilih. Silakan pilih model di menu Pengaturan AI Provider.',
            ];
        }

        $baseUrl = rtrim($record->base_url ?: 'https://openrouter.ai/api/v1', '/');
        $timeout = (int) ($record->settings['timeout'] ?? 30);

        try {
            $resp = Http::timeout($timeout)
                ->withToken($apiKey)
                ->withHeaders([
                    'HTTP-Referer' => config('app.url', 'http://127.0.0.1:8000'),
                    'X-Title' => 'Plant Coaction Affiliate AI',
                ])
                ->post($baseUrl . '/chat/completions', [
                    'model' => $selectedModel,
                    'messages' => $messages,
                    'temperature' => $options['temperature'] ?? 0.7,
                    'max_tokens' => $options['max_tokens'] ?? 1500,
                ]);

            if ($resp->failed()) {
                return [
                    'ok' => false,
                    'message' => 'Permintaan OpenRouter gagal (HTTP ' . $resp->status() . ').',
                ];
            }

            return [
                'ok' => true,
                'content' => (string) data_get($resp->json(), 'choices.0.message.content', ''),
                'model' => (string) data_get($resp->json(), 'model', $selectedModel),
                'usage' => (array) data_get($resp->json(), 'usage', []),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal menjalankan inferensi OpenRouter: ' . $this->logger->redact($e->getMessage()),
            ];
        }
    }
}
