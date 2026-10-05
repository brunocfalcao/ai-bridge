# Architecture

## Package structure

```
src/
  Resolver/
    AiResolver.php                 Named connections, failover, effort, embeddings, streaming
  Agents/
    ConfiguredAgent.php            AnonymousAgent + per-provider options
    ConfiguredStructuredAgent.php  StructuredAnonymousAgent + per-provider options
    ResolvesConnectionOptions.php  Shared provider-options trait
  AiBridgeServiceProvider.php      Merges config, binds the resolver singleton
config/
  ai-bridge.php                    All configuration
```

There is no database table, no route, no command, no published migration. The
service provider registers one singleton and publishes one config file.

## What this package is for

Laravel AI already resolves providers, prompts agents, streams, embeds and
fails over. It has no opinion about *which* model a given business use should
run on, and no way to express:

1. **A connection** — a stable name for a use (`survey`, `analyst`) that a call
   site refers to, so a model swap is a config edit rather than a code change.
2. **A reasoning effort per connection and provider**, written once as
   `low | medium | high` and translated into each provider's own request shape.
3. **Embedding identity and model-level failover.** Laravel AI keys its failover
   list by provider name, so two models of one provider collapse into a single
   entry and the primary is silently dropped — which is exactly the shape an
   embedding fallback needs. The resolver walks that chain itself and reports
   the model that produced each vector.

Those three gaps are the whole package.

## Data flow

### Text

```
Call site
  -> AiResolver::agent('analyst', instructions: …)
       -> ConfiguredAgent with providerOptions() per provider
  -> ->prompt($text, provider: AiResolver::using('analyst'))
       -> ['openai' => 'gpt-4.1', 'gemini' => 'gemini-3.1-pro-preview']
       -> Laravel AI withModelFailover() tries each in order
```

### Streaming

```
Call site
  -> AiResolver::stream($messages, 'analyst')
       -> split: system turn -> instructions, last user turn -> prompt,
                 the rest -> ad-hoc history
       -> agent()->stream($prompt, provider: using())
       -> yield ['type' => 'delta', …] per TextDelta, then one 'done'
```

### Embeddings

```
Call site
  -> AiResolver::embedWithMeta($text)
       -> walk embedding + embedding_fallbacks candidate-by-candidate
       -> on FailoverableException: emit ProviderFailedOver, try the next
       -> return [vector, provider, model, identity]
```

## Design notes

| Decision | Why |
|---|---|
| Fake-aware `agent()` | Laravel AI fakes by exact class. When only the plain `AnonymousAgent` is faked, the resolver returns that class so host tests keep passing; options are irrelevant to a fake. |
| Embedding chain walked here | Laravel AI's provider-keyed failover cannot express two models of one provider — the only kind of fallback available when a single embedding provider is enabled on an account. |
| `ProviderFailedOver` re-emitted | Host alerting sees embedding failover exactly as it sees text failover. |
| `ai_config_key` indirection | A project that keeps AI configuration under its own key points this at it instead of duplicating config. |
