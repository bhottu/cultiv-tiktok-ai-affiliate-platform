<?php

namespace App\Modules\AICore;

use App\Contracts\AIProviderInterface;
use App\Models\AiProvider;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\AIProviders\GeminiProvider;
use App\Modules\AIProviders\OpenRouterProvider;
use App\Modules\Settings\ApplicationSettingService;
use InvalidArgumentException;

class AIProviderManager
{
    /** @var array<string, AIProviderInterface> */
    private array $providers = [];

    public function __construct(
        OpenRouterProvider $openRouter,
        GeminiProvider $gemini,
        private readonly ApplicationSettingService $settings,
        private readonly ActivityLogger $logger,
    ) {
        $this->register($openRouter);
        $this->register($gemini);
    }

    public function register(AIProviderInterface $provider): void
    {
        $this->providers[$provider->getSlug()] = $provider;
    }

    public function getDefaultProviderSlug(): string
    {
        $dbDefault = AiProvider::where('is_default', true)->where('is_active', true)->value('slug');
        if ($dbDefault && isset($this->providers[$dbDefault])) {
            return $dbDefault;
        }

        $configured = (string) config('affiliate_ai.ai.default_provider', 'openrouter');

        return isset($this->providers[$configured]) ? $configured : 'openrouter';
    }

    public function isFallbackEnabled(): bool
    {
        $val = $this->settings->get('ai.fallback_enabled');
        if ($val !== null) {
            return filter_var($val, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) config('affiliate_ai.ai.fallback_enabled', false);
    }

    public function getFallbackProviderSlug(?string $primarySlug = null): ?string
    {
        $primary = $primarySlug ?: $this->getDefaultProviderSlug();
        $configuredFallback = $this->settings->get('ai.fallback_provider');

        if (is_string($configuredFallback) && $configuredFallback !== '' && $configuredFallback !== $primary && isset($this->providers[$configuredFallback])) {
            return $configuredFallback;
        }

        foreach (array_keys($this->providers) as $slug) {
            if ($slug !== $primary) {
                return $slug;
            }
        }

        return null;
    }

    public function driver(?string $slug = null): AIProviderInterface
    {
        $key = $slug ?: $this->getDefaultProviderSlug();
        if (! isset($this->providers[$key])) {
            throw new InvalidArgumentException("AI provider [{$key}] belum terdaftar.");
        }

        return $this->providers[$key];
    }

    public function hasAnyConfiguredProvider(): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->isConfigured()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Execute chat using selected/default provider with optional, explicit, single-hop fallback.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function chatWithFallback(
        array $messages,
        ?string $preferredProvider = null,
        ?string $modelOverride = null,
        array $options = [],
    ): array {
        $primarySlug = $preferredProvider ?: $this->getDefaultProviderSlug();
        $primary = $this->driver($primarySlug);

        $primaryResult = $primary->chat($messages, $modelOverride, $options);
        if ($primaryResult['ok'] ?? false) {
            return array_merge($primaryResult, [
                'provider_used' => $primarySlug,
                'fallback_used' => false,
                'fallback_reason' => null,
            ]);
        }

        $primaryReason = (string) ($primaryResult['message'] ?? 'Provider utama gagal merespons.');

        // Fallback hanya dijalankan jika diaktifkan secara eksplisit dan maksimal 1 kali pindah provider
        if (! $this->isFallbackEnabled()) {
            return array_merge($primaryResult, [
                'provider_used' => $primarySlug,
                'fallback_used' => false,
                'fallback_reason' => null,
            ]);
        }

        $fallbackSlug = $this->getFallbackProviderSlug($primarySlug);
        if (! $fallbackSlug) {
            return array_merge($primaryResult, [
                'provider_used' => $primarySlug,
                'fallback_used' => false,
                'fallback_reason' => 'Tidak ada provider alternatif yang tersedia.',
            ]);
        }

        $fallbackProvider = $this->driver($fallbackSlug);
        if (! $fallbackProvider->isConfigured()) {
            return array_merge($primaryResult, [
                'provider_used' => $primarySlug,
                'fallback_used' => false,
                'fallback_reason' => "Provider alternatif ({$fallbackProvider->getName()}) belum dikonfigurasi.",
            ]);
        }

        $this->logger->log(
            'ai_core',
            'provider_fallback_triggered',
            "Fallback AI dari {$primary->getName()} ke {$fallbackProvider->getName()}. Alasan: {$primaryReason}",
            'warning',
            [
                'primary_provider' => $primarySlug,
                'fallback_provider' => $fallbackSlug,
                'reason' => $primaryReason,
            ],
        );

        // Jangan kirim modelOverride milik provider lama ke provider alternatif agar tidak mismatch model ID
        $fallbackOptions = $options;
        $fallbackOptions['max_retries'] = 0;

        $fallbackResult = $fallbackProvider->chat($messages, null, $fallbackOptions);

        if ($fallbackResult['ok'] ?? false) {
            $this->logger->log(
                'ai_core',
                'provider_fallback_succeeded',
                "Permintaan AI berhasil dipenuhi melalui provider fallback {$fallbackProvider->getName()} ({$fallbackResult['model']}).",
                'info',
                [
                    'provider_used' => $fallbackSlug,
                    'primary_provider' => $primarySlug,
                    'model' => $fallbackResult['model'] ?? null,
                ],
            );

            return array_merge($fallbackResult, [
                'provider_used' => $fallbackSlug,
                'fallback_used' => true,
                'fallback_reason' => $primaryReason,
            ]);
        }

        $fallbackReason = (string) ($fallbackResult['message'] ?? 'Provider fallback juga gagal.');
        $this->logger->log(
            'ai_core',
            'provider_fallback_failed',
            "Fallback ke {$fallbackProvider->getName()} juga gagal: {$fallbackReason}",
            'error',
            [
                'primary_provider' => $primarySlug,
                'fallback_provider' => $fallbackSlug,
            ],
        );

        return [
            'ok' => false,
            'provider_used' => $fallbackSlug,
            'fallback_used' => true,
            'fallback_reason' => $primaryReason,
            'message' => "Provider utama ({$primary->getName()}) gagal: {$primaryReason} | Fallback ({$fallbackProvider->getName()}) juga gagal: {$fallbackReason}",
        ];
    }

    /**
     * @return array<int, array{slug: string, name: string, configured: bool, is_default: bool}>
     */
    public function available(): array
    {
        $defaultSlug = $this->getDefaultProviderSlug();

        return array_values(array_map(fn (AIProviderInterface $p) => [
            'slug' => $p->getSlug(),
            'name' => $p->getName(),
            'configured' => $p->isConfigured(),
            'is_default' => $p->getSlug() === $defaultSlug,
        ], $this->providers));
    }
}
