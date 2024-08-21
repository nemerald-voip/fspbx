<?php

namespace App\Services;

use App\Models\CallCenterQueues;
use App\Models\CallFlows;
use App\Models\Dialplans;
use App\Models\Extensions;
use App\Models\Faxes;
use App\Models\IvrMenus;
use App\Models\Recordings;
use App\Models\RingGroups;
use App\Models\Voicemails;
use Illuminate\Support\Facades\Session;

class ActionsService
{
    protected ?string $domain = null;

    protected array $categories = [
        'call_centers' => 'Call Center',
        'call_flows' => 'Call Flow',
        'dial_plans' => 'Dial Plan',
        'extensions' => 'Extension',
        'faxes' => 'Fax',
        'ivr_menus' => 'IVR',
        'recordings' => 'Recording',
        'ring_groups' => 'Ring Group',
        //'time_conditions' => 'Time Condition',
        //'tones' => 'Tone',
        'voicemails' => 'Voicemail',
        'other' => 'Other'
    ];

    public function __construct($domain = null)
    {
        if ($domain !== null) {
            logger("ActionService does not support $domain argument yet. ".__FILE__);
        }
    }

    public function getData(): array
    {
        $output = [];
        foreach ($this->categories as $key => $label) {
            $output[$key] = [
                'name' => $label,
                'options' => $this->{ucfirst($key).'Options'}()
            ];
        }

        return $output;
    }

    protected function call_centersOptions(): array
    {
        $options = [];
        $rows = CallCenterQueues::select('queue_extension', 'queue_name')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('queue_extension')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s:%s XML %s', 'transfer', $row->queue_extension, Session::get('domain_name')),
                'name' => $row->queue_extension." - ".$row->queue_name
            ];
        }
        return $options;
    }

    protected function call_flowsOptions(): array
    {
        $options = [];
        $rows = CallFlows::select('call_flow_extension', 'call_flow_name')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('call_flow_extension')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s:%s XML %s', 'transfer', $row->call_flow_extension, Session::get('domain_name')),
                'name' => $row->call_flow_extension." - ".$row->call_flow_name
            ];
        }
        return $options;
    }

    protected function faxesOptions(): array
    {
        $options = [];
        $rows = Faxes::select('fax_extension', 'fax_name')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('fax_extension')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s:%s XML %s', 'transfer', $row->fax_extension, Session::get('domain_name')),
                'name' => $row->fax_extension." - ".$row->fax_name
            ];
        }
        return $options;
    }

    protected function extensionsOptions(): array
    {
        $options = [];
        $rows = Extensions::select('extension', 'effective_caller_id_name')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('extension')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s:%s XML %s', 'transfer', $row->extension, Session::get('domain_name')),
                'name' => $row->extension." - ".$row->effective_caller_id_name
            ];
        }
        return $options;
    }

    protected function dial_plansOptions(): array
    {
        $options = [];
        $rows = Dialplans::select('dialplan_name')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->where('dialplan_enabled', 'true')
            ->where('dialplan_destination', 'true')
            ->where('dialplan_number', '<>', '')
            ->orderBy('dialplan_name')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s:%s XML %s', 'transfer', $row->dialplan_name, Session::get('domain_name')),
                'name' => $row->dialplan_name
            ];
        }
        return $options;
    }

    protected function ivr_menusOptions(): array
    {
        $options = [];
        $rows = IvrMenus::select('ivr_menu_extension', 'ivr_menu_name')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('ivr_menu_extension')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s:%s XML %s', 'transfer', $row->ivr_menu_extension, Session::get('domain_name')),
                'name' => $row->ivr_menu_extension." - ".$row->ivr_menu_name
            ];
        }
        return $options;
    }

    protected function recordingsOptions(): array
    {
        $options = [];
        $rows = Recordings::select('recording_filename', 'recording_name')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('recording_name')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s:streamfile.lua %s', 'lua', $row->recording_filename),
                'name' => $row->recording_name
            ];
        }
        return $options;
    }

    protected function ring_groupsOptions(): array
    {
        $options = [];
        $rows = RingGroups::select('ring_group_extension', 'ring_group_name')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('ring_group_extension')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s:%s XML %s', 'transfer', $row->ring_group_extension, Session::get('domain_name')),
                'name' => $row->ring_group_extension." - ".$row->ring_group_name
            ];
        }
        return $options;
    }

    protected function time_conditionsOptions(): array
    {
        $options = [];
        /*$rows = RingGroups::select('ring_group_extension', 'ring_group_name')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('ring_group_extension')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s XML %s', $row->ring_group_extension, Session::get('domain_name')),
                'name' => $row->ring_group_extension." - ".$row->ring_group_name
            ];
        }*/
        return $options;
    }

    protected function tonesOptions(): array
    {
        // [{"destination_app":"playback","destination_data":"tone_stream:\/\/v"},
        //{"destination_app":"playback","destination_data":"tone_stream:\/\/%(500,500,480,620)"},
        //{"destination_app":"playback","destination_data":"tone_stream:\/\/v"},
        //{"destination_app":"playback","destination_data":"tone_stream:\/\/%(330,15,950);%(330,15,1400);%(330,1000,1800)"},
        //{"destination_app":"playback","destination_data":"tone_stream:\/\/%(274,0,913.8);%(274,0,1370.6);%(380,0,1776.7)"}]
        $options = [];
        // TODO
        /*$rows = RingGroups::select('ring_group_extension', 'ring_group_name')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('ring_group_extension')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s XML %s', $row->ring_group_extension, Session::get('domain_name')),
                'name' => $row->ring_group_extension." - ".$row->ring_group_name
            ];
        }*/
        return $options;
    }

    protected function voicemailsOptions(): array
    {
        $options = [];
        $rows = Voicemails::select('voicemail_id', 'voicemail_description')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('voicemail_id')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s:%s%s XML %s', 'transfer', '*99', $row->voicemail_id, Session::get('domain_name')),
                'name' => $row->voicemail_id." - ".$row->voicemail_description
            ];
        }
        return $options;
    }

    protected function otherOptions(): array
    {
        return [
            [
                'value' => sprintf('%s:%s XML %s', 'transfer', '*98', Session::get('domain_name')),
                'name' => 'Check Voicemail'
            ],
            [
                'value' => sprintf('%s:%s XML %s', 'transfer', '*411', Session::get('domain_name')),
                'name' => 'Company Directory'
            ],
            [
                'value' => 'hangup:',
                'name' => 'Hangup'
            ],
            [
                'value' => sprintf('%s:%s XML %s', 'transfer', '*732', Session::get('domain_name')),
                'name' => 'Record'
            ]
        ];
    }

    protected function getTimeoutDestinationsLabels(array $actions, $domain = null): array
    {
        $destinations = $this->getTimeoutDestinations($domain);
        $output = [];
        foreach ($actions as $action) {
            foreach ($destinations["targets"] as $category => $values) {
                foreach ($values as $data) {
                    if ($data["value"] == $action['destination_data']) {
                        $output[] = $destinations["categories"][(string) $category]["name"].' '.$data["name"];
                    }
                }
            }
        }
        return $output;
    }
}
