<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoRenderJob extends Model
{
    protected $fillable = [
        'video_project_id', 'renderer', 'idempotency_key', 'status', 'stage',
        'progress_percent', 'attempts', 'started_at', 'completed_at', 'cancelled_at',
        'error_message', 'output_file_path', 'file_size_bytes', 'render_metrics',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'render_metrics' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(VideoProject::class, 'video_project_id');
    }
}
