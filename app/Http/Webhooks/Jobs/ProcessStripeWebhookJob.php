<?php

namespace App\Http\Webhooks\Jobs;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\BillingProduct;
use Illuminate\Support\Carbon;
use App\Services\CeretaxService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessStripeWebhookJob extends SpatieProcessWebhookJob
{
    private $stripe_api_key;
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 30;

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    private $acceptedTypes = [
        'invoice.created',
        'invoice.updated',
        'product.created',
        'product.deleted',
        'product.updated',
    ];


    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */


    public function __construct(WebhookCall $webhookCall)
    {
        $this->queue = 'stripe';
        $this->webhookCall = $webhookCall;
    }

    public function handle()
    {
        // $this->webhookCall // contains an instance of `WebhookCall`

        // Allow only 2 tasks every 1 second
        Redis::throttle('stripe')->allow(2)->every(1)->then(function () {

            try {
                $payload = $this->webhookCall->payload;

                // logger($payload);

                $type = $payload['type'] ?? null;
                if (!in_array($type, $this->acceptedTypes)) return;

                // figure out mode from settings (fallback to Stripe event's livemode if needed)
                $slug = 'stripe';

                $sandboxSetting = gateway_setting($slug, 'sandbox'); // e.g. "true"/"false" or bool
                $useSandbox = $sandboxSetting !== null
                    ? filter_var($sandboxSetting, FILTER_VALIDATE_BOOLEAN)
                    : (isset($payload['livemode']) ? !$payload['livemode'] : false); // fallback: Stripe sends livemode=true for prod

                $this->stripe_api_key = gateway_setting($slug, $useSandbox ? 'sandbox_secret_key' : 'live_mode_secret_key');

                if (empty($this->stripe_api_key)) {
                    logger("Stripe webhook: missing API key for mode=" . ($useSandbox ? 'sandbox' : 'live'));
                    return; // or throw
                }

                // logger("[Webhook] Received event: $event", $payload);

                switch ($payload['type']) {
                    case 'invoice.created':
                        $this->processInvoice($payload['data']['object']['id']);
                        break;

                    case 'invoice.updated':
                        if ($this->shouldProcessUpdate($payload)) {
                            $this->processInvoice($payload['data']['object']['id']);
                        }
                        break;

                    case 'product.updated':
                    case 'product.created':
                    case 'product.deleted':
                        $this->handleStripeProductEvent($payload);
                        break;

                    default:
                        Log::warning("[Webhook] Unhandled event: {$payload['type']}");
                }


                return true;
            } catch (\Exception $e) {
                return $this->handleError($e);
            }
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(5);
        });
    }


    protected function shouldProcessUpdate(array $payload): bool
    {
        $req = $payload['request'] ?? null;
        $key = is_array($req) ? ($req['idempotency_key'] ?? null) : null;

        // Idempotency key is null or a valid UUID then process the request
        if (empty($key) || (is_string($key) && Str::isUuid($key))) {
            return true;
        }

        return false;
    }


    protected function processInvoice(string $invoiceId): void
    {
        if (!$invoiceId) return;

        $stripe  = new \Stripe\StripeClient(['api_key' => $this->stripe_api_key]);
        $ceretax = app(\App\Services\CeretaxService::class);

        // Fetch once with the expansions you truly need
        $invoice = $stripe->invoices->retrieve($invoiceId, [
            'expand' => [
                'lines.data',
                'customer',
            ],
        ]);

        // Only work on draft invoices
        if (($invoice->status ?? null) !== 'draft') return;

        // 1) Remove prior tax lines (identified by metadata flag)
        $toDelete = [];
        foreach ($invoice->lines->data as $li) {
            if (($li->metadata['is_telecom_tax'] ?? null) === 'true') {
                $toDelete[] = [
                    'id'       => $li->id,
                    'behavior' => 'delete',
                ];
            }
        }

        if (!empty($toDelete)) {
            // 3) Bulk remove in one API call
            $updated = $stripe->invoices->removeLines(
                $invoice->id,
                ['lines' => $toDelete],
                ['idempotency_key' => "fspbx:" . Str::uuid()]
            );
        }

        // $stripe->invoiceItems->create([
        //     'invoice'     => $invoice->id,
        //     'customer'    => $invoice->customer,
        //     'currency'    => $invoice->currency,
        //     'amount'      => 123, // 1.23 in minor units; change as needed
        //     'description' => 'TEST Ceretax tax (dummy)',
        //     'tax_rates'   => [],  // don’t tax the tax
        //     'metadata'    => [
        //         'is_telecom_tax' => 'true',     // so you can identify/remove it later
        //         'note'            => 'loop-proof test',
        //     ],
        // ], [
        //     'idempotency_key' => "fspbx:" . Str::uuid() ,  
        // ]);


        // 2) Ask Ceretax to create a new transaction
        $result = $ceretax->createTelcoTransaction($invoice, 'Quote');
        $taxes = $this->summarizeTaxesCollapsed($result);

        $components = $result['components'] ?? [];

        // 3) Add itemized tax lines (don’t tax the tax)
        foreach ($components as $c) {
            $amount = (int) ($c['amount_minor'] ?? 0);
            if ($amount <= 0) continue;

            $stripe->invoiceItems->create([
                'invoice'     => $invoice->id,
                'customer'    => $invoice->customer,
                'currency'    => $invoice->currency,
                'amount'      => $amount,
                'description' => $c['label'] ?? 'Telecom Tax',
                'tax_rates'   => [],
                'metadata'    => [
                    'is_telecom_tax' => 'true',
                    'jurisdiction'   => $c['jurisdiction'] ?? null,
                    'tax_code'       => $c['code'] ?? null,
                ],
            ]);
        }

        // 4) Status move in Ceretax — usually do this when you finalize/charge.
        // If you must do it here, consider 'Active' now and 'Posted' at finalization.
        // $ceretax->updateTransactionStatus($invoice->id, 'Active');
    }


    protected function handleStripeProductEvent(array $event): void
    {
        $type = $event['type'] ?? '';
        $obj  = Arr::get($event, 'data.object', []);

        $provider = 'stripe';
        $providerId = $obj['id'] ?? null; // prod_...
        if (!$providerId) return;

        if ($type === 'product.deleted') {
            BillingProduct::where([
                'provider' => $provider,
                'provider_product_id' => $providerId,
            ])->delete(); // soft delete
            return;
        }

        // Convert Stripe epoch seconds to tz timestamps
        $extCreated = isset($obj['created']) ? Carbon::createFromTimestampUTC($obj['created']) : null;
        $extUpdated = isset($obj['updated']) ? Carbon::createFromTimestampUTC($obj['updated']) : null;

        BillingProduct::updateOrCreate(
            [
                'provider'            => $provider,
                'provider_product_id' => $providerId,
            ],
            [
                // generate/stabilize a UUID id for our table if row is new
                'uuid'                  => Str::uuid()->toString(),
                'default_price_ref'   => $obj['default_price'] ?? null,
                'livemode'            => (bool) ($obj['livemode'] ?? $event['livemode'] ?? false),
                'active'              => (bool) ($obj['active'] ?? true),
                'name'                => $obj['name'] ?? null,
                'description'         => $obj['description'] ?? null,
                'type'                => $obj['type'] ?? null,
                'statement_descriptor' => $obj['statement_descriptor'] ?? null,
                'unit_label'          => $obj['unit_label'] ?? null,
                'url'                 => $obj['url'] ?? null,
                'metadata'            => $obj['metadata'] ?? [],
                'images'              => $obj['images'] ?? [],
                'marketing_features'  => $obj['marketing_features'] ?? [],
                'package_dimensions'  => $obj['package_dimensions'] ?? null,
                'shippable'           => array_key_exists('shippable', $obj) ? (is_null($obj['shippable']) ? null : (bool)$obj['shippable']) : null,
                'external_created_at' => $extCreated,
                'external_updated_at' => $extUpdated,
                'deleted_at'          => null, // undelete on update
            ]
        );
    }


    /**
     * Summarize taxes from a CereTax response.
     * - Collapses all Sales Tax (taxType=130) into one line.
     * - Other taxes remain separate by (taxType + description).
     *
     * @return array<int, array{taxType:string, description:string, taxTypeDesc:?string, amount:float, amount_cents:int}>
     */
    function summarizeTaxesCollapsed(array $result): array
    {
        // 1) Flatten all taxes out of lineItems
        $rows = collect(data_get($result, 'invoice.lineItems', []))
            ->flatMap(function ($li) {
                $taxes = data_get($li, 'taxes', []);
                return collect($taxes)->map(function ($tax) {
                    $totalTax = data_get($tax, 'totalTax');
                    $amount   = is_numeric($totalTax)
                        ? (float) $totalTax
                        : ((float) data_get($tax, 'tax', 0) + (float) data_get($tax, 'taxOnTax', 0));
                    $cents = (int) round($amount * 100);

                    return [
                        'taxType'      => (string) data_get($tax, 'taxType'),
                        'description'  => (string) data_get($tax, 'description'),
                        'taxTypeDesc'  => data_get($tax, 'taxTypeDesc'),
                        'taxLevel'     => data_get($tax, 'taxLevel'),
                        'taxLevelDesc' => data_get($tax, 'taxLevelDesc'),
                        'amount_cents' => $cents,
                    ];
                });
            });

        // 2) Grouping:
        //    - All 130 (sales tax) collapsed together.
        //    - Everything else grouped by (taxType + description).
        $grouped = $rows->groupBy(function ($r) {
            return ($r['taxType'] === '130')
                ? '130' // one bucket for all sales tax
                : $r['taxType'] . '|' . $r['description'];
        });

        // 3) Sum and format
        $combined = $grouped->map(function ($group, $key) {
            $first   = $group->first();
            $sumCents = (int) $group->sum('amount_cents');

            $desc = ($first['taxType'] === '130')
                ? 'SALES TAX'
                : $first['description'];

            return [
                'taxType'      => $first['taxType'],
                'description'  => $desc,
                'taxTypeDesc'  => $first['taxTypeDesc'],
                'amount'       => round($sumCents / 100, 2),
                'amount_cents' => $sumCents,
            ];
        })
            // Optional: keep a stable order (sales tax first, then others by taxType)
            ->sortBy(fn($row) => $row['taxType'] === '130' ? '000' : $row['taxType'])
            ->values()
            ->all();

        return $combined;
    }

    private function toCents($totalTax, $tax, $taxOnTax): int
    {
        // Prefer totalTax if present; otherwise tax + taxOnTax
        $val = is_numeric($totalTax)
            ? (float) $totalTax
            : ((float) ($tax ?? 0) + (float) ($taxOnTax ?? 0));

        // Convert dollars -> integer cents (avoid fp drift)
        return (int) round($val * 100);
    }



    private function handleError(\Exception $e)
    {

        logger('ProcessStripeWebhookJob error: '
            . $e->getMessage()
            . " at " . $e->getFile() . ":" . $e->getLine());

        return response()->json(['error' => $e->getMessage()], 400);
    }
}
