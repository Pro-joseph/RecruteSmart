<?php

declare(strict_types=1);

return [

    'provider' => env('LLM_PROVIDER', 'groq'),

    'api_key' => env('LLM_API_KEY'),

    'base_url' => env('LLM_BASE_URL', 'https://api.groq.com/openai/v1'),

    'model' => env('LLM_MODEL', 'llama-3.3-70b-versatile'),

    /*
    | true  → response_format json_schema (strict, only gpt-oss models)
    | false → response_format json_object + opis schema validation (all models)
    */
    'structured_output' => (bool) env('LLM_STRUCTURED_OUTPUT', false),

    'temperature' => (float) env('LLM_TEMPERATURE', 0.1),

    'max_output_tokens' => (int) env('LLM_MAX_OUTPUT_TOKENS', 1500),

    'timeout' => (int) env('LLM_TIMEOUT', 60),

    'max_input_chars' => (int) env('LLM_MAX_INPUT_CHARS', 30000),

    'daily_limit_per_user' => (int) env('LLM_DAILY_LIMIT_PER_USER', 300),

    'rate_limit_per_minute' => (int) env('LLM_RATE_LIMIT_PER_MINUTE', 20),

    'prompt_version' => env('PROMPT_VERSION', 'v1'),
];
