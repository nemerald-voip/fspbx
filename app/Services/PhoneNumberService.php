<?php

namespace App\Services;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberService
{
    /** @var array<string, string> */
    private array $countryCodes = [];

    public function countryCodeForDomain(?string $domainUuid = null): string
    {
        $domainUuid = $domainUuid ?: session('domain_uuid');
        $cacheKey = $domainUuid ?: '__default__';

        if (!array_key_exists($cacheKey, $this->countryCodes)) {
            $countryCode = get_domain_setting('country', $domainUuid) ?: 'US';
            $this->countryCodes[$cacheKey] = strtoupper(trim((string) $countryCode)) ?: 'US';
        }

        return $this->countryCodes[$cacheKey];
    }

    public function formatForDomain(
        ?string $phoneNumber,
        ?string $domainUuid = null,
        int $format = PhoneNumberFormat::NATIONAL
    ): ?string {
        return formatPhoneNumber(
            $phoneNumber,
            $this->countryCodeForDomain($domainUuid),
            $format
        );
    }

    /**
     * Build a literal FreeSWITCH dialplan match for a phone number or identifier.
     *
     * Valid phone numbers are canonicalized to E.164 and matched against the
     * common forms carriers use for inbound Request-URI users. Values that are
     * not valid phone numbers, such as internal extensions, remain exact.
     *
     * @return array{canonical: string, expression: string, is_phone_number: bool}
     */
    public function dialplanMatchForDomain(string $value, ?string $domainUuid = null): array
    {
        return $this->dialplanMatchForCountry(
            $value,
            $this->countryCodeForDomain($domainUuid)
        );
    }

    /**
     * @return array{canonical: string, expression: string, is_phone_number: bool}
     */
    public function dialplanMatchForCountry(string $value, string $countryCode): array
    {
        $value = trim($value);
        $exact = [
            'canonical' => $value,
            'expression' => '^' . preg_quote($value, '/') . '$',
            'is_phone_number' => false,
        ];

        if ($value === '') {
            return $exact;
        }

        $phoneNumbers = PhoneNumberUtil::getInstance();

        try {
            $number = $phoneNumbers->parse(
                $value,
                strtoupper(trim($countryCode)) ?: 'US'
            );
        } catch (NumberParseException) {
            return $exact;
        }

        if (! $phoneNumbers->isValidNumber($number)) {
            return $exact;
        }

        $e164 = $phoneNumbers->format($number, PhoneNumberFormat::E164);
        $nationalDigits = preg_replace(
            '/\D+/',
            '',
            $phoneNumbers->format($number, PhoneNumberFormat::NATIONAL)
        );

        $variants = array_values(array_unique(array_filter([
            $e164,
            ltrim($e164, '+'),
            $nationalDigits,
            $phoneNumbers->getNationalSignificantNumber($number),
        ], fn (?string $variant) => $variant !== null && $variant !== '')));

        return [
            'canonical' => $e164,
            'expression' => '^(?:' . implode('|', array_map(
                fn (string $variant) => preg_quote($variant, '/'),
                $variants
            )) . ')$',
            'is_phone_number' => true,
        ];
    }

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
