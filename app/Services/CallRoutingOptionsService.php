<?php

namespace App\Services;

use App\Support\BridgeRuntimeDestination;
use App\Models\{AiAgent, Bridge, BusinessHour, CallCenterQueues, CallFlows, Conferences, Dialplans, Domain, DynamicRoute, Extensions, Faxes, IvrMenus, Recordings, RingGroups, Voicemails};
use App\Models\ConferenceCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CallRoutingOptionsService
{
    protected ?string $domainUuid;
    protected ?string $domainName;

    public array $routingTypes;

    public array $forwardingTypes;

    /**
     * Shared by destination selectors, saved polymorphic targets, and dialplan generation.
     * Target-free actions deliberately have no model or destination field.
     * Model destinations use transfer unless a handler is specified. Add regression
     * cases in tests/Unit/RoutingDestinations.php when introducing a destination.
     */
    private static function destinations(bool $translate = false): array
    {
        return [
            'extensions' => ['label' => $translate ? __('Extension') : 'Extension', 'model' => Extensions::class, 'field' => 'extension', 'name' => 'effective_caller_id_name'],
            'voicemails' => ['label' => $translate ? __('Voicemail') : 'Voicemail', 'model' => Voicemails::class, 'field' => 'voicemail_id', 'name' => 'voicemail_description', 'handler' => 'voicemail'],
            'ring_groups' => ['label' => $translate ? __('Ring Group') : 'Ring Group', 'model' => RingGroups::class, 'field' => 'ring_group_extension', 'name' => 'ring_group_name'],
            'ivrs' => ['label' => $translate ? __('Virtual Receptionist') : 'Virtual Receptionist', 'model' => IvrMenus::class, 'field' => 'ivr_menu_extension', 'name' => 'ivr_menu_name'],
            'business_hours' => ['label' => $translate ? __('Business Hours') : 'Business Hours', 'model' => BusinessHour::class, 'field' => 'extension', 'name' => 'name'],
            'time_conditions' => ['label' => $translate ? __('Schedule') : 'Schedule', 'model' => Dialplans::class, 'field' => 'dialplan_number', 'name' => 'dialplan_name'],
            'contact_centers' => ['label' => $translate ? __('Contact Center') : 'Contact Center', 'model' => CallCenterQueues::class, 'field' => 'queue_extension', 'name' => 'queue_name'],
            'bridges' => ['label' => $translate ? __('Bridge') : 'Bridge', 'model' => Bridge::class, 'field' => 'bridge_uuid', 'name' => 'bridge_name', 'handler' => 'bridge'],
            'faxes' => ['label' => $translate ? __('Fax') : 'Fax', 'model' => Faxes::class, 'field' => 'fax_extension', 'name' => 'fax_name'],
            'call_flows' => ['label' => $translate ? __('Call Flow') : 'Call Flow', 'model' => CallFlows::class, 'field' => 'call_flow_extension', 'name' => 'call_flow_name'],
            'dynamic_routes' => ['label' => $translate ? __('Dynamic Route') : 'Dynamic Route', 'model' => DynamicRoute::class, 'field' => 'extension', 'name' => 'name'],
            'recordings' => ['label' => $translate ? __('Play Greeting') : 'Play Greeting', 'model' => Recordings::class, 'field' => 'recording_filename', 'name' => 'recording_name', 'handler' => 'recording'],
            'conferences' => ['label' => $translate ? __('Conferences') : 'Conferences', 'model' => Conferences::class, 'field' => 'conference_extension', 'name' => 'conference_name'],
            'conference_centers' => ['label' => $translate ? __('Conference Centers') : 'Conference Centers', 'model' => ConferenceCenter::class, 'field' => 'conference_center_extension', 'name' => 'conference_center_name'],
            'ai_agents' => ['label' => $translate ? __('AI Agent') : 'AI Agent', 'model' => AiAgent::class, 'field' => 'extension', 'name' => 'name'],
            'check_voicemail' => ['label' => $translate ? __('Check Voicemail') : 'Check Voicemail', 'handler' => 'check_voicemail'],
            'company_directory' => ['label' => $translate ? __('Company Directory') : 'Company Directory', 'handler' => 'company_directory'],
            'hangup' => ['label' => $translate ? __('Hang up') : 'Hang up', 'handler' => 'hangup'],
        ];
    }

    private const TRANSFER_FORMAT = '%s:%s XML %s';

    public function __construct(?string $domainUuid = null,)
    {
        $this->domainUuid = $domainUuid ?? session('domain_uuid');
        $this->domainName = session('domain_name');
        $this->routingTypes = [];
        foreach (self::destinations() as $type => $definition) {
            $this->routingTypes[] = ['value' => $type, 'name' => $definition['label']];
        }

        $this->forwardingTypes = [];
        $translatedDestinations = self::destinations(translate: true);
        foreach (array_diff(self::forwardingDestinationTypes(), ['external']) as $type) {
            $this->forwardingTypes[] = ['value' => $type, 'label' => $translatedDestinations[$type]['label']];
        }
        $this->forwardingTypes[] = ['value' => 'external', 'label' => __('External Number')];
    }


    public function getOptions(): array
    {
        $type = request('category');
        if ($type === 'other') {
            return $this->otherOptions();
        }

        $definition = self::destinations()[$type] ?? null;
        if (! isset($definition['model'])) {
            return [];
        }

        return $this->buildOptions($definition['model'], $definition['field'], $definition['name']);
    }

    protected function buildOptions($model, string $extensionField, string $nameField = ''): array
    {
        // Create an instance of the model
        $modelInstance = new $model;

        $query = $model::query(); // Start with a base query

        if ($model === AiAgent::class) {
            $query->where('enabled', true)->where('provisioning_status', 'synced');
        }
        if ($model === DynamicRoute::class) {
            $query->where('enabled', true);
        }
        if ($model === Bridge::class) {
            $query->where('bridge_enabled', 'true')
                ->whereNotNull('bridge_destination')->where('bridge_destination', '<>', '');
        }

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

        $fields = [$modelInstance->getKeyName(), $extensionField, $nameField];
        if ($model === Bridge::class) {
            $fields[] = 'bridge_destination';
        }
        $query->select(array_unique($fields))->where('domain_uuid', $this->domainUuid);

        $rows = $query->orderBy($model === Bridge::class ? $nameField : $extensionField)->get();

        // logger($rows);

        $options = [];
        foreach ($rows as $row) {

            $name = $row->$extensionField . ($nameField ? " - " . $row->$nameField : '');
            if ($model === Voicemails::class) {
                $name = $this->voicemailOptionName($row);
            }

            if ($model === Recordings::class) {
                $name = $row->$nameField;
            }

            if ($model === Bridge::class) {
                $name = $row->bridge_name ?: $row->bridge_destination;
            }

            $option = [
                'value' => $row->{$modelInstance->getKeyName()},
                'extension' => $row->$extensionField,
                'name' => $name,
            ];
            if ($model === Bridge::class) {
                $option['bridge_uuid'] = $row->bridge_uuid;
            }
            $options[] = $option;
        }
        // logger($options);
        return $options;
    }

    protected function otherOptions(): array
    {
        return [
            [
                'value' => sprintf(self::TRANSFER_FORMAT, 'transfer', '*98', $this->domainName),
                'name' => __('Check Voicemail')
            ],
            [
                'value' => sprintf(self::TRANSFER_FORMAT, 'transfer', '*411', $this->domainName),
                'name' => __('Company Directory')
            ],
            [
                'value' => 'hangup:',
                'name' => __('Hangup')
            ],
            [
                'value' => sprintf(self::TRANSFER_FORMAT, 'transfer', '*732', $this->domainName),
                'name' => __('Record')
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
                            $bridgeUuid = BridgeRuntimeDestination::uuidFromScriptData(
                                $action['destination_data'] ?? null
                            );
                            $routing_options[] = $bridgeUuid
                                ? $this->reverseEngineerBridgeAction(null, $bridgeUuid)
                                : $this->extractRecordingUuidFromData($action['destination_data']);
                            break;

                        case 'bridge':
                            $routing_options[] = $this->reverseEngineerBridgeAction(
                                $action['destination_data'] ?? null,
                                $action['bridge_uuid'] ?? null
                            );
                            break;

                        case 'hangup':
                            $routing_options[] =  array(
                                'type' => 'hangup',
                            );
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
        return $this->reverseEngineerApplicationAction($ivrAction, 'IVR');
    }

    /**
     * Reverse engineer Ring Group exit options based on the provided parameter.
     *
     * @param string $ivrAction A string containing the action details (e.g., "transfer 201 XML api.us.nemerald.net").
     * @return array Reverse-engineered IVR option details.
     */
    public function reverseEngineerRingGroupExitAction($action)
    {
        return $this->reverseEngineerApplicationAction($action, 'Ring Group');
    }

    /**
     * Reverse engineer Call Flow route options based on the provided parameter.
     *
     * @param string $action A string containing the action details (e.g., "transfer 201 XML api.us.nemerald.net").
     * @return array|null Reverse-engineered routing option details.
     */
    public function reverseEngineerCallFlowAction($action)
    {
        return $this->reverseEngineerApplicationAction($action, 'Call Flow');
    }

    protected function reverseEngineerApplicationAction($action, string $label)
    {
        try {
            if (!$action) {
                return null;
            }
            $action = trim($action);
            // Split the string by spaces to extract details
            $parts = explode(' ', $action);
            $actionType = $parts[0]; // e.g., "transfer"

            if ($actionType === 'bridge') {
                if (count($parts) < 2) {
                    throw new \Exception("Invalid {$label} bridge action format");
                }

                return $this->reverseEngineerBridgeAction(implode(' ', array_slice($parts, 1)));
            }

            if (count($parts) < 3 && $actionType != "hangup") {
                throw new \Exception("Invalid {$label} action format");
            }

            // Extract relevant data
            if ($actionType != 'hangup') {
                $destination = $parts[1]; // e.g., "201"
                $context = $parts[2]; // e.g., "XML"
                $domain_name = $parts[3] ?? null; // e.g., "api.us.domain.net"
            }

            // Reverse engineer based on the action type
            switch ($actionType) {
                case 'transfer':
                    return $this->reverseEngineerTransferAction("$destination $context $domain_name");
                    break;
                case 'lua':
                    $scriptData = implode(' ', array_slice($parts, 1));
                    $bridgeUuid = BridgeRuntimeDestination::uuidFromScriptData($scriptData);

                    return $bridgeUuid
                        ? $this->reverseEngineerBridgeAction(null, $bridgeUuid)
                        : $this->extractRecordingUuidFromData($scriptData);

                case 'hangup':
                    return array(
                        'type' => 'hangup',
                    );
                    break;

                // Add more cases for other actions as needed

                default:
                    throw new \Exception("Unsupported {$label} action type: $actionType");
            }
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return null;
        }
    }

    /**
     * Reverse engineer Forward options based on the provided parameter.
     *
     * @param string $destination A string containing the forwarding destination
     * @return array Reverse-engineered forward option details.
     */
    public function reverseEngineerForwardAction($destination)
    {
        try {

            if (!filled($destination)) {
                return [
                    'type' => null,
                    'extension' => null,
                    'option' => null,
                    'name' => null
                ];
            }

            $domainUuid = $this->domainUuid;

            $dialplan = Dialplans::where('dialplan_number', $destination)
                ->where('dialplan_enabled', 'true')
                ->where(function ($query) use ($domainUuid) {
                    $query->where('domain_uuid', $domainUuid)
                        ->orWhereNull('domain_uuid');
                })
                ->where('dialplan_context', '!=', 'public')
                ->select('dialplan_uuid', 'dialplan_name', 'dialplan_number', 'dialplan_xml', 'dialplan_order')
                ->first();

            // If a Dialplan match is found, reverse-engineer it based on the XML and determine the type
            if ($dialplan) {
                return $this->mapDialplanToRoutingOption($dialplan, $destination);
            }

            // Check if destination is voicemail
            if ((substr($destination, 0, 3) == '*99') !== false) {
                $voicemail = Voicemails::where('domain_uuid', $domainUuid)
                    ->where('voicemail_id', substr($destination, 3))
                    ->with(['extension' => function ($query) use ($domainUuid) {
                        $query->select('extension_uuid', 'extension', 'effective_caller_id_name')
                            ->where('domain_uuid', $domainUuid);
                    }])
                    ->first();


                if ($voicemail) {
                    return [
                        'type' => 'voicemails',
                        'extension' => $voicemail->voicemail_id,
                        'option' => $voicemail->voicemail_uuid,
                        'name' => $this->voicemailOptionName($voicemail),
                    ];
                }
            }

            // Check if it's an extension
            $ext = Extensions::where('domain_uuid', $domainUuid)
                ->where('extension', $destination)
                ->first();
            if ($ext) {

                return [
                    'type' => 'extensions',
                    'extension' => $ext->extension,
                    'option' => $ext->extension_uuid,
                    'name' => $ext->name_formatted,
                ];
            }

            // Assuming it's some external destination
            return [
                'type' => 'external',
                'extension' => $destination,
                'option' => '',
                'name' => '',
            ];
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
        $domainUuid = $this->domainUuid;

        // Use regex and check in the Dialplan database to determine what this extension belongs to
        $dialplan = Dialplans::where('dialplan_number', $extension)
            ->where('dialplan_enabled', 'true')
            ->where(function ($query) use ($domainUuid) {
                $query->where('domain_uuid', $domainUuid)
                    ->orWhereNull('domain_uuid');
            })
            ->select('dialplan_uuid', 'dialplan_name', 'dialplan_number', 'dialplan_xml', 'dialplan_order')
            ->first();

        // If a Dialplan match is found, reverse-engineer it based on the XML and determine the type
        if ($dialplan) {
            return $this->mapDialplanToRoutingOption($dialplan, $extension);
        }

        // Check if destination is voicemail
        if ((substr($extension, 0, 3) == '*99') !== false) {
            $voicemail = Voicemails::where('domain_uuid', $domainUuid)
                ->where('voicemail_id', substr($extension, 3))
                ->with(['extension' => function ($query) use ($domainUuid) {
                    $query->select('extension_uuid', 'extension', 'effective_caller_id_name')
                        ->where('domain_uuid', $domainUuid);
                }])
                ->first();

            if (!$voicemail) return null;
            return [
                'type' => 'voicemails',
                'extension' => $voicemail->voicemail_id,
                'option' => $voicemail->voicemail_uuid,
                'name' => $this->voicemailOptionName($voicemail),
            ];
        }

        // Fallback: assume it's an extension if no Dialplan match
        $ext = Extensions::where('domain_uuid', $domainUuid)
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

    private function voicemailOptionName(Voicemails $voicemail): string
    {
        if ($voicemail->extension) {
            return $voicemail->extension->name_formatted;
        }

        return $voicemail->voicemail_id
            . ' - Team voicemail'
            . ($voicemail->voicemail_description ? ' (' . $voicemail->voicemail_description . ')' : '');
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
            'business_hours' => '/business_hours=([0-9a-fA-F-]+)/',
            'call_flows' => '/call_flow_uuid=([0-9a-fA-F-]+)/',
            'dynamic_routes' => '/dynamic_route_uuid=([0-9a-fA-F-]+)/',
            'time_conditions' => '/\b(year|yday|mon|mday|week|mweek|wday|hour|minute|minute-of-day|time-of-day|date-time)=("[^"]+"|\'[^\']+\'|\S+)/',
            'faxes' => '/fax_uuid=([0-9a-fA-F-]+)/',
            'conferences' => '/conference_uuid=([0-9a-fA-F-]+)/',
            'conference_centers' => '/app.lua conference_center/',
            'ai_agents' => '/ai_agent.lua\s+([0-9a-fA-F-]+)/',
            'check_voicemail' => '/app.lua voicemail/',
            'company_directory' => '/directory.lua/',
            'external' => '/disa.lua/',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $dialplan->dialplan_xml, $matches)) {
                if ($type === 'business_hours') {
                    // For business hours, return the dialplan UUID as the option
                    return [
                        'type' => $type,
                        'extension' => $extension,
                        'option' => $matches[1],
                        'name' => $dialplan->dialplan_name,
                    ];
                }
                if ($type === 'time_conditions') {
                    // For time conditions, return the dialplan UUID as the option
                    return [
                        'type' => $type,
                        'extension' => $extension,
                        'option' => $dialplan->dialplan_uuid,
                        'name' => $dialplan->dialplan_name,
                    ];
                }

                if ($type === 'check_voicemail') {
                    // For time conditions, return the dialplan UUID as the option
                    return [
                        'type' => $type,
                        'extension' => $extension,
                        'option' => null,
                        // 'name' => $dialplan->dialplan_name,
                    ];
                }

                if ($type === 'company_directory') {
                    // For time conditions, return the dialplan UUID as the option
                    return [
                        'type' => $type,
                        'extension' => $extension,
                        'option' => null,
                        // 'name' => $dialplan->dialplan_name,
                    ];
                }

                if ($type === 'conference_centers') {
                    $conferenceCenter = ConferenceCenter::where('domain_uuid', $this->domainUuid)
                        ->where('conference_center_extension', $extension)
                        ->first();

                    return [
                        'type' => $type,
                        'extension' => $extension,
                        'option' => $conferenceCenter?->conference_center_uuid,
                        'name' => $conferenceCenter
                            ? $conferenceCenter->conference_center_extension . ' - ' . $conferenceCenter->conference_center_name
                            : $dialplan->dialplan_name,
                    ];
                }

                if ($type === 'ai_agents') {
                    $agent = AiAgent::query()
                        ->where('domain_uuid', $this->domainUuid)
                        ->where('ai_agent_uuid', $matches[1])
                        ->first();

                    return [
                        'type' => $type,
                        'extension' => $extension,
                        'option' => $agent?->ai_agent_uuid,
                        'name' => $agent ? $agent->extension . ' - ' . $agent->name : $dialplan->dialplan_name,
                    ];
                }

                if ($type === 'dynamic_routes') {
                    $dynamicRoute = DynamicRoute::query()
                        ->where('domain_uuid', $this->domainUuid)
                        ->whereKey($matches[1])
                        ->first(['dynamic_route_uuid', 'extension', 'name']);

                    return [
                        'type' => $type,
                        'extension' => $dynamicRoute?->extension ?? $extension,
                        'option' => $dynamicRoute?->dynamic_route_uuid ?? $matches[1],
                        'name' => $dynamicRoute
                            ? $dynamicRoute->extension . ' - ' . $dynamicRoute->name
                            : $dialplan->dialplan_name,
                    ];
                }

                if ($type === 'external') {
                    // For unknown destination
                    return [
                        'type' => $type,
                        'extension' => $extension,
                        'option' => null,
                        // 'name' => $dialplan->dialplan_name,
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
        $domainUuid = $this->domainUuid;

        // Get the second part, which is the file name
        if (isset($parts[1])) {
            $fileName = $parts[1]; // This will return the file name (e.g., recorded_0bbac5f48265cd0392946a0f2f79423c.wav)
        }

        $recording = Recordings::where('domain_uuid', $domainUuid)
            ->where('recording_filename', $fileName)
            ->first();

        if ($recording) {
            return [
                'type' => 'recordings',
                'extension' => $fileName,
                'option' => $recording->recording_uuid,
                'name' => $recording->recording_name,
            ];
        } else {
            return [];
        }
    }

    protected function reverseEngineerBridgeAction(?string $destinationData, ?string $bridgeUuid = null): array
    {
        $destination = trim((string) $destinationData);

        $bridgeUuid ??= BridgeRuntimeDestination::uuid($destination);

        if ($destination === '' && blank($bridgeUuid)) {
            return [
                'type' => 'bridges',
                'extension' => null,
                'option' => null,
                'name' => null,
            ];
        }

        $bridge = null;

        if (filled($bridgeUuid)) {
            $bridge = Bridge::where('domain_uuid', $this->domainUuid)
                ->whereKey($bridgeUuid)
                ->first(['bridge_uuid', 'bridge_name', 'bridge_destination']);
        }

        $bridge ??= Bridge::where('domain_uuid', $this->domainUuid)
            ->where('bridge_destination', $destination)
            ->first(['bridge_uuid', 'bridge_name', 'bridge_destination']);

        return [
            'type' => 'bridges',
            'extension' => $bridge?->bridge_uuid ?? $destination,
            'option' => $bridge?->bridge_uuid,
            'bridge_uuid' => $bridge?->bridge_uuid,
            'name' => $bridge?->bridge_name ?? $destination,
        ];
    }

    public function getFriendlyTypeName(string $type): string
    {
        $typeMapping = [
            'extensions' => __('Extension'),
            'voicemails' => __('Voicemail'),
            'ring_groups' => __('Ring Group'),
            'ivrs' => __('Virtual Receptionist'),
            'contact_centers' => __('Contact Center'),
            'faxes' => __("Fax"),
            'business_hours' => __('Business Hours'),
            'time_conditions' => __('Schedules'),
            'bridges' => __('Bridge'),
            'call_flows' => __('Call Flow'),
            'dynamic_routes' => __('Dynamic Route'),
            'conferences' => __('Conference'),
            'conference_centers' => __('Conference Center'),
            'ai_agents' => __('AI Agent'),
            'recordings' => __('Play recording'),
            'company_directory' => __('Company Directory'),
            'check_voicemail' => __('Check Voicemail'),
            'hangup' => __('Hang up'),
            'external' => __("External Number")
        ];

        return $typeMapping[$type] ?? __('Unknown');
    }

    /**
     * Given an action key, return the corresponding Eloquent model class.
     *
     * @param  string  $action
     * @return class-string<Model>

     */
    public function mapActionToModel(string $action)
    {
        return self::destinations()[$action]['model'] ?? null;
    }

    public static function destinationHandler(string $action): ?string
    {
        $definition = self::destinations()[$action] ?? null;

        return $definition ? ($definition['handler'] ?? (isset($definition['model']) ? 'transfer' : null)) : null;
    }

    public static function requiresTarget(string $action): bool
    {
        return isset(self::destinations()[$action]['model']);
    }

    public static function destinationTypes(): array
    {
        return array_keys(self::destinations());
    }

    public static function forwardingDestinationTypes(): array
    {
        return [...array_values(array_filter(self::destinationTypes(), fn ($type) =>
            self::requiresTarget($type) && in_array(self::destinationHandler($type), ['transfer', 'voicemail'], true)
        )), 'external'];
    }

    public static function forwardingTarget(?string $action, ?string $target, ?string $external = null): ?string
    {
        if ($action === 'external') {
            return $external ?? $target;
        }
        if (blank($target)) {
            return null;
        }

        return match (self::destinationHandler($action ?? '')) {
            'transfer' => self::requiresTarget($action) ? $target : null,
            'voicemail' => '*99'.$target,
            default => null,
        };
    }

    public function findTarget(string $action, string $value, bool $byDestination = false): ?Model
    {
        $definition = self::destinations()[$action] ?? null;
        if (! isset($definition['model']) || ! $this->domainUuid) {
            return null;
        }

        $model = new $definition['model'];

        return $model->newQuery()->where('domain_uuid', $this->domainUuid)
            ->where($byDestination ? $definition['field'] : $model->getKeyName(), $value)->first();
    }

    public function actionForTarget(string $action, ?Model $target, ?string $domainName = null): array
    {
        $definition = self::destinations()[$action] ?? null;
        if (! $definition) {
            throw ValidationException::withMessages(['target' => __('Unsupported routing action.')]);
        }

        $extension = null;
        if (isset($definition['model'])) {
            $model = $definition['model'];
            if (! $target instanceof $model || ! $this->domainUuid || $target->domain_uuid !== $this->domainUuid) {
                throw ValidationException::withMessages(['target' => __('Select a valid routing target in this account.')]);
            }

            $extension = $target->{$definition['field']};
            if (blank($extension)) {
                throw ValidationException::withMessages(['target' => __('The routing target has no destination.')]);
            }
        }

        $destination = buildDestinationAction(['type' => $action, 'extension' => $extension], $domainName ?? $this->domainName);
        if (! isset($destination['destination_app'], $destination['destination_data'])) {
            throw ValidationException::withMessages(['target' => __('Unsupported routing action.')]);
        }

        return $destination;
    }
}
