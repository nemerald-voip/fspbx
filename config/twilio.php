<?php

return [
    'account_sid' => env('TWILIO_ACCOUNT_SID'),
    'auth_token'  => env('TWILIO_AUTH_TOKEN'),
    'base_url'    => env('TWILIO_BASE_URL', 'https://api.twilio.com/2010-04-01'),
];
