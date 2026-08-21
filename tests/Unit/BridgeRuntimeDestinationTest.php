<?php

namespace Tests\Unit;

use App\Support\BridgeRuntimeDestination;
use PHPUnit\Framework\TestCase;

class BridgeRuntimeDestinationTest extends TestCase
{
    private const UUID = '9df158e6-b145-4c21-b3a9-049b4ee57e9a';

    public function test_it_parses_bridge_script_data(): void
    {
        $this->assertSame(
            self::UUID,
            BridgeRuntimeDestination::uuidFromScriptData('bridge.lua ' . self::UUID)
        );
    }

    public function test_it_rejects_invalid_bridge_targets(): void
    {
        $this->assertNull(BridgeRuntimeDestination::uuidFromScriptData('streamfile.lua file.wav'));
    }
}
