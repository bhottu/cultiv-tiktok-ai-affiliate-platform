<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoScene extends Model
{
    protected $fillable = [
        'video_project_id', 'media_asset_id', 'scene_order', 'title',
        'overlay_text', 'narration_segment', 'visual_recommendation',
        'duration_seconds', 'transition_type', 'motion_effect',
        'highlight_keywords', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'float',
            'highlight_keywords' => 'array',
            'metadata' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(VideoProject::class, 'video_project_id');
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
