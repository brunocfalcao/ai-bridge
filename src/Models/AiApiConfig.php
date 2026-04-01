<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon|null $oauth_expires_at
 */
class AiApiConfig extends Model
{
    protected $fillable = [
        'team_id',
        'application_id',
        'purpose',
        'provider',
        'api_key',
        'model',
        'is_active',
        'oauth_access_token',
        'oauth_refresh_token',
        'oauth_expires_at',
        'priority',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'oauth_access_token' => 'encrypted',
            'oauth_refresh_token' => 'encrypted',
            'oauth_expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function hasOAuthToken(): bool
    {
        return ! empty($this->oauth_access_token);
    }

    public function isOAuthExpired(): bool
    {
        if (! $this->oauth_expires_at) {
            return false;
        }

        return $this->oauth_expires_at->isPast();
    }

    /**
     * Resolve the API key, preferring static keys over OAuth tokens.
     */
    public function resolveApiKey(): ?string
    {
        if ($this->api_key) {
            return $this->api_key;
        }

        if ($this->hasOAuthToken() && ! $this->isOAuthExpired()) {
            return $this->oauth_access_token;
        }

        return null;
    }

    public function team(): BelongsTo
    {
        $model = config('ai-bridge.models.team');

        return $model
            ? $this->belongsTo($model)
            : $this->belongsTo(static::class, 'id', 'id');
    }

    public function application(): BelongsTo
    {
        $model = config('ai-bridge.models.application');

        return $model
            ? $this->belongsTo($model)
            : $this->belongsTo(static::class, 'id', 'id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeForApplication(Builder $query, int $applicationId): Builder
    {
        return $query->where('application_id', $applicationId);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('team_id')->whereNull('application_id');
    }
}
