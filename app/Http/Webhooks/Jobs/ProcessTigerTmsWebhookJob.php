<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\HotelRoom;
use App\Services\HotelRoomService;
use App\Services\PmsOutboundSyncContext;
use App\Services\PmsProviderSettings;
use App\Services\TigerTmsRoomMapper;
use App\Services\TigerTmsWebhookNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;

class ProcessTigerTmsWebhookJob extends SpatieProcessWebhookJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $payload = $this->webhookCall->payload;
        $normalized = app(TigerTmsWebhookNormalizer::class)->normalize($payload);

        $domainUuid = strtolower((string) Arr::get($payload, '_tigertms_resolved_domain_uuid', ''));
        $roomName = (string) ($normalized['room'] ?? Arr::get($payload, '_tigertms_room', ''));
        $action = $normalized['action'] ?? Arr::get($payload, '_tigertms_action');

        if ((bool) Arr::get($payload, '_tigertms_site_unprocessable', false)) {
            Log::warning('TigerTMS webhook skipped because site value is not recognized', [
                ...$this->logContext($payload, $normalized),
                'site' => Arr::get($payload, '_tigertms_site'),
            ]);

            return;
        }

        if (!in_array($action, ['checkin', 'checkout', 'transfer'], true)) {
            Log::warning('TigerTMS webhook skipped because event is not recognized yet', [
                ...$this->logContext($payload, $normalized),
                'event' => $normalized['event'] ?? Arr::get($payload, '_tigertms_event'),
            ]);

            return;
        }

        if ($domainUuid === '') {
            Log::warning('TigerTMS webhook skipped because domain is missing', [
                ...$this->logContext($payload, $normalized),
                'domain_uuid' => $domainUuid,
            ]);

            return;
        }

        if (! app(PmsProviderSettings::class)->isTigerTms($domainUuid)) {
            Log::warning('TigerTMS webhook skipped because TigerTMS is not enabled for this tenant', [
                ...$this->logContext($payload, $normalized),
                'domain_uuid' => $domainUuid,
                'room' => $roomName,
            ]);

            return;
        }

        /** @var HotelRoomService $service */
        $service = app(HotelRoomService::class);
        $outboundSync = app(PmsOutboundSyncContext::class);

        if ($action === 'transfer') {
            $this->processTransfer($payload, $normalized, $domainUuid, $service, $outboundSync);

            return;
        }

        if ($roomName === '') {
            Log::warning('TigerTMS webhook skipped because room is missing', [
                ...$this->logContext($payload, $normalized),
                'domain_uuid' => $domainUuid,
            ]);

            return;
        }

        $roomCode = app(TigerTmsRoomMapper::class)->normalize($roomName);
        $room = $this->findRoom($domainUuid, $roomName);

        if (!$room) {
            Log::warning('TigerTMS webhook skipped because hotel room was not found', [
                ...$this->logContext($payload, $normalized),
                'domain_uuid' => $domainUuid,
                'room' => $roomName,
                'tigertms_room' => $roomCode,
            ]);

            return;
        }

        if ($action === 'checkout') {
            $outboundSync->withoutOutboundSync(fn () => $service->checkOut($room));

            return;
        }

        $payloadForService = array_filter([
            'guest_first_name' => $normalized['guest_first_name'] ?? null,
            'guest_last_name' => $normalized['guest_last_name'] ?? null,
            'arrival_date' => $normalized['arrival_date'] ?? null,
            'departure_date' => $normalized['departure_date'] ?? null,
            'occupancy_status' => 'Checked in',
            'extension_name' => $this->guestDisplayName($normalized),
        ], fn ($value) => $value !== null && $value !== '');

        $outboundSync->withoutOutboundSync(fn () => $service->checkIn($room, $payloadForService));
    }

    private function processTransfer(
        array $payload,
        array $normalized,
        string $domainUuid,
        HotelRoomService $service,
        PmsOutboundSyncContext $outboundSync
    ): void {
        $fromRoom = (string) ($normalized['from_room'] ?? '');
        $toRoom = (string) ($normalized['to_room'] ?? '');

        if ($fromRoom === '' || $toRoom === '') {
            Log::warning('TigerTMS transfer skipped because source or destination room is missing', [
                ...$this->logContext($payload, $normalized),
                'from_room' => $fromRoom,
                'to_room' => $toRoom,
            ]);

            return;
        }

        $source = $this->findRoom($domainUuid, $fromRoom);
        $destination = $this->findRoom($domainUuid, $toRoom);

        if (! $source || ! $destination) {
            Log::warning('TigerTMS transfer skipped because source or destination room was not found', [
                ...$this->logContext($payload, $normalized),
                'from_room' => $fromRoom,
                'to_room' => $toRoom,
                'source_found' => (bool) $source,
                'destination_found' => (bool) $destination,
            ]);

            return;
        }

        try {
            $outboundSync->withoutOutboundSync(fn () => $service->move($source, $destination));
        } catch (\DomainException $e) {
            Log::warning('TigerTMS transfer skipped because FS PBX could not move the guest', [
                ...$this->logContext($payload, $normalized),
                'from_room_uuid' => $source->uuid,
                'to_room_uuid' => $destination->uuid,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    private function findRoom(string $domainUuid, string $roomName): ?HotelRoom
    {
        $roomCode = app(TigerTmsRoomMapper::class)->normalize($roomName);

        return HotelRoom::query()
            ->where('domain_uuid', $domainUuid)
            ->where(function ($query) use ($roomName, $roomCode) {
                $query->where('room_name', $roomName)
                    ->orWhere('room_name', $roomCode)
                    ->orWhere('room_name', 'Room ' . $roomCode);
            })
            ->first();
    }

    private function guestDisplayName(array $normalized): ?string
    {
        $name = trim(implode(' ', array_filter([
            $normalized['guest_first_name'] ?? null,
            $normalized['guest_last_name'] ?? null,
        ])));

        return $name === '' ? null : $name;
    }

    private function logContext(array $payload, array $normalized): array
    {
        return [
            'webhook_call_id' => $this->webhookCall->id ?? null,
            'webhook_id' => $payload['id'] ?? null,
            'delivery_id' => Arr::get($payload, '_tigertms_request.headers.x-ilink-delivery.0'),
            'domain_uuid' => Arr::get($payload, '_tigertms_resolved_domain_uuid'),
            'site' => $normalized['site'] ?? null,
            'room' => $normalized['room'] ?? null,
            'from_room' => $normalized['from_room'] ?? null,
            'to_room' => $normalized['to_room'] ?? null,
            'event' => $normalized['event'] ?? null,
            'action' => $normalized['action'] ?? null,
            'reservation_number' => $normalized['reservation_number'] ?? null,
        ];
    }
}
