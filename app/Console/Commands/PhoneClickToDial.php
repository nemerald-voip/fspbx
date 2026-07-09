<?php

namespace App\Console\Commands;

use App\Services\FreeswitchEslService;
use App\Services\ClickToDialService;
use Illuminate\Console\Command;
use Throwable;

class PhoneClickToDial extends Command
{
    protected $signature = 'phone:click-to-dial
        {extension : Extension to control}
        {domain : Domain name or UUID}
        {destination? : Destination number to call}
        {vendor? : Optional user agent vendor to target, for example poly or yealink}
        {--list-uas : List controllable registered user agent groups for the extension}
        {--vendor= : User agent vendor to target, for example poly or yealink}
        {--lan-ip= : Narrow selection to a LAN IP when several devices match}
        {--call-id= : Narrow selection to a specific FreeSWITCH registration call-id}
        {--event= : Force the NOTIFY transport and override its SIP Event header (vendor recipe otherwise)}
        {--content-type= : Force the NOTIFY transport and override its Content-Type (vendor recipe otherwise)}
        {--body= : Force the NOTIFY transport and override its body (vendor recipe otherwise)}
        {--dry-run : Resolve targets and print what would be sent without sending anything}';

    protected $description = 'Make the selected registered phone dial a number (click to dial).';

    protected $aliases = ['uacsta:make-call'];

    public function handle(ClickToDialService $clickToDial, FreeswitchEslService $eslService): int
    {
        try {
            if ((bool) $this->option('list-uas')) {
                $groups = $clickToDial->candidateGroups(
                    $eslService,
                    (string) $this->argument('extension'),
                    (string) $this->argument('domain')
                );

                $eslService->disconnect();

                if ($groups->isEmpty()) {
                    $this->warn('No controllable registered user agents were found.');
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

            if (! $this->argument('destination')) {
                $this->error('The destination argument is required unless --list-uas is used.');
                return self::FAILURE;
            }

            $result = $clickToDial->makeCall(
                $eslService,
                (string) $this->argument('extension'),
                (string) $this->argument('domain'),
                (string) $this->argument('destination'),
                [
                    'vendor' => $this->option('vendor') ?: $this->argument('vendor'),
                    'lan_ip' => $this->option('lan-ip'),
                    'call_id' => $this->option('call-id'),
                    'event' => $this->option('event'),
                    'content_type' => $this->option('content-type'),
                    'body' => $this->option('body'),
                    'dry_run' => (bool) $this->option('dry-run'),
                ]
            );

            foreach ($result['groups'] ?? [$result['group']] as $group) {
                $this->info(sprintf(
                    'Selected %s registration group: %s on %s (%s), %d registration(s).',
                    $group['vendor'],
                    $group['lan_ip'] ?: 'unknown LAN IP',
                    $group['sip_profile_name'] ?: 'unknown profile',
                    $group['agent'] ?: 'unknown agent',
                    $group['count']
                ));
            }

            foreach ($result['results'] as $item) {
                if (! $item['sent']) {
                    $this->warn($item['reason'] ?? 'FreeSWITCH did not confirm the uaCSTA command ran.');
                    if (($item['result'] ?? null) !== null) {
                        $this->line('Response: ' . (is_scalar($item['result']) ? (string) $item['result'] : json_encode($item['result'])));
                    }
                    continue;
                }

                $this->line(sprintf(
                    '%s %s via %s -> %s',
                    $this->option('dry-run') ? 'Would send' : 'Sent',
                    $item['vendor'],
                    $item['sip_profile_name'] ?: ($item['transport'] ?? 'unknown'),
                    $item['target_uri']
                ));
                $this->line('Transport: ' . ($item['transport'] ?? 'esl'));
                if (! empty($item['command'])) {
                    $this->line('Command: ' . $item['command']);
                }
                if (! empty($item['body'])) {
                    $this->line('Body: ' . $item['body']);
                }
                if (($item['result'] ?? null) !== null && $item['result'] !== '+OK') {
                    $this->line('Response: ' . (is_scalar($item['result']) ? (string) $item['result'] : json_encode($item['result'])));
                }
                if (! empty($item['ringotel'])) {
                    $this->line('Ringotel payload: ' . json_encode($item['ringotel']['payload']));
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $eslService->disconnect();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
