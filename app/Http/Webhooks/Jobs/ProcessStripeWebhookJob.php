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
        'invoice.deleted',
        'invoice.voided',
        'invoice.marked_uncollectible',
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
                        $this->processInvoice($payload['data']['object']);
                        break;

                    case 'invoice.updated':
                        if ($this->shouldProcessUpdate($payload)) {
                            $this->processInvoice($payload['data']['object']);
                        }
                        break;

                    case 'invoice.marked_uncollectible':
                    case 'invoice.deleted':
                    case 'invoice.voided':
                        $this->handleDeleteInvoice($payload['data']['object']);
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
            return $this->release(15);
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


    protected function processInvoice($invoice): void
    {
        $stripe  = new \Stripe\StripeClient(['api_key' => $this->stripe_api_key]);
        $ceretax = app(\App\Services\CeretaxService::class);

        // Only work on draft invoices
        if ((data_get($invoice, 'status') ?? null) !== 'draft') return;

        // Fetch once with the expansions you truly need
        $stripeInvoice = $stripe->invoices->retrieve(data_get($invoice, 'id'), [
            'expand' => [
                'customer',
            ],
        ]);

        $lines = $this->fetchAllInvoiceLines(data_get($invoice, 'id'));

        $this->applyProductMetadataToInvoiceLines($stripe, (string) data_get($invoice, 'id'), $lines);

        // Separate lines: tax vs non-tax
        $taxLines = [];
        $nonTaxLines = [];
        foreach ($lines as $li) {
            $isTax = (Arr::get($li, 'metadata.is_telecom_tax') === 'true')
                || (Arr::get($li, 'metadata.ceretax_generated') === 'true');
            if ($isTax) {
                $taxLines[] = $li;
            } else {
                $nonTaxLines[] = $li;
            }
        }

        // Early exit: nothing to tax
        if (count($nonTaxLines) === 0) {
            return;
        }

        // 1) Bulk remove prior tax lines (if any)
        if (!empty($taxLines)) {
            $toDelete = array_map(fn($li) => [
                'id'       => (string) data_get($li, 'id'),
                'behavior' => 'delete',
            ], $taxLines);

            $stripe->invoices->removeLines(
                (string) data_get($invoice, 'id'),
                ['lines' => $toDelete],
                ['idempotency_key' => 'fspbx:bulkdel:' . (string) Str::uuid()]
            );
        }


        // 2) Ask Ceretax to create a new transaction
        $result = $ceretax->createTelcoTransaction($stripeInvoice, $lines);
        $taxes = $this->summarizeTaxesCollapsed($result);

        $toAdd = [];
        foreach ($taxes as $idx => $tax) {
            $amountCents = (int) ($tax['amount_cents'] ?? 0);
            if ($amountCents <= 0) continue;

            $toAdd[] = [
                'amount'      => $amountCents,                   // integer cents
                'description' => $tax['description'],
                'tax_rates'   => [],                             // don't tax the tax
                'metadata'    => [
                    'is_telecom_tax' => 'true',              // mark as ours 
                    'ceretax_tax_type'  => (string)($tax['taxType'] ?? ''),
                    'ceretax_tax_desc'  => $tax['taxTypeDesc'] ?? '',
                ],
            ];
        }

        if (!empty($toAdd)) {
            // One idempotent call to add all taxes as lines
            $idk = 'fspbx:add:' . Str::uuid();
            $updated = $stripe->invoices->addLines(
                data_get($invoice, 'id'),
                [
                    'lines' => $toAdd,
                    // merge/overwrite invoice metadata in the same call:
                    'invoice_metadata' => [
                        'ceretax_status'   => $result['status']['currentStatus'],
                        'ceretax_ksuid' => $result['ksuid'] ?? null,
                    ],
                ],
                ['idempotency_key' => $idk]
            );
        }

        $stripe->invoices->update(
            data_get($invoice, 'id'),
            [
                'rendering' => [
                    'template' => ''   // empty string unsets the template
                ],
            ],
            ['idempotency_key' => 'fspbx:' . (string) Str::uuid()]
        );

        $stripe->invoices->update(
            data_get($invoice, 'id'),
            [
                'rendering' => [
                    'template' => data_get($invoice, 'rendering.template')
                ],
                'collection_method' => data_get($stripeInvoice, 'customer.invoice_settings.default_payment_method') ? 'charge_automatically' : 'send_invoice',
                'days_until_due' => data_get($stripeInvoice, 'customer.invoice_settings.default_payment_method') ? 0 : 14,
            ],
            ['idempotency_key' => 'fspbx:' . (string) Str::uuid()]
        );


        logger('taxes added');

        // 4) Status move in Ceretax — usually do this when you finalize/charge.
        // If you must do it here, consider 'Active' now and 'Posted' at finalization.
        // $ceretax->updateTransactionStatus($invoice->id, 'Active');
    }

    protected function handleDeleteInvoice($stripeInvoice): void
    {
        $ceretax = app(\App\Services\CeretaxService::class);

        $existingKsuid = data_get($stripeInvoice, 'metadata.ceretax_ksuid');
        if (!$existingKsuid) return;

        $result = $ceretax->suspendExistingFromInvoiceMetadata($stripeInvoice);
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

    /**
     * Page through all invoice lines (limit 100 per page).
     * @return array<\Stripe\InvoiceLineItem>
     */
    protected function fetchAllInvoiceLines($invoiceId)
    {
        $stripe  = new \Stripe\StripeClient(['api_key' => $this->stripe_api_key]);
        $params = ['limit' => 100];

        $lines = [];
        foreach ($stripe->invoices->allLines($invoiceId, $params)->autoPagingIterator() as $li) {
            // Convert Stripe\InvoiceLineItem to a plain array for easy Arr::get(), logging, etc.
            $lines[] = $li->toArray();
        }

        return $lines;
    }

    /**
     * For each NON-tax invoice line:
     *   - read Stripe product id from the line
     *   - load local BillingProduct by provider_product_id
     *   - copy BillingProduct->metadata (JSONB) onto the line as Stripe metadata
     * Does one or more bulk updateLines calls (invoice must be DRAFT).
     */
    protected function applyProductMetadataToInvoiceLines(\Stripe\StripeClient $stripe, string $invoiceId, array $lines): void
    {
        // 1) Keep only non-tax lines
        $nonTax = array_filter($lines, fn($li) => (Arr::get($li, 'metadata.is_telecom_tax') ?? 'false') !== 'true');
        if (!$nonTax) return;

        // 2) Collect Stripe product IDs from line shapes you showed
        $productIds = [];
        foreach ($nonTax as $li) {
            $pid = Arr::get($li, 'pricing.price_details.product') ?? null;
            if ($pid) $productIds[] = (string) $pid;
        }
        $productIds = array_values(array_unique(array_filter($productIds)));
        if (!$productIds) return;

        // 3) Load local products keyed by Stripe product id (provider_product_id)
        $catalog = BillingProduct::query()
            ->where('provider', 'stripe')
            ->whereIn('provider_product_id', $productIds)
            ->get()
            ->keyBy('provider_product_id');

        // 4) Build bulk line updates
        $updates = [];
        foreach ($nonTax as $li) {
            $lineId = (string) Arr::get($li, 'id');
            $pid = Arr::get($li, 'pricing.price_details.product')
                ?? Arr::get($li, 'price.product')
                ?? Arr::get($li, 'plan.product');

            if (!$lineId || !$pid) continue;

            $bp = $catalog->get($pid);
            if (!$bp) continue;

            // Start with JSONB metadata (must be string=>string for Stripe)
            $meta = $this->toStripeMetadata($bp->metadata ?? []);

            if ($meta) {
                $updates[] = [
                    'id'       => $lineId,
                    'metadata' => $meta,
                ];
            }
        }

        if (!$updates) return;

        // 5) Single bulk call (idempotent)
        $stripe->invoices->updateLines(
            $invoiceId,
            ['lines' => $updates],
            ['idempotency_key' => 'fspbx:' . (string) Str::uuid()]
        );
    }

    /**
     * Convert arbitrary PHP array (from JSONB) into Stripe-safe metadata:
     * - values must be strings
     * - nested arrays/objects are JSON-encoded
     * - nulls dropped, booleans cast to 'true'/'false'
     */
    protected function toStripeMetadata(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = (string) $k;
            if ($v === null) {
                continue;
            } elseif (is_bool($v)) {
                $out[$key] = $v ? 'true' : 'false';
            } elseif (is_scalar($v)) {
                $out[$key] = (string) $v;
            } else {
                // arrays/objects -> JSON
                $out[$key] = json_encode($v, JSON_UNESCAPED_SLASHES);
            }
        }
        return $out;
    }
}
