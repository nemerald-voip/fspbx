<?php

namespace App\Services;

use App\Models\{
    CallCenterQueues,
    CallFlows,
    Dialplans,
    Extensions,
    Faxes,
    IvrMenus,
    Recordings,
    RingGroups,
    Voicemails
};

class CallRoutingOptionsService
{
    protected ?string $domainUuid;
    protected ?string $domainName;

    public array $routingTypes = [
        ['value' => 'extensions', 'name' => 'Extension'],
        ['value' => 'voicemails', 'name' => 'Voicemail'],
        ['value' => 'ring_groups', 'name' => 'Ring Group'],
        ['value' => 'ivrs', 'name' => 'Auto Receptionist'],
        ['value' => 'time_conditions', 'name' => 'Schedule'],
        ['value' => 'contact_centers', 'name' => 'Contact Center'],
        ['value' => 'faxes', 'name' => 'Fax'],
        ['value' => 'call_flows', 'name' => 'Call Flow'],
        ['value' => 'recordings', 'name' => 'Play Greeting'],
        // ['value' => 'other', 'name' => 'Other']
    ];

    private const TRANSFER_FORMAT = '%s:%s XML %s';

    public function __construct()
    {
        $this->domainUuid = session('domain_uuid');
        $this->domainName = session('domain_name');
    }

    // public function getData(): array
    // {
    //     $output = [];
    //     foreach ($this->categories as $key => $label) {
    //         $output[$key] = [
    //             'name' => $label,
    //             'options' => $this->getOptions($key)
    //         ];
    //     }


    //     return $output;
    // }

    // public function findLabel(array $actions): array
    // {
    //     $data = $this->getData();
    //     $output = [];
    //     foreach ($actions as $action) {
    //         foreach ($data as $values) {
    //             foreach ($values['options'] as $value) {
    //                 if (str_contains($value["value"], $action['destination_app'].':'.$action['destination_data'])) {
    //                     $output[] = $values['name'].' '.$value["name"];
    //                 }
    //             }
    //         }
    //     }
    //     return $output;
    // }

    public function getOptions(): array
    {
        switch (request('category')) {
            case 'contact_centers':
                return $this->buildOptions(CallCenterQueues::class, 'queue_extension', 'queue_name');
            case 'call_flows':
                return $this->buildOptions(CallFlows::class, 'call_flow_extension', 'call_flow_name');
                // case 'dial_plans':
                //     return $this->buildOptions(Dialplans::class, 'dialplan_name', '', true);
            case 'extensions':
                return $this->buildOptions(Extensions::class, 'extension', 'effective_caller_id_name');
            case 'faxes':
                return $this->buildOptions(Faxes::class, 'fax_extension', 'fax_name');
            case 'ivrs':
                return $this->buildOptions(IvrMenus::class, 'ivr_menu_extension', 'ivr_menu_name');
            case 'recordings':
                return $this->buildOptions(Recordings::class, 'recording_filename', 'recording_name');
            case 'ring_groups':
                return $this->buildOptions(RingGroups::class, 'ring_group_extension', 'ring_group_name');
            case 'time_conditions':
                return $this->buildOptions(Dialplans::class, 'dialplan_number', 'dialplan_name');
            case 'voicemails':
                return $this->buildOptions(Voicemails::class, 'voicemail_id', 'voicemail_description');
            case 'other':
                return $this->otherOptions();
            default:
                return [];
        }

        throw new \Exception('Failed to fetch routing options.');
    }

    protected function buildOptions($model, string $extensionField, string $nameField = ''): array
    {
        // Create an instance of the model
        $modelInstance = new $model;

        $query = $model::query(); // Start with a base query

        // Apply specific conditions only for Dialplans
        if ($model === Dialplans::class) {
            $query->where('dialplan_enabled', 'true')
                ->where('dialplan_number', '<>', '')
                ->where('dialplan_xml', '~*', '(year|yday|mon|mday|week|mweek|wday|hour|minute|minute-of-day|time-of-day|date-time)=[^>]*');
        }

        // Check if the model is Voicemails and eager load extensions
        if ($model === Voicemails::class) {
            $domainUuid = $this->domainUuid;
            $query->with(['extension' => function ($query) use ($domainUuid) {
                $query->select('extension_uuid', 'extension', 'effective_caller_id_name')
                    ->where('domain_uuid', $domainUuid);
            }]);
        }

        $query->select($modelInstance->getKeyName(), $extensionField, $nameField)->where('domain_uuid', $this->domainUuid);

        $rows = $query->orderBy($extensionField)->get();

        // logger($rows);

        $options = [];
        foreach ($rows as $row) {

            $name = $row->$extensionField . ($nameField ? " - " . $row->$nameField : '');
            if ($model === Voicemails::class) {
                if ($row->extension) {
                    // Use extension's name_formatted if extension exists
                    $name = $row->extension->name_formatted;
                } else {
                    // Fallback to voicemail_id - "Team voicemail" if extension does not exist
                    $name =  $row->voicemail_id . " - Team voicemail";
                }
            }

            if ($model === Recordings::class) {
                $name = $row->$nameField;
            }

            $options[] = [
                'value' => $row->{$modelInstance->getKeyName()},
                'extension' => $row->$extensionField,
                'name' => $name,
            ];
        }
        // logger($options);
        return $options;
    }

    protected function otherOptions(): array
    {
        return [
            [
                'value' => sprintf(self::TRANSFER_FORMAT, 'transfer', '*98', $this->domainName),
                'name' => 'Check Voicemail'
            ],
            [
                'value' => sprintf(self::TRANSFER_FORMAT, 'transfer', '*411', $this->domainName),
                'name' => 'Company Directory'
            ],
            [
                'value' => 'hangup:',
                'name' => 'Hangup'
            ],
            [
                'value' => sprintf(self::TRANSFER_FORMAT, 'transfer', '*732', $this->domainName),
                'name' => 'Record'
            ]
        ];
    }


    /**
     * Reverse engineer the destination actions.
     *
     * @param string $destinationActions JSON encoded destination actions.
     * @return array Reverse-engineered routing options.
     */
    public function reverseEngineerDestinationActions($destinationActions)
    {
        try {
            // Decode the JSON into an array
            $actions = json_decode($destinationActions, true);

            $routing_options = [];

            if ($actions) {
                foreach ($actions as $action) {
                    switch ($action['destination_app']) {
                        case 'transfer':
                            // Use regex and the Dialplan database to determine the type and details
                            $routing_options[] = $this->reverseEngineerTransferAction($action['destination_data']);
                            break;

                        case 'lua':
                            // Handle recordings
                            $routing_options[] =  $this->extractRecordingUuidFromData($action['destination_data']);
                            break;

                            // Add more cases as necessary
                    }
                }
            }

            return $routing_options;
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return null;
        }
    }

    /**
     * Reverse engineer IVR options based on the provided parameter.
     *
     * @param string $ivrAction A string containing the action details (e.g., "transfer 201 XML api.us.nemerald.net").
     * @return array Reverse-engineered IVR option details.
     */
    public function reverseEngineerIVROption($ivrAction)
    {
        try {
            // Split the string by spaces to extract details
            $parts = explode(' ', $ivrAction);

            if (count($parts) < 3) {
                throw new \InvalidArgumentException("Invalid IVR action format");
            }

            // Extract relevant data
            $actionType = $parts[0]; // e.g., "transfer"
            $destination = $parts[1]; // e.g., "201"
            $context = $parts[2]; // e.g., "XML"
            $domain = $parts[3] ?? null; // e.g., "api.us.nemerald.net"

            // Reverse engineer based on the action type
            switch ($actionType) {
                case 'transfer':
                    return $this->reverseEngineerTransferAction("$destination $context $domain");

                    // Add more cases for other IVR actions as needed

                default:
                    throw new \InvalidArgumentException("Unsupported IVR action type: $actionType");
            }
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return null;
        }
    }

    /**
     * Reverse engineer a 'transfer' action based on destination_data.
     */
    protected function reverseEngineerTransferAction($destinationData)
    {
        // Extract the extension/identifier from destination_data
        $extension = explode(' ', $destinationData)[0]; // Extracts '0600' from '0600 XML tenant.domain.net'

        // Use regex and check in the Dialplan database to determine what this extension belongs to
        $dialplan = Dialplans::where(function ($query) use ($extension) {
            $query->where('dialplan_number', $extension)
                ->orWhere('dialplan_number', '=', '1' . $extension);
        })
            ->where('dialplan_enabled', 'true')
            ->where('domain_uuid', session('domain_uuid'))
            ->select('dialplan_uuid', 'dialplan_name', 'dialplan_number', 'dialplan_xml', 'dialplan_order')
            ->first();

        // If a Dialplan match is found, reverse-engineer it based on the XML and determine the type
        if ($dialplan) {
            return $this->mapDialplanToRoutingOption($dialplan, $extension);
        }

        // Check if destination is voicemail
        if ((substr($extension, 0, 3) == '*99') !== false) {
            $voicemail = Voicemails::where('domain_uuid', session('domain_uuid'))
                ->where('voicemail_id', substr($extension, 3))
                ->with(['extension' => function ($query) {
                    $query->select('extension_uuid', 'extension', 'effective_caller_id_name')
                        ->where('domain_uuid', session('domain_uuid'));
                }])
                ->first();

            if (!$voicemail) return null;
            return [
                'type' => 'voicemails',
                'extension' => $voicemail->voicemail_id,
                'option' => $voicemail->voicemail_uuid,
                'name' => $voicemail->extension->name_formatted,
            ];
        }

        // Fallback: assume it's an extension if no Dialplan match
        $ext = Extensions::where('domain_uuid', session('domain_uuid'))
            ->where('extension', $extension)
            ->first();
        if (!$ext) {
            return [
                'type' => null,
                'extension' => null,
                'option' => null,
                'name' => null
            ];
        } else {
            return [
                'type' => 'extensions',
                'extension' => $ext->extension,
                'option' => $ext->extension_uuid,
                'name' => $ext->name_formatted,
            ];
        }
    }

    /**
     * Map Dialplan data back to a routing option.
     */
    protected function mapDialplanToRoutingOption($dialplan, $extension)
    {
        // Define regex patterns to determine what the dialplan matches
        $patterns = [
            'ring_groups' => '/ring_group_uuid=([0-9a-fA-F-]+)/',
            'ivrs' => '/ivr_menu_uuid=([0-9a-fA-F-]+)/',
            'contact_centers' => '/call_center_queue_uuid=([0-9a-fA-F-]+)/',
            'call_flows' => '/call_flow_uuid=([0-9a-fA-F-]+)/',
            'time_conditions' => '/\b(year|yday|mon|mday|week|mweek|wday|hour|minute|minute-of-day|time-of-day|date-time)=("[^"]+"|\'[^\']+\'|\S+)/',
            'faxes' => '/fax_uuid=([0-9a-fA-F-]+)/',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $dialplan->dialplan_xml, $matches)) {
                if ($type === 'time_conditions') {
                    // For time conditions, return the dialplan UUID as the option
                    return [
                        'type' => $type,
                        'extension' => $extension,
                        'option' => $dialplan->dialplan_uuid,
                        'name' => $dialplan->dialplan_name,
                    ];
                }

                // For non-time condition types
                return [
                    'type' => $type,
                    'extension' => $extension,
                    'option' => $matches[1],
                    'name' => $dialplan->dialplan_name,
                ];
            }
        }

        // Check if dialplan_order is 300 and assume it's time conditions
        if ($dialplan->dialplan_order == 300) {
            return [
                'type' => 'time_conditions',
                'extension' => $extension,
                'option' => $dialplan->dialplan_uuid,
                'name' => $dialplan->dialplan_name,
            ];
        }

        // If no specific type was matched and no dialplan_order of 300, return empty array
        return [];
    }

    /**
     * Extract recording UUID from lua destination data.
     */
    protected function extractRecordingUuidFromData($destinationData)
    {

        // Split the string by spaces
        $parts = explode(' ', $destinationData);

        // Get the second part, which is the file name
        if (isset($parts[1])) {
            $fileName = $parts[1]; // This will return the file name (e.g., recorded_0bbac5f48265cd0392946a0f2f79423c.wav)
        }

        $recording = Recordings::where('domain_uuid', session('domain_uuid'))
            ->where('recording_filename', $fileName)
            ->first();

        if ($recording) {
            return [
                'type' => 'recordings',
                'extension' => $fileName,
                'option' => $recording->recording_uuid,
            ];
        } else {
            return [];
        }
    }
}
