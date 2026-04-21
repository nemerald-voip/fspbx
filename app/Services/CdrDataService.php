<?php

namespace App\Services;

use App\Data\Api\V1\CdrData;
use App\Models\CDR;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use App\Data\Api\V1\CdrCallFlowStepData;
use App\Exceptions\ApiException;
use App\Models\Dialplans;
use App\Models\Extensions;
use Illuminate\Support\Carbon;

class CdrDataService
{

    public function getData($params = [])
    {
        $currentDomain = $params['domain_uuid'];

        // Check if user is allowed to see all CDRs for tenant
        $user = auth()->user();
        if ($user && userCheckPermission("xml_cdr_view") && userCheckPermission("xml_cdr_view_self_records")) {
            $params['filter']['entity']['value'] = $user->extension_uuid;
            $params['filter']['entity']['type'] = 'extension';
        }

        if (empty($params['filter']['showGlobal'])) {
            $params['filter']['showGlobal'] = 'false';
        }

        // Main query:
        $cdrs = QueryBuilder::for(CDR::class, request()->merge($params))
            ->select([
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
                'start_epoch',
                'end_epoch',
                'duration',
                'record_path',
                'record_name',
                'voicemail_message',
                'missed_call',
                'cc_cancel_reason',
                'cc_cause',
                'waitsec',
                'hangup_cause',
                'hangup_cause_q850',
                'sip_hangup_disposition',
                'rtp_audio_in_mos',
                'status',
            ])
            ->with([
                'domain:domain_uuid,domain_name,domain_description',
                'extension:extension_uuid,extension,effective_caller_id_name',
            ])
            ->allowedFilters([
                AllowedFilter::callback('startPeriod', function ($query, $value) {
                    $query->where('start_epoch', '>=', $value);
                }),
                AllowedFilter::callback('endPeriod', function ($query, $value) {
                    $query->where('start_epoch', '<=', $value);
                }),
                AllowedFilter::callback('direction', function ($query, $value) {
                    $query->where('direction',  $value);
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('caller_id_name', 'ilike', "%{$value}%")
                            ->orWhere('caller_id_number', 'ilike', "%{$value}%")
                            ->orWhere('caller_destination', 'ilike', "%{$value}%")
                            ->orWhere('destination_number', 'ilike', "%{$value}%")
                            ->orWhere('xml_cdr_uuid', 'ilike', "%{$value}%");

                        // Search inside related extension fields
                        $q->orWhereHas('extension', function ($extQuery) use ($value) {
                            $extQuery->where('extension', 'ilike', "%{$value}%")
                                ->orWhere('effective_caller_id_name', 'ilike', "%{$value}%");
                        });
                    });
                }),
                AllowedFilter::callback('sentiment', function ($query, $value) {
                    if ($value === null) return;

                    $value = strtolower(trim((string) $value['value']));
                    $allowed = ['negative', 'neutral', 'positive'];

                    if (!in_array($value, $allowed, true)) {
                        return; // ignore invalid values
                    }

                    $query->whereHas('callTranscription', function ($tq) use ($value) {
                        // Postgres jsonb: this compiles to summary_payload->>'sentiment_overall' = ?
                        $tq->where('summary_payload->sentiment_overall', $value);
                    });
                }),
                AllowedFilter::callback('entity', function ($query, $value) {
                    switch ($value['type']) {
                        case 'queue':
                            $query->where('call_center_queue_uuid', $value['value']);
                            break;
                        case 'extension':
                            if (!$value['value']) {
                                $query->where('xml_cdr_uuid', null);
                                break;
                            }
                            $extension = \App\Models\Extensions::find($value['value']);
                            if (!$extension) break;
                            $query->where(function ($q) use ($extension) {
                                $q->where('extension_uuid', $extension->extension_uuid)
                                    ->orWhere('caller_id_number', $extension->extension)
                                    ->orWhere('caller_destination', $extension->extension)
                                    ->orWhere('source_number', $extension->extension)
                                    ->orWhere('destination_number', $extension->extension)
                                    ->orWhere('destination_number', '*99' . $extension->extension);
                            });
                            break;
                    }
                }),
                AllowedFilter::callback('status', function ($query, $value) {
                    $status = $value['value'];
                    $query->where(function ($q) use ($status) {
                        if ($status === 'missed call') {
                            $q->orWhere(function ($q2) {
                                $q2->where('voicemail_message', false)
                                    ->where('missed_call', true)
                                    ->where('hangup_cause', 'NORMAL_CLEARING')
                                    ->whereNull('cc_cancel_reason')
                                    ->whereNull('cc_cause');
                            });
                        } elseif ($status === 'abandoned') {
                            $q->orWhere(function ($q2) {
                                $q2->where('voicemail_message', false)
                                    ->where('missed_call', true)
                                    ->where('hangup_cause', 'NORMAL_CLEARING')
                                    ->where('cc_cancel_reason', 'BREAK_OUT')
                                    ->where('cc_cause', 'cancel');
                            });
                        } elseif ($status === 'voicemail') {
                            $q->orWhere('voicemail_message', true);
                        } else {
                            $q->orWhere('status', $status);
                        }
                    });
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) use ($currentDomain) {
                    // If showGlobal is falsey (0, '0', false, null), restrict to the current domain
                    if (!$value || $value === '0' || $value === 0 || $value === 'false') {
                        $query->where('domain_uuid', $currentDomain);
                    }
                    // else, do nothing and show all domains
                }),
            ])
            ->where('hangup_cause', '!=', 'LOSE_RACE')
            ->whereNull('cc_member_session_uuid')
            ->whereNull('originating_leg_uuid')
            // Sorting
            ->allowedSorts([
                'direction',
                'caller_id_name',
                'caller_id_number',
                'caller_destination',
                'destination_number',
                'start_epoch',
                'duration',
                'rtp_audio_in_mos',
            ])
            ->defaultSort('-start_epoch');

        if ($params['paginate']) {
            $cdrs = $cdrs->paginate($params['paginate']);
        } else {
            $cdrs = $cdrs->cursor();
        }
        // logger($cdrs);

        return $cdrs;
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

    public function getExtensionStatistics($params = [])
    {
        $domain_uuid = $params['domain_uuid'] ?? session('domain_uuid');

        $search = trim($params['filter']['search'] ?? '');

        // 1) Load all extensions in this domain (only what we need)
        $extensions = \App\Models\Extensions::query()
            ->where('domain_uuid', $domain_uuid)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('extension', 'ILIKE', "%{$search}%")
                        ->orWhere('effective_caller_id_name', 'ILIKE', "%{$search}%");
                });
            })
            ->get(['extension_uuid', 'extension', 'effective_caller_id_name']);

        // Lookups and pre-initialized stats
        $extToUuid  = [];
        $metaByUuid = [];
        $stats      = [];

        foreach ($extensions as $ext) {
            $extNum = (string) $ext->extension;
            $extToUuid[$extNum] = $ext->extension_uuid;

            $metaByUuid[$ext->extension_uuid] = [
                'extension'       => $extNum,
                'extension_label' => $ext->name_formatted,
            ];

            $stats[$ext->extension_uuid] = [
                'extension_uuid'            => $ext->extension_uuid,
                'extension_label'           => $ext->name_formatted,
                'extension'                 => $extNum,
                'inbound'                   => 0,
                'outbound'                  => 0,
                'missed'                    => 0,
                'total_duration'            => 0,
                'call_count'                => 0,
                'total_talk_time'           => 0,
                'average_duration'          => 0,           // filled later
                'total_duration_formatted'  => '00:00:00',  // filled later
                'total_talk_time_formatted' => '00:00:00',  // filled later
                'average_duration_formatted' => '00:00:00',  // filled later
            ];
        }

        // 2) Stream CDRs for the requested period (cursor from getData)
        // Make sure caller doesn't paginate when calling stats; we rely on cursor streaming.
        $cdrs = $this->getData($params);

        foreach ($cdrs as $cdr) {
            // Collect matched extension UUIDs for this CDR (uuid or number fields or *99ext)
            $matched = [];

            // a) Direct link via extension_uuid (only if it's an extension from this domain)
            if ($cdr->extension_uuid && isset($metaByUuid[$cdr->extension_uuid])) {
                $matched[$cdr->extension_uuid] = true;
            }

            // b) Match by number fields
            $n1 = (string) ($cdr->caller_id_number ?? '');
            $n2 = (string) ($cdr->caller_destination ?? '');
            $n3 = (string) ($cdr->source_number ?? '');
            $n4 = (string) ($cdr->destination_number ?? '');

            if ($n1 && isset($extToUuid[$n1])) $matched[$extToUuid[$n1]] = true;
            if ($n2 && isset($extToUuid[$n2])) $matched[$extToUuid[$n2]] = true;
            if ($n3 && isset($extToUuid[$n3])) $matched[$extToUuid[$n3]] = true;
            if ($n4 && isset($extToUuid[$n4])) $matched[$extToUuid[$n4]] = true;

            // c) Voicemail (*99{extension}) on destination_number
            if ($n4 !== '' && str_starts_with($n4, '*99')) {
                $maybeExt = substr($n4, 3);
                if ($maybeExt !== '' && isset($extToUuid[$maybeExt])) {
                    $matched[$extToUuid[$maybeExt]] = true;
                }
            }

            if (!$matched) continue;

            // Fast locals
            $duration  = (int) ($cdr->duration ?? 0);
            $direction = $cdr->direction ?? null;
            $missed    = !empty($cdr->missed_call);

            foreach (array_keys($matched) as $extUuid) {
                $s = &$stats[$extUuid];
                $s['call_count']      += 1;
                $s['total_duration']  += $duration;
                $s['total_talk_time'] += $duration;

                if ($direction === 'inbound')  $s['inbound']  += 1;
                if ($direction === 'outbound') $s['outbound'] += 1;
                if ($missed)                   $s['missed']   += 1;
            }
        }

        // 3) Compute averages + formatted durations
        foreach ($stats as &$s) {
            $s['average_duration']          = $s['call_count'] > 0 ? ($s['total_duration'] / $s['call_count']) : 0;
            $s['total_duration_formatted']  = $this->getFormattedDuration($s['total_duration']);
            $s['total_talk_time_formatted'] = $this->getFormattedDuration($s['total_talk_time']);
            $s['average_duration_formatted'] = $this->getFormattedDuration($s['average_duration']);
        }
        unset($s);

        // 4) Paginate extensions (not CDRs)
        $perPage     = (int) ($params['per_page'] ?? 50);
        $currentPage = (int) ($params['page']     ?? 1);
        // Sort by extension before pagination
        $all = collect($stats)
            ->sortBy('extension', SORT_NATURAL) // SORT_NATURAL keeps 1, 2, 10 in the right order
            ->values()
            ->all();
        $total       = count($all);
        $pageItems   = collect($all)->forPage($currentPage, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }


    public function getApiIndexQuery(string $domainUuid): QueryBuilder
    {
        return QueryBuilder::for(CDR::class)
            ->where('domain_uuid', $domainUuid)
            ->defaultSort('xml_cdr_uuid')
            ->reorder('xml_cdr_uuid')
            ->select([
                'xml_cdr_uuid',
                'domain_uuid',
                'sip_call_id',
                'extension_uuid',
                'call_center_queue_uuid',
                'direction',
                'caller_id_name',
                'caller_id_number',
                'caller_destination',
                'destination_number',
                'start_epoch',
                'answer_epoch',
                'end_epoch',
                'duration',
                'voicemail_message',
                'missed_call',
                'hangup_cause',
                'hangup_cause_q850',
                'sip_hangup_disposition',
                'cc_cancel_reason',
                'cc_cause',
                'status',
            ]);
    }

    public function applyApiIndexFilters(QueryBuilder $query, array $filters): QueryBuilder
    {
        if (!empty($filters['starting_after'])) {
            $query->where('xml_cdr_uuid', '>', $filters['starting_after']);
        }

        if (!empty($filters['search'])) {
            $search = $this->normalizeSearchTerm($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('caller_id_name', 'ilike', "%{$search}%")
                    ->orWhere('caller_id_number', 'ilike', "%{$search}%")
                    ->orWhere('caller_destination', 'ilike', "%{$search}%")
                    ->orWhere('destination_number', 'ilike', "%{$search}%")
                    ->orWhere('sip_call_id', 'ilike', "%{$search}%")
                    ->orWhere('xml_cdr_uuid', 'ilike', "%{$search}%");
            });
        }

        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (!empty($filters['status'])) {
            $this->applyStatusFilter($query, $filters['status']);
        }

        if (!empty($filters['extension_uuid'])) {
            $query->where('extension_uuid', $filters['extension_uuid']);
        }

        if (!empty($filters['call_center_queue_uuid'])) {
            $query->where('call_center_queue_uuid', $filters['call_center_queue_uuid']);
        }

        if (!empty($filters['date_from_epoch'])) {
            $query->where('start_epoch', '>=', $filters['date_from_epoch']);
        }

        if (!empty($filters['date_to_epoch'])) {
            $query->where('start_epoch', '<=', $filters['date_to_epoch']);
        }

        return $query;
    }

    public function buildApiIndexData($rows)
    {
        return $rows->map(function ($cdr) {
            return new CdrData(
                xml_cdr_uuid: (string) $cdr->xml_cdr_uuid,
                object: 'cdr',
                domain_uuid: (string) $cdr->domain_uuid,

                sip_call_id: $cdr->sip_call_id,
                extension_uuid: $cdr->extension_uuid,
                call_center_queue_uuid: $cdr->call_center_queue_uuid,

                direction: $cdr->direction,

                caller_id_name: $cdr->caller_id_name,
                caller_id_number: $cdr->caller_id_number,
                caller_destination: $cdr->caller_destination,
                destination_number: $cdr->destination_number,

                start_epoch: $cdr->start_epoch !== null ? (int) $cdr->start_epoch : null,
                answer_epoch: $cdr->answer_epoch !== null ? (int) $cdr->answer_epoch : null,
                end_epoch: $cdr->end_epoch !== null ? (int) $cdr->end_epoch : null,

                duration: $cdr->duration !== null ? (int) $cdr->duration : null,

                hangup_cause: $cdr->hangup_cause,
                hangup_cause_q850: $cdr->hangup_cause_q850,

                status: $cdr->status,
                call_disposition: $cdr->call_disposition,
            );
        })->values();
    }

    public function normalizeSearchTerm($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $search = trim((string) $value);

        if (preg_match('/[A-Za-z]/', $search)) {
            return $search;
        }

        $digits = preg_replace('/\D+/', '', $search);

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return $digits;
    }

    public function toBool($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    protected function applyStatusFilter($query, $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        $query->where(function ($q) use ($status) {
            if ($status === 'missed call') {
                $q->where(function ($q2) {
                    $q2->where('voicemail_message', false)
                        ->where('missed_call', true)
                        ->where('hangup_cause', 'NORMAL_CLEARING')
                        ->whereNull('cc_cancel_reason')
                        ->whereNull('cc_cause');
                });
            } elseif ($status === 'abandoned') {
                $q->where(function ($q2) {
                    $q2->where('voicemail_message', false)
                        ->where('missed_call', true)
                        ->where('hangup_cause', 'NORMAL_CLEARING')
                        ->where('cc_cancel_reason', 'BREAK_OUT')
                        ->where('cc_cause', 'cancel');
                });
            } elseif ($status === 'voicemail') {
                $q->where('voicemail_message', true);
            } else {
                $q->where('status', $status);
            }
        });
    }


    public function buildApiShowPayload(string $domainUuid, string $xmlCdrUuid): CdrData
    {
        $cdr = CDR::query()
            ->where('domain_uuid', $domainUuid)
            ->where('xml_cdr_uuid', $xmlCdrUuid)
            ->select([
                'xml_cdr_uuid',
                'domain_uuid',
                'sip_call_id',
                'extension_uuid',
                'call_center_queue_uuid',
                'direction',
                'caller_id_name',
                'caller_id_number',
                'caller_destination',
                'destination_number',
                'start_epoch',
                'answer_epoch',
                'end_epoch',
                'duration',
                'call_flow',
                'missed_call',
                'voicemail_message',
                'hangup_cause',
                'hangup_cause_q850',
                'cc_cancel_reason',
                'cc_cause',
                'sip_hangup_disposition',
                'status',
            ])
            ->first();

        if (! $cdr) {
            throw new ApiException(404, 'invalid_request_error', 'CDR not found.', 'resource_missing', 'xml_cdr_uuid');
        }

        return new CdrData(
            xml_cdr_uuid: (string) $cdr->xml_cdr_uuid,
            object: 'cdr',
            domain_uuid: (string) $cdr->domain_uuid,

            sip_call_id: $cdr->sip_call_id,
            extension_uuid: $cdr->extension_uuid,
            call_center_queue_uuid: $cdr->call_center_queue_uuid,

            direction: $cdr->direction,

            caller_id_name: $cdr->caller_id_name,
            caller_id_number: $cdr->caller_id_number,
            caller_destination: $cdr->caller_destination,
            destination_number: $cdr->destination_number,

            start_epoch: $cdr->start_epoch !== null ? (int) $cdr->start_epoch : null,
            answer_epoch: $cdr->answer_epoch !== null ? (int) $cdr->answer_epoch : null,
            end_epoch: $cdr->end_epoch !== null ? (int) $cdr->end_epoch : null,

            duration: $cdr->duration !== null ? (int) $cdr->duration : null,

            hangup_cause: $cdr->hangup_cause,
            hangup_cause_q850: $cdr->hangup_cause_q850,

            voicemail_message: $this->toBool($cdr->voicemail_message),
            cc_cancel_reason: $cdr->cc_cancel_reason,
            cc_cause: $cdr->cc_cause,
            sip_hangup_disposition: $cdr->sip_hangup_disposition,

            status: $cdr->status,
            call_disposition: $cdr->call_disposition,

            call_flow: $this->buildApiCallFlowData($cdr),
        );
    }

    public function buildApiCallFlowData(CDR $cdr): array
    {
        return $this->buildCallFlowSummary($cdr)
            ->map(function ($row) {
                return new CdrCallFlowStepData(
                    destination_number: $row['destination_number'] ?? null,
                    context: $row['context'] ?? null,

                    bridged_time: $row['bridged_time'] ?? null,
                    created_time: $row['created_time'] ?? null,
                    answered_time: $row['answered_time'] ?? null,
                    progress_time: $row['progress_time'] ?? null,
                    transfer_time: $row['transfer_time'] ?? null,
                    profile_created_time: $row['profile_created_time'] ?? null,
                    profile_end_time: $row['profile_end_time'] ?? null,
                    progress_media_time: $row['progress_media_time'] ?? null,
                    hangup_time: $row['hangup_time'] ?? null,

                    duration_seconds: $row['duration_seconds'] ?? null,
                    duration_formatted: $row['duration_formatted'] ?? null,

                    call_disposition: $row['call_disposition'] ?? null,
                    time_line: $row['time_line'] ?? null,

                    dialplan_app: $row['dialplan_app'] ?? null,
                    dialplan_name: $row['dialplan_name'] ?? null,
                    dialplan_description: $row['dialplan_description'] ?? null,
                );
            })
            ->all();
    }

    public function buildCallFlowSummary(CDR $cdr)
    {
        $mainCallFlowData = collect(json_decode($cdr->call_flow, true) ?: []);
        $combinedCallFlowData = $mainCallFlowData;

        if (! empty($cdr->call_center_queue_uuid)) {
            $relatedCalls = $cdr->relatedQueueCalls()
                ->where('domain_uuid', $cdr->domain_uuid)
                ->select([
                    'xml_cdr_uuid',
                    'domain_uuid',
                    'call_flow',
                    'direction',
                    'hangup_cause',
                    'sip_hangup_disposition',
                ])
                ->get();

            foreach ($relatedCalls as $relatedCall) {
                $relatedCallFlow = collect(json_decode($relatedCall->call_flow, true) ?: [])
                    ->map(function ($flow) use ($relatedCall) {
                        if (isset($flow['times'])) {
                            $flow['times']['call_disposition'] = $relatedCall->call_disposition;
                        }

                        return $flow;
                    });

                $combinedCallFlowData = $combinedCallFlowData->merge($relatedCallFlow);
            }
        }

        $relatedCalls = $cdr->relatedRingGroupCalls()
            ->where('domain_uuid', $cdr->domain_uuid)
            ->select([
                'xml_cdr_uuid',
                'domain_uuid',
                'call_flow',
                'direction',
                'hangup_cause',
                'sip_hangup_disposition',
            ])
            ->get();

        foreach ($relatedCalls as $relatedCall) {
            $relatedCallFlow = collect(json_decode($relatedCall->call_flow, true) ?: [])
                ->map(function ($flow) use ($relatedCall) {
                    if (isset($flow['times'])) {
                        $flow['times']['call_disposition'] = $relatedCall->call_disposition;
                    }

                    return $flow;
                });

            $combinedCallFlowData = $combinedCallFlowData->merge($relatedCallFlow);
        }

        $callFlowSummary = $this->handleCallFlowSteps($combinedCallFlowData)
            ->map(function ($row) {
                return $this->buildSummaryItem($row);
            })
            ->sortBy('profile_created_time')
            ->values()
            ->map(function ($row) use ($cdr) {
                $timeDifference = $row['profile_created_time'] - $cdr->start_epoch;
                $row['time_line'] = sprintf('%02d:%02d', floor($timeDifference / 60), $timeDifference % 60);

                if ($cdr->direction === 'outbound') {
                    $row['dialplan_app'] = 'Outbound Call';
                }

                return $row;
            });

        return $this->formatTimes($callFlowSummary)
            ->map(function ($row) use ($cdr) {
                return $this->getAppDetails($row, (string) $cdr->domain_uuid);
            })
            ->values();
    }


    /**
     * Handle transfers in the call flow array
     *
     * @param Collection $callFlowData
     * @return Collection
     */
    protected function handleCallFlowSteps($callFlowData)
    {
        $newRows = collect();

        $callFlowData->reduce(function ($carry, $row) use ($newRows) {

            // Check if 'ring_group_uuid' exists in the 'application' array
            if (isset($row['extension']['application'])) {
                foreach ($row['extension']['application'] as $application) {
                    if (isset($application['@attributes']['app_data']) && strpos($application['@attributes']['app_data'], 'ring_group_uuid') !== false) {
                        // Extract the ring_group_uuid value
                        preg_match('/ring_group_uuid=([a-f0-9\-]+)/', $application['@attributes']['app_data'], $matches);
                        if (isset($matches[1]) && $row['times']['bridged_time'] != '0') {

                            $newRow = [
                                'caller_profile' => [
                                    'destination_number' => $row['caller_profile']['destination_number'],
                                    'context' => !empty($row['caller_profile']['context']) ? $row['caller_profile']['context'] : '',
                                    'caller_id_name' => $row['caller_profile']['callee_id_name'],
                                    'caller_id_number' => $row['caller_profile']['caller_id_number'],
                                ],
                                'times' => [
                                    'bridged_time' => '0',
                                    'created_time' => $row['times']['profile_created_time'],
                                    'answered_time' => '0',
                                    'progress_time' => $row['times']['profile_created_time'],
                                    'transfer_time' => $row['times']['answered_time'],
                                    'progress_media_time' => $row['times']['profile_created_time'],
                                    'hangup_time' => 0,
                                    'profile_created_time' => $row['times']['profile_created_time'],
                                    'profile_end_time' => $row['times']['bridged_time'] != '0' ? $row['times']['bridged_time'] : $row['times']['profile_end_time']
                                ]
                            ];

                            // Insert the new row right before the current row
                            $newRows->push($newRow);

                            // Adjust created time for current row
                            $row['times']['profile_created_time'] = $row['times']['bridged_time'] != '0' ? $row['times']['bridged_time'] : $row['times']['transfer_time'];
                            $row['times']['progress_media_time'] = $row['times']['bridged_time'] != '0' ? $row['times']['bridged_time'] : $row['times']['transfer_time'];
                        } else {
                            $row['caller_profile']['callee_id_number'] = $row['caller_profile']['destination_number'];
                        }
                    }
                }
            }

            // Push the current row (updated or not) to the new collection
            $newRows->push($row);

            // Return the carry for reduce
            return $carry;
        }, $callFlowData);

        return $newRows;
    }



    /**
     * Format the times in the call flow array
     *
     * @param Collection $callFlowSummary
     * @return Collection
     */
    protected function formatTimes($callFlowSummary)
    {
        return $callFlowSummary->map(function ($item) {
            // Define the keys that need to be formatted
            $timeKeys = [
                'created_time',
                'answered_time',
                'progress_time',
                'bridged_time',
                'transfer_time',
                'profile_created_time',
                'profile_end_time',
                'progress_media_time',
                'hangup_time'
            ];

            // Loop through each key and format the time
            foreach ($timeKeys as $key) {
                if (isset($item[$key]) && $item[$key] != 0) {
                    $item[$key] = Carbon::createFromTimestamp($item[$key])->toDateTimeString();
                }
            }

            return $item;
        });
    }


    /**
     * Build a summary item for the call flow
     *
     * @param array $row
     * @return array
     */
    protected function buildSummaryItem(array $row): array
    {
        // $app = $this->findApp($row['caller_profile']['destination_number']);

        $profileCreatedEpoch = $this->formatTime($row['times']['profile_created_time']);
        $profileEndEpoch = $this->formatTime($row['times']['profile_end_time']);


        // logger($row);

        if (!empty($row["caller_profile"]["destination_number"]) && (substr($row["caller_profile"]["destination_number"], 0, 4) == 'park' || (substr($row["caller_profile"]["destination_number"], 0, 3) == '*59' && strlen($row["caller_profile"]["destination_number"]) > 3))) {
            if (strpos($row['caller_profile']['transfer_source'], "park+") !== false) {
                $destinationNumber = $row['caller_profile']['destination_number'];
            } else {
                $destinationNumber = $row['caller_profile']['callee_id_number'];
            }
        }
        //check if this is intercept
        else if (
            isset($row["caller_profile"]["originator"]["originator_caller_profile"]["destination_number"]) &&
            (substr($row["caller_profile"]["originator"]["originator_caller_profile"]["destination_number"], 0, 3) == '*97' &&
                strlen($row["caller_profile"]["originator"]["originator_caller_profile"]["destination_number"]) > 3)
        ) {

            $destinationNumber = $row["caller_profile"]["originator"]["originator_caller_profile"]["destination_number"] . "^" . $row["caller_profile"]["originator"]["originator_caller_profile"]["caller_id_number"];
        }
        // all other cases
        else {
            $destinationNumber = !empty($row['caller_profile']['callee_id_number']) ? $row['caller_profile']['callee_id_number'] : $row['caller_profile']['destination_number'];
        }

        $durationInSeconds = $profileEndEpoch - $profileCreatedEpoch;
        $minutes = floor($durationInSeconds / 60);
        $seconds = $durationInSeconds % 60;

        if ($minutes > 0) {
            $durationFormatted = sprintf('%d min %02d s', $minutes, $seconds);
        } else {
            $durationFormatted = sprintf('%02d s', $seconds);
        }

        return [
            'destination_number' => $destinationNumber,
            // 'destination_number' => !empty($row['caller_profile']['callee_id_number']) ? $row['caller_profile']['callee_id_number'] : $row['caller_profile']['destination_number'],
            'context' => !empty($row['caller_profile']['context']) ? $row['caller_profile']['context'] : '',
            'bridged_time' => $row['times']['bridged_time'] == 0 ? 0 : $this->formatTime($row['times']['bridged_time']),
            'created_time' => $row['times']['created_time'] == 0 ? 0 : $this->formatTime($row['times']['created_time']),
            'answered_time' => $row['times']['answered_time'] == 0 ? 0 : $this->formatTime($row['times']['answered_time']),
            'progress_time' => $row['times']['progress_time'] == 0 ? 0 : $this->formatTime($row['times']['progress_time']),
            'transfer_time' => $row['times']['transfer_time'] == 0 ? 0 : $this->formatTime($row['times']['transfer_time']),
            'profile_created_time' => $row['times']['profile_created_time'] == 0 ? 0 : $this->formatTime($row['times']['profile_created_time']),
            'profile_end_time' => $row['times']['profile_end_time'] == 0 ? 0 : $this->formatTime($row['times']['profile_end_time']),
            'progress_media_time' => $row['times']['progress_media_time'] == 0 ? 0 : $this->formatTime($row['times']['progress_media_time']),
            'hangup_time' => $row['times']['hangup_time'] == 0 ? 0 : $this->formatTime($row['times']['hangup_time']),
            'duration_seconds' => $durationInSeconds,
            'duration_formatted' => $durationFormatted,
            'call_disposition' =>  isset($row['times']['call_disposition']) ? $row['times']['call_disposition'] : null,
        ];
    }

    private function formatTime($time)
    {
        return (int) round($time / 1000000);
    }

    /**
     * Get app details associated with call flow step
     *
     */
    public function getAppDetails($row, string $domainUuid)
    {
        // Convert to E164 format if this is a valid number
        $destination = formatPhoneNumber($row['destination_number'], "US", 0); // 0 is E164 format

        // Check if the number starts with '+1' and remove it if present
        if (strpos($destination, '+1') === 0) {
            $bareNumber = substr($destination, 2);
        } else {
            $bareNumber = $destination;
        }

        $dialplan = Dialplans::where('dialplan_context', $row['context'])
            ->where(function ($query) use ($destination, $bareNumber) {
                $query->where('dialplan_number', $destination)
                    ->orWhere('dialplan_number', '=', $bareNumber)
                    ->orWhere('dialplan_number', '=', '1' . $bareNumber);
            })
            ->where('dialplan_enabled', 'true')
            ->select(
                'dialplan_uuid',
                'dialplan_name',
                'dialplan_number',
                'dialplan_xml',
                'dialplan_description',
            )
            ->first();

        if ($dialplan) {
            $patterns = [
                'ring_group_uuid' => [
                    'pattern' => '/ring_group_uuid=([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/',
                    'app' => 'Ring Group',
                ],
                'ivr_menu_uuid' => [
                    'pattern' => '/ivr_menu_uuid=([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/',
                    'app' => 'Auto Receptionist',
                ],
                'call_center_queue_uuid' => [
                    'pattern' => '/call_center_queue_uuid=([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/',
                    'app' => 'Contact Center Queue',
                ],
                'call_direction_inbound' => [
                    'pattern' => '/call_direction=inbound/',
                    'app' => 'Inbound Call',
                ],
                'date_time' => [
                    'pattern' => '/\b(?:year|yday|mon|mday|week|mweek|wday|hour|minute|minute-of-day|time-of-day|date-time)=/',
                    'app' => 'Schedule',
                ],
                'application_rxfax' => [
                    'pattern' => '/application="rxfax"/',
                    'app' => 'Virtual Fax',
                ],
                'call_flow_uuid' => [
                    'pattern' => '/call_flow_uuid=([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/',
                    'app' => 'Call Flow',
                ],
            ];

            foreach ($patterns as $key => $info) {
                if (preg_match($info['pattern'], $dialplan->dialplan_xml, $matches)) {
                    $row['dialplan_app'] = $info['app'];
                    $row['dialplan_name'] = $dialplan->dialplan_name;
                    $row['dialplan_description'] = $dialplan->dialplan_description;
                    break; // Stop checking after the first match
                }
            }

            return $row;
        }

        // Check if destination is Park
        if (strpos($row['destination_number'], "park+") !== false) {
            $row['dialplan_app'] = "Park";
            $row['dialplan_name'] = substr($row['destination_number'], 6);
            $row['dialplan_description'] = '';
            return $row;
        }

        // Check if destination is voicemail
        if ((substr($row['destination_number'], 0, 3) == '*99') !== false) {
            $row['dialplan_app'] = "Voicemail";
            $row['dialplan_name'] = substr($row['destination_number'], 3);
            $row['dialplan_description'] = '';
            return $row;
        }

        // Check if destination is intercept
        if ((substr($row['destination_number'], 0, 3) == '*97') !== false) {
            // Use regex to capture the digits after *97 up to ^ and everything after ^
            if (preg_match('/\*97(\d+)\^(.+)/', $row['destination_number'], $matches)) {
                $interceptedExt = $matches[1];
                $intereceptedByExt = $matches[2];

                $row['dialplan_app'] = "Call Intercept " . $interceptedExt;

                // Check if intereceptedByExt is extension
                $extension = Extensions::where('domain_uuid', $domainUuid)
                    ->where('extension', $intereceptedByExt)
                    ->first();

                if ($extension) {
                    $row['dialplan_name'] = $extension->effective_caller_id_name . ' (' . $intereceptedByExt .  ')';
                } else {
                    $row['dialplan_name'] = null;
                }
                $row['dialplan_description'] = '';

                return $row;
            }
        }

        // Check if destination is extension
        $extension = Extensions::where('domain_uuid', $domainUuid)
            ->where('extension', $row['destination_number'])
            ->first();

        if ($extension) {
            $row['dialplan_app'] = "Extension";
            $row['dialplan_name'] = $extension->effective_caller_id_name;
            $row['dialplan_description'] = $extension->description;
            return $row;
        }

        $row['dialplan_app'] = "Misc. Destination";
        $row['dialplan_name'] = $row['destination_number'];
        $row['dialplan_description'] = null;
        return $row;
    }
}
