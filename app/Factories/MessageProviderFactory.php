<?php
namespace App\Factories;

use App\Services\ApidazeMessageProvider;
use App\Services\BandwidthMessageProvider;
use App\Services\ClickSendMessageProvider;
use App\Services\CommioMessageProvider;
use App\Services\Interfaces\MessageProviderInterface;
use App\Services\SinchMessageProvider;
use App\Services\TelnyxMessageProvider;
use Exception;

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
            'apidaze'   => new ApidazeMessageProvider(),
            default     => throw new Exception("Unsupported carrier: {$carrier}"),
        };
    }
}