<?php

return [
    'base_url' => env('APIDAZE_BASE_URL', 'https://cpaas-api.voipinnovations.com'),
    'endpoint' => '/sms/send',
    'api_key' => env('APIDAZE_API_KEY'),
    'api_secret' => env('APIDAZE_API_SECRET'),
];