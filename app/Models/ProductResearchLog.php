<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductResearchLog extends Model
{
    protected $fillable = [
        'user_id', 'source', 'query_term', 'category', 'status',
        'candidates_found', 'summary', 'metadata', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
