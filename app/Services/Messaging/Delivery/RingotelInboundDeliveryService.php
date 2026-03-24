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
        messaging_webhook_debug('Ringotel delivery started', [
            'message_uuid' => $messageUuid,
            'org_id' => $orgId,
            'extension' => $extension,
        ]);

        $message = Messages::find($messageUuid);

        if (!$message) {
            messaging_webhook_debug('Ringotel delivery message not found', [
                'message_uuid' => $messageUuid,
            ]);
            return false;
        }

        $this->messages->markRingotelStatus($messageUuid, 'queued');

        try {
            if ($message->type === 'mms') {
                $mediaUrls = $this->buildMediaUrls($message);

                if (empty($mediaUrls)) {
                    throw new \Exception('MMS message has no accessible media URLs.');
                }

                foreach ($mediaUrls as $url) {
                    messaging_webhook_debug('Sending MMS media to Ringotel', [
                        'message_uuid' => $messageUuid,
                        'url' => $url,
                    ]);

                    $response = $this->ringotel->message([
                        'orgid' => $orgId,
                        'from' => $message->source,
                        'to' => $extension,
                        'content' => $url,
                        'type' => 7,
                    ]);

                    messaging_webhook_debug('Ringotel MMS response received', [
                        'response' => $response,
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

                messaging_webhook_debug('Ringotel SMS response received', [
                    'response' => $response,
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
