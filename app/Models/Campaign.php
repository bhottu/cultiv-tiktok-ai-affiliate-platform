<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'social_account_id', 'affiliate_product_id', 'name', 'slug',
        'objective', 'start_date', 'end_date', 'status', 'target_videos_per_day', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'settings' => 'array',
        ];
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function affiliateProduct(): BelongsTo
    {
        return $this->belongsTo(AffiliateProduct::class);
    }

    public function contents(): HasMany
    {
        return $this->hasMany(GeneratedContent::class);
    }
}
