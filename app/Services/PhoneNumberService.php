<?php

namespace App\Services;


class PhoneNumberService
{
    private function toBool(mixed $value, bool $default = false): bool
    {
        // Handles: true/false, "true"/"false", 1/0, "1"/"0", "on"/"off", etc.
        return filter_var($value ?? $default, FILTER_VALIDATE_BOOLEAN);
    }

    private function boolToString(mixed $value, bool $default = false): string
    {
        return $this->toBool($value, $default) ? 'true' : 'false';
    }

    /**
     * Build destination_actions from routing_options using the existing helper.
     * - If routing_options key is present:
     *   - [] => clears destination_actions
     *   - null => clears destination_actions
     *   - array => builds json
     * - If routing_options key is NOT present: do not touch destination_actions (PATCH-safe)
     */
    private function buildDestinationActionsFromRoutingOptions(array $validated, array &$data, $domain_name): void
    {
        if (! array_key_exists('routing_options', $validated)) {
            return;
        }

        $destination_actions = [];

        $routing = $validated['routing_options'] ?? [];
        if (is_array($routing) && ! empty($routing)) {
            foreach ($routing as $option) {
                $destination_actions[] = buildDestinationAction($option, $domain_name);
            }
        }

        $data['destination_actions'] = json_encode($destination_actions);

        // Do not attempt to store routing_options (not a DB field)
        unset($data['routing_options']);
    }


    /**
     * Build data for update (PATCH-safe).
     * Only normalizes fields that are present in the payload.
     */
    public function buildUpdateData(array $validated,$domain_uuid, $domain_name): array
    {
        $data = $validated;

        // Normalize booleans if present 
        foreach (['destination_enabled', 'destination_record', 'destination_enabled'] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = $this->boolToString($validated[$field]);
            }
        }

        // Numeric fax flag if present
        if (array_key_exists('destination_type_fax', $validated)) {
            $data['destination_type_fax'] = $this->toBool($validated['destination_type_fax']) ? 1 : null;
        }

        // destination_actions if routing_options present 
        $this->buildDestinationActionsFromRoutingOptions($validated, $data,$domain_name);

        return $data;
    }
}
