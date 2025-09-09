<?php

namespace App\Http\Webhooks\Jobs;

use App\Models\Extensions;
use App\Models\HotelRoom;
use App\Models\HotelRoomStatus;
use App\Services\HotelRoomService;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob as SpatieProcessWebhookJob;
use Carbon\Carbon;

class ProcessCharPmsWebhookJob extends SpatieProcessWebhookJob implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        $payload     = $this->webhookCall->payload;
        $action      = strtoupper((string) Arr::get($payload, 'action', ''));
        $domainUuid  = (string) Arr::get($payload, '_resolved_domain_uuid', '');
        $ext         = Arr::get($payload, 'extension_id');
        $dst         = Arr::get($payload, 'destination_id');

        /** @var HotelRoomService $svc */
        $svc = app(HotelRoomService::class);

        $findRoomByExt = function (string $domainUuid, string $ext) {
            $extensionUuid = Extensions::query()
                ->where('domain_uuid', $domainUuid)
                ->where('extension', $ext)
                ->value('extension_uuid');
            if (!$extensionUuid) return null;

            return HotelRoom::query()
                ->where('domain_uuid', $domainUuid)
                ->where('extension_uuid', $extensionUuid)
                ->first();
        };

        switch ($action) {
            case 'CHKI': {
                    $room = $findRoomByExt($domainUuid, (string)$ext);
                    if (!$room) break;

                    $payloadForSvc = [
                        'guest_first_name' => Arr::get($payload, 'name'),
                        'guest_last_name'  => Arr::get($payload, 'surname'),
                        'arrival_date'     => Arr::get($payload, 'arrival'),
                        'departure_date'   => Arr::get($payload, 'departure'),
                        'extension_id'      => Arr::get($payload, 'extension_id'),
                        'extension_name'    => Arr::get($payload, 'extension_name'),
                    ];

                    $svc->checkIn($room, array_filter($payloadForSvc, fn($v) => $v !== null));
                    break;
                }

            case 'UPDATE': {
                    $room = $findRoomByExt($domainUuid, (string)$ext);
                    if (!$room) break;

                    $changes = [
                        'guest_first_name' => Arr::get($payload, 'name'),
                        'guest_last_name'  => Arr::get($payload, 'surname'),
                        'arrival_date'     => Arr::get($payload, 'arrival'),
                        'departure_date'   => Arr::get($payload, 'departure'),
                        'extension_id'      => Arr::get($payload, 'extension_id'),
                        'extension_name'    => Arr::get($payload, 'extension_name'),
                    ];

                    $svc->update($room, array_filter($changes, fn($v) => $v !== null && $v !== ''));

                    break;
                }

            case 'MOVE': { // Move guest between extensions
                    $srcExt  = (string) $ext;
                    $dstExt  = (string) $dst;

                    $src   = $findRoomByExt($domainUuid, $srcExt);
                    $dstRm = $findRoomByExt($domainUuid, $dstExt);

                    // Controller already validated existence; double-check defensively
                    if (!$src || !$dstRm) {
                        logger()->warning('MOVE skipped: room not found', [
                            'domain' => $domainUuid,
                            'src_ext' => $srcExt,
                            'dst_ext' => $dstExt,
                        ]);
                        break;
                    }

                    try {
                        $changes = [
                            'extension_id'      => Arr::get($payload, 'extension_id'),
                            'destination_id'    => Arr::get($payload, 'destination_id'),
                        ];
                        // Atomic move (locks rows, checks occupancy again to avoid races)
                        $svc->move($src, $dstRm, array_filter($changes, fn($v) => $v !== null && $v !== ''));

                    } catch (\DomainException $e) {
                        // Permanent business rule violation detected after acceptance (race window)
                        // Do not throw: we already returned 200 to CHAR; just log for audit.
                        logger()->warning('MOVE rejected post-acceptance', [
                            'reason'   => $e->getMessage(),
                            'domain'   => $domainUuid,
                            'src_room' => $src->uuid ?? null,
                            'dst_room' => $dstRm->uuid ?? null,
                        ]);
                        // no rethrow
                    } catch (\Throwable $e) {
                        // Unexpected error (transient). Let Spatie record the failure so you can retry.
                        report($e);
                        throw $e;
                    }

                    break;
                }


            case 'CHKO': { // Check-out
                    $room = $findRoomByExt($domainUuid, (string)$ext);
                    if ($room) {
                        $svc->checkOut($room);
                    }
                    break;
                }
        }
    }
}
