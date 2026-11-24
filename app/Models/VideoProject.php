<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class VideoProject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'generated_content_id', 'voice_profile_id', 'voiceover_asset_id',
        'bg_music_asset_id', 'bg_music_volume', 'voiceover_volume', 'subtitle_enabled',
        'subtitle_style', 'cta_text', 'title', 'composition_id', 'template_slug',
        'product_category_id', 'visual_theme_preset',
        'aspect_ratio', 'width', 'height', 'fps', 'duration_seconds', 'status',
        'qc_status', 'approval_status', 'approved_by', 'approved_at', 'approval_notes',
        'subtitle_path', 'output_video_path', 'thumbnail_path', 'composition_props', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'float',
            'bg_music_volume' => 'float',
            'voiceover_volume' => 'float',
            'subtitle_enabled' => 'boolean',
            'subtitle_style' => 'array',
            'composition_props' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(GeneratedContent::class, 'generated_content_id');
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function voiceoverAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'voiceover_asset_id');
    }

    public function bgMusicAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'bg_music_asset_id');
    }

    public function scenes(): HasMany
    {
        return $this->hasMany(VideoScene::class)->orderBy('scene_order');
    }

    public function subtitles(): HasMany
    {
        return $this->hasMany(SubtitleSegment::class)->orderBy('segment_order');
    }

    public function renderJobs(): HasMany
    {
        return $this->hasMany(VideoRenderJob::class)->latest();
    }

    public function latestRenderJob(): HasOne
    {
        return $this->hasOne(VideoRenderJob::class)->latestOfMany();
    }
}
