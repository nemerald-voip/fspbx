<?php

namespace App\Jobs;

use App\Mail\FaxFailed;
use App\Mail\FaxSent;
use App\Models\OutboundFax;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Sends the result email (success or failure) for an outbound fax and
 * marks the row as notified. Idempotent — re-runs detect notify_sent_at
 * and skip.
 *
 * Reads the latest v_fax_logs row for the fax to populate the email
 * template with wire-level details (pages transferred, duration, etc.).
 */
class SendFaxNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $timeout = 60;
    public $backoff = 30;

    public function __construct(public string $outboundFaxUuid)
    {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        Redis::throttle('fax')->allow(2)->every(1)->then(function () {
            $fax = OutboundFax::with(['logs' => function ($q) {
                $q->orderByDesc('fax_date')->limit(1);
            }])->find($this->outboundFaxUuid);

            if (!$fax) {
                fax_webhook_debug('SendFaxNotificationJob: row not found, dropping', [
                    'outbound_fax_uuid' => $this->outboundFaxUuid,
                ]);
                return;
            }

            // Idempotency: already notified once.
            if ($fax->notify_sent_at !== null) {
                fax_webhook_debug('SendFaxNotificationJob: already notified, skipping', [
                    'outbound_fax_uuid' => $fax->outbound_fax_uuid,
                    'notify_sent_at'    => $fax->notify_sent_at?->toIso8601String(),
                ]);
                return;
            }

            // Skip if no recipient (e.g. UI form with send_confirmation off).
            if (empty($fax->email)) {
                fax_webhook_debug('SendFaxNotificationJob: no email recipient, marking notified and skipping', [
                    'outbound_fax_uuid' => $fax->outbound_fax_uuid,
                ]);
                $fax->update(['notify_sent_at' => now()]);
                return;
            }

            // Only ever send for terminal states.
            if (!in_array($fax->status, ['sent', 'failed'], true)) {
                fax_webhook_debug('SendFaxNotificationJob: row not terminal, deferring', [
                    'outbound_fax_uuid' => $fax->outbound_fax_uuid,
                    'status'            => $fax->status,
                ]);
                return;
            }

            $attributes = $this->buildAttributes($fax);

            $mailable = $fax->status === 'sent'
                ? new FaxSent($attributes)
                : new FaxFailed($attributes);

            try {
                Mail::to($fax->email)->send($mailable);
                $fax->update(['notify_sent_at' => now()]);

                fax_webhook_debug('SendFaxNotificationJob: notification sent', [
                    'outbound_fax_uuid' => $fax->outbound_fax_uuid,
                    'status'            => $fax->status,
                    'recipient'         => $fax->email,
                ]);
            } catch (Throwable $e) {
                logger('SendFaxNotificationJob: send failed for ' . $fax->outbound_fax_uuid . ': ' . $e->getMessage());
                throw $e; // surface to Horizon for retry
            }
        }, function () {
            $this->release(5);
        });
    }

    /**
     * Build the attributes array fed to the Mailable / Blade template.
     * Pulls wire-level detail from the latest v_fax_logs row when available.
     */
    private function buildAttributes(OutboundFax $fax): array
    {
        $log = $fax->logs->first(); // single latest log thanks to with() above

        $attributes = [
            'fax_destination'        => $fax->destination,
            'fax_source'             => $fax->source,
            'from'                   => $fax->email,
        ];

        if ($log) {
            $attributes['fax_pages']       = (string) ($log->fax_document_transferred_pages ?? '');
            $attributes['fax_total_pages'] = (string) ($log->fax_document_total_pages ?? '');
            $attributes['fax_date']        = optional($log->fax_date)->format('Y-m-d H:i');

            $duration = (int) ($log->fax_duration ?? 0);
            if ($duration > 0) {
                $attributes['fax_duration']           = (string) $duration;
                $attributes['fax_duration_formatted'] = sprintf(
                    '%02dh %02dm %02ds',
                    intdiv($duration, 3600),
                    intdiv($duration, 60) % 60,
                    $duration % 60
                );
            }

            $attributes['fax_result_code'] = $log->fax_result_code !== null ? (string) $log->fax_result_code : '';
            $attributes['fax_result_text'] = (string) ($log->fax_result_text ?? '');
        }

        // Failure email expects $attributes['email_message'] for the body line.
        if ($fax->status === 'failed') {
            $attributes['email_message'] = $this->failureMessage($attributes);
        }

        // Attach the PDF rendition of the sent fax when available.
        $pdfPath = preg_replace('/\.tif$/i', '.pdf', $fax->file_path);
        if ($pdfPath && is_file($pdfPath)) {
            $attributes['attachment_path'] = $pdfPath;
            $attributes['attachment_name'] = basename($pdfPath);
            $attributes['attachment_mime'] = 'application/pdf';
        }

        return $attributes;
    }

    private function failureMessage(array $attributes): string
    {
        $reason = trim($attributes['fax_result_text'] ?? '');

        if ($reason === '' || strtoupper($reason) === 'FS_NOT_SET') {
            return 'We were unable to deliver your fax after the maximum number of attempts.';
        }

        return 'We were unable to deliver your fax after the maximum number of attempts. Last reported reason: ' . $reason;
    }
}
