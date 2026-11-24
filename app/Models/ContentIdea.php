<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentIdea extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'content_pillar_id', 'affiliate_product_id', 'product_name', 'title',
        'category', 'product_category_id', 'subcategory', 'target_audience', 'content_goal',
        'angle', 'format_type', 'content_format', 'marketing_angle', 'experience_mode',
        'experience_notes', 'research_analysis',
        'hook_concept', 'concept_summary', 'visual_recommendation', 'cta_recommendation',
        'estimated_duration_seconds', 'needs_fact_verification', 'saved_to_library',
        'status', 'priority', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'needs_fact_verification' => 'boolean',
            'saved_to_library' => 'boolean',
            'experience_notes' => 'array',
            'research_analysis' => 'array',
            'metadata' => 'array',
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

    public function pillar(): BelongsTo
    {
        return $this->belongsTo(ContentPillar::class, 'content_pillar_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(AffiliateProduct::class, 'affiliate_product_id');
    }

    public function affiliateProduct(): BelongsTo
    {
        return $this->product();
    }

    public function generatedContents(): HasMany
    {
        return $this->hasMany(GeneratedContent::class);
    }
}
