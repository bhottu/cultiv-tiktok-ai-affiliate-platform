<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PublishingSchedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'video_project_id', 'social_account_id', 'platform',
        'scheduled_for', 'timezone', 'status', 'idempotency_key',
        'external_publish_id', 'external_post_url', 'product_tag_status',
        'error_message', 'retry_count', 'metadata', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function videoProject(): BelongsTo
    {
        return $this->belongsTo(VideoProject::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
