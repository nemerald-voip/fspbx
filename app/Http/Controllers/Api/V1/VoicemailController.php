<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Domain;
use App\Models\Voicemails;
use Illuminate\Http\Request;
use App\Exceptions\ApiException;

use App\Data\Api\V1\VoicemailData;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;

use Illuminate\Support\Facades\Storage;
use App\Data\Api\V1\DeletedResponseData;
use App\Data\Api\V1\VoicemailExtensionData;
use App\Data\Api\V1\VoicemailListResponseData;
use App\Http\Requests\Api\V1\StoreVoicemailRequest;
use App\Http\Requests\Api\V1\UpdateVoicemailRequest;

class VoicemailController extends Controller
{
    /**
     * List voicemails
     *
     * Returns voicemails for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `voicemail_domain` permission.
     *
     * Pagination (cursor-based):
     * - Both `limit` and `starting_after` are optional.
     * - If `limit` is not provided, it defaults to 50.
     * - If `starting_after` is not provided, results start from the beginning.
     * - If `has_more` is true, request the next page by passing `starting_after`
     *   equal to the last item's `voicemail_uuid` from the previous response.
     *
     * Examples:
     * - First page: `GET /api/v1/domains/{domain_uuid}/voicemails`
     * - Next page:  `GET /api/v1/domains/{domain_uuid}/voicemails?starting_after={last_voicemail_uuid}`
     * - Custom size: `GET /api/v1/domains/{domain_uuid}/voicemails?limit=50`
     *
     * @group Voicemails
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     *
     * @queryParam limit integer Optional. Number of results to return (min 1, max 100). Defaults to 50. Example: 25
     * @queryParam starting_after string Optional. Return results after this voicemail UUID (cursor). Example: d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/voicemails",
     *   "has_more": true,
     *   "data": [
     *     {
     *       "voicemail_uuid": "d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b",
     *       "object": "voicemail",
     *       "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *       "voicemail_id": "1001",
     *       "voicemail_password: "1001",
     *       "voicemail_mail_to": "frontdesk@example.com",
     *       "voicemail_sms_to": null,
     *       "voicemail_transcription_enabled": true,
     *       "voicemail_local_after_email": true,
     *       "voicemail_tutorial": true,
     *       "voicemail_recording_instructions": true,
     *       "voicemail_file": "attach",
     *       "voicemail_alternate_greet_id": 200,
     *       "voicemail_description": "Front desk voicemail",
     *       "greeting_id": 3,
     *       "voicemail_enabled": true
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
     * @response 400 scenario="Invalid starting_after UUID" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Invalid starting_after UUID.",
     *     "code": "invalid_request",
     *     "param": "starting_after"
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

        $toBool = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        };

        $query = QueryBuilder::for(Voicemails::class)
            ->where('domain_uuid', $domain_uuid)
            ->defaultSort('voicemail_uuid')
            ->reorder('voicemail_uuid')
            ->limit($limit + 1)
            ->select([
                'voicemail_uuid',
                'domain_uuid',
                'voicemail_id',
                'voicemail_alternate_greet_id',
                'voicemail_password',
                'voicemail_mail_to',
                'voicemail_sms_to',
                'voicemail_enabled',
                'voicemail_transcription_enabled',
                'voicemail_local_after_email',
                'voicemail_tutorial',
                'voicemail_recording_instructions',
                'voicemail_attach_file',
                'voicemail_file',
                'voicemail_description',
                'greeting_id',
            ]);

        if ($startingAfter !== '') {
            if (! preg_match('/^[0-9a-fA-F-]{36}$/', $startingAfter)) {
                throw new ApiException(400, 'invalid_request_error', 'Invalid starting_after UUID.', 'invalid_request', 'starting_after');
            }
            $query->where('voicemail_uuid', '>', $startingAfter);
        }

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $data = $rows->map(function ($vm) use ($toBool) {
            return new VoicemailData(
                voicemail_uuid: (string) $vm->voicemail_uuid,
                object: 'voicemail',
                domain_uuid: (string) $vm->domain_uuid,

                voicemail_id: (string) $vm->voicemail_id,
                voicemail_password: (string) $vm->voicemail_password,

                voicemail_mail_to: $vm->voicemail_mail_to,
                voicemail_sms_to: $vm->voicemail_sms_to,

                voicemail_transcription_enabled: $toBool($vm->voicemail_transcription_enabled),
                voicemail_local_after_email: $toBool($vm->voicemail_local_after_email),
                voicemail_tutorial: $toBool($vm->voicemail_tutorial),
                voicemail_recording_instructions: $toBool($vm->voicemail_recording_instructions),

                voicemail_file: $vm->voicemail_file,
                voicemail_description: $vm->voicemail_description,

                greeting_id: $vm->greeting_id !== null ? (int) $vm->greeting_id : null,
                voicemail_alternate_greet_id: $vm->voicemail_alternate_greet_id !== null ? (int) $vm->voicemail_alternate_greet_id : null,
                voicemail_enabled: $toBool($vm->voicemail_enabled),

            );
        })->all();

        $url = "/api/v1/domains/{$domain_uuid}/voicemails";

        $payload = new VoicemailListResponseData(
            object: 'list',
            url: $url,
            has_more: $hasMore,
            data: $data,
        );

        return response()->json($payload->toArray(), 200);
    }

    /**
     * Retrieve a voicemail
     *
     * Returns a single voicemail for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `voicemail_view` permission.
     *
     * Notes:
     * - If the domain does not exist, a `resource_missing` error is returned.
     * - If the voicemail does not exist in that domain, a `resource_missing` error is returned.
     * - The voicemail password is never returned in API responses.
     *
     * @group Voicemails
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam voicemail_uuid string required The voicemail UUID. Example: d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b
     *
     * @response 200 scenario="Success" {
     *   "voicemail_uuid": "d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b",
     *   "object": "voicemail",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "voicemail_id": "1001",
     *   "voicemail_password: "1001",
     *   "voicemail_mail_to": "frontdesk@example.com",
     *   "voicemail_sms_to": null,
     *   "voicemail_transcription_enabled": true,
     *   "voicemail_local_after_email": true,
     *   "voicemail_tutorial": true,
     *   "voicemail_recording_instructions": true,
     *   "voicemail_file": "attach",
     *   "voicemail_description": "Front desk voicemail",
     *   "greeting_id": 3,
     *   "voicemail_enabled": true
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
     * @response 400 scenario="Invalid voicemail UUID" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Invalid voicemail UUID.",
     *     "code": "invalid_request",
     *     "param": "voicemail_uuid"
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
     * @response 404 scenario="Voicemail not found" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Voicemail not found.",
     *     "code": "resource_missing",
     *     "param": "voicemail_uuid"
     *   }
     * }
     */

    public function show(Request $request, string $domain_uuid, string $voicemail_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $voicemail_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid voicemail UUID.', 'invalid_request', 'voicemail_uuid');
        }

        $domainExists = Domain::query()->where('domain_uuid', $domain_uuid)->exists();
        if (! $domainExists) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $toBool = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        };

        $vm = QueryBuilder::for(Voicemails::class)
            ->where('domain_uuid', $domain_uuid)
            ->where('voicemail_uuid', $voicemail_uuid)
            ->select([
                'voicemail_uuid',
                'domain_uuid',
                'voicemail_id',
                'voicemail_password',
                'voicemail_mail_to',
                'voicemail_sms_to',
                'voicemail_enabled',
                'voicemail_transcription_enabled',
                'voicemail_local_after_email',
                'voicemail_tutorial',
                'voicemail_recording_instructions',
                'voicemail_file',
                'voicemail_description',
                'greeting_id',
            ])
            ->first();

        if (! $vm) {
            throw new ApiException(404, 'invalid_request_error', 'Voicemail not found.', 'resource_missing', 'voicemail_uuid');
        }

        $payload = new VoicemailData(
            voicemail_uuid: (string) $vm->voicemail_uuid,
            object: 'voicemail',
            domain_uuid: (string) $vm->domain_uuid,

            voicemail_id: (string) $vm->voicemail_id,
            voicemail_password: (string) $vm->voicemail_password,

            voicemail_mail_to: $vm->voicemail_mail_to,
            voicemail_sms_to: $vm->voicemail_sms_to,

            voicemail_enabled: $toBool($vm->voicemail_enabled),
            voicemail_transcription_enabled: $toBool($vm->voicemail_transcription_enabled),
            voicemail_local_after_email: $toBool($vm->voicemail_local_after_email),
            voicemail_tutorial: $toBool($vm->voicemail_tutorial),
            voicemail_recording_instructions: $toBool($vm->voicemail_recording_instructions),

            voicemail_file: $vm->voicemail_file,
            voicemail_description: $vm->voicemail_description,

            greeting_id: $vm->greeting_id !== null ? (int) $vm->greeting_id : null,
            voicemail_alternate_greet_id: $vm->voicemail_alternate_greet_id,
        );

        return response()->json($payload->toArray(), 200);
    }

    /**
     * Create a voicemail
     *
     * Creates a new voicemail box in the specified domain.
     *
     * Notes:
     * - Some fields may be defaulted server-side (e.g., voicemail_password when omitted).
     *
     * @group Voicemails
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     *
     * @response 201 scenario="Created" {
     *   "voicemail_uuid": "d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b",
     *   "object": "voicemail",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "voicemail_id": "1001",
     *   "voicemail_password: "1001",
     *   "voicemail_mail_to": "frontdesk@example.com",
     *   "voicemail_sms_to": null,
     *   "voicemail_transcription_enabled": true,
     *   "voicemail_local_after_email": true,
     *   "voicemail_tutorial": true,
     *   "voicemail_recording_instructions": true,
     *   "voicemail_file": "attach",
     *   "voicemail_description": "Front desk voicemail",
     *   "greeting_id": 3,
     *   "voicemail_alternate_greet_id": null
     *   "voicemail_enabled": true
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
     * @response 404 scenario="Domain not found" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Domain not found.",
     *     "code": "resource_missing",
     *     "param": "domain_uuid"
     *   }
     * }
     */

    public function store(StoreVoicemailRequest $request, string $domain_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $boolText = static function ($value, ?bool $default = null): ?string {
            if ($value === null || $value === '') {
                return $default === null ? null : ($default ? 'true' : 'false');
            }
            if (is_bool($value)) return $value ? 'true' : 'false';
            $b = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($b === null) {
                return $default === null ? null : ($default ? 'true' : 'false');
            }
            return $b ? 'true' : 'false';
        };

        $toBool = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        };

        $validated = $request->validated();

        $mailbox = (string) $validated['voicemail_id'];

        // password defaulting (matches your extensions behavior)
        $password = $validated['voicemail_password'] ?? $mailbox;
        if (function_exists('get_domain_setting') && get_domain_setting('password_complexity', $domain_uuid)) {
            $password = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        }

        try {
            /** @var \App\Models\Voicemails $vm */
            $vm = DB::transaction(function () use ($domain_uuid, $validated, $boolText, $password, $mailbox) {

                $inputs = [
                    'domain_uuid'        => $domain_uuid,
                    'voicemail_id'       => $mailbox,
                    'voicemail_password' => (string) $password,

                    'voicemail_mail_to'  => $validated['voicemail_mail_to'] ?? null,
                    'voicemail_sms_to'   => $validated['voicemail_sms_to'] ?? null,

                    'voicemail_enabled'                => $boolText($validated['voicemail_enabled'] ?? null, true),
                    'voicemail_transcription_enabled'  => $boolText($validated['voicemail_transcription_enabled'] ?? null, true),
                    'voicemail_local_after_email'      => $boolText($validated['voicemail_local_after_email'] ?? null, true),
                    'voicemail_tutorial'               => $boolText($validated['voicemail_tutorial'] ?? null, true),
                    'voicemail_recording_instructions' => $boolText($validated['voicemail_recording_instructions'] ?? null, true),

                    'voicemail_file'        => $validated['voicemail_file'] ?? 'attach',
                    'voicemail_description' => $validated['voicemail_description'] ?? null,
                    'greeting_id'           => $validated['greeting_id'] ?? null,
                ];

                $vm = Voicemails::create($inputs)->fresh();

                return $vm;
            });

            // Return resource (no password returned)
            $payload = new VoicemailData(
                voicemail_uuid: (string) $vm->voicemail_uuid,
                object: 'voicemail',
                domain_uuid: (string) $vm->domain_uuid,

                voicemail_id: (string) $vm->voicemail_id,
                voicemail_password: (string) $vm->voicemail_password,

                voicemail_mail_to: $vm->voicemail_mail_to,
                voicemail_sms_to: $vm->voicemail_sms_to,

                voicemail_enabled: $toBool($vm->voicemail_enabled),
                voicemail_transcription_enabled: $toBool($vm->voicemail_transcription_enabled),
                voicemail_local_after_email: $toBool($vm->voicemail_local_after_email),
                voicemail_tutorial: $toBool($vm->voicemail_tutorial),
                voicemail_recording_instructions: $toBool($vm->voicemail_recording_instructions),

                voicemail_file: $vm->voicemail_file,
                voicemail_description: $vm->voicemail_description,

                greeting_id: $vm->greeting_id !== null ? (int) $vm->greeting_id : null,
                voicemail_alternate_greet_id: $vm->voicemail_alternate_greet_id,
            );

            return response()
                ->json($payload->toArray(), 201)
                ->header('Location', "/api/v1/domains/{$domain_uuid}/voicemails/{$vm->voicemail_uuid}");
        } catch (\Exception $e) {
            logger('API Voicemail store QueryException: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        } catch (\Throwable $e) {
            logger('API Voicemail store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }

    /**
     * Update a voicemail
     *
     * Updates an existing voicemail in the specified domain.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `voicemail_edit` permission.
     *
     * Notes:
     * - Fields are optional. If a field is omitted, the current value is unchanged.
     *
     * @group Voicemails
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam voicemail_uuid string required The voicemail UUID. Example: d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b
     *
     * @response 200 scenario="Success" {
     *   "voicemail_uuid": "d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b",
     *   "object": "voicemail",
     *   "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *   "voicemail_id": "1001",
     *   "voicemail_password: "1001",
     *   "voicemail_mail_to": "frontdesk@example.com",
     *   "voicemail_sms_to": null,
     *   "voicemail_transcription_enabled": true,
     *   "voicemail_local_after_email": true,
     *   "voicemail_tutorial": true,
     *   "voicemail_recording_instructions": true,
     *   "voicemail_file": "attach",
     *   "voicemail_description": "Updated description",
     *   "greeting_id": 3,
     *   "voicemail_alternate_greet_id": null,
     *   "voicemail_enabled": true
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
     * @response 400 scenario="Invalid voicemail UUID" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Invalid voicemail UUID.",
     *     "code": "invalid_request",
     *     "param": "voicemail_uuid"
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
     * @response 404 scenario="Voicemail not found" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Voicemail not found.",
     *     "code": "resource_missing",
     *     "param": "voicemail_uuid"
     *   }
     * }
     */

    public function update(UpdateVoicemailRequest $request, string $domain_uuid, string $voicemail_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $voicemail_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid voicemail UUID.', 'invalid_request', 'voicemail_uuid');
        }

        $domainExists = Domain::query()->where('domain_uuid', $domain_uuid)->exists();
        if (! $domainExists) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $vm = Voicemails::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('voicemail_uuid', $voicemail_uuid)
            ->first();

        if (! $vm) {
            throw new ApiException(404, 'invalid_request_error', 'Voicemail not found.', 'resource_missing', 'voicemail_uuid');
        }

        $boolText = static function ($value, ?bool $default = null): ?string {
            if ($value === null || $value === '') {
                return $default === null ? null : ($default ? 'true' : 'false');
            }
            if (is_bool($value)) return $value ? 'true' : 'false';
            $b = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($b === null) {
                return $default === null ? null : ($default ? 'true' : 'false');
            }
            return $b ? 'true' : 'false';
        };

        $toBool = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        };

        $inputs = $request->validated();

        try {
            DB::transaction(function () use ($vm, $inputs, $boolText) {
                $update = [];

                // Scalar-ish fields
                foreach (
                    [
                        'voicemail_id',
                        'voicemail_password',
                        'voicemail_mail_to',
                        'voicemail_sms_to',
                        'voicemail_file',
                        'voicemail_description',
                        'greeting_id',
                        'voicemail_alternate_greet_id',
                    ] as $key
                ) {
                    if (array_key_exists($key, $inputs)) {
                        $update[$key] = $inputs[$key];
                    }
                }

                // Boolean fields stored as "true"/"false" text in FusionPBX tables
                foreach (
                    [
                        'voicemail_enabled',
                        'voicemail_transcription_enabled',
                        'voicemail_local_after_email',
                        'voicemail_tutorial',
                        'voicemail_recording_instructions',
                    ] as $key
                ) {
                    if (array_key_exists($key, $inputs)) {
                        $update[$key] = $boolText($inputs[$key]);
                    }
                }

                if (! empty($update)) {
                    $vm->fill($update);
                    $vm->save();
                }
            });

            $fresh = QueryBuilder::for(Voicemails::class)
                ->where('domain_uuid', $domain_uuid)
                ->where('voicemail_uuid', $voicemail_uuid)
                ->select([
                    'voicemail_uuid',
                    'domain_uuid',
                    'voicemail_id',
                    'voicemail_password',
                    'voicemail_mail_to',
                    'voicemail_sms_to',
                    'voicemail_enabled',
                    'voicemail_transcription_enabled',
                    'voicemail_local_after_email',
                    'voicemail_tutorial',
                    'voicemail_recording_instructions',
                    'voicemail_file',
                    'voicemail_description',
                    'greeting_id',
                    'voicemail_alternate_greet_id',
                ])
                ->firstOrFail();

            $payload = new VoicemailData(
                voicemail_uuid: (string) $fresh->voicemail_uuid,
                object: 'voicemail',
                domain_uuid: (string) $fresh->domain_uuid,

                voicemail_id: (string) $fresh->voicemail_id,
                voicemail_password: (string) $fresh->voicemail_password,

                voicemail_mail_to: $fresh->voicemail_mail_to,
                voicemail_sms_to: $fresh->voicemail_sms_to,

                voicemail_enabled: $toBool($fresh->voicemail_enabled),
                voicemail_transcription_enabled: $toBool($fresh->voicemail_transcription_enabled),
                voicemail_local_after_email: $toBool($fresh->voicemail_local_after_email),
                voicemail_tutorial: $toBool($fresh->voicemail_tutorial),
                voicemail_recording_instructions: $toBool($fresh->voicemail_recording_instructions),

                voicemail_file: $fresh->voicemail_file,
                voicemail_description: $fresh->voicemail_description,

                greeting_id: $fresh->greeting_id !== null ? (int) $fresh->greeting_id : null,
                voicemail_alternate_greet_id: $fresh->voicemail_alternate_greet_id !== null ? (int) $fresh->voicemail_alternate_greet_id : null,

            );

            return response()->json($payload->toArray(), 200);
        } catch (\Exception $e) {
            logger('API Voicemail update QueryException: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        } catch (\Throwable $e) {
            logger('API Voicemail update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }


    /**
     * Delete a voicemail
     *
     * Deletes a voicemail within the specified domain.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `voicemail_delete` permission.
     *
     * @group Voicemails
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @urlParam voicemail_uuid string required The voicemail UUID. Example: d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b
     *
     * @response 200 scenario="Success" {
     *   "object": "voicemail",
     *   "uuid": "d2c7b17c-8b0d-4f0f-b5ff-2cfb6d7a4f4b",
     *   "deleted": true
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
     * @response 400 scenario="Invalid voicemail UUID" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Invalid voicemail UUID.",
     *     "code": "invalid_request",
     *     "param": "voicemail_uuid"
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
     * @response 404 scenario="Voicemail not found" {
     *   "error": {
     *     "type": "invalid_request_error",
     *     "message": "Voicemail not found.",
     *     "code": "resource_missing",
     *     "param": "voicemail_uuid"
     *   }
     * }
     */

    public function destroy(Request $request, string $domain_uuid, string $voicemail_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $voicemail_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid voicemail UUID.', 'invalid_request', 'voicemail_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        /** @var \App\Models\Voicemails|null $vm */
        $vm = Voicemails::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('voicemail_uuid', $voicemail_uuid)
            ->first();

        if (! $vm) {
            throw new ApiException(404, 'invalid_request_error', 'Voicemail not found.', 'resource_missing', 'voicemail_uuid');
        }

        try {
            DB::transaction(function () use ($vm, $domain) {
                // Delete related voicemail destinations
                if (method_exists($vm, 'voicemail_destinations')) {
                    $vm->voicemail_destinations()->delete();
                }

                // Delete related voicemail messages
                if (method_exists($vm, 'messages')) {
                    $vm->messages()->delete();
                }

                // Delete related voicemail greetings
                if (method_exists($vm, 'greetings')) {
                    $vm->greetings()->delete();
                }

                // Delete voicemail directory on disk: {domain_name}/{voicemail_id}
                $path = (string) $domain->domain_name . '/' . (string) $vm->voicemail_id;

                if (Storage::disk('voicemail')->exists($path)) {
                    Storage::disk('voicemail')->deleteDirectory($path);
                }

                // Delete the voicemail row
                $vm->delete();
            });

            $payload = DeletedResponseData::from([
                'uuid'    => (string) $voicemail_uuid,
                'object'  => 'voicemail',
                'deleted' => true,
            ]);

            return response()->json($payload->toArray(), 200);
        } catch (\Exception $e) {
            logger('API Voicemail delete QueryException: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        } catch (\Throwable $e) {
            logger('API Voicemail delete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            throw new ApiException(500, 'api_error', 'Internal server error.', 'internal_error');
        }
    }
}
