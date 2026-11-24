<?php

namespace App\Http\Controllers\Settings;

use App\Events\ProviderConnectionTested;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\Settings\ApplicationSettingService;
use App\Modules\TextToSpeech\GeminiTTSProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeminiTtsSettingController extends Controller
{
    public function __construct(
        private readonly GeminiTTSProvider $geminiTts,
        private readonly ApplicationSettingService $settings,
        private readonly ActivityLogger $logger,
    ) {}

    public function updateProviderSelection(Request $request): RedirectResponse
    {
        Gate::authorize('manage-providers');

        $data = $request->validate([
            'default_tts_provider' => ['required', 'in:google_gemini_tts,google_cloud_tts'],
        ], [
            'default_tts_provider.in' => 'Provider TTS harus Google Gemini TTS atau Google Cloud TTS.',
        ]);

        $this->settings->set(
            'tts.default_provider',
            $data['default_tts_provider'],
            'tts',
            false,
            'Provider TTS utama aplikasi.',
        );

        $this->logger->log('tts', 'select_default_provider', "Provider TTS default diubah ke {$data['default_tts_provider']}.");

        return redirect()
            ->route('settings.tts.index', ['tab' => $data['default_tts_provider']])
            ->with('status', 'Provider Text-to-Speech utama berhasil diperbarui.');
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage-providers');

        $allowedModels = array_keys(GeminiTTSProvider::SUPPORTED_TTS_MODELS);
        $allowedVoices = array_column(GeminiTTSProvider::OFFICIAL_PREBUILT_VOICES, 'name');

        $data = $request->validate([
            'model_id' => ['required', 'string', Rule::in($allowedModels)],
            'voice_id' => ['required', 'string', Rule::in($allowedVoices)],
            'language_code' => ['required', 'string', 'in:id-ID'],
            'pace_preset' => ['required', 'in:slow,normal,fast'],
            'style_instruction' => ['nullable', 'string', 'max:350'],
        ], [
            'model_id.in' => 'Model yang dipilih tidak mendukung Gemini TTS resmi.',
            'voice_id.in' => 'Voice yang dipilih tidak tersedia dalam daftar resmi Gemini TTS.',
            'language_code.in' => 'Bahasa dubbing difokuskan pada Bahasa Indonesia (id-ID).',
        ]);

        $profile = $this->geminiTts->getProfile();
        $profile->language_code = $data['language_code'];
        $profile->voice_id = $data['voice_id'];
        $profile->metadata = array_merge($profile->metadata ?? [], [
            'model_id' => $data['model_id'],
            'pace_preset' => $data['pace_preset'],
            'style_instruction' => trim((string) ($data['style_instruction'] ?? '')),
        ]);
        $profile->save();

        $this->logger->log('tts', 'update_gemini_tts', "Pengaturan Google Gemini TTS diperbarui ({$data['model_id']}, voice {$data['voice_id']}).");

        return redirect()
            ->route('settings.tts.index', ['tab' => 'google_gemini_tts'])
            ->with('status', 'Pengaturan Google Gemini TTS berhasil disimpan.');
    }

    public function testConnection(): JsonResponse
    {
        Gate::authorize('manage-providers');

        $result = $this->geminiTts->testConnection();
        event(new ProviderConnectionTested('tts', 'google_gemini_tts', $result['ok'], $result['message']));

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('manage-providers');

        $data = $request->validate([
            'text' => ['required', 'string', 'min:5', 'max:600'],
            'model_id' => ['nullable', 'string', 'max:120'],
            'voice_id' => ['nullable', 'string', 'max:80'],
            'pace_preset' => ['nullable', 'in:slow,normal,fast'],
            'style_instruction' => ['nullable', 'string', 'max:350'],
        ], [
            'text.required' => 'Teks preview suara wajib diisi.',
            'text.max' => 'Teks preview maksimal 600 karakter.',
        ]);

        $res = $this->geminiTts->synthesize($data['text'], array_filter([
            'model_id' => $data['model_id'] ?? null,
            'voice_id' => $data['voice_id'] ?? null,
            'pace_preset' => $data['pace_preset'] ?? null,
            'style_instruction' => $data['style_instruction'] ?? null,
            'storage_subdir' => 'tts/previews',
        ], fn ($v) => $v !== null));

        if (! ($res['ok'] ?? false)) {
            return response()->json($res, (int) ($res['http_status'] ?? 422) >= 400 ? 422 : 422);
        }

        // Simpan metadata asset preview privat (terikat ke user_id, tanpa token/API key)
        $asset = MediaAsset::create([
            'user_id' => $request->user()->id,
            'asset_type' => 'tts_preview',
            'source_type' => 'tts_generated',
            'license_status' => 'cleared',
            'disk' => 'local',
            'file_path' => (string) $res['file_path'],
            'mime_type' => (string) ($res['mime_type'] ?? 'audio/mpeg'),
            'file_size_bytes' => (int) data_get($res, 'metadata.mp3_size_bytes', 0),
            'duration_seconds' => (float) data_get($res, 'metadata.duration_seconds', 0),
            'metadata' => [
                'provider' => 'google_gemini_tts',
                'model_id' => $res['model_id'] ?? null,
                'voice_id' => $res['voice_id'] ?? null,
                'language_code' => 'id-ID',
                'master_wav_path' => $res['master_wav_path'] ?? null,
                'sample_rate' => data_get($res, 'metadata.sample_rate'),
                'channels' => data_get($res, 'metadata.channels'),
            ],
        ]);

        return response()->json([
            'ok' => true,
            'provider' => 'google_gemini_tts',
            'model_id' => $res['model_id'],
            'voice_id' => $res['voice_id'],
            'mime_type' => $res['mime_type'],
            'audio_base64' => $res['audio_base64'],
            'duration_seconds' => data_get($res, 'metadata.duration_seconds', 0),
            'sample_rate' => data_get($res, 'metadata.sample_rate', 24000),
            'download_url' => route('settings.tts.audio.download', $asset),
            'asset_id' => $asset->id,
        ]);
    }

    public function downloadAudio(Request $request, MediaAsset $asset): StreamedResponse
    {
        $user = $request->user();
        if (! $user || ! $user->is_active || ($asset->user_id !== $user->id && ! in_array($user->role, ['owner', 'admin'], true))) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses file audio ini.');
        }

        if (! in_array($asset->asset_type, ['tts_preview', 'voiceover'], true)) {
            abort(404);
        }

        if (! Storage::disk($asset->disk)->exists($asset->file_path)) {
            abort(404, 'File audio tidak ditemukan atau sudah dibersihkan.');
        }

        return Storage::disk($asset->disk)->download(
            $asset->file_path,
            'plant-coaction-tts-' . $asset->id . '.mp3',
            ['Content-Type' => $asset->mime_type ?: 'audio/mpeg']
        );
    }
}
