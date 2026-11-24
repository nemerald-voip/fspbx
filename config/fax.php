<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook debug logging
    |--------------------------------------------------------------------------
    |
    | When true, the fax_webhook_debug() helper writes detailed step-by-step
    | logs through the standard logger. Enable in .env via FAX_WEBHOOK_DEBUG=true
    | when troubleshooting fax send / receive flows.
    |
    */

    'webhook_debug' => env('FAX_WEBHOOK_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Long txfax channel alert
    |--------------------------------------------------------------------------
    |
    | CheckLongFaxChannelsJob mails this address when a txfax channel is still
    | up after its page budget. There is no default. A blank value skips the
    | mail and still hangs the channel up.
    |
    */

    'long_channel_alert_to' => env('FAX_LONG_CHANNEL_ALERT_TO', ''),
];
