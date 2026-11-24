<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class VoiceProfile extends Model
{
    protected $fillable = [
        'provider_slug',
        'name',
        'language_code',
        'voice_id',
        'gender',
        'speaking_rate',
        'pitch',
        'volume_gain_db',
        'is_default',
        'is_active',
        'credentials_encrypted',
        'connection_status',
        'last_tested_at',
        'last_error',
        'metadata',
    ];

    protected $hidden = [
        'credentials_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'speaking_rate' => 'float',
            'pitch' => 'float',
            'volume_gain_db' => 'float',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function setRawCredential(?string $plainCredential): void
    {
        $trimmed = $plainCredential !== null ? trim($plainCredential) : '';
        $this->credentials_encrypted = $trimmed !== '' ? Crypt::encryptString($trimmed) : null;
    }

    public function getDecryptedCredential(): ?string
    {
        if (empty($this->credentials_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->credentials_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getMaskedCredential(): ?string
    {
        $plain = $this->getDecryptedCredential();
        if (! $plain) {
            return null;
        }

        $len = strlen($plain);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return substr($plain, 0, 4) . str_repeat('*', max(6, $len - 8)) . substr($plain, -4);
    }

    public function hasCredential(): bool
    {
        return ! empty($this->getDecryptedCredential());
    }
}
