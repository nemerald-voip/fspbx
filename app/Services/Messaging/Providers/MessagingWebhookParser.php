<?php

namespace App\Services\Messaging\Providers;

use App\Services\Messaging\Data\DownloadedMediaData;
use Spatie\WebhookClient\Models\WebhookCall;

interface MessagingWebhookParser
{
    /**
     * @return iterable<object>
     */
    public function parse(WebhookCall $webhookCall): iterable;

    public function downloadMedia(string $url): DownloadedMediaData;
}