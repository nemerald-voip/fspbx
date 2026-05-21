<?php

namespace App\Services;

use App\Models\AiReceptionist;
use App\Models\AiReceptionistSession;
use App\Models\AiReceptionistSetting;
use App\Models\AiReceptionistTool;
use App\Models\AiReceptionistToolRun;
use App\Models\CallCenterQueues;
use App\Models\CallFlows;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\Domain;
use App\Models\Extensions;
use App\Models\Faxes;
use App\Models\FusionCache;
use App\Models\IvrMenus;
use App\Models\RingGroups;
use App\Models\Voicemails;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class AiReceptionistService
{
    private const APP_UUID = '3a1ab4b0-5bb9-4f5a-9cb6-4f55a1a10000';
    private const SETTINGS_CACHE_TAG = 'ai-receptionist-settings';
    private const SETTINGS_CACHE_PREFIX = 'ai-receptionist:settings:';
    private const SETTINGS_CACHE_TTL_HOURS = 24;

    public const ENGINE_DEFINITIONS = [
        'standard_pipeline' => [
            'label' => 'Deepgram STT + OpenAI LLM + ElevenLabs TTS',
            'description' => 'Recommended modular pipeline. Deepgram STT, OpenAI LLM, and ElevenLabs TTS are provided by LiveKit Inference.',
        ],
        'openai_realtime' => [
            'label' => 'OpenAI Realtime Speech-to-Speech',
            'description' => 'Premium low-latency speech-to-speech path using OpenAI Realtime through the system OPENAI_API_KEY.',
        ],
        'assemblyai_agent' => [
            'label' => 'AssemblyAI Realtime Agent',
            'description' => 'AssemblyAI STT, OpenAI LLM, and ElevenLabs TTS are provided by LiveKit Inference.',
        ],
    ];

    public const ENGINES = [
        'standard_pipeline' => self::ENGINE_DEFINITIONS['standard_pipeline']['label'],
        'openai_realtime' => self::ENGINE_DEFINITIONS['openai_realtime']['label'],
        'assemblyai_agent' => self::ENGINE_DEFINITIONS['assemblyai_agent']['label'],
    ];

    public const AGENT_RUNTIME_DEFINITIONS = [
        'local_worker' => [
            'label' => 'Local FS PBX Worker',
            'description' => 'Run the Python LiveKit worker on this FS PBX server with Supervisor.',
            'uses_local_service' => true,
        ],
        'external_worker' => [
            'label' => 'External Self-Hosted Worker',
            'description' => 'Run the Python worker on another VM or container and point it back to this FS PBX API.',
            'uses_local_service' => false,
        ],
        'livekit_cloud_agent' => [
            'label' => 'LiveKit Cloud Hosted Agent',
            'description' => 'Deploy the worker as a managed LiveKit Cloud agent. FS PBX only supplies PBX tools and policy.',
            'uses_local_service' => false,
        ],
        'telnyx_hosted_agent' => [
            'label' => 'Telnyx Hosted Agent',
            'description' => 'Deploy the worker on LiveKit on Telnyx. FS PBX only supplies PBX tools and policy.',
            'uses_local_service' => false,
        ],
    ];

    public const AGENT_RUNTIMES = [
        'local_worker' => self::AGENT_RUNTIME_DEFINITIONS['local_worker']['label'],
        'external_worker' => self::AGENT_RUNTIME_DEFINITIONS['external_worker']['label'],
        'livekit_cloud_agent' => self::AGENT_RUNTIME_DEFINITIONS['livekit_cloud_agent']['label'],
        'telnyx_hosted_agent' => self::AGENT_RUNTIME_DEFINITIONS['telnyx_hosted_agent']['label'],
    ];

    public function saveReceptionist(array $validated, ?AiReceptionist $receptionist = null): AiReceptionist
    {
        return DB::transaction(function () use ($validated, $receptionist) {
            $receptionist ??= new AiReceptionist();
            $isNew = ! $receptionist->exists;
            $dialplanUuid = $receptionist->dialplan_uuid ?: (string) Str::uuid();

            $receptionist->forceFill([
                'domain_uuid' => session('domain_uuid'),
                'dialplan_uuid' => $dialplanUuid,
                'name' => $validated['name'],
                'extension' => $validated['extension'],
                'system_prompt' => $this->blankToNull($validated['system_prompt'] ?? null),
                'initial_message' => $this->blankToNull($validated['initial_message'] ?? null),
                'fallback_type' => $this->blankToNull($validated['fallback_type'] ?? null),
                'fallback_target' => $this->blankToNull($validated['fallback_target'] ?? null),
                'fallback_label' => $this->blankToNull($validated['fallback_label'] ?? null),
                'max_duration_seconds' => (int) ($validated['max_duration_seconds'] ?? 900),
                'user_silence_checkin_seconds' => (int) ($validated['user_silence_checkin_seconds'] ?? 15),
                'user_idle_timeout_seconds' => (int) ($validated['user_idle_timeout_seconds'] ?? 60),
                'allow_interruptions' => $this->toBoolean($validated['allow_interruptions'] ?? true),
                'min_interruption_duration' => (float) ($validated['min_interruption_duration'] ?? 0.5),
                'transcript_enabled' => $this->toBoolean($validated['transcript_enabled'] ?? true),
                'tool_access_enabled' => $this->toBoolean($validated['tool_access_enabled'] ?? true),
                'description' => $this->blankToNull($validated['description'] ?? null),
            ])->save();

            $this->saveDialplan($receptionist, $isNew);
            $this->clearDialplanCache($receptionist);

            return $receptionist;
        });
    }

    public function deleteReceptionists(Collection $receptionists): int
    {
        return DB::transaction(function () use ($receptionists) {
            $dialplanUuids = $receptionists->pluck('dialplan_uuid')->filter();
            $receptionistUuids = $receptionists->pluck('ai_receptionist_uuid')->filter();

            if ($dialplanUuids->isNotEmpty()) {
                DialplanDetails::query()->whereIn('dialplan_uuid', $dialplanUuids)->delete();
                Dialplans::query()->whereIn('dialplan_uuid', $dialplanUuids)->delete();
            }

            AiReceptionistTool::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('ai_receptionist_uuid', $receptionistUuids)
                ->delete();

            $deleted = AiReceptionist::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('ai_receptionist_uuid', $receptionistUuids)
                ->delete();

            FusionCache::clear('dialplan:' . session('domain_name'));

            return $deleted;
        });
    }

    public function configForReceptionist(AiReceptionist $receptionist): array
    {
        $settings = $this->resolvedSettings($receptionist->domain_uuid);
        $engine = $settings['default_engine'] ?? 'standard_pipeline';

        return [
            'ai_receptionist_uuid' => $receptionist->ai_receptionist_uuid,
            'domain_uuid' => $receptionist->domain_uuid,
            'domain_name' => optional($receptionist->domain)->domain_name,
            'name' => $receptionist->name,
            'extension' => $receptionist->extension,
            'engine' => $engine,
            'engine_label' => self::ENGINE_DEFINITIONS[$engine]['label'] ?? $engine,
            'system_prompt' => $receptionist->system_prompt,
            'initial_message' => $receptionist->initial_message,
            'max_duration_seconds' => (int) $receptionist->max_duration_seconds,
            'user_silence_checkin_seconds' => (int) $receptionist->user_silence_checkin_seconds,
            'user_idle_timeout_seconds' => (int) $receptionist->user_idle_timeout_seconds,
            'allow_interruptions' => $this->boolString($receptionist->allow_interruptions, true),
            'min_interruption_duration' => (float) $receptionist->min_interruption_duration,
            'transcript_enabled' => $this->boolString($receptionist->transcript_enabled, true),
            'tool_access_enabled' => $this->boolString($receptionist->tool_access_enabled, true),
            'fallback' => [
                'type' => $receptionist->fallback_type,
                'target' => $receptionist->fallback_target,
                'label' => $receptionist->fallback_label,
            ],
            'settings' => $settings,
            'tools' => $this->toolsForReceptionist($receptionist)
                ->map(fn (AiReceptionistTool $tool) => [
                    'tool_uuid' => $tool->tool_uuid,
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'request_schema' => $tool->request_schema ?? [],
                ])
                ->values()
                ->all(),
        ];
    }

    public function startSession(AiReceptionist $receptionist, array $payload): AiReceptionistSession
    {
        $settings = $this->resolvedSettings($receptionist->domain_uuid);
        $engine = $settings['default_engine'] ?? 'standard_pipeline';

        return AiReceptionistSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'domain_uuid' => $receptionist->domain_uuid,
            'ai_receptionist_uuid' => $receptionist->ai_receptionist_uuid,
            'setting_uuid' => $settings['setting_uuid'] ?? null,
            'engine' => $engine,
            'status' => 'started',
            'freeswitch_uuid' => $this->blankToNull($payload['freeswitch_uuid'] ?? null),
            'livekit_room' => $this->blankToNull($payload['livekit_room'] ?? null),
            'livekit_participant' => $this->blankToNull($payload['livekit_participant'] ?? null),
            'caller_id_name' => $this->blankToNull($payload['caller_id_name'] ?? null),
            'caller_id_number' => $this->blankToNull($payload['caller_id_number'] ?? null),
            'destination_number' => $this->blankToNull($payload['destination_number'] ?? null),
            'metadata' => Arr::wrap($payload['metadata'] ?? []),
            'started_at' => now(),
        ]);
    }

    public function resolveDestination(AiReceptionistSession $session, array $payload): array
    {
        $type = $payload['type'] ?? null;
        $target = trim((string) ($payload['target'] ?? ''));
        $intent = trim((string) ($payload['intent'] ?? ''));

        if (filled($type) && filled($target)) {
            return $this->destinationFromExplicitTarget($session->domain_uuid, $type, $target);
        }

        if ($intent === '') {
            throw new RuntimeException('No intent or target was provided.');
        }

        $result = $this->searchDestinations($session->domain_uuid, $intent)->first();

        if (! $result) {
            throw new RuntimeException('No matching destination was found.');
        }

        return $result;
    }

    public function transfer(AiReceptionistSession $session, array $destination): array
    {
        $domainName = Domain::query()
            ->where('domain_uuid', $session->domain_uuid)
            ->value('domain_name');

        if (blank($domainName)) {
            throw new RuntimeException('Domain could not be resolved.');
        }

        if (blank($session->freeswitch_uuid)) {
            throw new RuntimeException('FreeSWITCH call UUID is missing.');
        }

        $target = $destination['extension'] ?? null;
        if (blank($target) || ! preg_match('/^[*+#A-Za-z0-9_.-]+$/', $target)) {
            throw new RuntimeException('Transfer target is invalid.');
        }

        $command = sprintf('uuid_transfer %s %s XML %s', $session->freeswitch_uuid, $target, $domainName);
        $response = app(FreeswitchEslService::class)->executeCommand($command);

        $ok = is_string($response) && ! str_starts_with($response, '-ERR');

        $session->forceFill([
            'status' => $ok ? 'transferred' : 'transfer_failed',
            'transfer_type' => $destination['type'] ?? null,
            'transfer_target' => $target,
            'transfer_label' => $destination['name'] ?? null,
            'error_message' => $ok ? null : (is_string($response) ? $response : 'FreeSWITCH did not return a successful response.'),
            'ended_at' => $ok ? now() : $session->ended_at,
        ])->save();

        return [
            'success' => $ok,
            'command' => $command,
            'response' => $response,
            'destination' => $destination,
        ];
    }

    public function executeHttpTool(AiReceptionistSession $session, string $toolName, array $payload): array
    {
        $tool = $this->toolsForReceptionist($session->receptionist)
            ->firstWhere('name', $toolName);

        if (! $tool) {
            throw new RuntimeException("Tool {$toolName} is not enabled for this receptionist.");
        }

        $run = AiReceptionistToolRun::query()->create([
            'tool_run_uuid' => (string) Str::uuid(),
            'session_uuid' => $session->session_uuid,
            'tool_uuid' => $tool->tool_uuid,
            'tool_name' => $tool->name,
            'status' => 'started',
            'request_payload' => $payload,
            'started_at' => now(),
        ]);

        try {
            $method = strtolower($tool->method ?: 'post');
            if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                throw new RuntimeException('Tool HTTP method is not allowed.');
            }

            $response = Http::withHeaders($tool->headers ?? [])
                ->timeout((int) ($tool->timeout_seconds ?: 10))
                ->{$method}($tool->url, $payload);

            $body = $response->json();
            if ($body === null) {
                $body = ['body' => $response->body()];
            }

            $run->forceFill([
                'status' => $response->successful() ? 'completed' : 'failed',
                'response_payload' => [
                    'status' => $response->status(),
                    'body' => $body,
                ],
                'error_message' => $response->successful() ? null : 'HTTP tool returned status ' . $response->status(),
                'ended_at' => now(),
            ])->save();

            return $run->response_payload;
        } catch (\Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'ended_at' => now(),
            ])->save();

            throw $exception;
        }
    }

    public function endSession(AiReceptionistSession $session, array $payload): AiReceptionistSession
    {
        $session->forceFill([
            'status' => $payload['status'] ?? 'completed',
            'transcript' => $payload['transcript'] ?? $session->transcript,
            'summary' => $payload['summary'] ?? $session->summary,
            'error_message' => $payload['error_message'] ?? $session->error_message,
            'ended_at' => now(),
        ])->save();

        return $session;
    }

    public function engineOptions(): array
    {
        return collect(self::ENGINE_DEFINITIONS)
            ->map(fn ($definition, $value) => [
                'value' => $value,
                'label' => $definition['label'],
                'description' => $definition['description'],
            ])
            ->values()
            ->all();
    }

    public function agentRuntimeOptions(): array
    {
        return collect(self::AGENT_RUNTIME_DEFINITIONS)
            ->map(fn ($definition, $value) => [
                'value' => $value,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'uses_local_service' => $definition['uses_local_service'],
            ])
            ->values()
            ->all();
    }

    public function usesLocalAgentRuntime(array|string|null $settingsOrRuntime): bool
    {
        $runtime = is_array($settingsOrRuntime)
            ? ($settingsOrRuntime['agent_runtime'] ?? 'local_worker')
            : ($settingsOrRuntime ?: 'local_worker');

        return (self::AGENT_RUNTIME_DEFINITIONS[$runtime]['uses_local_service'] ?? false) === true;
    }

    public function resolvedSettings(?string $domainUuid = null, bool $includeSecrets = false): array
    {
        $settings = Cache::tags(self::SETTINGS_CACHE_TAG)
            ->remember(
                $this->settingsCacheKey($domainUuid),
                now()->addHours(self::SETTINGS_CACHE_TTL_HOURS),
                fn () => $this->buildResolvedSettings($domainUuid)
            );

        if (! $includeSecrets) {
            unset($settings['livekit_api_key'], $settings['livekit_api_secret']);
        }

        return $settings;
    }

    public function freshResolvedSettings(?string $domainUuid = null, bool $includeSecrets = false): array
    {
        $settings = $this->buildResolvedSettings($domainUuid);

        if (! $includeSecrets) {
            unset($settings['livekit_api_key'], $settings['livekit_api_secret']);
        }

        return $settings;
    }

    private function buildResolvedSettings(?string $domainUuid = null): array
    {
        $rows = AiReceptionistSetting::query()
            ->whereNull('domain_uuid')
            ->when($domainUuid, fn ($query) => $query->orWhere('domain_uuid', $domainUuid))
            ->get()
            ->keyBy(fn (AiReceptionistSetting $setting) => $setting->domain_uuid === null ? 'system' : 'domain');

        $system = $rows->get('system');
        $domain = $rows->get('domain');
        $effective = $domain ?: $system;

        $systemProviderConfig = $system?->provider_config ?? [];
        $domainProviderConfig = $domain?->provider_config ?? [];

        return [
            'setting_uuid' => $effective?->setting_uuid,
            'scope' => $domain ? 'domain' : 'system',
            'domain_uuid' => $domainUuid,
            'enabled' => (bool) ($domain?->enabled ?? $system?->enabled ?? false),
            'default_engine' => $domain?->default_engine ?: ($system?->default_engine ?: 'standard_pipeline'),
            'agent_runtime' => $system?->agent_runtime ?: 'local_worker',
            'livekit_url' => $system?->livekit_url,
            'livekit_api_key' => $system?->livekit_api_key,
            'livekit_api_secret' => $system?->livekit_api_secret,
            'livekit_hosting' => $this->detectLiveKitHosting($system?->livekit_url),
            'provider_config' => array_replace_recursive($systemProviderConfig, $domainProviderConfig),
        ];
    }

    public function invalidateSettingsCache(?string $domainUuid = null): void
    {
        if (filled($domainUuid)) {
            Cache::tags(self::SETTINGS_CACHE_TAG)->forget($this->settingsCacheKey($domainUuid));
            return;
        }

        Cache::tags(self::SETTINGS_CACHE_TAG)->flush();
    }

    public function saveSettings(array $validated, ?string $domainUuid = null): AiReceptionistSetting
    {
        $isDomainOverride = filled($domainUuid);

        $values = [
            'domain_uuid' => $domainUuid,
            'default_engine' => $this->blankToNull($validated['default_engine'] ?? null),
            'provider_config' => $validated['provider_config'] ?? [],
            'enabled' => $this->toBoolean($validated['enabled'] ?? false),
        ];

        if (! $isDomainOverride) {
            $values += [
                'agent_runtime' => $validated['agent_runtime'] ?? 'local_worker',
                'livekit_url' => $this->blankToNull($validated['livekit_url'] ?? null),
                'livekit_api_key' => $this->blankToNull($validated['livekit_api_key'] ?? null),
                'livekit_api_secret' => $this->blankToNull($validated['livekit_api_secret'] ?? null),
            ];
        }

        $setting = AiReceptionistSetting::query()->updateOrCreate(
            ['domain_uuid' => $domainUuid],
            $values
        );

        $this->invalidateSettingsCache($domainUuid);

        return $setting;
    }

    public function deleteSettingsOverride(string $domainUuid): int
    {
        $deleted = AiReceptionistSetting::query()
            ->where('domain_uuid', $domainUuid)
            ->delete();

        $this->invalidateSettingsCache($domainUuid);

        return $deleted;
    }

    private function settingsCacheKey(?string $domainUuid): string
    {
        return self::SETTINGS_CACHE_PREFIX . ($domainUuid ?: 'system');
    }

    public function detectLiveKitHosting(?string $url): array
    {
        $host = parse_url((string) $url, PHP_URL_HOST) ?: '';
        $host = strtolower($host);

        return match (true) {
            $host === '' => [
                'value' => 'unknown',
                'label' => 'Not configured',
                'description' => 'Enter a LiveKit URL to identify where the media server is hosted.',
            ],
            str_ends_with($host, 'livekit.cloud') => [
                'value' => 'livekit_cloud',
                'label' => 'LiveKit Cloud',
                'description' => 'Media, SIP, and rooms are hosted by LiveKit Cloud.',
            ],
            str_ends_with($host, 'livekit-telnyx.com') => [
                'value' => 'telnyx',
                'label' => 'LiveKit on Telnyx',
                'description' => 'Media, SIP, and rooms are hosted on Telnyx infrastructure.',
            ],
            in_array($host, ['localhost', '127.0.0.1', '::1'], true) => [
                'value' => 'local',
                'label' => 'Local LiveKit',
                'description' => 'The LiveKit media server appears to be running locally.',
            ],
            default => [
                'value' => 'custom',
                'label' => 'Custom or self-hosted LiveKit',
                'description' => 'The LiveKit media server appears to use a custom host.',
            ],
        };
    }

    public function saveTool(array $validated, ?AiReceptionistTool $tool = null): AiReceptionistTool
    {
        $tool ??= new AiReceptionistTool();

        $tool->forceFill([
            'domain_uuid' => session('domain_uuid'),
            'ai_receptionist_uuid' => $this->blankToNull($validated['ai_receptionist_uuid'] ?? null),
            'name' => $validated['name'],
            'description' => $this->blankToNull($validated['description'] ?? null),
            'method' => strtoupper($validated['method'] ?? 'POST'),
            'url' => $validated['url'],
            'headers' => $validated['headers'] ?? [],
            'request_schema' => $validated['request_schema'] ?? [],
            'timeout_seconds' => (int) ($validated['timeout_seconds'] ?? 10),
            'enabled' => $this->toBoolean($validated['enabled'] ?? true),
        ])->save();

        return $tool;
    }

    private function saveDialplan(AiReceptionist $receptionist, bool $isNew): void
    {
        $dialplan = Dialplans::query()
            ->where('dialplan_uuid', $receptionist->dialplan_uuid)
            ->first() ?? new Dialplans();

        $dialplan->forceFill([
            'domain_uuid' => session('domain_uuid'),
            'dialplan_uuid' => $receptionist->dialplan_uuid,
            'app_uuid' => self::APP_UUID,
            'dialplan_name' => $receptionist->name,
            'dialplan_number' => $receptionist->extension,
            'dialplan_context' => session('domain_name'),
            'dialplan_continue' => 'false',
            'dialplan_xml' => $this->dialplanXml($receptionist),
            'dialplan_order' => '235',
            'dialplan_enabled' => 'true',
            'dialplan_description' => $receptionist->description,
            $isNew || ! $dialplan->exists ? 'insert_date' : 'update_date' => now(),
            $isNew || ! $dialplan->exists ? 'insert_user' : 'update_user' => session('user_uuid'),
        ])->save();
    }

    private function dialplanXml(AiReceptionist $receptionist): string
    {
        $bridgeTarget = '${ai_receptionist_livekit_sip_uri}';
        $metadata = [
            'domain_uuid' => $receptionist->domain_uuid,
            'ai_receptionist_uuid' => $receptionist->ai_receptionist_uuid,
            'ai_receptionist_extension' => $receptionist->extension,
        ];

        $lines = [
            sprintf('<extension name="%s" continue="false" uuid="%s">', $this->xml($receptionist->name), $this->xml($receptionist->dialplan_uuid)),
            sprintf("\t" . '<condition field="destination_number" expression="^%s$">', $this->xml($receptionist->extension)),
            "\t\t" . '<action application="answer" data=""/>',
        ];

        foreach ($metadata as $key => $value) {
            $lines[] = sprintf("\t\t" . '<action application="export" data="%s=%s" inline="true"/>', $this->xml($key), $this->xml($value));
        }

        $lines[] = "\t\t" . '<action application="export" data="ai_receptionist_freeswitch_uuid=${uuid}" inline="true"/>';
        $lines[] = "\t\t" . '<action application="export" data="ai_receptionist_caller_id_name=${caller_id_name}" inline="true"/>';
        $lines[] = "\t\t" . '<action application="export" data="ai_receptionist_caller_id_number=${caller_id_number}" inline="true"/>';
        $lines[] = "\t\t" . '<action application="export" data="ai_receptionist_destination_number=${destination_number}" inline="true"/>';
        $lines[] = sprintf("\t\t" . '<action application="set" data="sip_h_X-FSPBX-Domain-Uuid=%s"/>', $this->xml($receptionist->domain_uuid));
        $lines[] = sprintf("\t\t" . '<action application="set" data="sip_h_X-FSPBX-AI-Receptionist-Uuid=%s"/>', $this->xml($receptionist->ai_receptionist_uuid));
        $lines[] = "\t\t" . '<action application="set" data="sip_h_X-FSPBX-FreeSWITCH-Uuid=${uuid}"/>';
        $lines[] = "\t\t" . '<action application="set" data="sip_h_X-FSPBX-Caller-ID-Name=${caller_id_name}"/>';
        $lines[] = "\t\t" . '<action application="set" data="sip_h_X-FSPBX-Caller-ID-Number=${caller_id_number}"/>';
        $lines[] = "\t\t" . '<action application="set" data="sip_h_X-FSPBX-Destination-Number=${destination_number}"/>';
        $lines[] = "\t\t" . '<action application="set" data="hangup_after_bridge=true"/>';
        $lines[] = sprintf("\t\t" . '<action application="bridge" data="%s"/>', $this->xml($bridgeTarget));
        $lines[] = "\t" . '</condition>';
        $lines[] = '</extension>';

        return implode("\n", $lines);
    }

    private function clearDialplanCache(AiReceptionist $receptionist): void
    {
        $context = optional($receptionist->domain)->domain_name ?: session('domain_name');
        app(DialplanService::class)->clearDialplanCache($context);
        FusionCache::clear('dialplan:' . $context);
    }

    private function destinationFromExplicitTarget(string $domainUuid, string $type, string $target): array
    {
        if ($type === 'external') {
            return ['type' => 'external', 'extension' => $target, 'name' => $target];
        }

        return $this->searchDestinations($domainUuid, $target, $type)->first()
            ?? ['type' => $type, 'extension' => $target, 'name' => $target];
    }

    private function searchDestinations(string $domainUuid, string $search, ?string $type = null): Collection
    {
        $search = trim($search);

        $lookups = [
            'extensions' => [Extensions::class, 'extension', 'effective_caller_id_name'],
            'ring_groups' => [RingGroups::class, 'ring_group_extension', 'ring_group_name'],
            'ivrs' => [IvrMenus::class, 'ivr_menu_extension', 'ivr_menu_name'],
            'contact_centers' => [CallCenterQueues::class, 'queue_extension', 'queue_name'],
            'call_flows' => [CallFlows::class, 'call_flow_extension', 'call_flow_name'],
            'faxes' => [Faxes::class, 'fax_extension', 'fax_name'],
            'voicemails' => [Voicemails::class, 'voicemail_id', 'voicemail_description'],
        ];

        return collect($lookups)
            ->when($type, fn ($items) => $items->only($type))
            ->flatMap(function (array $lookup, string $lookupType) use ($domainUuid, $search) {
                [$model, $extensionField, $nameField] = $lookup;

                return $model::query()
                    ->where('domain_uuid', $domainUuid)
                    ->where(function ($query) use ($extensionField, $nameField, $search) {
                        $query->where($extensionField, 'ilike', "%{$search}%")
                            ->orWhere($nameField, 'ilike', "%{$search}%");
                    })
                    ->limit(10)
                    ->get([$extensionField, $nameField])
                    ->map(fn ($row) => [
                        'type' => $lookupType,
                        'extension' => (string) $row->{$extensionField},
                        'name' => trim($row->{$extensionField} . ' - ' . ($row->{$nameField} ?? '')),
                    ]);
            })
            ->values();
    }

    private function toolsForReceptionist(AiReceptionist $receptionist): Collection
    {
        if (! $this->boolString($receptionist->tool_access_enabled, true)) {
            return collect();
        }

        return AiReceptionistTool::query()
            ->where('domain_uuid', $receptionist->domain_uuid)
            ->where('enabled', true)
            ->where(function ($query) use ($receptionist) {
                $query->whereNull('ai_receptionist_uuid')
                    ->orWhere('ai_receptionist_uuid', $receptionist->ai_receptionist_uuid);
            })
            ->orderBy('name')
            ->get();
    }

    private function blankToNull(mixed $value): mixed
    {
        return blank($value) ? null : $value;
    }

    private function boolString(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function toBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
