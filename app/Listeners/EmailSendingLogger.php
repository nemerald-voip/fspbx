<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Support\Str;
use Illuminate\Mail\Events\MessageSending;

class EmailSendingLogger
{
    /**
     * Create the event listener
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event
     */
    public function handle(MessageSending $event): void
    {
        try {
            $email = $event->message;

            $attributes = $event->data['attributes'] ?? [];
            $logId = $attributes['logId'] ?? (string) Str::uuid();;

            // Ensure header is present for the Sent listener
            $headers = $email->getHeaders();
            if (!$headers->has('X-Email-Log-Id')) {
                $headers->addTextHeader('X-Email-Log-Id', $logId);
            }

            if ($this->likelyUsesPostmark() && !$headers->has('X-PM-Metadata-email_log_uuid')) {
                $headers->addTextHeader('X-PM-Metadata-email_log_uuid', $logId);
            }

            EmailLog::updateOrCreate(
                ['uuid' => $logId], // match on uuid
                [
                    'domain_uuid' => $attributes['domain_uuid'] ?? null,
                    'from'        => $this->recipientsToString($email->getFrom() ?? []),
                    'to'          => $this->recipientsToString($email->getTo() ?? []),
                    'cc'          => $this->recipientsToString($email->getCc() ?? []),
                    'bcc'         => $this->recipientsToString($email->getBcc() ?? []),
                    'subject'     => $email->getSubject(),
                    'html_body'   => $email->getHtmlBody(),
                    'text_body'   => $email->getTextBody(),
                    'status'      => 'sending',
                    'provider'    => $this->likelyUsesPostmark() ? 'postmark' : null,
                    'provider_message_stream' => config('mail.mailers.postmark.message_stream_id'),
                    // 'attachments' => $attributes['attachments'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            logger('EmailSendingLogger@handle error ' . $e->getMessage());
        }
    }

    private function recipientsToString(array $recipients): string
    {
        return implode(
            ', ',
            array_map(function ($email) {
                return $email->toString();
            }, $recipients)
        );
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
