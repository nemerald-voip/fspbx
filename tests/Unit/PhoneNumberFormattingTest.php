<?php

namespace Tests\Unit;

use App\Services\PhoneNumberService;
use libphonenumber\PhoneNumberFormat;
use PHPUnit\Framework\TestCase;

class PhoneNumberFormattingTest extends TestCase
{
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
