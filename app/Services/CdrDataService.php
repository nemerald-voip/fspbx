<?php

namespace App\Services;

use App\Models\CDR;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class CdrDataService
{

    public function getData($params = [])
    {
        $currentDomain = $params['domain_uuid'];

        // Check if user is allowed to see all CDRs for tenant
        $user = auth()->user();
        if ($user && !userCheckPermission("xml_cdr_domain")) {
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
            ->allowedSorts(['start_epoch', 'caller_id_number', 'destination_number']) // add more if needed
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
}
