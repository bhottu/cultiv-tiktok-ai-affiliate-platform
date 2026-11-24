<?php

namespace App\Modules\TextToSpeech;

use App\Contracts\TextToSpeechInterface;
use App\Modules\Settings\ApplicationSettingService;
use InvalidArgumentException;

class TTSProviderManager
{
    /** @var array<string, TextToSpeechInterface> */
    private array $providers = [];

    public function __construct(
        GoogleCloudTTSProvider $googleCloudTts,
        GeminiTTSProvider $geminiTts,
        private readonly ApplicationSettingService $settings,
    ) {
        $this->register($geminiTts);
        $this->register($googleCloudTts);
    }

    public function register(TextToSpeechInterface $provider): void
    {
        $this->providers[$provider->getSlug()] = $provider;
    }

    public function getDefaultProviderSlug(): string
    {
        $saved = $this->settings->get('tts.default_provider');
        if (is_string($saved) && isset($this->providers[$saved])) {
            return $saved;
        }

        $configured = (string) config('affiliate_ai.tts.default_provider', 'google_gemini_tts');

        return isset($this->providers[$configured]) ? $configured : 'google_gemini_tts';
    }

    public function driver(?string $slug = null): TextToSpeechInterface
    {
        $key = $slug ?: $this->getDefaultProviderSlug();
        if (! isset($this->providers[$key])) {
            throw new InvalidArgumentException("TTS provider [{$key}] belum diimplementasikan.");
        }

        return $this->providers[$key];
    }

    /**
     * @return array<int, array{slug: string, name: string, status: string, configured: bool, is_default: bool}>
     */
    public function catalog(): array
    {
        $default = $this->getDefaultProviderSlug();
        $gemini = $this->providers['google_gemini_tts'];
        $google = $this->providers['google_cloud_tts'];

        return [
            [
                'slug' => 'google_gemini_tts',
                'name' => $gemini->getName(),
                'status' => 'active',
                'configured' => $gemini->isConfigured(),
                'is_default' => $default === 'google_gemini_tts',
            ],
            [
                'slug' => 'google_cloud_tts',
                'name' => $google->getName(),
                'status' => 'active',
                'configured' => $google->isConfigured(),
                'is_default' => $default === 'google_cloud_tts',
            ],
            [
                'slug' => 'azure_speech',
                'name' => 'Microsoft Azure Speech Neural (id-ID)',
                'status' => 'planned_next_phase',
                'configured' => false,
                'is_default' => false,
            ],
            [
                'slug' => 'elevenlabs',
                'name' => 'ElevenLabs Multilingual / Voice Cloning',
                'status' => 'planned_next_phase',
                'configured' => false,
                'is_default' => false,
            ],
        ];
    }
}
