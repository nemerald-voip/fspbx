<?php

namespace App\Jobs;

use App\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Address;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class RetryLoggedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $maxExceptions = 3;
    public $timeout = 120;
    public $failOnTimeout = true;
    public $backoff = [30, 60, 120, 300];

    public function __construct(
        public string $logUuid
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $log = EmailLog::query()->find($this->logUuid);

        if (! $log) {
            return;
        }

        try {
            $this->appendDebug(
                $log,
                'Retry attempt #' . $this->attempts() . ' started at ' . now()->toDateTimeString()
            );

            $log->update([
                'status' => 'sending',
            ]);

            // Rebuild the failover transport for this retry attempt.
            // This helps avoid stale RoundRobinTransport state in long-running Horizon workers.
            Mail::purge(config('mail.default'));

            Mail::send([], [
                'attributes' => [
                    'logId'       => (string) $log->uuid,
                    'domain_uuid' => $log->domain_uuid,
                    'is_retry'    => true,
                ],
            ], function ($message) use ($log) {
                $this->applyFromAddress($message, $log->from);
                $this->applyRecipients($message, 'to', $log->to);
                $this->applyRecipients($message, 'cc', $log->cc);
                $this->applyRecipients($message, 'bcc', $log->bcc);

                $message->subject((string) $log->subject);

                $symfonyMessage = $message->getSymfonyMessage();

                if (! empty($log->html_body)) {
                    $symfonyMessage->html((string) $log->html_body);

                    if (! empty($log->text_body)) {
                        $symfonyMessage->text((string) $log->text_body);
                    }
                } elseif (! empty($log->text_body)) {
                    $symfonyMessage->text((string) $log->text_body);
                } elseif (! empty($log->raw_body)) {
                    $symfonyMessage->text((string) $log->raw_body);
                } else {
                    throw new \RuntimeException('Email log does not contain a usable body.');
                }

                $this->attachFromLog($message, $log->attachments);
            });

            $log->refresh();

            $this->appendDebug(
                $log,
                'Retry sent successfully at ' . now()->toDateTimeString()
            );
        } catch (\Throwable $e) {
            $classification = $this->classifyMailException($e);

            $log->refresh();
            $log->update([
                'status' => $classification['status'],
            ]);

            $this->appendDebug(
                $log,
                'Retry failed at ' . now()->toDateTimeString() . ': ' . $classification['summary']
            );

            if (! empty($classification['details'])) {
                $this->appendDebug($log, 'Details: ' . $classification['details']);
            }

            logger()->warning('RetryLoggedEmail send failure', [
                'log_uuid' => $log->uuid,
                'attempt' => $this->attempts(),
                'status' => $classification['status'],
                'category' => $classification['category'],
                'summary' => $classification['summary'],
                'details' => $classification['details'],
            ]);

            // Temporary lockout: delay instead of hammering the SMTP server again immediately
            if ($classification['category'] === 'smtp_lockout') {
                $this->release($classification['delay']);
                return;
            }

            // Permanent config/auth problems: stop retrying now
            if ($classification['category'] === 'auth_invalid' || $classification['category'] === 'config_error') {
                $this->fail($e);
                return;
            }

            throw $e;
        }
    }

    protected function classifyMailException(\Throwable $e): array
    {
        $raw = $this->exceptionMessageWithPrevious($e);
        $normalized = strtolower($raw);

        // SMTP auth lockout / rate limiting
        if (
            str_contains($normalized, 'too many failed login attempts') ||
            str_contains($normalized, 'temporarily locked') ||
            str_contains($normalized, 'try again later')
        ) {
            return [
                'category' => 'smtp_lockout',
                'status' => 'failed',
                'delay' => 900, // 15 minutes
                'summary' => 'SMTP authentication temporarily locked due to too many failed login attempts.',
                'details' => $this->shortenTransportMessage($raw),
            ];
        }

        // Permanent SMTP auth/config failures
        if (
            str_contains($normalized, 'invalid credentials') ||
            str_contains($normalized, 'authentication failed') ||
            str_contains($normalized, 'failed to authenticate on smtp server')
        ) {
            return [
                'category' => 'auth_invalid',
                'status' => 'permanent_failed',
                'delay' => null,
                'summary' => 'SMTP authentication failed. Check the outgoing mail username, password, and server settings.',
                'details' => $this->shortenTransportMessage($raw),
            ];
        }

        // Common config / addressing issues
        if (
            str_contains($normalized, 'rfc 2822') ||
            str_contains($normalized, 'addr-spec') ||
            str_contains($normalized, 'invalid from address format') ||
            str_contains($normalized, 'no valid recipient found') ||
            str_contains($normalized, 'does not contain a usable body')
        ) {
            return [
                'category' => 'config_error',
                'status' => 'permanent_failed',
                'delay' => null,
                'summary' => 'Email could not be sent because of invalid message data or mail configuration.',
                'details' => $this->shortenTransportMessage($raw),
            ];
        }

        // Everything else: retryable generic transport failure
        return [
            'category' => 'transport_error',
            'status' => 'failed',
            'delay' => null,
            'summary' => 'Email send attempt failed due to a mail transport error.',
            'details' => $this->shortenTransportMessage($raw),
        ];
    }

    protected function exceptionMessageWithPrevious(\Throwable $e): string
    {
        $messages = [];

        do {
            $messages[] = get_class($e) . ': ' . $e->getMessage();
            $e = $e->getPrevious();
        } while ($e);

        return implode(' | Previous: ', $messages);
    }

    protected function shortenTransportMessage(string $message, int $max = 500): string
    {
        $message = preg_replace('/\s+/', ' ', trim($message));

        if (mb_strlen($message) <= $max) {
            return $message;
        }

        return mb_substr($message, 0, $max - 3) . '...';
    }

    public function failed(\Throwable $e): void
    {
        $log = EmailLog::query()->find($this->logUuid);

        if (! $log) {
            return;
        }

        $classification = $this->classifyMailException($e);

        $log->update([
            'status' => 'permanent_failed',
        ]);

        $this->appendDebug(
            $log,
            'Retry permanently failed at ' . now()->toDateTimeString() . ': ' . $classification['summary']
        );
    }

    protected function applyFromAddress($message, ?string $from): void
    {
        $from = trim((string) $from);

        if ($from === '') {
            return;
        }

        $address = Address::create($from);

        $message->from(
            $address->getAddress(),
            $address->getName() ?: null
        );
    }

    protected function applyRecipients($message, string $method, ?string $value): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        $parts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value) ?: [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $address = Address::create($part);

            $message->{$method}(
                $address->getAddress(),
                $address->getName() ?: null
            );
        }
    }

    protected function attachFromLog($message, $attachments): void
    {
        if (is_string($attachments)) {
            $attachments = json_decode($attachments, true);
        }

        if (! is_array($attachments) || empty($attachments)) {
            return;
        }

        foreach ($attachments as $attachment) {
            if (is_string($attachment)) {
                if (is_file($attachment)) {
                    $message->attach($attachment);
                }
                continue;
            }

            if (! is_array($attachment)) {
                continue;
            }

            $name = $attachment['name']
                ?? $attachment['filename']
                ?? $attachment['original_name']
                ?? 'attachment';

            $mime = $attachment['mime']
                ?? $attachment['content_type']
                ?? null;

            if (! empty($attachment['content'])) {
                $decoded = base64_decode($attachment['content'], true);

                if ($decoded !== false) {
                    $options = $mime ? ['mime' => $mime] : [];
                    $message->attachData($decoded, $name, $options);
                }

                continue;
            }

            $disk = $attachment['disk'] ?? null;
            $path = $attachment['path']
                ?? $attachment['file_path']
                ?? $attachment['storage_path']
                ?? null;

            if ($disk && $path && Storage::disk($disk)->exists($path)) {
                $options = $mime ? ['mime' => $mime] : [];

                $message->attachData(
                    Storage::disk($disk)->get($path),
                    $name ?: basename($path),
                    $options
                );

                continue;
            }

            if ($path && is_file($path)) {
                $options = [];

                if ($name) {
                    $options['as'] = $name;
                }

                if ($mime) {
                    $options['mime'] = $mime;
                }

                $message->attach($path, $options);
            }
        }
    }

    protected function appendDebug(EmailLog $log, string $line): void
    {
        $existing = trim((string) $log->sent_debug_info);

        $log->update([
            'sent_debug_info' => $existing
                ? $existing . PHP_EOL . $line
                : $line,
        ]);
    }
}
