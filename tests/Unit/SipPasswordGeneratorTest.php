<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SipPasswordGeneratorTest extends TestCase
{
    public function test_generated_sip_passwords_never_contain_dollar_signs(): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $this->assertStringNotContainsString('$', generate_sip_password());
        }
    }
}
