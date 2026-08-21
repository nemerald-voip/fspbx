<?php

namespace Tests\Unit;

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
}
