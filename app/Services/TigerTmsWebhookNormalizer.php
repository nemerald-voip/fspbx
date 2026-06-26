<?php

namespace App\Services;

use Illuminate\Support\Arr;

class TigerTmsWebhookNormalizer
{
    public function normalize(array $payload): array
    {
        $site = $this->first($payload, [
            'site', 'Site', 'siteCode', 'site_code', 'property', 'Property',
            'propertyId', 'propertyID', 'property_id', 'propertyCode', 'property_code',
            'data.site', 'data.siteCode', 'data.property', 'data.propertyId',
            'event.site', 'event.siteCode', 'event.property', 'event.propertyId',
            'payload.site', 'payload.siteCode', 'payload.property', 'payload.propertyId',
        ]);

        $room = $this->first($payload, [
            'room', 'Room', 'roomNumber', 'RoomNumber', 'room_number',
            'data.room', 'data.roomNumber', 'data.room_number',
            'event.room', 'event.roomNumber', 'event.room_number',
            'payload.room', 'payload.roomNumber', 'payload.room_number',
            'reservation.room', 'reservation.roomNumber', 'Reservation.Room', 'Reservation.RoomNumber',
            'room.roomNumber', 'room.room_number',
        ]);

        $event = $this->first($payload, [
            'action', 'Action', 'eventType', 'eventtype', 'event_type', 'type', 'Type',
            'event', 'Event', 'eventName', 'event_name', 'eventSubType', 'eventsubtype',
            'event_subtype', 'subType', 'subtype', 'operation',
            'data.action', 'data.eventType', 'data.type', 'data.event',
            'event.action', 'event.eventType', 'event.type', 'event.event',
            'payload.action', 'payload.eventType', 'payload.type', 'payload.event',
        ]);

        $action = $this->normalizeAction($event);

        return [
            'site' => $this->stringOrNull($site),
            'room' => $this->stringOrNull($room),
            'from_room' => $this->stringOrNull($this->first($payload, [
                'fromRoom', 'fromRoomNumber', 'from_room', 'from_room_number',
                'data.fromRoom', 'data.fromRoomNumber', 'data.from_room', 'data.from_room_number',
                'payload.fromRoom', 'payload.fromRoomNumber', 'payload.from_room', 'payload.from_room_number',
            ])),
            'to_room' => $this->stringOrNull($this->first($payload, [
                'toRoom', 'toRoomNumber', 'to_room', 'to_room_number',
                'data.toRoom', 'data.toRoomNumber', 'data.to_room', 'data.to_room_number',
                'payload.toRoom', 'payload.toRoomNumber', 'payload.to_room', 'payload.to_room_number',
            ])),
            'event' => $this->stringOrNull($event),
            'action' => $action,
            'reservation_number' => $this->stringOrNull($this->first($payload, [
                'reservationNumber', 'reservation_number',
                'reservation.reservationNumber', 'reservation.reservation_number',
                'Reservation.ReservationNumber', 'Reservation.reservationNumber',
                'data.reservationNumber', 'data.reservation.reservationNumber',
                'payload.reservationNumber', 'payload.reservation.reservationNumber',
            ])),
            'guest_first_name' => $this->stringOrNull($this->first($payload, [
                'guest_first_name', 'guestFirstName', 'firstname', 'firstName', 'name',
                'guest.firstname', 'guest.firstName', 'guest.name',
                'guests.0.firstname', 'guests.0.firstName', 'guests.0.name',
                'reservation.guests.0.firstname', 'reservation.guests.0.firstName', 'reservation.guests.0.name',
                'Reservation.Guests.0.firstname', 'Reservation.Guests.0.firstName', 'Reservation.Guests.0.name',
                'data.guests.0.firstname', 'data.guests.0.firstName', 'data.guests.0.name',
                'data.reservation.guests.0.firstname', 'data.reservation.guests.0.firstName',
                'payload.guests.0.firstname', 'payload.guests.0.firstName', 'payload.guests.0.name',
            ])),
            'guest_last_name' => $this->stringOrNull($this->first($payload, [
                'guest_last_name', 'guestLastName', 'lastname', 'lastName', 'surname',
                'guest.lastname', 'guest.lastName', 'guest.surname',
                'guests.0.lastname', 'guests.0.lastName', 'guests.0.surname',
                'reservation.guests.0.lastname', 'reservation.guests.0.lastName', 'reservation.guests.0.surname',
                'Reservation.Guests.0.lastname', 'Reservation.Guests.0.lastName', 'Reservation.Guests.0.surname',
                'data.guests.0.lastname', 'data.guests.0.lastName', 'data.guests.0.surname',
                'data.reservation.guests.0.lastname', 'data.reservation.guests.0.lastName',
                'payload.guests.0.lastname', 'payload.guests.0.lastName', 'payload.guests.0.surname',
            ])),
            'arrival_date' => $this->normalizeDateForHotelService($this->first($payload, [
                'arrivalDate', 'arrival_date', 'arrival',
                'reservation.arrivalDate', 'reservation.arrival_date', 'reservation.arrival',
                'Reservation.ArrivalDate', 'Reservation.arrivalDate',
                'data.arrivalDate', 'data.reservation.arrivalDate',
                'payload.arrivalDate', 'payload.reservation.arrivalDate',
            ])),
            'departure_date' => $this->normalizeDateForHotelService($this->first($payload, [
                'departureDate', 'departure_date', 'departure',
                'reservation.departureDate', 'reservation.departure_date', 'reservation.departure',
                'Reservation.DepartureDate', 'Reservation.departureDate',
                'data.departureDate', 'data.reservation.departureDate',
                'payload.departureDate', 'payload.reservation.departureDate',
            ])),
        ];
    }

    private function normalizeAction($value): ?string
    {
        $event = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $value));

        return match (true) {
            $event === 'chki',
            str_contains($event, 'checkin'),
            str_contains($event, 'checkedin') => 'checkin',

            $event === 'chko',
            str_contains($event, 'checkout'),
            str_contains($event, 'checkedout') => 'checkout',

            str_contains($event, 'transfer'),
            str_contains($event, 'roommove'),
            str_contains($event, 'move') => 'transfer',

            default => null,
        };
    }

    private function normalizeDateForHotelService($value): ?string
    {
        $value = $this->stringOrNull($value);

        if ($value === null) {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return "{$value} 00:00:00";
        }

        return $value;
    }

    private function first(array $payload, array $paths)
    {
        foreach ($paths as $path) {
            $value = Arr::get($payload, $path);

            if ($value !== null && $value !== '' && !is_array($value) && !is_object($value)) {
                return $value;
            }
        }

        return null;
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
