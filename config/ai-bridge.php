<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Connection Resolution
    |--------------------------------------------------------------------------
    |
    | Named AI connections map to a "provider:model" string. The AiResolver
    | parses the provider name before the first colon. Fallbacks define
    | a provider-level chain tried on quota/billing exhaustion.
    |
    */

    'resolver' => [
        'connections' => [],
        'fallbacks' => [],
        'default' => 'gemini:gemini-2.5-flash',
    ],

    /*
    |--------------------------------------------------------------------------
    | Host App Model Bindings
    |--------------------------------------------------------------------------
    |
    | Point these to your application's Eloquent model classes. These are
    | required for MCP knowledge registration and per-team API configs.
    | Set to null to disable features that depend on them.
    |
    */

    'models' => [
        'application' => null,
        'team' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported AI Providers
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'supported' => [
            'anthropic',
            'openai',
            'gemini',
            'groq',
            'deepseek',
            'mistral',
            'xai',
            'openrouter',
        ],

        'models' => [
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic OAuth
    |--------------------------------------------------------------------------
    */

    'oauth' => [
        'anthropic' => [
            'client_id' => env('ANTHROPIC_OAUTH_CLIENT_ID'),
            'token_url' => env('ANTHROPIC_OAUTH_TOKEN_URL', 'https://console.anthropic.com/v1/oauth/token'),
            'user_agent' => env('AI_BRIDGE_USER_AGENT', 'ai-bridge/1.0'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat Streaming
    |--------------------------------------------------------------------------
    */

    'chat' => [
        'queue' => env('AI_BRIDGE_CHAT_QUEUE', 'chat'),
        'channel_prefix' => 'chat',
        'summarizer_threshold' => 20,
        'default_instructions' => 'You are a helpful AI assistant.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Knowledge / MCP / Vector Search
    |--------------------------------------------------------------------------
    */

    'knowledge' => [
        'chunk_max_chars' => 3200,
        'chunk_overlap_chars' => 400,
        'embedding_dimensions' => 1536,
        'vector_min_similarity' => 0.4,
        'vector_search_limit' => 5,
        'content_truncation' => 1500,
        'topics_cache_ttl' => 300,

        'db' => [
            'driver' => 'pgsql',
            'host' => env('AI_BRIDGE_KNOWLEDGE_DB_HOST', '127.0.0.1'),
            'port' => env('AI_BRIDGE_KNOWLEDGE_DB_PORT', '5432'),
            'charset' => 'utf8',
            'sslmode' => env('AI_BRIDGE_KNOWLEDGE_DB_SSLMODE', 'prefer'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Tools
    |--------------------------------------------------------------------------
    */

    'tools' => [
        'commands_path' => env('AI_BRIDGE_COMMANDS_PATH'),
        'max_file_size' => 512_000,
        'command_timeout' => 30,

        'allowed_commands' => [
            'php', 'composer', 'npm', 'node', 'npx', 'git', 'ls', 'cat',
            'head', 'tail', 'wc', 'find', 'grep', 'awk', 'sed', 'sort',
            'uniq', 'diff', 'mkdir', 'cp', 'mv', 'touch', 'ps', 'top',
            'free', 'df', 'du', 'uptime', 'whoami', 'hostname',
        ],

        'allowed_sudo_commands' => [
            'supervisorctl' => null,
            'systemctl' => ['status', 'start', 'stop', 'restart', 'reload'],
            'service' => ['status', 'start', 'stop', 'restart'],
            'kill' => null,
            'killall' => null,
            'journalctl' => null,
            'nginx' => ['-s', '-t'],
        ],

        'dangerous_patterns' => [
            'rm -rf /',
            'rm -rf ~',
            '> /dev/',
            'mkfs',
            'dd if=',
            ':(){',
            'chmod -R 777 /',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Systems (populated dynamically per project)
    |--------------------------------------------------------------------------
    */

    'mcp_systems' => [],

];
