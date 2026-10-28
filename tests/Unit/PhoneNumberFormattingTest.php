<?php

namespace Tests\Unit;

use App\Models\Destinations;
use App\Services\PhoneNumberService;
use libphonenumber\PhoneNumberFormat;
use PHPUnit\Framework\TestCase;

class PhoneNumberFormattingTest extends TestCase
{
    /** @dataProvider originalDids */
    public function test_original_did_normalization(array $attributes, string $country, ?string $expected): void
    {
        $service = new class($country) extends PhoneNumberService
        {
            public ?string $requestedDomain = null;

            public function __construct(private string $country) {}

            public function countryCodeForDomain(?string $domainUuid = null): string
            {
                $this->requestedDomain = $domainUuid;

                return $this->country;
            }
        };
        $phoneNumber = new Destinations(array_merge([
            'domain_uuid' => '7d58342b-2b29-4dcf-92d6-e9a9e002a4e5',
        ], $attributes));

        $this->assertSame($expected, $service->originalDid($phoneNumber));
        if ($expected !== null) {
            $this->assertSame($phoneNumber->domain_uuid, $service->requestedDomain);
        }
    }

    public static function originalDids(): array
    {
        return [
            'existing DID' => [['destination_number' => '5304792220', 'destination_prefix' => '1'], 'US', '+15304792220'],
            'possible DID with country prefix' => [['destination_number' => '7282396453', 'destination_prefix' => '1'], 'US', '+17282396453'],
            'possible DID without prefix' => [['destination_number' => '7282396453'], 'US', '+17282396453'],
            'possible DID outside recognized ranges' => [['destination_number' => '9995550123', 'destination_prefix' => '1'], 'US', '+19995550123'],
            'possible E164 DID' => [['destination_number' => '+17282396453', 'destination_prefix' => '1'], 'US', '+17282396453'],
            'complete DID does not duplicate prefix' => [['destination_number' => '17282396453', 'destination_prefix' => '1'], 'US', '+17282396453'],
            'explicit country differs from account' => [['destination_number' => '7282396453', 'destination_prefix' => '1'], 'GB', '+17282396453'],
            'account country' => [['destination_number' => '02079460958'], 'GB', '+442079460958'],
            'national trunk prefix' => [['destination_number' => '02079460958', 'destination_prefix' => '44'], 'GB', '+442079460958'],
            'separate area and trunk' => [['destination_number' => '79460958', 'destination_area_code' => '20', 'destination_trunk_prefix' => '0', 'destination_prefix' => '44'], 'GB', '+442079460958'],
            'significant Italian zero' => [['destination_number' => '066982', 'destination_prefix' => '39'], 'IT', '+39066982'],
            'blank' => [['destination_number' => ''], 'US', null],
            'short identifier' => [['destination_number' => '1001'], 'US', null],
            'short number with prefix' => [['destination_number' => '1001', 'destination_prefix' => '1'], 'US', null],
            'too long' => [['destination_number' => '728239645312345678', 'destination_prefix' => '1'], 'US', null],
            'wildcard' => [['destination_number' => '72823964XX', 'destination_prefix' => '1'], 'US', null],
            'regular expression' => [['destination_number' => '(7282396453|5304792220)', 'destination_prefix' => '1'], 'US', null],
            'prefix pattern' => [['destination_number' => '7282396453', 'destination_prefix' => '1?'], 'US', null],
            'area pattern' => [['destination_number' => '2396453', 'destination_area_code' => '72X', 'destination_prefix' => '1'], 'US', null],
            'trunk pattern' => [['destination_number' => '7282396453', 'destination_trunk_prefix' => '0?', 'destination_prefix' => '1'], 'US', null],
            'unknown calling code' => [['destination_number' => '+9997282396453'], 'US', null],
        ];
    }

    public function test_national_number_uses_the_requested_country(): void
    {
        $this->assertSame(
            '+442079460958',
            formatPhoneNumber('2079460958', 'gb', PhoneNumberFormat::E164)
        );
        $this->assertSame(
            '+12079460958',
            formatPhoneNumber('2079460958', 'US', PhoneNumberFormat::E164)
        );
    }

    public function test_numeric_format_arguments_remain_compatible_with_enum_formats(): void
    {
        $this->assertSame('+17282396453', formatPhoneNumber('7282396453', 'US', 0));
        $this->assertSame('(728) 239-6453', formatPhoneNumber('+17282396453', 'US', 2));

        $service = new class extends PhoneNumberService
        {
            public function countryCodeForDomain(?string $domainUuid = null): string
            {
                return 'US';
            }
        };
        $this->assertSame('(728) 239-6453', $service->formatForDomain('7282396453'));
        $this->assertSame('+17282396453', $service->formatForDomain('7282396453', null, 0));
        $this->assertSame('+17282396453', $service->formatForDomain('7282396453', null, PhoneNumberFormat::E164));
    }

    public function test_updated_metadata_recognizes_the_reported_did(): void
    {
        $this->assertSame([
            'canonical' => '+17282396453',
            'expression' => '^(?:\\+17282396453|17282396453|7282396453)$',
            'is_phone_number' => true,
        ], (new PhoneNumberService())->dialplanMatchForCountry('7282396453', 'US'));

        $this->assertSame('+17282396453', formatPhoneNumber('7282396453', 'US', PhoneNumberFormat::E164));
        $this->assertSame('(728) 239-6453', formatPhoneNumber('7282396453', 'US'));
    }

    public function test_number_type_enum_preserves_group_messaging_limits(): void
    {
        $capabilities = new \App\Services\Messaging\MessageCarrierCapabilities();

        $this->assertSame(10, $capabilities->recipientLimit('bandwidth', '+17282396453'));
        $this->assertSame(1, $capabilities->recipientLimit('bandwidth', '+18005550100'));
        $this->assertSame(1, $capabilities->recipientLimit('bandwidth', 'not-a-number'));
    }

    public function test_international_number_is_converted_to_the_requested_format(): void
    {
        $this->assertSame(
            '020 7946 0958',
            formatPhoneNumber('+442079460958', 'GB', PhoneNumberFormat::NATIONAL)
        );
        $this->assertSame(
            '+442079460958',
            formatPhoneNumber('+442079460958', 'GB', PhoneNumberFormat::E164)
        );
    }

    public function test_us_international_dialing_prefix_is_parsed_for_e164_output(): void
    {
        $this->assertSame(
            '+442079460958',
            formatPhoneNumber('011442079460958', 'US', PhoneNumberFormat::E164)
        );
    }

    public function test_invalid_and_blank_values_are_preserved(): void
    {
        $this->assertSame('not-a-number', formatPhoneNumber('not-a-number', 'GB', PhoneNumberFormat::E164));
        $this->assertSame('', formatPhoneNumber('', 'GB', PhoneNumberFormat::E164));
        $this->assertNull(formatPhoneNumber(null, 'GB', PhoneNumberFormat::E164));
    }

    public function test_dialplan_match_accepts_us_national_and_e164_carrier_formats(): void
    {
        $this->assertSame([
            'canonical' => '+15304792220',
            'expression' => '^(?:\+15304792220|15304792220|5304792220)$',
            'is_phone_number' => true,
        ], (new PhoneNumberService())->dialplanMatchForCountry('5304792220', 'US'));
    }

    public function test_dialplan_match_uses_the_numbers_country_for_explicit_international_input(): void
    {
        $this->assertSame([
            'canonical' => '+79108583458',
            'expression' => '^(?:\+79108583458|79108583458|89108583458|9108583458)$',
            'is_phone_number' => true,
        ], (new PhoneNumberService())->dialplanMatchForCountry('+79108583458', 'US'));
    }

    public function test_dialplan_match_includes_country_specific_national_trunk_prefix(): void
    {
        $this->assertSame([
            'canonical' => '+442079460958',
            'expression' => '^(?:\+442079460958|442079460958|02079460958|2079460958)$',
            'is_phone_number' => true,
        ], (new PhoneNumberService())->dialplanMatchForCountry('020 7946 0958', 'GB'));
    }

    public function test_dialplan_match_keeps_non_phone_identifiers_exact(): void
    {
        $this->assertSame([
            'canonical' => '9005',
            'expression' => '^9005$',
            'is_phone_number' => false,
        ], (new PhoneNumberService())->dialplanMatchForCountry('9005', 'US'));
    }
}
