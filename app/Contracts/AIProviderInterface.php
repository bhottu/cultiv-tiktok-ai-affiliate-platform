<?php

namespace App\Contracts;

interface AIProviderInterface
{
    public function getSlug(): string;

    public function getName(): string;

    public function isConfigured(): bool;

    /**
     * @return array{ok: bool, status: string, message: string, latency_ms?: int, models_count?: int}
     */
    public function testConnection(): array;

    /**
     * @return array{ok: bool, models: array<int, array<string, mixed>>, message?: string}
     */
    public function listModels(bool $forceRefresh = false): array;

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array{ok: bool, content?: string, model?: string, usage?: array<string, mixed>, message?: string}
     */
    public function chat(array $messages, ?string $model = null, array $options = []): array;
}
