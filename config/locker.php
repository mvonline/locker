<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Connection
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache connection used by the locker.
    | You can override this per lock instance if needed.
    |
    */
    'connection' => env('LOCKER_CACHE_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Default Lock Settings
    |--------------------------------------------------------------------------
    |
    | These are the default settings applied to all locks unless overridden.
    |
    */
    'defaults' => [
        'ttl' => 60,
        'type' => 'simple',
    ],

    /*
    |--------------------------------------------------------------------------
    | Redlock Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Redlock algorithm (Redis-only).
    |
    */
    'redlock' => [
        'connections' => ['default'],
        'clock_drift_factor' => 0.01,
        'quorum' => null, // Auto-calculated as (N/2) + 1
    ],
];

