<?php

namespace App\Jobs;

use App\Models\Faxes;
use App\Models\FaxFiles;
use App\Models\FaxLogs;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessFaxWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [30, 60, 120];

    public function __construct(
        public string $event,
        public ?string $timestamp,
        public array $data
    ) {}

    public function handle(): void
    {
        $faxLogUuid = $this->stringOrNull($this->data['uuid'] ?? null);
        $domainUuid = $this->stringOrNull($this->data['domain_uuid'] ?? null);
        $faxUuid = $this->stringOrNull($this->data['fax_uuid'] ?? null);
        $faxFilePath = $this->stringOrNull($this->data['fax_file'] ?? null);
        $callDirection = $this->stringOrNull($this->data['call_direction'] ?? null) ?? 'inbound';

        if (!$faxLogUuid || !$domainUuid) {
            Log::warning('[Webhook] Missing fax identifiers', [
                'event' => $this->event,
                'data' => $this->data,
            ]);
            return;
        }

        DB::transaction(function () use ($faxLogUuid, $domainUuid, $faxUuid, $faxFilePath, $callDirection) {
            $faxLog = FaxLogs::find($faxLogUuid);

            if (!$faxLog) {
                $faxLog = new FaxLogs();
                $faxLog->fax_log_uuid = $faxLogUuid;
            }

            $faxLog->domain_uuid = $domainUuid;
            $faxLog->fax_uuid = $faxUuid;
            $faxLog->fax_success = $this->stringOrNull($this->data['fax_success'] ?? null);
            $faxLog->fax_result_code = $this->numericOrNull($this->data['fax_result_code'] ?? null);
            $faxLog->fax_result_text = $this->stringOrNull($this->data['fax_result_text'] ?? null);
            $faxLog->fax_file = $faxFilePath;
            $faxLog->fax_ecm_used = $this->stringOrNull($this->data['fax_ecm_used'] ?? null);
            $faxLog->fax_local_station_id = $this->stringOrNull($this->data['fax_local_station_id'] ?? null);
            $faxLog->fax_document_transferred_pages = $this->numericOrNull($this->data['fax_document_transferred_pages'] ?? null);
            $faxLog->fax_document_total_pages = $this->numericOrNull($this->data['fax_document_total_pages'] ?? null);
            $faxLog->fax_image_resolution = $this->stringOrNull($this->data['fax_image_resolution'] ?? null);
            $faxLog->fax_image_size = $this->numericOrNull($this->data['fax_image_size'] ?? null);
            $faxLog->fax_bad_rows = $this->numericOrNull($this->data['fax_bad_rows'] ?? null);
            $faxLog->fax_transfer_rate = $this->numericOrNull($this->data['fax_transfer_rate'] ?? null);
            $faxLog->fax_uri = $this->stringOrNull($this->data['fax_uri'] ?? null);
            $faxLog->fax_duration = $this->numericOrNull($this->data['duration'] ?? null);
            $faxLog->fax_date = now();
            $faxLog->fax_epoch = $this->numericOrNull($this->timestamp) ?? now()->timestamp;
            $faxLog->save();

            if (
                ($this->data['fax_success'] ?? null) === '1'
                && $faxFilePath
                && $faxUuid
            ) {
                $faxFile = FaxFiles::find($faxLogUuid);

                if (!$faxFile) {
                    $faxFile = new FaxFiles();
                    $faxFile->fax_file_uuid = $faxLogUuid;
                }

                $faxFile->fax_uuid = $faxUuid;
                $faxFile->fax_mode = $callDirection === 'outbound' ? 'tx' : 'rx';
                $faxFile->fax_destination = $callDirection === 'outbound'
                    ? ($this->stringOrNull($this->data['fax_destination_number'] ?? null)
                        ?? $this->stringOrNull($this->data['sip_to_user'] ?? null))
                    : ($this->stringOrNull($this->data['caller_destination'] ?? null)
                        ?? $this->stringOrNull($this->data['sip_to_user'] ?? null)
                        ?? $this->stringOrNull($fax->fax_extension ?? null));
                $faxFile->fax_file_type = pathinfo($faxFilePath, PATHINFO_EXTENSION) ?: 'tif';
                $faxFile->fax_file_path = $faxFilePath;
                $faxFile->fax_caller_id_name = $this->stringOrNull($this->data['caller_id_name'] ?? null);
                $faxFile->fax_caller_id_number = $this->stringOrNull($this->data['caller_id_number'] ?? null);
                $faxFile->fax_date = now();
                $faxFile->fax_epoch = $this->numericOrNull($this->timestamp) ?? now()->timestamp;
                $faxFile->domain_uuid = $domainUuid;
                $faxFile->save();
            }
        });

        $pdfPath = $this->convertTiffToPdf($faxFilePath);

        if (
            $this->event === 'fax.received'
            && ($this->data['fax_success'] ?? null) === '1'
            && $faxUuid
        ) {
            $fax = Faxes::query()
                ->where('domain_uuid', $domainUuid)
                ->where('fax_uuid', $faxUuid)
                ->first();

            if ($fax) {
                $this->sendFaxEmail(
                    faxLogUuid: $faxLogUuid,
                    fax: $fax,
                    pdfPath: $pdfPath,
                    tiffPath: $faxFilePath
                );
            }
        }

        Log::info('[Webhook] Fax event processed', [
            'event' => $this->event,
            'uuid' => $faxLogUuid,
            'fax_uuid' => $faxUuid,
            'pdf_path' => $pdfPath,
            'pages_transferred' => $this->data['fax_document_transferred_pages'] ?? null,
            'pages_total' => $this->data['fax_document_total_pages'] ?? null,
        ]);
    }

    private function convertTiffToPdf(?string $tiffPath): ?string
    {
        $tiffPath = $this->stringOrNull($tiffPath);

        if (!$tiffPath || !is_file($tiffPath)) {
            return null;
        }

        $pdfPath = pathinfo($tiffPath, PATHINFO_DIRNAME)
            . DIRECTORY_SEPARATOR
            . pathinfo($tiffPath, PATHINFO_FILENAME)
            . '.pdf';

        if (is_file($pdfPath)) {
            return $pdfPath;
        }

        try {
            $process = new Process([
                'tiff2pdf',
                '-o',
                $pdfPath,
                $tiffPath,
            ]);

            $process->setTimeout(120);
            $process->run();

            if (!$process->isSuccessful() || !is_file($pdfPath)) {
                Log::warning('[Webhook] TIFF to PDF conversion failed', [
                    'tiff' => $tiffPath,
                    'pdf' => $pdfPath,
                    'output' => $process->getErrorOutput() ?: $process->getOutput(),
                ]);

                return null;
            }

            return $pdfPath;
        } catch (Throwable $e) {
            Log::warning('[Webhook] TIFF to PDF conversion exception', [
                'tiff' => $tiffPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function sendFaxEmail(
        string $faxLogUuid,
        Faxes $fax,
        ?string $pdfPath,
        ?string $tiffPath
    ): void {
        $recipient = $this->stringOrNull($fax->fax_email ?? null);

        if (!$recipient) {
            return;
        }

        $lockKey = 'fax-email-sent:' . $faxLogUuid;

        if (!Cache::add($lockKey, true, now()->addDay())) {
            return;
        }

        $attachmentPath = $pdfPath && is_file($pdfPath) ? $pdfPath : $tiffPath;

        if (!$attachmentPath || !is_file($attachmentPath)) {
            Log::warning('[Webhook] No fax attachment available for email', [
                'uuid' => $faxLogUuid,
                'pdf' => $pdfPath,
                'tiff' => $tiffPath,
            ]);
            return;
        }

        $baseName = pathinfo((string) $tiffPath, PATHINFO_FILENAME);
        $isPdf = $pdfPath && is_file($pdfPath);

        $attributes = [
            'domain_name' => $this->stringOrNull($this->data['domain_name'] ?? null),
            'fax_destination' => $this->stringOrNull($this->data['fax_destination'] ?? null)
                ?? $this->stringOrNull($this->data['caller_destination'] ?? null)
                ?? $this->stringOrNull($this->data['sip_to_user'] ?? null)
                ?? $this->stringOrNull($fax->fax_extension ?? null),
            'fax_extension' => $this->stringOrNull($fax->fax_extension ?? null),
            'caller_id_name' => $this->stringOrNull($this->data['caller_id_name'] ?? null),
            'caller_id_number' => $this->stringOrNull($this->data['caller_id_number'] ?? null),
            'fax_pages' => $this->stringOrNull($this->data['fax_document_total_pages'] ?? null) ?? '0',
            'fax_result_text' => $this->stringOrNull($this->data['fax_result_text'] ?? null) ?? 'OK',
            'attachment_path' => $attachmentPath,
            'attachment_name' => $baseName . ($isPdf ? '.pdf' : '.tif'),
            'attachment_mime' => $isPdf ? 'application/pdf' : 'image/tiff',
        ];

        try {
            Mail::to($recipient)->send(new \App\Mail\FaxReceived($attributes));
        } catch (Throwable $e) {
            Cache::forget($lockKey);

            Log::error('[Webhook] Fax email send failed', [
                'uuid' => $faxLogUuid,
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function numericOrNull($value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? $value + 0 : null;
    }
}
