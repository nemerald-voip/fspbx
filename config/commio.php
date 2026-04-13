<?php

return [
    'base_url' => env('COMMIO_BASE_URL', 'https://api.thinq.com'),
    'send_endpoint' => env('COMMIO_SEND_ENDPOINT', '/messaging/send'),
    'account_id' => env('THINQ_ACCOUNT_ID'),
    'username' => env('THINQ_USERNAME'),
    'token' => env('THINQ_TOKEN'),
    'webhook_secret' => env('COMMIO_WEBHOOK_SECRET'),
];
