<?php

use Laravel\Pulse\Http\Middleware\Authorize;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders;

return [
    'domain' => env('PULSE_DOMAIN'),
    'path' => env('PULSE_PATH', 'pulse'),

    // Runtime telemetry is enabled deliberately by the deployment environment.
    'enabled' => filter_var(env('PULSE_ENABLED', false), FILTER_VALIDATE_BOOL),

    'storage' => [
        'driver' => env('PULSE_STORAGE_DRIVER', 'database'),
        'trim' => [
            'keep' => env('PULSE_STORAGE_KEEP', '3 days'),
        ],
        'database' => [
            'connection' => env('PULSE_DB_CONNECTION'),
            'chunk' => (int) env('PULSE_DB_CHUNK', 1000),
        ],
    ],

    'ingest' => [
        'driver' => env('PULSE_INGEST_DRIVER', 'storage'),
        'buffer' => (int) env('PULSE_INGEST_BUFFER', 1000),
        'trim' => [
            'lottery' => [1, 1000],
            'keep' => env('PULSE_INGEST_KEEP', '3 days'),
        ],
        'redis' => [
            'connection' => env('PULSE_REDIS_CONNECTION'),
            'chunk' => (int) env('PULSE_REDIS_CHUNK', 1000),
        ],
    ],

    'cache' => env('PULSE_CACHE_DRIVER'),

    'middleware' => [
        'web',
        Authorize::class,
    ],

    'recorders' => [
        Recorders\CacheInteractions::class => [
            'enabled' => env('PULSE_CACHE_INTERACTIONS_ENABLED', false),
            'sample_rate' => (float) env('PULSE_CACHE_INTERACTIONS_SAMPLE_RATE', 1),
            'ignore' => [
                ...Pulse::defaultVendorCacheKeys(),
            ],
            'groups' => [
                '/^job-exceptions:.*/' => 'job-exceptions:*',
            ],
        ],

        Recorders\Exceptions::class => [
            'enabled' => env('PULSE_EXCEPTIONS_ENABLED', true),
            'sample_rate' => (float) env('PULSE_EXCEPTIONS_SAMPLE_RATE', 1),
            'location' => env('PULSE_EXCEPTIONS_LOCATION', true),
            'ignore' => [],
        ],

        Recorders\Queues::class => [
            'enabled' => env('PULSE_QUEUES_ENABLED', true),
            'sample_rate' => (float) env('PULSE_QUEUES_SAMPLE_RATE', 1),
            'ignore' => [],
        ],

        Recorders\Servers::class => [
            'server_name' => env('PULSE_SERVER_NAME', gethostname()),
            'directories' => explode(':', env('PULSE_SERVER_DIRECTORIES', '/')),
        ],

        Recorders\SlowJobs::class => [
            'enabled' => env('PULSE_SLOW_JOBS_ENABLED', true),
            'sample_rate' => (float) env('PULSE_SLOW_JOBS_SAMPLE_RATE', 1),
            'threshold' => (int) env('PULSE_SLOW_JOBS_THRESHOLD', 1000),
            'ignore' => [],
        ],

        Recorders\SlowOutgoingRequests::class => [
            'enabled' => env('PULSE_SLOW_OUTGOING_REQUESTS_ENABLED', true),
            'sample_rate' => (float) env('PULSE_SLOW_OUTGOING_REQUESTS_SAMPLE_RATE', 1),
            'threshold' => (int) env('PULSE_SLOW_OUTGOING_REQUESTS_THRESHOLD', 750),
            'ignore' => [],
            // Store only the dependency host. Full URLs may contain API tokens or private query data.
            'groups' => [
                '#^https?://([^/?#]+).*$#' => '\\1',
            ],
        ],

        Recorders\SlowQueries::class => [
            'enabled' => env('PULSE_SLOW_QUERIES_ENABLED', true),
            'sample_rate' => (float) env('PULSE_SLOW_QUERIES_SAMPLE_RATE', 1),
            'threshold' => (int) env('PULSE_SLOW_QUERIES_THRESHOLD', 250),
            'location' => env('PULSE_SLOW_QUERIES_LOCATION', true),
            'max_query_length' => (int) env('PULSE_SLOW_QUERIES_MAX_QUERY_LENGTH', 2048),
            'ignore' => [
                '/(["`])pulse_[\w]+?\1/',
                '/(["`])telescope_[\w]+?\1/',
            ],
        ],

        Recorders\SlowRequests::class => [
            'enabled' => env('PULSE_SLOW_REQUESTS_ENABLED', true),
            'sample_rate' => (float) env('PULSE_SLOW_REQUESTS_SAMPLE_RATE', 1),
            'threshold' => (int) env('PULSE_SLOW_REQUESTS_THRESHOLD', 1000),
            'ignore' => [
                '#^/'.env('PULSE_PATH', 'pulse').'$#',
                '#^/telescope#',
            ],
        ],

        Recorders\UserJobs::class => [
            'enabled' => env('PULSE_USER_JOBS_ENABLED', false),
            'sample_rate' => (float) env('PULSE_USER_JOBS_SAMPLE_RATE', 1),
            'ignore' => [],
        ],

        Recorders\UserRequests::class => [
            'enabled' => env('PULSE_USER_REQUESTS_ENABLED', false),
            'sample_rate' => (float) env('PULSE_USER_REQUESTS_SAMPLE_RATE', 1),
            'ignore' => [
                '#^/'.env('PULSE_PATH', 'pulse').'$#',
                '#^/telescope#',
            ],
        ],
    ],
];
