<?php

return [
    'queue' => 'webhooks',
    'connection' => 'redis',
    'http_verb' => 'post',
    'proxy' => null,
    'signer' => \Spatie\WebhookServer\Signer\DefaultSigner::class,
    'signature_header_name' => 'Signature',
    'timestamp_header_name' => 'Timestamp',
    'headers' => [
        'Content-Type' => 'application/json',
        'User-Agent' => 'FS-PBX-Call-Webhooks/1.0',
    ],
    'timeout_in_seconds' => 3,
    'tries' => 3,
    'backoff_strategy' => \Spatie\WebhookServer\BackoffStrategy\ExponentialBackoffStrategy::class,
    'webhook_job' => \App\Jobs\CallWebhooks\SafeCallWebhookJob::class,
    'verify_ssl' => true,
    'throw_exception_on_failure' => false,
    'tags' => ['call-webhook'],
];
