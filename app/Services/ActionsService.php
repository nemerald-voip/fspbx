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
        'time_conditions' => 'Time Condition',
        //'tones' => 'Tone',
        'voicemails' => 'Voicemail',
        'other' => 'Other'
    ];

    private static ?ActionsService $instance = null;

    /**
     * @param $domain
     */
    public function __construct($domain = null)
    {
        if ($domain !== null) {
            logger("ActionService does not support $domain argument yet. ".__FILE__);
        }
    }

    /**
     * @return void
     */
    private function __clone()
    {
        // Ensures the instance cannot be cloned
    }

    /**
     * @param $domain
     * @return ActionsService
     */
    public static function getInstance($domain = null): ActionsService
    {
        if (self::$instance === null) {
            self::$instance = new self($domain);
        }
        return self::$instance;
    }

    /**
     * @return array
     */
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

    /**
     * @param  array  $actions
     * @return array
     */
    public function findLabel(array $actions): array
    {
        $data = $this->getData();
        $output = [];
        foreach ($actions as $action) {
            foreach ($data as $values) {
                foreach ($values['options'] as $value) {
                    if (str_contains($value["value"], $action['destination_app'].':'.$action['destination_data'])) {
                        $output[] = $value["name"];
                    }
                }
            }
        }
        return $output;
    }

    /**
     * Retrieve options for call centers.
     *
     * This method retrieves call center options from the database based on the current session's domain UUID.
     * It selects the queue extension and name columns from the CallCenterQueues table.
     * The options are ordered by the queue extension in ascending order.
     * The retrieved rows are then transformed into an array format for the options.
     * Each option is an associative array with the following
     */
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

    /**
     * Retrieve options for call flows.
     *
     * This method retrieves call flows options from the database based on the current session's domain UUID.
     * It selects the call flow extension and name columns from the CallFlows table.
     * The options are ordered by the call flow extension in ascending order.
     * The retrieved rows are then transformed into an array format for the options.
     * Each option is an associative array with the following keys:
     *    - 'value': A formatted string that includes the "transfer" keyword, call flow extension, and domain name.
     *    - 'name': A concatenated string of the call flow extension and name.
     *
     * @return array The options for call flows in the specified format.
     */
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

    /**
     * @return array
     */
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

    /**
     * Retrieves an array of extension options.
     *
     * This method retrieves a list of extensions from the database for the current domain
     * and generates an array of options that can be used in a select input element.
     * Each option in the array has a "value" and "name" property.
     *
     * @return array The array of extension options.
     */
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

    /**
     * Retrieves the options for dial plans.
     *
     * This method queries the `Dialplans` table in the database to fetch
     * the names of the enabled dial plans that have a destination assigned
     * and a non-empty number. It then formats the retrieved data into an
     * array of options, where each option has a value and a name.
     *
     * The value of each option is formatted as "%s:%s XML %s", where the
     * first %s represents the action type ("transfer"), the second %s
     * represents the dial plan name, and the third %s represents the
     * domain name. The name of each option is set to the dial plan name.
     *
     * @return array The array of options for dial plans.
     */
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

    /**
     * Returns an array of options for IVR menus.
     *
     * The options are generated by querying the IvrMenus table and retrieving the IVR menu extension and name for each row.
     * The options array is then populated with values and names based on the retrieved data.
     *
     * @return array An array of options for IVR menus.
     */
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

    /**
     * Returns an array of options for recordings.
     *
     * This method retrieves recordings from the database that belong to the current domain and
     * creates an array of options with their values and names. The options array is sorted by
     * the recording name.
     *
     * @return array An array containing options for recordings. Each option is represented as an
     *               associative array with two keys: 'value' and 'name'. The 'value' key contains
     *               a string formatted as "lua:streamfile.lua <recording_filename>". The 'name'
     *               key contains the recording name.
     */
    protected function recordingsOptions(): array
    {
        $options = [];
        $rows = Recordings::select('recording_filename', 'recording_name')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('recording_name')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s:%s %s', 'lua', 'streamfile.lua', $row->recording_filename),
                'name' => $row->recording_name
            ];
        }
        return $options;
    }

    /**
     * Retrieves the options for ring groups.
     *
     * This method will query the database to fetch the ring group extension and name for each ring group
     * associated with the current domain. The options will be returned as an array of key-value pairs, where
     * the key is the ring group extension prefixed with the transfer XML command, and the value is the
     * ring group extension concatenated with the ring group name.
     *
     * @return array The options for ring groups.
     */
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

    /**
     * Retrieve the options for time conditions.
     *
     * This method queries the `Dialplans` table to fetch the `dialplan_name` and `dialplan_number`
     * columns for time conditions linked to the current domain. It then formats the retrieved data
     * and adds it to an array of options. Each option includes a `value` and `name` property.
     *
     * The `value` property is built using the `sprintf` function to concatenate the string values
     * "transfer", the `dialplan_number` value, "XML", and the current domain's name from the session data.
     *
     * The `name` property is a concatenation of the `dialplan_number` value and the `dialplan_name`.
     *
     * The resulting options array is then returned by the method.
     *
     * @return array The options for time conditions.
     */
    protected function time_conditionsOptions(): array
    {
        $options = [];
        $rows = Dialplans::select('dialplan_name', 'dialplan_number')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->where('app_uuid', '4b821450-926b-175a-af93-a03c441818b1')
            ->orderBy('dialplan_number')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s:%s XML %s', 'transfer', $row->dialplan_number, Session::get('domain_name')),
                'name' => $row->dialplan_number." - ".$row->dialplan_name
            ];
        }
        return $options;
    }

    /**
     * Returns an array of tones options.
     *
     * The options are represented as a JSON array of objects, where each object
     * represents a tone option. Each tone option object has two properties:
     * - "destination_app": represents the destination application for the tone.
     * - "destination_data": represents the destination data for the tone.
     *
     * The array of tones options is initially empty. The function currently
     * has a TODO comment and does not populate the array with any values.
     *
     * @return array An array of tones options.
     */
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

    /**
     * @return array
     */
    protected function voicemailsOptions(): array
    {
        $options = [];
        $rows = Voicemails::select('voicemail_id', 'voicemail_description')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('voicemail_id')
            ->get();
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf('%s:%s%s XML %s', 'transfer', '*99', $row->voicemail_id,
                    Session::get('domain_name')),
                'name' => $row->voicemail_id." - ".$row->voicemail_description
            ];
        }
        return $options;
    }

    /**
     * @return array
     */
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
}
