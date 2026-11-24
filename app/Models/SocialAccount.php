<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class SocialAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'platform', 'handle', 'external_account_id', 'display_name',
        'avatar_url', 'status', 'affiliate_status', 'access_token_encrypted',
        'refresh_token_encrypted', 'token_expires_at', 'refresh_token_expires_at',
        'scopes', 'profile_stats', 'oauth_metadata', 'last_synced_at',
        'connected_at', 'disconnected_at', 'last_error',
    ];

    protected $hidden = [
        'access_token_encrypted',
        'refresh_token_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'scopes' => 'array',
            'profile_stats' => 'array',
            'oauth_metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function affiliateProducts(): HasMany
    {
        return $this->hasMany(AffiliateProduct::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function setEncryptedTokens(?string $accessToken, ?string $refreshToken = null): void
    {
        $this->access_token_encrypted = ($accessToken !== null && trim($accessToken) !== '')
            ? Crypt::encryptString(trim($accessToken))
            : null;

        if ($refreshToken !== null) {
            $this->refresh_token_encrypted = trim($refreshToken) !== ''
                ? Crypt::encryptString(trim($refreshToken))
                : null;
        }
    }

    public function clearTokens(): void
    {
        $this->access_token_encrypted = null;
        $this->refresh_token_encrypted = null;
        $this->token_expires_at = null;
        $this->refresh_token_expires_at = null;
    }

    public function getDecryptedAccessToken(): ?string
    {
        if (empty($this->access_token_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->access_token_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function getDecryptedRefreshToken(): ?string
    {
        if (empty($this->refresh_token_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->refresh_token_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->isPast();
    }

    public function isRefreshTokenExpired(): bool
    {
        if (! $this->refresh_token_expires_at) {
            return empty($this->refresh_token_encrypted);
        }

        return $this->refresh_token_expires_at->isPast();
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}
