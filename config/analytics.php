<?php

return [
    'matomo' => [
        'enabled' => (bool) env('MATOMO_ENABLED', false),
        'base_url' => env('MATOMO_BASE_URL'),
        'site_id' => env('MATOMO_SITE_ID'),
        'api_token' => env('MATOMO_API_TOKEN'),
        'report_timeout_seconds' => (int) env('MATOMO_REPORT_TIMEOUT_SECONDS', 5),
        'report_cache_seconds' => (int) env('MATOMO_REPORT_CACHE_SECONDS', 600),
        'report_stale_seconds' => (int) env('MATOMO_REPORT_STALE_SECONDS', 86400),
    ],
];
