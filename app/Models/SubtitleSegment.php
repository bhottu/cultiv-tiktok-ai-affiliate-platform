<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubtitleSegment extends Model
{
    protected $fillable = [
        'video_project_id', 'segment_order', 'start_seconds', 'end_seconds',
        'text', 'highlight_words', 'timing_source',
    ];

    protected function casts(): array
    {
        return [
            'start_seconds' => 'float',
            'end_seconds' => 'float',
            'highlight_words' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(VideoProject::class, 'video_project_id');
    }
}
