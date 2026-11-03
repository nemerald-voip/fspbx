<?php

namespace App\Services;

use Illuminate\Contracts\Config\Repository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use WeakMap;

class EmailDeliveryFailureReporter
{
    private WeakMap $reported;

    public function __construct(
        private LoggerInterface $logger,
        private Repository $config,
    ) {
        $this->reported = new WeakMap;
    }

    public function report(TransportExceptionInterface $exception, array $context = []): void
    {
        // A job can report with its IDs before Laravel reports the same exception.
        // Weak references avoid retaining failed messages in long-running workers.
        if (isset($this->reported[$exception])) {
            return;
        }

        $message = preg_replace('/\s+/', ' ', trim($exception->getMessage()));
        $normalized = strtolower($message);
        $providerCode = preg_match('/\(code (\d+)\)\.?$/', $message, $matches)
            ? (int) $matches[1]
            : null;

        $code = match (true) {
            $normalized === 'all transports failed.' => 'MAIL_ALL_TRANSPORTS_FAILED',
            $normalized === 'no transports found.' => 'MAIL_NO_TRANSPORT_AVAILABLE',
            $providerCode === 406,
            str_contains($normalized, 'inactive recipients'),
            str_contains($normalized, 'marked as inactive') => 'MAIL_RECIPIENT_SUPPRESSED',
            $providerCode === 10,
            str_contains($normalized, 'authentication failed'),
            str_contains($normalized, 'failed to authenticate'),
            str_contains($normalized, 'invalid credentials') => 'MAIL_AUTH_FAILED',
            default => 'MAIL_TRANSPORT_ERROR',
        };

        $this->logger->error('Email delivery failed', array_merge($context, [
            'code' => $code,
            'mailer' => $this->config->get('mail.default'),
            'error' => mb_strimwidth($message, 0, 500, '...'),
        ], $providerCode !== null ? ['provider_code' => $providerCode] : []));

        $this->reported[$exception] = true;
    }
}
