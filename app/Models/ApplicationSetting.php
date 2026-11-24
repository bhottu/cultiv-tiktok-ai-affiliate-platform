<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ApplicationSetting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value_text',
        'value_json',
        'is_encrypted',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'is_encrypted' => 'boolean',
        ];
    }

    public function getResolvedValue(): mixed
    {
        if ($this->value_json !== null) {
            return $this->value_json;
        }

        if ($this->is_encrypted && $this->value_text) {
            try {
                return Crypt::decryptString($this->value_text);
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->value_text;
    }
}
