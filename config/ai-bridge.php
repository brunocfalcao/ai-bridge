<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Connection Resolution
    |--------------------------------------------------------------------------
    |
    | Named AI connections map to a "provider:model" string. The AiResolver
    | parses the provider name before the first colon, and the provider name
    | must match a key in Laravel AI's `ai.providers` config.
    |
    | Everything below is read through `ai_config_key`, so a project that keeps
    | its AI configuration elsewhere only has to point that key at it.
    |
    */

    'resolver' => [

        /*
        | One connection per AI use, named after the use rather than the model
        | behind it, so a model change never reaches a call site.
        */
        'connections' => [],

        /*
        | Provider-level failover, walked by AiResolver::using() and executed by
        | Laravel AI's withModelFailover(): a rate-limit, overload or
        | insufficient-credit response retries the same call on the next
        | provider. Keyed by provider name, `null` terminates the chain.
        */
        'fallbacks' => [],

        /*
        | Per-connection failover, which replaces `fallbacks` for that
        | connection only. Two connections on one provider can so fail over to
        | different places. Values are an ordered list of "provider:model".
        */
        'connection_fallbacks' => [],

        /*
        | Used when a requested connection is not configured.
        */
        'default' => 'gemini:gemini-3-flash-preview',

        /*
        | Reasoning effort per connection and provider (low | medium | high),
        | translated by AiResolver into each provider's own request shape.
        | Providers without an effort setting keep their default.
        */
        'efforts' => [],

        /*
        | Raw Laravel AI provider options per connection and provider, merged
        | over whatever `efforts` produced. For anything the effort mapping
        | does not cover.
        */
        'options' => [],

        /*
        |----------------------------------------------------------------------
        | Embedding Connection
        |----------------------------------------------------------------------
        |
        | Same "provider:model" format. The provider's API key is resolved from
        | Laravel AI's `ai.providers` config.
        |
        | Vectors are only comparable within the model that produced them, so
        | embedWithMeta() reports the producing provider:model and callers that
        | persist a vector must persist that identity with it.
        |
        */

        'embedding' => env('AI_BRIDGE_EMBEDDING', 'gemini:gemini-embedding-001'),

        /*
        | Embedding failover, walked candidate-by-candidate so a model-level
        | hop works: Laravel AI keys its own failover by provider name, which
        | collapses two models of one provider into a single entry. Keyed by
        | the full "provider:model" of the entry it follows; a bare provider
        | key applies to any model of that provider.
        */
        'embedding_fallbacks' => [],

        /*
        | The vector size requested from the provider. Set it to match the
        | column the vectors are stored in.
        */
        'embedding_dimensions' => (int) env('AI_BRIDGE_EMBEDDING_DIMENSIONS', 768),

        /*
        | Per-model override of the requested size, keyed by the full
        | "provider:model", for a fallback model whose native output differs
        | from the primary's.
        */
        'embedding_request_dimensions' => [],
    ],

];
