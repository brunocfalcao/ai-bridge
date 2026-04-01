# Providers - DB-Based Provider Management

## Overview

The Providers subsystem manages AI API credentials stored in the database, with support for per-team and per-application overrides, Anthropic OAuth token lifecycle, and transparent Bearer authentication for OAuth tokens.

## ProviderResolver

**Namespace:** `BrunoCFalcao\AiBridge\Providers\ProviderResolver`

Resolves API credentials from `AiApiConfig` database records.

### `resolve(Collection $apiConfigs): array`

Takes a collection of `AiApiConfig` records and returns:

```php
[
    'providers' => ['anthropic' => 'claude-sonnet-4-6', 'openai' => 'gpt-4.1'],
    'keys'      => ['anthropic' => 'sk-ant-...', 'openai' => 'sk-proj-...'],
    'embedding' => ['provider' => 'openai', 'key' => 'sk-...', 'model' => 'text-embedding-3-small'],
]
```

**Process:**
1. Filters for active chat-purpose configs, sorted by priority.
2. For each, calls `refreshOAuthTokenIfNeeded()` if applicable.
3. Calls `resolveApiKey()` which returns: static key > non-expired OAuth token > null.
4. Separately resolves one embedding config (purpose = 'embeddings').

### `resolveForApplication(ApplicationContract $app): array`

Static method. Loads `AiApiConfig` records for both the application and its team. Merges with app-specific overriding team-global, keyed by `provider:purpose`. Delegates to `resolve()`.

### `injectIntoConfig(array $resolved): void`

Injects resolved API keys into Laravel's runtime config:
```php
config(["ai.providers.{$provider}.key" => $key]);
```
Also sets embedding default provider and model if available.

### `refreshOAuthTokenIfNeeded(AiApiConfig $config): void`

Checks if the config has an expired OAuth token with a refresh token. If so, calls `AnthropicOAuthService::refreshToken()` and updates the database record.

## AnthropicOAuthService

**Namespace:** `BrunoCFalcao\AiBridge\Providers\AnthropicOAuthService`

### `refreshToken(string $refreshToken): array`

POSTs to the Anthropic OAuth token endpoint with `grant_type: refresh_token`. Returns:

```php
['access_token' => '...', 'refresh_token' => '...', 'expires_in' => 3600]
```

Config keys used:
- `ai-bridge.oauth.anthropic.token_url` (endpoint URL)
- `ai-bridge.oauth.anthropic.client_id` (OAuth client ID)

## BearerAnthropic

**Namespace:** `BrunoCFalcao\AiBridge\Providers\BearerAnthropic`
**Extends:** `Prism\Prism\Providers\Anthropic\Anthropic`

A Prism provider variant that uses `Authorization: Bearer` instead of the standard `x-api-key` header. This enables Anthropic OAuth access tokens (`sk-ant-oat-*`) to work transparently.

**Auto-registered:** The `AiBridgeServiceProvider` hooks into `PrismManager` via `afterResolving`. When creating an `anthropic` driver, if the API key starts with `sk-ant-oat`, it substitutes `BearerAnthropic` for the standard `Anthropic` provider.

**Additional headers:**
- `anthropic-beta: oauth-2025-04-20` (required for OAuth)
- `User-Agent`: from `config('ai-bridge.oauth.anthropic.user_agent')`

## AiApiConfig Model

See [Models & Migrations](models.md) for the full model specification.
