<?php

return [
    'key_id' => env('APNS_KEY_ID', ''),
    'team_id' => env('APNS_TEAM_ID', ''),
    'bundle_id' => env('APNS_BUNDLE_ID', 'com.example.MobileApp'),
    // The VoIP topic sent to APNs is bundle_id + ".voip".
    'key_path' => env('APNS_KEY_PATH', storage_path('app/apns/AuthKey.p8')),
    'production' => env('APNS_PRODUCTION', false),
];
