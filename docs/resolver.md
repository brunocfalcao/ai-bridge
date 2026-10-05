# Resolver

`BrunoCFalcao\AiBridge\Resolver\AiResolver`, registered as a singleton.

A **connection** is a stable name for one AI use. It resolves to a
`provider:model` string, split on the first colon; the provider name must match
a key in Laravel AI's `ai.providers` config.

```
openai:gpt-4.1                     -> openai      / gpt-4.1
gemini:gemini-3-flash-preview      -> gemini      / gemini-3-flash-preview
openrouter:qwen/qwen3.6-plus:free  -> openrouter  / qwen/qwen3.6-plus:free
```

Every key below is read through `ai_config_key`, which defaults to
`ai-bridge.resolver`.

## Text

### `using(string|BackedEnum $connection): array`

The ordered provider map for `Promptable::prompt(provider: …)` and
`stream(provider: …)`: primary first, then the fallback chain. Laravel AI's
`withModelFailover()` iterates it, catching `FailoverableException`
(`InsufficientCreditsException`, `RateLimitedException`,
`ProviderOverloadedException`) and trying the next provider.

Chain resolution:

1. `connections.{name}`, or `default` when the connection is not configured.
2. `connection_fallbacks.{name}` — an ordered list that **replaces** the
   provider-level chain for that connection only. Two connections on one
   provider can therefore fail over to different places.
3. Otherwise `fallbacks.{provider}`, followed hop by hop until `null` or a
   repeat.

Laravel AI keys the map by provider, so a chain that reuses one provider with a
*different* model cannot be represented. That throws `InvalidArgumentException`
rather than silently replacing the primary model.

### `primary(string|BackedEnum $connection): array`

`[$provider, $model]` for the primary only, no chain.

### `agent(connection, instructions, messages, tools, schema): AnonymousAgent`

An ad-hoc agent carrying the connection's provider options. Pass a `$schema`
closure to get a `StructuredAnonymousAgent`. Prompt it with
`->prompt($text, provider: $resolver->using($connection))`.

When the host's tests fake the plain `AnonymousAgent` /
`StructuredAnonymousAgent` class and not the configured subclass, the plain
class is returned — options do not matter to a fake.

### `stream(array $messages, string|BackedEnum $connection): Generator`

Streams an OpenAI-shaped message list:

```php
[
    ['role' => 'system',    'content' => '…'],  // -> agent instructions
    ['role' => 'user',      'content' => '…'],  // -> history
    ['role' => 'assistant', 'content' => '…'],  // -> history
    ['role' => 'user',      'content' => '…'],  // -> the prompt (last user turn)
]
```

Yields `['type' => 'delta', 'content' => '…']` per text chunk and one closing
`['type' => 'done', 'content' => null]`. The connection's fallback chain and
reasoning effort both apply.

## Reasoning effort

### `effort(connection, provider): ?string`

`efforts.{connection}.{provider}` — `low`, `medium` or `high`, or null for the
model's own default.

### `connectionOptions(connection): array` / `providerOptions(connection, provider): array`

The configured effort in each provider's own request shape, merged with any raw
`options.{connection}.{provider}` override:

| Provider | Shape |
|---|---|
| `openai`, `openrouter` | `['reasoning' => ['effort' => …]]` |
| `gemini` | `['thinking_level' => …]` |
| `anthropic` | `['output_config' => ['effort' => …]]` |
| anything else | `[]` |

## Embeddings

### `embedWithMeta(string $text, ?int $dimensions = null): array`

Returns `['vector' => …, 'provider' => …, 'model' => …, 'identity' => 'provider:model']`.

Vectors are only comparable within the model that produced them: cosine
distance across two embedding spaces is noise that looks like a result. Any
caller that persists a vector must persist this identity with it and refuse to
compare across it.

`embed()` is the same call, returning only the vector.

### `embeddingProviders(): array`

The embedding chain as an ordered list of `provider:model` strings — primary
`embedding`, then `embedding_fallbacks` keyed by the full `provider:model` of
the entry it follows (a bare provider key also matches any model of that
provider).

It is a list, not a provider-keyed map, because a map cannot express two models
of one provider — and a model-level fallback is the only kind available when a
single embedding provider is enabled on an account. Each switch re-emits
`ProviderFailedOver`.

### `embeddingConnection(): array`

`[$provider, $model]` for the configured primary embedding model.
