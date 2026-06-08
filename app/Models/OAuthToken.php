<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int         $id
 * @property string      $provider        e.g. 'gmail'
 * @property string      $access_token    encrypted
 * @property string|null $refresh_token   encrypted
 * @property string|null $token_type
 * @property int|null    $expires_in
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class OAuthToken extends Model
{
    protected $table = 'oauth_tokens';

    protected $fillable = [
        'provider',
        'access_token',
        'refresh_token',
        'token_type',
        'expires_in',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'immutable_datetime',
        'expires_in' => 'integer',
    ];

    // ──────────────────────────────────────────────
    // Encrypt / decrypt access_token transparently
    // ──────────────────────────────────────────────

    public function setAccessTokenAttribute(string $value): void
    {
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }

    public function getAccessTokenAttribute(string $value): string
    {
        return Crypt::decryptString($value);
    }

    // ──────────────────────────────────────────────
    // Encrypt / decrypt refresh_token transparently
    // ──────────────────────────────────────────────

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Find the latest token for a given provider, or null.
     */
    public static function forProvider(string $provider): ?self
    {
        return static::where('provider', $provider)
            ->latest()
            ->first();
    }
}
