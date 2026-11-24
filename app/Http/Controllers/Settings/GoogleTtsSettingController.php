<?php

namespace App\Http\Controllers\Settings;

use App\Events\ProviderConnectionTested;
use App\Http\Controllers\Controller;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\TextToSpeech\GeminiTTSProvider;
use App\Modules\TextToSpeech\GoogleCloudTTSProvider;
use App\Modules\TextToSpeech\TTSProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GoogleTtsSettingController extends Controller
{
    public function __construct(
        private readonly GoogleCloudTTSProvider $googleTts,
        private readonly GeminiTTSProvider $geminiTts,
        private readonly TTSProviderManager $ttsManager,
        private readonly ActivityLogger $logger,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('manage-providers');

        $defaultSlug = $this->ttsManager->getDefaultProviderSlug();
        $activeTab = (string) $request->query('tab', $defaultSlug);
        if (! in_array($activeTab, ['google_gemini_tts', 'google_cloud_tts'], true)) {
            $activeTab = $defaultSlug;
        }

        $googleProfile = $this->googleTts->getProfile();
        $geminiProfile = $this->geminiTts->getProfile();

        return view('settings.tts', [
            'activeTab' => $activeTab,
            'defaultTtsSlug' => $defaultSlug,
            'profile' => $googleProfile,
            'hasCredential' => $this->googleTts->isConfigured(),
            'maskedCredential' => $googleProfile->getMaskedCredential(),
            'geminiTtsProfile' => $geminiProfile,
            'geminiTtsHasKey' => $this->geminiTts->isConfigured(),
            'geminiTtsMaskedKey' => $this->geminiTts->getMaskedSharedApiKey(),
            'geminiTtsModels' => $this->geminiTts->availableModels(false),
            'geminiTtsVoices' => GeminiTTSProvider::OFFICIAL_PREBUILT_VOICES,
            'providersCatalog' => $this->ttsManager->catalog(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage-providers');

        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'min:10', 'max:255'],
            'clear_credential' => ['nullable', 'boolean'],
            'language_code' => ['required', 'string', 'in:id-ID'],
            'voice_id' => ['nullable', 'string', 'max:120'],
            'gender' => ['nullable', 'string', 'in:MALE,FEMALE,NEUTRAL'],
            'speaking_rate' => ['required', 'numeric', 'min:0.25', 'max:4.0'],
            'pitch' => ['required', 'numeric', 'min:-20', 'max:20'],
            'volume_gain_db' => ['required', 'numeric', 'min:-96', 'max:16'],
        ], [
            'api_key.min' => 'Kredensial API key Google Cloud TTS minimal 10 karakter.',
            'language_code.in' => 'Saat ini bahasa dubbing difokuskan pada Bahasa Indonesia (id-ID).',
            'speaking_rate.between' => 'Kecepatan bicara harus antara 0.25 hingga 4.0.',
        ]);

        $profile = $this->googleTts->getProfile();
        $profile->language_code = $data['language_code'];
        if (array_key_exists('voice_id', $data)) {
            $profile->voice_id = $data['voice_id'] ?: $profile->voice_id;
        }
        if (array_key_exists('gender', $data)) {
            $profile->gender = $data['gender'] ?: $profile->gender;
        }
        $profile->speaking_rate = (float) $data['speaking_rate'];
        $profile->pitch = (float) $data['pitch'];
        $profile->volume_gain_db = (float) $data['volume_gain_db'];

        if ($request->boolean('clear_credential')) {
            $profile->setRawCredential(null);
            $profile->connection_status = 'not_configured';
            $profile->last_error = null;
        } elseif (! empty($data['api_key'])) {
            $profile->setRawCredential($data['api_key']);
            $profile->connection_status = 'configured_untested';
            $profile->last_error = null;
        }

        $profile->save();
        $this->logger->log('tts', 'update_config', 'Pengaturan Google Cloud TTS diperbarui secara aman.');

        return redirect()
            ->route('settings.tts.index')
            ->with('status', 'Pengaturan Google Cloud Text-to-Speech berhasil disimpan.');
    }

    public function testConnection(): JsonResponse
    {
        Gate::authorize('manage-providers');

        $result = $this->googleTts->testConnection();
        event(new ProviderConnectionTested('tts', 'google_cloud_tts', $result['ok'], $result['message']));

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function voices(Request $request): JsonResponse
    {
        Gate::authorize('manage-providers');

        $force = $request->boolean('refresh', false);
        $res = $this->googleTts->listVoices('id-ID', $force);

        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('manage-providers');

        $data = $request->validate([
            'text' => ['required', 'string', 'min:5', 'max:400'],
            'voice_id' => ['nullable', 'string', 'max:120'],
            'speaking_rate' => ['nullable', 'numeric', 'min:0.25', 'max:4.0'],
            'pitch' => ['nullable', 'numeric', 'min:-20', 'max:20'],
            'volume_gain_db' => ['nullable', 'numeric', 'min:-96', 'max:16'],
        ], [
            'text.required' => 'Teks uji suara wajib diisi.',
        ]);

        $res = $this->googleTts->synthesize($data['text'], $data);

        return response()->json($res, $res['ok'] ? 200 : 422);
    }
}
