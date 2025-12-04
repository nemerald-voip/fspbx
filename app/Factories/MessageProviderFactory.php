<?php
namespace App\Factories;

use Exception;
use App\Services\SinchMessageProvider;
use App\Services\CommioMessageProvider;
use App\Services\TelnyxMessageProvider;
use App\Services\BandwidthMessageProvider;
use App\Services\ClickSendMessageProvider;
use App\Services\Interfaces\MessageProviderInterface;

class MessageProviderFactory
{
    public static function make(string $carrier): MessageProviderInterface
    {
        return match ($carrier) {
            'thinq'     => new CommioMessageProvider(),
            'sinch'     => new SinchMessageProvider(),
            'bandwidth' => new BandwidthMessageProvider(),
            'telnyx'    => new TelnyxMessageProvider(),
            'clicksend' => new ClickSendMessageProvider(),
            default     => throw new Exception("Unsupported carrier: {$carrier}"),
        };
    }
}