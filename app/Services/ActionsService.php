<?php

namespace App\Services;

use App\Models\Dialplans;
use App\Models\Extensions;
use App\Models\IvrMenus;
use App\Models\Recordings;
use App\Models\RingGroups;
use App\Models\Voicemails;
use Illuminate\Support\Facades\Session;

class ActionsService
{
    protected ?string $domain = null;

    protected array $categories = [
        //'call_centers' => 'Call Center',
        //'call_flows' => 'Call Flow',
        'dialplans' => 'Dial Plan',
        'extensions' => 'Extension',
        //'faxes' => 'Fax',
        //'ivr_menus' => 'IVR',
        //'recordings' => 'Recording',
        //'ring_groups' => 'Ring Group',
        //'time_conditions' => 'Time Condition',
        //'tones' => 'Tone',
        //'voicemails' => 'Voicemail',
        //'other' => 'Other'
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

    protected function extensionsOptions(): array
    {
        $options = [];
        $rows = Extensions::select('extension', 'effective_caller_id_name')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('extension')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s XML %s', $row->extension, Session::get('domain_name')),
                'name' => $row->extension." - ".$row->effective_caller_id_name
            ];
        }
        return $options;
    }

    protected function dialplansOptions(): array
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
                'value' => sprintf('%s XML %s', $row->dialplan_name, Session::get('domain_name')),
                'name' => $row->dialplan_name
            ];
        }
        return $options;
    }

    /*
    protected function getOptionsByCategory($key): array
    {
        $output = [];
        //$selectedCategory = null;
        //$selectedDestination = null;
        $rows = null;



        return $this->{ucfirst($key).'Options'}();





        switch ($key) {
            case 'call_centers':
            case 'call_flows':
            case 'fax':
            case 'time_conditions':
            case 'tones':
                $options = [];
                break;
            /*case 'dialplans':
                $options = Dialplans::select('dialplan_name')
                    ->where('domain_uuid', $domain_uuid)
                    ->where('dialplan_enabled', 'true')
                    ->where('dialplan_destination', 'true')
                    ->where('dialplan_number', '<>', '')
                    ->orderBy('dialplan_name')
                    ->get();
                break;
            case 'extensions':
                $options = Extensions::select('extension', 'effective_caller_id_name')
                    ->where('domain_uuid', $domain_uuid)
                    ->orderBy('extension')
                    ->get();
                break;
            /*case 'ivr_menus':
                $options = IvrMenus::where('domain_uuid', $domain_uuid)
                    ->orderBy('ivr_menu_extension')
                    ->get();
                break;
            case 'recordings':
                $options = Recordings::where('domain_uuid', $domain_uuid)
                    ->orderBy('recording_name')
                    ->get();
                break;
            case 'ring_groups':
                $options = RingGroups::where('domain_uuid', $domain_uuid)
                    ->where('ring_group_enabled', 'true')
                    ->orderBy('ring_group_extension')
                    ->get();
                break;
            case 'voicemails':
                $options = Voicemails::select('voicemail_id', 'voicemail_description')
                    ->where('domain_uuid', $domain_uuid)
                    ->where('voicemail_enabled', 'true')
                    ->orderBy('voicemail_id')
                    ->get();
                break;
            case 'other':
                $options = [
                    [
                        'id' => sprintf('*98 XML %s', $domain_name),
                        'label' => 'Check Voicemail'
                    ],
                    [
                        'id' => sprintf('*411 XML %s', $domain_name),
                        'label' => 'Company Directory'
                    ],
                    ['id' => 'hangup:', 'label' => 'Hangup'],
                    [
                        'id' => sprintf('*732 XML %s', $domain_name),
                        'label' => 'Record'
                    ]
                ];
                break;*/
        //}

       /* if ($rows) {
            foreach ($rows as $row) {
                switch ($category) {
                    case 'call_centers':
                    case 'call_flows':
                    case 'fax':
                    case 'time_conditions':
                    case 'tones':
                        $rows = [];
                        break;
                    case 'dialplans':
                        $rows = Dialplans::where('domain_uuid', $domain_uuid)
                            ->where('dialplan_enabled', 'true')
                            ->where('dialplan_destination', 'true')
                            ->where('dialplan_number', '<>', '')
                            ->orderBy('dialplan_name')
                            ->get();
                        break;
                    case 'extensions':
                        $id = sprintf('%s XML %s', $row->extension, Session::get('domain_name'));
                        $label = $row->extension." - ".$row->effective_caller_id_name;
                        $app_name = "Extension";
                        break;
                    case 'ivr_menus':
                        $id = sprintf('%s XML %s', $row->ivr_menu_extension, Session::get('domain_name'));
                        $label = $row->ivr_menu_extension." - ".$row->ivr_menu_name;
                        $app_name = "Auto Receptionist";
                        break;
                    case 'recordings':
                        $id = sprintf('streamfile.lua %s', $row->recording_filename);
                        $label = $row->recording_name;
                        $app_name = "Recordings";
                        break;
                    case 'ring_groups':
                        $id = sprintf('%s XML %s', $row->ring_group_extension, Session::get('domain_name'));
                        $label = $row->ring_group_extension." - ".$row->ring_group_name;
                        $app_name = "Ring Group";
                        break;
                    case 'voicemails':
                        $id = sprintf('*99%s XML %s', $row->voicemail_id, Session::get('domain_name'));
                        $label = $row->voicemail_id;
                        if ($row->extension) {
                            $label .= " - ".$row->extension->effective_caller_id_name;
                        } elseif ($row->voicemail_description != '') {
                            $label .= " - ".$row->voicemail_description;
                        }
                        $app_name = "Voicemail";
                        break;
                    case 'other':
                        $id = $row['id'];
                        $label = $row['label'];
                        $app_name = "Miscellaneous";
                        break;
                }


                if (isset($id)) {

                    // Add to the output array
                    $output[] = [
                        'id' => $id,
                        'label' => $label,
                        'app_name' => $app_name,
                    ];
                }
            }
        }*/

        //return $output;
    //}

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
