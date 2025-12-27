<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Domain;
use App\Models\Extensions;
use App\Models\Voicemails;
use App\Jobs\DeleteAppUser;
use App\Models\FusionCache;
use App\Jobs\SuspendAppUser;
use Illuminate\Http\Request;
use App\Jobs\UpdateAppSettings;
use App\Exceptions\ApiException;
use App\Data\Api\V1\ExtensionData;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use App\Data\Api\V1\DeletedResponseData;
use App\Data\Api\V1\ExtensionListResponseData;
use App\Http\Requests\Api\V1\StoreExtensionRequest;
use App\Http\Requests\Api\V1\UpdateExtensionRequest;

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

        // Verify domain exists + fetch domain_name for defaults
        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
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

        // Normalize booleans into the DB's expected TEXT values ('true'/'false')
        $boolText = static function ($value, ?bool $default = null): ?string {
            if ($value === null || $value === '') {
                return $default === null ? null : ($default ? 'true' : 'false');
            }
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            // handles "true"/"false"/"1"/"0"/1/0
            $b = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($b === null) {
                return $default === null ? null : ($default ? 'true' : 'false');
            }
            return $b ? 'true' : 'false';
        };

        // Numeric normalization helpers
        $toNumericString = static function ($value): ?string {
            if ($value === null || $value === '') return null;
            // Keep it simple; let DB cast numeric strings
            return (string) $value;
        };

        $validated = $request->validated();

        // --- Controller-only computed defaults (NOT documented) ---
        $first = (string) ($validated['directory_first_name'] ?? '');
        $last  = (string) ($validated['directory_last_name'] ?? '');
        $fullName = trim($first . ' ' . $last);

        $extensionNumber = (string) ($validated['extension'] ?? '');

        // voicemail_password logic: default to extension unless complexity enabled
        $voicemailPassword = $validated['voicemail_password'] ?? $extensionNumber;
        if (function_exists('get_domain_setting') && get_domain_setting('password_complexity', $domain_uuid)) {
            // 4-digit pin
            $voicemailPassword = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        }

        // Defaults that should apply when values are omitted
        // (These match what you documented in descriptions)
        $defaults = [
            // required scoping
            'domain_uuid'  => (string) $domain->domain_uuid,

            // keep internal-only fields out of docs
            'password'     => function_exists('generate_password') ? generate_password() : bin2hex(random_bytes(12)),
            'user_context' => $validated['user_context'] ?? (string) $domain->domain_name,
            'accountcode'  => $validated['accountcode']  ?? (string) $domain->domain_name,

            'effective_caller_id_name'   => $fullName,
            'effective_caller_id_number' => $extensionNumber,

            // default booleans (DB stores as text)
            'enabled'                 => $boolText($validated['enabled'] ?? null, true),
            'do_not_disturb'          => $boolText($validated['do_not_disturb'] ?? null, false),
            'directory_visible'       => $boolText($validated['directory_visible'] ?? null, true),
            'directory_exten_visible' => $boolText($validated['directory_exten_visible'] ?? null, true),
            'user_record'             => $boolText($validated['user_record'] ?? null, false),
            'call_screen_enabled'     => $boolText($validated['call_screen_enabled'] ?? null, false),
            'suspended'               => ($validated['suspended'] ?? null), // this is boolean column in your list; keep as bool
        ];

        // Normalize fields that are TEXT booleans in DB
        // (These are *inputs* that can arrive as bool, but DB expects 'true'/'false')
        $normalized = [
            'sip_bypass_media' => $boolText($validated['sip_bypass_media'] ?? null, null),
            'force_ping'       => $boolText($validated['force_ping'] ?? null, null),

            // numeric columns in v_extensions
            'call_timeout'     => $toNumericString($validated['call_timeout'] ?? null),
            'sip_force_expires' => $toNumericString($validated['sip_force_expires'] ?? null),

            // voicemail booleans stored as text
            'voicemail_enabled'               => $boolText($validated['voicemail_enabled'] ?? null, true),
            'voicemail_local_after_email'     => $boolText($validated['voicemail_local_after_email'] ?? null, true),
            'voicemail_transcription_enabled' => $boolText($validated['voicemail_transcription_enabled'] ?? null, true),
            'voicemail_tutorial'              => $boolText($validated['voicemail_tutorial'] ?? null, true),
            'voicemail_recording_instructions' => $boolText($validated['voicemail_recording_instructions'] ?? null, true),
        ];

        // Build final inputs for v_extensions
        // Only include keys that actually exist in v_extensions
        $extensionInputs = array_merge(
            $validated,
            $defaults,
            $normalized,
            [
                // If you want these as TEXT in v_extensions, you can boolText them too
                // but your v_extensions doesn't have these as booleans anyway.
            ]
        );

        // Ensure voicemail_id defaulting is consistent when enabled
        $voicemailEnabled = filter_var($validated['voicemail_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($voicemailEnabled === null) $voicemailEnabled = true;

        $voicemailId = $validated['voicemail_id'] ?? null;
        if ($voicemailEnabled && ($voicemailId === null || $voicemailId === '')) {
            $voicemailId = $extensionNumber;
        }

        try {
            $extension = DB::transaction(function () use (
                $extensionInputs,
                $voicemailEnabled,
                $voicemailId,
                $voicemailPassword,
                $domain_uuid,
                $boolText
            ) {
            // 1) Create extension
                /** @var \App\Models\Extensions $ext */
                $ext = Extensions::create($extensionInputs)->fresh();

                // 2) Create voicemail ONLY if enabled
                if ($voicemailEnabled) {
                    $vmInputs = [
                        'domain_uuid'                      => $domain_uuid,
                        'voicemail_id'                     => (string) $voicemailId,
                        'voicemail_password'               => (string) $voicemailPassword,
                        'voicemail_mail_to'                => $extensionInputs['voicemail_mail_to'] ?? null,
                        'voicemail_sms_to'                 => $extensionInputs['voicemail_sms_to'] ?? null,
                        'voicemail_enabled'                => $boolText($extensionInputs['voicemail_enabled'] ?? 'true', true),

                        'voicemail_transcription_enabled'  => $boolText($extensionInputs['voicemail_transcription_enabled'] ?? 'true', true),
                        'voicemail_recording_instructions' => $boolText($extensionInputs['voicemail_recording_instructions'] ?? 'true', true),
                        'voicemail_file'                   => $extensionInputs['voicemail_file'] ?? 'attach',
                        'voicemail_local_after_email'      => $boolText($extensionInputs['voicemail_local_after_email'] ?? 'true', true),
                        'voicemail_tutorial'               => $boolText($extensionInputs['voicemail_tutorial'] ?? 'true', true),

                        'voicemail_description'            => $extensionInputs['voicemail_description'] ?? null,
                        // greeting_id is numeric in DB. Only include if provided.
                        'greeting_id'                      => $extensionInputs['greeting_id'] ?? null,
                    ];

                    Voicemails::create($vmInputs);
                }

                return $ext;
            });

            // Build API payload (you can reuse your helper formatting from index/show)
            $nameFormatted = trim(implode(' ', array_filter([
                $extension->directory_first_name,
                $extension->directory_last_name,
            ])));
            $nameFormatted = $nameFormatted !== '' ? $nameFormatted : null;

            $payload = new ExtensionData(
                extension_uuid: (string) $extension->extension_uuid,
                object: 'extension',
                domain_uuid: (string) $extension->domain_uuid,
                extension: (string) $extension->extension,

                // enabled stored as text in DB ('true'/'false')
                enabled: filter_var($extension->enabled, FILTER_VALIDATE_BOOLEAN) ? true : false,

                effective_caller_id_name: $extension->effective_caller_id_name,
                effective_caller_id_number: $extension->effective_caller_id_number,

                outbound_caller_id_number_e164: $extension->outbound_caller_id_number,
                outbound_caller_id_number_formatted: null,
                emergency_caller_id_number_e164: $extension->emergency_caller_id_number,

                directory_first_name: $extension->directory_first_name,
                directory_last_name: $extension->directory_last_name,
                name_formatted: $nameFormatted,

                directory_visible: filter_var($extension->directory_visible, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                directory_exten_visible: filter_var($extension->directory_exten_visible, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),

                email: $extension->email ?? null,

                do_not_disturb: filter_var($extension->do_not_disturb, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                user_record: filter_var($extension->user_record, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),

                suspended: $extension->suspended !== null ? (bool) $extension->suspended : null,

                description: $extension->description,

                forward_all_enabled: filter_var($extension->forward_all_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                forward_busy_enabled: filter_var($extension->forward_busy_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                forward_no_answer_enabled: filter_var($extension->forward_no_answer_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                forward_user_not_registered_enabled: filter_var($extension->forward_user_not_registered_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),

                follow_me_enabled: filter_var($extension->follow_me_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
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

    /**
     * Update an extension
     *
     * Updates an existing extension in the specified domain.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `extension_edit` permission.
     *
     * Notes:
     * - Fields are optional. If a field is omitted, the current value is unchanged.
     * - Voicemail behavior:
     *   - If `voicemail_enabled` is omitted, voicemail settings are unchanged.
     *   - If `voicemail_enabled` is true, a voicemail box is created (or updated) for this extension.
     *   - If `voicemail_enabled` is false, voicemail is disabled for this extension.
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
     *   "outbound_caller_id_number_formatted": null,
     *   "emergency_caller_id_number_e164": "+12135559876",
     *   "directory_first_name": "Front",
     *   "directory_last_name": "Desk",
     *   "name_formatted": "Front Desk",
     *   "directory_visible": true,
     *   "directory_exten_visible": true,
     *   "email": null,
     *   "do_not_disturb": false,
     *   "user_record": true,
     *   "suspended": false,
     *   "description": "Main reception phone",
     *   "forward_all_enabled": null,
     *   "forward_busy_enabled": null,
     *   "forward_no_answer_enabled": null,
     *   "forward_user_not_registered_enabled": null,
     *   "follow_me_enabled": null
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
     *     "message": "Extension not found.",
     *     "code": "resource_missing",
     *     "param": "extension_uuid"
     *   }
     * }
     */

    public function update(UpdateExtensionRequest $request, string $domain_uuid, string $extension_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        // Validate UUIDs early
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $extension_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid extension UUID.', 'invalid_request', 'extension_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $extension = QueryBuilder::for(Extensions::class)
            ->where('domain_uuid', $domain_uuid)
            ->where('extension_uuid', $extension_uuid)
            ->with([
                'advSettings',
                'voicemail' => function ($q) use ($domain_uuid) {
                    $q->where('domain_uuid', $domain_uuid);
                },
                'mobile_app' => function ($q) {
                    $q->select([
                        'mobile_app_user_uuid',
                        'extension_uuid',
                        'org_id',
                        'conn_id',
                        'user_id',
                        'status',
                        'exclude_from_stale_report',
                    ]);
                },
            ])
            ->first();

        if (! $extension) {
            throw new ApiException(404, 'invalid_request_error', 'Extension not found.', 'resource_missing', 'extension_uuid');
        }

        // --- helpers ---
        $boolText = static function ($value): ?string {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value ? 'true' : 'false';
            $b = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return $b === null ? null : ($b ? 'true' : 'false');
        };

        $boolNative = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        };

        $toNumericString = static function ($value): ?string {
            if ($value === null || $value === '') return null;
            return (string) $value;
        };

        $inputs = $request->validated();

        // Keep old values for cache flush (and voicemail lookup fallback)
        $oldExtNumber = (string) $extension->extension;
        $oldContext   = (string) ($extension->user_context ?? $domain->domain_name);

        // Determine “computed” values (only if needed)
        $newExtNumber = array_key_exists('extension', $inputs)
            ? (string) $inputs['extension']
            : $oldExtNumber;

        $first = array_key_exists('directory_first_name', $inputs)
            ? (string) $inputs['directory_first_name']
            : (string) ($extension->directory_first_name ?? '');

        $last = array_key_exists('directory_last_name', $inputs)
            ? (string) ($inputs['directory_last_name'] ?? '')
            : (string) ($extension->directory_last_name ?? '');

        $fullName = trim($first . ' ' . $last);

        // --- Build extension update payload ONLY from provided inputs ---
        $update = [];

        // plain text-ish fields (as stored)
        foreach (
            [
                'extension',
                'directory_first_name',
                'directory_last_name',
                'description',
                'max_registrations',
                'limit_max',
                'limit_destination',
                'toll_allow',
                'call_group',
                'hold_music',
                'cidr',
                'sip_force_contact',
                'mwi_account',
                'absolute_codec_string',
                'dial_string',
                'auth_acl',
                'outbound_caller_id_number',
                'outbound_caller_id_name',
                'emergency_caller_id_number',
                'emergency_caller_id_name',
                'user_context',
                'accountcode',
                'sip_bypass_media',
                'force_ping',
            ] as $key
        ) {
            if (array_key_exists($key, $inputs)) {
                $update[$key] = $inputs[$key];
            }
        }

        // numeric DB columns
        if (array_key_exists('call_timeout', $inputs)) {
            $update['call_timeout'] = $toNumericString($inputs['call_timeout']);
        }
        if (array_key_exists('sip_force_expires', $inputs)) {
            $update['sip_force_expires'] = $toNumericString($inputs['sip_force_expires']);
        }

        // TEXT boolean columns in v_extensions
        foreach (
            [
                'enabled',
                'do_not_disturb',
                'directory_visible',
                'directory_exten_visible',
                'user_record',
                'call_screen_enabled',
                'sip_bypass_media',
                'force_ping',
            ] as $key
        ) {
            if (array_key_exists($key, $inputs)) {
                $update[$key] = $boolText($inputs[$key]);
            }
        }

        // Controller-only derived fields (kept out of docs)
        $nameChanged = array_key_exists('directory_first_name', $inputs) || array_key_exists('directory_last_name', $inputs);
        $extChanged  = array_key_exists('extension', $inputs);

        if ($nameChanged) {
            $update['effective_caller_id_name'] = $fullName;
        }
        if ($extChanged) {
            $update['effective_caller_id_number'] = $newExtNumber;
        }

        // Voicemail touch semantics:
        // - if voicemail_enabled omitted => do not touch voicemail at all
        $touchVoicemail  = array_key_exists('voicemail_enabled', $inputs);
        $enableVoicemail = $touchVoicemail ? ($boolNative($inputs['voicemail_enabled']) === true) : null;

        // Any of these keys means we should update voicemail *if it exists* (even if voicemail_enabled omitted)
        $vmKeys = [
            'voicemail_mail_to',
            'voicemail_sms_to',
            'voicemail_file',
            'voicemail_local_after_email',
            'voicemail_transcription_enabled',
            'voicemail_description',
            'voicemail_destinations',
            'voicemail_password',
            'voicemail_tutorial',
            'voicemail_recording_instructions',
            'voicemail_id',
        ];
        $touchVoicemailBecauseFields = collect($vmKeys)->contains(fn($k) => array_key_exists($k, $inputs));

        try {
            DB::transaction(function () use (
                $domain_uuid,
                $domain,
                $extension,
                $inputs,
                $update,
                $touchVoicemail,
                $enableVoicemail,
                $touchVoicemailBecauseFields,
                $newExtNumber,
                $oldExtNumber,
                $boolText
            ) {
                // 1) Update extension (only if something to change)
                if (! empty($update)) {
                    $extension->fill($update);
                    $extension->save();
                }

                // 2) Advanced settings (suspended)
                if (array_key_exists('suspended', $inputs)) {
                    $extension->advSettings()->updateOrCreate(
                        ['extension_uuid' => $extension->extension_uuid],
                        ['suspended' => (bool) $inputs['suspended']]
                    );
                }

                // 3) Voicemail handling
                if (! $touchVoicemail && ! $touchVoicemailBecauseFields) {
                    // caller did not request voicemail changes
                } else {
                    // Find voicemail safely by domain + mailbox.
                    // Prefer existing mailbox ID = current extension number (old), but if extension changed, we may need to migrate to new.
                    $vm = Voicemails::query()
                        ->where('domain_uuid', $domain_uuid)
                        ->whereIn('voicemail_id', array_values(array_unique([$oldExtNumber, $newExtNumber])))
                        ->first();

                    // If caller explicitly disabled voicemail
                    if ($touchVoicemail && $enableVoicemail === false) {
                        if ($vm) {
                            $vm->voicemail_enabled = 'false';
                            $vm->save();
                        }
                    } else {
                        // Either enabling voicemail explicitly OR updating voicemail fields
                        // If we don't have a voicemail row yet, only create when voicemail_enabled is explicitly true.
                        if (! $vm && ! ($touchVoicemail && $enableVoicemail === true)) {
                            // They provided voicemail fields but voicemail doesn't exist and they didn't ask to create it.
                            // So: do nothing.
                        } else {
                            // Build vm update payload (only apply provided keys; plus creation defaults)
                            $vmData = [];

                            // If they explicitly enabled voicemail, ensure enabled true
                            if ($touchVoicemail && $enableVoicemail === true) {
                                $vmData['voicemail_enabled'] = 'true';
                            }

                            // If extension changed and caller didn't provide voicemail_id,
                            // keep mailbox aligned with extension number.
                            if (($touchVoicemail && $enableVoicemail === true) || array_key_exists('voicemail_id', $inputs)) {
                                $vmData['voicemail_id'] = (string) ($inputs['voicemail_id'] ?? $newExtNumber);
                            } elseif ($oldExtNumber !== $newExtNumber && $vm && $vm->voicemail_id === $oldExtNumber) {
                                $vmData['voicemail_id'] = $newExtNumber;
                            }

                            if (array_key_exists('voicemail_mail_to', $inputs)) {
                                $vmData['voicemail_mail_to'] = $inputs['voicemail_mail_to'];
                            }
                            if (array_key_exists('voicemail_sms_to', $inputs)) {
                                $vmData['voicemail_sms_to'] = $inputs['voicemail_sms_to'];
                            }
                            if (array_key_exists('voicemail_file', $inputs)) {
                                $vmData['voicemail_file'] = $inputs['voicemail_file'];
                            }
                            if (array_key_exists('voicemail_local_after_email', $inputs)) {
                                $vmData['voicemail_local_after_email'] = $boolText($inputs['voicemail_local_after_email']);
                            }
                            if (array_key_exists('voicemail_transcription_enabled', $inputs)) {
                                $vmData['voicemail_transcription_enabled'] = $boolText($inputs['voicemail_transcription_enabled']);
                            }
                            if (array_key_exists('voicemail_description', $inputs)) {
                                $vmData['voicemail_description'] = $inputs['voicemail_description'];
                            }
                            if (array_key_exists('voicemail_tutorial', $inputs)) {
                                $vmData['voicemail_tutorial'] = $boolText($inputs['voicemail_tutorial']);
                            }
                            if (array_key_exists('voicemail_recording_instructions', $inputs)) {
                                $vmData['voicemail_recording_instructions'] = $boolText($inputs['voicemail_recording_instructions']);
                            }

                            // password: if provided use; if creating + omitted, default to extension or random PIN (complexity)
                            if (array_key_exists('voicemail_password', $inputs)) {
                                $vmData['voicemail_password'] = (string) $inputs['voicemail_password'];
                            }

                            if (! $vm) {
                                // Create voicemail (only happens when voicemail_enabled was explicitly true)
                                $vmData['domain_uuid'] = $domain_uuid;

                                // Required-ish creation defaults if omitted
                                $vmData['voicemail_enabled'] = 'true';
                                $vmData['voicemail_id'] = $vmData['voicemail_id'] ?? $newExtNumber;
                                $vmData['voicemail_file'] = $vmData['voicemail_file'] ?? 'attach';
                                $vmData['voicemail_local_after_email'] = $vmData['voicemail_local_after_email'] ?? 'true';
                                $vmData['voicemail_transcription_enabled'] = $vmData['voicemail_transcription_enabled'] ?? 'true';
                                $vmData['voicemail_tutorial'] = $vmData['voicemail_tutorial'] ?? 'true';
                                $vmData['voicemail_recording_instructions'] = $vmData['voicemail_recording_instructions'] ?? 'true';

                                if (! isset($vmData['voicemail_password'])) {
                                    $pin = $newExtNumber;
                                    if (function_exists('get_domain_setting') && get_domain_setting('password_complexity', $domain_uuid)) {
                                        $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                                    }
                                    $vmData['voicemail_password'] = (string) $pin;
                                }

                                $vm = Voicemails::create($vmData);
                            } else {
                                if (! empty($vmData)) {
                                    $vm->fill($vmData);
                                    $vm->save();
                                }
                            }

                            // voicemail_destinations syncCopies (only if provided and voicemail exists)
                            if (
                                array_key_exists('voicemail_destinations', $inputs)
                                && is_array($inputs['voicemail_destinations'] ?? null)
                                && $vm
                                && method_exists($vm, 'syncCopies')
                            ) {
                                $vm->syncCopies($inputs['voicemail_destinations']);
                            }
                        }
                    }
                }

                // 4) Mobile app update + jobs
                if ($extension->mobile_app) {
                    if (array_key_exists('exclude_from_ringotel_stale_users', $inputs)) {
                        $extension->mobile_app->exclude_from_stale_report = (bool) $inputs['exclude_from_ringotel_stale_users'];
                        if ($extension->mobile_app->isDirty()) {
                            $extension->mobile_app->save();
                        }
                    }

                    // Build payload (prefer freshly computed values)
                    $suspended = array_key_exists('suspended', $inputs)
                        ? (bool) $inputs['suspended']
                        : (bool) ($extension->advSettings->suspended ?? false);

                    $mobileAppPayload = [
                        'user_id'   => $extension->mobile_app->user_id,
                        'org_id'    => $extension->mobile_app->org_id,
                        'conn_id'   => $extension->mobile_app->conn_id,
                        'status'    => $extension->mobile_app->status,
                        'no_email'  => $extension->mobile_app->no_email ?? true,

                        'name'      => $update['effective_caller_id_name'] ?? $extension->effective_caller_id_name ?? '',
                        'email'     => $inputs['voicemail_mail_to'] ?? ($extension->voicemail->voicemail_mail_to ?? ''),
                        'ext'       => $newExtNumber,
                        'password'  => $extension->password,
                    ];

                    UpdateAppSettings::dispatch($mobileAppPayload)->onQueue('default');

                    if ($suspended && (int) $extension->mobile_app->status !== -1) {
                        SuspendAppUser::dispatch($mobileAppPayload)->onQueue('default');
                    }
                }
            });

            // Clear FusionPBX directory cache (old + new)
            $newContext = array_key_exists('user_context', $inputs)
                ? (string) $inputs['user_context']
                : (string) ($extension->user_context ?? $oldContext);

            FusionCache::clear("directory:{$oldExtNumber}@{$oldContext}");
            FusionCache::clear("directory:{$newExtNumber}@{$newContext}");

            // Return fresh resource
            $fresh = QueryBuilder::for(Extensions::class)
                ->where('domain_uuid', $domain_uuid)
                ->where('extension_uuid', $extension_uuid)
                ->with([
                    'advSettings',
                    'voicemail' => function ($q) use ($domain_uuid) {
                        $q->where('domain_uuid', $domain_uuid);
                    },
                ])
                ->firstOrFail();

            $firstName = $fresh->directory_first_name ?? null;
            $lastName  = $fresh->directory_last_name ?? null;
            $nameFormatted = trim(implode(' ', array_filter([$firstName, $lastName])));
            $nameFormatted = $nameFormatted !== '' ? $nameFormatted : null;

            $payload = new ExtensionData(
                extension_uuid: (string) $fresh->extension_uuid,
                object: 'extension',
                domain_uuid: (string) $fresh->domain_uuid,
                extension: (string) $fresh->extension,

                enabled: (bool) filter_var($fresh->enabled, FILTER_VALIDATE_BOOLEAN),

                effective_caller_id_name: $fresh->effective_caller_id_name,
                effective_caller_id_number: $fresh->effective_caller_id_number,

                outbound_caller_id_number_e164: $fresh->outbound_caller_id_number,
                outbound_caller_id_number_formatted: null,
                emergency_caller_id_number_e164: $fresh->emergency_caller_id_number,

                directory_first_name: $firstName,
                directory_last_name: $lastName,
                name_formatted: $nameFormatted,

                directory_visible: filter_var($fresh->directory_visible, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                directory_exten_visible: filter_var($fresh->directory_exten_visible, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),

                email: $fresh->email ?? null,

                do_not_disturb: filter_var($fresh->do_not_disturb, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                user_record: filter_var($fresh->user_record, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),

                suspended: (bool) ($fresh->advSettings->suspended ?? false),

                description: $fresh->description,

                forward_all_enabled: filter_var($fresh->forward_all_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                forward_busy_enabled: filter_var($fresh->forward_busy_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                forward_no_answer_enabled: filter_var($fresh->forward_no_answer_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                forward_user_not_registered_enabled: filter_var($fresh->forward_user_not_registered_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),

                follow_me_enabled: filter_var($fresh->follow_me_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            );

            return response()->json($payload->toArray(), 200);
        } catch (\Exception $e) {

            logger('API Extension update QueryException: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        } catch (\Throwable $e) {
            logger('API Extension update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }


    /**
     * Delete an extension
     *
     * Deletes an extension within the specified domain.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `extension_delete` permission.
     *
     * @group Extensions
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam extension_uuid string required The extension UUID. Example: d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b
     *
     * @response 200 scenario="Success" {
     *   "object": "extension",
     *   "extension_uuid": "d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b",
     *   "deleted": true
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

    public function destroy(Request $request, string $domain_uuid, string $extension_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        // Validate UUIDs early
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $extension_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid extension UUID.', 'invalid_request', 'extension_uuid');
        }

        // Domain must exist
        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        // Load the extension + related records we need to delete
        $extension = Extensions::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('extension_uuid', $extension_uuid)
            ->with([
                'followMe.followMeDestinations',
                'extension_users',
                'mobile_app',
                'advSettings',
                'deviceLines' => function ($q) use ($domain_uuid) {
                    $q->where('domain_uuid', $domain_uuid)
                        ->select('device_line_uuid', 'device_uuid', 'auth_id', 'domain_uuid', 'password');
                },
                'voicemail' => function ($q) use ($domain_uuid) {
                    $q->where('domain_uuid', $domain_uuid);
                },
            ])
            ->first();

        if (! $extension) {
            throw new ApiException(404, 'invalid_request_error', 'Extension not found.', 'resource_missing', 'extension_uuid');
        }

        try {
            DB::transaction(function () use ($extension, $domain_uuid) {

                // 1) Delete voicemail (if present)
                if ($extension->relationLoaded('voicemail') && $extension->voicemail) {
                    $extension->voicemail->delete();
                }

                // 2) Delete follow-me + destinations (if present)
                if ($extension->relationLoaded('followMe') && $extension->followMe) {
                    $extension->followMe->followMeDestinations()->delete();
                    $extension->followMe->delete();
                }

                // 3) Delete extension users (if present)
                if ($extension->relationLoaded('extension_users') && $extension->extension_users) {
                    $extension->extension_users()->delete();
                }

                // 4) Unassign device lines only from this domain
                $extension->deviceLines()
                    ->where('domain_uuid', $domain_uuid)
                    ->delete();

                // 5) Mobile app: dispatch delete job, then delete local row (if present)
                if ($extension->relationLoaded('mobile_app') && $extension->mobile_app) {
                    $mobileAppPayload = [
                        'mobile_app_user_uuid' => $extension->mobile_app->mobile_app_user_uuid ?? null,
                        'user_id'              => $extension->mobile_app->user_id ?? null,
                        'org_id'               => $extension->mobile_app->org_id ?? null,
                    ];

                    DeleteAppUser::dispatch($mobileAppPayload)->onQueue('default');

                    // Remove local record too (prevents orphan rows)
                    $extension->mobile_app()->delete();
                }

                // 6) Delete advanced settings
                if ($extension->relationLoaded('advSettings') && $extension->advSettings) {
                    $extension->advSettings()->delete();
                }

                // 7) Delete the extension itself
                $extension->delete();
            });

            // Clear FusionPBX cache for this extension (after delete is OK; we still have values)
            if (! empty($extension->extension) && ! empty($extension->user_context)) {
                FusionCache::clear("directory:" . $extension->extension . "@" . $extension->user_context);
            }

            $payload = DeletedResponseData::from([
                'uuid'      => (string) $extension_uuid,
                'object'  => 'extension',
                'deleted' => true,
            ]);

            return response()->json($payload->toArray(), 200);
        } catch (\Exception $e) {
            logger('API Extension delete QueryException: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        } catch (\Throwable $e) {
            logger('API Extension delete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }
}
