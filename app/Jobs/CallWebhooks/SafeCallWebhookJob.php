<?php

namespace App\Jobs\CallWebhooks;

use App\Services\CallWebhooks\PublicWebhookUrlValidator;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\TransferStats;
use InvalidArgumentException;
use Spatie\WebhookServer\CallWebhookJob;

class SafeCallWebhookJob extends CallWebhookJob
{
    protected bool $unsafeDestination = false;

    protected function createRequest(array $body): Response
    {
        try {
            $resolvedAddress = app(PublicWebhookUrlValidator::class)
                ->validateAndResolve((string) $this->webhookUrl);
        } catch (InvalidArgumentException $exception) {
            $this->unsafeDestination = true;
            $this->errorType = InvalidArgumentException::class;
            $this->errorMessage = $exception->getMessage();
            throw $exception;
        }

        $parts = parse_url((string) $this->webhookUrl);
        $host = trim((string) ($parts['host'] ?? ''), '[]');
        $port = (int) ($parts['port'] ?? 443);
        $pinnedAddress = str_contains($resolvedAddress, ':')
            ? "[{$resolvedAddress}]"
            : $resolvedAddress;

        $options = [
            'timeout' => $this->requestTimeout,
            'verify' => $this->verifySsl,
            'headers' => $this->headers,
            'allow_redirects' => false,
            'on_stats' => function (TransferStats $stats) {
                $this->transferStats = $stats;
            },
        ];

        if (! defined('CURLOPT_RESOLVE')) {
            $this->unsafeDestination = true;
            $this->errorType = InvalidArgumentException::class;
            $this->errorMessage = 'Secure webhook address pinning is unavailable.';
            throw new InvalidArgumentException($this->errorMessage);
        }

        $options['curl'] = [
            CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinnedAddress}"],
        ];

        return $this->getClient()->request($this->httpVerb, $this->webhookUrl, array_merge(
            $options,
            $body,
            is_null($this->proxy) ? [] : ['proxy' => $this->proxy],
            is_null($this->cert) ? [] : ['cert' => [$this->cert, $this->certPassphrase]],
            is_null($this->sslKey) ? [] : ['ssl_key' => [$this->sslKey, $this->sslKeyPassphrase]],
        ));
    }

    protected function shouldBeRemovedFromQueue(): bool
    {
        return $this->unsafeDestination;
    }
}
