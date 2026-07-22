<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListClickToDialTargetsRequest;
use App\Http\Requests\Api\V1\StoreClickToDialCallRequest;
use App\Models\Domain;
use App\Services\ClickToDialService;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class ClickToDialController extends Controller
{
    /**
     * Legacy click-to-dial target discovery endpoint.
     *
     * Kept for compatibility with deployed integrations. New clients should
     * retrieve agent values from
     * `GET /api/v1/domains/{domain_uuid}/phone-control/targets`.
     *
     * @hideFromAPIDocumentation
     */
    public function targets(
        ListClickToDialTargetsRequest $request,
        ClickToDialService $clickToDial,
        FreeswitchEslService $eslService,
        string $domain_uuid
    ) {
        $domain = $this->resolveDomain($domain_uuid);
        $validated = $request->validated();

        try {
            $groups = $clickToDial->candidateGroups(
                $eslService,
                (string) $validated['extension'],
                (string) $domain->domain_uuid
            );

            $eslService->disconnect();
        } catch (RuntimeException $exception) {
            $eslService->disconnect();
            throw $this->toApiException($exception);
        } catch (Throwable $exception) {
            $eslService->disconnect();
            throw $exception;
        }

        return response()->json([
            'object' => 'list',
            'url' => "/api/v1/domains/{$domain_uuid}/click-to-dial/targets",
            'has_more' => false,
            'data' => $this->normalizeGroups($groups)->all(),
        ], 200);
    }

    /**
     * Create a click-to-dial call
     *
     * Makes the selected registered phone place a call to the requested
     * destination. If no selector is provided, FS PBX picks the freshest
     * controllable registration group and returns any other matching groups in
     * `skipped_targets`. To select a specific phone, retrieve its `agent` from
     * `GET /api/v1/domains/{domain_uuid}/phone-control/targets` and send it
     * with this request. Use `vendor` only when any phone from that vendor is
     * an acceptable target.
     *
     * Access rules:
     * - Caller must have access to the target domain.
     * - Caller must have the `phone_control_call` permission.
     *
     * @group Phone Control
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @bodyParam extension string required Extension number to control. Example: 1001
     * @bodyParam destination string required Destination number or dial string to call. Example: 18005551212
     * @bodyParam agent string Optional. Preferred selector for a specific phone. Retrieve the `agent` value from `GET /api/v1/domains/{domain_uuid}/phone-control/targets`; plain text matching is case-insensitive. Example: SIP-T53W
     * @bodyParam vendor string Optional. Broader selector for any matching phone from this vendor. Valid values: poly, polycom, yealink, grandstream, snom, ringotel, or generic. Example: yealink
     * @bodyParam event string Optional. Force NOTIFY transport and override the SIP Event header. Example: ACTION-URI
     * @bodyParam content_type string Optional. Force NOTIFY transport and override Content-Type. Example: message/sipfrag
     * @bodyParam body string Optional. Force NOTIFY transport and override the message body. Example: number=18005551212&outgoing_uri=sip:1001@example.com
     *
     * @response 200 scenario="Success" {
     *   "object": "click_to_dial_call",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "extension_uuid": "d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b",
     *   "extension": "1001",
     *   "destination": "18005551212",
     *   "sent": true,
     *   "targets": [
     *     {
     *       "vendor": "yealink",
     *       "agent": "Yealink SIP-T53W 96.86.0.45"
     *     }
     *   ],
     *   "skipped_targets": [],
     *   "results": [
     *     {
     *       "sent": true,
     *       "reason": null,
     *       "vendor": "yealink",
     *       "agent": "Yealink SIP-T53W 96.86.0.45"
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid selection" {"error":{"type":"invalid_request_error","message":"No controllable registrations matched the selection.","code":"invalid_request","param":"selection"}}
     * @response 400 scenario="Validation error" {"error":{"type":"invalid_request_error","message":"The destination field is required.","code":"invalid_parameter","param":"destination"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 scenario="Forbidden (domain access)" {"error":{"type":"invalid_request_error","message":"You do not have access to this domain.","code":"forbidden_domain","param":"domain_uuid"}}
     * @response 403 scenario="Forbidden (missing permission)" {"success":false,"message":"Forbidden (missing permission).","error":{"code":"forbidden_permission","permission":"phone_control_call"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Extension not found" {"error":{"type":"invalid_request_error","message":"Extension not found.","code":"resource_missing","param":"extension"}}
     * @response 409 scenario="No controllable registrations" {"error":{"type":"invalid_request_error","message":"No controllable registrations found for 1001@pbx.example.com.","code":"no_controllable_registrations","param":"extension"}}
     * @response 500 scenario="Internal server error" {"error":{"type":"api_error","message":"An unexpected error occurred."}}
     */
    public function store(
        StoreClickToDialCallRequest $request,
        ClickToDialService $clickToDial,
        FreeswitchEslService $eslService,
        string $domain_uuid
    ) {
        $domain = $this->resolveDomain($domain_uuid);
        $validated = $request->validated();

        try {
            $result = $clickToDial->makeCall(
                $eslService,
                (string) $validated['extension'],
                (string) $domain->domain_uuid,
                (string) $validated['destination'],
                [
                    'vendor' => $validated['vendor'] ?? null,
                    'agent' => $validated['agent'] ?? null,
                    'event' => $validated['event'] ?? null,
                    'content_type' => $validated['content_type'] ?? null,
                    'body' => $validated['body'] ?? null,
                ]
            );
        } catch (RuntimeException $exception) {
            $eslService->disconnect();
            throw $this->toApiException($exception);
        } catch (Throwable $exception) {
            $eslService->disconnect();
            throw $exception;
        }

        $results = collect($result['results'] ?? [])
            ->map(fn (array $item) => $this->normalizeResult($item))
            ->values();

        return response()->json([
            'object' => 'click_to_dial_call',
            'domain_uuid' => (string) ($result['domain']->domain_uuid ?? $domain->domain_uuid),
            'extension_uuid' => (string) ($result['extension']->extension_uuid ?? ''),
            'extension' => (string) ($result['extension']->extension ?? $validated['extension']),
            'destination' => (string) $validated['destination'],
            'sent' => $results->isNotEmpty() && $results->every(fn (array $item) => (bool) ($item['sent'] ?? false)),
            'targets' => $this->normalizeGroups(collect($result['groups'] ?? []))->all(),
            'skipped_targets' => $this->normalizeGroups(collect($result['skipped_groups'] ?? []))->all(),
            'results' => $results->all(),
        ], 200);
    }

    private function resolveDomain(string $domainUuid): Domain
    {
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domainUuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domainUuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        return $domain;
    }

    private function normalizeGroups(Collection $groups): Collection
    {
        return $groups
            ->map(fn (array $group) => [
                'vendor' => (string) ($group['vendor'] ?? ''),
                'agent' => (string) ($group['agent'] ?? ''),
            ])
            ->values();
    }

    private function normalizeResult(array $item): array
    {
        return [
            'sent' => (bool) ($item['sent'] ?? false),
            'reason' => $item['reason'] ?? null,
            'vendor' => (string) ($item['vendor'] ?? ''),
            'agent' => (string) ($item['agent'] ?? ''),
        ];
    }

    private function toApiException(RuntimeException $exception): ApiException
    {
        $message = $exception->getMessage();
        $firstLine = strtok($message, "\n") ?: $message;

        if (str_starts_with($message, 'Extension [')) {
            return new ApiException(404, 'invalid_request_error', 'Extension not found.', 'resource_missing', 'extension');
        }

        if (str_starts_with($message, 'Domain [')) {
            return new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        if (str_starts_with($message, 'No controllable registrations found')) {
            return new ApiException(409, 'invalid_request_error', $firstLine, 'no_controllable_registrations', 'extension');
        }

        if (str_starts_with($message, 'No controllable registrations matched')
            || str_starts_with($message, 'Multiple registration groups matched')
            || str_starts_with($message, 'Unknown vendor')) {
            return new ApiException(400, 'invalid_request_error', $firstLine, 'invalid_request', 'selection');
        }

        return new ApiException(400, 'invalid_request_error', $firstLine, 'invalid_request');
    }
}
