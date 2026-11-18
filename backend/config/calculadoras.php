<?php

/*
| Official Receita Federal calculators (see docs/calculadoras.md).
| They run only on the internal Docker network and have no authentication.
*/

return [
    'regime_geral' => [
        'base_url' => env('CALC_REGULAR_URL', 'http://calc-regular:8080/api'),
        'timeout' => (int) env('CALC_TIMEOUT', 60),
        'connect_timeout' => (int) env('CALC_CONNECT_TIMEOUT', 5),
        'retries' => (int) env('CALC_RETRIES', 2),
        'retry_sleep_ms' => (int) env('CALC_RETRY_SLEEP_MS', 500),
    ],

    'simples_nacional' => [
        'base_url' => env('CALC_SIMPLES_URL', 'http://calc-simples:8082/api'),
        'timeout' => (int) env('CALC_TIMEOUT', 60),
        'connect_timeout' => (int) env('CALC_CONNECT_TIMEOUT', 5),
        'retries' => (int) env('CALC_RETRIES', 2),
        'retry_sleep_ms' => (int) env('CALC_RETRY_SLEEP_MS', 500),
    ],

    // Open-data endpoints (NCM, NBS, cClassTrib, rates, Simples activities) are cached in Redis.
    'cache_store' => env('CALC_CACHE_STORE'),
    'cache_ttl' => (int) env('CALC_CACHE_TTL', 60 * 60 * 24),

    'log_channel' => env('CALC_LOG_CHANNEL', 'calculadora'),

    // Real calculator integration tests (tests/Integration) run only when this is true.
    'integration_tests' => (bool) env('CALC_INTEGRATION_TESTS', false),
];
