<?php

namespace App\Services\Fax;

use App\Services\FreeswitchEslService;
use RuntimeException;

/**
 * Read-only snapshot of local txfax channels. This class never hangs up.
 */
final class FaxChannelProbe
{
    private const VARIABLE_NAMES = [
        'outbound_fax_uuid',
        'outbound_fax_attempt_uuid',
        'origination_uuid',
        'originating_leg_uuid',
        'fax_success',
        'fax_channel_budget',
        'fax_document_transferred_pages',
        'fax_document_total_pages',
    ];

    public function __construct(private FreeswitchEslService $esl) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function txfaxChannels(): array
    {
        $result = $this->esl->executeCommand('show channels as json', false);
        if (! is_array($result)) {
            throw new RuntimeException('show channels did not return JSON');
        }

        $rows = $result['rows'] ?? [];
        if ($rows === [] || $rows === null) {
            return [];
        }

        if (isset($rows['uuid'])) {
            $rows = [$rows];
        }

        $channels = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (! FaxChannelGuard::isTxfax($row['application'] ?? '')) {
                continue;
            }

            $uuid = FaxChannelGuard::norm($row['uuid'] ?? '');
            if (! FaxChannelGuard::isUuid($uuid)) {
                continue;
            }

            $vars = $this->variables($uuid);
            $destination = FaxChannelGuard::norm($row['dest'] ?? '');
            if ($destination === '') {
                $destination = FaxChannelGuard::norm($row['initial_dest'] ?? '');
            }

            $channels[] = [
                'uuid' => $uuid,
                'application' => 'txfax',
                'call_uuid' => FaxChannelGuard::norm($row['call_uuid'] ?? ''),
                'created_epoch' => (int) ($row['created_epoch'] ?? 0),
                'destination' => $destination,
                'outbound_fax_uuid' => $vars['outbound_fax_uuid'],
                'origination_uuid' => $vars['origination_uuid'],
                'originating_leg_uuid' => $vars['originating_leg_uuid'],
                'attempt_uuid' => $vars['outbound_fax_attempt_uuid'],
                'fax_success' => $vars['fax_success'],
                'budget_seconds' => (int) $vars['fax_channel_budget'],
                'transferred_pages' => $vars['fax_document_transferred_pages'],
                'total_pages' => $vars['fax_document_total_pages'],
            ];
        }

        return $channels;
    }

    /**
     * @return array<string, string>
     */
    private function variables(string $uuid): array
    {
        $values = [];
        foreach (self::VARIABLE_NAMES as $name) {
            $values[$name] = FaxChannelGuard::norm(
                $this->esl->executeCommand('uuid_getvar '.$uuid.' '.$name, false)
            );
        }

        return $values;
    }
}
