# Configuration Reference

All configuration lives under the `ai-bridge` key. Publish with:

```bash
php artisan vendor:publish --tag=ai-bridge-config
```

## Resolver

Scope-based AI provider/model resolution. See [Resolver](resolver.md) for usage.

| Key | Type | Default | Description |
|---|---|---|---|
| `resolver.scopes` | `array<string, string>` | `[]` | Maps scope strings to `provider:model` format. Example: `'leads-discover' => 'openrouter:google/gemini-2.5-flash'` |
| `resolver.fallbacks` | `array<string, string\|null>` | `[]` | Provider-level fallback chain. `null` = terminal (throws on failure). Example: `'openai' => 'openrouter:openai/gpt-4.1'` |
| `resolver.default` | `string` | `'gemini:gemini-2.5-flash'` | Default `provider:model` when no scope matches |
| `ai_config_key` | `string` | _(not set)_ | Override the config path for scope resolution. Defaults to `'ai-bridge.resolver'`. Set to e.g. `'myapp.ai'` to use a different config root |

## Models

Host application model bindings. Required for MCP knowledge server and per-team API configs.

| Key | Type | Default | Description |
|---|---|---|---|
| `models.application` | `class-string\|null` | `null` | Host app's Application model class. Must implement `ApplicationContract`. Set to `null` to disable MCP features |
| `models.team` | `class-string\|null` | `null` | Host app's Team model class. Must implement `TeamContract` |

## Providers

Static provider and model catalogs. Used for UI dropdowns and summarization.

| Key | Type | Default | Description |
|---|---|---|---|
| `providers.supported` | `string[]` | 8 providers | List of supported provider identifiers |
| `providers.models` | `array<string, string[]>` | _(per provider)_ | Available models per provider |
| `providers.embedding_models` | `array<string, string[]>` | _(per provider)_ | Available embedding models per provider |
| `providers.cheap_models` | `array<string, string>` | _(per provider)_ | One cheap/fast model per provider for summarization |

## OAuth

Anthropic OAuth configuration for CLI token support.

| Key | Type | Default | Description |
|---|---|---|---|
| `oauth.anthropic.client_id` | `string\|null` | `env('ANTHROPIC_OAUTH_CLIENT_ID')` | OAuth client ID for token refresh |
| `oauth.anthropic.token_url` | `string` | `env('ANTHROPIC_OAUTH_TOKEN_URL', 'https://console.anthropic.com/v1/oauth/token')` | Token refresh endpoint |
| `oauth.anthropic.user_agent` | `string` | `env('AI_BRIDGE_USER_AGENT', 'ai-bridge/1.0')` | User-Agent header sent with OAuth requests |

## Chat

Real-time chat streaming configuration.

| Key | Type | Default | Description |
|---|---|---|---|
| `chat.queue` | `string` | `env('AI_BRIDGE_CHAT_QUEUE', 'chat')` | Queue name for `ProcessChatMessage` jobs |
| `chat.channel_prefix` | `string` | `'chat'` | WebSocket channel prefix. Events broadcast on `{prefix}.{userId}` |
| `chat.summarizer_threshold` | `int` | `20` | Message count that triggers conversation summarization |
| `chat.default_instructions` | `string` | `'You are a helpful AI assistant.'` | System prompt for the default agent (when no custom agent class is provided) |

## Knowledge

MCP knowledge server and vector search configuration.

| Key | Type | Default | Description |
|---|---|---|---|
| `knowledge.chunk_max_chars` | `int` | `3200` | Maximum characters per content chunk |
| `knowledge.chunk_overlap_chars` | `int` | `400` | Character overlap between adjacent chunks |
| `knowledge.embedding_dimensions` | `int` | `1536` | pgvector embedding column dimensions |
| `knowledge.vector_min_similarity` | `float` | `0.4` | Minimum cosine similarity threshold for search results |
| `knowledge.vector_search_limit` | `int` | `5` | Maximum number of search results returned |
| `knowledge.content_truncation` | `int` | `1500` | Max characters when displaying chunk content in search results |
| `knowledge.topics_cache_ttl` | `int` | `300` | Cache TTL in seconds for topic listings |
| `knowledge.db.driver` | `string` | `'pgsql'` | Database driver for knowledge connections |
| `knowledge.db.host` | `string` | `env('AI_BRIDGE_KNOWLEDGE_DB_HOST', '127.0.0.1')` | Database host |
| `knowledge.db.port` | `string` | `env('AI_BRIDGE_KNOWLEDGE_DB_PORT', '5432')` | Database port |
| `knowledge.db.charset` | `string` | `'utf8'` | Database charset |
| `knowledge.db.sslmode` | `string` | `env('AI_BRIDGE_KNOWLEDGE_DB_SSLMODE', 'prefer')` | PostgreSQL SSL mode |

## Tools

Agent tool configuration for sandboxed operations.

| Key | Type | Default | Description |
|---|---|---|---|
| `tools.commands_path` | `string\|null` | `env('AI_BRIDGE_COMMANDS_PATH')` | Base path for slash command `.md` files |
| `tools.max_file_size` | `int` | `512000` | Maximum file size in bytes for the ReadFile tool |
| `tools.command_timeout` | `int` | `30` | Shell command timeout in seconds |
| `tools.allowed_commands` | `string[]` | 28 commands | Whitelisted base commands for RunCommand |
| `tools.allowed_sudo_commands` | `array<string, string[]\|null>` | 7 entries | Whitelisted sudo targets. `null` = any subcommand, array = restricted subcommands |
| `tools.dangerous_patterns` | `string[]` | 7 patterns | Blocked shell patterns (e.g. `rm -rf /`, `mkfs`) |

## MCP Systems

Dynamically populated at boot. Do not set manually.

| Key | Type | Default | Description |
|---|---|---|---|
| `mcp_systems` | `array` | `[]` | Per-application MCP system configs. Populated by `AiBridgeServiceProvider::registerMcpRoutes()` |
