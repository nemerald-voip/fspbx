<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired by ProcessStripeWebhookJob for every accepted Stripe event so optional
 * modules can react without the core job depending on them. The Billing module
 * uses it to keep local copies of invoices and payments.
 */
class StripeWebhookReceived
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload  The Stripe event exactly as received.
     */
    public function __construct(public array $payload)
    {
    }
}
