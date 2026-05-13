<?php

namespace App\Services;

use App\Models\CallCenterAgents;
use App\Models\CallCenterQueueAgents;
use App\Models\CallCenterQueues;
use App\Models\Domain;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\FusionCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BasicQueueService
{
    private const APP_UUID = '95788e50-9500-079e-2807-fd530b0ea370';

    public function saveQueue(array $validated, ?CallCenterQueues $queue = null): CallCenterQueues
    {
        return DB::transaction(function () use ($validated, $queue) {
            $queue ??= new CallCenterQueues();
            $isNew = ! $queue->exists;
            $queueUuid = $queue->call_center_queue_uuid ?: (string) Str::uuid();
            $dialplanUuid = $queue->dialplan_uuid ?: (string) Str::uuid();

            $queue->forceFill([
                'domain_uuid' => session('domain_uuid'),
                'call_center_queue_uuid' => $queueUuid,
                'dialplan_uuid' => $dialplanUuid,
                'queue_name' => $validated['queue_name'],
                'queue_extension' => $validated['queue_extension'],
                'queue_greeting' => $validated['queue_greeting'] ?? '',
                'queue_strategy' => $validated['queue_strategy'],
                'queue_moh_sound' => $validated['queue_moh_sound'] ?? 'local_stream://default',
                'queue_record_template' => $queue->queue_record_template ?: 'true',
                'queue_time_base_score' => $queue->queue_time_base_score ?: 'system',
                'queue_max_wait_time' => (string) ($validated['queue_max_wait_time'] ?? 0),
                'queue_max_wait_time_with_no_agent' => (string) ($validated['queue_max_wait_time_with_no_agent'] ?? 90),
                'queue_max_wait_time_with_no_agent_time_reached' => $queue->queue_max_wait_time_with_no_agent_time_reached ?: '5',
                'queue_tier_rules_apply' => $validated['queue_tier_rules_apply'] ?? 'false',
                'queue_tier_rule_wait_second' => $queue->queue_tier_rule_wait_second ?: '30',
                'queue_tier_rule_wait_multiply_level' => $queue->queue_tier_rule_wait_multiply_level ?: 'false',
                'queue_tier_rule_no_agent_no_wait' => $queue->queue_tier_rule_no_agent_no_wait ?: 'false',
                'queue_discard_abandoned_after' => $queue->queue_discard_abandoned_after ?: '900',
                'queue_abandoned_resume_allowed' => $queue->queue_abandoned_resume_allowed ?: 'false',
                'queue_cid_prefix' => $this->blankToNull($validated['queue_cid_prefix'] ?? null),
                'queue_timeout_action' => $this->buildQueueTimeoutAction($validated, session('domain_name')),
                'queue_description' => $this->blankToNull($validated['queue_description'] ?? null),
            ]);

            $queueChanged = $isNew || $queue->isDirty();

            if ($queueChanged) {
                $queue->forceFill([
                    $isNew ? 'insert_date' : 'update_date' => now(),
                    $isNew ? 'insert_user' : 'update_user' => session('user_uuid'),
                ])->save();
            }

            $tierSync = $this->syncTiers($queue, $validated['tiers'] ?? []);
            if ($queueChanged) {
                $this->saveDialplan($queue, $isNew);
            }
            $this->clearCaches();

            $reloadXml = ! (
                ! $queueChanged
                && $tierSync['removed']->isNotEmpty()
                && ! $tierSync['added']
                && ! $tierSync['updated']
            );

            $isNew
                ? $this->loadRuntimeQueues(collect([$queue]))
                : $this->reloadRuntimeQueues(collect([$queue]), $reloadXml);

            return $queue;
        });
    }

    public function saveAgent(array $validated, ?CallCenterAgents $agent = null): CallCenterAgents
    {
        return DB::transaction(function () use ($validated, $agent) {
            $agent ??= new CallCenterAgents();
            $isNew = ! $agent->exists;

            $agent->forceFill([
                'domain_uuid' => session('domain_uuid'),
                'call_center_agent_uuid' => $agent->call_center_agent_uuid ?: (string) Str::uuid(),
                'agent_name' => $validated['agent_name'],
                'agent_type' => $validated['agent_type'],
                'agent_call_timeout' => (string) $validated['agent_call_timeout'],
                'agent_id' => $this->blankToNull($validated['agent_id'] ?? null),
                'agent_password' => $this->blankToNull($validated['agent_password'] ?? null),
                'agent_contact' => str_replace('$', '', $validated['agent_contact']),
                'agent_status' => $validated['agent_status'] ?? 'Logged Out',
                'agent_no_answer_delay_time' => (string) ($validated['agent_no_answer_delay_time'] ?? 30),
                'agent_max_no_answer' => (string) ($validated['agent_max_no_answer'] ?? 0),
                'agent_wrap_up_time' => (string) ($validated['agent_wrap_up_time'] ?? 10),
                'agent_reject_delay_time' => (string) ($validated['agent_reject_delay_time'] ?? 90),
                'agent_busy_delay_time' => (string) ($validated['agent_busy_delay_time'] ?? 90),
                'agent_record' => $validated['agent_record'] ?? 'true',
                $isNew ? 'insert_date' : 'update_date' => now(),
                $isNew ? 'insert_user' : 'update_user' => session('user_uuid'),
            ])->save();

            CallCenterQueueAgents::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->where('call_center_agent_uuid', $agent->call_center_agent_uuid)
                ->update([
                    'agent_name' => $agent->agent_name,
                    'update_date' => now(),
                    'update_user' => session('user_uuid'),
                ]);

            $this->clearCaches();
            $this->refreshRuntimeAgent($agent);

            return $agent;
        });
    }

    public function deleteQueues(Collection $queues): int
    {
        return DB::transaction(function () use ($queues) {
            $this->unloadRuntimeQueues($queues);

            $queueUuids = $queues->pluck('call_center_queue_uuid');
            $dialplanUuids = $queues->pluck('dialplan_uuid')->filter();

            CallCenterQueueAgents::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('call_center_queue_uuid', $queueUuids)
                ->delete();

            if ($dialplanUuids->isNotEmpty()) {
                DialplanDetails::query()
                    ->whereIn('dialplan_uuid', $dialplanUuids)
                    ->delete();

                Dialplans::query()
                    ->whereIn('dialplan_uuid', $dialplanUuids)
                    ->delete();
            }

            $deleted = CallCenterQueues::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('call_center_queue_uuid', $queueUuids)
                ->delete();

            $this->clearCaches();

            return $deleted;
        });
    }

    public function deleteAgents(Collection $agents): int
    {
        return DB::transaction(function () use ($agents) {
            $agentUuids = $agents->pluck('call_center_agent_uuid');
            $tiers = CallCenterQueueAgents::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('call_center_agent_uuid', $agentUuids)
                ->with(['queue.domain'])
                ->get();

            $this->deleteRuntimeTiers($tiers);
            $this->deleteRuntimeAgents($agents);

            CallCenterQueueAgents::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('call_center_agent_uuid', $agentUuids)
                ->delete();

            $deleted = CallCenterAgents::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('call_center_agent_uuid', $agentUuids)
                ->delete();

            $this->clearCaches();

            return $deleted;
        });
    }

    private function syncTiers(CallCenterQueues $queue, array $tiers): array
    {
        $incoming = collect($tiers)
            ->filter(fn ($tier) => ! empty($tier['call_center_agent_uuid']))
            ->values();

        $existing = CallCenterQueueAgents::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('call_center_queue_uuid', $queue->call_center_queue_uuid)
            ->with(['queue.domain'])
            ->get();
        $existingAgentUuids = $existing->pluck('call_center_agent_uuid');
        $incomingAgentUuids = $incoming->pluck('call_center_agent_uuid');
        $removed = $existing
            ->whereNotIn('call_center_agent_uuid', $incomingAgentUuids->all())
            ->values();
        $existingByAgent = $existing->keyBy('call_center_agent_uuid');
        $updated = $incoming->contains(function ($tier) use ($existingByAgent) {
            $existingTier = $existingByAgent->get($tier['call_center_agent_uuid']);

            if (! $existingTier) {
                return false;
            }

            return (string) ($tier['tier_level'] ?? 0) !== (string) $existingTier->tier_level
                || (string) ($tier['tier_position'] ?? 0) !== (string) $existingTier->tier_position;
        });

        $this->deleteRuntimeTiers($removed, false);

        $agents = CallCenterAgents::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('call_center_agent_uuid', $incoming->pluck('call_center_agent_uuid'))
            ->get()
            ->keyBy('call_center_agent_uuid');

        CallCenterQueueAgents::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('call_center_queue_uuid', $queue->call_center_queue_uuid)
            ->delete();

        foreach ($incoming as $tier) {
            $agent = $agents->get($tier['call_center_agent_uuid']);

            if (! $agent) {
                continue;
            }

            CallCenterQueueAgents::query()->create([
                'domain_uuid' => session('domain_uuid'),
                'call_center_tier_uuid' => (string) Str::uuid(),
                'call_center_queue_uuid' => $queue->call_center_queue_uuid,
                'call_center_agent_uuid' => $agent->call_center_agent_uuid,
                'agent_name' => $agent->agent_name,
                'queue_name' => $queue->queue_extension . '@' . session('domain_name'),
                'tier_level' => (string) ($tier['tier_level'] ?? 0),
                'tier_position' => (string) ($tier['tier_position'] ?? 0),
                'insert_date' => now(),
                'insert_user' => session('user_uuid'),
            ]);
        }

        return [
            'removed' => $removed,
            'added' => $incomingAgentUuids->diff($existingAgentUuids)->isNotEmpty(),
            'updated' => $updated,
        ];
    }

    private function buildQueueTimeoutAction(array $validated, string $domainName): ?string
    {
        if (blank($validated['timeout_action'] ?? null)) {
            return null;
        }

        $timeout = match ($validated['timeout_action']) {
            'extensions',
            'ring_groups',
            'ivrs',
            'business_hours',
            'time_conditions',
            'contact_centers',
            'faxes',
            'conferences',
            'call_flows',
            'conference_centers' => [
                'action' => 'transfer',
                'data' => ($validated['timeout_target'] ?? '') . ' XML ' . $domainName,
            ],
            'bridges' => [
                'action' => 'bridge',
                'data' => $validated['timeout_target'] ?? '',
            ],
            'voicemails' => [
                'action' => 'transfer',
                'data' => '*99' . ($validated['timeout_target'] ?? '') . ' XML ' . $domainName,
            ],
            'recordings' => [
                'action' => 'lua',
                'data' => 'streamfile.lua ' . ($validated['timeout_target'] ?? ''),
            ],
            'check_voicemail' => [
                'action' => 'transfer',
                'data' => '*98 XML ' . $domainName,
            ],
            'company_directory' => [
                'action' => 'transfer',
                'data' => '*411 XML ' . $domainName,
            ],
            'hangup' => [
                'action' => 'hangup',
                'data' => '',
            ],
            default => [],
        };

        if (blank($timeout['action'] ?? null)) {
            return null;
        }

        return $timeout['action'] . ':' . ($timeout['data'] ?? '');
    }

    private function saveDialplan(CallCenterQueues $queue, bool $isNew): void
    {
        $dialplan = Dialplans::query()
            ->where('dialplan_uuid', $queue->dialplan_uuid)
            ->first() ?? new Dialplans();

        $context = session('domain_name');
        $dialplan->forceFill([
            'domain_uuid' => session('domain_uuid'),
            'dialplan_uuid' => $queue->dialplan_uuid,
            'app_uuid' => self::APP_UUID,
            'dialplan_name' => $queue->queue_name,
            'dialplan_number' => $queue->queue_extension,
            'dialplan_context' => $context,
            'dialplan_continue' => 'false',
            'dialplan_xml' => $this->dialplanXml($queue),
            'dialplan_order' => '230',
            'dialplan_enabled' => 'true',
            'dialplan_description' => $queue->queue_description,
            $isNew || ! $dialplan->exists ? 'insert_date' : 'update_date' => now(),
            $isNew || ! $dialplan->exists ? 'insert_user' : 'update_user' => session('user_uuid'),
        ])->save();
    }

    private function dialplanXml(CallCenterQueues $queue): string
    {
        $lines = [
            sprintf(
                '<extension name="%s" continue="" uuid="%s">',
                $this->xml($queue->queue_name),
                $this->xml($queue->dialplan_uuid)
            ),
            "\t" . '<condition field="destination_number" expression="^([^#]+#)(.*)$" break="never">',
            "\t\t" . '<action application="set" data="caller_id_name=$2"/>',
            "\t" . '</condition>',
            sprintf(
                "\t" . '<condition field="destination_number" expression="^(callcenter\+)?%s$">',
                $this->xml($queue->queue_extension)
            ),
            "\t\t" . '<action application="answer" data=""/>',
            sprintf("\t\t" . '<action application="set" data="call_center_queue_uuid=%s"/>', $this->xml($queue->call_center_queue_uuid)),
            sprintf("\t\t" . '<action application="set" data="queue_extension=%s"/>', $this->xml($queue->queue_extension)),
            "\t\t" . '<action application="set" data="cc_export_vars=${cc_export_vars},call_center_queue_uuid,sip_h_Alert-Info"/>',
            "\t\t" . '<action application="set" data="hangup_after_bridge=true"/>',
        ];

        if ($queue->queue_cid_prefix) {
            $lines[] = sprintf(
                "\t\t" . '<action application="set" data="effective_caller_id_name=%s#${caller_id_name}"/>',
                $this->xml($queue->queue_cid_prefix)
            );
        }

        if ($recordingPath = $this->queueGreetingPath($queue)) {
            $lines[] = sprintf(
                "\t\t" . '<action application="playback" data="%s"/>',
                $this->xml($recordingPath)
            );
        }

        $lines[] = sprintf(
            "\t\t" . '<action application="callcenter" data="%s@%s"/>',
            $this->xml($queue->queue_extension),
            $this->xml(session('domain_name'))
        );

        if ($queue->queue_timeout_action && str_contains($queue->queue_timeout_action, ':')) {
            [$application, $data] = explode(':', $queue->queue_timeout_action, 2);
            $lines[] = sprintf(
                "\t\t" . '<action application="%s" data="%s"/>',
                $this->xml($application),
                $this->xml($data)
            );
        }

        $lines[] = "\t" . '</condition>';
        $lines[] = '</extension>';

        return implode("\n", $lines);
    }

    private function clearCaches(): void
    {
        $this->writeCallCenterXml();
        app(DialplanService::class)->clearDialplanCache(session('domain_name'));
        FusionCache::clear('dialplan:' . session('domain_name'));
        FusionCache::clear('configuration:callcenter.conf*');

        if (isset($_SESSION['destinations']['array'])) {
            unset($_SESSION['destinations']['array']);
        }
    }

    private function reloadRuntimeQueues(Collection $queues, bool $reloadXml = true): void
    {
        $queues = $this->runtimeQueues($queues);

        if ($queues->isEmpty()) {
            $this->reloadXml();
            return;
        }

        $this->withEventSocket(function ($fp) use ($queues, $reloadXml) {
            if ($reloadXml) {
                event_socket_request($fp, 'api reloadxml');
            }

            foreach ($queues as $queue) {
                event_socket_request($fp, sprintf(
                    'bgapi callcenter_config queue reload %s@%s',
                    $queue->queue_extension,
                    $this->domainName($queue->domain_uuid, $queue->domain)
                ));
            }
        });
    }

    private function loadRuntimeQueues(Collection $queues): void
    {
        $queues = $this->runtimeQueues($queues);

        if ($queues->isEmpty()) {
            $this->reloadXml();
            return;
        }

        $this->withEventSocket(function ($fp) use ($queues) {
            event_socket_request($fp, 'api reloadxml');

            foreach ($queues as $queue) {
                event_socket_request($fp, sprintf(
                    'api callcenter_config queue load %s@%s',
                    $queue->queue_extension,
                    $this->domainName($queue->domain_uuid, $queue->domain)
                ));
            }
        });
    }

    private function unloadRuntimeQueues(Collection $queues): void
    {
        $queues = $this->runtimeQueues($queues);

        if ($queues->isEmpty()) {
            return;
        }

        $this->withEventSocket(function ($fp) use ($queues) {
            foreach ($queues as $queue) {
                event_socket_request($fp, sprintf(
                    'api callcenter_config queue unload %s@%s',
                    $queue->queue_extension,
                    $this->domainName($queue->domain_uuid, $queue->domain)
                ));
            }
        });
    }

    private function refreshRuntimeAgent(CallCenterAgents $agent): void
    {
        if (blank($agent->call_center_agent_uuid)) {
            return;
        }

        $this->withEventSocket(function ($fp) use ($agent) {
            event_socket_request($fp, sprintf(
                'bgapi callcenter_config agent add %s %s',
                $agent->call_center_agent_uuid,
                $agent->agent_type ?: 'callback'
            ));
            event_socket_request($fp, sprintf(
                'bgapi callcenter_config agent reload %s',
                $agent->call_center_agent_uuid
            ));
        });
    }

    private function deleteRuntimeAgents(Collection $agents): void
    {
        $agents = $agents
            ->filter(fn ($agent) => $agent instanceof CallCenterAgents && filled($agent->call_center_agent_uuid))
            ->unique('call_center_agent_uuid')
            ->values();

        if ($agents->isEmpty()) {
            return;
        }

        $this->withEventSocket(function ($fp) use ($agents) {
            foreach ($agents as $agent) {
                event_socket_request($fp, sprintf(
                    'api callcenter_config agent del %s',
                    $agent->call_center_agent_uuid
                ));
            }
        });
    }

    private function deleteRuntimeTiers(Collection $tiers, bool $reloadQueues = true): void
    {
        if ($tiers->isEmpty()) {
            return;
        }

        $this->withEventSocket(function ($fp) use ($tiers, $reloadQueues) {
            foreach ($tiers as $tier) {
                if (! $tier->queue) {
                    continue;
                }

                event_socket_request($fp, sprintf(
                    'api callcenter_config tier del %s@%s %s',
                    $tier->queue->queue_extension,
                    $this->domainName($tier->queue->domain_uuid, $tier->queue->domain),
                    $tier->call_center_agent_uuid
                ));

                if ($reloadQueues) {
                    event_socket_request($fp, sprintf(
                        'api callcenter_config queue reload %s@%s',
                        $tier->queue->queue_extension,
                        $this->domainName($tier->queue->domain_uuid, $tier->queue->domain)
                    ));
                }
            }
        });
    }

    private function reloadXml(): void
    {
        $this->withEventSocket(fn ($fp) => event_socket_request($fp, 'api reloadxml'));
    }

    private function runtimeQueues(Collection $queues): Collection
    {
        return $queues
            ->filter(fn ($queue) => $queue instanceof CallCenterQueues && filled($queue->queue_extension))
            ->each(fn (CallCenterQueues $queue) => $queue->loadMissing('domain'))
            ->unique('call_center_queue_uuid')
            ->values();
    }

    private function withEventSocket(callable $callback): void
    {
        try {
            $fp = event_socket_create(
                config('eventsocket.ip'),
                config('eventsocket.port'),
                config('eventsocket.password')
            );

            if (! $fp) {
                return;
            }

            $callback($fp);
            fclose($fp);
        } catch (\Throwable $e) {
            logger('BasicQueueService FreeSWITCH refresh failed: ' . $e->getMessage());
        }
    }

    private function writeCallCenterXml(): void
    {
        try {
            $confDir = data_get(session('switch'), 'conf.dir');
            if (blank($confDir)) {
                return;
            }

            $template = collect([
                public_path('app/switch/resources/conf/autoload_configs/callcenter.conf.xml.noload'),
                public_path('app/switch/resources/conf/autoload_configs/callcenter.conf.xml'),
            ])->first(fn (string $path) => is_readable($path));

            if (! $template || ! is_readable($template)) {
                return;
            }

            $contents = file_get_contents($template);
            if ($contents === false) {
                return;
            }

            if (str_contains($contents, '{v_queues}')) {
                $contents = str_replace('{v_queues}', $this->queueXml(), $contents);
                $contents = str_replace('{v_agents}', $this->agentXml(), $contents);
                $contents = str_replace('{v_tiers}', $this->tierXml(), $contents);
            } else {
                $contents = $this->callCenterXml();
            }

            $target = rtrim($confDir, '/') . '/autoload_configs/callcenter.conf.xml';
            if (! is_dir(dirname($target))) {
                return;
            }

            file_put_contents($target, $contents);
        } catch (\Throwable $e) {
            logger('BasicQueueService callcenter XML refresh failed: ' . $e->getMessage());
        }
    }

    private function callCenterXml(): string
    {
        return implode("\n", [
            '<configuration name="callcenter.conf" description="CallCenter">',
            "\t<settings>",
            "\t\t" . '<!--<param name="odbc-dsn" value="$${dsn}"/>-->',
            "\t\t" . '<!--<param name="dbname" value="/usr/local/freeswitch/db/call_center.db"/>-->',
            "\t</settings>",
            "\t<queues>",
            $this->queueXml(),
            "\t</queues>",
            "\t" . '<!-- WARNING: Configuration of XML Agents will be updated into the DB upon restart. -->',
            "\t" . '<!-- WARNING: Configuration of XML Tiers will reset the level and position if those were supplied. -->',
            "\t" . "<!-- WARNING: Agents and Tiers XML config shouldn't be used in a multi FS shared DB setup (Not currently supported anyway) -->",
            "\t<agents>",
            $this->agentXml(),
            "\t</agents>",
            "\t" . '<!-- If no level or position is provided, they will default to 1.  You should do this to keep db value on restart. -->',
            "\t<tiers>",
            $this->tierXml(),
            "\t</tiers>",
            '</configuration>',
        ]);
    }

    private function queueXml(): string
    {
        return CallCenterQueues::query()
            ->with('domain:domain_uuid,domain_name')
            ->orderBy('queue_name')
            ->get()
            ->map(function (CallCenterQueues $queue) {
                $domainName = $this->domainName($queue->domain_uuid, $queue->domain);
                $queueLabel = str_replace(' ', '-', (string) $queue->queue_name);
                $mohSound = $queue->queue_moh_sound ?: 'local_stream://default';

                return implode("\n", [
                    sprintf(
                        "\t\t<queue name=\"%s@%s\" label=\"%s@%s\">",
                        $this->xml($queue->queue_extension),
                        $this->xml($domainName),
                        $this->xml($queueLabel),
                        $this->xml($domainName)
                    ),
                    sprintf("\t\t\t<param name=\"strategy\" value=\"%s\"/>", $this->xml($queue->queue_strategy ?: 'ring-all')),
                    sprintf("\t\t\t<param name=\"moh-sound\" value=\"%s\"/>", $this->xml($this->mohSound($mohSound))),
                    $queue->queue_record_template
                        ? sprintf("\t\t\t<param name=\"record-template\" value=\"%s\"/>", $this->xml($queue->queue_record_template))
                        : null,
                    sprintf("\t\t\t<param name=\"time-base-score\" value=\"%s\"/>", $this->xml($queue->queue_time_base_score ?: 'system')),
                    sprintf("\t\t\t<param name=\"max-wait-time\" value=\"%s\"/>", $this->xml($queue->queue_max_wait_time ?? '0')),
                    sprintf("\t\t\t<param name=\"max-wait-time-with-no-agent\" value=\"%s\"/>", $this->xml($queue->queue_max_wait_time_with_no_agent ?? '90')),
                    sprintf("\t\t\t<param name=\"max-wait-time-with-no-agent-time-reached\" value=\"%s\"/>", $this->xml($queue->queue_max_wait_time_with_no_agent_time_reached ?? '5')),
                    sprintf("\t\t\t<param name=\"tier-rules-apply\" value=\"%s\"/>", $this->xml($queue->queue_tier_rules_apply ?? 'false')),
                    sprintf("\t\t\t<param name=\"tier-rule-wait-second\" value=\"%s\"/>", $this->xml($queue->queue_tier_rule_wait_second ?? '30')),
                    sprintf("\t\t\t<param name=\"tier-rule-wait-multiply-level\" value=\"%s\"/>", $this->xml($queue->queue_tier_rule_wait_multiply_level ?? 'false')),
                    sprintf("\t\t\t<param name=\"tier-rule-no-agent-no-wait\" value=\"%s\"/>", $this->xml($queue->queue_tier_rule_no_agent_no_wait ?? 'false')),
                    sprintf("\t\t\t<param name=\"discard-abandoned-after\" value=\"%s\"/>", $this->xml($queue->queue_discard_abandoned_after ?? '900')),
                    sprintf("\t\t\t<param name=\"abandoned-resume-allowed\" value=\"%s\"/>", $this->xml($queue->queue_abandoned_resume_allowed ?? 'false')),
                    sprintf("\t\t\t<param name=\"announce-sound\" value=\"%s\"/>", $this->xml($queue->queue_announce_sound)),
                    sprintf("\t\t\t<param name=\"announce-frequency\" value=\"%s\"/>", $this->xml($queue->queue_announce_frequency)),
                    "\t\t</queue>",
                ]);
            })
            ->map(fn (string $xml) => collect(explode("\n", $xml))->filter(fn ($line) => $line !== '')->implode("\n"))
            ->implode("\n\t\t");
    }

    private function agentXml(): string
    {
        return CallCenterAgents::query()
            ->with('domain:domain_uuid,domain_name')
            ->orderBy('agent_name')
            ->get()
            ->map(function (CallCenterAgents $agent) {
                $domainName = $this->domainName($agent->domain_uuid, $agent->domain);

                return sprintf(
                    '<agent name="%s" label="%s@%s" type="%s" contact="%s" status="%s" no-answer-delay-time="%s" max-no-answer="%s" wrap-up-time="%s" reject-delay-time="%s" busy-delay-time="%s"/>',
                    $this->xml($agent->call_center_agent_uuid),
                    $this->xml($agent->agent_name),
                    $this->xml($domainName),
                    $this->xml($agent->agent_type ?: 'callback'),
                    $this->xml($this->agentContact($agent)),
                    $this->xml($agent->agent_status ?: 'Logged Out'),
                    $this->xml($agent->agent_no_answer_delay_time ?? '30'),
                    $this->xml($agent->agent_max_no_answer ?? '0'),
                    $this->xml($agent->agent_wrap_up_time ?? '10'),
                    $this->xml($agent->agent_reject_delay_time ?? '90'),
                    $this->xml($agent->agent_busy_delay_time ?? '90'),
                );
            })
            ->implode("\n\t\t");
    }

    private function tierXml(): string
    {
        return CallCenterQueueAgents::query()
            ->with([
                'domain:domain_uuid,domain_name',
                'queue:call_center_queue_uuid,domain_uuid,queue_extension',
            ])
            ->orderBy('queue_name')
            ->orderBy('tier_level')
            ->orderBy('tier_position')
            ->get()
            ->map(function (CallCenterQueueAgents $tier) {
                $queue = $tier->queue;
                $domainName = $this->domainName($tier->domain_uuid, $tier->domain);
                $queueName = $queue?->queue_extension ?: $tier->queue_name;
                $queueIdentifier = str_contains((string) $queueName, '@')
                    ? (string) $queueName
                    : $queueName . '@' . $domainName;

                return sprintf(
                    '<tier agent="%s" queue="%s" level="%s" position="%s"/>',
                    $this->xml($tier->call_center_agent_uuid),
                    $this->xml($queueIdentifier),
                    $this->xml($tier->tier_level ?? '1'),
                    $this->xml($tier->tier_position ?? '1'),
                );
            })
            ->implode("\n\t\t");
    }

    private function mohSound(string $sound): string
    {
        if (str_starts_with($sound, '${') && str_ends_with($sound, 'ring}')) {
            return "tone_stream://{$sound};loops=-1";
        }

        return $sound;
    }

    private function agentContact(CallCenterAgents $agent): string
    {
        $contact = (string) $agent->agent_contact;
        $timeout = (string) ($agent->agent_call_timeout ?: '20');

        if (! str_contains($contact, '}')) {
            return "{call_timeout={$timeout}}" . $contact;
        }

        if (str_contains($contact, 'call_timeout')) {
            return $contact;
        }

        $position = strrpos($contact, '}');

        return substr($contact, 0, $position) . ",call_timeout={$timeout}" . substr($contact, $position);
    }

    private function queueGreetingPath(CallCenterQueues $queue): ?string
    {
        if (blank($queue->queue_greeting)) {
            return null;
        }

        $path = $this->domainName($queue->domain_uuid, $queue->domain) . '/' . $queue->queue_greeting;

        return Storage::disk('recordings')->exists($path)
            ? Storage::disk('recordings')->path($path)
            : null;
    }

    private function domainName(?string $domainUuid, ?Domain $domain = null): string
    {
        return $domain?->domain_name
            ?: data_get(session('domains'), "{$domainUuid}.domain_name")
            ?: (string) session('domain_name');
    }

    private function xml(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
