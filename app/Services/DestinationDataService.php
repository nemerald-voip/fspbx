<?php

namespace App\Services;

use App\Models\Dialplans;
use App\Models\Voicemails;

class DestinationDataService
{
    /**
     * Reverse engineer the destination actions.
     *
     * @param string $destinationActions JSON encoded destination actions.
     * @return array Reverse-engineered routing options.
     */
    public function reverseEngineerDestinationActions($destinationActions)
    {
        // Decode the JSON into an array
        $actions = json_decode($destinationActions, true);

        $routing_options = [];

        foreach ($actions as $action) {
            switch ($action['destination_app']) {
                case 'transfer':
                    // Use regex and the Dialplan database to determine the type and details
                    $routing_options[] = $this->reverseEngineerTransferAction($action['destination_data']);
                    break;

                case 'lua':
                    // Handle recordings
                    $routing_options[] = [
                        'type' => 'recordings',
                        'option' => $this->extractRecordingUuidFromData($action['destination_data']),
                    ];
                    break;

                    // Add more cases as necessary
            }
        }

        return $routing_options;
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
            ->select('dialplan_uuid', 'dialplan_name', 'dialplan_number', 'dialplan_xml')
            ->first();

        // If a Dialplan match is found, reverse-engineer it based on the XML and determine the type
        if ($dialplan) {
            return $this->mapDialplanToRoutingOption($dialplan);
        }

        // Check if destination is voicemail
        if ((substr($extension, 0, 3) == '*99') !== false) {
            $voicemail = Voicemails::where('domain_uuid', session('domain_uuid'))
                ->where('voicemail_id', substr($extension, 3))
                ->first();

            if (!$voicemail) return null;
            return [
                'type' => 'voicemails',
                'extension' => $voicemail->voicemail_id,
                'option' => $voicemail->voicemail_uuid,
            ];
        }

        // Fallback: assume it's an extension if no Dialplan match
        return [
            'type' => 'extensions',
            'option' => $extension,
        ];
    }

    /**
     * Map Dialplan data back to a routing option.
     */
    protected function mapDialplanToRoutingOption($dialplan)
    {
        logger($dialplan);
        // Define regex patterns to determine what the dialplan matches
        $patterns = [
            'ring_groups' => '/ring_group_uuid=([0-9a-fA-F-]+)/',
            'ivrs' => '/ivr_menu_uuid=([0-9a-fA-F-]+)/',
            'contact_centers' => '/call_center_queue_uuid=([0-9a-fA-F-]+)/',
            'call_flows' => '/call_flow_uuid=([0-9a-fA-F-]+)/',
            'time_conditions' => '/time_condition_uuid=([0-9a-fA-F-]+)/',
            'faxes' => '/fax_uuid=([0-9a-fA-F-]+)/',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $dialplan->dialplan_xml, $matches)) {
                return [
                    'type' => $type,
                    'option' => $matches[1],
                ];
            }
        }

        // If no specific type was matched, return the dialplan as an extension
        return [
            'type' => 'extensions',
            'option' => $dialplan->dialplan_number,
        ];
    }

    /**
     * Extract recording UUID from lua destination data.
     */
    protected function extractRecordingUuidFromData($destinationData)
    {
        // Extract the part after "recorded_" and before ".wav"
        if (preg_match('/recorded_([a-f0-9]{32})\.wav/', $destinationData, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
