# Knowledge - MCP Server & Vector Search

## Overview

The Knowledge subsystem provides a multi-tenant Model Context Protocol (MCP) knowledge server backed by per-project PostgreSQL databases with pgvector. Content is split into overlapping chunks, embedded, and searchable via cosine similarity.

## Multi-Tenant Architecture

Each application gets its own PostgreSQL database with the pgvector extension. Connections are registered dynamically at boot based on active applications in the database.

**Flow:**
1. `AiBridgeServiceProvider::registerMcpRoutes()` queries the Application model for active apps with `knowledge_connection`.
2. For each, calls `registerKnowledgeConnection()` to add a Laravel DB connection using `config('ai-bridge.knowledge.db')` settings.
3. Registers an MCP route at `/mcp/{slug}` with `AuthenticateApiKey` middleware.
4. `SystemContext` singleton holds the current request's application slug.
5. `KnowledgeChunk::getConnectionName()` reads from `SystemContext` to dynamically route queries to the correct database.

## SystemContext

**Namespace:** `BrunoCFalcao\AiBridge\Knowledge\SystemContext`
**Registered as:** singleton

Holds the per-request application context for the MCP server.

| Method | Returns | Description |
|---|---|---|
| `setSlug(string $slug)` | void | Set the current application slug |
| `getSlug()` | string | Get slug (throws RuntimeException if not set) |
| `getConnection()` | string | Returns the DB connection name from `ai-bridge.mcp_systems.{slug}.connection` |
| `getName()` | string | Returns the application name from config |
| `isSet()` | bool | Whether a slug has been set |

## ContentChunker

**Namespace:** `BrunoCFalcao\AiBridge\Knowledge\ContentChunker`

Splits content into overlapping chunks for embedding ingestion.

### `chunk(string $content, ?int $maxChars = null, ?int $overlapChars = null): array`

**Algorithm:**
1. Defaults: `chunk_max_chars` (3200), `chunk_overlap_chars` (400) from config.
2. If content fits in one chunk, returns `[$content]`.
3. Sliding window with sentence-boundary-aware splitting:
   - Takes a `$maxChars` slice.
   - Finds the best break point (`. ` or `\n`) in the second half.
   - Trims each chunk, discards chunks under 50 characters.
4. Advances by `(slice_length - overlap)` to create overlap between consecutive chunks.

## AuthenticateApiKey Middleware

**Namespace:** `BrunoCFalcao\AiBridge\Knowledge\Middleware\AuthenticateApiKey`

Validates incoming MCP requests:
1. Extracts the slug from the URL path.
2. Looks up the Application model (from `config('ai-bridge.models.application')`) by slug, active status, and having a `knowledge_connection`.
3. Validates the `Authorization: Bearer` token against `$application->mcp_api_key`.
4. Sets the slug on `SystemContext`.
5. Returns 404 if app not found, 401 if key mismatch.

## KnowledgeChunk Model

**Namespace:** `BrunoCFalcao\AiBridge\Knowledge\Models\KnowledgeChunk`

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Auto-incrementing primary key |
| `title` | string | Topic/document title |
| `content` | text | Chunk content |
| `source_type` | string(50) | `'web'`, `'file'`, or `'chat'` |
| `source_url` | string nullable | Source URL |
| `metadata` | jsonb nullable | Arbitrary metadata |
| `embedding` | vector(1536) | pgvector embedding column |

**Casts:** `embedding => array`, `metadata => array`
**Dynamic connection:** Uses `SystemContext::getConnection()` when set.

## MCP Server

**Namespace:** `BrunoCFalcao\AiBridge\Knowledge\Mcp\KnowledgeServer`
**Extends:** `Laravel\Mcp\Server`

Registered at route `/mcp/{slug}` with Bearer authentication.

### Tools

#### search-knowledge (read-only)

Search the knowledge base by semantic similarity.

**Input:** `{ query: string }`
**Output:** Array of `{ title, content (truncated), source_type, source_url }`

Uses `KnowledgeChunk::whereVectorSimilarTo('embedding', $query, minSimilarity, limit)`.

#### store-knowledge

Ingest new content into the knowledge base.

**Input:** `{ title: string, content: string, source_type: 'web'|'file'|'chat', source_url?: string }`
**Output:** `{ stored_chunks: int, system: string, title: string }`

Process:
1. Chunks content via `ContentChunker`.
2. For each chunk, generates embedding via `Str::of($chunk)->toEmbeddings()` (Laravel AI macro).
3. Creates `KnowledgeChunk` records on the current connection.
4. Invalidates topics cache.

#### list-topics (read-only)

List all topics with chunk counts.

**Input:** none
**Output:** Array of `{ title, chunk_count }`

Cached for `topics_cache_ttl` seconds (default 300).

### Resources

#### knowledge://topics

Same data as `list-topics` tool, exposed as an MCP resource.

#### knowledge://chunks/{id}

Fetch a single chunk by ID. Returns `{ id, title, content, source_type, source_url, metadata, created_at }`.

## Database Setup

The knowledge migration is NOT auto-loaded. Publish and run manually on each project's PostgreSQL database:

```bash
php artisan vendor:publish --tag=ai-bridge-migrations
php artisan migrate --database=your_knowledge_connection
```

The migration:
1. Creates the `pgvector` extension.
2. Creates `knowledge_chunks` table.
3. Adds a `vector({dimensions})` column via raw SQL.
4. Creates an IVFFlat cosine similarity index with 100 lists.

Embedding dimensions default to 1536 (OpenAI text-embedding-3-small compatible). Configure via `ai-bridge.knowledge.embedding_dimensions`.
