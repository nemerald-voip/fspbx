<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Str;

class EmailSentLogger
{
    public function handle(MessageSent $event): void
    {
        try {
            $logId = optional($event->message->getHeaders()->get('X-Email-Log-Id'))
                ?->getBodyAsString()
                ?? data_get($event->data, 'attributes.logId');

            if (!$logId) {
                // nothing to update
                return;
            }

            $updates = [
                'status' => 'sent',
            ];

            $providerMessageId = $this->likelyUsesPostmark()
                ? $this->providerMessageId($event)
                : null;

            if ($providerMessageId) {
                $updates['provider'] = 'postmark';
                $updates['provider_message_id'] = $providerMessageId;
                $updates['provider_message_stream'] = config('mail.mailers.postmark.message_stream_id');
            }

            EmailLog::query()
                ->where('uuid', $logId)
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhereNotIn('status', ['failed', 'permanent_failed']);
                })
                ->update($updates);
        } catch (\Throwable $e) {
            logger('EmailSentLogger@handle error ' . $e->getMessage());
        }
    }

    private function providerMessageId(MessageSent $event): ?string
    {
        $sent = $event->sent ?? null;

        if ($sent && method_exists($sent, 'getMessageId')) {
            $messageId = trim((string) $sent->getMessageId(), " <>\t\n\r\0\x0B");

            if (Str::isUuid($messageId)) {
                return $messageId;
            }
        }

        foreach (['X-Postmark-MessageID', 'X-Postmark-Message-Id', 'X-PM-MessageID'] as $headerName) {
            $header = $event->message->getHeaders()->get($headerName);
            $value = $header ? trim((string) $header->getBodyAsString()) : '';

            if (Str::isUuid($value)) {
                return $value;
            }
        }

        return null;
    }

    private function likelyUsesPostmark(): bool
    {
        $defaultMailer = config('mail.default');
        $mailer = config("mail.mailers.{$defaultMailer}", []);

        if (($mailer['transport'] ?? null) === 'postmark') {
            return true;
        }

        if (($mailer['transport'] ?? null) === 'failover') {
            return collect($mailer['mailers'] ?? [])
                ->contains(fn ($name) => (config("mail.mailers.{$name}.transport") === 'postmark'));
        }

        return str_contains((string) ($mailer['host'] ?? ''), 'postmarkapp.com');
    }
}
