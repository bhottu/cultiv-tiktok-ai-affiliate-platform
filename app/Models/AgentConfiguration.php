<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentConfiguration extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'module',
        'role_description',
        'ai_model_configuration_id',
        'system_prompt',
        'status',
        'is_enabled',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    public function modelConfiguration(): BelongsTo
    {
        return $this->belongsTo(AiModelConfiguration::class, 'ai_model_configuration_id');
    }
}
