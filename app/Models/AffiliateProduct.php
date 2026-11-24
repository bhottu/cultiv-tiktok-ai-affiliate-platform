<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AffiliateProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'social_account_id', 'external_product_id', 'source_platform',
        'data_source_type', 'verification_status', 'affiliate_eligibility_status',
        'name', 'category', 'product_category_id', 'subcategory', 'brand', 'description',
        'features', 'specifications', 'target_audience', 'research_notes',
        'seller_name', 'price', 'promo_price',
        'commission_amount', 'commission_rate', 'rating', 'review_count', 'sales_count',
        'stock_status', 'product_url', 'image_url', 'affiliate_link', 'approval_status',
        'quality_score', 'analysis_result', 'analysis_score_breakdown', 'analyzed_at',
        'approved_by', 'approved_at', 'approval_notes', 'metadata', 'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'promo_price' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'rating' => 'decimal:2',
            'quality_score' => 'decimal:2',
            'features' => 'array',
            'specifications' => 'array',
            'analysis_result' => 'array',
            'analysis_score_breakdown' => 'array',
            'metadata' => 'array',
            'analyzed_at' => 'datetime',
            'approved_at' => 'datetime',
            'fetched_at' => 'datetime',
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

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function generatedContents(): HasMany
    {
        return $this->hasMany(GeneratedContent::class);
    }
}
