<?php

namespace App\Services\Messaging\Delivery;

use App\Mail\SmsToEmail;
use App\Models\Messages;
use App\Services\MessageMediaObjectStorageService;
use App\Services\Messaging\MessageRepository;
use Illuminate\Support\Facades\Mail;

class InboundMessageEmailService
{
    public function __construct(
        protected MessageRepository $messages,
        protected MessageMediaObjectStorageService $mediaStorage,
    ) {}

    public function deliver(string $messageUuid, ?string $orgId, string $email): bool
    {
        $message = Messages::find($messageUuid);

        if (!$message) {
            return false;
        }

        $this->messages->markEmailStatus($messageUuid, 'queued', $email);

        try {
            $attachments = $this->buildEmailAttachments($message);

            Mail::to($email)->send(
                new SmsToEmail($message, $orgId, $attachments)
            );

            $this->messages->markEmailStatus($messageUuid, 'success', $email);

            return true;
        } catch (\Throwable $e) {
            $this->messages->markEmailStatus($messageUuid, 'failed', $email, $e->getMessage());

            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return false;
        }
    }

    protected function buildEmailAttachments(Messages $message): array
    {
        $attachments = [];

        foreach (($message->media ?? []) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $bucket = $item['bucket'] ?? null;
            $objectKey = $item['object_key'] ?? null;

            if (!$bucket || !$objectKey) {
                continue;
            }

            try {
                $object = $this->mediaStorage->getObjectForDomain(
                    domainUuid: $message->domain_uuid,
                    bucket: $bucket,
                    objectKey: $objectKey,
                );

                $attachments[] = [
                    'data' => (string) $object['body'],
                    'name' => $item['original_name']
                        ?? $item['stored_name']
                        ?? basename($objectKey)
                        ?? ('attachment-' . $index),
                    'mime' => $item['mime_type']
                        ?? $object['content_type']
                        ?? 'application/octet-stream',
                ];
            } catch (\Throwable $e) {
                logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            }
        }

        return $attachments;
    }
}