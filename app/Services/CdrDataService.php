<?php

namespace App\Services;

use App\Models\CDR;
use App\Models\Extensions;

class CdrDataService
{
    private $domain_uuid;
    private $filters = [];
    private $sortField;
    private $sortOrder;
    private $model;
    private $domains;
    private $permissions;
    private $searchable;

    public function getData($params = [])
    {
        $this->domain_uuid = $params['domain_uuid'] ?? null;
        $this->domains = $params['domains'] ?? null;
        $this->permissions = $params['permissions'] ?? [];
        $this->searchable = $params['searchable'] ?? [];

        $this->filters = [
            'startPeriod' => $params['filterData']['startPeriod'] ?? null,
            'endPeriod' => $params['filterData']['endPeriod'] ?? null,
            'showGlobal' => $params['filterData']['showGlobal'] ?? null,
            'direction' => $params['filterData']['direction'] ?? null,
            'search' => $params['filterData']['search'] ?? null,
            'entity' => $params['filterData']['entity'] ?? null,
            'entityType' => $params['filterData']['entityType'] ?? null,
            'status' => $params['filterData']['selectedStatuses'] ?? null
        ];

        $this->sortField = $params['filterData']['sortField'] ?? 'start_epoch';
        $this->sortOrder = $params['filterData']['sortOrder'] ?? 'desc';

        $cdrs = $this->builder($this->filters);

        if ($params['paginate']) {
            $cdrs = $cdrs->paginate($params['paginate']);
        } else {
            $cdrs = $cdrs->cursor();
        }
        // logger($cdrs);

        return $cdrs;
    }

    public function builder($filters = [])
    {
        $this->model = new CDR();
        $data =  $this->model::query();

        if ($filters['showGlobal'] == 'true') {
            $data->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description'); // Specify the fields you need
            }]);
            // Access domains through the session and filter by those domains
            $domainUuids = $this->domains;
            $data->whereHas('domain', function ($query) use ($domainUuids) {
                $query->whereIn($this->model->getTable() . '.domain_uuid', $domainUuids);
            });
        } else {
            // Directly filter by the session's domain_uuid
            $domainUuid = $this->domain_uuid;
            $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);
        }

        $data->with(['extension' => function ($query) {
            $query->select('extension_uuid', 'extension', 'effective_caller_id_name'); // Specify the fields you need
        }]);

        $data->select(
            'xml_cdr_uuid',
            'direction',
            'caller_id_name',
            'caller_id_number',
            'caller_destination',
            'destination_number',
            'domain_uuid',
            'extension_uuid',
            'sip_call_id',
            'source_number',
            // 'start_stamp',
            'start_epoch',
            // 'answer_stamp',
            // 'answer_epoch',
            'end_epoch',
            // 'end_stamp',
            'duration',
            'record_path',
            'record_name',
            // 'leg',
            'voicemail_message',
            'missed_call',
            // 'call_center_queue_uuid',
            // 'cc_side',
            // 'cc_queue_joined_epoch',
            // 'cc_queue',
            // 'cc_agent',
            // 'cc_agent_bridged',
            // 'cc_queue_answered_epoch',
            // 'cc_queue_terminated_epoch',
            // 'cc_queue_canceled_epoch',
            'cc_cancel_reason',
            'cc_cause',
            'waitsec',
            'hangup_cause',
            'hangup_cause_q850',
            'sip_hangup_disposition',
            'status'
        );

        //exclude legs that were not answered
        if (!$this->permissions['xml_cdr_lose_race']) {
            $data->where('hangup_cause', '!=', 'LOSE_RACE');
        }

        // Exclude all related queue calls (only keep the main queue calls)
        // This ensures that calls with cc_member_session_uuid are excluded.
        $data->whereNull('cc_member_session_uuid');


        foreach ($filters as $field => $value) {
            if (method_exists($this, $method = "filter" . ucfirst($field))) {
                $this->$method($data, $value);
            }
        }

        // Apply sorting
        $data->orderBy($this->sortField, $this->sortOrder);

        return $data;
    }

    public function getExtensionStatistics($params = [])
    {
        $this->domain_uuid = $params['domain_uuid'] ?? null;
        $this->domains = $params['domains'] ?? null;
        $this->permissions = $params['permissions'] ?? [];
        $this->searchable = $params['searchable'] ?? [];

        $this->filters = [
            'startPeriod' => $params['filterData']['startPeriod'] ?? null,
            'endPeriod' => $params['filterData']['endPeriod'] ?? null,
            'showGlobal' => $params['filterData']['showGlobal'] ?? null,
            'direction' => $params['filterData']['direction'] ?? null,
            'search' => $params['filterData']['search'] ?? null,
            'entity' => $params['filterData']['entity'] ?? null,
            'entityType' => $params['filterData']['entityType'] ?? null,
            'status' => $params['filterData']['selectedStatuses'] ?? null,
        ];

        $this->sortField = $params['filterData']['sortField'] ?? 'start_epoch';
        $this->sortOrder = $params['filterData']['sortOrder'] ?? 'desc';

        // Initialize an empty array for statistics
        $extensionStats = [];

        // Use chunking to process records in smaller batches
        $this->builder($this->filters)->chunk(1000, function ($cdrs) use (&$extensionStats) {
            foreach ($cdrs as $cdr) {
                // Skip records without extension_uuid
                if (empty($cdr->extension_uuid) || empty($cdr->extension)) {
                    continue;
                }

                // Use extension_uuid as the key
                $extensionUuid = $cdr->extension_uuid;

                // Initialize stats for this extension if not already set
                if (!isset($extensionStats[$extensionUuid])) {
                    $extensionStats[$extensionUuid] = [
                        'extension_uuid' => $extensionUuid,
                        'extension_label' => null,
                        'extension' => null,
                        'inbound' => 0,
                        'outbound' => 0,
                        'missed' => 0,
                        'total_duration' => 0,
                        'call_count' => 0,
                        'total_talk_time' => 0,
                    ];
                }

                // Extract and format the extension name if available
                if ($cdr->extension) {
                    $extensionStats[$extensionUuid]['extension_label'] = $cdr->extension->name_formatted;
                }

                // Update call count and talk time
                $extensionStats[$extensionUuid]['call_count'] += 1;
                $extensionStats[$extensionUuid]['total_duration'] += $cdr->duration;
                $extensionStats[$extensionUuid]['total_talk_time'] += $cdr->duration;

                // Check direction (inbound/outbound)
                if ($cdr->direction === 'inbound') {
                    $extensionStats[$extensionUuid]['inbound'] += 1;
                } elseif ($cdr->direction === 'outbound') {
                    $extensionStats[$extensionUuid]['outbound'] += 1;
                }

                // Check missed calls
                if ($cdr->missed_call === true) {
                    $extensionStats[$extensionUuid]['missed'] += 1;
                }
            }
        });

        // Calculate average call duration for each extension
        foreach ($extensionStats as &$stats) {
            $stats['average_duration'] = $stats['call_count'] > 0 ? $stats['total_duration'] / $stats['call_count'] : 0;

            // Format durations using getFormattedDuration method
            $stats['total_duration_formatted'] = $this->getFormattedDuration($stats['total_duration']);
            $stats['total_talk_time_formatted'] = $this->getFormattedDuration($stats['total_talk_time']);
            $stats['average_duration_formatted'] = $this->getFormattedDuration($stats['average_duration']);
        }

        // Paginate the result manually
        $perPage = $params['paginate'] ?? 15;  // Default items per page
        $currentPage = $params['page'] ?? 1;   // Current page number
        $total = count($extensionStats);

        // logger($extensionStats);

        // Manually paginate the array of statistics
        $paginatedStats = collect($extensionStats)->forPage($currentPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedStats,
            $total,
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }


    protected function filterStartPeriod($query, $value)
    {
        $query->where('start_epoch', '>=', $value->getTimestamp());
    }

    protected function filterEndPeriod($query, $value)
    {
        $query->where('start_epoch', '<=', $value->getTimestamp());
    }

    protected function filterDirection($query, $value)
    {
        $query->where('direction', 'ilike', '%' . $value . '%');
    }

    protected function filterSearch($query, $value)
    {
        $searchable = $this->searchable;
        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value, $searchable) {
            foreach ($searchable as $field) {
                if (strpos($field, '.') !== false) {
                    // Nested field (e.g., 'extension.name_formatted')
                    [$relation, $nestedField] = explode('.', $field, 2);

                    $query->orWhereHas($relation, function ($query) use ($nestedField, $value) {
                        $query->where($nestedField, 'ilike', '%' . $value . '%');
                    });
                } else {
                    // Direct field
                    $query->orWhere($field, 'ilike', '%' . $value . '%');
                }
            }
        });
    }

    protected function filterEntity($query, $value)
    {
        if (!isset($this->filters['entityType'])) {
            return;
        }
        switch ($this->filters['entityType']) {
            case 'queue':
                $query->where('call_center_queue_uuid', 'ilike', '%' . $value . '%');
                break;
            case 'extension':
                $extention = Extensions::find($value);
                $query->where(function ($query) use ($extention) {
                    $query->where('extension_uuid', 'ilike', '%' . $extention->extension_uuid . '%')
                        ->orWhere('caller_id_number', $extention->extension)
                        ->orWhere('caller_destination', $extention->extension)
                        ->orWhere('source_number', $extention->extension)
                        ->orWhere('destination_number', $extention->extension);
                });
                break;
        }
    }

    protected function filterStatus($query, $array)
    {
        if ($array) {
            $query->where(function ($query) use ($array) {
                foreach ($array as $status) {
                    if ($status === 'missed call') {
                        $query->orWhere(function ($query) {
                            $query->where('voicemail_message', false)
                                ->where('missed_call', true)
                                ->where('hangup_cause', 'NORMAL_CLEARING')
                                ->whereNull('cc_cancel_reason') // Ensure cc_cancel_reason is not set
                                ->whereNull('cc_cause'); // Ensure cc_cause is not set
                        });
                    } elseif ($status === 'abandoned') {
                        $query->orWhere(function ($query) {
                            $query->where('voicemail_message', false)
                                ->where('missed_call', true)
                                ->where('hangup_cause', 'NORMAL_CLEARING')
                                ->where('cc_cancel_reason', 'BREAK_OUT')
                                ->where('cc_cause', 'cancel');
                        });
                    } elseif ($status === 'voicemail') {
                        $query->orWhere('voicemail_message', true);
                    } else {
                        $query->orWhere('status', $status);
                    }
                }
            });
        }
    }

    public function getFormattedDuration($value)
    {
        // Calculate hours, minutes, and seconds
        $hours = floor($value / 3600);
        $minutes = floor(($value % 3600) / 60);
        $seconds = $value % 60;

        // Format each component to be two digits with leading zeros if necessary
        $formattedHours = str_pad($hours, 2, "0", STR_PAD_LEFT);
        $formattedMinutes = str_pad($minutes, 2, "0", STR_PAD_LEFT);
        $formattedSeconds = str_pad($seconds, 2, "0", STR_PAD_LEFT);

        // Concatenate the formatted components
        $formattedDuration = $formattedHours . ':' . $formattedMinutes . ':' . $formattedSeconds;

        return $formattedDuration;
    }
}
