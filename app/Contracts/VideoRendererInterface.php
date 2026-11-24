<?php

namespace App\Contracts;

interface VideoRendererInterface
{
    public function getSlug(): string;

    /**
     * Check local Remotion / FFmpeg binary & environment readiness.
     *
     * @return array{ready: bool, node: bool, ffmpeg: bool, remotion_installed: bool, message: string}
     */
    public function checkReadiness(): array;

    /**
     * Render a video project (implemented end-to-end in Fase 5).
     *
     * @param  array<string, mixed>  $props
     * @return array{ok: bool, status: string, output_path?: string, message: string}
     */
    public function render(string $compositionId, array $props, string $outputPath): array;
}
