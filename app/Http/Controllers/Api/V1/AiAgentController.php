<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\V1\AiAgentData;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\Domain;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

class AiAgentController extends Controller
{
    /**
     * List AI agents
     *
     * Returns AI agents for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `ai_agent_view` permission.
     *
     * Pagination (cursor-based):
     * - Both `limit` and `starting_after` are optional.
     * - If `limit` is not provided, it defaults to 50.
     * - If `starting_after` is not provided, results start from the beginning.
     * - If `has_more` is true, request the next page by passing `starting_after`
     *   equal to the last item's `ai_agent_uuid` from the previous response.
     *
     * @group AI Agents
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b
     * @queryParam limit integer Optional. Number of results to return (min 1, max 100). Defaults to 50. Example: 50
     * @queryParam starting_after string Optional. Return results after this AI agent UUID (cursor). Example: c0ec8113-aa15-40ac-8437-47185dd9dcf4
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b/ai-agents",
     *   "has_more": false,
     *   "data": [
     *     {
     *       "ai_agent_uuid": "c0ec8113-aa15-40ac-8437-47185dd9dcf4",
     *       "object": "ai_agent",
     *       "domain_uuid": "4018f7a3-8e0a-47bb-9f4f-04b1313e0e1b",
     *       "agent_name": "Sales Receptionist",
     *       "agent_extension": "9250",
     *       "agent_enabled": true,
     *       "description": "Handles inbound sales enquiries"
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid starting_after UUID" {"error":{"type":"invalid_request_error","message":"Invalid starting_after UUID.","code":"invalid_request","param":"starting_after"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
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

        $textBool = static function ($value): ?bool {
            // v_ai_agents stores booleans as text ('true'/'false')
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;
            if (is_numeric($value)) return ((int) $value) === 1;
            $v = strtolower(trim((string) $value));
            if (in_array($v, ['true', 't', '1', 'yes', 'y', 'on'], true)) return true;
            if (in_array($v, ['false', 'f', '0', 'no', 'n', 'off'], true)) return false;
            return null;
        };

        $query = QueryBuilder::for(AiAgent::class)
            ->where('domain_uuid', $domain_uuid)
            ->defaultSort('ai_agent_uuid')
            ->reorder('ai_agent_uuid')
            ->limit($limit + 1)
            ->select([
                'ai_agent_uuid',
                'domain_uuid',
                'agent_name',
                'agent_extension',
                'agent_enabled',
                'description',
            ]);

        if ($startingAfter !== '') {
            if (! preg_match('/^[0-9a-fA-F-]{36}$/', $startingAfter)) {
                throw new ApiException(400, 'invalid_request_error', 'Invalid starting_after UUID.', 'invalid_request', 'starting_after');
            }
            $query->where('ai_agent_uuid', '>', $startingAfter);
        }

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit);

        $data = $rows->map(function ($a) use ($textBool) {
            return new AiAgentData(
                ai_agent_uuid: (string) $a->ai_agent_uuid,
                object: 'ai_agent',
                domain_uuid: (string) $a->domain_uuid,

                agent_name: (string) $a->agent_name,
                agent_extension: (string) $a->agent_extension,

                agent_enabled: $textBool($a->agent_enabled),

                description: $a->description,
            );
        });

        return response()->json([
            'object'   => 'list',
            'url'      => "/api/v1/domains/{$domain_uuid}/ai-agents",
            'has_more' => $hasMore,
            'data'     => $data,
        ], 200);
    }

    /**
     * Retrieve an AI agent
     *
     * Returns a single AI agent for the specified domain the caller is allowed to access.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `ai_agent_view` permission.
     *
     * @group AI Agents
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 7d58342b-2b29-4dcf-92d6-e9a9e002a4e5
     * @urlParam ai_agent_uuid string required The AI agent UUID. Example: 40aec3e8-a572-40da-954b-ddf6a8a65324
     *
     * @response 200 scenario="Success" {
     *   "ai_agent_uuid": "40aec3e8-a572-40da-954b-ddf6a8a65324",
     *   "object": "ai_agent",
     *   "domain_uuid": "7d58342b-2b29-4dcf-92d6-e9a9e002a4e5",
     *   "agent_name": "Sales Receptionist",
     *   "agent_extension": "9250",
     *   "agent_enabled": true,
     *   "description": "Handles inbound sales enquiries",
     *   "voice_id": "21m00Tcm4TlvDq8ikWAM",
     *   "language": "en",
     *   "first_message": "Hello, how can I help?",
     *   "system_prompt": "You are a helpful receptionist...",
     *   "elevenlabs_agent_id": "abc123",
     *   "elevenlabs_phone_number_id": "pn_xyz"
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid AI agent UUID" {"error":{"type":"invalid_request_error","message":"Invalid AI agent UUID.","code":"invalid_request","param":"ai_agent_uuid"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     * @response 404 scenario="AI agent not found" {"error":{"type":"invalid_request_error","message":"AI agent not found.","code":"resource_missing","param":"ai_agent_uuid"}}
     */
    public function show(Request $request, string $domain_uuid, string $ai_agent_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $ai_agent_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid AI agent UUID.', 'invalid_request', 'ai_agent_uuid');
        }

        $domainExists = Domain::query()->where('domain_uuid', $domain_uuid)->exists();
        if (! $domainExists) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        $textBool = static function ($value): ?bool {
            if ($value === null || $value === '') return null;
            if (is_bool($value)) return $value;
            if (is_numeric($value)) return ((int) $value) === 1;
            $v = strtolower(trim((string) $value));
            if (in_array($v, ['true', 't', '1', 'yes', 'y', 'on'], true)) return true;
            if (in_array($v, ['false', 'f', '0', 'no', 'n', 'off'], true)) return false;
            return null;
        };

        $agent = AiAgent::query()
            ->where('domain_uuid', $domain_uuid)
            ->where('ai_agent_uuid', $ai_agent_uuid)
            ->select([
                'ai_agent_uuid',
                'domain_uuid',
                'agent_name',
                'agent_extension',
                'agent_enabled',
                'description',
                'voice_id',
                'language',
                'first_message',
                'system_prompt',
                'elevenlabs_agent_id',
                'elevenlabs_phone_number_id',
            ])
            ->first();

        if (! $agent) {
            throw new ApiException(404, 'invalid_request_error', 'AI agent not found.', 'resource_missing', 'ai_agent_uuid');
        }

        $payload = new AiAgentData(
            ai_agent_uuid: (string) $agent->ai_agent_uuid,
            object: 'ai_agent',
            domain_uuid: (string) $agent->domain_uuid,

            agent_name: (string) $agent->agent_name,
            agent_extension: (string) $agent->agent_extension,

            agent_enabled: $textBool($agent->agent_enabled),

            description: $agent->description,

            voice_id: $agent->voice_id,
            language: $agent->language,
            first_message: $agent->first_message,
            system_prompt: $agent->system_prompt,

            elevenlabs_agent_id: $agent->elevenlabs_agent_id,
            elevenlabs_phone_number_id: $agent->elevenlabs_phone_number_id,
        );

        return response()->json($payload->toArray(), 200);
    }
}
