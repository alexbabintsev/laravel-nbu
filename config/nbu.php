<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | API endpoint
    |--------------------------------------------------------------------------
    |
    | The NBU statistics directory. This endpoint already normalises rates to a
    | single unit, unlike NBU_Exchange, which reports some currencies per 10 or
    | 100 units and would need the Units field applied by hand.
    |
    */
    'endpoint' => env('NBU_ENDPOINT', 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange'),

    /*
    |--------------------------------------------------------------------------
    | Request timeout
    |--------------------------------------------------------------------------
    |
    | Kept short: a page rendering a price should not wait on the bank. When the
    | request does time out the last known rates are served instead.
    |
    */
    'timeout' => (int) env('NBU_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | The NBU publishes one rate per banking day, so today's rates are cached
    | until midnight and a past date's forever - a rate that has already been
    | set never changes.
    |
    | `fallback_days` is how long the last successful response is kept as a
    | safety net. It is only read when the bank cannot be reached.
    |
    */
    'cache' => [
        'store' => env('NBU_CACHE_STORE'),
        'prefix' => env('NBU_CACHE_PREFIX', 'nbu'),
        'fallback_days' => (int) env('NBU_FALLBACK_DAYS', 30),
    ],
];
