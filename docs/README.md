# ai-bridge Documentation

## Package overview

**brunocfalcao/ai-bridge** is a named-connection layer over
[Laravel AI](https://github.com/laravel/ai). It maps a business use to a
`provider:model`, walks its failover chain, applies its reasoning effort, and
reports which model produced an embedding. Nothing else — agents, streaming,
structured output, tools and MCP are Laravel AI's own and are used directly.

- **PHP:** ^8.4
- **Laravel:** ^12.0 || ^13.0
- **Dependencies:** `laravel/ai` ^1.0
- **License:** MIT

## Documentation index

| Document | Description |
|---|---|
| [Architecture](architecture.md) | Package structure, what it exists for, data flows |
| [Configuration](configuration.md) | Every config key, type, default and purpose |
| [Resolver](resolver.md) | Connections, failover chains, effort, streaming, embeddings |
