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
];
