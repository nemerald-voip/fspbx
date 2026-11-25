<?php

namespace App\Services;

use Illuminate\Support\Str;
use Throwable;

class FaxChannelInspector
{
    public function __construct(private FreeswitchEslService $esl) {}

    /** Index the actual txfax legs, including channels left behind by earlier attempts. */
    public function scan(): array
    {
        $scan = ['complete' => false, 'carriers' => [], 'listed' => []];
        try {
            if (!$this->esl->isConnected()) {
                $this->esl->reconnect();
            }
        } catch (Throwable $e) {
            fax_webhook_debug('Fax watchdog could not connect to ESL', ['error' => $e->getMessage()]);
            return $scan;
        }

        $list = $this->command('show channels as json');
        if (!is_array($list)) {
            return $scan;
        }
        $count = $list['row_count'] ?? null;
        // FreeSWITCH omits "rows" for an empty channel table.
        $rows = array_key_exists('rows', $list) ? $list['rows'] : [];
        if (!is_array($rows) || !array_is_list($rows)
            || !(is_int($count) || (is_string($count) && ctype_digit($count)))) {
            return $scan;
        }
        $scan['complete'] = count($rows) === (int) $count;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                $scan['complete'] = false;
                continue;
            }
            $uuid = $row['uuid'] ?? null;
            if (!Str::isUuid($uuid) || isset($scan['listed'][$uuid]) || !is_string($row['application'] ?? null)) {
                $scan['complete'] = false;
                continue;
            }
            $scan['listed'][$uuid] = true;
            if ($row['application'] !== 'txfax') {
                continue;
            }
            $data = $this->dump($uuid);
            if (!$data || $data['current_application'] !== 'txfax') {
                $scan['complete'] = false;
                continue;
            }
            $data['created_epoch'] = $row['created_epoch'] ?? $data['start_epoch'] ?? null;
            $scan['carriers'][$data['outbound_fax_uuid']][$data['outbound_fax_attempt_uuid']][] = $data;
        }
        return $scan;
    }

    /** Read correlation and result fields from the same channel snapshot. */
    public function dump(string $uuid): ?array
    {
        if (!Str::isUuid($uuid)) {
            return null;
        }
        $dump = $this->command("uuid_dump $uuid json");
        if (!is_array($dump) || ($dump['Unique-ID'] ?? null) !== $uuid
            || !is_string($dump['variable_current_application'] ?? null)
            || trim($dump['variable_current_application']) === ''
            || !Str::isUuid($dump['variable_domain_uuid'] ?? null)
            || !Str::isUuid($dump['variable_outbound_fax_uuid'] ?? null)
            || !Str::isUuid($dump['variable_outbound_fax_attempt_uuid'] ?? null)) {
            return null;
        }

        $data = ['uuid' => $uuid];
        foreach ($dump as $header => $value) {
            if (str_starts_with($header, 'variable_fax_') || in_array($header, [
                'variable_current_application', 'variable_domain_uuid', 'variable_outbound_fax_uuid',
                'variable_outbound_fax_attempt_uuid', 'variable_billsec', 'variable_duration', 'variable_start_epoch', 'variable_end_epoch',
            ], true)) {
                if (!is_string($value) && !is_int($value)) {
                    return null;
                }
                $data[substr($header, 9)] = $value;
            }
        }
        return $data;
    }

    public function exists(string $uuid): ?bool
    {
        if (!Str::isUuid($uuid)) {
            return null;
        }
        $reply = $this->command("uuid_exists $uuid");
        return match (is_string($reply) ? strtolower(trim($reply)) : $reply) {
            true, 1, 1.0, '1', 'true' => true,
            false, 0, 0.0, '0', 'false' => false,
            default => null,
        };
    }

    public function hangup(string $uuid): void
    {
        $reply = $this->command("uuid_kill $uuid");
        if (!is_string($reply) || !preg_match('/^\+OK(?:\s|$)/i', trim($reply))) {
            logger()->warning('Fax watchdog hangup was not acknowledged', ['uuid' => $uuid, 'reply' => $reply]);
            return;
        }
        // Even +OK just acknowledges the request. Only a later complete scan
        // can establish disappearance and begin the missing-result grace period.
        fax_webhook_debug('Fax watchdog hangup acknowledged', ['uuid' => $uuid]);
    }

    private function command(string $command): mixed
    {
        try {
            return $this->esl->executeCommand($command, false);
        } catch (Throwable $e) {
            fax_webhook_debug('Fax watchdog ESL command failed', ['command' => $command, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
