<?php

namespace App\Services;

use App\Data\Api\V1\CdrData;
use App\Models\CDR;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use App\Data\Api\V1\CdrCallFlowStepData;
use App\Exceptions\ApiException;
use App\Models\BasicDialerCampaignAttempt;
use App\Models\OutboundFax;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\LaravelData\Optional;

class CdrDataService
{
    private const RELATED_CALL_WINDOW_PADDING_SECONDS = 3600;

    public function getData($params = [])
    {
        $currentDomain = $params['domain_uuid'];

        // Check if user is allowed to see all CDRs for tenant
        $user = auth()->user();
        if ($user && userCheckPermission("xml_cdr_view_self_records") && !userCheckPermission("xml_cdr_view_all_records")) {
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
                    $this->applyStatusFilter($query, $value['value']);
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
            ->where(fn ($query) => $this->filterCallLegs($query))
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


    protected function filterCallLegs($query): void
    {
        $query->where(function ($ordinary) {
            $ordinary->whereNull('cc_member_session_uuid')->whereNull('originating_leg_uuid');
        });
        // Callback phones retain their native queue-member and routing parents.
        // Include both endpoint roles without exposing ordinary secondary legs.
        if (Schema::hasColumn('v_xml_cdr', 'cc_callback_attempt_uuid')) {
            $query->orWhereNotNull('cc_callback_attempt_uuid');
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

    public function getExtensionStatistics(array $params = []): LengthAwarePaginator
    {
        $all = $this->getExtensionStatisticsCollection($params);

        $perPage = (int) ($params['per_page'] ?? 50);
        if (! in_array($perPage, fspbx_pagination_options(), true)) {
            $perPage = 50;
        }

        $currentPage = max(1, (int) ($params['page'] ?? 1));
        $total       = $all->count();
        $pageItems   = $all->forPage($currentPage, $perPage)->values();

        return new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function getExtensionStatisticsCollection(array $params = []): Collection
    {
        $params['filter'] = $params['filter'] ?? [];
        $params['filter']['showGlobal'] = false;

        return $this->buildExtensionStatisticsCollection($params);
    }

    public function getApiExtensionStatistics(array $params = []): array
    {
        $all = $this->getExtensionStatisticsCollection($params);

        $limit = (int) ($params['limit'] ?? 50);
        $limit = max(1, min(100, $limit));

        $startingAfter = (string) ($params['starting_after'] ?? '');
        if ($startingAfter !== '') {
            $position = $all->search(fn($row) => ($row['extension_uuid'] ?? null) === $startingAfter);
            $all = $position === false ? collect() : $all->slice($position + 1)->values();
        }

        $hasMore = $all->count() > $limit;

        return [
            'data' => $all->take($limit)->values(),
            'has_more' => $hasMore,
        ];
    }

    protected function buildExtensionStatisticsCollection(array $params = []): Collection
    {
        $domain_uuid = $params['domain_uuid'] ?? session('domain_uuid');
        $extensionUuid = $params['filter']['extension_uuid'] ?? null;

        $search = trim((string) ($params['filter']['search'] ?? ''));

        $user = auth()->user();
        $selfExtensionUuid = null;
        if (
            $user
            && userCheckPermission("xml_cdr_view_self_records")
            && !userCheckPermission("xml_cdr_view_all_records")
        ) {
            $selfExtensionUuid = $user->extension_uuid;
        }

        // 1) Load all extensions in this domain (only what we need)
        $extensions = Extensions::query()
            ->where('domain_uuid', $domain_uuid)
            ->when($selfExtensionUuid, function ($q) use ($selfExtensionUuid) {
                $q->where('extension_uuid', $selfExtensionUuid);
            })
            ->when(!$selfExtensionUuid && $extensionUuid, function ($q) use ($extensionUuid) {
                $q->where('extension_uuid', $extensionUuid);
            })
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

        return collect($stats)
            ->sortBy('extension', SORT_NATURAL) // SORT_NATURAL keeps 1, 2, 10 in the right order
            ->values();
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
                'record_path',
                'record_name',
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
            ])
            ->with('archive_recording:xml_cdr_uuid,object_key');
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
                recording_uuid: $this->apiRecordingUuid($cdr),

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

        if (in_array($status, ['missed call', 'abandoned', 'voicemail'], true)) {
            $query->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'callback_requested'));
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
                'record_path',
                'record_name',
                'direction',
                'caller_id_name',
                'caller_id_number',
                'caller_destination',
                'destination_number',
                'start_epoch',
                'answer_epoch',
                'end_epoch',
                'duration',
                'missed_call',
                'voicemail_message',
                'hangup_cause',
                'hangup_cause_q850',
                'cc_cancel_reason',
                'cc_cause',
                'sip_hangup_disposition',
                'status',
                ...$this->timelineSelectColumns(),
            ])
            ->with('archive_recording:xml_cdr_uuid,object_key')
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
            recording_uuid: $this->apiRecordingUuid($cdr),

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

    public function apiRecordingUuid(CDR $cdr): ?string
    {
        return $this->cdrHasRecording($cdr) ? (string) $cdr->xml_cdr_uuid : null;
    }

    public function cdrHasRecording(CDR $cdr): bool
    {
        $recordPath = trim((string) $cdr->record_path);
        $recordName = trim((string) $cdr->record_name);

        if ($recordPath === 'S3') {
            return $recordName !== ''
                || ($cdr->archive_recording && !empty($cdr->archive_recording->object_key));
        }

        if ($recordPath === '' || $recordName === '') {
            return false;
        }

        return is_file(rtrim($recordPath, '/') . '/' . $recordName);
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
                    xml_cdr_uuid: $row['xml_cdr_uuid'] ?? new Optional(),
                    cc_callback_attempt_uuid: $row['cc_callback_attempt_uuid'] ?? new Optional(),
                    cc_callback_role: $row['cc_callback_role'] ?? new Optional(),
                    status: $row['status'] ?? new Optional(),
                    billsec: $row['billsec'] ?? new Optional(),
                    waitsec: $row['waitsec'] ?? new Optional(),
                    queue_result: $row['queue_result'] ?? new Optional(),
                );
            })
            ->all();
    }

    public function buildCallFlowSummary(CDR $cdr)
    {
        if ($this->isCallbackCdr($cdr)) {
            return $this->buildCallbackCallFlowSummary($cdr);
        }

        $mainProfiles = $this->normalizeMainCallFlowData($cdr, $this->decodeCallFlow($cdr));
        $profiles = $this->attributeCallFlow($mainProfiles, $cdr);
        $start = (int) $cdr->start_epoch;
        $end = max($start, (int) $cdr->end_epoch);

        // A channel may have both parent references. A single OR query returns
        // it once, without collapsing distinct transfers/profiles on that channel.
        $relatedCalls = CDR::query()
            ->where('domain_uuid', $cdr->domain_uuid)
            ->where('xml_cdr_uuid', '!=', $cdr->xml_cdr_uuid)
            ->where(function ($query) use ($cdr) {
                $query->where('originating_leg_uuid', $cdr->xml_cdr_uuid);
                if (! empty($cdr->call_center_queue_uuid)) {
                    $query->orWhere('cc_member_session_uuid', $cdr->xml_cdr_uuid);
                }
            })
            ->when($start > 0, fn ($query) => $query->whereBetween('start_epoch', [
                max(0, $start - self::RELATED_CALL_WINDOW_PADDING_SECONDS),
                $end + self::RELATED_CALL_WINDOW_PADDING_SECONDS,
            ]))
            ->select(['xml_cdr_uuid', 'call_flow', 'direction', 'hangup_cause',
                'sip_hangup_disposition', 'call_center_queue_uuid', 'cc_cause', 'cc_cancel_reason', 'status'])
            ->orderBy('start_epoch')->orderBy('xml_cdr_uuid')
            ->get();

        foreach ($relatedCalls as $relatedCall) {
            foreach ($this->attributeCallFlow($this->decodeCallFlow($relatedCall), $relatedCall, true) as $profile) {
                $profiles->push($profile);
            }
        }

        $summary = $this->handleCallFlowSteps($profiles)
            ->map(fn ($row) => $this->buildSummaryItem($row))
            ->sortBy(fn ($row) => $row['profile_created_time'] ?: PHP_INT_MAX)
            ->values()
            ->map(function ($row) use ($start) {
                $offset = $row['profile_created_time'] - $start;
                $row['time_line'] = $start > 0 && $row['profile_created_time'] > 0 && $offset >= 0
                    ? sprintf('%02d:%02d', intdiv($offset, 60), $offset % 60)
                    : '--:--';
                return $row;
            });

        return $this->enrichCallFlowSummary($this->formatTimes($summary), (string) $cdr->domain_uuid);
    }

    private function decodeCallFlow(CDR $cdr): Collection
    {
        $profiles = json_decode((string) $cdr->call_flow, true);
        return collect(is_array($profiles) ? $profiles : [])
            ->filter(fn ($profile) => is_array($profile) && is_array($profile['caller_profile'] ?? null))
            ->map(function ($profile) {
                // XML-to-JSON conversion stores empty elements as [], not ''.
                foreach (['destination_number', 'callee_id_number', 'context', 'transfer_source', 'source', 'uuid', 'chan_name'] as $key) {
                    $value = $profile['caller_profile'][$key] ?? '';
                    $profile['caller_profile'][$key] = is_scalar($value) ? (string) $value : '';
                }
                return $profile;
            })
            ->values();
    }

    private function attributeCallFlow(Collection $profiles, CDR $cdr, bool $related = false): Collection
    {
        return $profiles->map(function ($profile) use ($cdr, $related) {
            $profile['times'] = array_replace(array_fill_keys([
                'bridged_time', 'created_time', 'answered_time', 'progress_time', 'transfer_time',
                'profile_created_time', 'profile_end_time', 'progress_media_time', 'hangup_time',
            ], 0), is_array($profile['times'] ?? null) ? $profile['times'] : []);
            $profile['_cdr_uuid'] = $cdr->xml_cdr_uuid;
            $profile['_direction'] = $cdr->direction;
            $profile['_queue_uuid'] = $cdr->call_center_queue_uuid;
            $profile['_queue_result'] = $cdr->cc_result;
            if ($related) {
                $profile['times']['call_disposition'] = $cdr->call_disposition;
            }
            return $profile;
        });
    }

    protected function enrichCallFlowSummary(Collection $rows, string $domainUuid): Collection
    {
        return (new CdrTimelineEnricher())->enrich($rows, $domainUuid);
    }

    public function timelineSelectColumns(): array
    {
        $identity = $this->callbackIdentityColumns();
        // Callback details use native channel rows, so do not transfer their
        // unused raw profile JSON from PostgreSQL to PHP.
        $flow = $identity ? DB::raw(
            "CASE WHEN cc_callback_attempt_uuid IS NOT NULL AND cc_callback_role IN ('agent', 'customer') "
            ."THEN NULL ELSE call_flow END AS call_flow"
        ) : 'call_flow';

        return [...$identity, $flow];
    }

    public function callbackIdentityColumns(): array
    {
        $columns = ['cc_callback_attempt_uuid', 'cc_callback_role'];

        return Schema::hasColumns('v_xml_cdr', $columns) ? $columns : [];
    }

    public function isCallbackCdr(CDR $cdr): bool
    {
        return Str::isUuid((string) $cdr->cc_callback_attempt_uuid)
            && in_array($cdr->cc_callback_role, ['agent', 'customer'], true);
    }

    protected function buildCallbackCallFlowSummary(CDR $cdr): Collection
    {
        // One indexed lookup for this attempt, including destination names.
        // No optional-module tables or raw XML/profile parsing are required.
        $calls = CDR::query()
            ->where('v_xml_cdr.domain_uuid', $cdr->domain_uuid)
            ->where('v_xml_cdr.cc_callback_attempt_uuid', $cdr->cc_callback_attempt_uuid)
            ->whereIn('v_xml_cdr.cc_callback_role', ['agent', 'customer'])
            ->where('v_xml_cdr.start_epoch', '>', 0)
            ->leftJoin('v_extensions as callback_extension', function ($join) {
                $join->on('callback_extension.domain_uuid', '=', 'v_xml_cdr.domain_uuid')
                    ->on('callback_extension.extension', '=', 'v_xml_cdr.destination_number');
            })
            ->select([
                'v_xml_cdr.xml_cdr_uuid', 'v_xml_cdr.cc_callback_attempt_uuid', 'v_xml_cdr.cc_callback_role',
                'v_xml_cdr.destination_number', 'v_xml_cdr.caller_destination', 'v_xml_cdr.start_epoch',
                'v_xml_cdr.answer_epoch', 'v_xml_cdr.end_epoch', 'v_xml_cdr.duration', 'v_xml_cdr.billsec',
                'v_xml_cdr.status', 'v_xml_cdr.direction', 'v_xml_cdr.hangup_cause', 'v_xml_cdr.sip_hangup_disposition',
                'callback_extension.effective_caller_id_name as destination_name',
            ])
            ->get()
            ->unique('xml_cdr_uuid')
            ->sortBy([['start_epoch', 'asc'], ['cc_callback_role', 'asc'], ['xml_cdr_uuid', 'asc']])
            ->values();

        $started = (int) $calls->min('start_epoch');
        $steps = $calls->map(function (CDR $call) use ($started) {
            $start = (int) $call->start_epoch;
            $answer = (int) $call->answer_epoch;
            $end = (int) $call->end_epoch;
            $duration = max(0, (int) $call->duration);
            $billsec = max(0, (int) $call->billsec);
            $waitsec = $answer > 0 ? max(0, $answer - $start) : $duration;
            $offset = max(0, $start - $started);
            $statusLabel = match ($call->status) {
                'answered' => __('Answered'),
                'busy' => __('Busy'),
                'no_answer' => __('No Answer'),
                'cancelled' => __('Cancelled'),
                'failed' => __('Failed'),
                'missed' => __('Missed Call'),
                default => __('Unknown'),
            };

            return [
                'xml_cdr_uuid' => $call->xml_cdr_uuid,
                'cc_callback_attempt_uuid' => $call->cc_callback_attempt_uuid,
                'cc_callback_role' => $call->cc_callback_role,
                'destination_number' => $call->destination_number ?: $call->caller_destination,
                'dialplan_app_type' => 'callback_'.$call->cc_callback_role,
                'dialplan_app' => $call->cc_callback_role === 'agent' ? __('Agent') : __('Customer'),
                'dialplan_name' => $call->destination_name,
                'status' => $call->status,
                'status_label' => $statusLabel,
                'call_disposition' => $call->call_disposition,
                'created_time' => $start,
                'profile_created_time' => $start,
                'answered_time' => $answer,
                'profile_end_time' => $end,
                'hangup_time' => $end,
                // These are overlapping phone-leg intervals, never a sum of
                // profile durations or evidence of callback confirmation.
                'duration_seconds' => $duration,
                'duration_formatted' => $this->getFormattedDuration($duration),
                'billsec' => $billsec,
                'billsec_formatted' => $this->getFormattedDuration($billsec),
                'waitsec' => $waitsec,
                'waitsec_formatted' => $this->getFormattedDuration($waitsec),
                'time_line' => sprintf('%02d:%02d', intdiv($offset, 60), $offset % 60),
            ];
        });

        return $this->formatTimes($steps);
    }

    private function normalizeMainCallFlowData(CDR $cdr, Collection $callFlowData): Collection
    {
        $hasXmlPlaceholder = $callFlowData->contains(function ($row) {
            $profile = $row['caller_profile'] ?? [];
            return ($profile['source'] ?? null) === 'mod_loopback'
                && strcasecmp(trim((string) ($profile['callee_id_number'] ?? '')), 'XML') === 0;
        });
        if ($hasXmlPlaceholder && $this->isBasicDialerCdr($cdr)) {
            $callFlowData = $callFlowData->map(function (array $profile) {
                $callerProfile = $profile['caller_profile'] ?? [];
                $callee = trim((string) ($callerProfile['callee_id_number'] ?? ''));
                $destination = trim((string) ($callerProfile['destination_number'] ?? ''));

                if (($callerProfile['source'] ?? null) === 'mod_loopback'
                    && strcasecmp($callee, 'XML') === 0
                    && $destination !== ''
                    && strcasecmp($destination, 'XML') !== 0) {
                    $profile['caller_profile']['callee_id_number'] = $destination;
                }

                return $profile;
            });

            return $callFlowData;
        }

        $hasDuplicateLoopback = false;
        $previous = null;
        foreach ($callFlowData as $profile) {
            if ($previous !== null && $this->sameFaxChannelProfile($previous, $profile)) {
                $hasDuplicateLoopback = true;
                break;
            }
            $previous = $profile;
        }
        if (! $hasDuplicateLoopback || ! $this->isOutboundFaxCdr($cdr)) {
            return $callFlowData;
        }

        return $callFlowData->reduce(function (Collection $profiles, array $profile) {
            $previous = $profiles->last();

            if (! is_array($previous) || ! $this->sameFaxChannelProfile($previous, $profile)) {
                return $profiles->push($profile);
            }

            if ($this->callFlowProfileDuration($profile) >= $this->callFlowProfileDuration($previous)) {
                $profiles->pop();
                $profiles->push($profile);
            }

            return $profiles;
        }, collect());
    }

    protected function isBasicDialerCdr(CDR $cdr): bool
    {
        return $cdr->direction === 'outbound'
            && BasicDialerCampaignAttempt::query()
                ->where('call_uuid', $cdr->xml_cdr_uuid)
                ->exists();
    }

    protected function isOutboundFaxCdr(CDR $cdr): bool
    {
        return $cdr->direction === 'outbound'
            && OutboundFax::query()->where('call_uuid', $cdr->xml_cdr_uuid)->exists();
    }

    private function sameFaxChannelProfile(array $left, array $right): bool
    {
        $leftProfile = $left['caller_profile'] ?? [];
        $rightProfile = $right['caller_profile'] ?? [];
        $leftUuid = (string) ($leftProfile['uuid'] ?? '');
        $rightUuid = (string) ($rightProfile['uuid'] ?? '');
        $leftChannel = (string) ($leftProfile['chan_name'] ?? '');
        $rightChannel = (string) ($rightProfile['chan_name'] ?? '');
        $leftDestination = preg_replace('/\D+/', '', (string) ($leftProfile['destination_number'] ?? ''));
        $rightDestination = preg_replace('/\D+/', '', (string) ($rightProfile['destination_number'] ?? ''));

        return ($leftProfile['source'] ?? null) === 'mod_loopback'
            && ($rightProfile['source'] ?? null) === 'mod_loopback'
            && $leftUuid !== ''
            && $leftUuid === $rightUuid
            && $leftChannel !== ''
            && $leftChannel === $rightChannel
            && $leftDestination !== ''
            && $leftDestination === $rightDestination;
    }

    private function callFlowProfileDuration(array $profile): int
    {
        $times = $profile['times'] ?? [];

        return max(
            0,
            (int) ($times['profile_end_time'] ?? 0) - (int) ($times['profile_created_time'] ?? 0)
        );
    }


    /**
     * Handle transfers in the call flow array
     *
     * @param Collection $callFlowData
     * @return Collection
     */
    protected function handleCallFlowSteps($callFlowData)
    {
        $steps = collect();
        foreach ($callFlowData as $row) {
            $application = CdrTimelineEnricher::savedApplication($row);
            if (($application['type'] ?? null) === 'ring_group') {
                $times = $row['times'];
                $bridge = (int) $times['bridged_time'];
                $start = (int) $times['profile_created_time'];
                $end = (int) $times['profile_end_time'];
                if ($start > 0 && $bridge > $start && $bridge <= $end) {
                    $waiting = $row;
                    $waiting['caller_profile']['callee_id_number'] = $row['caller_profile']['destination_number'] ?? '';
                    $waiting['times']['profile_end_time'] = $bridge;
                    $waiting['times']['bridged_time'] = 0;
                    $waiting['times']['answered_time'] = 0;
                    $waiting['times']['call_disposition'] = null;
                    $steps->push($waiting);
                    $row['times']['profile_created_time'] = $bridge;
                    $row['times']['progress_media_time'] = $bridge;
                    $row['_endpoint_after_ring_group'] = true;
                } elseif ($bridge === 0) {
                    $row['caller_profile']['callee_id_number'] = $row['caller_profile']['destination_number'] ?? '';
                }
            }
            $steps->push($row);
        }
        return $steps;
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
        $profile = $row['caller_profile'];
        $destination = (string) ($profile['destination_number'] ?? '');
        $callee = (string) ($profile['callee_id_number'] ?? '');
        $intercept = data_get($profile, 'originator.originator_caller_profile.destination_number');
        $interceptor = data_get($profile, 'originator.originator_caller_profile.caller_id_number');
        if (str_starts_with($destination, 'park') || (str_starts_with($destination, '*59') && strlen($destination) > 3)) {
            $number = str_contains((string) ($profile['transfer_source'] ?? ''), 'park+') ? $destination : ($callee ?: $destination);
        } elseif (is_string($intercept) && preg_match('/^\*97\d+$/', $intercept) && is_scalar($interceptor)) {
            $number = $intercept.'^'.$interceptor;
        } else {
            $number = $callee !== '' ? $callee : $destination;
        }

        $times = [];
        foreach (['bridged_time', 'created_time', 'answered_time', 'progress_time', 'transfer_time',
            'profile_created_time', 'profile_end_time', 'progress_media_time', 'hangup_time'] as $key) {
            $times[$key] = $this->formatTime($row['times'][$key] ?? 0);
        }
        $start = $times['profile_created_time'];
        $end = $times['profile_end_time'];
        $duration = $start > 0 && $end >= $start ? $end - $start : null;
        $formatted = $duration === null ? __('Unknown') : ($duration >= 60
            ? sprintf('%d min %02d s', intdiv($duration, 60), $duration % 60)
            : sprintf('%02d s', $duration));

        // A ring-group profile may be split into its wait and bridged endpoint.
        // Do not attribute the routing application to the endpoint phone step.
        $application = $number === $destination && empty($row['_endpoint_after_ring_group'])
            ? CdrTimelineEnricher::savedApplication($row) : null;

        return $times + [
            'destination_number' => $number,
            'context' => (string) ($profile['context'] ?? ''),
            'duration_seconds' => $duration,
            'duration_formatted' => $formatted,
            'call_disposition' => $row['times']['call_disposition'] ?? null,
            '_saved_type' => $application['type'] ?? null,
            '_saved_uuid' => $application['uuid'] ?? null,
            '_cdr_uuid' => $row['_cdr_uuid'] ?? null,
            '_direction' => $row['_direction'] ?? null,
            '_queue_uuid' => $row['_queue_uuid'] ?? null,
            '_queue_result' => $row['_queue_result'] ?? null,
        ];
    }

    private function formatTime($time)
    {
        return is_numeric($time) ? max(0, (int) round($time / 1000000)) : 0;
    }

    /**
     * Get app details associated with call flow step
     *
     */
    public function getAppDetails($row, string $domainUuid)
    {
        return $this->enrichCallFlowSummary(collect([$row]), $domainUuid)->first();
    }
}
