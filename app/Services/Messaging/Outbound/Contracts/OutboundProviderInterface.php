<?php

namespace App\Services\Messaging\Outbound\Contracts;

use App\Models\Messages;
use App\Services\Messaging\Outbound\Data\OutboundSendResultData;

interface OutboundProviderInterface
{
    public function send(Messages $message): OutboundSendResultData;
}