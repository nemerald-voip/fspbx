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
}
