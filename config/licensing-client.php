<?php

return [
    /**
     * The base URL of your licensing server
     */
    'server_url' => env('LICENSING_SERVER_URL', 'https://licensing.example.com'),

    /**
     * API version to use
     */
    'api_version' => env('LICENSING_API_VERSION', 'v1'),

    /**
     * The license key for this application
     */
    'license_key' => env('LICENSING_KEY'),

    /**
     * Public key for PASETO token verification
     */
    'public_key' => env('LICENSING_PUBLIC_KEY'),

    /**
     * Cache configuration
     */
    'cache' => [
        'enabled' => env('LICENSING_CACHE_ENABLED', true),
        'store' => env('LICENSING_CACHE_STORE', 'file'),
        'ttl' => env('LICENSING_CACHE_TTL', 3600), // 1 hour
    ],

    /**
     * Heartbeat configuration
     */
    'heartbeat' => [
        'enabled' => env('LICENSING_HEARTBEAT_ENABLED', true),
        'interval' => env('LICENSING_HEARTBEAT_INTERVAL', 3600), // 1 hour in seconds
    ],

    /**
     * Grace period when license server is unreachable (in days)
     */
    'grace_period_days' => env('LICENSING_GRACE_PERIOD_DAYS', 7),

    /**
     * Timeout for API requests in seconds
     */
    'timeout' => env('LICENSING_TIMEOUT', 30),

    /**
     * Enable debug mode for detailed logging
     */
    'debug' => env('LICENSING_DEBUG', false),

    /**
     * Storage path for offline tokens
     */
    'storage_path' => storage_path('app/licensing'),

    /**
     * Middleware groups to apply license checking
     */
    'middleware_groups' => ['web', 'api'],

    /**
     * Routes to exclude from license checking
     */
    'excluded_routes' => [
        'login',
        'register',
        'password/*',
        'licensing/*',
    ],
];
