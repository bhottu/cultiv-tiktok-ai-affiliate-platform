<?php

namespace App\Modules\TextToSpeech;

use App\Contracts\TextToSpeechInterface;
use App\Models\VoiceProfile;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\AIProviders\GeminiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class GeminiTTSProvider implements TextToSpeechInterface
{
    /**
     * Verified Gemini Developer API speech-generation models (strictly dedicated TTS models).
     * Never treat text-only Gemini models as TTS-capable.
     */
    public const SUPPORTED_TTS_MODELS = [
        'gemini-2.5-flash-preview-tts' => [
            'id' => 'gemini-2.5-flash-preview-tts',
            'name' => 'Gemini 2.5 Flash Preview TTS',
            'description' => 'Model TTS cepat & hemat untuk narasi konten harian (input maks. 8.192 token, output maks. 16.384 token audio).',
            'supports_style_prompt' => true,
            'supports_numeric_pitch' => false,
            'supports_numeric_volume' => false,
        ],
        'gemini-2.5-pro-preview-tts' => [
            'id' => 'gemini-2.5-pro-preview-tts',
            'name' => 'Gemini 2.5 Pro Preview TTS',
            'description' => 'Model TTS ekspresif tingkat lanjut untuk kontrol intonasi mendalam (kuota lebih ketat dibanding Flash TTS).',
            'supports_style_prompt' => true,
            'supports_numeric_pitch' => false,
            'supports_numeric_volume' => false,
        ],
    ];

    /**
     * Official prebuilt studio voices documented by Google Gemini Speech Generation API.
     */
    public const OFFICIAL_PREBUILT_VOICES = [
        ['name' => 'Kore', 'trait' => 'Firm (Tegas & jernih)', 'recommended_for' => 'Edukasi perawatan & review objektif'],
        ['name' => 'Charon', 'trait' => 'Informative (Informatif & tenang)', 'recommended_for' => 'Tutorial media tanam & aroid'],
        ['name' => 'Aoede', 'trait' => 'Breezy (Santai & natural)', 'recommended_for' => 'Storytelling & demonstrasi produk'],
        ['name' => 'Zephyr', 'trait' => 'Bright (Cerah & ramah)', 'recommended_for' => 'Pembukaan hook & edukasi pemula'],
        ['name' => 'Puck', 'trait' => 'Upbeat (Ceria & energik)', 'recommended_for' => 'Sorotan produk & CTA promosi natural'],
        ['name' => 'Sulafat', 'trait' => 'Warm (Hangat & bersahabat)', 'recommended_for' => 'Tips harian & interaksi audiens'],
        ['name' => 'Leda', 'trait' => 'Youthful (Segar & ringan)', 'recommended_for' => 'Video singkat 15–30 detik'],
        ['name' => 'Fenrir', 'trait' => 'Excitable (Dinamis & antusias)', 'recommended_for' => 'Highlight alat greenhouse & berkebun'],
        ['name' => 'Orus', 'trait' => 'Firm (Mantap & jelas)', 'recommended_for' => 'Penekanan manfaat & kesimpulan'],
    ];

    public function __construct(
        private readonly GeminiProvider $geminiAiProvider,
        private readonly AudioConverterService $audioConverter,
        private readonly ActivityLogger $logger,
    ) {}

    public function getSlug(): string
    {
        return 'google_gemini_tts';
    }

    public function getName(): string
    {
        return 'Google Gemini TTS';
    }

    public function getProfile(): VoiceProfile
    {
        return VoiceProfile::firstOrCreate(
            ['provider_slug' => 'google_gemini_tts', 'is_default' => true],
            [
                'name' => 'Google Gemini TTS — Bahasa Indonesia',
                'language_code' => 'id-ID',
                'voice_id' => (string) config('affiliate_ai.tts.google_gemini_tts.default_voice', 'Kore'),
                'speaking_rate' => 1.00,
                'pitch' => 0.00,
                'volume_gain_db' => 0.00,
                'is_active' => true,
                'connection_status' => 'not_configured',
                'metadata' => [
                    'model_id' => (string) config('affiliate_ai.tts.google_gemini_tts.default_model', 'gemini-2.5-flash-preview-tts'),
                    'style_instruction' => 'Ucapkan secara natural, ramah, jelas, dan bertempo sedang dalam Bahasa Indonesia seperti praktisi tanaman hias.',
                    'pace_preset' => 'normal',
                ],
            ]
        );
    }

    /**
     * Reuse the encrypted Gemini Developer API Key from GeminiProvider (zero key duplication).
     */
    public function resolveSharedApiKey(): ?string
    {
        return $this->geminiAiProvider->resolveApiKey();
    }

    public function getMaskedSharedApiKey(): ?string
    {
        return $this->geminiAiProvider->getRecord()->getMaskedApiKey();
    }

    public function isConfigured(): bool
    {
        return $this->resolveSharedApiKey() !== null;
    }

    /**
     * Return official verified TTS models, enriched with availability check from Gemini /v1beta/models when connected.
     *
     * @return array<int, array<string, mixed>>
     */
    public function availableModels(bool $checkApi = false): array
    {
        $catalog = self::SUPPORTED_TTS_MODELS;
        $discoveredIds = null;

        if ($checkApi && $this->isConfigured()) {
            $list = $this->geminiAiProvider->listModels(false);
            if ($list['ok'] ?? false) {
                $discoveredIds = array_column($list['models'] ?? [], 'id');
            }
        }

        $result = [];
        foreach ($catalog as $id => $info) {
            $result[] = array_merge($info, [
                'available_in_api' => $discoveredIds !== null ? in_array($id, $discoveredIds, true) : null,
            ]);
        }

        return $result;
    }

    public function isSupportedTtsModel(string $modelId): bool
    {
        $normalized = preg_replace('/^models\//', '', trim($modelId)) ?: '';

        return array_key_exists($normalized, self::SUPPORTED_TTS_MODELS);
    }

    public function isSupportedVoice(string $voiceName): bool
    {
        foreach (self::OFFICIAL_PREBUILT_VOICES as $v) {
            if ($v['name'] === $voiceName) {
                return true;
            }
        }

        return false;
    }

    public function listVoices(string $languageCode = 'id-ID', bool $forceRefresh = false): array
    {
        $voices = array_map(fn (array $v) => [
            'name' => $v['name'],
            'trait' => $v['trait'],
            'recommended_for' => $v['recommended_for'],
            'language_codes' => ['id-ID'],
        ], self::OFFICIAL_PREBUILT_VOICES);

        return [
            'ok' => true,
            'voices' => $voices,
        ];
    }

    public function testConnection(): array
    {
        $profile = $this->getProfile();
        $apiKey = $this->resolveSharedApiKey();

        if (! $apiKey) {
            $msg = 'API Key Google Gemini belum tersedia. Hubungkan Gemini API Key terlebih dahulu melalui halaman AI Provider.';
            $profile->update([
                'connection_status' => 'not_configured',
                'last_tested_at' => now(),
                'last_error' => $msg,
            ]);

            return ['ok' => false, 'status' => 'not_configured', 'message' => $msg];
        }

        $modelsRes = $this->geminiAiProvider->listModels(true);
        if (! ($modelsRes['ok'] ?? false)) {
            $msg = $modelsRes['message'] ?? 'Gagal memverifikasi akses Gemini Developer API untuk TTS.';
            $profile->update([
                'connection_status' => 'failed',
                'last_tested_at' => now(),
                'last_error' => $msg,
            ]);
            $this->logger->log('tts', 'gemini_tts_test_failed', $msg, 'warning', ['provider' => 'google_gemini_tts']);

            return ['ok' => false, 'status' => 'failed', 'message' => $msg];
        }

        $apiModelIds = array_column($modelsRes['models'] ?? [], 'id');
        $availableTts = array_values(array_intersect(array_keys(self::SUPPORTED_TTS_MODELS), $apiModelIds));

        if (empty($availableTts)) {
            $msg = 'Koneksi Gemini API berhasil, tetapi belum ditemukan model TTS resmi (' . implode(', ', array_keys(self::SUPPORTED_TTS_MODELS)) . ') pada akun/wilayah API Key ini.';
            $profile->update([
                'connection_status' => 'failed',
                'last_tested_at' => now(),
                'last_error' => $msg,
            ]);

            return ['ok' => false, 'status' => 'model_unavailable', 'message' => $msg];
        }

        $msg = 'Koneksi Google Gemini TTS berhasil. Model TTS tersedia: ' . implode(', ', $availableTts) . '.';
        $profile->update([
            'connection_status' => 'connected',
            'last_tested_at' => now(),
            'last_error' => null,
        ]);
        $this->logger->log('tts', 'gemini_tts_test_connected', $msg, 'info', [
            'provider' => 'google_gemini_tts',
            'available_tts_models' => $availableTts,
        ]);

        return [
            'ok' => true,
            'status' => 'connected',
            'message' => $msg,
            'voices_count' => count(self::OFFICIAL_PREBUILT_VOICES),
            'available_models' => $availableTts,
        ];
    }

    /**
     * Generate Indonesian speech using official Gemini Developer API and convert PCM -> WAV & MP3 via FFmpeg.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function synthesize(string $text, array $options = []): array
    {
        $trimmedText = trim($text);
        if ($trimmedText === '') {
            return ['ok' => false, 'message' => 'Teks narasi tidak boleh kosong.'];
        }

        $maxChars = (int) config('affiliate_ai.tts.google_gemini_tts.max_input_chars', 3500);
        if (mb_strlen($trimmedText) > $maxChars) {
            return ['ok' => false, 'message' => "Teks terlalu panjang (maksimal {$maxChars} karakter per permintaan TTS)."];
        }

        $apiKey = $this->resolveSharedApiKey();
        if (! $apiKey) {
            return ['ok' => false, 'message' => 'API Key Google Gemini belum tersedia. Silakan konfigurasi di halaman AI Provider terlebih dahulu.'];
        }

        $profile = $this->getProfile();
        $meta = $profile->metadata ?? [];

        $modelId = (string) ($options['model_id'] ?? $meta['model_id'] ?? config('affiliate_ai.tts.google_gemini_tts.default_model', 'gemini-2.5-flash-preview-tts'));
        $modelId = preg_replace('/^models\//', '', trim($modelId)) ?: '';
        if (! $this->isSupportedTtsModel($modelId)) {
            return ['ok' => false, 'message' => "Model '{$modelId}' tidak mendukung Gemini TTS (hanya model khusus TTS resmi yang diizinkan)."];
        }

        $voiceName = (string) ($options['voice_id'] ?? $profile->voice_id ?? 'Kore');
        if (! $this->isSupportedVoice($voiceName)) {
            return ['ok' => false, 'message' => "Voice '{$voiceName}' tidak termasuk dalam daftar prebuilt voice resmi Gemini TTS."];
        }

        $styleInstruction = trim((string) ($options['style_instruction'] ?? $meta['style_instruction'] ?? ''));
        $pacePreset = (string) ($options['pace_preset'] ?? $meta['pace_preset'] ?? 'normal');
        $paceCue = match ($pacePreset) {
            'slow' => 'dengan tempo agak pelan dan tenang',
            'fast' => 'dengan tempo sedikit lebih cepat namun tetap jelas',
            default => 'dengan tempo sedang dan natural',
        };

        $promptText = $styleInstruction !== ''
            ? "{$styleInstruction} ({$paceCue}, Bahasa Indonesia):\n{$trimmedText}"
            : "Bacakan teks berikut dalam Bahasa Indonesia {$paceCue}:\n{$trimmedText}";

        $baseUrl = rtrim($this->geminiAiProvider->getRecord()->base_url ?: 'https://generativelanguage.googleapis.com/v1beta', '/');
        $timeout = (int) config('affiliate_ai.tts.google_gemini_tts.timeout', 45);
        $maxRetries = min(2, max(0, (int) ($options['max_retries'] ?? config('affiliate_ai.tts.google_gemini_tts.max_retries', 1))));

        $url = "{$baseUrl}/models/" . rawurlencode($modelId) . ':generateContent';
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $promptText],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $voiceName,
                        ],
                    ],
                ],
            ],
        ];

        $lastError = 'Gagal menghubungi Google Gemini TTS API.';

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $resp = Http::timeout($timeout)
                    ->withHeaders([
                        'x-goog-api-key' => $apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post($url, $payload);

                if ($resp->successful()) {
                    $inlineData = data_get($resp->json(), 'candidates.0.content.parts.0.inlineData');
                    if (! is_array($inlineData) || empty($inlineData['data'])) {
                        return [
                            'ok' => false,
                            'message' => 'Response Gemini TTS berhasil tetapi tidak mengandung data audio (inlineData kosong).',
                        ];
                    }

                    $mimeType = (string) ($inlineData['mimeType'] ?? 'audio/L16;codec=pcm;rate=24000');
                    $subdir = (string) ($options['storage_subdir'] ?? 'tts/previews');
                    $converted = $this->audioConverter->decodeAndConvert((string) $inlineData['data'], $mimeType, $subdir);

                    if (! ($converted['ok'] ?? false)) {
                        return [
                            'ok' => false,
                            'message' => $converted['message'] ?? 'Gagal memproses audio hasil Gemini TTS.',
                        ];
                    }

                    $this->logger->log('tts', 'gemini_tts_synthesized', "Audio Bahasa Indonesia berhasil dibuat via Gemini TTS ({$modelId}, voice {$voiceName}, durasi {$converted['duration_seconds']}s).", 'info', [
                        'provider' => 'google_gemini_tts',
                        'model_id' => $modelId,
                        'voice_id' => $voiceName,
                        'duration_seconds' => $converted['duration_seconds'],
                    ]);

                    return [
                        'ok' => true,
                        'provider' => $this->getSlug(),
                        'model_id' => $modelId,
                        'voice_id' => $voiceName,
                        'language_code' => 'id-ID',
                        'audio_base64' => $converted['mp3_base64'],
                        'mime_type' => $converted['mime_type'],
                        'file_path' => $converted['mp3_path'],
                        'master_wav_path' => $converted['wav_path'],
                        'metadata' => [
                            'duration_seconds' => $converted['duration_seconds'],
                            'sample_rate' => $converted['sample_rate'],
                            'channels' => $converted['channels'],
                            'wav_size_bytes' => $converted['wav_size_bytes'],
                            'mp3_size_bytes' => $converted['mp3_size_bytes'],
                            'source_mime_type' => $mimeType,
                        ],
                    ];
                }

                $status = $resp->status();
                $lastError = $this->mapHttpError($status, $resp->json());

                // Dilarang retry untuk 400, 401, 403, 404, 429 (quota/rate limit/invalid key)
                if ($status < 500) {
                    $this->logger->log('tts', 'gemini_tts_client_error', $lastError, 'warning', ['http_status' => $status, 'model_id' => $modelId]);

                    return [
                        'ok' => false,
                        'http_status' => $status,
                        'message' => $lastError,
                    ];
                }
            } catch (ConnectionException $e) {
                $lastError = 'Request timeout atau gangguan jaringan ke Google Gemini TTS: ' . $this->logger->redact($e->getMessage());
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'message' => 'Kesalahan internal Gemini TTS: ' . $this->logger->redact($e->getMessage()),
                ];
            }
        }

        $this->logger->log('tts', 'gemini_tts_failed', $lastError, 'error', ['model_id' => $modelId]);

        return ['ok' => false, 'message' => $lastError];
    }

    private function mapHttpError(int $status, mixed $body): string
    {
        $apiMessage = is_array($body) ? (string) data_get($body, 'error.message', '') : '';
        $apiStatus = is_array($body) ? (string) data_get($body, 'error.status', '') : '';
        $detail = $apiMessage !== '' ? ' (' . $this->logger->redact($apiMessage) . ')' : '';

        if ($status === 429 || strtoupper($apiStatus) === 'RESOURCE_EXHAUSTED') {
            return 'Rate limit atau kuota Google Gemini TTS terlampaui (HTTP 429 / RESOURCE_EXHAUSTED). Periksa batas kuota di Google AI Studio.' . $detail;
        }

        return match ($status) {
            400 => 'Permintaan Gemini TTS tidak valid atau API Key salah (HTTP 400).' . $detail,
            401, 403 => 'API Key Google Gemini tidak valid atau akses ditolak (HTTP ' . $status . ').' . $detail,
            404 => 'Model Gemini TTS tidak ditemukan atau belum tersedia untuk akun Anda (HTTP 404).' . $detail,
            500, 503 => 'Layanan Google Gemini TTS mengalami gangguan sementara (HTTP ' . $status . ').',
            default => 'Google Gemini TTS mengembalikan error (HTTP ' . $status . ').' . $detail,
        };
    }
}
