# Models & Migrations

## AiApiConfig

**Namespace:** `BrunoCFalcao\AiBridge\Models\AiApiConfig`
**Table:** `ai_api_configs`

Stores per-team and per-application AI provider credentials with OAuth support.

### Schema

| Column | Type | Description |
|---|---|---|
| `id` | bigint auto | Primary key |
| `team_id` | FK nullable | Team owner (constrained to `teams`, nullOnDelete) |
| `application_id` | FK nullable | Application override (constrained to `applications`, nullOnDelete) |
| `purpose` | string(50) | `'chat'` or `'embeddings'` |
| `provider` | string | Provider identifier (e.g. `'anthropic'`, `'openai'`) |
| `api_key` | text nullable | **Encrypted.** Static API key |
| `oauth_access_token` | text nullable | **Encrypted.** Anthropic OAuth access token |
| `oauth_refresh_token` | text nullable | **Encrypted.** Anthropic OAuth refresh token |
| `oauth_expires_at` | timestamp nullable | OAuth token expiry |
| `model` | string nullable | Model identifier (e.g. `'claude-sonnet-4-6'`) |
| `is_active` | boolean | Whether this config is active (default: true) |
| `priority` | unsigned int | Resolution priority (lower = higher priority, default: 0) |

**Unique constraint:** `(team_id, application_id, provider, purpose)`

### Casts

```php
'api_key' => 'encrypted',
'oauth_access_token' => 'encrypted',
'oauth_refresh_token' => 'encrypted',
'oauth_expires_at' => 'datetime',
'is_active' => 'boolean',
```

### Methods

| Method | Returns | Description |
|---|---|---|
| `hasOAuthToken()` | bool | Whether `oauth_access_token` is set |
| `isOAuthExpired()` | bool | Whether `oauth_expires_at` is in the past |
| `resolveApiKey()` | ?string | Priority: static `api_key` > non-expired `oauth_access_token` > null |

### Relationships

| Relationship | Type | Related Model |
|---|---|---|
| `team()` | BelongsTo | `config('ai-bridge.models.team')` -- safe no-op if null |
| `application()` | BelongsTo | `config('ai-bridge.models.application')` -- safe no-op if null |

### Query Scopes

| Scope | SQL | Usage |
|---|---|---|
| `active()` | `WHERE is_active = true` | Filter active configs |
| `forTeam(int $teamId)` | `WHERE team_id = ?` | Filter by team |
| `forApplication(int $appId)` | `WHERE application_id = ?` | Filter by application |
| `global()` | `WHERE team_id IS NULL AND application_id IS NULL` | Global (no team/app) configs |

---

## Conversation

**Namespace:** `BrunoCFalcao\AiBridge\Chat\Models\Conversation`
**Table:** `ai_conversations`

### Schema

| Column | Type | Description |
|---|---|---|
| `id` | uuid | Primary key (non-incrementing) |
| `user_id` | FK nullable | Owner user |
| `title` | string | Display title |
| `summary` | longText nullable | AI-generated conversation summary |
| `type` | string(50) | Conversation type (default: `'normal'`) |
| `project_id` | bigint nullable | Associated project |
| `cleared_at` | timestamp nullable | Soft-clear timestamp |

**Indexes:** `[user_id, updated_at]`, `[project_id]`

### Casts

```php
'cleared_at' => 'datetime',
```

### Relationships

| Relationship | Type | Related Model |
|---|---|---|
| `messages()` | HasMany | `ConversationMessage` |

---

## ConversationMessage

**Namespace:** `BrunoCFalcao\AiBridge\Chat\Models\ConversationMessage`
**Table:** `ai_conversation_messages`

### Schema

| Column | Type | Description |
|---|---|---|
| `id` | uuid | Primary key (non-incrementing) |
| `conversation_id` | uuid | Parent conversation |
| `user_id` | FK nullable | Message author |
| `agent` | string | Agent class name used |
| `role` | string(25) | `'user'`, `'assistant'`, `'system'` |
| `content` | text nullable | Message content |
| `attachments` | text nullable | File attachments |
| `tool_calls` | text | Tools invoked (cast: array) |
| `tool_results` | text | Tool responses (cast: array) |
| `usage` | text | Token usage data (cast: array) |
| `meta` | text | Additional metadata (cast: array) |

**Indexes:** Composite `[conversation_id, user_id, updated_at]`

### Casts

```php
'tool_calls' => 'array',
'tool_results' => 'array',
'usage' => 'array',
'meta' => 'array',
```

### Relationships

| Relationship | Type | Related Model |
|---|---|---|
| `conversation()` | BelongsTo | `Conversation` |

---

## KnowledgeChunk

**Namespace:** `BrunoCFalcao\AiBridge\Knowledge\Models\KnowledgeChunk`
**Table:** `knowledge_chunks` (per-project PostgreSQL)

### Schema

| Column | Type | Description |
|---|---|---|
| `id` | bigint auto | Primary key |
| `title` | string | Topic/document title |
| `content` | text | Chunk content |
| `source_type` | string(50) | `'web'`, `'file'`, `'chat'` |
| `source_url` | string nullable | Source URL |
| `metadata` | jsonb nullable | Arbitrary metadata |
| `embedding` | vector(1536) | pgvector embedding |

**Index:** IVFFlat cosine similarity index with 100 lists.

### Casts

```php
'embedding' => 'array',
'metadata' => 'array',
```

### Dynamic Connection

`getConnectionName()` returns `SystemContext::getConnection()` when the SystemContext singleton has a slug set (during MCP requests). Falls back to the default connection otherwise.

---

## Migration Files

### Auto-loaded (main database)

Located in `database/migrations/app/`:

| File | Creates |
|---|---|
| `2026_03_13_100001_create_ai_conversations_table.php` | `ai_conversations` |
| `2026_03_13_100002_create_ai_conversation_messages_table.php` | `ai_conversation_messages` |
| `2026_03_13_100003_create_ai_api_configs_table.php` | `ai_api_configs` |
| `2026_03_13_200001_add_application_id_to_ai_api_configs_table.php` | Adds `application_id` to `ai_api_configs` |

### Publishable (per-project PostgreSQL)

Located in `database/migrations/knowledge/`:

| File | Creates |
|---|---|
| `2026_01_01_000004_create_knowledge_chunks_table.php` | `knowledge_chunks` + pgvector extension + IVFFlat index |

Publish and run manually:
```bash
php artisan vendor:publish --tag=ai-bridge-migrations
php artisan migrate --database=your_knowledge_connection
```
