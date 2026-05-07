<?php

namespace App\Jobs;

use App\Models\FaxLogs;
use App\Models\FaxFiles;
use App\Models\OutboundFax;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Handles a fax.sent webhook from FreeSWITCH (the Lua hangup hook posting
 * the outcome of one outbound attempt).
 *
 * Responsibilities:
 *   1. Anti-stale check — payload's outbound_fax_attempt_uuid must match the
 *      row's current_attempt_uuid. Late webhooks from orphaned earlier
 *      attempts get dropped here.
 *   2. Write a v_fax_logs row capturing the wire-level outcome of this
 *      attempt (one row per attempt — full troubleshooting history).
 *   3. Update the outbound_faxes row's status based on the outcome.
 *   4. Dispatch the next step:
 *        success → SendFaxNotificationJob
 *        retry   → SendFaxJob (delayed; backoff grows with each retry)
 *        exhausted → SendFaxNotificationJob with status=failed
 */
class HandleFaxTxEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = 10;

    /**
     * Base delay between fax attempts. Each retry adds RETRY_BACKOFF_STEP
     * seconds to space out attempts further as we hit the harder cases.
     */
    private const RETRY_BASE_SECONDS = 60;
    private const RETRY_BACKOFF_STEP = 30;

    public function __construct(public array $data)
    {
        $this->onQueue('faxes');
    }

    public function handle(): void
    {
        $outboundFaxUuid = $this->data['outbound_fax_uuid'] ?? null;
        $attemptUuid     = $this->data['outbound_fax_attempt_uuid'] ?? null;

        if (!$outboundFaxUuid) {
            fax_webhook_debug('HandleFaxTxEventJob: missing outbound_fax_uuid, dropping', $this->data);
            return;
        }

        $fax = OutboundFax::find($outboundFaxUuid);
        if (!$fax) {
            fax_webhook_debug('HandleFaxTxEventJob: outbound_fax row not found', [
                'outbound_fax_uuid' => $outboundFaxUuid,
            ]);
            return;
        }

        // Anti-stale: ignore webhooks from attempts the row has moved past.
        // (The reaper may have superseded an orphaned earlier attempt; that
        //  earlier call eventually hung up and posted a stale webhook.)
        if ($attemptUuid && $fax->current_attempt_uuid && $attemptUuid !== $fax->current_attempt_uuid) {
            fax_webhook_debug('HandleFaxTxEventJob: stale attempt webhook, ignoring', [
                'outbound_fax_uuid'        => $outboundFaxUuid,
                'webhook_attempt_uuid'     => $attemptUuid,
                'row_current_attempt_uuid' => $fax->current_attempt_uuid,
            ]);
            return;
        }

        // Already at a terminal state — duplicate webhook, skip.
        if ($fax->isTerminal()) {
            fax_webhook_debug('HandleFaxTxEventJob: row already terminal, ignoring', [
                'outbound_fax_uuid' => $outboundFaxUuid,
                'status'            => $fax->status,
            ]);
            return;
        }

        // Always write the attempt log first — the troubleshooting history
        // needs to capture every attempt regardless of next decision.
        $log = $this->writeFaxLog($fax);

        // Decide next step from the wire outcome.
        $faxSuccess  = (string) ($this->data['fax_success'] ?? '0');
        $isBusy      = $this->isBusyResult($this->data);
        $retriesLeft = $fax->retry_count < $fax->retry_limit;

        if ($faxSuccess === '1') {
            $this->writeFaxFile($fax, $log);

            fax_webhook_debug('HandleFaxTxEventJob: fax succeeded', [
                'outbound_fax_uuid' => $outboundFaxUuid,
                'pages_transferred' => $this->data['fax_document_transferred_pages'] ?? null,
                'pages_total'       => $this->data['fax_document_total_pages'] ?? null,
            ]);

            $fax->update(['status' => 'sent']);
            SendFaxNotificationJob::dispatch($outboundFaxUuid);
            return;
        }

        if (!$retriesLeft) {
            fax_webhook_debug('HandleFaxTxEventJob: retries exhausted, marking failed', [
                'outbound_fax_uuid' => $outboundFaxUuid,
                'retry_count'       => $fax->retry_count,
                'retry_limit'       => $fax->retry_limit,
                'fax_result_code'   => $this->data['fax_result_code'] ?? null,
                'fax_result_text'   => $this->data['fax_result_text'] ?? null,
            ]);

            $fax->update(['status' => 'failed']);
            SendFaxNotificationJob::dispatch($outboundFaxUuid);
            return;
        }

        // Schedule the retry. Backoff grows with each subsequent attempt
        // so we don't pummel a misbehaving receiver.
        $delaySeconds = self::RETRY_BASE_SECONDS
            + (self::RETRY_BACKOFF_STEP * max(0, (int) $fax->retry_count - 1));

        // Mark the row pending — busy is tracked separately so the dashboard
        // can show "line was busy" vs. a generic transmission failure.
        $fax->update([
            'status'   => $isBusy ? 'busy' : 'trying',
            'retry_at' => now(),
        ]);

        fax_webhook_debug('HandleFaxTxEventJob: scheduling retry', [
            'outbound_fax_uuid' => $outboundFaxUuid,
            'fax_attempt'       => (int) $fax->retry_count,
            'next_attempt'      => (int) $fax->retry_count + 1,
            'delay_seconds'     => $delaySeconds,
            'is_busy'           => $isBusy,
            'fax_result_code'   => $this->data['fax_result_code'] ?? null,
            'fax_result_text'   => $this->data['fax_result_text'] ?? null,
        ]);

        SendFaxJob::dispatch($outboundFaxUuid)->delay(now()->addSeconds($delaySeconds));
    }

    /**
     * Insert a v_fax_logs row capturing this attempt's wire-level outcome.
     * One row per attempt — joining v_fax_logs by outbound_fax_uuid gives the
     * full history of what happened across retries.
     */
    private function writeFaxLog(OutboundFax $fax): FaxLogs
    {
        $log = new FaxLogs();
        $log->fax_log_uuid                   = (string) Str::uuid();
        $log->domain_uuid                    = $fax->domain_uuid;
        $log->fax_uuid                       = $fax->fax_uuid;
        $log->outbound_fax_uuid              = $fax->outbound_fax_uuid;
        $log->outbound_fax_attempt_uuid      = $fax->current_attempt_uuid;
        // Use the row's authoritative values rather than channel headers.
        // On the outbound originate leg FreeSWITCH reports caller_id_number
        // as the destination — not what we want. The row has what we set.
        $log->source                         = $fax->source;
        $log->destination                    = $fax->destination;
        $log->fax_success                    = $this->stringOrNull($this->data['fax_success'] ?? null);
        $log->fax_result_code                = $this->numericOrNull($this->data['fax_result_code'] ?? null);
        $log->fax_result_text                = $this->stringOrNull($this->data['fax_result_text'] ?? null);
        $log->fax_file                       = $this->stringOrNull($this->data['fax_file'] ?? $fax->file_path);
        $log->fax_ecm_used                   = $this->stringOrNull($this->data['fax_ecm_used'] ?? null);
        $log->fax_local_station_id           = $this->stringOrNull($this->data['fax_local_station_id'] ?? null);
        $log->fax_document_transferred_pages = $this->numericOrNull($this->data['fax_document_transferred_pages'] ?? null);
        $log->fax_document_total_pages       = $this->numericOrNull($this->data['fax_document_total_pages'] ?? null);
        $log->fax_image_resolution           = $this->stringOrNull($this->data['fax_image_resolution'] ?? null);
        $log->fax_image_size                 = $this->numericOrNull($this->data['fax_image_size'] ?? null);
        $log->fax_bad_rows                   = $this->numericOrNull($this->data['fax_bad_rows'] ?? null);
        $log->fax_transfer_rate              = $this->numericOrNull($this->data['fax_transfer_rate'] ?? null);
        $log->fax_uri                        = $this->stringOrNull($this->data['fax_uri'] ?? null);
        $log->fax_duration                   = $this->numericOrNull($this->data['billsec'] ?? $this->data['duration'] ?? null);
        $log->fax_date                       = now();
        $log->fax_epoch                      = $this->numericOrNull($this->data['end_epoch'] ?? null) ?? time();
        $log->save();

        fax_webhook_debug('HandleFaxTxEventJob: wrote new fax log', [
            'fax_log_uuid'      => $log->fax_log_uuid,
            'outbound_fax_uuid' => $fax->outbound_fax_uuid,
            'fax_attempt'       => (int) $fax->retry_count,
            'fax_success'       => $log->fax_success,
            'fax_result_code'   => $log->fax_result_code,
        ]);

        return $log;
    }

    private function writeFaxFile(OutboundFax $fax, FaxLogs $log): ?FaxFiles
    {
        if (!$log->fax_file || !$log->fax_uuid) {
            fax_webhook_debug('HandleFaxTxEventJob: successful fax has no file path, skipping fax file row', [
                'outbound_fax_uuid' => $fax->outbound_fax_uuid,
                'fax_log_uuid'      => $log->fax_log_uuid,
                'fax_file'          => $log->fax_file,
            ]);

            return null;
        }

        $file = FaxFiles::find($log->fax_log_uuid);

        if (!$file) {
            $file = new FaxFiles();
            $file->fax_file_uuid = $log->fax_log_uuid;
        }

        $file->domain_uuid = $log->domain_uuid;
        $file->fax_uuid = $log->fax_uuid;
        $file->fax_mode = 'tx';
        $file->fax_destination = $log->destination;
        $file->fax_file_type = pathinfo($log->fax_file, PATHINFO_EXTENSION) ?: 'tif';
        $file->fax_file_path = $log->fax_file;
        $file->fax_caller_id_name = $fax->source_name;
        $file->fax_caller_id_number = $log->source;
        $file->fax_date = $log->fax_date;
        $file->fax_epoch = $log->fax_epoch;
        $file->save();

        fax_webhook_debug('HandleFaxTxEventJob: wrote sent fax file row', [
            'fax_file_uuid'    => $file->fax_file_uuid,
            'outbound_fax_uuid' => $fax->outbound_fax_uuid,
            'fax_file'         => $file->fax_file_path,
        ]);

        return $file;
    }

    /**
     * Detect "busy" using call-layer signals — Q.850 cause 17 (USER_BUSY) is
     * the canonical indicator. Also accept the string forms of hangup_cause
     * variables in case Q.850 wasn't populated. (The legacy script's check
     * on fax_result_code 2/3 was incorrect — those are SpanDSP T.30 protocol
     * errors, not call-level busy signals.)
     */
    private function isBusyResult(array $data): bool
    {
        $q850 = (string) ($data['hangup_cause_q850'] ?? '');
        if ($q850 === '17') {
            return true;
        }

        foreach (['hangup_cause', 'bridge_hangup_cause'] as $field) {
            $value = strtoupper((string) ($data[$field] ?? ''));
            if ($value === 'USER_BUSY' || $value === 'BUSY') {
                return true;
            }
        }

        return false;
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (string) $value;
    }

    private function numericOrNull($value)
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return $value + 0; // coerce to int/float
    }
}
