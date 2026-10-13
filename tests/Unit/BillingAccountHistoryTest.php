<?php

namespace Tests\Unit;

use App\Events\StripeWebhookReceived;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Carbon;
use Mockery;
use Modules\Billing\Listeners\MirrorStripeBillingEvent;
use Modules\Billing\Models\BillingInvoice;
use Modules\Billing\Models\BillingPayment;
use Modules\Billing\Services\BillingAccountHistoryService;
use Modules\Billing\Services\BillingAccountService;
use Modules\Billing\Services\StripeBillingService;
use Tests\TestCase;

/**
 * Customer billing page (Billing module): how Stripe invoices and charges map
 * to the local copies, and the status rules the page shows.
 */
class BillingAccountHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(BillingAccountHistoryService::class)) {
            $this->markTestSkipped('The Billing module is not installed.');
        }
    }

    public function test_invoice_attributes_map_a_finalized_stripe_invoice(): void
    {
        $attributes = $this->history()->invoiceAttributes([
            'id' => 'in_123',
            'customer' => 'cus_123',
            'parent' => ['subscription_details' => ['subscription' => 'sub_123']],
            'number' => 'ABC-0001',
            'status' => 'open',
            'collection_method' => 'send_invoice',
            'currency' => 'USD',
            'subtotal' => 14000,
            'total' => 15000,
            'amount_due' => 15000,
            'amount_paid' => 0,
            'amount_remaining' => 15000,
            'effective_at' => 1790000000,
            'created' => 1789990000,
            'due_date' => 1792592000,
            'status_transitions' => ['finalized_at' => 1790000000, 'paid_at' => null, 'voided_at' => null],
            'attempt_count' => 0,
            'next_payment_attempt' => null,
            'livemode' => false,
        ]);

        $this->assertSame('cus_123', $attributes['provider_customer_id']);
        $this->assertSame('sub_123', $attributes['provider_subscription_id']);
        $this->assertSame('ABC-0001', $attributes['number']);
        $this->assertSame('usd', $attributes['currency']);
        $this->assertSame(15000, $attributes['total_cents']);
        $this->assertSame(15000, $attributes['amount_remaining_cents']);
        $this->assertSame(1790000000, $attributes['invoice_date']->getTimestamp());
        $this->assertSame(1792592000, $attributes['due_date']->getTimestamp());
        $this->assertNull($attributes['paid_at']);
        $this->assertNull($attributes['next_payment_attempt_at']);
        $this->assertFalse($attributes['livemode']);
    }

    public function test_invoice_date_falls_back_to_finalized_then_created(): void
    {
        $history = $this->history();

        $this->assertSame(200, $history->invoiceAttributes([
            'created' => 100,
            'status_transitions' => ['finalized_at' => 200],
        ])['invoice_date']->getTimestamp());

        $this->assertSame(100, $history->invoiceAttributes(['created' => 100])['invoice_date']->getTimestamp());
    }

    public function test_charge_attributes_read_card_and_bank_details(): void
    {
        $card = $this->history()->chargeAttributes([
            'id' => 'ch_1',
            'customer' => 'cus_1',
            'payment_intent' => ['id' => 'pi_1'],
            'status' => 'succeeded',
            'amount' => 15000,
            'amount_refunded' => 5000,
            'currency' => 'usd',
            'created' => 1790000000,
            'payment_method_details' => ['type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242']],
        ]);

        $this->assertSame('pi_1', $card['provider_payment_intent_id']);
        $this->assertSame('card', $card['payment_method_type']);
        $this->assertSame('visa', $card['payment_method_brand']);
        $this->assertSame('4242', $card['payment_method_last4']);
        $this->assertSame(5000, $card['amount_refunded_cents']);
        // Charges don't name their invoice; only invoice linking sets it.
        $this->assertArrayNotHasKey('provider_invoice_id', $card);

        $bank = $this->history()->chargeAttributes([
            'id' => 'ch_2',
            'customer' => 'cus_1',
            'status' => 'failed',
            'failure_message' => 'Insufficient funds.',
            'payment_method_details' => [
                'type' => 'us_bank_account',
                'us_bank_account' => ['bank_name' => 'STRIPE TEST BANK', 'last4' => '6789'],
            ],
        ]);

        $this->assertSame('STRIPE TEST BANK', $bank['payment_method_brand']);
        $this->assertSame('6789', $bank['payment_method_last4']);
        $this->assertSame('Insufficient funds.', $bank['failure_message']);
        $this->assertNull($bank['provider_payment_intent_id']);
    }

    public function test_past_due_rules(): void
    {
        $now = Carbon::parse('2026-10-06 12:00:00', 'UTC');
        $open = ['status' => 'open', 'amount_remaining_cents' => 100];

        $overdue = new BillingInvoice($open + ['collection_method' => 'send_invoice', 'due_date' => '2026-10-01 00:00:00']);
        $this->assertTrue($overdue->isPastDue($now));
        $this->assertSame('past_due', $overdue->displayStatus($now));

        $notYetDue = new BillingInvoice($open + ['collection_method' => 'send_invoice', 'due_date' => '2026-10-29 00:00:00']);
        $this->assertFalse($notYetDue->isPastDue($now));
        $this->assertSame('open', $notYetDue->displayStatus($now));

        // Automatically charged invoices have no due date; a failed attempt makes them past due.
        $failedCharge = new BillingInvoice($open + ['collection_method' => 'charge_automatically', 'attempt_count' => 1]);
        $this->assertTrue($failedCharge->isPastDue($now));

        $notCharged = new BillingInvoice($open + ['collection_method' => 'charge_automatically', 'attempt_count' => 0]);
        $this->assertFalse($notCharged->isPastDue($now));

        $paid = new BillingInvoice(['status' => 'paid', 'amount_remaining_cents' => 0, 'due_date' => '2026-10-01 00:00:00']);
        $this->assertSame('paid', $paid->displayStatus($now));
    }

    public function test_payment_display_status_reflects_refunds(): void
    {
        $payment = fn (string $status, int $refunded) => new BillingPayment([
            'status' => $status,
            'amount_cents' => 100,
            'amount_refunded_cents' => $refunded,
        ]);

        $this->assertSame('succeeded', $payment('succeeded', 0)->displayStatus());
        $this->assertSame('partially_refunded', $payment('succeeded', 40)->displayStatus());
        $this->assertSame('refunded', $payment('succeeded', 100)->displayStatus());
        $this->assertSame('failed', $payment('failed', 0)->displayStatus());
    }

    public function test_money_formats_dollars_and_other_currencies(): void
    {
        $this->assertSame('$1,234.56', BillingAccountService::money(123456, 'usd'));
        $this->assertSame('-$5.00', BillingAccountService::money(-500, 'USD'));
        $this->assertSame('$0.00', BillingAccountService::money(0, null));
        $this->assertSame('12.50 EUR', BillingAccountService::money(1250, 'eur'));
        // Zero-decimal currencies are already in whole units.
        $this->assertSame('1,500 JPY', BillingAccountService::money(1500, 'jpy'));
    }

    public function test_drafts_and_other_mode_events_never_call_stripe(): void
    {
        $stripe = Mockery::mock(StripeBillingService::class);
        $stripe->shouldReceive('isSandbox')->andReturn(true);
        $stripe->shouldNotReceive('client');

        $history = new BillingAccountHistoryService($stripe);

        $history->handleEvent(['type' => 'invoice.paid', 'livemode' => true, 'data' => ['object' => ['id' => 'in_1', 'status' => 'paid']]]);
        $history->handleEvent(['type' => 'invoice.updated', 'livemode' => false, 'data' => ['object' => ['id' => 'in_2', 'status' => 'draft']]]);
        $history->handleEvent(['type' => 'charge.dispute.created', 'livemode' => false, 'data' => ['object' => ['id' => 'dp_1', 'object' => 'dispute']]]);

        // Mockery verifies on tear down that no Stripe client was requested.
        $this->addToAssertionCount(1);
    }

    public function test_queued_webhook_listener_runs_with_only_the_event(): void
    {
        $history = Mockery::mock(BillingAccountHistoryService::class);
        $history->shouldReceive('handleEvent')->once()->with(['type' => 'invoice.paid']);
        $this->app->instance(BillingAccountHistoryService::class, $history);

        // Same path as a queue worker: the container builds the listener and
        // handle() receives only the event, so services must come in through
        // the constructor.
        $queued = new CallQueuedListener(
            MirrorStripeBillingEvent::class,
            'handle',
            [new StripeWebhookReceived(['type' => 'invoice.paid'])]
        );
        $queued->setJob(Mockery::mock(Job::class));
        $queued->handle($this->app);

        $this->addToAssertionCount(1);
    }

    protected function history(): BillingAccountHistoryService
    {
        return new BillingAccountHistoryService(Mockery::mock(StripeBillingService::class));
    }
}
