<?php

namespace App\Contracts;

interface SocialPlatformInterface
{
    public function getPlatformSlug(): string;

    public function isConfigured(): bool;

    /**
     * Return integration readiness status without fabricating connection state.
     *
     * @return array{platform: string, oauth_ready: bool, affiliate_api_verified: bool, phase_enabled: bool, message: string}
     */
    public function getReadinessStatus(): array;
}
