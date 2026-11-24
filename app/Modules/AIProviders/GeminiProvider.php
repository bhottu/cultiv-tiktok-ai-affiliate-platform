<?php

namespace App\Modules\AIProviders;

use App\Contracts\AIProviderInterface;
use App\Models\AiProvider;
use App\Modules\ActivityLogs\ActivityLogger;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeminiProvider implements AIProviderInterface
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function getSlug(): string
    {
        return 'google_gemini';
    }

    public function getName(): string
    {
        return 'Google Gemini';
    }

    public function getRecord(): AiProvider
    {
        return AiProvider::firstOrCreate(
            ['slug' => 'google_gemini'],
            [
                'name' => 'Google Gemini',
                'driver' => 'google_gemini',
                'base_url' => config('affiliate_ai.ai.google_gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'),
                'is_active' => true,
                'is_default' => false,
                'connection_status' => 'not_configured',
                'settings' => [
                    'timeout' => (int) config('affiliate_ai.ai.google_gemini.timeout', 30),
                    'max_retries' => (int) config('affiliate_ai.ai.google_gemini.max_retries', 1),
                    'temperature' => 0.7,
                    'max_output_tokens' => 2048,
                    'test_history' => [],
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

        $envKey = trim((string) config('affiliate_ai.ai.google_gemini.api_key', ''));

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
            $msg = 'API Key Google Gemini belum dikonfigurasi. Dapatkan API Key resmi di Google AI Studio terlebih dahulu.';
            $this->recordTestResult($record, 'not_configured', $msg, null);

            return [
                'ok' => false,
                'status' => 'not_configured',
                'message' => $msg,
            ];
        }

        $started = microtime(true);
        $modelsRes = $this->listModels(true);
        $latencyMs = (int) round((microtime(true) - $started) * 1000);

        if (! ($modelsRes['ok'] ?? false)) {
            $msg = $modelsRes['message'] ?? 'Gagal terhubung ke Google Gemini Developer API.';
            $this->recordTestResult($record, 'failed', $msg, $latencyMs);
            $this->logger->log('ai_providers', 'test_connection', $msg, 'warning', ['provider' => 'google_gemini', 'latency_ms' => $latencyMs]);

            return [
                'ok' => false,
                'status' => 'failed',
                'message' => $msg,
                'latency_ms' => $latencyMs,
            ];
        }

        $allModels = $modelsRes['models'] ?? [];
        $genCount = count(array_filter($allModels, fn (array $m) => (bool) ($m['supports_text_generation'] ?? false)));
        $msg = "Koneksi Google Gemini berhasil ({$latencyMs} ms). Ditemukan {$genCount} model yang mendukung generateContent.";

        $this->recordTestResult($record, 'connected', $msg, $latencyMs);
        $this->logger->log('ai_providers', 'test_connection', $msg, 'info', [
            'provider' => 'google_gemini',
            'models_count' => $genCount,
            'latency_ms' => $latencyMs,
        ]);

        return [
            'ok' => true,
            'status' => 'connected',
            'message' => $msg,
            'latency_ms' => $latencyMs,
            'models_count' => $genCount,
        ];
    }

    public function listModels(bool $forceRefresh = false): array
    {
        $apiKey = $this->resolveApiKey();
        if (! $apiKey) {
            return [
                'ok' => false,
                'models' => [],
                'message' => 'Masukkan Gemini API Key terlebih dahulu untuk memuat daftar model resmi Google Gemini.',
            ];
        }

        $cacheKey = 'google_gemini_models_catalog_v1';
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
        $baseUrl = rtrim($record->base_url ?: 'https://generativelanguage.googleapis.com/v1beta', '/');
        $timeout = (int) ($record->settings['timeout'] ?? 30);

        try {
            $resp = Http::timeout($timeout)
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->get($baseUrl . '/models', ['pageSize' => 100]);

            if ($resp->failed()) {
                return [
                    'ok' => false,
                    'models' => [],
                    'message' => $this->mapHttpError($resp->status(), $resp->json()),
                ];
            }

            $rawModels = $resp->json('models', []);
            $models = [];

            foreach ($rawModels as $item) {
                if (! is_array($item) || empty($item['name'])) {
                    continue;
                }

                $fullName = (string) $item['name'];
                $modelId = preg_replace('/^models\//', '', $fullName) ?: $fullName;
                $methods = array_values(array_map('strval', (array) ($item['supportedGenerationMethods'] ?? [])));
                $supportsGenerateContent = in_array('generateContent', $methods, true);

                $models[] = [
                    'id' => $modelId,
                    'resource_name' => $fullName,
                    'name' => (string) ($item['displayName'] ?? $modelId),
                    'description' => (string) ($item['description'] ?? ''),
                    'supported_generation_methods' => $methods,
                    'supports_text_generation' => $supportsGenerateContent,
                    'input_token_limit' => isset($item['inputTokenLimit']) ? (int) $item['inputTokenLimit'] : null,
                    'output_token_limit' => isset($item['outputTokenLimit']) ? (int) $item['outputTokenLimit'] : null,
                ];
            }

            usort($models, fn ($a, $b) => strcmp($a['id'], $b['id']));
            Cache::put($cacheKey, $models, now()->addMinutes(15));

            return [
                'ok' => true,
                'models' => $models,
            ];
        } catch (ConnectionException $e) {
            return [
                'ok' => false,
                'models' => [],
                'message' => 'Koneksi timeout atau jaringan terputus saat menghubungi Google Gemini API: ' . $this->logger->redact($e->getMessage()),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'models' => [],
                'message' => 'Gagal memuat model Google Gemini: ' . $this->logger->redact($e->getMessage()),
            ];
        }
    }

    public function chat(array $messages, ?string $model = null, array $options = []): array
    {
        $apiKey = $this->resolveApiKey();
        if (! $apiKey) {
            return [
                'ok' => false,
                'provider' => $this->getSlug(),
                'message' => 'Google Gemini belum dikonfigurasi. Masukkan Gemini API Key di halaman Pengaturan AI Provider.',
            ];
        }

        $record = $this->getRecord();
        $selectedModel = $model ?: $record->models()->where('is_default', true)->value('model_id');
        if (! $selectedModel) {
            $selectedModel = trim((string) config('affiliate_ai.ai.google_gemini.default_model', ''));
        }
        if ($selectedModel === '') {
            return [
                'ok' => false,
                'provider' => $this->getSlug(),
                'message' => 'Model default Google Gemini belum dipilih. Pilih model terlebih dahulu pada menu Pengaturan AI Provider.',
            ];
        }

        $modelId = preg_replace('/^models\//', '', $selectedModel) ?: $selectedModel;
        $baseUrl = rtrim($record->base_url ?: 'https://generativelanguage.googleapis.com/v1beta', '/');
        $timeout = (int) ($options['timeout'] ?? $record->settings['timeout'] ?? 30);
        $maxRetries = min(2, max(0, (int) ($options['max_retries'] ?? $record->settings['max_retries'] ?? 1)));

        [$systemInstruction, $contents] = $this->formatMessagesForGemini($messages);
        if (empty($contents)) {
            return [
                'ok' => false,
                'provider' => $this->getSlug(),
                'message' => 'Prompt pengguna tidak boleh kosong saat memanggil Google Gemini.',
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => (float) ($options['temperature'] ?? $record->settings['temperature'] ?? 0.7),
                'maxOutputTokens' => (int) ($options['max_tokens'] ?? $options['max_output_tokens'] ?? $record->settings['max_output_tokens'] ?? 2048),
            ],
        ];

        if ($systemInstruction !== null) {
            $payload['system_instruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        $url = "{$baseUrl}/models/" . rawurlencode($modelId) . ':generateContent';
        $lastError = 'Gagal menghubungi Google Gemini API.';

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $resp = Http::timeout($timeout)
                    ->withHeaders([
                        'x-goog-api-key' => $apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($url, $payload);

                if ($resp->successful()) {
                    $json = $resp->json();
                    $parts = data_get($json, 'candidates.0.content.parts', []);
                    $text = implode('', array_map(fn ($p) => (string) ($p['text'] ?? ''), is_array($parts) ? $parts : []));

                    return [
                        'ok' => true,
                        'provider' => $this->getSlug(),
                        'model' => $modelId,
                        'content' => $text,
                        'usage' => [
                            'prompt_tokens' => data_get($json, 'usageMetadata.promptTokenCount'),
                            'completion_tokens' => data_get($json, 'usageMetadata.candidatesTokenCount'),
                            'total_tokens' => data_get($json, 'usageMetadata.totalTokenCount'),
                        ],
                    ];
                }

                $status = $resp->status();
                $lastError = $this->mapHttpError($status, $resp->json());

                // Jangan retry untuk error client (400/401/403/404) atau rate limit/quota (429) agar tidak memboroskan request
                if ($status < 500) {
                    return [
                        'ok' => false,
                        'provider' => $this->getSlug(),
                        'model' => $modelId,
                        'http_status' => $status,
                        'message' => $lastError,
                    ];
                }
            } catch (ConnectionException $e) {
                $lastError = 'Timeout atau gagal terhubung ke Google Gemini API: ' . $this->logger->redact($e->getMessage());
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'provider' => $this->getSlug(),
                    'model' => $modelId,
                    'message' => 'Kesalahan internal saat memanggil Google Gemini: ' . $this->logger->redact($e->getMessage()),
                ];
            }
        }

        return [
            'ok' => false,
            'provider' => $this->getSlug(),
            'model' => $modelId,
            'message' => $lastError,
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{0: ?string, 1: array<int, array<string, mixed>>}
     */
    private function formatMessagesForGemini(array $messages): array
    {
        $systemParts = [];
        $contents = [];

        foreach ($messages as $msg) {
            $role = strtolower((string) ($msg['role'] ?? 'user'));
            $text = (string) ($msg['content'] ?? '');
            if (trim($text) === '') {
                continue;
            }

            if ($role === 'system') {
                $systemParts[] = $text;
                continue;
            }

            $geminiRole = $role === 'assistant' || $role === 'model' ? 'model' : 'user';
            $contents[] = [
                'role' => $geminiRole,
                'parts' => [['text' => $text]],
            ];
        }

        $systemInstruction = ! empty($systemParts) ? implode("\n\n", $systemParts) : null;

        return [$systemInstruction, $contents];
    }

    private function mapHttpError(int $status, mixed $body): string
    {
        $apiMessage = is_array($body) ? (string) data_get($body, 'error.message', '') : '';
        $apiStatus = is_array($body) ? (string) data_get($body, 'error.status', '') : '';
        $safeDetail = $apiMessage !== '' ? ' (' . $this->logger->redact($apiMessage) . ')' : '';

        if ($status === 429 || strtoupper($apiStatus) === 'RESOURCE_EXHAUSTED') {
            return 'Batas rate limit atau kuota Google Gemini API terlampaui (HTTP 429 / RESOURCE_EXHAUSTED). Periksa kuota dan billing di Google AI Studio.' . $safeDetail;
        }

        return match ($status) {
            400 => 'Permintaan ke Google Gemini API tidak valid atau API Key salah (HTTP 400).' . $safeDetail,
            401, 403 => 'API Key Google Gemini tidak valid, ditolak, atau tidak memiliki izin akses (HTTP ' . $status . ').' . $safeDetail,
            404 => 'Model Google Gemini yang dipilih tidak ditemukan atau tidak mendukung generateContent (HTTP 404).' . $safeDetail,
            500, 503 => 'Server Google Gemini API sedang mengalami gangguan sementara (HTTP ' . $status . ').',
            default => 'Google Gemini API mengembalikan error (HTTP ' . $status . ').' . $safeDetail,
        };
    }

    private function recordTestResult(AiProvider $record, string $status, string $message, ?int $latencyMs): void
    {
        $settings = $record->settings ?? [];
        $history = is_array($settings['test_history'] ?? null) ? $settings['test_history'] : [];

        array_unshift($history, [
            'tested_at_wib' => now('Asia/Jakarta')->format('d M Y H:i:s') . ' WIB',
            'status' => $status,
            'latency_ms' => $latencyMs,
            'message' => $this->logger->redact($message),
        ]);

        $settings['test_history'] = array_slice($history, 0, 10);

        $record->update([
            'connection_status' => $status,
            'last_tested_at' => now(),
            'last_error' => $status === 'connected' ? null : $this->logger->redact($message),
            'settings' => $settings,
        ]);
    }
}
