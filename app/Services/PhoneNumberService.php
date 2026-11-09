<?php

namespace App\Services;

use App\Models\Destinations;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\ValidationResult;

class PhoneNumberService
{
    /** @var array<string, string> */
    private array $countryCodes = [];

    /** Resolve a literal configured DID without changing its routing expression. */
    public function originalDid(Destinations $phoneNumber): ?string
    {
        $number = trim((string) $phoneNumber->destination_number);
        $prefix = trim((string) $phoneNumber->destination_prefix);
        $area = trim((string) $phoneNumber->destination_area_code);
        $trunk = trim((string) $phoneNumber->destination_trunk_prefix);

        // Patterns (N/X/Z, regexes, etc.) do not identify one business number.
        if (! preg_match('/^\+?[0-9]+$/D', $number)
            || ($prefix !== '' && ! preg_match('/^\+?[0-9]+$/D', $prefix))
            || ! preg_match('/^[0-9]*$/D', $area)
            || ! preg_match('/^[0-9]*$/D', $trunk)) {
            return null;
        }

        $country = $this->countryCodeForDomain($phoneNumber->domain_uuid);
        if (str_starts_with($number, '+')) {
            return $this->normalizeOriginalDid($number, $country);
        } elseif ($prefix !== '') {
            $callingCode = ltrim($prefix, '+');
            $national = $area . $number;
            // Avoid duplicating a prefix when the stored number is already a
            // complete, possible international number.
            $complete = $this->normalizeOriginalDid('+' . $national, $country);
            if ($area === '' && str_starts_with($number, $callingCode) && $complete !== null) {
                return $complete;
            }

            // libphonenumber handles national trunk prefixes. Do not strip
            // leading zeros ourselves: they are significant in Italy.
            return $this->normalizeOriginalDid('+' . $callingCode . $national, $country);
        }

        return $this->normalizeOriginalDid($area . $number, $country);
    }

    private function normalizeOriginalDid(string $value, string $country): ?string
    {
        $phoneNumbers = PhoneNumberUtil::getInstance();

        try {
            $number = $phoneNumbers->parse($value, $country);
        } catch (NumberParseException) {
            return null;
        }

        // A configured DID can belong to a range newer than the bundled metadata.
        // Check its structure without requiring a recognized allocation.
        return $phoneNumbers->isPossibleNumberWithReason($number) === ValidationResult::IS_POSSIBLE
            ? $phoneNumbers->format($number, PhoneNumberFormat::E164)
            : null;
    }

    public function countryCodeForDomain(?string $domainUuid = null): string
    {
        $domainUuid = $domainUuid ?: session('domain_uuid');
        $cacheKey = $domainUuid ?: '__default__';

        if (!array_key_exists($cacheKey, $this->countryCodes)) {
            $countryCode = get_domain_setting('country', $domainUuid);
            // Unknown region still permits explicit international numbers.
            $this->countryCodes[$cacheKey] = strtoupper(trim((string) $countryCode)) ?: PhoneNumberUtil::UNKNOWN_REGION;
        }

        return $this->countryCodes[$cacheKey];
    }

    public function formatForDomain(
        ?string $phoneNumber,
        ?string $domainUuid = null,
        PhoneNumberFormat|int $format = PhoneNumberFormat::NATIONAL
    ): ?string {
        return formatPhoneNumber(
            $phoneNumber,
            $this->countryCodeForDomain($domainUuid),
            $format
        );
    }

    /** Normalize a literal DID into the existing country-prefix/number fields. */
    public function destinationParts(string $value, ?string $prefix, ?string $domainUuid): array
    {
        // Keep dialplan patterns and internal identifiers unchanged.
        if (! preg_match('/^\+?[0-9(][0-9\s().-]*$/D', trim($value))) {
            return ['destination_number' => $value, 'destination_prefix' => $prefix];
        }

        $did = $this->originalDid(new Destinations([
            'domain_uuid' => $domainUuid,
            'destination_number' => preg_replace('/[\s().-]+/', '', trim($value)),
            'destination_prefix' => $prefix,
        ]));
        if ($did === null) {
            return ['destination_number' => $value, 'destination_prefix' => $prefix];
        }

        $util = PhoneNumberUtil::getInstance();
        $number = $util->parse($did);

        return [
            'destination_number' => $util->getNationalSignificantNumber($number),
            'destination_prefix' => (string) $number->getCountryCode(),
        ];
    }

    public function isValidForDomain(string $value, ?string $domainUuid = null): bool
    {
        // Do not interpret dialplan patterns or vanity strings as phone numbers.
        if (! preg_match('/^\+?[0-9(][0-9\s().-]*$/D', trim($value))) {
            return false;
        }

        $util = PhoneNumberUtil::getInstance();
        try {
            return $util->isValidNumber($util->parse($value, $this->countryCodeForDomain($domainUuid)));
        } catch (NumberParseException) {
            return false;
        }
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
                strtoupper(trim($countryCode)) ?: PhoneNumberUtil::UNKNOWN_REGION
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
