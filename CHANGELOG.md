# Changelog

All notable changes to this project will be documented in this file.

## 1.0.0 - 2026-04-01

### Features

- [NEW FEATURE] Scope-based AI provider/model resolution with `provider:model` format and provider-level fallback chains (`AiResolver`)
- [NEW FEATURE] DB-based provider resolution with per-team and per-application API key management (`ProviderResolver`, `AiApiConfig`)
- [NEW FEATURE] Anthropic OAuth token lifecycle — auto-refresh expired tokens, Bearer authentication via `BearerAnthropic`
- [NEW FEATURE] Real-time chat streaming pipeline with queued job, WebSocket broadcasting, and cancellation support (`ProcessChatMessage`)
- [NEW FEATURE] Automatic conversation summarization using cheap models (`ConversationSummarizer`)
- [NEW FEATURE] Multi-tenant MCP knowledge server backed by per-project PostgreSQL + pgvector (`KnowledgeServer`)
- [NEW FEATURE] Content chunking with sentence-boundary-aware overlap splitting (`ContentChunker`)
- [NEW FEATURE] Vector similarity search, content ingestion, and topic listing via MCP tools and resources
- [NEW FEATURE] Six sandboxed agent tools: ListDirectory, ReadFile, WriteFile, RunCommand, SearchCode, SearchKnowledge
- [NEW FEATURE] Secret detection for 12 credential types (`SecretDetector`)
- [NEW FEATURE] Chat Eloquent models: `Conversation`, `ConversationMessage` with UUID primary keys

### Architecture

- [IMPROVED] Sub-namespace organization: Resolver, Providers, Chat, Knowledge, Tools, Support, Contracts, Models
- [IMPROVED] Host app model decoupling via `ApplicationContract` and `TeamContract` interfaces
- [IMPROVED] Config-driven model bindings — set to `null` to disable features gracefully
- [IMPROVED] All hardcoded values moved to `config/ai-bridge.php` (OAuth, DB params, tool allowlists, timeouts)
- [IMPROVED] Base `ChatEvent` abstract class eliminates duplication across 4 broadcast events
- [IMPROVED] `ResolvesProjectPath` trait shared across filesystem tools for path traversal protection

### Security

- [SECURITY] RunCommand: command chaining detection (`;`, `&&`, `||`, `|`, backticks, `$()`)
- [SECURITY] RunCommand: config-driven allowlists for commands, sudo targets, and dangerous patterns
- [SECURITY] WriteFile: path traversal check runs BEFORE directory creation
- [SECURITY] All API keys and OAuth tokens encrypted at rest via Laravel's `encrypted` cast
- [SECURITY] MCP endpoints protected by Bearer token authentication
