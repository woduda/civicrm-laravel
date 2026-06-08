<?php

declare(strict_types=1);

return [
    'base_url'   => env('CIVICRM_BASE_URL'),
    'api_token'  => env('CIVICRM_API_TOKEN'),
    'site_key'   => env('CIVICRM_SITE_KEY'),

    /*
     * Request timeout in seconds. Applied when the host application binds
     * a configurable PSR-18 client (e.g. Guzzle) in the container.
     */
    'timeout'    => env('CIVICRM_TIMEOUT', 30),

    /*
     * Whether to verify TLS certificates. Applied at the PSR-18 client level
     * when the host application provides a configurable client.
     */
    'verify_tls' => env('CIVICRM_VERIFY_TLS', true),

    /*
     * Retry configuration — honoured once woduda/civicrm-php PR #11 (ExponentialBackoff)
     * lands in the core library. Guarded with class_exists() in the provider.
     */
    'retry' => [
        'enabled'       => env('CIVICRM_RETRY', false),
        'max_attempts'  => 3,
        'base_delay_ms' => 200,
    ],

    'queue' => [
        'connection' => env('CIVICRM_QUEUE_CONNECTION'),
        'queue'      => env('CIVICRM_QUEUE', 'default'),
    ],

    /*
     * Webhook verification — used by the civicrm.webhook middleware (LPR #5).
     */
    'webhook' => [
        'secret'            => env('CIVICRM_WEBHOOK_SECRET'),
        'tolerance_seconds' => 300,
        'headers'           => [
            'signature' => 'X-Civi-Signature',
            'timestamp'  => 'X-Civi-Timestamp',
            'nonce'      => 'X-Civi-Nonce',
        ],
        'cache_store' => env('CIVICRM_WEBHOOK_CACHE'),
        'nonce_ttl'   => 600,
    ],

    /*
     * Transactional outbox — used by the outbox job dispatcher (LPR #3).
     */
    'outbox' => [
        'enabled' => env('CIVICRM_OUTBOX', false),
        'table'   => 'civicrm_outbox',
    ],
];
