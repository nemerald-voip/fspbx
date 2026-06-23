<?php

namespace Tests\Unit;

use App\Models\HotelRoom;
use App\Services\TigerTmsRoomMapper;
use Tests\TestCase;

class TigerTmsRoomMapperTest extends TestCase
{
    public function test_outbound_strips_common_room_display_prefix(): void
    {
        $room = new HotelRoom(['room_name' => 'Room 100']);

        $this->assertSame('100', app(TigerTmsRoomMapper::class)->outbound($room));
    }

    public function test_outbound_leaves_plain_room_code_unchanged(): void
    {
        $room = new HotelRoom(['room_name' => '100A']);

        $this->assertSame('100A', app(TigerTmsRoomMapper::class)->outbound($room));
    }
}
