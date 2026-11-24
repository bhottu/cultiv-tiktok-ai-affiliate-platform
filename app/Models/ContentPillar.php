<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentPillar extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'description', 'goal', 'target_ratio_percent', 'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function ideas(): HasMany
    {
        return $this->hasMany(ContentIdea::class);
    }
}
