<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | Supported providers: "openrouter", "gemini", "openai", "deepseek", "anthropic", "minimax", "mistral", "chutex"
    |
    */
    'default' => env('AI_PROVIDER', 'openrouter'),

    /*
    |--------------------------------------------------------------------------
    | Providers Configuration
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'chutex' => [
            'api_key' => env('CHUTEX_API_KEY', ''),
            // Sent to 9Router as-is in the "model" field. Normally this is the
            // name of a combo/preset you configured in the 9Router dashboard
            // (e.g. "Chutex" — a routing + vision-adapter combo), not a literal
            // model id. 9Router resolves the combo (provider, fallback, vision
            // handling, etc.) on its side.
            'model' => env('CHUTEX_MODEL', 'Chutex'),
            'base_url' => env('CHUTEX_BASE_URL', 'http://192.168.1.253/v1'),
            // Only used as a last resort when CHUTEX_MODEL is left empty: sends
            // model:"auto" and lets 9Router pick a live model with no preset.
            'auto_route' => env('CHUTEX_AUTO_ROUTE', false),
            // Optional 9Router budget tier: cheap|smart|mini. Only applies when
            // the resolved model is literally "auto". Leave null otherwise.
            'budget_tier' => env('CHUTEX_BUDGET_TIER', null),
            'max_tokens' => env('AI_MAX_TOKENS', 4096),
            'timeout' => env('AI_REQUEST_TIMEOUT', 90),
        ],

        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', 'minimax/minimax-01'),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'site_url' => env('APP_URL', 'http://localhost'),
            'site_name' => env('APP_NAME', 'HR-CII Assistant'),
            'max_tokens' => env('AI_MAX_TOKENS', 4096),
            'timeout' => env('AI_REQUEST_TIMEOUT', 90),
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'max_tokens' => env('AI_MAX_TOKENS', 4096),
            'timeout' => env('AI_REQUEST_TIMEOUT', 60),
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => 'https://api.openai.com/v1',
            'max_tokens' => env('AI_MAX_TOKENS', 4096),
            'timeout' => env('AI_REQUEST_TIMEOUT', 60),
        ],

        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-ai/DeepSeek-V4-Flash'),
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api-inference.bitdeer.ai/v1/'),
            'max_tokens' => env('AI_MAX_TOKENS', 4096),
            'timeout' => env('AI_REQUEST_TIMEOUT', 120),
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
            'base_url' => 'https://api.anthropic.com/v1',
            'max_tokens' => env('AI_MAX_TOKENS', 4096),
            'timeout' => env('AI_REQUEST_TIMEOUT', 60),
        ],

        'minimax' => [
            'api_key' => env('MINIMAX_API_KEY'),
            'model' => env('MINIMAX_MODEL', 'MiniMax-Text-01'),
            'base_url' => env('MINIMAX_BASE_URL', 'https://api.minimaxi.chat/v1'),
            'group_id' => env('MINIMAX_GROUP_ID'),
            'max_tokens' => env('AI_MAX_TOKENS', 4096),
            'timeout' => env('AI_REQUEST_TIMEOUT', 90),
        ],

        'mistral' => [
            'api_key' => env('MISTRAL_API_KEY'),
            'model' => env('MISTRAL_MODEL', 'mistral-large-latest'),
            'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai/v1'),
            'max_tokens' => env('AI_MAX_TOKENS', 4096),
            'timeout' => env('AI_REQUEST_TIMEOUT', 60),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety & SQL Query Guardrails
    |--------------------------------------------------------------------------
    */
    'sql_guardrails' => [
        'max_limit' => 100,
        'forbidden_keywords' => [
            'insert', 'update', 'delete', 'drop', 'alter', 'truncate',
            'create', 'grant', 'revoke', 'replace', 'exec', 'execute',
            'information_schema', 'show tables', 'show databases',
            'outfile', 'dumpfile', 'load_file', 'union', 'into'
        ],
    ],
];