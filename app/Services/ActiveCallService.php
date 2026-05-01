<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Gateways;
use Illuminate\Support\Collection;

class ActiveCallService
{
    private array $searchable = [
        'cid_name',
        'cid_num',
        'dest',
        'application_data',
        'application',
        'read_codec',
        'write_codec',
        'secure',
    ];

    private array $allowedSortFields = [
        'context',
        'created_epoch',
        'duration',
        'cid_name',
        'cid_num',
        'dest',
        'application',
        'read_codec',
        'secure',
    ];

    public function getCalls(FreeswitchEslService $eslService, array $options = []): Collection
    {
        $sortField = $this->normalizeSortField($options['sortField'] ?? 'created_epoch');
        $sortOrder = $this->normalizeSortOrder($options['sortOrder'] ?? 'desc');
        $showGlobal = (bool) ($options['showGlobal'] ?? false);
        $domainName = $options['domain_name'] ?? null;
        $domainUuid = $options['domain_uuid'] ?? null;
        $viewerTimeZone = $options['viewer_timezone'] ?? 'UTC';
        $search = trim((string) ($options['search'] ?? ''));

        $data = $eslService->getAllChannels();

        if ($sortField === 'duration') {
            $data = $sortOrder === 'asc'
                ? $data->sortByDesc('created_epoch')
                : $data->sortBy('created_epoch');
        } elseif ($sortOrder === 'asc') {
            $data = $data->sortBy($sortField);
        } else {
            $data = $data->sortByDesc($sortField);
        }

        if (! $showGlobal && $domainName !== null) {
            $data = $data->filter(function ($item) use ($domainName) {
                return ($item['context'] ?? null) === $domainName;
            });
        }

        if ($search !== '') {
            $data = $data->filter(function ($item) use ($search) {
                foreach ($this->searchable as $field) {
                    if (stripos((string) ($item[$field] ?? ''), $search) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $data
            ->map(function ($call) use ($domainUuid, $viewerTimeZone, $showGlobal) {
                return $this->transformCall($call, $domainUuid, $viewerTimeZone, $showGlobal);
            })
            ->values();
    }

    public function findCallByUuid(FreeswitchEslService $eslService, string $uuid, array $options = []): ?array
    {
        return $this->getCalls($eslService, $options)
            ->firstWhere('uuid', $uuid);
    }

    private function transformCall(array $call, ?string $domainUuid, string $viewerTimeZone, bool $showGlobal): array
    {
        if (isset($call['application_data']) && str_contains((string) $call['application_data'], 'sofia/gateway')) {
            preg_match('/sofia\/gateway\/([a-z0-9\-]+)\//', (string) $call['application_data'], $matches);

            if (isset($matches[1])) {
                $gatewayUuid = $matches[1];
                $gateway = Gateways::where('gateway_uuid', $gatewayUuid)->first();

                if ($gateway) {
                    $call['application_data'] = str_replace($gatewayUuid, $gateway->gateway, (string) $call['application_data']);
                }
            }
        }

        $createdEpoch = isset($call['created_epoch']) ? (int) $call['created_epoch'] : null;
        $call['start_epoch'] = $createdEpoch ? $createdEpoch * 1000 : null;
        $call['duration_seconds'] = $createdEpoch ? max(0, time() - $createdEpoch) : null;

        $displayTimeZone = $showGlobal
            ? $viewerTimeZone
            : (get_local_time_zone($domainUuid) ?? $viewerTimeZone);

        $call['display_timezone'] = $displayTimeZone;
        $call['created_display'] = $createdEpoch
            ? Carbon::createFromTimestamp($createdEpoch, 'UTC')
                ->setTimezone($displayTimeZone)
                ->format('Y-m-d H:i:s')
            : null;

        $call['app_full'] = trim(
            ((string) ($call['application'] ?? ''))
            . ((string) ($call['application_data'] ?? '') !== '' ? ': ' . $call['application_data'] : '')
        );
        $call['app_preview'] = mb_strimwidth((string) $call['app_full'], 0, 90, '...');

        return $call;
    }

    private function normalizeSortField(string $sortField): string
    {
        return in_array($sortField, $this->allowedSortFields, true)
            ? $sortField
            : 'created_epoch';
    }

    private function normalizeSortOrder(string $sortOrder): string
    {
        return in_array($sortOrder, ['asc', 'desc'], true)
            ? $sortOrder
            : 'desc';
    }
}
