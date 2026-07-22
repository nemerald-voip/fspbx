<?php

return [
    'sms_url' => env('FIBERNETICS_SMS_URL', 'https://smshttpsgw.fibernetics.ca/cgi-bin/sendsms'),
    'sms_username' => env('FIBERNETICS_SMS_USERNAME'),
    'sms_password' => env('FIBERNETICS_SMS_PASSWORD'),

    'mm7_url' => env('FIBERNETICS_MM7_URL', 'https://mmsout.mms.fibernetics.ca:8091/mm7'),
    'mm7_username' => env('FIBERNETICS_MM7_USERNAME'),
    'mm7_password' => env('FIBERNETICS_MM7_PASSWORD'),
    'mm7_version' => env('FIBERNETICS_MM7_VERSION', '6.8.0'),
    'mm7_subject' => env('FIBERNETICS_MM7_SUBJECT', env('APP_NAME', 'Message')),
    'mm7_verify_ssl' => filter_var(env('FIBERNETICS_MM7_VERIFY_SSL', false), FILTER_VALIDATE_BOOL),

    'timeout' => (int) env('FIBERNETICS_TIMEOUT', 60),
    'webhook_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'FIBERNETICS_WEBHOOK_IPS',
            '74.205.214.128/29,74.205.214.136/29,107.150.228.32/29,107.150.228.40/29'
        ))
    ))),
];
