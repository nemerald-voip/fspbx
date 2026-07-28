<?php

namespace Tests\Unit;

use App\Http\Controllers\ProvisioningController;
use ReflectionMethod;
use Tests\TestCase;

class ProvisioningKeyTypeTest extends TestCase
{
    public function test_yealink_park_uses_call_park_type_while_blf_remains_blf(): void
    {
        $controller = new ProvisioningController();
        $translate = new ReflectionMethod($controller, 'translateKeyTypeForVendor');
        $translate->setAccessible(true);

        $this->assertSame('10', $translate->invoke($controller, 'yealink', 'park')['type']);
        $this->assertSame('16', $translate->invoke($controller, 'yealink', 'blf')['type']);
    }

    public function test_legacy_yealink_key_mapping_matches_native_mapping(): void
    {
        $this->assertSame('10', fspbx_vendor_key_type_code('yealink', 'park'));
        $this->assertSame('16', fspbx_vendor_key_type_code('yealink', 'blf'));
    }
}
