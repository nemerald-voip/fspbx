<?php

namespace App\Console\Commands;

use App\Services\FreeswitchEslService;
use App\Services\PhoneControlDriver;
use App\Services\PhoneControlService;
use Illuminate\Console\Command;
use Throwable;

class PhoneControl extends Command
{
    protected $signature = 'phone:control
        {extension : Extension registered on the phone}
        {domain : Domain name or UUID}
        {action? : Vendor-supported action such as hold, blind-transfer, conference, or dnd-on}
        {destination? : Action destination; currently used by transfer actions}
        {--list-uas : List registered phones supported by the phone-control drivers}
        {--list-calls : List the extension\'s active calls and their states}
        {--vendor= : Vendor driver to use, for example yealink; detected from the agent when omitted}
        {--lan-ip= : Narrow selection to a LAN IP when several phones match}
        {--agent= : Narrow selection to user agents matching this regex (case-insensitive; plain text works too)}
        {--call-id= : Narrow selection to a specific FreeSWITCH registration call-id}
        {--force : Skip the hold/resume call-state check and send the vendor key action anyway}
        {--no-resume : After cancel-transfer, leave the original call on hold instead of resuming it automatically}
        {--dry-run : Resolve the phone and print the command without sending it}';

    protected $description = 'Control a registered phone through its vendor phone-control driver.';

    public function handle(
        PhoneControlService $phoneControl,
        FreeswitchEslService $eslService
    ): int {
        try {
            if ((bool) $this->option('list-uas')) {
                return $this->listCandidates($phoneControl, $eslService);
            }

            if ((bool) $this->option('list-calls')) {
                return $this->listCalls($phoneControl, $eslService);
            }

            if ($this->argument('action') === null) {
                $this->error('The action argument is required unless --list-uas or --list-calls is used.');

                return self::FAILURE;
            }

            $action = strtolower(trim((string) $this->argument('action')));
            $result = $phoneControl->execute(
                $eslService,
                (string) $this->argument('extension'),
                (string) $this->argument('domain'),
                $action,
                $this->argument('destination') !== null
                    ? (string) $this->argument('destination')
                    : null,
                [
                    'vendor' => $this->option('vendor'),
                    'lan_ip' => $this->option('lan-ip'),
                    'agent' => $this->option('agent'),
                    'call_id' => $this->option('call-id'),
                    'force' => (bool) $this->option('force'),
                    'no_resume' => (bool) $this->option('no-resume'),
                    'dry_run' => (bool) $this->option('dry-run'),
                ]
            );

            foreach ($result['groups'] ?? [$result['group']] as $group) {
                $this->info(sprintf(
                    'Selected %s registration: %s on %s (%s).',
                    $group['label'] ?? $group['vendor'],
                    $group['lan_ip'] ?: 'unknown LAN IP',
                    $group['sip_profile_name'] ?: 'unknown profile',
                    $group['agent'] ?: 'unknown agent'
                ));
            }

            foreach ($result['skipped_groups'] ?? [] as $group) {
                $this->warn(sprintf(
                    'Skipped another %s phone at %s (%s). Target it with --vendor, --agent, --lan-ip, or --call-id.',
                    $group['vendor'],
                    $group['lan_ip'] ?: 'unknown LAN IP',
                    $group['agent'] ?: 'unknown agent'
                ));
            }

            $sent = false;

            foreach ($result['results'] as $item) {
                if (! $item['sent']) {
                    $this->warn($item['reason'] ?? 'FreeSWITCH did not accept the phone-control command.');
                    continue;
                }

                $sent = true;
                $this->line(sprintf(
                    '%s %s %s to %s.',
                    $this->option('dry-run') ? 'Would send' : 'Sent',
                    $item['vendor'],
                    $action,
                    $item['target_uri']
                ));
                $this->line('Command: ' . $item['command']);
                $this->line('Body: ' . $item['body']);
            }

            if ($result['auto_resume'] !== null) {
                if ($result['auto_resume']['sent']) {
                    $this->line('Automatically resumed the call.');
                } else {
                    $this->warn('Could not automatically resume the call: '
                        . ($result['auto_resume']['reason'] ?? 'unknown reason') . '.');
                }
            }

            if ((bool) $this->option('force')
                && in_array($action, [
                    PhoneControlDriver::ACTION_HOLD,
                    PhoneControlDriver::ACTION_RESUME,
                    PhoneControlDriver::ACTION_END_CALL,
                ], true)) {
                $this->warn('Call-state check bypassed; the phone acts on whatever call is selected on its screen.');
            }

            $this->writeActionNote($action);

            return $sent ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $eslService->disconnect();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function listCandidates(
        PhoneControlService $phoneControl,
        FreeswitchEslService $eslService
    ): int {
        $groups = $phoneControl->candidateGroups(
            $eslService,
            (string) $this->argument('extension'),
            (string) $this->argument('domain')
        );
        $eslService->disconnect();

        if ($groups->isEmpty()) {
            $this->warn(
                'No registered phones matched an installed phone-control driver. Supported vendors: '
                . implode(', ', $phoneControl->supportedVendors()) . '.'
            );

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Vendor', 'Profile', 'Device IP', 'Registration IP', 'Registrations', 'Agent', 'Call IDs'],
            $groups->map(fn (array $group) => [
                $group['index'],
                $group['vendor'],
                $group['sip_profile_name'] ?: 'unknown',
                $group['lan_ip'] ?: 'unknown',
                $group['registration_lan_ip'] ?: 'unknown',
                $group['count'],
                $group['agent'] ?: 'unknown',
                collect($group['registrations'])->pluck('call_id')->filter()->implode(', '),
            ])->all()
        );

        return self::SUCCESS;
    }

    private function listCalls(
        PhoneControlService $phoneControl,
        FreeswitchEslService $eslService
    ): int {
        $calls = $phoneControl->activeCallsFor(
            $eslService,
            (string) $this->argument('extension'),
            (string) $this->argument('domain')
        );
        $eslService->disconnect();

        if ($calls->isEmpty()) {
            $this->info('No active calls found for this extension.');

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'SIP Call-ID', 'State', 'Direction', 'Other Party', 'Channel UUID'],
            $calls->map(fn (array $call, int $index) => [
                $index + 1,
                $call['sip_call_id'] ?: 'unknown',
                $call['callstate'] ?: 'unknown',
                $call['direction'] ?: 'unknown',
                $call['other_party'] ?: 'unknown',
                $call['uuid'],
            ])->all()
        );

        return self::SUCCESS;
    }

    private function writeActionNote(string $action): void
    {
        if ($action === PhoneControlDriver::ACTION_CONFERENCE) {
            $this->warn('Conference may depend on the phone already being in a call state where its Conference action is valid.');
        }

        if ($action === PhoneControlDriver::ACTION_MUTE_TOGGLE) {
            $this->warn('This action toggles the current local microphone state.');
        }

        if (in_array($action, [
            PhoneControlDriver::ACTION_DND_ON,
            PhoneControlDriver::ACTION_DND_OFF,
            PhoneControlDriver::ACTION_DND_TOGGLE,
        ], true)) {
            $this->warn('This changes the phone DND state; PBX-side sync still depends on vendor and account configuration.');
        }
    }
}
