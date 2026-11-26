<?php

return [
    'endpoint' => env('VI_SMS_ENDPOINT', 'https://backoffice.voipinnovations.com/Services/APIService.asmx'),
    'username' => env('VI_SMS_USERNAME', ''),
    'password' => env('VI_SMS_PASSWORD', ''),
    'connection_timeout' => (int) env('VI_SMS_CONNECTION_TIMEOUT', 15),
    'mms_max_file_bytes' => (int) env('VI_MMS_MAX_FILE_BYTES', 1048576),
    'inbound_webhook_key' => env('VI_INBOUND_WEBHOOK_KEY', ''),
];
