<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListPhoneControlCallsRequest;
use App\Http\Requests\Api\V1\ListPhoneControlTargetsRequest;
use App\Http\Requests\Api\V1\StorePhoneControlActionRequest;
use App\Models\Domain;
use App\Services\FreeswitchEslService;
use App\Services\PhoneControlService;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PhoneControlController extends Controller
{
    /**
     * List phone-control targets
     *
     * Returns the registered phones that FS PBX can control for one extension.
     * Use the returned `agent` value with either the click-to-dial or phone-control
     * action endpoint. Each target also lists its supported phone-control actions.
     * Use `vendor`, `lan_ip`, or a `registration_call_ids` value when needed.
     * See [Supported phones and actions](https://www.fspbx.com/docs/additional-information/phone-control/#supported-phones-and-actions)
     * for vendor-specific capabilities and behavior.
     *
     * Access rules:
     * - Caller must have access to the target domain.
     * - Caller must have the `phone_control_view` permission.
     *
     * @group Phone Control
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam extension string required Extension number to inspect. Example: 1001
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/phone-control/targets",
     *   "has_more": false,
     *   "data": [
     *     {
     *       "vendor": "yealink",
     *       "agent": "Yealink SIP-T53W 96.86.0.45",
     *       "lan_ip": "10.0.0.25",
     *       "registration_count": 1,
     *       "registration_call_ids": ["8cc81337-3728-4b87-a507-f59627abf313"],
     *       "supported_actions": ["hold", "resume", "blind-transfer", "attended-transfer", "complete-transfer", "cancel-transfer", "conference", "mute-toggle", "end-call", "answer-call", "dnd-on", "dnd-off"]
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 400 scenario="Validation error" {"error":{"type":"invalid_request_error","message":"The extension field is required.","code":"invalid_parameter","param":"extension","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 403 scenario="Forbidden (domain access)" {"error":{"type":"invalid_request_error","message":"You do not have access to this domain.","code":"forbidden_domain","param":"domain_uuid","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 403 scenario="Forbidden (missing permission)" {"success":false,"message":"Forbidden (missing permission).","error":{"code":"forbidden_permission","permission":"phone_control_view"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 404 scenario="Extension not found" {"error":{"type":"invalid_request_error","message":"Extension not found.","code":"resource_missing","param":"extension","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 500 scenario="Internal server error" {"error":{"type":"api_error","message":"An unexpected error occurred.","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     */
    public function targets(
        ListPhoneControlTargetsRequest $request,
        PhoneControlService $phoneControl,
        FreeswitchEslService $eslService,
        string $domain_uuid
    ) {
        $domain = $this->resolveDomain($domain_uuid);
        $validated = $request->validated();

        try {
            $groups = $phoneControl->candidateGroups(
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
            'url' => "/api/v1/domains/{$domain_uuid}/phone-control/targets",
            'has_more' => false,
            'data' => $this->normalizeGroups($groups, $phoneControl, true)->all(),
        ], 200);
    }

    /**
     * List active calls for phone control
     *
     * Returns the extension's current FreeSWITCH calls and their states. Use
     * this before or after an action to confirm whether a call is `RINGING`,
     * `ACTIVE`, or `HELD`. An empty `data` array means there are no calls.
     *
     * Access rules:
     * - Caller must have access to the target domain.
     * - Caller must have the `phone_control_view` permission.
     *
     * @group Phone Control
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam extension string required Extension number to inspect. Example: 1001
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/phone-control/calls",
     *   "has_more": false,
     *   "data": [
     *     {
     *       "channel_uuid": "adf94fd8-4a57-45ea-a463-2c1f53b38f80",
     *       "sip_call_id": "0_123456@10.0.0.25",
     *       "state": "ACTIVE",
     *       "direction": "inbound",
     *       "other_party": "2001"
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 400 scenario="Validation error" {"error":{"type":"invalid_request_error","message":"The extension field is required.","code":"invalid_parameter","param":"extension","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 403 scenario="Forbidden (domain access)" {"error":{"type":"invalid_request_error","message":"You do not have access to this domain.","code":"forbidden_domain","param":"domain_uuid","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 403 scenario="Forbidden (missing permission)" {"success":false,"message":"Forbidden (missing permission).","error":{"code":"forbidden_permission","permission":"phone_control_view"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 404 scenario="Extension not found" {"error":{"type":"invalid_request_error","message":"Extension not found.","code":"resource_missing","param":"extension","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 500 scenario="Internal server error" {"error":{"type":"api_error","message":"An unexpected error occurred.","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     */
    public function calls(
        ListPhoneControlCallsRequest $request,
        PhoneControlService $phoneControl,
        FreeswitchEslService $eslService,
        string $domain_uuid
    ) {
        $domain = $this->resolveDomain($domain_uuid);
        $validated = $request->validated();

        try {
            $calls = $phoneControl->activeCallsFor(
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
            'url' => "/api/v1/domains/{$domain_uuid}/phone-control/calls",
            'has_more' => false,
            'data' => $calls->map(fn (array $call) => [
                'channel_uuid' => (string) ($call['uuid'] ?? ''),
                'sip_call_id' => (string) ($call['sip_call_id'] ?? ''),
                'state' => (string) ($call['callstate'] ?? ''),
                'direction' => (string) ($call['direction'] ?? ''),
                'other_party' => (string) ($call['other_party'] ?? ''),
            ])->values()->all(),
        ], 200);
    }

    /**
     * Create a phone-control action
     *
     * Controls a registered phone or its active call through the same service
     * used by `php artisan phone:control`. Vendor support differs, so first use
     * the targets endpoint and choose an action from that phone's
     * `supported_actions`. Prefer `agent` when selecting a specific phone.
     * See [Supported phones and actions](https://www.fspbx.com/docs/additional-information/phone-control/#supported-phones-and-actions)
     * for vendor-specific capabilities and behavior.
     *
     * Call-state safeguards are enabled by default. Hold, resume, end-call,
     * transfer, and answer-call normally require one unambiguous call in the
     * expected state. `force=true` bypasses those checks. `dry_run=true`
     * resolves the target and returns a command preview without sending it.
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
     * @bodyParam action string required Action to execute. Valid values: hold, resume, blind-transfer, attended-transfer, complete-transfer, cancel-transfer, conference, mute-toggle, mute-on, mute-off, end-call, answer-call, dnd-on, dnd-off, or dnd-toggle. Example: hold
     * @bodyParam destination string Optional. Required for blind-transfer and attended-transfer. Example: 2001
     * @bodyParam agent string Optional. Preferred selector for a specific phone. Use an `agent` value from the targets response; plain text matching is case-insensitive. Example: SIP-T53W
     * @bodyParam vendor string Optional. Broader selector for a supported driver: yealink, snom, poly, grandstream, or generic. Example: yealink
     * @bodyParam lan_ip string Optional. Exact device IP selector from the targets response. Example: 10.0.0.25
     * @bodyParam call_id string Optional. Registration call ID selector from a target's `registration_call_ids`. Example: 8cc81337-3728-4b87-a507-f59627abf313
     * @bodyParam force boolean Optional. Skip normal single-call state checks. Default: false. Example: false
     * @bodyParam no_resume boolean Optional. For cancel-transfer, leave the original call held. Default: false. Example: false
     * @bodyParam dry_run boolean Optional. Preview the command without sending it. Default: false. Example: false
     *
     * @response 200 scenario="Success" {
     *   "object": "phone_control_action",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "extension_uuid": "d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b",
     *   "extension": "1001",
     *   "action": "hold",
     *   "destination": null,
     *   "vendor": "yealink",
     *   "active_call_id": "0_123456@10.0.0.25",
     *   "state_is_toggle": false,
     *   "dry_run": false,
     *   "sent": true,
     *   "targets": [{"vendor":"yealink","agent":"Yealink SIP-T53W 96.86.0.45","lan_ip":"10.0.0.25"}],
     *   "skipped_targets": [],
     *   "results": [{"sent":true,"reason":null,"vendor":"yealink","agent":"Yealink SIP-T53W 96.86.0.45","lan_ip":"10.0.0.25","sip_profile_name":"internal","target_uri":"1001@pbx.example.com","transport":"esl-notify","registration_call_id":"8cc81337-3728-4b87-a507-f59627abf313"}],
     *   "auto_resume": null
     * }
     *
     * @response 200 scenario="Dry run" {
     *   "object": "phone_control_action",
     *   "action": "hold",
     *   "dry_run": true,
     *   "sent": true,
     *   "results": [{"sent":true,"vendor":"yealink","command":"sendevent NOTIFY ...","body":"key=F_HOLD"}]
     * }
     *
     * @response 400 scenario="Invalid selection" {"error":{"type":"invalid_request_error","message":"No supported phone-control registrations matched the selection.","code":"invalid_request","param":"selection","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 400 scenario="Validation error" {"error":{"type":"invalid_request_error","message":"The destination field is required when action is blind transfer.","code":"invalid_parameter","param":"destination","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 403 scenario="Forbidden (domain access)" {"error":{"type":"invalid_request_error","message":"You do not have access to this domain.","code":"forbidden_domain","param":"domain_uuid","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 403 scenario="Forbidden (missing permission)" {"success":false,"message":"Forbidden (missing permission).","error":{"code":"forbidden_permission","permission":"phone_control_call"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 404 scenario="Extension not found" {"error":{"type":"invalid_request_error","message":"Extension not found.","code":"resource_missing","param":"extension","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 409 scenario="No supported registrations" {"error":{"type":"invalid_request_error","message":"No supported phone-control registrations found for 1001@pbx.example.com.","code":"no_supported_registrations","param":"extension","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 409 scenario="Unsupported action" {"error":{"type":"invalid_request_error","message":"Action [dnd-on] is not supported for Poly.","code":"unsupported_action","param":"action","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 409 scenario="Invalid call state" {"error":{"type":"invalid_request_error","message":"The call on 1001@pbx.example.com is already on hold.","code":"invalid_call_state","param":"action","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     * @response 500 scenario="Internal server error" {"error":{"type":"api_error","message":"An unexpected error occurred.","doc_url":"https://www.fspbx.com/docs/api/v1/errors/"}}
     */
    public function store(
        StorePhoneControlActionRequest $request,
        PhoneControlService $phoneControl,
        FreeswitchEslService $eslService,
        string $domain_uuid
    ) {
        $domain = $this->resolveDomain($domain_uuid);
        $validated = $request->validated();
        $dryRun = (bool) ($validated['dry_run'] ?? false);

        try {
            $result = $phoneControl->execute(
                $eslService,
                (string) $validated['extension'],
                (string) $domain->domain_uuid,
                (string) $validated['action'],
                isset($validated['destination']) ? (string) $validated['destination'] : null,
                [
                    'agent' => $validated['agent'] ?? null,
                    'vendor' => $validated['vendor'] ?? null,
                    'lan_ip' => $validated['lan_ip'] ?? null,
                    'call_id' => $validated['call_id'] ?? null,
                    'force' => (bool) ($validated['force'] ?? false),
                    'no_resume' => (bool) ($validated['no_resume'] ?? false),
                    'dry_run' => $dryRun,
                ]
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $eslService->disconnect();
            throw $this->toApiException($exception);
        } catch (Throwable $exception) {
            $eslService->disconnect();
            throw $exception;
        }

        $results = collect($result['results'] ?? [])
            ->map(fn (array $item) => $this->normalizeResult($item, $dryRun))
            ->values();

        return response()->json([
            'object' => 'phone_control_action',
            'domain_uuid' => (string) ($result['domain']->domain_uuid ?? $domain->domain_uuid),
            'extension_uuid' => (string) ($result['extension']->extension_uuid ?? ''),
            'extension' => (string) ($result['extension']->extension ?? $validated['extension']),
            'action' => (string) ($result['action'] ?? $validated['action']),
            'destination' => $result['destination'] ?? null,
            'vendor' => (string) ($result['vendor'] ?? ''),
            'active_call_id' => $result['active_call_id'] ?? null,
            'state_is_toggle' => (bool) ($result['state_is_toggle'] ?? false),
            'dry_run' => $dryRun,
            'sent' => $results->isNotEmpty()
                && $results->every(fn (array $item) => (bool) ($item['sent'] ?? false)),
            'targets' => $this->normalizeGroups(
                collect($result['groups'] ?? []),
                $phoneControl
            )->all(),
            'skipped_targets' => $this->normalizeGroups(
                collect($result['skipped_groups'] ?? []),
                $phoneControl
            )->all(),
            'results' => $results->all(),
            'auto_resume' => isset($result['auto_resume'])
                ? $this->normalizeResult($result['auto_resume'], false)
                : null,
        ], 200);
    }

    private function resolveDomain(string $domainUuid): Domain
    {
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domainUuid)) {
            throw new ApiException(
                400,
                'invalid_request_error',
                'Invalid domain UUID.',
                'invalid_request',
                'domain_uuid'
            );
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domainUuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(
                404,
                'invalid_request_error',
                'Domain not found.',
                'resource_missing',
                'domain_uuid'
            );
        }

        return $domain;
    }

    private function normalizeGroups(
        Collection $groups,
        PhoneControlService $phoneControl,
        bool $includeCapabilities = false
    ): Collection {
        return $groups
            ->map(function (array $group) use ($phoneControl, $includeCapabilities) {
                $normalized = [
                    'vendor' => (string) ($group['vendor'] ?? ''),
                    'agent' => (string) ($group['agent'] ?? ''),
                    'lan_ip' => (string) ($group['lan_ip'] ?? ''),
                ];

                if (! $includeCapabilities) {
                    return $normalized;
                }

                return $normalized + [
                    'registration_count' => (int) ($group['count'] ?? 0),
                    'registration_call_ids' => collect($group['registrations'] ?? [])
                        ->pluck('call_id')
                        ->filter()
                        ->map(fn ($callId) => (string) $callId)
                        ->values()
                        ->all(),
                    'supported_actions' => $phoneControl->supportedActions(
                        (string) ($group['vendor'] ?? '')
                    ),
                ];
            })
            ->values();
    }

    private function normalizeResult(array $item, bool $includePreview): array
    {
        $normalized = [
            'sent' => (bool) ($item['sent'] ?? false),
            'reason' => $item['reason'] ?? null,
            'vendor' => (string) ($item['vendor'] ?? ''),
            'agent' => (string) ($item['agent'] ?? ''),
            'lan_ip' => (string) ($item['lan_ip'] ?? ''),
            'sip_profile_name' => (string) ($item['sip_profile_name'] ?? ''),
            'target_uri' => (string) ($item['target_uri'] ?? ''),
            'transport' => (string) ($item['transport'] ?? ''),
            'registration_call_id' => $item['registration_call_id'] ?? null,
        ];

        if ($includePreview) {
            $normalized['command'] = (string) ($item['command'] ?? '');
            $normalized['body'] = (string) ($item['body'] ?? '');
        }

        return $normalized;
    }

    private function toApiException(
        InvalidArgumentException|RuntimeException $exception
    ): ApiException {
        $message = $exception->getMessage();
        $firstLine = strtok($message, "\n") ?: $message;

        if (str_starts_with($message, 'Extension [')) {
            return new ApiException(
                404,
                'invalid_request_error',
                'Extension not found.',
                'resource_missing',
                'extension'
            );
        }

        if (str_starts_with($message, 'Domain [')) {
            return new ApiException(
                404,
                'invalid_request_error',
                'Domain not found.',
                'resource_missing',
                'domain_uuid'
            );
        }

        if (str_starts_with(
            $message,
            'No supported phone-control registrations found'
        )) {
            return new ApiException(
                409,
                'invalid_request_error',
                $firstLine,
                'no_supported_registrations',
                'extension'
            );
        }

        if (str_starts_with(
            $message,
            'No supported phone-control registrations matched'
        )
            || str_starts_with($message, 'Multiple registration groups matched')
            || str_starts_with($message, 'Unknown vendor')) {
            return new ApiException(
                400,
                'invalid_request_error',
                $firstLine,
                'invalid_request',
                'selection'
            );
        }

        if (str_starts_with($message, 'Action [')
            && str_contains($message, 'is not supported')) {
            return new ApiException(
                409,
                'invalid_request_error',
                $firstLine,
                'unsupported_action',
                'action'
            );
        }

        if (str_contains($message, ' has no active calls to ')
            || str_contains($message, ' active calls; ')
            || str_starts_with($message, 'The call on ')
            || str_contains($message, ' has no ringing call to answer')) {
            return new ApiException(
                409,
                'invalid_request_error',
                $firstLine,
                'invalid_call_state',
                'action'
            );
        }

        return new ApiException(
            400,
            'invalid_request_error',
            $firstLine,
            'invalid_request'
        );
    }
}
