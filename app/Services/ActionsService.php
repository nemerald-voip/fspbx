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
        'call_centers',
        'call_flows',
        'dialplans',
        'extensions',
        'fax',
        'ivr_menus',
        'recordings',
        'ring_groups',
        'time_conditions',
        'tones',
        'voicemails',
        'other'
    ];

    public function __construct($domain = null)
    {
        if ($domain !== null) {
            logger("getTimeoutDestinations does not support $domain argument yet. ".__FILE__);
        }
    }

    public function getData(): array
    {
        // TODO: refactor the getDestinationByCategory function to use $domain
        $output = [
            'categories' => [],
            'targets' => [],
        ];
        foreach ($this->categories as $i => $category) {
            $data = $this->getDestinationByCategory($category)['list'];
            foreach ($data as $b => $d) {
                $output['categories'][$category] = [
                    'name' => $d['app_name'],
                    'value' => $category
                ];

                //[$i] = ;
                //$output['categories'][$i] = ;
                $output['targets'][$category][] = [
                    'name' => $d['label'],
                    'value' => $d['id']
                ];
            }
        }

        return $output;
    }

    protected function getDestinationByCategory($category/*, $data = null*/): array
    {
        $output = [];
        //$selectedCategory = null;
        //$selectedDestination = null;
        $rows = null;

        $domain_uuid = Session::get('domain_uuid');
        $domain_name = Session::get('domain_name');

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
                $rows = Extensions::where('domain_uuid', $domain_uuid)
                    ->orderBy('extension')
                    ->get();
                break;
            case 'ivr_menus':
                $rows = IvrMenus::where('domain_uuid', $domain_uuid)
                    ->orderBy('ivr_menu_extension')
                    ->get();
                break;
            case 'recordings':
                $rows = Recordings::where('domain_uuid', $domain_uuid)
                    ->orderBy('recording_name')
                    ->get();
                break;
            case 'ring_groups':
                $rows = RingGroups::where('domain_uuid', $domain_uuid)
                    ->where('ring_group_enabled', 'true')
                    ->orderBy('ring_group_extension')
                    ->get();
                break;
            case 'voicemails':
                $rows = Voicemails::where('domain_uuid', $domain_uuid)
                    ->where('voicemail_enabled', 'true')
                    ->orderBy('voicemail_id')
                    ->get();
                break;
            case 'other':
                $rows = [
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
                break;
        }

        if ($rows) {
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
                    // Check if the id matches the data
                    /*if ($id == $data || 'transfer:'.$id == $data) {
                        $selectedCategory = $category;
                        $selectedDestination = $id;
                    }*/

                    // Add to the output array
                    $output[] = [
                        'id' => $id,
                        'label' => $label,
                        'app_name' => $app_name,
                    ];
                }
            }
        }

        return [
            //'selectedCategory' => $selectedCategory,
            //'selectedDestination' => $selectedDestination,
            'list' => $output
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
