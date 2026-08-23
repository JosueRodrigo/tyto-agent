<?php

return [
    'enabled' => env('TYTO_ENABLED', config('laraowl.enabled', env('LARAOWL_ENABLED', true))),
    'token' => env('TYTO_TOKEN', config('laraowl.token', env('LARAOWL_TOKEN'))),
    'server_url' => env('TYTO_SERVER_URL', config('laraowl.server_url', env('LARAOWL_SERVER_URL', 'https://tyto.test'))),

    'environment' => [
        'deploy_id' => env('TYTO_DEPLOY', config('laraowl.environment.deploy_id', env('LARAOWL_DEPLOY'))),
        'server_name' => env('TYTO_SERVER_NAME', config('laraowl.environment.server_name', env('LARAOWL_SERVER_NAME', gethostname()))),
    ],

    'privacy' => [
        'capture_source_code' => env('TYTO_CAPTURE_SOURCE_CODE', config('laraowl.privacy.capture_source_code', env('LARAOWL_CAPTURE_SOURCE_CODE', true))),
        'capture_payload' => env('TYTO_CAPTURE_PAYLOAD', config('laraowl.privacy.capture_payload', env('LARAOWL_CAPTURE_PAYLOAD', false))),
        'redact_fields' => explode(',', env('TYTO_REDACT_FIELDS', implode(',', config('laraowl.privacy.redact_fields', ['_token', 'password', 'password_confirmation'])))),
        'redact_headers' => explode(',', env('TYTO_REDACT_HEADERS', implode(',', config('laraowl.privacy.redact_headers', ['Authorization', 'Cookie', 'Proxy-Authorization', 'X-XSRF-TOKEN'])))),
    ],

    'sampling' => [
        'requests' => (float) env('TYTO_SAMPLE_REQUESTS', config('laraowl.sampling.requests', env('LARAOWL_SAMPLE_REQUESTS', 1.0))),
        'commands' => (float) env('TYTO_SAMPLE_COMMANDS', config('laraowl.sampling.commands', env('LARAOWL_SAMPLE_COMMANDS', 1.0))),
        'exceptions' => (float) env('TYTO_SAMPLE_EXCEPTIONS', config('laraowl.sampling.exceptions', env('LARAOWL_SAMPLE_EXCEPTIONS', 1.0))),
        'tasks' => (float) env('TYTO_SAMPLE_TASKS', config('laraowl.sampling.tasks', env('LARAOWL_SAMPLE_TASKS', 1.0))),
    ],

    'ignore' => [
        'cache' => env('TYTO_IGNORE_CACHE', config('laraowl.ignore.cache', env('LARAOWL_IGNORE_CACHE', false))),
        'mail' => env('TYTO_IGNORE_MAIL', config('laraowl.ignore.mail', env('LARAOWL_IGNORE_MAIL', false))),
        'notifications' => env('TYTO_IGNORE_NOTIFICATIONS', config('laraowl.ignore.notifications', env('LARAOWL_IGNORE_NOTIFICATIONS', false))),
        'queries' => env('TYTO_IGNORE_QUERIES', config('laraowl.ignore.queries', env('LARAOWL_IGNORE_QUERIES', false))),
        'outgoing_requests' => env('TYTO_IGNORE_OUTGOING', config('laraowl.ignore.outgoing_requests', env('LARAOWL_IGNORE_OUTGOING', false))),
    ],

    'ingest' => [
        'timeout' => (float) env('TYTO_INGEST_TIMEOUT', config('laraowl.ingest.timeout', env('LARAOWL_INGEST_TIMEOUT', 2.0))),
        'buffer_size' => (int) env('TYTO_INGEST_BUFFER', config('laraowl.ingest.buffer_size', env('LARAOWL_INGEST_BUFFER', 500))),
    ],
];
