<?php

namespace App\Modules\ContentGeneration;

use App\Models\GeneratedContent;
use App\Modules\AICore\AIProviderManager;
use Illuminate\Database\Eloquent\Collection;

class ContentGenerationService
{
    public function __construct(
        private readonly AIProviderManager $aiManager,
    ) {}

    public function listRecent(int $limit = 20): Collection
    {
        return GeneratedContent::with(['campaign', 'product'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function isReady(): bool
    {
        return $this->aiManager->hasAnyConfiguredProvider();
    }

    public function activeProviderName(): string
    {
        return $this->aiManager->driver()->getName();
    }
}
