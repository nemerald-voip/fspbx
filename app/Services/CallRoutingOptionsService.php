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

    public function getData(): array
    {
        $output = [];
        foreach ($this->categories as $key => $label) {
            $output[$key] = [
                'name' => $label,
                'options' => $this->getOptions($key)
            ];
        }

        
        return $output;
    }

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
            case 'call_centers':
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
                return $this->buildOptions(Recordings::class, 'recording_filename', 'recording_name', false, 'lua:streamfile.lua');
            case 'ring_groups':
                return $this->buildOptions(RingGroups::class, 'ring_group_extension', 'ring_group_name');
            case 'time_conditions':
                return $this->buildOptions(Dialplans::class, 'dialplan_number', 'dialplan_name', true, '', '4b821450-926b-175a-af93-a03c441818b1');
            case 'voicemails':
                return $this->buildOptions(Voicemails::class, 'voicemail_id', 'voicemail_description', false, '*99');
            case 'other':
                return $this->otherOptions();
            default:
                return [];
        }

        throw new \Exception('Failed to fetch routing options.');

    }

    protected function buildOptions($model, string $extensionField, string $nameField = '', bool $enabled = false, string $prefix = 'transfer', string $appUuid = null): array
    {
        $query = $model::select($extensionField, $nameField)->where('domain_uuid', $this->domainUuid);

        if ($enabled) {
            $query->where('dialplan_enabled', 'true')
                ->where('dialplan_destination', 'true')
                ->where('dialplan_number', '<>', '');
        }

        if ($appUuid) {
            $query->where('app_uuid', $appUuid);
        }

        $rows = $query->orderBy($extensionField)->get();

        $options = [];
        foreach ($rows as $row) {
            $options[] = [
                'value' => sprintf(self::TRANSFER_FORMAT, $prefix, $row->$extensionField, $this->domainName),
                'name' => $row->$extensionField . ($nameField ? " - ".$row->$nameField : '')
            ];
        }
        logger($options);
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
