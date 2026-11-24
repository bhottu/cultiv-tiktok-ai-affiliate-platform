<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class AiProvider extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'driver',
        'base_url',
        'api_key_encrypted',
        'is_active',
        'is_default',
        'connection_status',
        'last_tested_at',
        'last_error',
        'settings',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'last_tested_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function models(): HasMany
    {
        return $this->hasMany(AiModelConfiguration::class);
    }

    public function setRawApiKey(?string $plainKey): void
    {
        $trimmed = $plainKey !== null ? trim($plainKey) : '';
        $this->api_key_encrypted = $trimmed !== '' ? Crypt::encryptString($trimmed) : null;
    }

    public function getDecryptedApiKey(): ?string
    {
        if (empty($this->api_key_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->api_key_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getMaskedApiKey(): ?string
    {
        $plain = $this->getDecryptedApiKey();
        if (! $plain) {
            return null;
        }

        $len = strlen($plain);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($plain, 0, 5) . str_repeat('*', max(6, $len - 9)) . substr($plain, -4);
    }

    public function hasApiKey(): bool
    {
        return ! empty($this->getDecryptedApiKey());
    }
}
