<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProviderConnectionTested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $providerType,
        public readonly string $providerSlug,
        public readonly bool $ok,
        public readonly string $message,
    ) {}
}
