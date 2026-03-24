<?php

namespace App\Services\Messaging\Delivery;

use App\Mail\SmsToEmail;
use App\Models\Messages;
use App\Services\Messaging\MessageRepository;
use Illuminate\Support\Facades\Mail;

class InboundMessageEmailService
{
    public function __construct(
        protected MessageRepository $messages,
    ) {}

    public function deliver(string $messageUuid, ?string $orgId, string $email): bool
    {
        $message = Messages::find($messageUuid);

        if (!$message) {
            return false;
        }

        $this->messages->markEmailStatus($messageUuid, 'queued', $email);

        try {
            Mail::to($email)->send(new SmsToEmail($message, $orgId));

            $this->messages->markEmailStatus($messageUuid, 'success', $email);

            return true;
        } catch (\Throwable $e) {
            $this->messages->markEmailStatus($messageUuid, 'failed', $email, $e->getMessage());

            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return false;
        }
    }
}