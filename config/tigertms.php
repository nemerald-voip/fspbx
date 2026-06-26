<?php

return [
    'base_url' => env('TIGERTMS_BASE_URL', 'https://integrationtesting.tigertms.com/ilinkweb'),
    'username' => env('TIGERTMS_USERNAME'),
    'password' => env('TIGERTMS_PASSWORD'),
    'timeout' => (int) env('TIGERTMS_TIMEOUT', 20),
    'default_language' => env('TIGERTMS_DEFAULT_LANGUAGE', 'en-US'),
    'webhook_secret' => env('TIGERTMS_WEBHOOK_SECRET'),
    'webhook_signature_tolerance_seconds' => (int) env('TIGERTMS_WEBHOOK_SIGNATURE_TOLERANCE_SECONDS', 300),

    'test_site_id' => env('TIGERTMS_TEST_SITE_ID'),
    'test_domain_uuid' => env('TIGERTMS_TEST_DOMAIN_UUID'),

    'rate' => [
        'max_attempts' => (int) env('TIGERTMS_WEBHOOK_RATE_MAX_ATTEMPTS', 120),
        'decay_seconds' => (int) env('TIGERTMS_WEBHOOK_RATE_DECAY_SECONDS', 60),
    ],
];
