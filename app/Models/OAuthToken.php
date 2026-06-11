<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * @property int         $id
 * @property string      $provider        e.g. 'gmail'
 * @property string      $access_token    encrypted
 * @property string|null $refresh_token   encrypted
 * @property string|null $token_type
 * @property int|null    $expires_in
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
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

    public function setAccessTokenAttribute(string $value): void
    {
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }

    public function getAccessTokenAttribute(string $value): string
    {
        return Crypt::decryptString($value);
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function estaExpirado(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function esValido(): bool
    {
        return ! $this->estaExpirado();
    }

    public static function paraProveedor(string $provider): ?self
    {
        return static::where('provider', $provider)
            ->latest()
            ->first();
    }
}
