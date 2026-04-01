<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Chrome / Playwright Configuration
    |--------------------------------------------------------------------------
    */
    'chrome_binary' => env('AI_BRIDGE_CHROME_BINARY', '/opt/chrome-headless-shell/chrome-headless-shell'),
    'browser_url' => env('AI_BRIDGE_BROWSER_URL', 'http://127.0.0.1:9222'),
    'browser_sidecar_url' => env('AI_BRIDGE_BROWSER_SIDECAR_URL', 'http://127.0.0.1:3100'),

    /*
    |--------------------------------------------------------------------------
    | Supported AI Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'anthropic',
        'openai',
        'gemini',
        'groq',
        'deepseek',
        'mistral',
        'xai',
        'openrouter',
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Models (for UI dropdowns)
    |--------------------------------------------------------------------------
    */
    'provider_models' => [
        'anthropic' => [
            'claude-opus-4-6',
            'claude-sonnet-4-6',
            'claude-haiku-4-5-20251001',
        ],
        'openai' => [
            'gpt-4.1',
            'gpt-4.1-mini',
            'o3',
            'o4-mini',
        ],
        'gemini' => [
            'gemini-2.5-pro',
            'gemini-2.5-flash',
        ],
        'groq' => [
            'llama-3.3-70b-versatile',
        ],
        'deepseek' => [
            'deepseek-chat',
            'deepseek-reasoner',
        ],
        'mistral' => [
            'mistral-large-latest',
        ],
        'xai' => [
            'grok-3',
        ],
        'openrouter' => [
            'anthropic/claude-sonnet-4',
            'openai/gpt-4.1',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Embedding Models (for UI dropdowns)
    |--------------------------------------------------------------------------
    */
    'embedding_models' => [
        'openai' => [
            'text-embedding-3-small',
            'text-embedding-3-large',
            'text-embedding-ada-002',
        ],
        'openrouter' => [
            'openai/text-embedding-3-small',
            'openai/text-embedding-3-large',
        ],
        'gemini' => [
            'text-embedding-004',
        ],
        'mistral' => [
            'mistral-embed',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cheap Models (for summarization, etc.)
    |--------------------------------------------------------------------------
    */
    'cheap_models' => [
        'anthropic' => 'claude-haiku-4-5-20251001',
        'openai' => 'gpt-4.1-mini',
        'gemini' => 'gemini-2.5-flash',
        'groq' => 'llama-3.3-70b-versatile',
        'deepseek' => 'deepseek-chat',
        'mistral' => 'mistral-large-latest',
        'xai' => 'grok-3',
        'openrouter' => 'openai/gpt-4.1-mini',
    ],

    /*
    |--------------------------------------------------------------------------
    | Slash Commands Directory
    |--------------------------------------------------------------------------
    | Path to the directory containing global slash command .md files.
    | Each .md file becomes a /command available in the chat.
    */
    'commands_path' => env('AI_BRIDGE_COMMANDS_PATH', '/home/waygou/.claude/commands'),

    /*
    |--------------------------------------------------------------------------
    | Knowledge / MCP Configuration
    |--------------------------------------------------------------------------
    */
    'knowledge' => [
        'chunk_max_chars' => 3200,
        'chunk_overlap_chars' => 400,
        'embedding_dimensions' => 1536,
        'vector_min_similarity' => 0.4,
        'vector_search_limit' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Scope Resolution
    |--------------------------------------------------------------------------
    |
    | Maps business scopes to a primary provider:model string.
    | Format is "provider:model" — provider is the key in config/ai.php providers.
    | Fallbacks map a provider name to its fallback provider:model string.
    | A null fallback means that provider is terminal (exception on failure).
    |
    | Projects can move this config to a different key (e.g. 'olloma.ai')
    | by setting 'ai_config_key' above.
    |
    */

    'ai' => [
        'scopes' => [],
        'fallbacks' => [],
        'default' => 'gemini:gemini-2.5-flash',
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Systems (populated dynamically per project)
    |--------------------------------------------------------------------------
    */
    'mcp_systems' => [],

];
