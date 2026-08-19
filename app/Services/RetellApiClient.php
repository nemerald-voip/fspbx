<?php

namespace App\Services;

use App\Contracts\AiProviderClient;
use App\Exceptions\RetellApiException;
use App\Models\AiAgent;
use App\Models\AiProviderIntegration;
use App\Services\AiTools\AiProviderToolCatalog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RetellApiClient implements AiProviderClient
{
    private const BASE_URL = 'https://api.retellai.com';

    public function __construct(private readonly AiProviderIntegrationService $integrations)
    {
    }

    public function provider(): string
    {
        return 'retell';
    }

    public function listAgents(AiProviderIntegration $integration): array
    {
        $agents = [];
        $paginationKey = null;

        do {
            $request = [
                'filter_criteria' => [
                    'channel' => [
                        'type' => 'string',
                        'op' => 'eq',
                        'value' => 'voice',
                    ],
                ],
                'limit' => 1000,
            ];

            if ($paginationKey !== null) {
                $request['pagination_key'] = $paginationKey;
            }

            $payload = $this->json($this->request($integration)->post(
                self::BASE_URL . '/v2/list-agents',
                $request
            ));
            $items = $payload['items'] ?? null;

            if (! is_array($items)) {
                throw new RetellApiException('Retell returned an invalid agent list response.');
            }

            $agents = array_merge($agents, $items);
            $hasMore = ($payload['has_more'] ?? false) === true;
            $nextPaginationKey = $payload['pagination_key'] ?? null;

            if ($hasMore && (! is_string($nextPaginationKey)
                || trim($nextPaginationKey) === ''
                || $nextPaginationKey === $paginationKey)) {
                throw new RetellApiException('Retell did not return a valid agent pagination key.');
            }

            $paginationKey = $hasMore ? $nextPaginationKey : null;
        } while ($hasMore);

        return collect($agents)
            ->filter(fn ($agent) => is_array($agent))
            ->filter(fn ($agent) => ($agent['channel'] ?? 'voice') === 'voice')
            ->map(fn ($agent) => [
                'value' => $agent['agent_id'] ?? null,
                'label' => $agent['agent_name'] ?? $agent['agent_id'] ?? 'Unnamed agent',
            ])
            ->filter(fn ($agent) => filled($agent['value']))
            ->unique('value')
            ->values()
            ->all();
    }

    public function test(AiProviderIntegration $integration): void
    {
        $this->listAgents($integration);
    }

    public function provision(AiAgent $agent): string
    {
        $integration = $this->integrations->retell();
        $payload = $this->bindingPayload($agent);
        $payload += [
            'phone_number' => (string) $agent->ai_agent_uuid,
            'termination_uri' => $this->integrations->terminationUri($integration),
            'ignore_e164_validation' => true,
            'transport' => 'TCP',
            'nickname' => $agent->name,
        ];

        $response = $this->json($this->request($integration)->post(self::BASE_URL . '/import-phone-number', $payload));

        return (string) ($response['phone_number'] ?? $agent->ai_agent_uuid);
    }

    public function synchronize(AiAgent $agent, ?bool $enabled = null): void
    {
        $integration = $this->integrations->retell();
        $payload = ($enabled ?? $agent->enabled) ? $this->bindingPayload($agent) : [
            'inbound_agents' => [],
            'outbound_agents' => [],
        ];
        $payload['nickname'] = $agent->name;

        $this->json($this->request($integration)->patch(
            self::BASE_URL . '/update-phone-number/' . rawurlencode($agent->provider_phone_number ?: $agent->ai_agent_uuid),
            $payload
        ));
    }

    public function refresh(AiAgent $agent): string
    {
        $integration = $this->integrations->retell();

        $response = $this->json($this->request($integration)->get(
            self::BASE_URL . '/get-phone-number/' . rawurlencode($agent->provider_phone_number ?: $agent->ai_agent_uuid)
        ));

        return (string) ($response['phone_number'] ?? $agent->provider_phone_number ?? $agent->ai_agent_uuid);
    }

    public function delete(AiAgent $agent): void
    {
        $integration = $this->integrations->retell();
        $this->json($this->request($integration)->delete(
            self::BASE_URL . '/delete-phone-number/' . rawurlencode($agent->provider_phone_number ?: $agent->ai_agent_uuid)
        ));
    }

    public function synchronizeTools(
        string $providerAgentId,
        array $managedTools,
        ?int $draftAgentVersion,
        callable $draftCreated,
    ): array {
        $integration = $this->integrations->retell();

        if ($managedTools === []) {
            throw new RetellApiException('No FS PBX tools are defined for Retell.');
        }

        if ($draftAgentVersion !== null) {
            $agent = $this->getAgent($integration, $providerAgentId, $draftAgentVersion);
            $flow = $this->getConversationFlow($integration, $this->conversationFlow($agent));

            if (($agent['is_published'] ?? false) === true) {
                if (! $this->managedToolsMatch($flow['tools'] ?? [], $managedTools)) {
                    throw new RetellApiException('The saved Retell tool-sync draft was published without the current FS PBX tools.');
                }

                return $this->toolSyncResult($agent, false, true, $flow['tools'] ?? []);
            }

            return $this->updateAndPublishDraft($integration, $providerAgentId, $agent, $flow, $managedTools);
        }

        $publishedAgent = $this->latestPublishedAgent($integration, $providerAgentId);
        $publishedFlow = $this->getConversationFlow($integration, $this->conversationFlow($publishedAgent));

        if ($this->managedToolsMatch($publishedFlow['tools'] ?? [], $managedTools)) {
            return $this->toolSyncResult($publishedAgent, false, false, $publishedFlow['tools'] ?? []);
        }

        $draft = $this->json($this->request($integration)->post(
            self::BASE_URL . '/create-agent-version/' . rawurlencode($providerAgentId),
            ['base_version' => (int) $publishedAgent['version']]
        ));

        if (! isset($draft['version'])) {
            throw new RetellApiException('Retell did not return the new draft agent version.');
        }

        $draftCreated((int) $draft['version']);
        $draftFlow = $this->getConversationFlow($integration, $this->conversationFlow($draft));

        return $this->updateAndPublishDraft($integration, $providerAgentId, $draft, $draftFlow, $managedTools);
    }

    private function bindingPayload(AiAgent $agent): array
    {
        $binding = fn (string $id) => [
            'agent_id' => $id,
            'weight' => 1,
            'agent_version' => 'latest_published',
        ];

        return [
            'inbound_agents' => [$binding($agent->inbound_agent_id)],
            'outbound_agents' => filled($agent->outbound_agent_id)
                ? [$binding($agent->outbound_agent_id)]
                : [],
        ];
    }

    private function latestPublishedAgent(AiProviderIntegration $integration, string $providerAgentId): array
    {
        $versions = $this->json($this->request($integration)->get(
            self::BASE_URL . '/get-agent-versions/' . rawurlencode($providerAgentId)
        ));

        $published = collect(array_is_list($versions) ? $versions : ($versions['agents'] ?? []))
            ->filter(fn ($version) => is_array($version) && ($version['is_published'] ?? false) === true)
            ->sortByDesc(fn (array $version) => (int) ($version['version'] ?? -1))
            ->first();

        if (! is_array($published)) {
            throw new RetellApiException('The selected Retell agent has no published version.');
        }

        return $published;
    }

    private function getAgent(AiProviderIntegration $integration, string $providerAgentId, int $version): array
    {
        return $this->json($this->request($integration)->get(
            self::BASE_URL . '/get-agent/' . rawurlencode($providerAgentId),
            ['version' => $version]
        ));
    }

    private function conversationFlow(array $agent): array
    {
        $engine = $agent['response_engine'] ?? null;

        if (! is_array($engine)
            || ($engine['type'] ?? null) !== 'conversation-flow'
            || blank($engine['conversation_flow_id'] ?? null)
            || ! is_numeric($engine['version'] ?? null)) {
            throw new RetellApiException('FS PBX tools require a Retell Conversation Flow agent.');
        }

        return [
            'type' => 'conversation-flow',
            'id' => (string) $engine['conversation_flow_id'],
            'version' => (int) $engine['version'],
        ];
    }

    private function getConversationFlow(AiProviderIntegration $integration, array $engine): array
    {
        return $this->json($this->request($integration)->get(
            self::BASE_URL . '/get-conversation-flow/' . rawurlencode($engine['id']),
            ['version' => $engine['version']]
        ));
    }

    private function updateAndPublishDraft(
        AiProviderIntegration $integration,
        string $providerAgentId,
        array $draft,
        array $flow,
        array $managedTools,
    ): array {
        $engine = $this->conversationFlow($draft);
        $tools = $this->reconcileTools($flow['tools'] ?? [], $managedTools);

        if (! $this->managedToolsMatch($flow['tools'] ?? [], $managedTools)) {
            $this->json($this->request($integration)->patch(
                self::BASE_URL . '/update-conversation-flow/' . rawurlencode($engine['id']) . '?version=' . $engine['version'],
                ['tools' => $tools]
            ));
        }

        $this->json($this->request($integration)->post(
            self::BASE_URL . '/publish-agent-version/' . rawurlencode($providerAgentId),
            [
                'version' => (int) $draft['version'],
                'version_title' => 'FS PBX tools',
                'version_description' => 'Published automatically after synchronizing FS PBX-managed tools.',
            ]
        ));

        return $this->toolSyncResult($draft, true, true, $tools);
    }

    private function toolSyncResult(array $agent, bool $changed, bool $published, array $tools): array
    {
        $engine = $this->conversationFlow($agent);

        return [
            'changed' => $changed,
            'published' => $published,
            'configuration_required' => $this->configurationRequired($tools),
            'response_engine_type' => $engine['type'],
            'response_engine_id' => $engine['id'],
            'response_engine_version' => $engine['version'],
            'published_agent_version' => (int) $agent['version'],
        ];
    }

    private function configurationRequired(array $tools): bool
    {
        $sendEmail = collect($tools)->first(fn ($tool) => is_array($tool)
            && (($tool['tool_id'] ?? null) === AiProviderToolCatalog::SEND_EMAIL_TOOL_ID
                || ($tool['name'] ?? null) === AiProviderToolCatalog::SEND_EMAIL_TOOL_NAME));

        if (! is_array($sendEmail)) {
            return false;
        }

        $recipient = trim((string) data_get($sendEmail, 'parameters.properties.recipient.const'));

        return $recipient === '' || $recipient === AiProviderToolCatalog::SEND_EMAIL_RECIPIENT_PLACEHOLDER;
    }

    private function reconcileTools(mixed $remoteTools, array $managedTools): array
    {
        $remoteTools = is_array($remoteTools) ? $remoteTools : [];
        $managedIds = collect($managedTools)->pluck('tool_id')->filter()->all();
        $managedNames = collect($managedTools)->pluck('name')->filter()->all();
        $preservedManagedTools = collect($managedTools)->map(function (array $expected) use ($remoteTools) {
            $remote = collect($remoteTools)->first(fn ($tool) => is_array($tool)
                && (($tool['tool_id'] ?? null) === ($expected['tool_id'] ?? null)
                    || ($tool['name'] ?? null) === ($expected['name'] ?? null)));

            return $this->preserveConfiguredValues($expected, is_array($remote) ? $remote : []);
        });

        return collect($remoteTools)
            ->filter(fn ($tool) => is_array($tool)
                && ! in_array($tool['tool_id'] ?? null, $managedIds, true)
                && ! in_array($tool['name'] ?? null, $managedNames, true))
            ->concat($preservedManagedTools)
            ->values()
            ->all();
    }

    private function managedToolsMatch(mixed $remoteTools, array $managedTools): bool
    {
        if (! is_array($remoteTools)) {
            return false;
        }

        foreach ($managedTools as $expected) {
            $matches = collect($remoteTools)->filter(fn ($tool) => is_array($tool)
                && (($tool['tool_id'] ?? null) === ($expected['tool_id'] ?? null)
                    || ($tool['name'] ?? null) === ($expected['name'] ?? null)));

            if ($matches->count() !== 1) {
                return false;
            }

            $expected = $this->preserveConfiguredValues($expected, $matches->first());

            if ($this->canonicalize($this->project($matches->first(), $expected)) !== $this->canonicalize($expected)) {
                return false;
            }
        }

        return true;
    }

    private function preserveConfiguredValues(array $expected, array $remote): array
    {
        if (($expected['name'] ?? null) !== AiProviderToolCatalog::SEND_EMAIL_TOOL_NAME) {
            return $expected;
        }

        $recipient = data_get($remote, 'parameters.properties.recipient.const');

        if (is_string($recipient) && trim($recipient) !== '') {
            data_set($expected, 'parameters.properties.recipient.const', trim($recipient));
        }

        return $expected;
    }

    private function project(mixed $actual, mixed $expected): mixed
    {
        if (! is_array($expected) || ! is_array($actual)) {
            return $actual;
        }

        if (array_is_list($expected)) {
            return $actual;
        }

        $projected = [];
        foreach ($expected as $key => $value) {
            $projected[$key] = array_key_exists($key, $actual)
                ? $this->project($actual[$key], $value)
                : null;
        }

        return $projected;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item) => $this->canonicalize($item), $value);
        }

        ksort($value);

        return array_map(fn (mixed $item) => $this->canonicalize($item), $value);
    }

    private function request(AiProviderIntegration $integration)
    {
        if (! $integration->hasApiKey()) {
            throw new RetellApiException('The Retell API key is not configured.');
        }

        return Http::withToken($integration->api_key)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->retry(2, 250, throw: false);
    }

    private function json(Response $response): array
    {
        if (! $response->successful()) {
            $message = $this->errorMessage($response);

            logger()->error('Retell API request failed.', [
                'status' => $response->status(),
                'response' => Str::limit(trim($response->body()), 2000),
            ]);

            throw new RetellApiException($message, $response->status());
        }

        return is_array($response->json()) ? $response->json() : [];
    }

    private function errorMessage(Response $response): string
    {
        $payload = $response->json();
        $message = data_get($payload, 'message')
            ?? data_get($payload, 'detail')
            ?? data_get($payload, 'error.message')
            ?? data_get($payload, 'error')
            ?? data_get($payload, 'errors.0.message');

        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }

        return 'Retell returned HTTP ' . $response->status() . '.';
    }
}
