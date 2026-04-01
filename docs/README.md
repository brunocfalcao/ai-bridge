# ai-bridge Documentation

## Package Overview

**brunocfalcao/ai-bridge** is a Laravel package providing multi-provider AI connectivity, real-time chat streaming, a Model Context Protocol (MCP) knowledge server, and sandboxed agent tools.

- **PHP:** ^8.4
- **Laravel:** ^12.0 || ^13.0
- **Dependencies:** `laravel/ai` ^0.3, `laravel/mcp` ^0.6, `prism-php/prism` ^0.71
- **License:** MIT

## Documentation Index

| Document | Description |
|---|---|
| [Architecture](architecture.md) | Package structure, sub-namespaces, data flows, design patterns |
| [Configuration](configuration.md) | Complete config reference with every key, type, default, and purpose |
| [Resolver](resolver.md) | Scope-based AI provider/model resolution with fallback chains |
| [Providers](providers.md) | DB-based provider resolution, Anthropic OAuth, BearerAnthropic |
| [Chat](chat.md) | Real-time chat streaming pipeline, events, models, summarization |
| [Knowledge](knowledge.md) | MCP knowledge server, vector search, content chunking, multi-tenant DB |
| [Tools](tools.md) | Sandboxed agent tools for filesystem, shell, code search, knowledge search |
| [Models & Migrations](models.md) | Eloquent models, database schema, migration details |
| [Contracts](contracts.md) | Interfaces for host app model decoupling |
| [Security](security.md) | Command hardening, path traversal protection, secret detection, credential encryption |
