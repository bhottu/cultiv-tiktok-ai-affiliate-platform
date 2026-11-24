<?php

namespace App\Modules\TextToSpeech;

use App\Contracts\TextToSpeechInterface;
use App\Models\VoiceProfile;
use App\Modules\ActivityLogs\ActivityLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleCloudTTSProvider implements TextToSpeechInterface
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function getSlug(): string
    {
        return 'google_cloud_tts';
    }

    public function getName(): string
    {
        return 'Google Cloud Text-to-Speech';
    }

    public function getProfile(): VoiceProfile
    {
        return VoiceProfile::firstOrCreate(
            ['provider_slug' => 'google_cloud_tts', 'is_default' => true],
            [
                'name' => 'Google Cloud TTS — Bahasa Indonesia',
                'language_code' => config('affiliate_ai.tts.google_cloud_tts.language_code', 'id-ID'),
                'voice_id' => config('affiliate_ai.tts.google_cloud_tts.voice_name') ?: null,
                'speaking_rate' => (float) config('affiliate_ai.tts.google_cloud_tts.speaking_rate', 1.0),
                'pitch' => (float) config('affiliate_ai.tts.google_cloud_tts.pitch', 0.0),
                'volume_gain_db' => (float) config('affiliate_ai.tts.google_cloud_tts.volume_gain_db', 0.0),
                'is_active' => true,
                'connection_status' => 'not_configured',
                'metadata' => [
                    'endpoint' => config('affiliate_ai.tts.google_cloud_tts.endpoint', 'https://texttospeech.googleapis.com/v1'),
                ],
            ]
        );
    }

    public function resolveApiKey(): ?string
    {
        $profile = $this->getProfile();
        $dbKey = $profile->getDecryptedCredential();
        if ($dbKey) {
            return $dbKey;
        }

        $envKey = trim((string) config('affiliate_ai.tts.google_cloud_tts.api_key', ''));

        return $envKey !== '' ? $envKey : null;
    }

    public function isConfigured(): bool
    {
        return $this->resolveApiKey() !== null;
    }

    public function testConnection(): array
    {
        $profile = $this->getProfile();
        $apiKey = $this->resolveApiKey();

        if (! $apiKey) {
            $msg = 'Kredensial Google Cloud Text-to-Speech belum dikonfigurasi. Simpan API key terlebih dahulu.';
            $profile->update([
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

        $res = $this->listVoices($profile->language_code ?: 'id-ID', true);
        if (! $res['ok']) {
            $msg = $res['message'] ?? 'Koneksi Google Cloud TTS gagal.';
            $profile->update([
                'connection_status' => 'failed',
                'last_tested_at' => now(),
                'last_error' => $msg,
            ]);
            $this->logger->log('tts', 'test_connection', $msg, 'warning', ['provider' => 'google_cloud_tts']);

            return [
                'ok' => false,
                'status' => 'failed',
                'message' => $msg,
            ];
        }

        $count = count($res['voices']);
        $msg = "Koneksi Google Cloud TTS berhasil. Ditemukan {$count} suara Bahasa Indonesia ({$profile->language_code}).";
        $profile->update([
            'connection_status' => 'connected',
            'last_tested_at' => now(),
            'last_error' => null,
        ]);
        $this->logger->log('tts', 'test_connection', $msg, 'info', ['provider' => 'google_cloud_tts', 'voices_count' => $count]);

        return [
            'ok' => true,
            'status' => 'connected',
            'message' => $msg,
            'voices_count' => $count,
        ];
    }

    public function listVoices(string $languageCode = 'id-ID', bool $forceRefresh = false): array
    {
        $apiKey = $this->resolveApiKey();
        if (! $apiKey) {
            return [
                'ok' => false,
                'voices' => [],
                'message' => 'Kredensial Google Cloud TTS belum diisi.',
            ];
        }

        $cacheKey = 'google_tts_voices_' . $languageCode;
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        if (! $forceRefresh && Cache::has($cacheKey)) {
            return [
                'ok' => true,
                'voices' => Cache::get($cacheKey, []),
            ];
        }

        $endpoint = rtrim((string) config('affiliate_ai.tts.google_cloud_tts.endpoint', 'https://texttospeech.googleapis.com/v1'), '/');

        try {
            $resp = Http::timeout(20)->get($endpoint . '/voices', [
                'languageCode' => $languageCode,
                'key' => $apiKey,
            ]);

            if ($resp->failed()) {
                $status = $resp->status();
                $errDetail = (string) data_get($resp->json(), 'error.message', '');
                $msg = match ($status) {
                    400, 401, 403 => 'API key Google Cloud TTS tidak valid atau Text-to-Speech API belum diaktifkan di Google Cloud Console (HTTP ' . $status . ').',
                    default => 'Gagal mengambil daftar suara Google Cloud TTS (HTTP ' . $status . ').',
                };
                if ($errDetail !== '') {
                    $msg .= ' ' . $this->logger->redact($errDetail);
                }

                return [
                    'ok' => false,
                    'voices' => [],
                    'message' => $msg,
                ];
            }

            $rawVoices = $resp->json('voices', []);
            $voices = array_values(array_map(fn ($v) => [
                'name' => (string) ($v['name'] ?? ''),
                'gender' => (string) ($v['ssmlGender'] ?? 'NEUTRAL'),
                'language_codes' => (array) ($v['languageCodes'] ?? [$languageCode]),
                'natural_sample_rate_hertz' => (int) ($v['naturalSampleRateHertz'] ?? 24000),
            ], $rawVoices));

            Cache::put($cacheKey, $voices, now()->addMinutes(30));

            return [
                'ok' => true,
                'voices' => $voices,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'voices' => [],
                'message' => 'Gagal menghubungi Google Cloud TTS: ' . $this->logger->redact($e->getMessage()),
            ];
        }
    }

    public function synthesize(string $text, array $options = []): array
    {
        $apiKey = $this->resolveApiKey();
        if (! $apiKey) {
            return [
                'ok' => false,
                'message' => 'Kredensial Google Cloud TTS belum dikonfigurasi. Tidak dapat membuat audio preview.',
            ];
        }

        $profile = $this->getProfile();
        $languageCode = (string) ($options['language_code'] ?? $profile->language_code ?: 'id-ID');
        $voiceName = (string) ($options['voice_id'] ?? $profile->voice_id ?? '');
        $speakingRate = (float) ($options['speaking_rate'] ?? $profile->speaking_rate ?? 1.0);
        $pitch = (float) ($options['pitch'] ?? $profile->pitch ?? 0.0);
        $volumeGainDb = (float) ($options['volume_gain_db'] ?? $profile->volume_gain_db ?? 0.0);

        $voicePayload = ['languageCode' => $languageCode];
        if ($voiceName !== '') {
            $voicePayload['name'] = $voiceName;
        }

        $endpoint = rtrim((string) config('affiliate_ai.tts.google_cloud_tts.endpoint', 'https://texttospeech.googleapis.com/v1'), '/');

        try {
            $resp = Http::timeout(30)->post($endpoint . '/text:synthesize?key=' . urlencode($apiKey), [
                'input' => ['text' => $text],
                'voice' => $voicePayload,
                'audioConfig' => [
                    'audioEncoding' => 'MP3',
                    'speakingRate' => max(0.25, min(4.0, $speakingRate)),
                    'pitch' => max(-20.0, min(20.0, $pitch)),
                    'volumeGainDb' => max(-96.0, min(16.0, $volumeGainDb)),
                ],
            ]);

            if ($resp->failed()) {
                $detail = $this->logger->redact((string) data_get($resp->json(), 'error.message', ''));

                return [
                    'ok' => false,
                    'message' => 'Gagal menghasilkan audio Google Cloud TTS (HTTP ' . $resp->status() . '). ' . $detail,
                ];
            }

            $audioContent = (string) $resp->json('audioContent', '');
            if ($audioContent === '') {
                return [
                    'ok' => false,
                    'message' => 'Google Cloud TTS tidak mengembalikan data audio.',
                ];
            }

            return [
                'ok' => true,
                'audio_base64' => $audioContent,
                'mime_type' => 'audio/mpeg',
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Gagal melakukan sintesis suara: ' . $this->logger->redact($e->getMessage()),
            ];
        }
    }
}
