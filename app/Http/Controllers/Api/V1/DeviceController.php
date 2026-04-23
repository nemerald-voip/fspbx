<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Domain;
use App\Models\Devices;
use Illuminate\Http\Request;
use App\Data\Api\V1\DeviceData;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;

class DeviceController extends Controller
{
    /**
     * List devices
     *
     * Returns devices for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `device_view` permission.
     *
     * Compatibility note:
     * - `device_template` is deprecated and retained for backward compatibility.
     * - Use `device_template_uuid` as its successor.
     *
     * Pagination (cursor-based):
     * - Both `limit` and `starting_after` are optional.
     * - If `limit` is not provided, it defaults to 50.
     * - If `starting_after` is not provided, results start from the beginning.
     * - If `has_more` is true, request the next page by passing `starting_after`
     *   equal to the last item's `device_uuid` from the previous response.
     *
     * Examples:
     * - First page: `GET /api/v1/domains/{domain_uuid}/devices`
     * - Next page:  `GET /api/v1/domains/{domain_uuid}/devices?starting_after={last_device_uuid}`
     * - Custom size: `GET /api/v1/domains/{domain_uuid}/devices?limit=50`
     *
     * @group Devices
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam limit integer Optional. Number of results to return (min 1, max 100). Defaults to 50. Example: 50
     * @queryParam starting_after string Optional. Return results after this device UUID (cursor). Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/devices",
     *   "has_more": true,
     *   "data": [
     *     {
     *       "device_uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *       "object": "device",
     *       "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *       "device_profile_uuid": "51759db8-c8bf-4b2f-b48a-6577d7ad6a1a",
     *       "device_address": "0004f23a5bc7",
     *       "device_label": "Front Desk Phone",
     *       "device_template": "Yealink T46U",
     *       "device_template_uuid": "a6cf59ba-4b2b-4bdd-b870-35cc55bca146",
     *       "device_description": "Reception desk device"
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid starting_after UUID" {"error":{"type":"invalid_request_error","message":"Invalid starting_after UUID.","code":"invalid_request","param":"starting_after"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 scenario="Forbidden (domain access)" {"error":{"type":"invalid_request_error","message":"You do not have access to this domain.","code":"forbidden_domain","param":"domain_uuid"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     */
    public function index(Request $request, string $domain_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        $domainExists = Domain::query()->where('domain_uuid', $domain_uuid)->exists();
        if (! $domainExists) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $limit = (int) $request->input('limit', 50);
        $limit = max(1, min(100, $limit));

        $startingAfter = (string) $request->input('starting_after', '');

        $query = QueryBuilder::for(Devices::class)
            ->where('domain_uuid', $domain_uuid)
            ->defaultSort('device_uuid')
            ->reorder('device_uuid')
            ->limit($limit + 1)
            ->select([
                'device_uuid',
                'domain_uuid',
                'device_profile_uuid',
                'device_address',
                'device_label',
                'device_template',
                'device_template_uuid',
                'device_description',
            ]);

        if ($startingAfter !== '') {
            if (! preg_match('/^[0-9a-fA-F-]{36}$/', $startingAfter)) {
                throw new ApiException(400, 'invalid_request_error', 'Invalid starting_after UUID.', 'invalid_request', 'starting_after');
            }

            $query->where('device_uuid', '>', $startingAfter);
        }

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $data = $rows->map(function ($device) {
            return [
                'device_uuid' => (string) $device->device_uuid,
                'object' => 'device',
                'domain_uuid' => (string) $device->domain_uuid,
                'device_profile_uuid' => $device->device_profile_uuid,
                'device_address' => $device->device_address,
                'device_label' => $device->device_label,
                'device_template' => $device->device_template,
                'device_template_uuid' => $device->device_template_uuid,
                'device_description' => $device->device_description,
            ];
        })->all();

        $url = "/api/v1/domains/{$domain_uuid}/devices";

        return response()->json([
            'object' => 'list',
            'url' => $url,
            'has_more' => $hasMore,
            'data' => $data,
        ], 200);
    }

    /**
     * Retrieve a device
     *
     * Returns a single device for the specified domain.
     *
     * Compatibility note:
     * - `device_template` is deprecated and retained for backward compatibility.
     * - Use `device_template_uuid` as its successor.
     *
     * @group Devices
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam device_uuid string required The device UUID. Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     *
     * @response 200 scenario="Success" {
     *   "device_uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *   "object": "device",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "device_profile_uuid": "51759db8-c8bf-4b2f-b48a-6577d7ad6a1a",
     *   "device_address": "0004f23a5bc7",
     *   "device_label": "Front Desk Phone",
     *   "device_template": "Yealink T46U",
     *   "device_template_uuid": "a6cf59ba-4b2b-4bdd-b870-35cc55bca146",
     *   "device_description": "Reception desk device",
     *   "device_provisioned_date": "2026-04-23 14:15:22",
     *   "device_provisioned_ip": "203.0.113.25",
     *   "device_provisioned_agent": "Yealink SIP-T46U 108.86.0.20"
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid device UUID" {"error":{"type":"invalid_request_error","message":"Invalid device UUID.","code":"invalid_request","param":"device_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="Device not found" {"error":{"type":"invalid_request_error","message":"Device not found.","code":"resource_missing","param":"device_uuid"}}
     */
    public function show(Request $request, string $domain_uuid, string $device_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $device_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid device UUID.', 'invalid_request', 'device_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $payload = $this->buildDeviceShowPayload($domain_uuid, $device_uuid);

        return response()->json($payload->toArray(), 200);
    }

    /**
     * Builds response payload as a reusable helper.
     */
    private function buildDeviceShowPayload(string $domain_uuid, string $device_uuid): DeviceData
    {
        $device = QueryBuilder::for(Devices::class)
            ->where('domain_uuid', $domain_uuid)
            ->where('device_uuid', $device_uuid)
            ->select([
                'device_uuid',
                'domain_uuid',
                'device_profile_uuid',
                'device_address',
                'device_label',
                'device_template',
                'device_template_uuid',
                'device_description',
                'device_provisioned_date',
                'device_provisioned_ip',
                'device_provisioned_agent',
            ])
            ->first();

        if (! $device) {
            throw new ApiException(404, 'invalid_request_error', 'Device not found.', 'resource_missing', 'device_uuid');
        }

        return new DeviceData(
            device_uuid: (string) $device->device_uuid,
            object: 'device',
            domain_uuid: (string) $device->domain_uuid,
            device_profile_uuid: $device->device_profile_uuid,
            device_address: $device->device_address,
            device_label: $device->device_label,
            device_template: $device->device_template,
            device_template_uuid: $device->device_template_uuid,
            device_description: $device->device_description,
            device_provisioned_date: $device->device_provisioned_date,
            device_provisioned_ip: $device->device_provisioned_ip,
            device_provisioned_agent: $device->device_provisioned_agent,
        );
    }
}
