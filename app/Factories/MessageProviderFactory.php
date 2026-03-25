<?php
namespace App\Factories;

use App\Services\CommioMessageProvider;
use App\Services\Interfaces\MessageProviderInterface;
use App\Services\TelnyxMessageProvider;
use Exception;

class MessageProviderFactory
{
    public static function make(string $carrier): MessageProviderInterface
    {
        return match ($carrier) {
            'thinq'     => new CommioMessageProvider(),
            'telnyx'    => new TelnyxMessageProvider(),
            default     => throw new Exception("Unsupported carrier: {$carrier}"),
        };
    }
}