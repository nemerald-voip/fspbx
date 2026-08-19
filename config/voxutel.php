<?php

return [
    'api_key' => env('VOXUTEL_API_KEY'),
    'message_broker_url' => env('VOXUTEL_MESSAGE_BROKER_URL'),
    'inbound_api_key' => env('VOXUTEL_INBOUND_API_KEY'),
    'inbound_signature_tolerance_seconds' => 300,
];
