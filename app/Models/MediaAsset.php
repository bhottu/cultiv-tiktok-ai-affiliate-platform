<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'affiliate_product_id', 'generated_content_id', 'campaign_id',
        'product_category_id', 'category_tag', 'asset_type',
        'source_type', 'license_status', 'disk', 'file_path', 'original_name',
        'mime_type', 'file_size_bytes', 'width', 'height', 'duration_seconds',
        'display_order', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'float',
            'metadata' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(AffiliateProduct::class, 'affiliate_product_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(GeneratedContent::class, 'generated_content_id');
    }

    public function generatedContent(): BelongsTo
    {
        return $this->content();
    }
}
