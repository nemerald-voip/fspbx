<?php

namespace App\Services;

use App\Jobs\SendFaxJob;
use App\Models\Faxes;
use App\Models\OutboundFax;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class FaxForwardService
{
    public function forward(Faxes $fax, string $receivedUuid, ?string $filePath, ?int $pages): void
    {
        $destination = preg_replace('/[\s().-]+/', '', (string) $fax->fax_forward_number);
        if ($destination === '') {
            return;
        }

        if (!preg_match('/^\+?[0-9]+$/', $destination)) {
            throw new RuntimeException('Fax forward number must be an extension or phone number.');
        }

        // Stable across webhook retries and servers; the primary key is the
        // durable identity of this forward, independent of cache expiry.
        $uuid = Uuid::uuid5(Uuid::NAMESPACE_URL, "fax-forward:{$fax->domain_uuid}:{$receivedUuid}")->toString();
        if (OutboundFax::whereKey($uuid)->exists()) {
            return;
        }

        // Match FaxSendService normalization; SendFaxJob owns route discovery.
        $destination = formatPhoneNumber(
            $destination,
            get_domain_setting('country', $fax->domain_uuid) ?? 'US',
            \libphonenumber\PhoneNumberFormat::E164
        );

        DB::transaction(function () use ($fax, $uuid, $destination, $filePath, $pages) {
            // Serialize duplicate deliveries before copying or creating the row.
            Faxes::where('domain_uuid', $fax->domain_uuid)
                ->whereKey($fax->fax_uuid)->lockForUpdate()->firstOrFail();
            if (OutboundFax::whereKey($uuid)->exists()) {
                return;
            }

            if (!$filePath || !is_file($filePath) || !is_readable($filePath) || filesize($filePath) === 0) {
                throw new RuntimeException('Received fax file is unavailable for forwarding.');
            }

            // Own a separate copy so deleting the inbox fax cannot break retries
            // or remove the sent fax attachment.
            $directory = "{$fax->accountcode}/{$fax->fax_extension}/sent";
            Storage::disk('fax')->makeDirectory($directory);
            $target = Storage::disk('fax')->path("{$directory}/forward-{$uuid}.tif");
            if (!copy($filePath, $target)) {
                throw new RuntimeException('Could not copy the received fax for forwarding.');
            }

            $pdf = preg_replace('/\.tiff?$/i', '.pdf', $filePath);
            if ($pdf !== $filePath && is_file($pdf) && !copy($pdf, substr($target, 0, -4) . '.pdf')) {
                throw new RuntimeException('Could not copy the received fax PDF for forwarding.');
            }

            app(FaxSendService::class)->createOutboundFax([
                'fax_destination' => $destination,
                'from' => $fax->fax_email,
            ], $fax, $target, $pages, $uuid);

            DB::afterCommit(function () use ($uuid) {
                try {
                    SendFaxJob::dispatch($uuid);
                } catch (\Throwable $e) {
                    // The existing stuck-fax scanner will dispatch waiting rows.
                    Log::warning('Fax forward dispatch failed; fax remains waiting.', [
                        'outbound_fax_uuid' => $uuid,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        });
    }
}
