<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModelConfiguration extends Model
{
    protected $fillable = [
        'ai_provider_id',
        'model_id',
        'display_name',
        'task_role',
        'is_free',
        'is_default',
        'is_fallback',
        'context_length',
        'pricing_meta',
        'parameters',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'is_default' => 'boolean',
            'is_fallback' => 'boolean',
            'is_active' => 'boolean',
            'pricing_meta' => 'array',
            'parameters' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }
}
