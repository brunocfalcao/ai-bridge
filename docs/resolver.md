# Resolver - Scope-Based AI Provider Resolution

## Overview

The `AiResolver` maps business scopes (e.g. "leads-discover", "study-full") to AI provider+model pairs with automatic fallback chains. It integrates with Laravel AI's `withModelFailover()` mechanism, which catches `FailoverableException` (quota exhaustion, rate limiting, billing errors) and cascades to the next provider.

**Namespace:** `BrunoCFalcao\AiBridge\Resolver\AiResolver`
**Registered as:** singleton via service provider

## Configuration Format

Connectivity strings use the `provider:model` format, split on the first colon:

```
openrouter:google/gemini-2.5-flash    -> provider: openrouter, model: google/gemini-2.5-flash
openai:gpt-4.1                        -> provider: openai, model: gpt-4.1
gemini:gemini-2.5-flash               -> provider: gemini, model: gemini-2.5-flash
openrouter:qwen/qwen3.6-plus:free     -> provider: openrouter, model: qwen/qwen3.6-plus:free
```

The provider name must match a key in Laravel's `config/ai.php` providers array.

## Config Example

```php
// config/ai-bridge.php (or published override)
'resolver' => [
    'scopes' => [
        'leads-discover' => 'openrouter:qwen/qwen3.6-plus-preview:free',
        'leads-osint'    => 'gemini:gemini-2.5-flash',
        'leads-dispatch' => 'openrouter:google/gemini-2.5-flash',
        'wizard'         => 'openrouter:qwen/qwen3.6-plus-preview:free',
        'study-preview'  => 'openai:gpt-4.1',
        'study-full'     => 'openai:gpt-4.1',
        'study-ce'       => 'openai:gpt-4.1',
    ],

    'fallbacks' => [
        'openai'     => 'openrouter:openai/gpt-4.1',
        'openrouter' => 'gemini:gemini-2.5-flash',
        'gemini'     => null,  // terminal - throws exception on failure
    ],

    'default' => 'gemini:gemini-2.5-flash',
],
```

## API

### `resolve(string|\BackedEnum $scope): array`

Returns an ordered associative array for use with `Promptable::prompt(provider: $array)`.

```php
$resolver = app(AiResolver::class);

$providers = $resolver->resolve('study-full');
// ['openai' => 'gpt-4.1', 'openrouter' => 'openai/gpt-4.1', 'gemini' => 'gemini-2.5-flash']

// Pass directly to any Laravel AI agent:
(new StudyAgent)->prompt($text, provider: $providers);
```

**Fallback chain algorithm:**
1. Look up scope in `resolver.scopes`. If not found, use `resolver.default`.
2. Parse the primary `provider:model` string.
3. Look up `resolver.fallbacks.{provider}`. If non-null, parse and append.
4. Repeat step 3 with the fallback provider. Stop on `null` or cycle detection.

**Example walkthrough for `study-full`:**
- Primary: `openai:gpt-4.1` -> `['openai' => 'gpt-4.1']`
- Fallback of `openai`: `openrouter:openai/gpt-4.1` -> append `['openrouter' => 'openai/gpt-4.1']`
- Fallback of `openrouter`: `gemini:gemini-2.5-flash` -> append `['gemini' => 'gemini-2.5-flash']`
- Fallback of `gemini`: `null` -> stop
- Result: 3-element array, tried in order

### `resolveUsing(string|\BackedEnum $scope): array`

Returns only the primary provider and model as an indexed array. No fallback chain. Used for direct Prism integration (e.g. agents that use `Prism::text()->using($provider, $model)`).

```php
[$provider, $model] = app(AiResolver::class)->resolveUsing('leads-osint');
// $provider = 'gemini', $model = 'gemini-2.5-flash'

Prism::text()
    ->using($provider, $model)
    ->withPrompt($prompt)
    ->asText();
```

## Host App Integration

The package does NOT define scope values. The host app defines its own scopes using either:

**Option A: String-backed enum (recommended)**
```php
// app/Ai/AiScope.php
enum AiScope: string
{
    case LeadsDiscover = 'leads-discover';
    case StudyFull = 'study-full';
    // ...
}

// Usage:
$resolver->resolve(AiScope::LeadsDiscover);
```

**Option B: Plain strings**
```php
$resolver->resolve('leads-discover');
```

Both work because `resolve()` accepts `string|\BackedEnum`.

## Custom Config Path

If your project stores AI config under a different key (e.g. `myapp.ai` instead of `ai-bridge.resolver`):

```php
// config/ai-bridge.php
'ai_config_key' => 'myapp.ai',
```

The resolver will then read `config('myapp.ai.scopes.*')`, `config('myapp.ai.fallbacks.*')`, and `config('myapp.ai.default')`.

## How Failover Works

When the primary provider returns a quota/billing error, Laravel AI's `Promptable::withModelFailover()` catches it and tries the next provider in the array:

1. `InsufficientCreditsException` (HTTP 402, "insufficient credits" in message)
2. `RateLimitedException` (HTTP 429)
3. `ProviderOverloadedException` (HTTP 503/529)

All three implement `FailoverableException`. The resolver's ordered array maps directly to this failover mechanism.

If all providers in the chain fail, the last exception propagates to the caller (step/job error handling).
