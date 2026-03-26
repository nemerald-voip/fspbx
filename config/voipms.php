<?php

return [
    'base_url' => env('VOIPMS_BASE_URL', 'https://voip.ms/api/v1/rest.php'),
    'api_username' => env('VOIPMS_API_USERNAME'),
    'api_password' => env('VOIPMS_API_PASSWORD'),
    'timeout' => (int) env('VOIPMS_TIMEOUT', 30),
];