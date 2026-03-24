<?php

namespace App\Jobs;

use App\Services\Messaging\Delivery\InboundMessageEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverMessageToEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $messageUuid,
        public ?string $orgId,
        public string $email,
    ) {}

    public function handle(InboundMessageEmailService $service): void
    {
        $service->deliver(
            messageUuid: $this->messageUuid,
            orgId: $this->orgId,
            email: $this->email,
        );
    }
}