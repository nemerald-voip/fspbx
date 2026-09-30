<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\V1\ActiveExtensionReportData;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ActiveExtensionReportController extends Controller
{
    /**
     * Retrieve active and suspended extensions report
     *
     * Returns the current "Active and suspended extensions per domain" report
     * for one domain as a single JSON object. No export or email is generated.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `extension_view` permission.
     *
     * Counts follow the report's definitions:
     * - Total extensions includes all extensions, including disabled extensions.
     * - Suspended extensions have their advanced setting `suspended` set to true.
     * - Active extensions equals total minus suspended. Missing advanced settings
     *   mean the extension is not suspended. This is not a SIP registration count.
     * - Active mobile apps counts extensions with a linked mobile app in the same
     *   domain whose stored status is 1, independently of extension suspension.
     * - Domains without extensions return zero for every count.
     *
     * @group Reports
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     *
     * @response 200 scenario="Success" {
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "object": "active_extension_report",
     *   "domain_name": "10001.fspbx.com",
     *   "domain_description": "BluePeak Solutions",
     *   "total_extensions": 11,
     *   "suspended_extensions": 2,
     *   "active_extensions": 9,
     *   "active_mobile_apps": 3
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 403 scenario="Forbidden (domain access)" {"error":{"type":"invalid_request_error","message":"You do not have access to this domain.","code":"forbidden_domain","param":"domain_uuid"}}
     * @response 403 scenario="Forbidden (missing permission)" {"success":false,"message":"Forbidden (missing permission).","error":{"code":"forbidden_permission","permission":"extension_view"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     *
     * @responseField domain_uuid string UUID of the requested domain.
     * @responseField object string Always `active_extension_report`.
     * @responseField domain_name string Domain name.
     * @responseField domain_description string Domain description, or null when unset.
     * @responseField total_extensions integer Total number of extensions in the domain.
     * @responseField suspended_extensions integer Number of suspended extensions.
     * @responseField active_extensions integer Total extensions minus suspended extensions.
     * @responseField active_mobile_apps integer Number of extensions with a linked mobile app whose stored status is 1.
     */
    public function show(Request $request, string $domain_uuid): JsonResponse
    {
        if (! $request->user()) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! Str::isUuid($domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        $domain = Domain::query()
            ->select(['domain_uuid', 'domain_name', 'domain_description'])
            ->where('domain_uuid', $domain_uuid)
            ->withCount([
                'extensions as total_extensions',
                'extensions as suspended_extensions' => fn (Builder $query) => $query
                    ->whereHas('advSettings', fn (Builder $settings) => $settings->where('suspended', true)),
                'extensions as active_mobile_apps' => fn (Builder $query) => $query
                    ->whereHas('mobile_app', fn (Builder $apps) => $apps
                        ->whereColumn('mobile_app_users.domain_uuid', 'v_extensions.domain_uuid')
                        ->where('status', 1)),
            ])
            ->first();

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $total = (int) $domain->total_extensions;
        $suspended = (int) $domain->suspended_extensions;

        $payload = new ActiveExtensionReportData(
            domain_uuid: (string) $domain->domain_uuid,
            object: 'active_extension_report',
            domain_name: (string) $domain->domain_name,
            domain_description: $domain->domain_description,
            total_extensions: $total,
            suspended_extensions: $suspended,
            active_extensions: $total - $suspended,
            active_mobile_apps: (int) $domain->active_mobile_apps,
        );

        return response()->json($payload->toArray());
    }
}
