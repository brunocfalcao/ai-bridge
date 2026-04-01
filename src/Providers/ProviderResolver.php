<?php

declare(strict_types=1);

namespace BrunoCFalcao\AiBridge\Providers;

use BrunoCFalcao\AiBridge\Contracts\ApplicationContract;
use BrunoCFalcao\AiBridge\Models\AiApiConfig;
use Illuminate\Support\Collection;

class ProviderResolver
{
    /**
     * Resolve provider config from a collection of API config records.
     * Each record must have: provider, model, api_key (decrypted), is_active, priority, purpose.
     */
    public function resolve(Collection $apiConfigs): array
    {
        $chatConfigs = $apiConfigs
            ->where('purpose', 'chat')
            ->where('is_active', true)
            ->sortBy('priority');

        $providers = [];
        $keys = [];

        foreach ($chatConfigs as $config) {
            $this->refreshOAuthTokenIfNeeded($config);

            $key = $config->resolveApiKey();
            if (! $key) {
                continue;
            }

            $providers[$config->provider] = $config->model;
            $keys[$config->provider] = $key;
        }

        $embedding = $this->resolveEmbeddingConfig($apiConfigs);

        return [
            'providers' => $providers,
            'keys' => $keys,
            'embedding' => $embedding,
        ];
    }

    /**
     * Resolve credentials for an Application with per-app override + team fallback.
     *
     * @return array{providers: array<string, string|null>, keys: array<string, string>, embedding: array}
     */
    public static function resolveForApplication(ApplicationContract $app): array
    {
        $appConfigs = AiApiConfig::active()
            ->forApplication($app->id)
            ->orderBy('priority')
            ->get();

        $teamConfigs = AiApiConfig::active()
            ->forTeam($app->team_id)
            ->whereNull('application_id')
            ->orderBy('priority')
            ->get();

        // Merge: app-specific wins over team-global (keyed by provider+purpose)
        $merged = collect();

        foreach ($teamConfigs as $config) {
            $merged->put("{$config->provider}:{$config->purpose}", $config);
        }

        foreach ($appConfigs as $config) {
            $merged->put("{$config->provider}:{$config->purpose}", $config);
        }

        return (new static)->resolve($merged->values());
    }

    /**
     * Inject resolved keys into Laravel's runtime config so the AI SDK can use them.
     */
    public function injectIntoConfig(array $resolved): void
    {
        foreach ($resolved['keys'] as $provider => $key) {
            config(["ai.providers.{$provider}.key" => $key]);
        }

        if (! empty($resolved['embedding'])) {
            config([
                "ai.providers.{$resolved['embedding']['provider']}.key" => $resolved['embedding']['key'],
            ]);
        }
    }

    /**
     * Refresh Anthropic OAuth token if it has expired using the refresh token.
     */
    public function refreshOAuthTokenIfNeeded(AiApiConfig $config): void
    {
        if (! $config->hasOAuthToken() || ! $config->isOAuthExpired() || ! $config->oauth_refresh_token) {
            return;
        }

        $tokens = app(AnthropicOAuthService::class)->refreshToken($config->oauth_refresh_token);

        $config->update([
            'oauth_access_token' => $tokens['access_token'],
            'oauth_refresh_token' => $tokens['refresh_token'] ?? $config->oauth_refresh_token,
            'oauth_expires_at' => isset($tokens['expires_in'])
                ? now()->addSeconds((int) $tokens['expires_in'])
                : null,
        ]);
    }

    protected function resolveEmbeddingConfig(Collection $apiConfigs): array
    {
        $embeddingConfig = $apiConfigs
            ->where('purpose', 'embeddings')
            ->where('is_active', true)
            ->sortBy('priority')
            ->first();

        if (! $embeddingConfig) {
            return [];
        }

        $this->refreshOAuthTokenIfNeeded($embeddingConfig);

        $key = $embeddingConfig->resolveApiKey();

        if (! $key) {
            return [];
        }

        return [
            'provider' => $embeddingConfig->provider,
            'key' => $key,
            'model' => $embeddingConfig->model,
        ];
    }
}
