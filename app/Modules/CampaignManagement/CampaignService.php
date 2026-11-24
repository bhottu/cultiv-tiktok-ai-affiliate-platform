<?php

namespace App\Modules\CampaignManagement;

use App\Models\Campaign;
use Illuminate\Database\Eloquent\Collection;

class CampaignService
{
    public function all(): Collection
    {
        return Campaign::withCount('contents')->latest()->get();
    }
}
