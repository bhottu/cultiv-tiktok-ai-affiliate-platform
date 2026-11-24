<?php

namespace App\Modules\ContentStrategy;

use App\Models\ContentIdea;
use App\Models\ContentPillar;
use Illuminate\Database\Eloquent\Collection;

class ContentStrategyService
{
    public function activePillars(): Collection
    {
        return ContentPillar::where('is_active', true)
            ->withCount('ideas')
            ->orderBy('id')
            ->get();
    }

    public function listIdeas(int $limit = 20): Collection
    {
        return ContentIdea::with(['pillar', 'product'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
