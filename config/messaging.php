<?php

return [
    'webhook_debug' => env('MESSAGING_WEBHOOK_DEBUG', false),

    'outbound_providers' => [
        'sinch' => \App\Services\Messaging\Outbound\Providers\SinchOutboundProvider::class,
        'bandwidth' => \App\Services\Messaging\Outbound\Providers\BandwidthOutboundProvider::class,
        'telnyx' => \App\Services\Messaging\Outbound\Providers\TelnyxOutboundProvider::class,
        'clicksend' => \App\Services\Messaging\Outbound\Providers\ClickSendOutboundProvider::class,
        'apidaze' => \App\Services\Messaging\Outbound\Providers\ApidazeOutboundProvider::class,
        // 'thinq' => \App\Services\Messaging\Outbound\Providers\CommioOutboundProvider::class,
    ],
];