<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Slack Webhook URLs
    |--------------------------------------------------------------------------
    |
    | Options for slack webhook URLs
    |
    */

    'fax' => env('SLACK_FAX_HOOK', ''),
    'sms' => env('SLACK_SMS_HOOK', ''),
    'system_status' => env('SLACK_SYSTEM_STATUS_HOOK', ''),


];
