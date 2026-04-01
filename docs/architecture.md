# Architecture

## Package Structure

```
src/
  Contracts/                         Host app model interfaces
    ApplicationContract.php
    TeamContract.php
  Resolver/                          Scope-based AI provider resolution
    AiResolver.php
  Providers/                         DB-based provider management + OAuth
    BearerAnthropic.php
    AnthropicOAuthService.php
    ProviderResolver.php
  Chat/                              Real-time chat streaming pipeline
    ProcessChatMessage.php
    ConversationSummarizer.php
    Events/
      ChatEvent.php                  Abstract base
      ChatInit.php
      ChatDelta.php
      ChatComplete.php
      ChatError.php
    Models/
      Conversation.php
      ConversationMessage.php
  Knowledge/                         MCP knowledge server + vector search
    SystemContext.php
    ContentChunker.php
    Middleware/
      AuthenticateApiKey.php
    Models/
      KnowledgeChunk.php
    Mcp/
      KnowledgeServer.php
      Tools/
        SearchKnowledgeTool.php
        StoreKnowledgeTool.php
        ListTopicsTool.php
      Resources/
        TopicsResource.php
        ChunkResource.php
  Tools/                             Sandboxed agent tools
    Concerns/
      ResolvesProjectPath.php
    ListDirectory.php
    ReadFile.php
    WriteFile.php
    RunCommand.php
    SearchCode.php
    SearchKnowledge.php
  Support/                           Utility services
    SecretDetector.php
  Models/                            Shared Eloquent models
    AiApiConfig.php
  AiBridgeServiceProvider.php        Package service provider
config/
  ai-bridge.php                      Package configuration
database/
  migrations/
    app/                             Auto-loaded migrations (main DB)
    knowledge/                       Publishable migrations (per-project PostgreSQL)
```

## Five Subsystems

### 1. Resolver (scope-based)
Config-driven mapping from business scopes to `provider:model` strings with provider-level fallback chains. Integrates with Laravel AI's `withModelFailover()` for automatic failover on quota/billing errors.

### 2. Providers (DB-based)
Per-team and per-application API key management stored in `ai_api_configs` table. Supports Anthropic OAuth token lifecycle (auto-refresh). `BearerAnthropic` extends Prism's Anthropic provider to use Bearer auth for OAuth tokens.

### 3. Chat
Queued job (`ProcessChatMessage`) that streams AI agent responses over private WebSocket channels via four broadcast events. Supports agent customization, slash commands, cancellation via Redis, and automatic conversation summarization.

### 4. Knowledge
Multi-tenant MCP knowledge server backed by per-project PostgreSQL databases with pgvector. Content is chunked with overlap, embedded, and searchable via cosine similarity. Exposed through three MCP tools and two MCP resources.

### 5. Tools
Six sandboxed `Laravel\Ai\Contracts\Tool` implementations giving AI agents controlled access to the filesystem (list, read, write), shell commands (with allowlist + chaining protection), code search, and knowledge base search.

## Data Flows

### Chat Streaming
```
Host App
  -> dispatch(ProcessChatMessage)
       -> restoreProviderKeys() into Laravel config
       -> resolveAgent() (custom class or default)
       -> agent->continue(conversationId)->stream(message, provider: [...])
       -> broadcast ChatInit
       -> for each TextDelta: broadcast ChatDelta
       -> if cancelled: insert sentinel message, break
       -> broadcast ChatComplete
       -> dispatch ConversationSummarizer (async, cheap model)
  <- ChatError on exception
```

### Provider Resolution (scope-based)
```
Call Site
  -> AiResolver::resolve(AiScope::LeadsDiscover)
       -> config('ai-bridge.resolver.scopes.leads-discover')
       -> parse 'openrouter:google/gemini-2.5-flash'
       -> walk fallbacks: openrouter -> gemini -> null (terminal)
       -> return ['openrouter' => 'google/gemini-2.5-flash', 'gemini' => 'gemini-2.5-flash']
  -> Agent::prompt($text, provider: $array)
       -> Laravel AI withModelFailover() tries each provider in order
       -> InsufficientCreditsException triggers next provider
```

### Provider Resolution (DB-based)
```
Host App
  -> ProviderResolver::resolveForApplication($app)
       -> load AiApiConfig records (app-level + team-level)
       -> merge (app overrides team)
       -> refreshOAuthTokenIfNeeded() for expired Anthropic tokens
       -> resolveApiKey() per config (static key > OAuth token > null)
       -> return { providers, keys, embedding }
  -> ProviderResolver::injectIntoConfig($resolved)
       -> config(['ai.providers.{provider}.key' => ...]) for each
```

### Knowledge Ingestion
```
MCP Client
  -> POST /mcp/{slug} (store-knowledge tool)
       -> AuthenticateApiKey middleware validates Bearer token
       -> SystemContext::setSlug($slug)
       -> ContentChunker::chunk($content) with overlap
       -> for each chunk: Str::of($chunk)->toEmbeddings()
       -> KnowledgeChunk::create() on dynamic connection
       -> Cache::forget("knowledge_topics.{slug}")
```

### Knowledge Search
```
MCP Client
  -> POST /mcp/{slug} (search-knowledge tool)
       -> AuthenticateApiKey validates, sets SystemContext
       -> KnowledgeChunk::whereVectorSimilarTo('embedding', $query, ...)
       -> return top N results by cosine similarity
```

## Design Patterns

| Pattern | Where | Why |
|---|---|---|
| **Multi-tenant DB** | Knowledge subsystem | Each application gets its own PostgreSQL + pgvector database, dynamically registered at boot |
| **Provider failover** | AiResolver | Ordered provider chain; Laravel AI catches FailoverableException and cascades |
| **Encrypted credentials** | AiApiConfig | `api_key`, `oauth_access_token`, `oauth_refresh_token` use Laravel's `encrypted` cast |
| **Resilient broadcasting** | ProcessChatMessage | Chat events retry up to 3x with exponential backoff on RedisException |
| **Config-driven security** | RunCommand, WriteFile | Allowlists, dangerous patterns, and timeouts are all configurable |
| **Singleton context** | SystemContext | Holds per-request application slug for the MCP knowledge server |
| **Trait extraction** | ResolvesProjectPath | Shared path traversal protection across filesystem tools |
| **Abstract event base** | ChatEvent | Shared constructor, traits, and broadcastOn() for all chat events |
| **Contract decoupling** | ApplicationContract, TeamContract | Package references interfaces, not concrete host app models |
