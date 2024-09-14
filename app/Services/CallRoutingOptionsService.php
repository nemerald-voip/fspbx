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
        ['value' => 'ivr_menus', 'name' => 'Auto Receptionist'],
        ['value' => 'time_conditions', 'name' => 'Schedule'],
        ['value' => 'contact_centers', 'name' => 'Contact Center'],
        ['value' => 'faxes', 'name' => 'Fax'],
        ['value' => 'call_flows', 'name' => 'Call Flow'],
        ['value' => 'recordings', 'name' => 'Play Greeting'],
        ['value' => 'other', 'name' => 'Other']
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
            case 'ivr_menus':
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
                    $name = $row->extension->name_formatted ;
                } else {
                    // Fallback to voicemail_id - "Team voicemail" if extension does not exist
                    $name =  $row->voicemail_id . " - Team voicemail";
                }
            }

            if ($model === Recordings::class) {
                    $name = $row->$nameField ;
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
}
