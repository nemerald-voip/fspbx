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
    private const WARM_TRANSFER_MIN_CONSULT_SECONDS = 6;

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
            $route = $activeWarmTransfer->route ?: $this->routeForSession($session, (string) ($payload['route_uuid'] ?? ''));

            return [
                'success' => true,
                'status' => 'recipient_connected',
                'warm_transfer_uuid' => $activeWarmTransfer->warm_transfer_uuid,
                'route' => $this->routePayload($route, includePrivate: false),
                'handoff_summary' => $activeWarmTransfer->handoff_summary,
                'instructions' => 'An active warm transfer is already connected. Do not call warm_transfer_call again. Brief the recipient live and ask them to accept or decline.',
                'response_instructions' => $this->recipientConsultInstructions($route, (string) $activeWarmTransfer->handoff_summary),
            ];
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

        $recipientUuid = (string) Str::uuid();
        $dialString = $this->warmTransferDialString($route, $session);
        $handoffSummary = trim((string) ($payload['handoff_summary'] ?? ''));

        $warmTransfer = AiReceptionistWarmTransfer::query()->create([
            'warm_transfer_uuid' => (string) Str::uuid(),
            'domain_uuid' => $session->domain_uuid,
            'session_uuid' => $session->session_uuid,
            'route_uuid' => $route->route_uuid,
            'status' => 'dialing',
            'caller_uuid' => $callerUuid,
            'openai_uuid' => $openAiUuid,
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
        $commands['park_openai'] = $this->eslString("uuid_park {$openAiUuid}");

        $domainName = $this->domainName($session);
        $recipientPresenceId = trim((string) $route->destination_target) . '@' . $domainName;

        $originateCommand = sprintf(
            'originate {origination_uuid=%s,originate_timeout=%d,hangup_after_bridge=false,domain_uuid=%s,domain_name=%s,dialed_domain=%s,presence_id=%s,context=%s,user_context=%s,effective_caller_id_name=%s,effective_caller_id_number=%s,origination_caller_id_name=%s,origination_caller_id_number=%s}%s &park()',
            $recipientUuid,
            self::WARM_TRANSFER_TIMEOUT_SECONDS,
            $session->domain_uuid,
            $this->eslQuote($domainName),
            $this->eslQuote($domainName),
            $this->eslQuote($recipientPresenceId),
            $this->eslQuote($domainName),
            $this->eslQuote($domainName),
            $this->eslQuote('AI Receptionist'),
            $this->eslQuote($session->caller_id_number ?: $session->destination_number ?: 'AI'),
            $this->eslQuote('AI Receptionist'),
            $this->eslQuote($session->caller_id_number ?: $session->destination_number ?: 'AI'),
            $dialString
        );

        $response = app(FreeswitchEslService::class)->executeCommand($originateCommand);
        $responseText = is_string($response) ? $response : json_encode($response);
        $commands['originate_recipient'] = $responseText;

        if (! is_string($response) || str_starts_with($response, '-ERR')) {
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

        $commands['bridge_recipient_to_openai'] = $this->eslString("bgapi uuid_bridge {$recipientUuid} {$openAiUuid}");

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

        return [
            'success' => true,
            'status' => 'recipient_connected',
            'warm_transfer_uuid' => $warmTransfer->warm_transfer_uuid,
            'route' => $this->routePayload($route, includePrivate: false),
            'handoff_summary' => $handoffSummary,
            'instructions' => 'Brief the recipient live. Wait for the recipient to explicitly accept or decline. If they accept, call complete_warm_transfer with their spoken response. If they decline, call cancel_warm_transfer.',
            'response_instructions' => $this->recipientConsultInstructions($route, $handoffSummary),
        ];
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
        $commands['bridge_caller_to_recipient'] = $this->eslString("bgapi uuid_bridge {$warmTransfer->caller_uuid} {$warmTransfer->recipient_uuid}");
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

        if (filled($warmTransfer->recipient_uuid)) {
            $this->eslString("uuid_kill {$warmTransfer->recipient_uuid}");
        }

        $commands = [];
        $commands['stop_caller_moh'] = $this->eslString("uuid_break {$warmTransfer->caller_uuid} all");
        $commands['unhold_caller'] = $this->eslString("uuid_hold off {$warmTransfer->caller_uuid}");
        $commands['return_caller_to_openai'] = $this->eslString("bgapi uuid_bridge {$warmTransfer->caller_uuid} {$warmTransfer->openai_uuid}");

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
            'message' => 'The warm transfer was cancelled. Apologize briefly, tell the caller the appropriate team has been notified, and tell them the team will return the call as soon as possible.',
            'response_instructions' => 'You are speaking to the original caller again. Briefly apologize and say: "I’m sorry, I couldn’t reach that team right now. I’ve notified them and they’ll return your call as soon as possible."',
        ];
    }

    public function sendRouteEmail(AiReceptionistSession $session, array $payload): array
    {
        $route = $this->routeForSession($session, (string) ($payload['route_uuid'] ?? ''));

        if ($route->action_type !== 'email') {
            throw new RuntimeException('The selected route is not configured for email handoff.');
        }

        $recipient = $this->firstEmailAddress($route->email_to);
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
                    '- %s: take a message and email the team. Match phrases: %s. Collect caller name, callback number, and a short message before calling send_route_email.%s',
                    $route->name,
                    $phrases ?: $route->name,
                    $extra
                );
                continue;
            }

            $mode = $route->transfer_type === 'warm' ? 'warm transfer' : 'cold transfer';
            $lines[] = sprintf(
                '- %s: %s to %s. Match phrases: %s.',
                $route->name,
                $mode,
                $route->destination_label ?: $route->destination_target,
                $phrases ?: $route->name
            );
        }

        $lines[] = 'When caller intent matches a configured route, call resolve_route first.';
        $lines[] = 'For cold transfer routes, call transfer_call using the returned route destination_type and destination_target.';
        $lines[] = 'For warm transfer routes, tell the caller you will try the team, then call warm_transfer_call with a concise handoff_summary. Brief the recipient live, ask them to say accept or decline, then call complete_warm_transfer or cancel_warm_transfer.';
        $lines[] = 'If warm_transfer_call returns no_answer, declined, failed, or unavailable, apologize briefly, tell the caller the appropriate team has been notified, and tell them the team will return the call as soon as possible.';
        $lines[] = 'For email routes, collect the requested message details, call send_route_email, then tell the caller the team has been notified and will return the call as soon as possible.';

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
            'message' => 'The recipient could not be reached within 60 seconds. Apologize briefly, tell the caller the appropriate team has been notified, and tell them the team will return the call as soon as possible.',
            'response_instructions' => 'You are speaking to the original caller again. Briefly apologize and say: "I’m sorry, I couldn’t reach that team right now. I’ve notified them and they’ll return your call as soon as possible."',
        ];
    }

    private function recipientConsultInstructions(AiReceptionistRoute $route, string $handoffSummary): string
    {
        $team = $route->destination_label ?: $route->name;
        $summary = $handoffSummary !== '' ? $handoffSummary : 'The caller asked to be connected.';

        return implode("\n", [
            'Do not call warm_transfer_call again. The warm transfer is already active.',
            "You are speaking to the transfer recipient for {$team} now, not the original caller.",
            'The original caller is parked/on hold and cannot hear this consult conversation.',
            'Do not say you are still trying to connect the caller.',
            'Do not ask the recipient how you can help them as if they were the caller.',
            'Say only this next:',
            "\"Hi, this is the AI receptionist. I have a caller for {$team}. {$summary} Would you like to accept the call?\"",
            'Wait for the recipient to answer.',
            'If the recipient clearly accepts, call complete_warm_transfer and include their exact spoken acceptance in recipient_response.',
            'If the recipient declines or cannot take the call, call cancel_warm_transfer with reason "declined".',
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
        if ($recipientResponse === '') {
            return false;
        }

        $response = Str::lower($recipientResponse);
        $acceptedPhrases = [
            'accept',
            'accepted',
            'yes',
            'yeah',
            'yep',
            'sure',
            'okay',
            'ok',
            'connect',
            'put them through',
            'send them through',
            'transfer them',
        ];

        return collect($acceptedPhrases)->contains(
            fn (string $phrase) => str_contains($response, $phrase)
        );
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

        return 'loopback/' . $target . '/' . $domainName;
    }

    private function domainName(AiReceptionistSession $session): string
    {
        return (string) Domain::query()
            ->where('domain_uuid', $session->domain_uuid)
            ->value('domain_name');
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

    private function normalizeMatchPhrases(mixed $phrases): array
    {
        if (is_string($phrases)) {
            $phrases = preg_split('/[\n,]+/', $phrases) ?: [];
        }

        return collect(Arr::wrap($phrases))
            ->map(fn ($phrase) => trim((string) $phrase))
            ->filter()
            ->unique()
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
        $settings = $this->resolvedSettings($receptionist->domain_uuid);
        $providerConfig = $settings['provider_config'] ?? [];
        $configuredTarget = trim((string) ($providerConfig['openai_sip_bridge_target'] ?? ''));

        if ($configuredTarget !== '') {
            return $configuredTarget;
        }

        $projectId = trim((string) ($providerConfig['openai_project_id'] ?? ''));

        if ($projectId !== '') {
            return '{absolute_codec_string=PCMU}sofia/external/sip:' . $projectId . '@sip.api.openai.com;transport=tls';
        }

        return '{absolute_codec_string=PCMU}${ai_receptionist_openai_sip_uri}';
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
