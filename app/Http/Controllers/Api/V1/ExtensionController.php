<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Domain;
use App\Models\Extensions;
use App\Models\Voicemails;
use App\Models\FusionCache;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;
use App\Data\Api\V1\ExtensionData;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use App\Data\Api\V1\ExtensionListResponseData;
use App\Http\Requests\Api\V1\StoreExtensionRequest;

class ExtensionController extends Controller
{
    /**
     * List extensions
     *
     * Returns extensions for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `extension_domain` permission.
     *
     * Pagination (cursor-based):
     * - Both `limit` and `starting_after` are optional.
     * - If `limit` is not provided, it defaults to 25.
     * - If `starting_after` is not provided, results start from the beginning.
     * - If `has_more` is true, request the next page by passing `starting_after`
     *   equal to the last item's `extension_uuid` from the previous response.
     *
     * Examples:
     * - First page: `GET /api/v1/domains/{domain_uuid}/extensions`
     * - Next page:  `GET /api/v1/domains/{domain_uuid}/extensions?starting_after={last_extension_uuid}`
     * - Custom size: `GET /api/v1/domains/{domain_uuid}/extensions?limit=50`
     *
     * @group Extensions
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     *
     * @queryParam limit integer Optional. Number of results to return (min 1, max 100). Defaults to 25. Example: 25
     * @queryParam starting_after string Optional. Return results after this extension UUID (cursor). Example: d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/extensions",
     *   "has_more": true,
     *   "data": [
     *     {
     *       "extension_uuid": "d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b",
     *       "object": "extension",
     *       "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *       "extension": "1001",
     *       "enabled": true,
     *       "effective_caller_id_name": "Front Desk",
     *       "effective_caller_id_number": "1001",
     *       "outbound_caller_id_number_e164": "+12135551212",
     *       "outbound_caller_id_number_formatted": "(213) 555-1212",
     *       "emergency_caller_id_number_e164": "+12135559876",
     *       "directory_first_name": "Front",
     *       "directory_last_name": "Desk",
     *       "name_formatted": "Front Desk",
     *       "directory_visible": true,
     *       "directory_exten_visible": true,
     *       "email": "frontdesk@example.com",
     *       "do_not_disturb": false,
     *       "user_record": true,
     *       "suspended": false,
     *       "description": "Main reception phone",
     *       "forward_all_enabled": false,
     *       "forward_busy_enabled": false,
     *       "forward_no_answer_enabled": false,
     *       "forward_user_not_registered_enabled": false,
     *       "follow_me_enabled": false
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Invalid domain UUID.",
     *     "code": "invalid_request",
     *     "param": "domain_uuid"
     *   }
     * }
     *
     * @response 401 scenario="Unauthenticated" {
     *   "error": {
     *     "type": "authentication_error",
     *     "message": "Unauthenticated.",
     *     "code": "unauthenticated"
     *   }
     * }
     *
     * @response 403 scenario="Forbidden (domain access)" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "You do not have access to this domain.",
     *     "code": "forbidden_domain",
     *     "param": "domain_uuid"
     *   }
     * }
     *
     * @response 404 scenario="Not found" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Domain not found.",
     *     "code": "resource_missing",
     *     "param": "domain_uuid"
     *   }
     * }
     */

    public function index(Request $request, string $domain_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        // Validate UUID format early (nice for consumers + Scribe)
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(
                400,
                'invalid_request_error',
                'Invalid domain UUID.',
                'invalid_request',
                'domain_uuid'
            );
        }

        // Optional: return a clean 404 if domain UUID doesn't exist
        $domainExists = Domain::query()->where('domain_uuid', $domain_uuid)->exists();
        if (! $domainExists) {
            throw new ApiException(
                404,
                'invalid_request_error',
                'Domain not found.',
                'resource_missing',
                'domain_uuid'
            );
        }

        $limit = (int) $request->input('limit', 25);
        $limit = max(1, min(100, $limit));

        $startingAfter = (string) $request->input('starting_after', '');

        // Helper: safely normalize "true"/"false"/"1"/"0"/null => ?bool
        $toBool = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;

            $b = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return $b;
        };

        $query = QueryBuilder::for(Extensions::class)
            ->where('domain_uuid', $domain_uuid)
            ->with(['voicemail' => function ($query) use ($domain_uuid) {
                $query->where('domain_uuid', $domain_uuid)
                    ->select('voicemail_id', 'domain_uuid', 'voicemail_mail_to');
            }])
            ->defaultSort('extension_uuid')
            ->reorder('extension_uuid')
            ->limit($limit + 1)
            ->select([
                'extension_uuid',
                'domain_uuid',
                'extension',
                'enabled',
                'effective_caller_id_name',
                'effective_caller_id_number',
                'outbound_caller_id_number',
                'emergency_caller_id_number',
                'directory_first_name',
                'directory_last_name',
                'directory_visible',
                'directory_exten_visible',
                'do_not_disturb',
                'user_record',
                'description',
                'forward_all_enabled',
                'forward_busy_enabled',
                'forward_no_answer_enabled',
                'forward_user_not_registered_enabled',
                'follow_me_enabled',
            ]);

        if ($startingAfter !== '') {
            $query->where('extension_uuid', '>', $startingAfter);
        }

        $rows = $query->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $data = $rows->map(function ($e) use ($toBool) {
            $first = $e->directory_first_name ?? null;
            $last  = $e->directory_last_name ?? null;

            $nameFormatted = trim(implode(' ', array_filter([$first, $last], fn($v) => $v !== null && $v !== '')));
            $nameFormatted = $nameFormatted !== '' ? $nameFormatted : null;

            return new ExtensionData(
                extension_uuid: (string) $e->extension_uuid,
                object: 'extension',
                domain_uuid: (string) $e->domain_uuid,
                extension: (string) $e->extension,
                enabled: (bool) $toBool($e->enabled) ?? false,

                effective_caller_id_name: $e->effective_caller_id_name,
                effective_caller_id_number: $e->effective_caller_id_number,

                outbound_caller_id_number_e164: $e->outbound_caller_id_number,
                outbound_caller_id_number_formatted: null, // set when you add a formatter/util
                emergency_caller_id_number_e164: $e->emergency_caller_id_number,

                directory_first_name: $first,
                directory_last_name: $last,
                name_formatted: $nameFormatted,

                directory_visible: $toBool($e->directory_visible),
                directory_exten_visible: $toBool($e->directory_exten_visible),

                email: $e->email,

                do_not_disturb: $toBool($e->do_not_disturb),
                user_record: $toBool($e->user_record),

                suspended: $e->suspended !== null ? (bool) $e->suspended : null,

                description: $e->description,

                forward_all_enabled: $toBool($e->forward_all_enabled),
                forward_busy_enabled: $toBool($e->forward_busy_enabled),
                forward_no_answer_enabled: $toBool($e->forward_no_answer_enabled),
                forward_user_not_registered_enabled: $toBool($e->forward_user_not_registered_enabled),

                follow_me_enabled: $toBool($e->follow_me_enabled),
            );
        })->all();

        $url = "/api/v1/domains/{$domain_uuid}/extensions";

        $payload = new ExtensionListResponseData(
            object: 'list',
            url: $url,
            has_more: $hasMore,
            data: $data,
        );

        return response()->json($payload->toArray(), 200);
    }

    /**
     * Retrieve an extension
     *
     * Returns a single extension for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `extension_view` permission.
     *
     * Notes:
     * - If the domain does not exist, a `resource_missing` error is returned.
     * - If the extension does not exist in that domain, a `resource_missing` error is returned.
     *
     * @group Extensions
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam extension_uuid string required The extension UUID. Example: d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b
     *
     * @response 200 scenario="Success" {
     *   "extension_uuid": "d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b",
     *   "object": "extension",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "extension": "1001",
     *   "enabled": true,
     *   "effective_caller_id_name": "Front Desk",
     *   "effective_caller_id_number": "1001",
     *   "outbound_caller_id_number_e164": "+12135551212",
     *   "outbound_caller_id_number_formatted": "(213) 555-1212",
     *   "emergency_caller_id_number_e164": "+12135559876",
     *   "directory_first_name": "Front",
     *   "directory_last_name": "Desk",
     *   "name_formatted": "Front Desk",
     *   "directory_visible": true,
     *   "directory_exten_visible": true,
     *   "email": "frontdesk@example.com",
     *   "do_not_disturb": false,
     *   "user_record": true,
     *   "suspended": false,
     *   "description": "Main reception phone",
     *   "forward_all_enabled": false,
     *   "forward_busy_enabled": false,
     *   "forward_no_answer_enabled": false,
     *   "forward_user_not_registered_enabled": false,
     *   "follow_me_enabled": false
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Invalid domain UUID.",
     *     "code": "invalid_request",
     *     "param": "domain_uuid"
     *   }
     * }
     *
     * @response 400 scenario="Invalid extension UUID" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Invalid extension UUID.",
     *     "code": "invalid_request",
     *     "param": "extension_uuid"
     *   }
     * }

     *
     * @response 401 scenario="Unauthenticated" {
     *   "error": {
     *     "type": "authentication_error",
     *     "message": "Unauthenticated.",
     *     "code": "unauthenticated"
     *   }
     * }
     *
     * @response 403 scenario="Forbidden (domain access)" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "You do not have access to this domain.",
     *     "code": "forbidden_domain",
     *     "param": "domain_uuid"
     *   }
     * }
     *
     * @response 404 scenario="Domain not found" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Domain not found.",
     *     "code": "resource_missing",
     *     "param": "domain_uuid"
     *   }
     * }
     *
     * @response 404 scenario="Extension not found" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Extension not found.",
     *     "code": "resource_missing",
     *     "param": "extension_uuid"
     *   }
     * }
     */


    public function show(Request $request, string $domain_uuid, string $extension_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        // Validate domain UUID format early (nice for consumers + Scribe)
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(
                400,
                'invalid_request_error',
                'Invalid domain UUID.',
                'invalid_request',
                'domain_uuid'
            );
        }

        // Validate extension UUID format early (nice for consumers + Scribe)
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $extension_uuid)) {
            throw new ApiException(
                400,
                'invalid_request_error',
                'Invalid extension UUID.',
                'invalid_request',
                'extension_uuid'
            );
        }

        $domainExists = Domain::query()->where('domain_uuid', $domain_uuid)->exists();
        if (! $domainExists) {
            throw new ApiException(
                404,
                'invalid_request_error',
                'Domain not found.',
                'resource_missing',
                'domain_uuid'
            );
        }

        // Helper: safely normalize "true"/"false"/"1"/"0"/null => ?bool
        $toBool = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;

            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        };

        $e = QueryBuilder::for(Extensions::class)
            ->where('domain_uuid', $domain_uuid)
            ->where('extension_uuid', $extension_uuid)
            ->with(['voicemail' => function ($query) use ($domain_uuid) {
                $query->where('domain_uuid', $domain_uuid)
                    ->select('voicemail_id', 'domain_uuid', 'voicemail_mail_to');
            }])
            ->defaultSort('extension_uuid')
            ->reorder('extension_uuid')
            ->select([
                'extension_uuid',
                'domain_uuid',
                'extension',
                'enabled',
                'effective_caller_id_name',
                'effective_caller_id_number',
                'outbound_caller_id_number',
                'emergency_caller_id_number',
                'directory_first_name',
                'directory_last_name',
                'directory_visible',
                'directory_exten_visible',
                'do_not_disturb',
                'user_record',
                'description',
                'forward_all_enabled',
                'forward_busy_enabled',
                'forward_no_answer_enabled',
                'forward_user_not_registered_enabled',
                'follow_me_enabled',
            ])
            ->first();

        if (! $e) {
            throw new ApiException(
                404,
                'invalid_request_error',
                'Extension not found.',
                'resource_missing',
                'extension_uuid'
            );
        }

        $first = $e->directory_first_name ?? null;
        $last  = $e->directory_last_name ?? null;

        $nameFormatted = trim(implode(' ', array_filter([$first, $last], fn($v) => $v !== null && $v !== '')));
        $nameFormatted = $nameFormatted !== '' ? $nameFormatted : null;

        $payload = new ExtensionData(
            extension_uuid: (string) $e->extension_uuid,
            object: 'extension',
            domain_uuid: (string) $e->domain_uuid,
            extension: (string) $e->extension,

            enabled: (bool) ($toBool($e->enabled) ?? false),

            effective_caller_id_name: $e->effective_caller_id_name,
            effective_caller_id_number: $e->effective_caller_id_number,

            outbound_caller_id_number_e164: $e->outbound_caller_id_number,
            outbound_caller_id_number_formatted: null, // plug in formatter later
            emergency_caller_id_number_e164: $e->emergency_caller_id_number,

            directory_first_name: $first,
            directory_last_name: $last,
            name_formatted: $nameFormatted,

            directory_visible: $toBool($e->directory_visible),
            directory_exten_visible: $toBool($e->directory_exten_visible),

            email: $e->email,

            do_not_disturb: $toBool($e->do_not_disturb),
            user_record: $toBool($e->user_record),

            suspended: $e->suspended !== null ? (bool) $e->suspended : null,

            description: $e->description,

            forward_all_enabled: $toBool($e->forward_all_enabled),
            forward_busy_enabled: $toBool($e->forward_busy_enabled),
            forward_no_answer_enabled: $toBool($e->forward_no_answer_enabled),
            forward_user_not_registered_enabled: $toBool($e->forward_user_not_registered_enabled),

            follow_me_enabled: $toBool($e->follow_me_enabled),
        );

        return response()->json($payload->toArray(), 200);
    }


    /**
     * Create an extension
     *
     * Creates a new extension in the specified domain.
     *
     * Notes:
     * - Some fields are defaulted server-side (e.g., effective caller-id, voicemail defaults) based on your internal request logic.
     * - Returns the created extension object.
     *
     * @group Extensions
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     *
     * @response 201 scenario="Created" {
     *   "extension_uuid": "d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b",
     *   "object": "extension",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "extension": "1001",
     *   "enabled": true,
     *   "effective_caller_id_name": "Front Desk",
     *   "effective_caller_id_number": "1001",
     *   "outbound_caller_id_number_e164": null,
     *   "outbound_caller_id_number_formatted": null,
     *   "emergency_caller_id_number_e164": null,
     *   "directory_first_name": "Front",
     *   "directory_last_name": "Desk",
     *   "name_formatted": "Front Desk",
     *   "directory_visible": true,
     *   "directory_exten_visible": true,
     *   "email": null,
     *   "do_not_disturb": null,
     *   "user_record": null,
     *   "suspended": null,
     *   "description": "Main reception phone",
     *   "forward_all_enabled": null,
     *   "forward_busy_enabled": null,
     *   "forward_no_answer_enabled": null,
     *   "forward_user_not_registered_enabled": null,
     *   "follow_me_enabled": null
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Invalid domain UUID.",
     *     "code": "invalid_request",
     *     "param": "domain_uuid"
     *   }
     * }
     *
     * @response 401 scenario="Unauthenticated" {
     *   "error": {
     *     "type": "authentication_error",
     *     "message": "Unauthenticated.",
     *     "code": "unauthenticated"
     *   }
     * }
     *
     * @response 404 scenario="Domain not found" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Domain not found.",
     *     "code": "resource_missing",
     *     "param": "domain_uuid"
     *   }
     * }
     */
    public function store(StoreExtensionRequest $request, string $domain_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        // Validate domain UUID format early (nice for consumers + Scribe)
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(
                400,
                'invalid_request_error',
                'Invalid domain UUID.',
                'invalid_request',
                'domain_uuid'
            );
        }

        $domainExists = Domain::query()->where('domain_uuid', $domain_uuid)->exists();
        if (! $domainExists) {
            throw new ApiException(
                404,
                'invalid_request_error',
                'Domain not found.',
                'resource_missing',
                'domain_uuid'
            );
        }

        // Force the domain_uuid from the route (prevents cross-domain writes)
        $validated = array_merge($request->validated(), [
            'domain_uuid' => $domain_uuid,
        ]);

        // Helper: normalize "true"/"false"/"1"/"0"/null => ?bool
        $toBool = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;

            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        };

        try {
            [$extension] = DB::transaction(function () use ($validated) {

                // 1) Create extension
                $extension = Extensions::create($validated);

                // 2) Create voicemail (only if voicemail_id provided)
                if (! empty($validated['voicemail_id'])) {
                    Voicemails::create($validated);
                }

                return [$extension->fresh()];
            });

            // Clear FusionPBX cache for the extension
            if (isset($extension->extension)) {
                FusionCache::clear("directory:" . $extension->extension . "@" . $extension->user_context);
            }

            $first = $extension->directory_first_name ?? null;
            $last  = $extension->directory_last_name ?? null;

            $nameFormatted = trim(implode(' ', array_filter([$first, $last], fn($v) => $v !== null && $v !== '')));
            $nameFormatted = $nameFormatted !== '' ? $nameFormatted : null;

            $payload = new ExtensionData(
                extension_uuid: (string) $extension->extension_uuid,
                object: 'extension',
                domain_uuid: (string) $extension->domain_uuid,
                extension: (string) $extension->extension,

                enabled: (bool) ($toBool($extension->enabled) ?? true),

                effective_caller_id_name: $extension->effective_caller_id_name,
                effective_caller_id_number: $extension->effective_caller_id_number,

                outbound_caller_id_number_e164: $extension->outbound_caller_id_number ?? null,
                outbound_caller_id_number_formatted: null,
                emergency_caller_id_number_e164: $extension->emergency_caller_id_number ?? null,

                directory_first_name: $first,
                directory_last_name: $last,
                name_formatted: $nameFormatted,

                directory_visible: $toBool($extension->directory_visible),
                directory_exten_visible: $toBool($extension->directory_exten_visible),

                email: $extension->email ?? null,

                do_not_disturb: $toBool($extension->do_not_disturb),
                user_record: $toBool($extension->user_record),

                suspended: $extension->suspended !== null ? (bool) $extension->suspended : null,

                description: $extension->description ?? null,

                forward_all_enabled: $toBool($extension->forward_all_enabled),
                forward_busy_enabled: $toBool($extension->forward_busy_enabled),
                forward_no_answer_enabled: $toBool($extension->forward_no_answer_enabled),
                forward_user_not_registered_enabled: $toBool($extension->forward_user_not_registered_enabled),

                follow_me_enabled: $toBool($extension->follow_me_enabled),
            );

            return response()
                ->json($payload->toArray(), 201)
                ->header('Location', "/api/v1/domains/{$domain_uuid}/extensions/{$extension->extension_uuid}");
        } catch (\Exception $e) {

            logger('API Extension store QueryException: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        } catch (\Throwable $e) {
            logger('API Extension store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }
}
