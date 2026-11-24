<?php

namespace App\Jobs;

use App\Enums\WorkflowStatus;
use App\Models\GeneratedContent;
use App\Modules\ActivityLogs\ActivityLogger;
use App\Modules\AICore\AIProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateContentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $generatedContentId,
        public readonly ?string $modelOverride = null,
        public readonly ?string $providerOverride = null,
    ) {}

    public function handle(AIProviderManager $aiManager, ActivityLogger $logger): void
    {
        $content = GeneratedContent::find($this->generatedContentId);
        if (! $content) {
            return;
        }

        if (! $aiManager->hasAnyConfiguredProvider()) {
            $content->update(['status' => WorkflowStatus::WAITING_CREDENTIALS->value]);
            $logger->log(
                'content_generation',
                'generate_skipped',
                'Generasi konten ditunda karena belum ada AI provider (OpenRouter / Google Gemini) yang dikonfigurasi.',
                'warning',
                ['generated_content_id' => $content->id],
                GeneratedContent::class,
                $content->id,
                $content->user_id,
            );

            return;
        }

        $content->update(['status' => WorkflowStatus::PROCESSING->value]);

        $response = $aiManager->chatWithFallback(
            [
                ['role' => 'system', 'content' => 'Anda adalah Content Strategist untuk akun TikTok tanaman hias Plant Coaction (@plant.coaction). Gunakan Bahasa Indonesia yang natural dan edukatif.'],
                ['role' => 'user', 'content' => "Buat konsep naskah singkat untuk judul: {$content->title}"],
            ],
            $this->providerOverride,
            $this->modelOverride,
        );

        if (! ($response['ok'] ?? false)) {
            $content->update(['status' => WorkflowStatus::FAILED->value]);
            throw new \RuntimeException($response['message'] ?? 'Generasi konten AI gagal.');
        }

        $content->update([
            'script_text' => $response['content'] ?? $content->script_text,
            'status' => WorkflowStatus::PENDING_APPROVAL->value,
            'metadata' => array_merge($content->metadata ?? [], [
                'provider_used' => $response['provider_used'] ?? null,
                'model_used' => $response['model'] ?? null,
                'fallback_used' => $response['fallback_used'] ?? false,
                'fallback_reason' => $response['fallback_reason'] ?? null,
            ]),
        ]);

        $logger->log(
            'content_generation',
            'generate_completed',
            "Draft konten #{$content->id} selesai diproses AI ({$response['provider_used']}) dan menunggu persetujuan.",
            'info',
            [
                'provider_used' => $response['provider_used'] ?? null,
                'model' => $response['model'] ?? null,
                'fallback_used' => $response['fallback_used'] ?? false,
            ],
            GeneratedContent::class,
            $content->id,
            $content->user_id,
        );
    }

    public function failed(Throwable $exception): void
    {
        $content = GeneratedContent::find($this->generatedContentId);
        if ($content) {
            $content->update(['status' => WorkflowStatus::FAILED->value]);
        }

        app(ActivityLogger::class)->log(
            'content_generation',
            'generate_failed',
            'Job generasi konten gagal: ' . $exception->getMessage(),
            'error',
            ['generated_content_id' => $this->generatedContentId],
            GeneratedContent::class,
            $this->generatedContentId,
        );
    }
}
