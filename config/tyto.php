<?php

return [
    'enabled' => env('TYTO_ENABLED', true),
    'token' => env('TYTO_TOKEN'),
    'server_url' => env('TYTO_SERVER_URL', 'https://tyto.test'),

    'environment' => [
        'deploy_id' => env('TYTO_DEPLOY'),
        'server_name' => env('TYTO_SERVER_NAME', gethostname()),
    ],

    'heartbeat' => [
        'enabled' => env('TYTO_HEARTBEAT_ENABLED', true),
        'slug' => env('TYTO_HEARTBEAT_SLUG', 'scheduler'),
        'name' => env('TYTO_HEARTBEAT_NAME', 'Laravel scheduler'),
        'interval' => (int) env('TYTO_HEARTBEAT_INTERVAL', 1),
    ],

    'privacy' => [
        'capture_source_code' => env('TYTO_CAPTURE_SOURCE_CODE', true),
        'capture_payload' => env('TYTO_CAPTURE_PAYLOAD', false),
        'redact_fields' => explode(',', env('TYTO_REDACT_FIELDS', '_token,password,password_confirmation')),
        'redact_headers' => explode(',', env('TYTO_REDACT_HEADERS', 'Authorization,Cookie,Proxy-Authorization,X-XSRF-TOKEN')),
    ],

    'sampling' => [
        'requests' => (float) env('TYTO_SAMPLE_REQUESTS', 1.0),
        'commands' => (float) env('TYTO_SAMPLE_COMMANDS', 1.0),
        'exceptions' => (float) env('TYTO_SAMPLE_EXCEPTIONS', 1.0),
        'tasks' => (float) env('TYTO_SAMPLE_TASKS', 1.0),
    ],

    'ignore' => [
        'cache' => env('TYTO_IGNORE_CACHE', false),
        'mail' => env('TYTO_IGNORE_MAIL', false),
        'notifications' => env('TYTO_IGNORE_NOTIFICATIONS', false),
        'queries' => env('TYTO_IGNORE_QUERIES', false),
        'outgoing_requests' => env('TYTO_IGNORE_OUTGOING', false),
    ],

    'ingest' => [
        'timeout' => (float) env('TYTO_INGEST_TIMEOUT', 2.0),
        'buffer_size' => (int) env('TYTO_INGEST_BUFFER', 500),
        'attempts' => (int) env('TYTO_INGEST_ATTEMPTS', 3),
        'backoff_ms' => (int) env('TYTO_INGEST_BACKOFF_MS', 100),
    ],
];
