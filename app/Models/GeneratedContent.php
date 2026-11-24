<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class GeneratedContent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'campaign_id', 'content_idea_id', 'affiliate_product_id',
        'ai_model_configuration_id', 'title', 'category', 'product_category_id', 'subcategory',
        'target_audience', 'narration_style', 'content_format', 'marketing_angle',
        'experience_mode', 'experience_notes', 'target_duration_seconds', 'hook', 'opening',
        'main_narration', 'supporting_explanation', 'closing', 'script_text',
        'caption', 'hashtags', 'cta', 'disclosure_text', 'suggested_visual_scenes',
        'status', 'qc_report', 'approved_by', 'approved_at', 'approval_notes',
        'revision_count', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'hashtags' => 'array',
            'suggested_visual_scenes' => 'array',
            'experience_notes' => 'array',
            'qc_report' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(ContentIdea::class, 'content_idea_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(AffiliateProduct::class, 'affiliate_product_id');
    }

    public function affiliateProduct(): BelongsTo
    {
        return $this->product();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function videoProject(): HasOne
    {
        return $this->hasOne(VideoProject::class)->latestOfMany();
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class)->orderBy('display_order');
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ContentApprovalLog::class)->latest('created_at');
    }

    public function buildFullNarration(): string
    {
        $parts = array_filter([
            trim((string) $this->hook),
            trim((string) $this->opening),
            trim((string) $this->main_narration),
            trim((string) $this->supporting_explanation),
            trim((string) $this->closing),
            trim((string) $this->cta),
        ], fn (string $s) => $s !== '');

        if (! empty($parts)) {
            return implode("\n\n", $parts);
        }

        return trim((string) $this->script_text);
    }
}
