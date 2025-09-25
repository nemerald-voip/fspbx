<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use App\Models\BillingProduct;
use Illuminate\Support\Carbon;
use App\Models\CeretaxTransaction;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\RequestException;

class CeretaxService
{
    protected Client $http;
    protected string $baseUrl;
    protected string $apiKey;
    protected string $clientProfileId;
    protected string $status;

    public function __construct(?Client $client = null)
    {
        $cfg    = config('services.ceretax');
        $isSandbox   = ($cfg['env'] ?? 'sandbox') === 'sandbox';

        $this->baseUrl         = rtrim($isSandbox ? $cfg['sandbox_url'] : $cfg['prod_url'], '/') . '/';
        $this->apiKey          = $isSandbox ? ($cfg['sandbox_api_key'] ?? '') : ($cfg['prod_api_key'] ?? '');
        $this->clientProfileId = $isSandbox ? ($cfg['sandbox_client_profile_id'] ?? 'default')
            : ($cfg['prod_client_profile_id'] ?? 'default');
        $this->status          =  $isSandbox ? 'Quote' : 'Active';
    }

    protected function base(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout(60)
            ->withHeaders($this->headers());
    }

    /**
     * Create a Telecommunications transaction in CereTax.
     *
     * $stripeInvoice is the expanded Stripe invoice object you fetched in your webhook/job.
     * $status should be 'Quote'|'Active'|'Posted' (start with 'Quote' for estimates, 'Active' before finalize, then 'Posted').
     *
     * Returns the decoded CereTax response (includes KSUID, tax breakdown by line, etc.).
     */
    public function createTelcoTransaction($stripeInvoice, $lines): array
    {
        // logger($stripeInvoice);
        // logger($lines);
        //override variables based on invoice if needed
        if (data_get($stripeInvoice, 'customer.metadata.ceretax_sandbox') == 'true') {
            $this->baseUrl = config('services.ceretax.sandbox_url', "https://calc.cert.ceretax.net/");
            $this->apiKey = config('services.ceretax.sandbox_api_key', '');
            $this->clientProfileId = config('services.ceretax.sandbox_client_profile_id', 'default');
            $this->status = "Quote";
        }

        // Preflight: suspend any existing tx tied to this invoice is status is not Quote
        if (data_get($stripeInvoice,'metadata.ceretax_status' != 'Quote')) {
            $this->suspendExistingFromInvoiceMetadata($stripeInvoice);
        }
        

        $payload       = $this->buildTelcoPayloadFromStripe($stripeInvoice, $lines);
        $invoiceNumber = (string) Arr::get($payload, 'invoice.invoiceNumber');
        $env           = config('services.ceretax.env', 'sandbox');

        // Persist request upfront so nothing gets lost
        $row = CeretaxTransaction::create([
            'invoice_number' => $invoiceNumber,
            'status'         => $this->status,
            'request_json'   => $payload,   // full payload
            'env'            => $env,
        ]);

        // Call CereTax (do not ->throw(); we want full body even on failure)
        $resp = $this->base()->post('telco', $payload);

        // Decode JSON if present (may be null on some errors)
        $json = null;
        try {
            $json = $resp->json();
        } catch (\Throwable $e) { /* ignore */
        }

        // Extract helpful bits
        $ksuid = $json['ksuid'] ?? null;
        $stan  = $json['systemTraceAuditNumber'] ?? null;

        // Small, human summary of any errorMessages (top/invoice/line)
        $summary = $this->compactCeretaxErrors($json);

        // Update the DB row with response
        $row->update([
            'http_status'   => $resp->status(),
            'response_json' => $json ?: ['raw' => $resp->body()],
            'ksuid'         => $ksuid,
            'stan'          => $stan,
            'error_summary' => $summary,
        ]);

        // Log a concise success or detailed error
        if ($resp->successful()) {
            logger('CereTax success', [
                'invoiceNumber'     => $invoiceNumber,
                'ksuid'             => $ksuid,
                'totalTaxInvoice'   => Arr::get($json, 'invoice.totalTaxInvoice'),
            ]);
            return $json ?? [];
        }

        // Rich error log (keeps raw body + parsed messages)
        $this->logCeretaxError($resp, $payload);

        // Bubble a simple exception; details live in logs/DB
        throw new \RuntimeException("CereTax telco call failed with HTTP {$resp->status()}");
    }

    /**
     * Turn CereTax error arrays into a short string for quick scanning.
     */
    protected function compactCeretaxErrors(?array $json): ?string
    {
        if (!$json) return null;

        $bits = [];

        foreach ($json['errorMessages'] ?? [] as $e) {
            $bits[] = "[{$e['code']}:{$e['type']}] {$e['message']}";
        }
        foreach (($json['invoice']['errorMessages'] ?? []) as $e) {
            $bits[] = "[invoice {$e['code']}:{$e['type']}] {$e['message']}";
        }
        foreach (($json['invoice']['lineItems'] ?? []) as $li) {
            foreach (($li['errorMessages'] ?? []) as $e) {
                $line = $li['lineId'] ?? '?';
                $bits[] = "[line {$line} {$e['code']}:{$e['type']}] {$e['message']}";
            }
        }

        return $bits ? implode(' | ', $bits) : null;
    }

    /**
     * Structured error logging with raw body preserved.
     */
    protected function logCeretaxError(Response $resp, array $payload): void
    {
        $json   = $resp->json();
        $raw    = $resp->body();

        $topErrors = $json['errorMessages'] ?? [];
        $invErrors = $json['invoice']['errorMessages'] ?? [];
        $lineErrs  = [];
        foreach ($json['invoice']['lineItems'] ?? [] as $li) {
            if (!empty($li['errorMessages'])) {
                $lineErrs[] = [
                    'lineId'     => $li['lineId'] ?? null,
                    'itemNumber' => $li['itemNumber'] ?? null,
                    'desc'       => $li['itemDescription'] ?? null,
                    'errors'     => $li['errorMessages'],
                ];
            }
        }

        logger('CereTax telco error', [
            'http_status'  => $resp->status(),
            'invoiceNumber' => Arr::get($payload, 'invoice.invoiceNumber'),
            'ksuid'        => $json['ksuid'] ?? null,
            'stan'         => $json['systemTraceAuditNumber'] ?? null,
            'top_errors'   => $topErrors,
            'invoice_errors' => $invErrors,
            'line_errors'  => $lineErrs,
            'raw_body'     => $raw,
        ]);
    }


    public function updateTransactionStatusByKsuid(
        string $ksuid,
        string $toStatus,
        ?string $invoiceNumber = null,
        ?string $systemTraceAuditNumber = null
    ): array {
        // Build request body exactly per docs
        $payload = array_filter([
            'ksuid'                  => $ksuid,               // required
            'systemTraceAuditNumber' => $systemTraceAuditNumber, // include only if you used STAN
            'transactionStatus'      => $toStatus,            // required: Active|Posted|Suspended
        ], fn($v) => $v !== null && $v !== '');

        // Create a new row to record this status update attempt
        $row = CeretaxTransaction::create([
            'invoice_number' => (string)($invoiceNumber ?? ''),
            'status'         => $toStatus,
            'ksuid'          => $ksuid,
            'request_json'   => $payload,
            'env'            => config('services.ceretax.env', 'sandbox'),
        ]);

        // Call the API (no ->throw so we can capture full body on 4xx/404)
        $resp = $this->base()->post('status', $payload);

        // Parse JSON if available
        $json = null;
        try {
            $json = $resp->json();
        } catch (\Throwable $e) {
        }

        // Compact any error messages (if your helper exists)
        $summary = method_exists($this, 'compactCeretaxErrors')
            ? $this->compactCeretaxErrors($json)
            : null;

        // Update the row with response info
        $row->update([
            'http_status'   => $resp->status(),
            'response_json' => $json ?: ['raw' => $resp->body()],
            'error_summary' => $summary,
        ]);

        if ($resp->successful()) {
            return $json ?? [];
        }

        // Log + throw concise error; details are in DB
        logger()->warning('CereTax status update failed', [
            'ksuid'       => $ksuid,
            'to_status'   => $toStatus,
            'http_status' => $resp->status(),
            'body'        => $resp->body(),
        ]);

        throw new \RuntimeException("CereTax status update failed (HTTP {$resp->status()})");
    }

    /**
     * Reverse a Posted transaction (e.g., refund/cancel scenario).
     * $reversalType is defined by CereTax (e.g., 'Posting Reversal' semantics).
     */
    public function reverseTransaction(string $invoiceNumber, string $reversalType = 'Posting Reversal'): array
    {
        $data = [
            'invoiceNumber' => $invoiceNumber,
            'reversalType'  => $reversalType,
        ];

        try {
            $resp = $this->http->post('reverse', [
                'headers' => $this->headers(),
                'json'    => $data,
            ]);
            return json_decode((string) $resp->getBody(), true) ?: [];
        } catch (RequestException $e) {
            $body = (string) optional($e->getResponse())->getBody();
            logger()->error('CereTax reverse failed', ['err' => $e->getMessage(), 'body' => $body, 'data' => $data]);
            throw $e;
        }
    }

    /**
     * Optional helper: query PS Codes to map SKUs -> PS Codes in your admin UI.
     * Example: $filter = 'psCategoryDescription eq Services'
     */
    public function listPsCodes(?string $filter = null, int $top = 50, int $skip = 0): array
    {
        $client = new Client(['base_uri' => 'https://data.cert.ceretax.net/', 'timeout' => 15]);

        $query = array_filter([
            '$filter' => $filter,
            '$top'    => $top,
            '$skip'   => $skip,
        ]);

        try {
            $resp = $client->get('psCodes', [
                'headers' => $this->headers(),
                'query'   => $query,
            ]);
            return json_decode((string) $resp->getBody(), true) ?: [];
        } catch (RequestException $e) {
            $body = (string) optional($e->getResponse())->getBody();
            logger()->error('CereTax psCodes failed', ['err' => $e->getMessage(), 'body' => $body, 'query' => $query]);
            throw $e;
        }
    }

    /**
     * —— Mapping from Stripe invoice -> CereTax telco payload ——
     */
    protected function buildTelcoPayloadFromStripe($invoice, $lines): array
    {
        // logger($invoice);
        // logger($lines);
        // --- Config knobs (tune or lift from config/services.php) ---
        $cfg     = config('services.ceretax');
        $profile = $cfg['sandbox_client_profile_id'] ?? $cfg['prod_client_profile_id'] ?? 'default';
        $calculationType = 'S'; //S-sales (Default)
        $decimals = 2;
        $unitsType = '03';
        $taxSitusRule = 'S'; // Customer Address             

        // Dates
        // Prefer invoice.period_start; fall back to created; then now()
        $periodStartTs = data_get($invoice, 'period_start')
            ?? data_get($invoice, 'created')
            ?? time();
        $invDate   = Carbon::createFromTimestamp((int) $periodStartTs)->utc();
        $yyyy      = $invDate->format('Y');
        $mm        = $invDate->format('m');
        $isoDate   = $invDate->toDateString(); // YYYY-MM-DD

        // Identifiers
        $invoiceNumber = data_get($invoice, 'id');
        $customerAccount = data_get($invoice, 'customer.id');

        $productIds = collect($lines ?? [])
            ->map(fn($li) => Arr::get($li, 'pricing.price_details.product'))
            ->filter()
            ->unique()
            ->values();

        $productsById = BillingProduct::query()
            ->where('provider', 'stripe')
            ->whereIn('provider_product_id', $productIds)
            ->get(['provider_product_id', 'metadata'])
            ->keyBy('provider_product_id'); // Collection keyed by product id

        // Build non-tax lines in CereTax format
        $lineItems = [];
        $lineId = 1;

        foreach ($lines as $li) {
            // --- SKIP any telecom tax lines you previously added ---
            $isTaxGenerated = Arr::get($li, 'metadata.is_telecom_tax') === 'true';
            if ($isTaxGenerated) {
                continue;
            }

            $productId = Arr::get($li, 'pricing.price_details.product');
            $bp        = $productId ? $productsById->get($productId) : null;
            $psCode    = $bp ? data_get($bp, 'metadata.ceretax_code') : null; // null if missing

            // Amounts: Stripe is in minor units; convert to major
            $amountMinor = (int) (data_get($li, 'amount') ?? 0);
            $amountMajor = round($amountMinor / 100, $decimals);

            $desc       = data_get($li, 'description');
            $qty        = (int) (data_get($li, 'quantity') ?? 1);
            $itemNumber = (string) data_get($li, 'id'); // Stripe line id (il_...) is fine for itemNumber

            $lineItems[] = [
                'revenueIncludesTax' => false,
                'lineTaxes'          => '0',
                'units'              => [
                    'quantity' => $qty,
                    'type'     => $unitsType,
                ],
                'situs' => [
                    'customerAddress' => [
                        'serviceAddress' => [
                            'addressLine1' => data_get($invoice, 'customer_address.line1') ?? null,
                            'addressLine2' => data_get($invoice, 'customer_address.line2') ?? null,
                            'city'         => data_get($invoice, 'customer_address.city') ?? null,
                            'state'        => data_get($invoice, 'customer_address.state') ?? null,
                            'postalCode'   => data_get($invoice, 'customer_address.postal_code') ?? null,
                        ],
                    ],
                    'taxSitusRule' => $taxSitusRule,
                ],
                'lineId'           => (string) $lineId++,
                'itemNumber'       => $itemNumber,
                'itemDescription'  => $desc,
                'dateOfTransaction' => $isoDate,
                'psCode'           => $psCode,
                'revenue'          => $amountMajor,
            ];
        }

        // Assemble final payload 
        return [
            'configuration' => [
                'status'          => $this->status,          // 'Active' | 'Quote' | 'Posted' ...
                'calculationType' => $calculationType,
                'responseOptions' => [
                    'passThroughType' => [
                        'excludeOptionalTaxesInTaxOnTax' => false,
                    ],
                ],
                'contentYear'     => $yyyy,
                'contentMonth'    => $mm,
                'complianceYear'  => $yyyy,
                'complianceMonth' => $mm,
                'decimals'        => $decimals,
                'profileId'       => $profile,
            ],
            'invoice' => [
                // These three come from your onboarding; keep your sample defaults unless told otherwise:
                'businessType'        => '15',
                'customerType'        => '02',
                'sellerType'          => '01',

                'invoiceDate'         => $isoDate,
                'invoiceNumber'       => (string) $invoiceNumber,
                'customerAccount'     => (string) $customerAccount,

                'lineItems'           => $lineItems,
            ],
        ];
    }

    public function suspendExistingFromInvoiceMetadata($stripeInvoice): void
    {
        $existingKsuid = data_get($stripeInvoice, 'metadata.ceretax_ksuid');
        if (!$existingKsuid) return;

        try {
            $this->updateTransactionStatusByKsuid(
                ksuid: $existingKsuid,
                toStatus: 'Suspended',
                invoiceNumber: (string) data_get($stripeInvoice,'id')
            );
            logger()->info('CereTax preflight: suspended prior transaction', [
                'invoice' => (string) data_get($stripeInvoice,'id'),
                'ksuid'   => $existingKsuid,
            ]);
        } catch (\Throwable $e) {
            // Non-blocking: we still proceed to create a fresh transaction
            logger()->warning('CereTax preflight suspend failed', [
                'invoice' => (string) data_get($stripeInvoice,'id'),
                'ksuid'   => $existingKsuid,
                'err'     => $e->getMessage(),
            ]);
        }
    }




    protected function headers(): array
    {
        return [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'x-api-key'     => $this->apiKey,
            'x-profile-id'  => $this->clientProfileId,
        ];
    }
}
