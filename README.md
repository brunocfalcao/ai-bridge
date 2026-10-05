# ai-bridge

Named AI connections for [Laravel AI](https://github.com/laravel/ai): one config
layer that maps a business use to a `provider:model`, walks its failover chain,
applies its reasoning effort, and reports which model produced an embedding.

Everything else Laravel AI already does — agents, streaming, structured output,
tools, MCP — is used directly. This package does not wrap it.

## Changes in v2.0.0

Everything that duplicated Laravel AI, or served a use nobody kept, is gone:
the Prism-backed chat providers, the Claude CLI and OpenClaw providers, the
knowledge/MCP server, the browser sidecar, the agent tools, the conversation
and API-config models with their migrations, and the Anthropic OAuth plumbing.
What remains is the resolver, its configured agents, and `AiResolver::stream()`,
which replaces `ChatManager` on Laravel AI's own streaming. `prism-php/prism`
and `laravel/mcp` are no longer required, and `laravel/ai` is pinned to `^1.0`.

## Requirements

- PHP ^8.4
- Laravel ^12.0 || ^13.0
- [laravel/ai](https://github.com/laravel/ai) ^1.0

## Installation

```bash
composer require brunocfalcao/ai-bridge
php artisan vendor:publish --tag=ai-bridge-config
```

## Named connections

A connection is named after the *use*, not the model behind it, so swapping a
model never reaches a call site:

```php
// config/ai-bridge.php
'resolver' => [
    'connections' => [
        'survey'  => 'openai:gpt-4.1-mini',
        'analyst' => 'openai:gpt-4.1',
    ],
    'fallbacks' => [
        'openai' => 'gemini:gemini-3.1-pro-preview',
    ],
    'efforts' => [
        'analyst' => ['openai' => 'high'],
    ],
],
```

```php
use BrunoCFalcao\AiBridge\Resolver\AiResolver;

$resolver = app(AiResolver::class);

// An agent carrying the connection's reasoning effort, prompted over its
// failover chain.
$response = $resolver->agent('analyst', instructions: 'Answer in JSON.')
    ->prompt($text, provider: $resolver->using('analyst'));

// Streaming, from an OpenAI-shaped message list.
foreach ($resolver->stream($messages, 'analyst') as $event) {
    // ['type' => 'delta', 'content' => '…'] … ['type' => 'done', 'content' => null]
}
```

`using()` returns the ordered provider map Laravel AI's `withModelFailover()`
consumes: a rate-limit, overload or insufficient-credit response retries the
same call on the next provider.

## Reasoning effort

`efforts.{connection}.{provider}` takes `low`, `medium` or `high` and is
translated into each provider's own request shape — OpenAI and OpenRouter
`reasoning.effort`, Gemini `thinking_level`, Anthropic `output_config.effort`.
Providers without an effort setting keep their default. `options` passes raw
Laravel AI provider options for anything the mapping does not cover.

## Embeddings

A vector is only comparable within the model that produced it, so
`embedWithMeta()` returns the producing `provider:model` alongside the vector.
Persist that identity with the vector and refuse to compare across it.

`embedding_fallbacks` is walked candidate-by-candidate rather than handed to
Laravel AI, which keys its own failover by provider name and so collapses two
models of one provider into a single entry. Each switch re-emits
`ProviderFailedOver`, so host alerting sees embedding failures exactly as it
sees text ones.

## Documentation

- [Architecture](docs/architecture.md)
- [Configuration](docs/configuration.md)
- [Resolver](docs/resolver.md)

## License

MIT
