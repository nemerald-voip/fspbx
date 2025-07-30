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
                            ->orWhere('destination_number', 'ilike', "%{$value}%");

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
}
