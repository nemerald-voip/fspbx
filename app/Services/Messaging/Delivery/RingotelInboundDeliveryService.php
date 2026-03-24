<?php

namespace App\Services\Messaging\Delivery;

use App\Jobs\SendSmsNotificationToSlack;
use App\Models\Messages;
use App\Services\Messaging\MessageRepository;
use App\Services\RingotelApiService;

class RingotelInboundDeliveryService
{
    public function __construct(
        protected RingotelApiService $ringotel,
        protected MessageRepository $messages,
    ) {}

    public function deliver(string $messageUuid, string $orgId, string $extension): bool
    {
        $message = Messages::find($messageUuid);

        if (!$message) {
            return false;
        }

        $this->messages->markRingotelStatus($messageUuid, 'queued');

        try {
            if ($message->type === 'mms') {
                foreach ($this->buildMediaUrls($message) as $url) {
                    $response = $this->ringotel->message([
                        'orgid' => $orgId,
                        'from' => $message->source,
                        'to' => $extension,
                        'content' => $url,
                        'type' => 7,
                    ]);

                    if (!isset($response['messageid'])) {
                        throw new \Exception('No messageid returned for MMS');
                    }
                }
            } else {
                $response = $this->ringotel->message([
                    'orgid' => $orgId,
                    'from' => $message->source,
                    'to' => $extension,
                    'content' => $message->message,
                    'type' => 1,
                ]);

                if (!isset($response['messageid'])) {
                    throw new \Exception('No messageid returned for SMS');
                }
            }

            $this->messages->markRingotelStatus($messageUuid, 'success');

            return true;
        } catch (\Throwable $e) {
            $this->messages->markRingotelStatus($messageUuid, 'failed', $e->getMessage());

            SendSmsNotificationToSlack::dispatch(
                "*Inbound message delivery to Ringotel failed*: From: {$message->source} To: {$extension}\n{$e->getMessage()}"
            )->onQueue('messages');

            return false;
        }
    }

    protected function buildMediaUrls(Messages $message): array
    {
        return collect($message->media ?? [])
            ->map(function ($item) {
                if (is_array($item) && !empty($item['access_path'])) {
                    return filter_var($item['access_path'], FILTER_VALIDATE_URL)
                        ? $item['access_path']
                        : url($item['access_path']);
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}