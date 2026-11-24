<?php

namespace App\Modules\PublishingWorkflow;

use App\Models\PublishingSchedule;
use Illuminate\Database\Eloquent\Collection;

class PublishingWorkflowService
{
    public function upcoming(int $limit = 20): Collection
    {
        return PublishingSchedule::with(['videoProject', 'socialAccount'])
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get();
    }
}
