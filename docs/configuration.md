# Configuration

`config/ai-bridge.php`. Publish it with:

```bash
php artisan vendor:publish --tag=ai-bridge-config
```

Everything lives under one key, read through `ai_config_key` (default
`ai-bridge.resolver`). A project that keeps its AI configuration elsewhere sets
`ai-bridge.ai_config_key` to that key instead of duplicating the block.

| Key | Type | Default | Purpose |
|---|---|---|---|
| `resolver.connections` | `array<string, string>` | `[]` | Connection name → `provider:model`. Named after the use, not the model. |
| `resolver.fallbacks` | `array<string, ?string>` | `[]` | Provider → next `provider:model`. `null` terminates the chain. |
| `resolver.connection_fallbacks` | `array<string, list<string>>` | `[]` | Per-connection chain that replaces `fallbacks` for that connection. |
| `resolver.default` | `string` | `gemini:gemini-3-flash-preview` | Used when a requested connection is not configured. |
| `resolver.efforts` | `array<string, array<string, string>>` | `[]` | Connection → provider → `low \| medium \| high`. |
| `resolver.options` | `array<string, array<string, array>>` | `[]` | Raw Laravel AI provider options, merged over `efforts`. |
| `resolver.embedding` | `string` | `gemini:gemini-embedding-001` | Primary embedding `provider:model`. |
| `resolver.embedding_fallbacks` | `array<string, string>` | `[]` | Keyed by the full `provider:model` it follows, or by a bare provider name. |
| `resolver.embedding_dimensions` | `int` | `768` | Vector size requested from the provider. Match the storage column. |
| `resolver.embedding_request_dimensions` | `array<string, int>` | `[]` | Per-`provider:model` override of the requested size. |

## Environment variables

| Variable | Key |
|---|---|
| `AI_BRIDGE_EMBEDDING` | `resolver.embedding` |
| `AI_BRIDGE_EMBEDDING_DIMENSIONS` | `resolver.embedding_dimensions` |

Provider API keys are **not** configured here — they belong to Laravel AI's own
`config/ai.php` (`ai.providers.{provider}.key`).

## Example

```php
'resolver' => [
    'connections' => [
        'survey'   => 'openai:gpt-4.1-mini',
        'analysis' => 'openai:gpt-4.1-mini',
        'analyst'  => 'openai:gpt-4.1',
    ],

    'fallbacks' => [
        'openai' => 'gemini:gemini-3.1-pro-preview',
    ],

    'efforts' => [
        'analyst' => ['openai' => 'high', 'gemini' => 'high'],
    ],

    'default' => 'gemini:gemini-3-flash-preview',

    'embedding' => 'gemini:gemini-embedding-001',
    'embedding_fallbacks' => [
        'gemini:gemini-embedding-001' => 'gemini:gemini-embedding-2',
    ],
    'embedding_dimensions' => 768,
],
```
