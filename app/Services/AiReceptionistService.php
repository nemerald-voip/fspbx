<?php

namespace App\Services;

use App\Mail\AiReceptionistRouteNotification;
use App\Models\AiReceptionist;
use App\Models\AiReceptionistRoute;
use App\Models\AiReceptionistSession;
use App\Models\AiReceptionistSetting;
use App\Models\AiReceptionistTool;
use App\Models\AiReceptionistToolRun;
use App\Models\AiReceptionistWarmTransfer;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class AiReceptionistService
{
    private const APP_UUID = '3a1ab4b0-5bb9-4f5a-9cb6-4f55a1a10000';
    private const SETTINGS_CACHE_TAG = 'ai-receptionist-settings';
    private const SETTINGS_CACHE_PREFIX = 'ai-receptionist:settings:';
    private const SETTINGS_CACHE_TTL_HOURS = 24;
    private const WARM_TRANSFER_TIMEOUT_SECONDS = 60;
    private const WARM_TRANSFER_POLL_INTERVAL_SECONDS = 1;
    private const WARM_TRANSFER_MIN_CONSULT_SECONDS = 2;

    public const ENGINE_DEFINITIONS = [
        'openai_realtime' => [
            'label' => 'OpenAI Realtime SIP',
            'description' => 'Low-latency speech-to-speech calls over OpenAI Realtime SIP using the system OPENAI_API_KEY.',
        ],
    ];

    public const ENGINES = [
        'openai_realtime' => self::ENGINE_DEFINITIONS['openai_realtime']['label'],
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
                'openai_voice' => $this->blankToNull($validated['openai_voice'] ?? null) ?: 'marin',
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

            $this->saveRoutes($receptionist, $validated['routes'] ?? []);

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

            AiReceptionistRoute::query()
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
        $engine = $settings['default_engine'] ?? 'openai_realtime';
        $routes = $this->routesForReceptionist($receptionist);

        if (! array_key_exists($engine, self::ENGINES)) {
            $engine = 'openai_realtime';
        }

        $instructions = app(AiReceptionistInstructionBuilder::class)->callerInstructions($receptionist, $routes);

        return [
            'ai_receptionist_uuid' => $receptionist->ai_receptionist_uuid,
            'domain_uuid' => $receptionist->domain_uuid,
            'domain_name' => optional($receptionist->domain)->domain_name,
            'name' => $receptionist->name,
            'extension' => $receptionist->extension,
            'engine' => $engine,
            'engine_label' => self::ENGINE_DEFINITIONS[$engine]['label'] ?? $engine,
            'openai_voice' => $receptionist->openai_voice ?: 'marin',
            'system_prompt' => $receptionist->system_prompt,
            'instructions' => $instructions,
            'instructions_preview' => $instructions,
            'routing_instructions' => $this->routingInstructions($routes),
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
            'routes' => $routes
                ->map(fn (AiReceptionistRoute $route) => $this->routePayload($route, includePrivate: false))
                ->values()
                ->all(),
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
        $engine = $settings['default_engine'] ?? 'openai_realtime';

        return AiReceptionistSession::query()->create([
            'session_uuid' => (string) Str::uuid(),
            'domain_uuid' => $receptionist->domain_uuid,
            'ai_receptionist_uuid' => $receptionist->ai_receptionist_uuid,
            'setting_uuid' => $settings['setting_uuid'] ?? null,
            'engine' => $engine,
            'status' => 'started',
            'freeswitch_uuid' => $this->blankToNull($payload['freeswitch_uuid'] ?? null),
            'openai_call_id' => $this->blankToNull($payload['realtime_call_id'] ?? $payload['openai_call_id'] ?? null),
            'sip_call_id' => $this->blankToNull($payload['sip_call_id'] ?? null),
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

    public function transferToRoute(AiReceptionistSession $session, string $routeUuid): array
    {
        $route = $this->routeForSession($session, $routeUuid);

        if ($route->action_type !== 'transfer' || $route->transfer_type !== 'cold') {
            throw new RuntimeException('The selected route is not configured for cold transfer.');
        }

        if (blank($route->destination_type) || blank($route->destination_target)) {
            throw new RuntimeException('The selected route has no destination configured.');
        }

        return $this->transfer($session, [
            'type' => $route->destination_type,
            'extension' => $route->destination_target,
            'name' => $route->destination_label ?: $route->name,
            'route_uuid' => $route->route_uuid,
            'route_name' => $route->name,
        ]);
    }

    public function coldTransfer(AiReceptionistSession $session, string $routeUuid): array
    {
        return $this->transferToRoute($session, $routeUuid);
    }

    public function resolveRoute(AiReceptionistSession $session, array $payload): array
    {
        $intent = trim((string) ($payload['intent'] ?? ''));

        if ($intent === '') {
            throw new RuntimeException('No route intent was provided.');
        }

        $needle = Str::lower($intent);
        $routes = $this->routesForReceptionist($session->receptionist);

        $route = $routes->first(function (AiReceptionistRoute $route) use ($needle) {
            $phrases = collect($route->match_phrases ?: [])
                ->push($route->name)
                ->filter()
                ->map(fn ($phrase) => Str::lower((string) $phrase));

            return $phrases->contains(fn (string $phrase) => $phrase !== '' && str_contains($needle, $phrase));
        }) ?? $routes->first(function (AiReceptionistRoute $route) use ($needle) {
            return str_contains(Str::lower($route->name), $needle)
                || str_contains($needle, Str::lower($route->name));
        });

        if (! $route) {
            throw new RuntimeException('No matching AI receptionist route was found.');
        }

        return [
            'route' => $this->routePayload($route, includePrivate: false),
        ];
    }

    public function warmTransfer(AiReceptionistSession $session, array $payload): array
    {
        $activeWarmTransfer = $this->activeWarmTransfer($session);
        if ($activeWarmTransfer) {
            return $this->waitForWarmTransferDecision($session, $activeWarmTransfer);
        }

        $route = $this->routeForSession($session, (string) ($payload['route_uuid'] ?? ''));

        if ($route->action_type !== 'transfer' || $route->transfer_type !== 'warm') {
            throw new RuntimeException('The selected route is not configured for warm transfer.');
        }

        if (! in_array($route->destination_type, ['extensions', 'external'], true)) {
            throw new RuntimeException('Warm transfer supports only extensions and external numbers.');
        }

        if (blank($session->freeswitch_uuid)) {
            throw new RuntimeException('FreeSWITCH caller UUID is missing.');
        }

        $callerUuid = $session->freeswitch_uuid;
        $openAiUuid = $this->eslString("uuid_getvar {$callerUuid} bridge_uuid");

        if (blank($openAiUuid) || str_starts_with($openAiUuid, '-ERR')) {
            throw new RuntimeException('Could not find the OpenAI call leg for warm transfer.');
        }

        $consultOpenAiUuid = (string) Str::uuid();
        $recipientUuid = (string) Str::uuid();
        $handoffSummary = trim((string) ($payload['handoff_summary'] ?? ''));

        $warmTransfer = AiReceptionistWarmTransfer::query()->create([
            'warm_transfer_uuid' => (string) Str::uuid(),
            'domain_uuid' => $session->domain_uuid,
            'session_uuid' => $session->session_uuid,
            'route_uuid' => $route->route_uuid,
            'status' => 'dialing',
            'caller_uuid' => $callerUuid,
            'openai_uuid' => $openAiUuid,
            'consult_freeswitch_uuid' => $consultOpenAiUuid,
            'recipient_uuid' => $recipientUuid,
            'destination_type' => $route->destination_type,
            'destination_target' => $route->destination_target,
            'destination_label' => $route->destination_label,
            'handoff_summary' => $handoffSummary,
            'metadata' => [
                'route' => $this->routePayload($route, includePrivate: false),
                'timeout_seconds' => self::WARM_TRANSFER_TIMEOUT_SECONDS,
            ],
            'started_at' => now(),
        ]);

        $session->forceFill([
            'status' => 'warm_transfer_dialing',
            'transfer_type' => 'warm',
            'transfer_target' => $route->destination_target,
            'transfer_label' => $route->destination_label ?: $route->name,
        ])->save();

        $commands = [];
        $holdMusic = $this->warmTransferHoldMusic($callerUuid);
        $commands['set_caller_hold_music'] = $this->eslString("uuid_setvar {$callerUuid} hold_music {$holdMusic}");
        $commands['hold_caller'] = $this->eslString("uuid_hold {$callerUuid}");
        $commands['park_caller'] = $this->eslString("uuid_park {$callerUuid}");
        $commands['caller_moh'] = $this->eslString("uuid_broadcast {$callerUuid} {$holdMusic} aleg");
        $commands['set_openai_hangup_after_bridge'] = $this->eslString("uuid_setvar {$openAiUuid} hangup_after_bridge false");
        $commands['set_openai_park_after_bridge'] = $this->eslString("uuid_setvar {$openAiUuid} park_after_bridge true");
        $commands['park_openai'] = $this->eslString("uuid_park {$openAiUuid}");

        $domainName = $this->domainName($session);
        $callerIdName = $this->warmTransferCallerIdName($session);
        $callerIdNumber = $this->warmTransferCallerIdNumber($session);

        $consultCommand = sprintf(
            'originate %s%s &park()',
            $this->eslOriginateVariables([
                'origination_uuid' => $consultOpenAiUuid,
                'originate_timeout' => 15,
                'hangup_after_bridge' => 'false',
                'absolute_codec_string' => 'PCMU',
                'domain_uuid' => $session->domain_uuid,
                'domain_name' => $domainName,
                'dialed_domain' => $domainName,
                'context' => $domainName,
                'user_context' => $domainName,
                'origination_dialplan' => 'XML',
                'origination_context' => $domainName,
                'effective_caller_id_name' => $callerIdName,
                'effective_caller_id_number' => $callerIdNumber,
                'origination_caller_id_name' => $callerIdName,
                'origination_caller_id_number' => $callerIdNumber,
                'caller_id_name' => $callerIdName,
                'caller_id_number' => $callerIdNumber,
                'ignore_display_updates' => 'true',
                'sip_cid_type' => 'pid',
                'sip_h_X-FSPBX-AI-Receptionist-Mode' => 'consult',
                'sip_h_X-FSPBX-Warm-Transfer-Uuid' => $warmTransfer->warm_transfer_uuid,
                'sip_h_X-FSPBX-FreeSWITCH-Uuid' => $consultOpenAiUuid,
                'sip_h_X-FSPBX-Domain-Uuid' => $session->domain_uuid,
                'sip_h_X-FSPBX-AI-Receptionist-Uuid' => $session->ai_receptionist_uuid,
            ]),
            $this->openAiSipUri($session->receptionist)
        );

        $consultResponse = app(FreeswitchEslService::class)->executeCommand($consultCommand);
        $consultResponseText = is_string($consultResponse) ? $consultResponse : json_encode($consultResponse);
        $commands['originate_consult_openai'] = $consultResponseText;

        if (! is_string($consultResponse) || str_starts_with($consultResponse, '-ERR')) {
            $commands['stop_caller_moh'] = $this->eslString("uuid_break {$callerUuid} all");
            $commands['unhold_caller'] = $this->eslString("uuid_hold off {$callerUuid}");
            $commands['return_caller_to_openai'] = $this->eslString("bgapi uuid_bridge {$callerUuid} {$openAiUuid}");
            $warmTransfer->forceFill([
                'metadata' => array_merge($warmTransfer->metadata ?? [], [
                    'commands' => $commands,
                ]),
            ])->save();
            $notified = $this->failWarmTransfer($warmTransfer, $session, $route, 'failed', $consultResponseText ?: 'Consult agent could not be reached.');

            return $this->warmTransferFailureResponse($route, 'failed', true, $notified);
        }

        $recipientPresenceId = trim((string) $route->destination_target) . '@' . $domainName;
        $recipientCommand = sprintf(
            'originate %s%s &park()',
            $this->eslOriginateVariables([
                'origination_uuid' => $recipientUuid,
                'originate_timeout' => self::WARM_TRANSFER_TIMEOUT_SECONDS,
                'hangup_after_bridge' => 'false',
                'domain_uuid' => $session->domain_uuid,
                'domain_name' => $domainName,
                'dialed_domain' => $domainName,
                'dialed_user' => trim((string) $route->destination_target),
                'presence_id' => $recipientPresenceId,
                'context' => $domainName,
                'user_context' => $domainName,
                'origination_dialplan' => 'XML',
                'origination_context' => $domainName,
                'accountcode' => $domainName,
                'effective_caller_id_name' => $callerIdName,
                'effective_caller_id_number' => $callerIdNumber,
                'origination_caller_id_name' => $callerIdName,
                'origination_caller_id_number' => $callerIdNumber,
                'caller_id_name' => $callerIdName,
                'caller_id_number' => $callerIdNumber,
                'origination_callee_id_name' => $route->destination_label ?: $route->name,
                'origination_callee_id_number' => trim((string) $route->destination_target),
                'callee_id_name' => $route->destination_label ?: $route->name,
                'callee_id_number' => trim((string) $route->destination_target),
                'ignore_display_updates' => 'true',
                'sip_cid_type' => 'pid',
                'sip_invite_domain' => $domainName,
            ]),
            $this->warmTransferDialString($route, $session)
        );

        $response = app(FreeswitchEslService::class)->executeCommand($recipientCommand);
        $responseText = is_string($response) ? $response : json_encode($response);
        $commands['originate_recipient'] = $responseText;

        if (! is_string($response) || str_starts_with($response, '-ERR')) {
            $commands['kill_consult_openai'] = $this->eslString("uuid_kill {$consultOpenAiUuid}");
            $commands['stop_caller_moh'] = $this->eslString("uuid_break {$callerUuid} all");
            $commands['unhold_caller'] = $this->eslString("uuid_hold off {$callerUuid}");
            $commands['return_caller_to_openai'] = $this->eslString("bgapi uuid_bridge {$callerUuid} {$openAiUuid}");
            $warmTransfer->forceFill([
                'metadata' => array_merge($warmTransfer->metadata ?? [], [
                    'commands' => $commands,
                ]),
            ])->save();
            $notified = $this->failWarmTransfer($warmTransfer, $session, $route, 'no_answer', $responseText ?: 'Recipient did not answer.');

            return $this->warmTransferFailureResponse($route, 'no_answer', true, $notified);
        }

        $bridgeResponse = $this->eslString("uuid_bridge {$recipientUuid} {$consultOpenAiUuid}");
        $commands['bridge_recipient_to_consult_openai'] = $bridgeResponse;

        if (str_starts_with($bridgeResponse, '-ERR')) {
            $commands['bridge_recipient_to_consult_openai_bg'] = $this->eslString("bgapi uuid_bridge {$recipientUuid} {$consultOpenAiUuid}");
        }

        $warmTransfer->forceFill([
            'status' => 'consulting',
            'answered_at' => now(),
            'metadata' => array_merge($warmTransfer->metadata ?? [], [
                'originate_response' => $responseText,
                'commands' => $commands,
            ]),
        ])->save();

        $session->forceFill([
            'status' => 'warm_transfer_consulting',
        ])->save();

        return $this->waitForWarmTransferDecision($session, $warmTransfer);
    }

    public function completeWarmTransfer(AiReceptionistSession $session, array $payload = []): array
    {
        $warmTransfer = $this->activeWarmTransfer($session);

        if (! $warmTransfer) {
            $completedWarmTransfer = $this->latestWarmTransfer($session, ['completed']);

            if ($completedWarmTransfer) {
                return [
                    'success' => true,
                    'status' => 'already_completed',
                    'warm_transfer_uuid' => $completedWarmTransfer->warm_transfer_uuid,
                    'message' => 'The caller was already connected to the recipient.',
                    'response_instructions' => 'The warm transfer has already been completed. Do not call complete_warm_transfer again.',
                ];
            }

            $terminalWarmTransfer = $this->latestWarmTransfer($session, ['cancelled', 'declined', 'failed', 'no_answer', 'unavailable']);

            if ($terminalWarmTransfer) {
                return [
                    'success' => false,
                    'status' => $terminalWarmTransfer->status,
                    'warm_transfer_uuid' => $terminalWarmTransfer->warm_transfer_uuid,
                    'message' => 'The warm transfer is no longer active.',
                    'response_instructions' => 'The warm transfer is no longer active. Do not call complete_warm_transfer again.',
                ];
            }

            throw new RuntimeException('No active warm transfer was found.');
        }

        $route = $warmTransfer->route;
        $recipientResponse = trim((string) ($payload['recipient_response'] ?? ''));
        $consultSeconds = $warmTransfer->answered_at
            ? $warmTransfer->answered_at->diffInSeconds(now())
            : 0;

        if ($consultSeconds < self::WARM_TRANSFER_MIN_CONSULT_SECONDS) {
            return $this->warmTransferRecipientConfirmationRequired(
                $warmTransfer,
                $route,
                'The recipient has not had enough time to hear the warm transfer summary.'
            );
        }

        if (! $this->recipientAcceptedWarmTransfer($recipientResponse)) {
            return $this->warmTransferRecipientConfirmationRequired(
                $warmTransfer,
                $route,
                'The recipient must explicitly accept the call before the caller is bridged.'
            );
        }

        $commands = [];
        $commands['stop_caller_moh'] = $this->eslString("uuid_break {$warmTransfer->caller_uuid} all");
        $commands['unhold_caller'] = $this->eslString("uuid_hold off {$warmTransfer->caller_uuid}");

        $recipientUuid = $this->activeWarmTransferRecipientUuid($warmTransfer, remember: true);

        if (! $recipientUuid) {
            throw new RuntimeException('Recipient leg is no longer connected.');
        }

        $commands['resolved_recipient_uuid'] = $recipientUuid;
        $bridgeResponse = $this->eslString("uuid_bridge {$warmTransfer->caller_uuid} {$recipientUuid}");
        $commands['bridge_caller_to_recipient'] = $bridgeResponse;

        if (str_starts_with($bridgeResponse, '-ERR')) {
            $commands['bridge_caller_to_recipient_bg'] = $this->eslString("bgapi uuid_bridge {$warmTransfer->caller_uuid} {$recipientUuid}");
        }

        $commands['kill_openai'] = $this->eslString("bgapi sched_api +1 none uuid_kill {$warmTransfer->openai_uuid}");

        $warmTransfer->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
            'metadata' => array_merge($warmTransfer->metadata ?? [], [
                'recipient_response' => $recipientResponse,
                'complete_commands' => $commands,
            ]),
        ])->save();

        $session->forceFill([
            'status' => 'transferred',
            'ended_at' => now(),
        ])->save();

        return [
            'success' => true,
            'status' => 'completed',
            'message' => 'The caller has been connected to the recipient.',
        ];
    }

    public function cancelWarmTransfer(AiReceptionistSession $session, array $payload): array
    {
        $warmTransfer = $this->activeWarmTransfer($session);

        if (! $warmTransfer) {
            $terminalWarmTransfer = $this->latestWarmTransfer($session, ['completed', 'cancelled', 'declined', 'failed', 'no_answer', 'unavailable']);

            if ($terminalWarmTransfer) {
                return [
                    'success' => $terminalWarmTransfer->status === 'completed',
                    'status' => $terminalWarmTransfer->status === 'completed' ? 'already_completed' : $terminalWarmTransfer->status,
                    'warm_transfer_uuid' => $terminalWarmTransfer->warm_transfer_uuid,
                    'message' => 'The warm transfer is no longer active.',
                    'response_instructions' => 'The warm transfer is no longer active. Do not call cancel_warm_transfer again.',
                ];
            }

            throw new RuntimeException('No active warm transfer was found.');
        }

        $reason = trim((string) ($payload['reason'] ?? 'declined')) ?: 'declined';
        $route = $warmTransfer->route;
        $commands = [];

        if (filled($warmTransfer->recipient_uuid)) {
            $commands['kill_recipient'] = $this->eslString("uuid_kill {$warmTransfer->recipient_uuid}");
        }

        $openAiAlive = $this->channelExists((string) $warmTransfer->openai_uuid);
        $callerAlive = $this->channelExists((string) $warmTransfer->caller_uuid);

        if ($openAiAlive) {
            $commands['break_openai'] = $this->eslString("uuid_break {$warmTransfer->openai_uuid} all");
            $commands['park_openai'] = $this->eslString("uuid_park {$warmTransfer->openai_uuid}");
        }

        if ($callerAlive) {
            $commands['stop_caller_moh'] = $this->eslString("uuid_break {$warmTransfer->caller_uuid} all");
            $commands['unhold_caller'] = $this->eslString("uuid_hold off {$warmTransfer->caller_uuid}");
        }

        if ($callerAlive && $openAiAlive) {
            $bridgeResponse = $this->eslString("uuid_bridge {$warmTransfer->caller_uuid} {$warmTransfer->openai_uuid}");
            $commands['return_caller_to_openai'] = $bridgeResponse;

            if (str_starts_with($bridgeResponse, '-ERR')) {
                $commands['return_caller_to_openai_bg'] = $this->eslString("bgapi uuid_bridge {$warmTransfer->caller_uuid} {$warmTransfer->openai_uuid}");
            }
        }

        $warmTransfer->forceFill([
            'status' => $reason,
            'cancelled_at' => now(),
            'metadata' => array_merge($warmTransfer->metadata ?? [], [
                'cancel_reason' => $reason,
                'cancel_commands' => $commands,
            ]),
        ])->save();

        $session->forceFill([
            'status' => 'started',
        ])->save();

        $notified = $route ? $this->notifyFailedWarmTransfer($session, $route, $warmTransfer, $reason) : false;

        return [
            'success' => false,
            'status' => $reason,
            'team_notified' => $notified,
            'message' => 'The warm transfer was cancelled. Apologize briefly to the caller in the language you have been speaking with them, tell them the appropriate team has been notified, and tell them the team will return the call as soon as possible.',
            'response_instructions' => 'You are speaking to the original caller again. Briefly apologize in the language you have been speaking with the caller, then say that you could not reach that team right now, that you have notified them, and that they will return the call as soon as possible. Do not switch language unless the caller does.',
        ];
    }

    public function checkWarmTransfer(AiReceptionistSession $session, array $payload = []): array
    {
        $warmTransfer = $this->activeWarmTransfer($session);
        $requestedWarmTransferUuid = trim((string) ($payload['warm_transfer_uuid'] ?? ''));

        if (! $warmTransfer || ($requestedWarmTransferUuid !== '' && $warmTransfer->warm_transfer_uuid !== $requestedWarmTransferUuid)) {
            $latestWarmTransfer = $this->latestWarmTransfer($session);

            return [
                'success' => true,
                'active' => false,
                'status' => $latestWarmTransfer?->status ?: 'inactive',
                'warm_transfer_uuid' => $latestWarmTransfer?->warm_transfer_uuid,
            ];
        }

        if (! $this->channelExists((string) $warmTransfer->caller_uuid)) {
            $this->tearDownAbandonedWarmTransfer($warmTransfer);

            return [
                'success' => true,
                'active' => false,
                'status' => 'caller_gone',
                'warm_transfer_uuid' => $warmTransfer->warm_transfer_uuid,
            ];
        }

        if (! $this->warmTransferRecipientStillConnected($warmTransfer)) {
            return [
                'success' => true,
                'active' => false,
                'status' => 'recipient_hangup',
                'reason' => 'unavailable',
                'warm_transfer_uuid' => $warmTransfer->warm_transfer_uuid,
            ];
        }

        $referenceTime = $warmTransfer->answered_at ?: $warmTransfer->started_at;
        if ($referenceTime && $referenceTime->diffInSeconds(now()) >= self::WARM_TRANSFER_TIMEOUT_SECONDS) {
            return [
                'success' => true,
                'active' => false,
                'status' => 'timeout',
                'reason' => 'no_answer',
                'warm_transfer_uuid' => $warmTransfer->warm_transfer_uuid,
            ];
        }

        return [
            'success' => true,
            'active' => true,
            'status' => $warmTransfer->status,
            'warm_transfer_uuid' => $warmTransfer->warm_transfer_uuid,
            'timeout_seconds' => self::WARM_TRANSFER_TIMEOUT_SECONDS,
        ];
    }

    public function consultConfig(AiReceptionistWarmTransfer $warmTransfer): array
    {
        $route = $warmTransfer->route;
        if (! $route) {
            throw new RuntimeException('Warm transfer route could not be resolved.');
        }

        $settings = $this->resolvedSettings($warmTransfer->domain_uuid);
        $providerConfig = $settings['provider_config'] ?? [];
        $instructionBuilder = app(AiReceptionistInstructionBuilder::class);
        $receptionist = $warmTransfer->session?->receptionist;

        return [
            'warm_transfer_uuid' => $warmTransfer->warm_transfer_uuid,
            'domain_uuid' => $warmTransfer->domain_uuid,
            'session_uuid' => $warmTransfer->session_uuid,
            'model' => $providerConfig['openai_realtime_model'] ?? 'gpt-realtime-2',
            'transcription_model' => $providerConfig['openai_realtime_transcription_model'] ?? 'gpt-4o-mini-transcribe',
            'voice' => optional($warmTransfer->session?->receptionist)->openai_voice ?: 'marin',
            'instructions' => $instructionBuilder->consultInstructions($route, (string) $warmTransfer->handoff_summary),
            'initial_message' => $instructionBuilder->consultInitialMessage(
                $route,
                (string) $warmTransfer->handoff_summary,
                $this->receptionistSpokenName($receptionist)
            ),
            'route' => $this->routePayload($route, includePrivate: false),
        ];
    }

    public function startWarmTransferConsult(AiReceptionistWarmTransfer $warmTransfer, array $payload): array
    {
        $warmTransfer->forceFill([
            'consult_openai_call_id' => $this->blankToNull($payload['openai_call_id'] ?? null),
            'consult_sip_call_id' => $this->blankToNull($payload['sip_call_id'] ?? null),
            'consult_freeswitch_uuid' => $this->blankToNull($payload['freeswitch_uuid'] ?? null) ?: $warmTransfer->consult_freeswitch_uuid,
            'metadata' => array_merge($warmTransfer->metadata ?? [], [
                'consult_metadata' => $payload['metadata'] ?? [],
            ]),
        ])->save();

        return [
            'success' => true,
            'status' => $warmTransfer->status,
            'warm_transfer_uuid' => $warmTransfer->warm_transfer_uuid,
        ];
    }

    public function acceptWarmTransfer(AiReceptionistWarmTransfer $warmTransfer, array $payload): array
    {
        $recipientResponse = trim((string) ($payload['recipient_response'] ?? ''));
        if ($recipientResponse === '') {
            throw new RuntimeException('Recipient acceptance response is required.');
        }
        $consultTranscript = trim((string) ($payload['transcript'] ?? ''));

        $session = $warmTransfer->session;
        if (! $session) {
            throw new RuntimeException('Warm transfer session could not be resolved.');
        }

        $commands = [];
        $commands['stop_caller_moh'] = $this->eslString("uuid_break {$warmTransfer->caller_uuid} all");
        $commands['unhold_caller'] = $this->eslString("uuid_hold off {$warmTransfer->caller_uuid}");

        $recipientUuid = $this->activeWarmTransferRecipientUuid($warmTransfer, remember: true);

        if (! $recipientUuid) {
            return $this->declineWarmTransfer($warmTransfer, [
                'reason' => 'unavailable',
                'transcript' => $consultTranscript,
            ]);
        }

        $commands['resolved_recipient_uuid'] = $recipientUuid;
        $commands['bridge_caller_to_recipient'] = $this->eslString("uuid_bridge {$warmTransfer->caller_uuid} {$recipientUuid}");

        if (str_starts_with($commands['bridge_caller_to_recipient'], '-ERR')) {
            $commands['bridge_caller_to_recipient_bg'] = $this->eslString("bgapi uuid_bridge {$warmTransfer->caller_uuid} {$recipientUuid}");
        }

        if (filled($warmTransfer->openai_uuid)) {
            $commands['kill_caller_openai'] = $this->eslString("bgapi sched_api +1 none uuid_kill {$warmTransfer->openai_uuid}");
        }

        if (filled($warmTransfer->consult_freeswitch_uuid)) {
            $commands['kill_consult_openai'] = $this->eslString("bgapi sched_api +1 none uuid_kill {$warmTransfer->consult_freeswitch_uuid}");
        }

        $warmTransfer->forceFill([
            'status' => 'completed',
            'decision' => 'accepted',
            'recipient_response' => $recipientResponse,
            'accepted_at' => now(),
            'completed_at' => now(),
            'metadata' => array_merge($warmTransfer->metadata ?? [], [
                'consult_transcript' => $consultTranscript ?: data_get($warmTransfer->metadata, 'consult_transcript'),
                'accept_commands' => $commands,
            ]),
        ])->save();

        $session->forceFill([
            'status' => 'transferred',
            'ended_at' => now(),
        ])->save();

        return [
            'success' => true,
            'status' => 'completed',
            'decision' => 'accepted',
            'warm_transfer_uuid' => $warmTransfer->warm_transfer_uuid,
            'message' => 'The recipient accepted and the caller was connected.',
        ];
    }

    public function declineWarmTransfer(AiReceptionistWarmTransfer $warmTransfer, array $payload): array
    {
        $reason = trim((string) ($payload['reason'] ?? 'declined')) ?: 'declined';
        $session = $warmTransfer->session;
        $route = $warmTransfer->route;
        $consultTranscript = trim((string) ($payload['transcript'] ?? ''));

        if (! $session || ! $route) {
            throw new RuntimeException('Warm transfer context could not be resolved.');
        }

        $commands = [];
        foreach ($this->warmTransferRecipientKillUuids($warmTransfer) as $index => $recipientUuid) {
            $key = $index === 0 ? 'kill_recipient' : 'kill_recipient_' . ($index + 1);
            $commands[$key] = $this->eslString("uuid_kill {$recipientUuid}");
        }

        if (filled($warmTransfer->consult_freeswitch_uuid)) {
            $commands['kill_consult_openai'] = $this->eslString("uuid_kill {$warmTransfer->consult_freeswitch_uuid}");
        }

        if ($this->channelExists((string) $warmTransfer->caller_uuid)) {
            $commands['stop_caller_moh'] = $this->eslString("uuid_break {$warmTransfer->caller_uuid} all");
            $commands['unhold_caller'] = $this->eslString("uuid_hold off {$warmTransfer->caller_uuid}");
        }

        if ($this->channelExists((string) $warmTransfer->caller_uuid) && $this->channelExists((string) $warmTransfer->openai_uuid)) {
            $commands['return_caller_to_openai'] = $this->eslString("uuid_bridge {$warmTransfer->caller_uuid} {$warmTransfer->openai_uuid}");
            if (str_starts_with($commands['return_caller_to_openai'], '-ERR')) {
                $commands['return_caller_to_openai_bg'] = $this->eslString("bgapi uuid_bridge {$warmTransfer->caller_uuid} {$warmTransfer->openai_uuid}");
            }
        }

        $warmTransfer->forceFill([
            'status' => $reason,
            'decision' => 'declined',
            'failure_reason' => $reason,
            'declined_at' => now(),
            'cancelled_at' => now(),
            'metadata' => array_merge($warmTransfer->metadata ?? [], [
                'consult_transcript' => $consultTranscript ?: data_get($warmTransfer->metadata, 'consult_transcript'),
                'decline_commands' => $commands,
            ]),
        ])->save();

        $session->forceFill([
            'status' => 'started',
        ])->save();

        $notified = $this->notifyFailedWarmTransfer($session, $route, $warmTransfer, $reason);

        return [
            'success' => true,
            'status' => $reason,
            'decision' => 'declined',
            'team_notified' => $notified,
            'warm_transfer_uuid' => $warmTransfer->warm_transfer_uuid,
        ];
    }

    public function sendRouteEmail(AiReceptionistSession $session, array $payload): array
    {
        $route = $this->routeForSession($session, (string) ($payload['route_uuid'] ?? ''));

        $isWarmTransferFallback = $route->action_type === 'transfer' && $route->transfer_type === 'warm';
        if ($route->action_type !== 'email' && ! $isWarmTransferFallback) {
            throw new RuntimeException('The selected route is not configured for email handoff.');
        }

        $recipient = $this->firstEmailAddress($isWarmTransferFallback
            ? ($route->failed_transfer_email_to ?: $route->email_to)
            : $route->email_to);
        if (! $recipient) {
            throw new RuntimeException('No notification email address is configured for this route.');
        }

        $callerName = trim((string) ($payload['caller_name'] ?? $session->caller_id_name ?? ''));
        $callerNumber = trim((string) ($payload['caller_number'] ?? $session->caller_id_number ?? ''));
        $message = trim((string) ($payload['message'] ?? ''));
        $urgency = trim((string) ($payload['urgency'] ?? ''));
        $transcript = trim((string) ($payload['transcript'] ?? $session->transcript ?? ''));
        $logId = (string) Str::uuid();

        Mail::to($recipient)->send(new AiReceptionistRouteNotification([
            'logId' => $logId,
            'domain_uuid' => $session->domain_uuid,
            'email_subject' => $route->email_subject ?: 'AI Receptionist message for ' . $route->name,
            'intro' => 'The AI Receptionist collected a caller message.',
            'route_name' => $route->name,
            'caller_name' => $callerName ?: null,
            'caller_number' => $callerNumber ?: null,
            'message' => $message,
            'urgency' => $urgency ?: null,
            'transcript' => $transcript ?: null,
        ]));

        return [
            'success' => true,
            'status' => 'sent',
            'route' => $this->routePayload($route, includePrivate: false),
            'email_to' => $recipient,
            'email_log_uuid' => $logId,
            'message' => 'The team has been notified by email.',
        ];
    }

    public function sendEmail(AiReceptionistSession $session, array $payload): array
    {
        return $this->sendRouteEmail($session, $payload);
    }

    public function endCall(AiReceptionistSession $session, array $payload = []): array
    {
        $reason = trim((string) ($payload['reason'] ?? 'conversation_complete')) ?: 'conversation_complete';
        $commands = [];

        if (filled($session->freeswitch_uuid)) {
            $bridgeUuid = $this->eslString("uuid_getvar {$session->freeswitch_uuid} bridge_uuid");
            $commands['hangup_caller'] = $this->eslString("uuid_kill {$session->freeswitch_uuid} NORMAL_CLEARING");

            if ($this->isUsableEslValue($bridgeUuid) && $bridgeUuid !== $session->freeswitch_uuid) {
                $commands['hangup_bridge'] = $this->eslString("uuid_kill {$bridgeUuid} NORMAL_CLEARING");
            }
        }

        $session->forceFill([
            'status' => $payload['status'] ?? 'completed',
            'metadata' => array_merge($session->metadata ?? [], [
                'end_call' => [
                    'reason' => $reason,
                    'commands' => $commands,
                    'ended_at' => now()->toISOString(),
                ],
            ]),
            'ended_at' => now(),
        ])->save();

        return [
            'success' => true,
            'status' => 'completed',
            'reason' => $reason,
            'message' => 'The call has been disconnected.',
            'commands' => $commands,
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

    public function recordBuiltInToolRun(AiReceptionistSession $session, string $toolName, array $requestPayload, callable $callback): array
    {
        $run = AiReceptionistToolRun::query()->create([
            'tool_run_uuid' => (string) Str::uuid(),
            'session_uuid' => $session->session_uuid,
            'tool_name' => $toolName,
            'status' => 'started',
            'request_payload' => $requestPayload,
            'started_at' => now(),
        ]);

        try {
            $responsePayload = $callback();

            $run->forceFill([
                'status' => 'completed',
                'response_payload' => $responsePayload,
                'ended_at' => now(),
            ])->save();

            return $responsePayload;
        } catch (RuntimeException $exception) {
            // Domain errors (no route matched, no destination found, transfer
            // preconditions, etc.) are expected outcomes that the AI should be
            // able to react to. Return them as a structured failure response
            // instead of letting them surface as HTTP 500 — otherwise the
            // Realtime controller treats them as fatal and tears down the call.
            $failurePayload = [
                'success' => false,
                'error' => $exception->getMessage(),
                'tool_name' => $toolName,
            ];

            $run->forceFill([
                'status' => 'failed',
                'response_payload' => $failurePayload,
                'error_message' => $exception->getMessage(),
                'ended_at' => now(),
            ])->save();

            return $failurePayload;
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

    public function resolvedSettings(?string $domainUuid = null, bool $includeSecrets = false): array
    {
        $settings = Cache::tags(self::SETTINGS_CACHE_TAG)
            ->remember(
                $this->settingsCacheKey($domainUuid),
                now()->addHours(self::SETTINGS_CACHE_TTL_HOURS),
                fn () => $this->buildResolvedSettings($domainUuid)
            );

        return $settings;
    }

    public function freshResolvedSettings(?string $domainUuid = null, bool $includeSecrets = false): array
    {
        $settings = $this->buildResolvedSettings($domainUuid);

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
        $engine = $domain?->default_engine ?: ($system?->default_engine ?: 'openai_realtime');

        if (! array_key_exists($engine, self::ENGINES)) {
            $engine = 'openai_realtime';
        }

        return [
            'setting_uuid' => $effective?->setting_uuid,
            'scope' => $domain ? 'domain' : 'system',
            'domain_uuid' => $domainUuid,
            'enabled' => (bool) ($domain?->enabled ?? $system?->enabled ?? false),
            'default_engine' => $engine,
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
            'default_engine' => 'openai_realtime',
            'provider_config' => $validated['provider_config'] ?? [],
            'enabled' => $this->toBoolean($validated['enabled'] ?? false),
        ];

        $setting = AiReceptionistSetting::query()->updateOrCreate(
            ['domain_uuid' => $domainUuid],
            $values
        );

        $this->invalidateSettingsCache($domainUuid);
        $this->refreshReceptionistDialplans($domainUuid);

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

    private function saveRoutes(AiReceptionist $receptionist, array $routes): void
    {
        $kept = [];

        foreach (array_values($routes) as $index => $routeData) {
            $name = trim((string) ($routeData['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $route = null;
            $routeUuid = $routeData['route_uuid'] ?? null;
            if ($routeUuid) {
                $route = AiReceptionistRoute::query()
                    ->where('domain_uuid', $receptionist->domain_uuid)
                    ->where('ai_receptionist_uuid', $receptionist->ai_receptionist_uuid)
                    ->whereKey($routeUuid)
                    ->first();
            }

            $route ??= new AiReceptionistRoute();

            $actionType = $routeData['action_type'] ?? 'transfer';
            $transferType = $actionType === 'transfer' ? ($routeData['transfer_type'] ?? 'cold') : null;

            $route->forceFill([
                'route_uuid' => $route->route_uuid ?: (string) Str::uuid(),
                'domain_uuid' => $receptionist->domain_uuid,
                'ai_receptionist_uuid' => $receptionist->ai_receptionist_uuid,
                'name' => $name,
                'match_phrases' => $this->normalizeMatchPhrases($routeData['match_phrases'] ?? []),
                'action_type' => $actionType,
                'transfer_type' => $transferType,
                'destination_type' => $actionType === 'transfer' ? $this->blankToNull($routeData['destination_type'] ?? null) : null,
                'destination_target' => $actionType === 'transfer' ? $this->blankToNull($routeData['destination_target'] ?? null) : null,
                'destination_label' => $actionType === 'transfer' ? $this->blankToNull($routeData['destination_label'] ?? null) : null,
                'email_to' => $this->blankToNull($routeData['email_to'] ?? null),
                'email_subject' => $this->blankToNull($routeData['email_subject'] ?? null),
                'email_instructions' => $this->blankToNull($routeData['email_instructions'] ?? null),
                'notify_on_failed_warm_transfer' => $this->toBoolean($routeData['notify_on_failed_warm_transfer'] ?? false),
                'failed_transfer_email_to' => $this->blankToNull($routeData['failed_transfer_email_to'] ?? null),
                'enabled' => $this->toBoolean($routeData['enabled'] ?? true),
                'sort_order' => (int) ($routeData['sort_order'] ?? $index),
            ])->save();

            $kept[] = $route->route_uuid;
        }

        AiReceptionistRoute::query()
            ->where('domain_uuid', $receptionist->domain_uuid)
            ->where('ai_receptionist_uuid', $receptionist->ai_receptionist_uuid)
            ->when($kept !== [], fn ($query) => $query->whereNotIn('route_uuid', $kept))
            ->delete();
    }

    private function routesForReceptionist(AiReceptionist $receptionist): Collection
    {
        return AiReceptionistRoute::query()
            ->where('domain_uuid', $receptionist->domain_uuid)
            ->where('ai_receptionist_uuid', $receptionist->ai_receptionist_uuid)
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function routePayload(AiReceptionistRoute $route, bool $includePrivate = true): array
    {
        $payload = [
            'route_uuid' => $route->route_uuid,
            'name' => $route->name,
            'match_phrases' => $route->match_phrases ?: [],
            'action_type' => $route->action_type,
            'transfer_type' => $route->transfer_type,
            'destination_type' => $route->destination_type,
            'destination_target' => $route->destination_target,
            'destination_label' => $route->destination_label,
            'email_subject' => $route->email_subject,
            'email_instructions' => $route->email_instructions,
            'notify_on_failed_warm_transfer' => (bool) $route->notify_on_failed_warm_transfer,
        ];

        if ($includePrivate) {
            $payload['email_to'] = $route->email_to;
            $payload['failed_transfer_email_to'] = $route->failed_transfer_email_to;
        }

        return $payload;
    }

    private function routingInstructions(Collection $routes): string
    {
        if ($routes->isEmpty()) {
            return '';
        }

        $lines = [
            'Configured AI Receptionist routes:',
        ];

        foreach ($routes as $route) {
            $phrases = collect($route->match_phrases ?: [])->filter()->implode(', ');

            if ($route->action_type === 'email') {
                $extra = $route->email_instructions ? ' Instructions: ' . $route->email_instructions : '';
                $lines[] = sprintf(
                    '- %s (route_uuid: %s): take a message and email the team. Match phrases: %s. Collect caller name, callback number, and a short message before calling send_email with this route_uuid.%s',
                    $route->name,
                    $route->route_uuid,
                    $phrases ?: $route->name,
                    $extra
                );
                continue;
            }

            $mode = $route->transfer_type === 'warm' ? 'warm transfer' : 'cold transfer';
            $lines[] = sprintf(
                '- %s (route_uuid: %s): %s to %s. Match phrases: %s.',
                $route->name,
                $route->route_uuid,
                $mode,
                $route->destination_label ?: $route->destination_target,
                $phrases ?: $route->name
            );
        }

        $lines[] = 'When caller intent matches a configured route, pick the matching route_uuid from the list above and invoke cold_transfer, warm_transfer, or send_email.';
        $lines[] = 'For cold transfer routes: first speak a brief one-sentence announcement to the caller telling them you are connecting them now, then call cold_transfer with route_uuid set to the route_uuid above.';
        $lines[] = 'For warm transfer routes: first speak a brief one-sentence announcement to the caller telling them you are connecting them now and asking them to hold, then call warm_transfer with the matching route_uuid and a concise handoff_summary.';
        $lines[] = 'If warm_transfer returns no_answer, declined, failed, or unavailable, collect a callback message and call send_email with the same route_uuid.';
        $lines[] = 'For email routes, collect the requested message details, call send_email with the matching route_uuid, then tell the caller the team has been notified and will return the call as soon as possible.';
        $lines[] = 'If no configured route fits the caller\'s request, do not guess a destination. Ask one clarifying question to find a fit among the configured routes, or take a message via send_email on the most appropriate route.';

        return implode("\n", $lines);
    }

    private function routeForSession(AiReceptionistSession $session, string $routeUuid): AiReceptionistRoute
    {
        if ($routeUuid === '') {
            throw new RuntimeException('No route UUID was provided.');
        }

        return AiReceptionistRoute::query()
            ->where('domain_uuid', $session->domain_uuid)
            ->where('ai_receptionist_uuid', $session->ai_receptionist_uuid)
            ->where('enabled', true)
            ->whereKey($routeUuid)
            ->firstOrFail();
    }

    private function activeWarmTransfer(AiReceptionistSession $session): ?AiReceptionistWarmTransfer
    {
        return $this->latestWarmTransfer($session, ['dialing', 'consulting']);
    }

    private function latestWarmTransfer(AiReceptionistSession $session, array $statuses = []): ?AiReceptionistWarmTransfer
    {
        return AiReceptionistWarmTransfer::query()
            ->where('domain_uuid', $session->domain_uuid)
            ->where('session_uuid', $session->session_uuid)
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->latest('created_at')
            ->first();
    }

    private function waitForWarmTransferDecision(AiReceptionistSession $session, AiReceptionistWarmTransfer $warmTransfer): array
    {
        $route = $warmTransfer->route;
        if (! $route) {
            throw new RuntimeException('Warm transfer route could not be resolved.');
        }

        $deadline = now()->addSeconds(self::WARM_TRANSFER_TIMEOUT_SECONDS);

        while (now()->lessThan($deadline)) {
            $warmTransfer->refresh();

            if ($warmTransfer->status === 'completed' || $warmTransfer->decision === 'accepted') {
                return [
                    'success' => true,
                    'status' => 'completed',
                    'decision' => 'accepted',
                    'warm_transfer_uuid' => $warmTransfer->warm_transfer_uuid,
                    'route' => $this->routePayload($route, includePrivate: false),
                    'message' => 'The recipient accepted and the caller was connected.',
                ];
            }

            if ($warmTransfer->decision === 'declined' || in_array($warmTransfer->status, ['declined', 'no_answer', 'unavailable', 'failed', 'recipient_hangup'], true)) {
                return $this->warmTransferFailureResponse(
                    $route,
                    $warmTransfer->failure_reason ?: $warmTransfer->status,
                    true,
                    false
                );
            }

            if (! $this->channelExists((string) $warmTransfer->caller_uuid)) {
                $this->tearDownAbandonedWarmTransfer($warmTransfer);

                return [
                    'success' => false,
                    'status' => 'caller_gone',
                    'warm_transfer_uuid' => $warmTransfer->warm_transfer_uuid,
                    'message' => 'The caller hung up during the warm transfer.',
                ];
            }

            if (filled($warmTransfer->consult_freeswitch_uuid) && ! $this->channelExists((string) $warmTransfer->consult_freeswitch_uuid)) {
                $this->declineWarmTransfer($warmTransfer, ['reason' => 'consult_unavailable']);

                return $this->warmTransferFailureResponse($route, 'consult_unavailable', true, false);
            }

            if (! $this->warmTransferRecipientStillConnected($warmTransfer)) {
                $this->declineWarmTransfer($warmTransfer, ['reason' => 'unavailable']);

                return $this->warmTransferFailureResponse($route, 'unavailable', true, false);
            }

            sleep(self::WARM_TRANSFER_POLL_INTERVAL_SECONDS);
        }

        $this->declineWarmTransfer($warmTransfer, ['reason' => 'no_answer']);

        return $this->warmTransferFailureResponse($route, 'no_answer', true, false);
    }

    private function failWarmTransfer(
        AiReceptionistWarmTransfer $warmTransfer,
        AiReceptionistSession $session,
        AiReceptionistRoute $route,
        string $status,
        ?string $error = null
    ): bool {
        $warmTransfer->forceFill([
            'status' => $status,
            'error_message' => $error,
            'cancelled_at' => now(),
        ])->save();

        $session->forceFill([
            'status' => 'started',
            'error_message' => null,
        ])->save();

        return $this->notifyFailedWarmTransfer($session, $route, $warmTransfer, $status);
    }

    private function warmTransferFailureResponse(AiReceptionistRoute $route, string $status, bool $returnedToAi, bool $teamNotified): array
    {
        return [
            'success' => false,
            'status' => $status,
            'route' => $this->routePayload($route, includePrivate: false),
            'team_notified' => $teamNotified,
            'returned_to_ai' => $returnedToAi,
            'message' => 'The recipient could not be reached. Apologize briefly, collect a callback message, then call send_email for this same route_uuid.',
            'response_instructions' => 'You are speaking to the original caller again. Briefly apologize in the language you have been speaking with the caller, say that you could not reach that team right now, then collect their name, callback number, and a short message. After collecting the message, call send_email with the same route_uuid. Do not switch language unless the caller does.',
        ];
    }

    private function recipientConsultInstructions(AiReceptionistRoute $route, string $handoffSummary): string
    {
        $team = $route->destination_label ?: $route->name;
        $summary = $handoffSummary !== '' ? $handoffSummary : 'The caller asked to be connected.';

        // Keep this VERY tight. OpenAI Realtime treats per-response `instructions` as
        // a script for the next response, and the model will paraphrase any
        // operational rules it sees here into spoken narration to the recipient.
        // Operational rules (when to call which tool, language, etc.) live in the
        // session-level consult persona that the controller installs via
        // session.update — do NOT duplicate them here.
        //
        // This response is sent with conversation="none", so the model only sees
        // these instructions — no prior caller-mode history. That is intentional:
        // when the model can see the caller-mode conversation it tends to emit a
        // stray "still trying to reach support" status line first, which the
        // recipient ends up hearing through the bridged audio path.
        return implode("\n", [
            'The recipient just picked up the phone. Speak directly to the recipient now. The original caller is on hold and cannot hear you.',
            'Your VERY FIRST spoken word must be the introduction below. Do NOT say anything before it. Do NOT say "still trying", "please hold", "one moment", or any status update; you are not talking to the original caller anymore.',
            'Produce exactly one short spoken turn that does these four things in this order:',
            '1. Identify yourself as the AI receptionist.',
            "2. Name the team you are reaching for: {$team}.",
            "3. State the handoff in one sentence: {$summary}",
            '4. Ask whether the recipient will accept the call.',
            'Stop speaking immediately after the question. Do not narrate, do not repeat yourself, do not add a status update.',
        ]);
    }

    private function warmTransferRecipientConfirmationRequired(
        AiReceptionistWarmTransfer $warmTransfer,
        ?AiReceptionistRoute $route,
        string $message
    ): array {
        return [
            'success' => false,
            'status' => 'recipient_confirmation_required',
            'warm_transfer_uuid' => $warmTransfer->warm_transfer_uuid,
            'message' => $message,
            'response_instructions' => $route
                ? $this->recipientConsultInstructions($route, (string) $warmTransfer->handoff_summary)
                : 'You are speaking to the transfer recipient now. Brief them, ask if they accept the call, and wait for their spoken acceptance before calling complete_warm_transfer.',
        ];
    }

    private function recipientAcceptedWarmTransfer(string $recipientResponse): bool
    {
        // The AI calls complete_warm_transfer only when the recipient accepts and
        // cancel_warm_transfer when they decline. We trust the AI's interpretation
        // (which works in any language the caller and recipient may be speaking)
        // and only require that it captured the recipient's spoken response, so
        // we have proof the recipient was given a chance to reply.
        return trim($recipientResponse) !== '';
    }

    private function warmTransferRecipientStillConnected(AiReceptionistWarmTransfer $warmTransfer): bool
    {
        return $this->activeWarmTransferRecipientUuid($warmTransfer) !== null;
    }

    private function activeWarmTransferRecipientUuid(AiReceptionistWarmTransfer $warmTransfer, bool $remember = false): ?string
    {
        $excludedUuids = array_filter(array_map('strval', [
            $warmTransfer->caller_uuid,
            $warmTransfer->openai_uuid,
            $warmTransfer->consult_freeswitch_uuid,
        ]));

        $candidates = [];

        if (filled($warmTransfer->consult_freeswitch_uuid)) {
            $consultPeerUuid = $this->channelVariable((string) $warmTransfer->consult_freeswitch_uuid, 'bridge_uuid');

            if ($consultPeerUuid !== null) {
                $candidates[] = $consultPeerUuid;
            }
        }

        if (filled($warmTransfer->recipient_uuid)) {
            $candidates[] = (string) $warmTransfer->recipient_uuid;
        }

        foreach (array_unique($candidates) as $candidateUuid) {
            $recipientUuid = $this->resolveWarmTransferRecipientUuid($candidateUuid, $excludedUuids);

            if ($recipientUuid === null) {
                continue;
            }

            if ($remember && $recipientUuid !== (string) $warmTransfer->recipient_uuid) {
                $warmTransfer->forceFill([
                    'recipient_uuid' => $recipientUuid,
                    'metadata' => array_merge($warmTransfer->metadata ?? [], [
                        'resolved_recipient_uuid' => $recipientUuid,
                    ]),
                ])->save();
            }

            return $recipientUuid;
        }

        return null;
    }

    private function resolveWarmTransferRecipientUuid(string $uuid, array $excludedUuids = [], int $depth = 0): ?string
    {
        $uuid = trim($uuid);

        if ($uuid === '' || in_array($uuid, $excludedUuids, true) || ! $this->channelExists($uuid)) {
            return null;
        }

        if ($depth >= 4) {
            return $uuid;
        }

        foreach (['other_loopback_leg_uuid', 'loopback_other_leg_uuid', 'other_loopback_uuid', 'other_loopback_from_uuid', 'bridge_uuid'] as $variable) {
            $candidateUuid = $this->channelVariable($uuid, $variable);

            if ($candidateUuid === null || $candidateUuid === $uuid || in_array($candidateUuid, $excludedUuids, true)) {
                continue;
            }

            $resolvedUuid = $this->resolveWarmTransferRecipientUuid(
                $candidateUuid,
                array_merge($excludedUuids, [$uuid]),
                $depth + 1
            );

            if ($resolvedUuid !== null) {
                return $resolvedUuid;
            }
        }

        return $uuid;
    }

    /**
     * @return array<int, string>
     */
    private function warmTransferRecipientKillUuids(AiReceptionistWarmTransfer $warmTransfer): array
    {
        $excludedUuids = array_filter(array_map('strval', [
            $warmTransfer->caller_uuid,
            $warmTransfer->openai_uuid,
            $warmTransfer->consult_freeswitch_uuid,
        ]));

        $uuids = [];
        $activeRecipientUuid = $this->activeWarmTransferRecipientUuid($warmTransfer);

        if ($activeRecipientUuid !== null) {
            $uuids[] = $activeRecipientUuid;
        }

        if (filled($warmTransfer->recipient_uuid)) {
            $uuids[] = (string) $warmTransfer->recipient_uuid;
        }

        return array_values(array_filter(array_unique($uuids), function (string $uuid) use ($excludedUuids): bool {
            return ! in_array($uuid, $excludedUuids, true) && $this->channelExists($uuid);
        }));
    }

    private function tearDownAbandonedWarmTransfer(AiReceptionistWarmTransfer $warmTransfer): array
    {
        $commands = [];

        foreach ($this->warmTransferRecipientKillUuids($warmTransfer) as $index => $recipientUuid) {
            $key = $index === 0 ? 'kill_recipient' : 'kill_recipient_' . ($index + 1);
            $commands[$key] = $this->eslString("uuid_kill {$recipientUuid}");
        }

        if (filled($warmTransfer->openai_uuid) && $this->channelExists((string) $warmTransfer->openai_uuid)) {
            $commands['kill_openai'] = $this->eslString("uuid_kill {$warmTransfer->openai_uuid}");
        }

        $warmTransfer->forceFill([
            'status' => 'caller_abandoned',
            'error_message' => 'Caller hung up while on hold during warm transfer.',
            'cancelled_at' => now(),
            'metadata' => array_merge($warmTransfer->metadata ?? [], [
                'caller_abandoned_commands' => $commands,
            ]),
        ])->save();

        return $commands;
    }

    private function notifyFailedWarmTransfer(
        AiReceptionistSession $session,
        AiReceptionistRoute $route,
        AiReceptionistWarmTransfer $warmTransfer,
        string $status
    ): bool {
        if (! $route->notify_on_failed_warm_transfer) {
            return false;
        }

        $recipient = $this->firstEmailAddress($route->failed_transfer_email_to ?: $route->email_to);
        if (! $recipient) {
            return false;
        }

        Mail::to($recipient)->send(new AiReceptionistRouteNotification([
            'logId' => (string) Str::uuid(),
            'domain_uuid' => $session->domain_uuid,
            'email_subject' => 'Missed AI Receptionist warm transfer: ' . $route->name,
            'intro' => 'The AI Receptionist could not complete a warm transfer.',
            'route_name' => $route->name,
            'caller_name' => $session->caller_id_name,
            'caller_number' => $session->caller_id_number,
            'failure_status' => $status,
            'handoff_summary' => $warmTransfer->handoff_summary,
            'transcript' => $session->transcript,
        ]));

        return true;
    }

    private function warmTransferDialString(AiReceptionistRoute $route, AiReceptionistSession $session): string
    {
        $target = trim((string) $route->destination_target);
        if ($target === '') {
            throw new RuntimeException('Warm transfer destination is missing.');
        }

        $domainName = $this->domainName($session);

        if (blank($domainName)) {
            throw new RuntimeException('Domain could not be resolved.');
        }

        if ($route->destination_type === 'extensions') {
            return 'user/' . $target . '@' . $domainName;
        }

        return 'loopback/' . $target . '/' . $domainName . '/XML';
    }

    private function domainName(AiReceptionistSession $session): string
    {
        $domainName = trim((string) $session->domain?->domain_name);
        if ($domainName !== '') {
            return $domainName;
        }

        return (string) Domain::query()
            ->where('domain_uuid', $session->domain_uuid)
            ->value('domain_name');
    }

    private function warmTransferCallerIdName(AiReceptionistSession $session): string
    {
        return trim((string) ($session->receptionist?->name ?: 'AI Receptionist')) ?: 'AI Receptionist';
    }

    private function warmTransferCallerIdNumber(AiReceptionistSession $session): string
    {
        $number = trim((string) ($session->caller_id_number ?: $session->receptionist?->extension ?: $session->destination_number));

        return $number !== '' ? $number : 'AI';
    }

    private function warmTransferHoldMusic(string $callerUuid): string
    {
        $channelHoldMusic = $this->eslString("uuid_getvar {$callerUuid} hold_music");
        if ($this->isUsableEslValue($channelHoldMusic)) {
            return $channelHoldMusic;
        }

        $globalHoldMusic = $this->eslString('global_getvar hold_music');
        if ($this->isUsableEslValue($globalHoldMusic)) {
            return $globalHoldMusic;
        }

        return 'local_stream://default';
    }

    private function isUsableEslValue(string $value): bool
    {
        return filled($value)
            && $value !== '_undef_'
            && ! str_starts_with($value, '-ERR');
    }

    private function channelVariable(string $uuid, string $variable): ?string
    {
        $uuid = trim($uuid);
        $variable = trim($variable);

        if ($uuid === '' || $variable === '') {
            return null;
        }

        $value = $this->eslString("uuid_getvar {$uuid} {$variable}");

        return $this->isUsableEslValue($value) ? $value : null;
    }

    private function channelExists(string $uuid): bool
    {
        if (blank($uuid)) {
            return false;
        }

        $response = Str::lower($this->eslString("uuid_exists {$uuid}"));

        return $response === 'true'
            || $response === '+ok true'
            || $response === '1'
            || str_contains($response, 'true');
    }

    private function eslString(string $command): string
    {
        $response = app(FreeswitchEslService::class)->executeCommand($command);

        if (is_string($response)) {
            return trim($response);
        }

        if ($response === null) {
            return '';
        }

        return trim(json_encode($response) ?: '');
    }

    private function eslQuote(string $value): string
    {
        return "'" . str_replace("'", "\\'", $value) . "'";
    }

    private function eslOriginateVariables(array $variables): string
    {
        $pairs = [];

        foreach ($variables as $key => $value) {
            $key = trim((string) $key);
            $value = trim((string) $value);

            if ($key === '' || $value === '') {
                continue;
            }

            $pairs[] = $key . '=' . $this->eslQuote($value);
        }

        return '{' . implode(',', $pairs) . '}';
    }

    private function firstEmailAddress(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        foreach (preg_split('/[,;\s]+/', $value) ?: [] as $candidate) {
            $candidate = trim($candidate);
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }

    private function receptionistSpokenName(?AiReceptionist $receptionist): ?string
    {
        if (! $receptionist) {
            return null;
        }

        foreach ([$receptionist->initial_message, $receptionist->system_prompt] as $source) {
            $text = trim(strip_tags((string) $source));
            if ($text === '') {
                continue;
            }

            if (preg_match('/\bthis is\s+([A-Z][A-Za-z\' -]{1,40})\b/i', $text, $matches)) {
                return trim($matches[1]);
            }

            if (preg_match('/\byou are\s+\**([A-Z][A-Za-z\' -]{1,40})\**(?=[,.\s]|$)/i', $text, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    private function normalizeMatchPhrases(mixed $phrases): array
    {
        if (is_string($phrases)) {
            $phrases = preg_split('/[\n,]+/', $phrases) ?: [];
        }

        return collect(Arr::wrap($phrases))
            ->map(function ($phrase) {
                if (is_array($phrase)) {
                    $phrase = $phrase['value'] ?? $phrase['label'] ?? $phrase['name'] ?? '';
                }

                return trim((string) $phrase);
            })
            ->filter()
            ->unique(fn (string $phrase) => Str::lower($phrase))
            ->values()
            ->all();
    }

    private function saveDialplan(AiReceptionist $receptionist, bool $isNew): void
    {
        $dialplan = Dialplans::query()
            ->where('dialplan_uuid', $receptionist->dialplan_uuid)
            ->first() ?? new Dialplans();
        $domainName = optional($receptionist->domain)->domain_name ?: session('domain_name');

        $dialplan->forceFill([
            'domain_uuid' => $receptionist->domain_uuid ?: session('domain_uuid'),
            'dialplan_uuid' => $receptionist->dialplan_uuid,
            'app_uuid' => self::APP_UUID,
            'dialplan_name' => $receptionist->name,
            'dialplan_number' => $receptionist->extension,
            'dialplan_context' => $domainName,
            'dialplan_continue' => 'false',
            'dialplan_xml' => $this->dialplanXml($receptionist),
            'dialplan_order' => '235',
            'dialplan_enabled' => 'true',
            'dialplan_description' => $receptionist->description,
            $isNew || ! $dialplan->exists ? 'insert_date' : 'update_date' => now(),
            $isNew || ! $dialplan->exists ? 'insert_user' : 'update_user' => session('user_uuid'),
        ])->save();
    }

    private function refreshReceptionistDialplans(?string $domainUuid): void
    {
        $query = AiReceptionist::query()->with('domain');

        if (filled($domainUuid)) {
            $query->where('domain_uuid', $domainUuid);
        } else {
            $query->where('domain_uuid', session('domain_uuid'));
        }

        $query->each(function (AiReceptionist $receptionist) {
            if (blank($receptionist->dialplan_uuid)) {
                return;
            }

            $this->saveDialplan($receptionist, false);
            $this->clearDialplanCache($receptionist);
        });
    }

    private function dialplanXml(AiReceptionist $receptionist): string
    {
        $bridgeTarget = $this->openAiSipBridgeTarget($receptionist);
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

    private function openAiSipBridgeTarget(AiReceptionist $receptionist): string
    {
        return '{absolute_codec_string=PCMU}' . $this->openAiSipUri($receptionist);
    }

    private function openAiSipUri(AiReceptionist $receptionist): string
    {
        $settings = $this->resolvedSettings($receptionist->domain_uuid);
        $providerConfig = $settings['provider_config'] ?? [];
        $configuredTarget = trim((string) ($providerConfig['openai_sip_bridge_target'] ?? ''));

        if ($configuredTarget !== '') {
            return preg_replace('/^\{[^}]*\}/', '', $configuredTarget) ?: $configuredTarget;
        }

        $projectId = trim((string) ($providerConfig['openai_project_id'] ?? ''));

        if ($projectId !== '') {
            return 'sofia/external/sip:' . $projectId . '@sip.api.openai.com;transport=tls';
        }

        return '${ai_receptionist_openai_sip_uri}';
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
