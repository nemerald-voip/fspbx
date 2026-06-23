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

        Log::info('TigerTMS webhook processing started', [
            'webhook_call_id' => $this->webhookCall->id ?? null,
            'domain_uuid' => $domainUuid,
            'normalized' => $normalized,
            'site_unprocessable' => (bool) Arr::get($payload, '_tigertms_site_unprocessable', false),
            'payload' => $payload,
        ]);

        if ((bool) Arr::get($payload, '_tigertms_site_unprocessable', false)) {
            Log::warning('TigerTMS webhook skipped because site value is not recognized', [
                'webhook_call_id' => $this->webhookCall->id ?? null,
                'site' => Arr::get($payload, '_tigertms_site'),
                'payload' => $payload,
            ]);

            return;
        }

        if (!in_array($action, ['checkin', 'checkout'], true)) {
            Log::info('TigerTMS webhook logged without processing because event is not recognized yet', [
                'webhook_call_id' => $this->webhookCall->id ?? null,
                'event' => $normalized['event'] ?? Arr::get($payload, '_tigertms_event'),
                'payload' => $payload,
            ]);

            return;
        }

        if ($domainUuid === '' || $roomName === '') {
            Log::warning('TigerTMS webhook skipped because domain or room is missing', [
                'webhook_call_id' => $this->webhookCall->id ?? null,
                'domain_uuid' => $domainUuid,
                'room' => $roomName,
                'payload' => $payload,
            ]);

            return;
        }

        if (! app(PmsProviderSettings::class)->isTigerTms($domainUuid)) {
            Log::warning('TigerTMS webhook skipped because TigerTMS is not enabled for this tenant', [
                'webhook_call_id' => $this->webhookCall->id ?? null,
                'domain_uuid' => $domainUuid,
                'room' => $roomName,
                'payload' => $payload,
            ]);

            return;
        }

        $roomCode = app(TigerTmsRoomMapper::class)->normalize($roomName);

        $room = HotelRoom::query()
            ->where('domain_uuid', $domainUuid)
            ->where(function ($query) use ($roomName, $roomCode) {
                $query->where('room_name', $roomName)
                    ->orWhere('room_name', $roomCode)
                    ->orWhere('room_name', 'Room ' . $roomCode);
            })
            ->first();

        if (!$room) {
            Log::warning('TigerTMS webhook skipped because hotel room was not found', [
                'webhook_call_id' => $this->webhookCall->id ?? null,
                'domain_uuid' => $domainUuid,
                'room' => $roomName,
                'tigertms_room' => $roomCode,
                'payload' => $payload,
            ]);

            return;
        }

        /** @var HotelRoomService $service */
        $service = app(HotelRoomService::class);
        $outboundSync = app(PmsOutboundSyncContext::class);

        if ($action === 'checkout') {
            $outboundSync->withoutOutboundSync(fn () => $service->checkOut($room));

            Log::info('TigerTMS checkout processed', [
                'webhook_call_id' => $this->webhookCall->id ?? null,
                'domain_uuid' => $domainUuid,
                'room_uuid' => $room->uuid,
                'room' => $roomName,
            ]);

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

        Log::info('TigerTMS check-in processed', [
            'webhook_call_id' => $this->webhookCall->id ?? null,
            'domain_uuid' => $domainUuid,
            'room_uuid' => $room->uuid,
            'room' => $roomName,
        ]);
    }

    private function guestDisplayName(array $normalized): ?string
    {
        $name = trim(implode(' ', array_filter([
            $normalized['guest_first_name'] ?? null,
            $normalized['guest_last_name'] ?? null,
        ])));

        return $name === '' ? null : $name;
    }
}
