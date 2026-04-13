# ai-bridge

Reusable AI bridge for Laravel: multi-provider connectivity, chat streaming, knowledge/MCP server, and agent tools.

## Requirements

- PHP ^8.4
- Laravel ^12.0 || ^13.0
- [laravel/ai](https://github.com/laravel/ai) ^0.3
- [laravel/mcp](https://github.com/laravel/mcp) ^0.6
- [prism-php/prism](https://github.com/prism-php/prism) ^0.71

## Installation

```bash
composer require brunocfalcao/ai-bridge
```

Publish the config file:

```bash
php artisan vendor:publish --tag=ai-bridge-config
```

Run migrations:

```bash
php artisan migrate
```

## What It Does

### Connection-Based AI Provider Resolution

Map named connections to `provider:model` pairs with automatic fallback chains:

```php
// config/ai-bridge.php
'resolver' => [
    'connections' => [
        'cheap' => 'gemini:gemini-2.5-flash',
    ],
    'fallbacks' => [
        'claude-cli' => 'openrouter:anthropic/claude-sonnet-4',
    ],
    'default' => 'claude-cli:opus',
],
```

### ChatManager — Unified Chat with Fallback

All providers (Claude CLI bridge, OpenClaw, Anthropic, OpenAI, Gemini, etc.) go through `ChatManager`:

```php
use BrunoCFalcao\AiBridge\Chat\ChatManager;

$chat = app(ChatManager::class);

// Non-streaming
$response = $chat->send($messages, connection: 'cheap');

// Streaming with automatic fallback
foreach ($chat->stream($messages) as $event) {
    echo $event['content'];
}
```

Supported providers:
- **claude-cli** — Routes through the openclaw-claude-bridge Node.js proxy (uses Claude subscription)
- **openclaw** — Routes through an OpenClaw gateway (agent routing, tools, session management)
- **Standard** (anthropic, openai, gemini, openrouter, etc.) — Uses Prism PHP

### Real-Time Chat Streaming

Queue-based AI chat with WebSocket broadcasting:

```php
use BrunoCFalcao\AiBridge\Chat\ProcessBridgeChatMessage;

ProcessBridgeChatMessage::dispatch(
    userId: $user->id,
    message: $input,
    conversationId: $conversationId,
);
```

### MCP Knowledge Server

Multi-tenant knowledge base with pgvector semantic search, exposed via Model Context Protocol.

### Sandboxed Agent Tools

Six tools for AI agents: file operations, shell commands, code search, and knowledge search — all with security hardening.

## Documentation

Full specification docs are in the [docs/](docs/) directory:

- [Architecture](docs/architecture.md)
- [Configuration](docs/configuration.md)
- [Resolver](docs/resolver.md)
- [Providers](docs/providers.md)
- [Chat](docs/chat.md)
- [Knowledge](docs/knowledge.md)
- [Tools](docs/tools.md)
- [Models & Migrations](docs/models.md)
- [Contracts](docs/contracts.md)
- [Security](docs/security.md)

## License

MIT
