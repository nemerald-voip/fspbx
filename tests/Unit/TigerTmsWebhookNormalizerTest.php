<?php

namespace Tests\Unit;

use App\Services\TigerTmsWebhookNormalizer;
use Tests\TestCase;

class TigerTmsWebhookNormalizerTest extends TestCase
{
    public function test_normalizes_common_checkin_payload_shape(): void
    {
        $normalized = app(TigerTmsWebhookNormalizer::class)->normalize([
            'eventType' => 'GuestCheckedIn',
            'property' => '001',
            'roomNumber' => '110',
            'reservation' => [
                'reservationNumber' => 'ABC-123',
                'arrivalDate' => '2026-06-23',
                'departureDate' => '2026-06-25',
                'guests' => [
                    [
                        'firstname' => 'Donald',
                        'lastname' => 'Duck',
                    ],
                ],
            ],
        ]);

        $this->assertSame('001', $normalized['site']);
        $this->assertSame('110', $normalized['room']);
        $this->assertSame('GuestCheckedIn', $normalized['event']);
        $this->assertSame('checkin', $normalized['action']);
        $this->assertSame('ABC-123', $normalized['reservation_number']);
        $this->assertSame('Donald', $normalized['guest_first_name']);
        $this->assertSame('Duck', $normalized['guest_last_name']);
        $this->assertSame('2026-06-23 00:00:00', $normalized['arrival_date']);
        $this->assertSame('2026-06-25 00:00:00', $normalized['departure_date']);
    }

    public function test_normalizes_checkout_action_alias(): void
    {
        $normalized = app(TigerTmsWebhookNormalizer::class)->normalize([
            'action' => 'CHKO',
            'siteCode' => '001',
            'room' => '110',
        ]);

        $this->assertSame('checkout', $normalized['action']);
    }

    public function test_unknown_event_is_left_unprocessed(): void
    {
        $normalized = app(TigerTmsWebhookNormalizer::class)->normalize([
            'eventType' => 'RoomCleaned',
            'siteCode' => '001',
            'roomNumber' => '110',
        ]);

        $this->assertNull($normalized['action']);
    }

    public function test_normalizes_room_transfer_payload_shape(): void
    {
        $normalized = app(TigerTmsWebhookNormalizer::class)->normalize([
            'eventType' => 'room.transfer',
            'propertyId' => '001',
            'data' => [
                'fromRoomNumber' => '100',
                'toRoomNumber' => '102',
            ],
        ]);

        $this->assertSame('001', $normalized['site']);
        $this->assertSame('room.transfer', $normalized['event']);
        $this->assertSame('transfer', $normalized['action']);
        $this->assertSame('100', $normalized['from_room']);
        $this->assertSame('102', $normalized['to_room']);
    }
}
