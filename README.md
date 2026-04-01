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

### Scope-Based AI Provider Resolution

Map business scopes to AI providers with automatic fallback chains:

```php
// config/ai-bridge.php
'resolver' => [
    'scopes' => [
        'leads-discover' => 'openrouter:google/gemini-2.5-flash',
        'study-full'     => 'openai:gpt-4.1',
    ],
    'fallbacks' => [
        'openai'     => 'openrouter:openai/gpt-4.1',
        'openrouter' => 'gemini:gemini-2.5-flash',
        'gemini'     => null,
    ],
    'default' => 'gemini:gemini-2.5-flash',
],
```

```php
use BrunoCFalcao\AiBridge\Resolver\AiResolver;

$providers = app(AiResolver::class)->resolve('study-full');
// ['openai' => 'gpt-4.1', 'openrouter' => 'openai/gpt-4.1', 'gemini' => 'gemini-2.5-flash']

(new MyAgent)->prompt($text, provider: $providers);
```

### Real-Time Chat Streaming

Queue-based AI chat with WebSocket broadcasting:

```php
use BrunoCFalcao\AiBridge\Chat\ProcessChatMessage;

ProcessChatMessage::dispatch(
    userId: $user->id,
    message: $input,
    conversationId: $conversationId,
    providerArray: $providers,
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
