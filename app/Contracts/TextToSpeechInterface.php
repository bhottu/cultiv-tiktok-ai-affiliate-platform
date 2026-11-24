<?php

namespace App\Contracts;

interface TextToSpeechInterface
{
    public function getSlug(): string;

    public function getName(): string;

    public function isConfigured(): bool;

    /**
     * @return array{ok: bool, status: string, message: string, voices_count?: int}
     */
    public function testConnection(): array;

    /**
     * @return array{ok: bool, voices: array<int, array<string, mixed>>, message?: string}
     */
    public function listVoices(string $languageCode = 'id-ID', bool $forceRefresh = false): array;

    /**
     * @param  array{voice_id?: string, language_code?: string, speaking_rate?: float, pitch?: float, volume_gain_db?: float}  $options
     * @return array{ok: bool, audio_base64?: string, mime_type?: string, file_path?: string, message?: string}
     */
    public function synthesize(string $text, array $options = []): array;
}
