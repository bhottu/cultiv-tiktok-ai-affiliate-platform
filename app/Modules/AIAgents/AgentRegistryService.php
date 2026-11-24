<?php

namespace App\Modules\AIAgents;

use App\Models\AgentConfiguration;
use Illuminate\Database\Eloquent\Collection;

class AgentRegistryService
{
    public function all(): Collection
    {
        return AgentConfiguration::with('modelConfiguration.provider')
            ->orderBy('id')
            ->get();
    }
}
