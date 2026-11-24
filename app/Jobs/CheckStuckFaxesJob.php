<?php

namespace App\Jobs;

use App\Models\FaxLogs;
use App\Models\OutboundFax;
use App\Services\Fax\FaxChannelGuard;
use App\Services\Fax\FaxChannelProbe;
use App\Services\FreeswitchEslService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Catches outbound faxes that got stuck in a non-terminal state because the
 * normal event-driven path failed (Lua hook never ran, webhook was lost,
 * Redis ate a delayed job, server died mid-send, etc.).
 *
 * Runs every 5 minutes on each server. Both servers run independently — the
 * atomic state transitions deduplicate any overlap.
 *
 * Phases:
 *   A — status='sending', call_uuid set, retry_at older than 5 min.
 *         Match live txfax channels by outbound_fax_uuid or by the row
 *         call_uuid against the channel uuid / origination_uuid /
 *         originating_leg_uuid / call_uuid. A match is not redispatched:
 *         fax_success=1 is marked sent, anything else is left for phase C.
 *         Only when the channel list was read, nothing matches, and
 *         uuid_exists says the row uuid is gone do we redispatch. A failed
 *         channel list leaves the row alone.
 *
 *   B — status='sending', call_uuid IS NULL (originate failed before saving)
 *         and retry_at older than 5 min: revert to 'trying' and redispatch.
 *
 *   C — pages-aware hard timeout (safety net). 90 s/page budget with a 15-min
 *         floor. When we don't know the page count, fall back to a flat 6 h.
 *         fax_success=1 on any matched channel is sent, never failed. Otherwise
 *         hang up every matched channel and the row uuid, then trying or failed.
 *         A failed channel list does not mark the row.
 *
 *   D — status in waiting/trying/busy and retry_at past the retry interval +
 *         a small buffer: the dispatch was lost (Redis flush, primary died).
 *         Redispatch SendFaxJob; the job's atomic claim deduplicates if
 *         another worker also picks it up.
 */
class CheckStuckFaxesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 120;

    public const SUCCESS_RESPONSE = 'CheckStuckFaxesJob: FreeSWITCH fax_success=1 on a live channel; not a failure';

    public const TIMEOUT_RESPONSE = 'CheckStuckFaxesJob: exceeded per-page budget without webhook; killed stuck FreeSWITCH call';

    /** Don't probe ESL on faxes younger than this — they're probably fine. */
    private const SENDING_GRACE_SECONDS = 300; // 5 min

    /** Per-page budget for Phase C. 90 s/page is conservative (real ~30 s). */
    private const SECONDS_PER_PAGE = 90;

    /** Phase C floor — even a 1-pager waits at least this long. */
    private const PHASE_C_MIN_SECONDS = 900; // 15 min

    /** Phase C fallback when total_pages is unknown. */
    private const PHASE_C_UNKNOWN_PAGES_FALLBACK = 21600; // 6 h

    /** Phase D — minimum retry interval (matches HandleFaxTxEventJob base) plus buffer. */
    private const PHASE_D_GRACE_SECONDS = 300; // 5 min buffer past base retry interval

    public function __construct()
    {
        $this->onQueue('faxes');
    }

    public function handle(FreeswitchEslService $esl): void
    {
        fax_webhook_debug('CheckStuckFaxesJob start');

        $channels = $this->loadChannels($esl);

        try {
            $this->phaseA($esl, $channels);
            $this->phaseB();
            $this->phaseC($esl, $channels);
            $this->phaseD();
        } finally {
            try {
                $esl->disconnect();
            } catch (Throwable $e) {
                fax_webhook_debug('CheckStuckFaxesJob: disconnect failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        fax_webhook_debug('CheckStuckFaxesJob done');
    }

    /**
     * @return list<array<string, mixed>>|null null when the channel list could not be read
     */
    private function loadChannels(FreeswitchEslService $esl): ?array
    {
        try {
            if (! $esl->isConnected()) {
                $esl->reconnect();
            }

            return (new FaxChannelProbe($esl))->txfaxChannels();
        } catch (Throwable $e) {
            fax_webhook_debug('CheckStuckFaxesJob: channel list unavailable', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * status='sending', call_uuid present — ask FS whether the call is alive.
     *
     * @param  list<array<string, mixed>>|null  $channels
     */
    private function phaseA(FreeswitchEslService $esl, ?array $channels): void
    {
        if (! is_array($channels)) {
            // uuid_exists on the row uuid is false after loopback bowout while
            // the gateway is still in txfax. Do not redispatch without a list.
            fax_webhook_debug('CheckStuckFaxesJob phaseA: channel list unavailable, not redistributing');

            return;
        }

        $rows = OutboundFax::where('status', 'sending')
            ->whereNotNull('call_uuid')
            ->where('retry_at', '<', now()->subSeconds(self::SENDING_GRACE_SECONDS))
            ->limit(50)
            ->select([
                'outbound_fax_uuid',
                'domain_uuid',
                'fax_uuid',
                'source',
                'destination',
                'file_path',
                'retry_count',
                'retry_limit',
                'retry_at',
                'call_uuid',
                'current_attempt_uuid',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows as $fax) {
            $matched = $this->matchedChannels($channels, $fax);
            $callExists = null;
            if ($matched === []) {
                $callExists = $this->rowCallExists($esl, $fax);
            }

            $decision = FaxChannelGuard::phaseADecision(true, $matched, $callExists);
            if ($decision === 'sent') {
                $this->markSentFromLiveChannel($esl, $fax, $matched);

                continue;
            }

            if ($decision !== 'redispatch') {
                continue;
            }

            fax_webhook_debug('CheckStuckFaxesJob phaseA: call gone, redispatching', [
                'outbound_fax_uuid' => $fax->outbound_fax_uuid,
                'call_uuid' => $fax->call_uuid,
            ]);

            $reset = OutboundFax::where('outbound_fax_uuid', $fax->outbound_fax_uuid)
                ->where('status', 'sending')
                ->update(['status' => 'trying']);

            if ($reset > 0) {
                $this->writeSyntheticFaxLog(
                    fax: $fax,
                    faxResultText: 'FreeSWITCH call ended before fax webhook was received',
                );

                SendFaxJob::dispatch($fax->outbound_fax_uuid);
            }
        }
    }

    /**
     * status='sending' but no call_uuid — originate didn't even get accepted
     * (or the worker died before persisting). Revert and redispatch.
     */
    private function phaseB(): void
    {
        $rows = OutboundFax::where('status', 'sending')
            ->whereNull('call_uuid')
            ->where('retry_at', '<', now()->subSeconds(self::SENDING_GRACE_SECONDS))
            ->limit(50)
            ->get([
                'outbound_fax_uuid',
                'domain_uuid',
                'fax_uuid',
                'source',
                'destination',
                'file_path',
                'retry_count',
                'retry_limit',
                'retry_at',
                'current_attempt_uuid',
            ]);

        foreach ($rows as $fax) {
            $reset = OutboundFax::where('outbound_fax_uuid', $fax->outbound_fax_uuid)
                ->where('status', 'sending')
                ->whereNull('call_uuid')
                ->update(['status' => 'trying']);

            if ($reset > 0) {
                $this->writeSyntheticFaxLog(
                    fax: $fax,
                    faxResultText: 'Fax attempt stuck without a FreeSWITCH call UUID',
                );

                fax_webhook_debug('CheckStuckFaxesJob phaseB: redispatching (no call_uuid)', [
                    'outbound_fax_uuid' => $fax->outbound_fax_uuid,
                ]);
                SendFaxJob::dispatch($fax->outbound_fax_uuid);
            }
        }
    }

    /**
     * Pages-aware hard timeout. If a row has been 'sending' longer than its
     * per-page budget, the call has been in 'sending' way past any legitimate
     * transmission time. A live fax_success=1 is sent instead of failed.
     *
     * @param  list<array<string, mixed>>|null  $channels
     */
    private function phaseC(FreeswitchEslService $esl, ?array $channels): void
    {
        if (! is_array($channels)) {
            // Without the channel list a past-budget row can still be a live
            // gateway whose fax_success=1 we did not read. Leave it sending.
            fax_webhook_debug('CheckStuckFaxesJob phaseC: channel list unavailable, leaving sending rows alone');

            return;
        }

        // Pull candidates older than the absolute floor; budget check is
        // per-row in PHP since the threshold depends on total_pages.
        $rows = OutboundFax::where('status', 'sending')
            ->where('retry_at', '<', now()->subSeconds(self::PHASE_C_MIN_SECONDS))
            ->limit(100)
            ->get([
                'outbound_fax_uuid',
                'domain_uuid',
                'fax_uuid',
                'source',
                'destination',
                'file_path',
                'retry_count',
                'retry_limit',
                'retry_at',
                'call_uuid',
                'current_attempt_uuid',
                'total_pages',
            ]);

        foreach ($rows as $fax) {
            $matched = $this->matchedChannels($channels, $fax);
            if (FaxChannelGuard::anySucceeded($matched)) {
                $this->markSentFromLiveChannel($esl, $fax, $matched);

                continue;
            }

            $budget = $fax->total_pages
                ? max(self::PHASE_C_MIN_SECONDS, $fax->total_pages * self::SECONDS_PER_PAGE)
                : self::PHASE_C_UNKNOWN_PAGES_FALLBACK;

            $sendingFor = $fax->retry_at->diffInSeconds(now(), false);
            if ($sendingFor < $budget) {
                continue;
            }

            if (! $this->ensureConnected($esl)) {
                continue;
            }

            $retriesLeft = $fax->retry_count < $fax->retry_limit;
            $status = FaxChannelGuard::terminalStatus(false, $retriesLeft);
            $uuids = FaxChannelGuard::killUuids($matched, $fax->call_uuid);

            $marked = OutboundFax::where('outbound_fax_uuid', $fax->outbound_fax_uuid)
                ->where('status', 'sending')
                ->update([
                    'status' => $status,
                    'response' => self::TIMEOUT_RESPONSE,
                ]);

            try {
                if ($marked > 0) {
                    $this->writeSyntheticFaxLog(
                        fax: $fax,
                        faxResultText: 'Exceeded fax send timeout without fax webhook; killed stuck FreeSWITCH call',
                    );

                    fax_webhook_debug('CheckStuckFaxesJob phaseC: hard-timeout, killed stuck call', [
                        'outbound_fax_uuid' => $fax->outbound_fax_uuid,
                        'call_uuid' => $fax->call_uuid,
                        'matched_uuids' => $uuids,
                        'sending_for_secs' => $sendingFor,
                        'budget_secs' => $budget,
                        'total_pages' => $fax->total_pages,
                        'retries_left' => $retriesLeft,
                    ]);

                    if ($retriesLeft) {
                        SendFaxJob::dispatch($fax->outbound_fax_uuid);
                    } else {
                        SendFaxNotificationJob::dispatch($fax->outbound_fax_uuid);
                    }
                }
            } finally {
                // The row is terminal before the hangup, so a late webhook cannot
                // rewrite a success we already decided was a timeout.
                $this->hangupChannels($esl, $uuids);
            }
        }
    }

    /**
     * Lost dispatches — waiting/trying/busy rows whose retry window passed
     * without a SendFaxJob picking them up. Caused by Redis flush, primary
     * dying with delayed jobs in its queue, etc.
     */
    private function phaseD(): void
    {
        $rows = OutboundFax::whereIn('status', ['waiting', 'trying', 'busy'])
            ->where(function ($q) {
                $q->whereNull('retry_at')
                    ->orWhere('retry_at', '<', now()->subSeconds(self::PHASE_D_GRACE_SECONDS));
            })
            ->limit(100)
            ->get(['outbound_fax_uuid']);

        foreach ($rows as $fax) {
            fax_webhook_debug('CheckStuckFaxesJob phaseD: redispatching pending row', [
                'outbound_fax_uuid' => $fax->outbound_fax_uuid,
            ]);
            SendFaxJob::dispatch($fax->outbound_fax_uuid);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $channels
     * @return list<array<string, mixed>>
     */
    private function matchedChannels(array $channels, OutboundFax $fax): array
    {
        $matched = [];
        foreach ($channels as $channel) {
            if (FaxChannelGuard::matches($channel, (string) $fax->outbound_fax_uuid, $fax->call_uuid)) {
                $matched[] = $channel;
            }
        }

        return $matched;
    }

    private function rowCallExists(FreeswitchEslService $esl, OutboundFax $fax): ?bool
    {
        if (! FaxChannelGuard::isUuid($fax->call_uuid)) {
            return null;
        }

        try {
            if (! $esl->isConnected()) {
                $esl->reconnect();
            }

            return FaxChannelGuard::uuidExists(
                $esl->executeCommand('uuid_exists '.$fax->call_uuid, false)
            );
        } catch (Throwable $e) {
            fax_webhook_debug('CheckStuckFaxesJob phaseA: uuid_exists failed', [
                'outbound_fax_uuid' => $fax->outbound_fax_uuid,
                'call_uuid' => $fax->call_uuid,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $matched
     */
    private function markSentFromLiveChannel(FreeswitchEslService $esl, OutboundFax $fax, array $matched): void
    {
        $success = [];
        foreach ($matched as $channel) {
            if (FaxChannelGuard::norm($channel['fax_success'] ?? '') === '1') {
                $success = $channel;
                break;
            }
        }

        $marked = OutboundFax::where('outbound_fax_uuid', $fax->outbound_fax_uuid)
            ->where('status', 'sending')
            ->update([
                'status' => 'sent',
                'response' => self::SUCCESS_RESPONSE,
            ]);

        $uuids = FaxChannelGuard::killUuids($matched, $fax->call_uuid);
        try {
            if ($marked > 0) {
                $this->writeSuccessFaxLog($fax, $success);
                SendFaxNotificationJob::dispatch($fax->outbound_fax_uuid);
                fax_webhook_debug('CheckStuckFaxesJob: fax_success=1 on a live channel', [
                    'outbound_fax_uuid' => $fax->outbound_fax_uuid,
                    'call_uuid' => $fax->call_uuid,
                    'matched_uuids' => $uuids,
                ]);
            }
        } finally {
            $this->hangupChannels($esl, $uuids);
        }
    }

    /**
     * Record attempts recovered by the reaper when FreeSWITCH never posted the
     * normal fax hangup webhook. One log row per current_attempt_uuid keeps the
     * retry history readable without duplicating late webhook rows.
     */
    private function writeSyntheticFaxLog(OutboundFax $fax, string $faxResultText): ?FaxLogs
    {
        if ($fax->current_attempt_uuid) {
            $exists = FaxLogs::query()
                ->where('outbound_fax_attempt_uuid', $fax->current_attempt_uuid)
                ->exists();

            if ($exists) {
                return null;
            }
        }

        $log = new FaxLogs();
        $log->fax_log_uuid = (string) Str::uuid();
        $log->domain_uuid = $fax->domain_uuid;
        $log->fax_uuid = $fax->fax_uuid;
        $log->outbound_fax_uuid = $fax->outbound_fax_uuid;
        $log->outbound_fax_attempt_uuid = $fax->current_attempt_uuid;
        $log->source = $fax->source;
        $log->destination = $fax->destination;
        $log->fax_success = '0';
        $log->fax_result_text = $faxResultText;
        $log->fax_file = $fax->file_path;
        $log->fax_retry_attempts = $fax->retry_count;
        $log->fax_retry_limit = $fax->retry_limit;
        $log->fax_duration = 0;
        $faxEpoch = time();
        $log->fax_date = Carbon::createFromTimestamp($faxEpoch, 'UTC');
        $log->fax_epoch = $faxEpoch;
        $log->save();

        fax_webhook_debug('CheckStuckFaxesJob: wrote synthetic v_fax_logs row', [
            'fax_log_uuid' => $log->fax_log_uuid,
            'outbound_fax_uuid' => $fax->outbound_fax_uuid,
            'fax_attempt' => (int) $fax->retry_count,
            'fax_result_text' => $faxResultText,
        ]);

        return $log;
    }

    /**
     * A success log is written even when this attempt already has a failure
     * row. SendFaxNotificationJob prefers fax_success=1 on the current attempt.
     *
     * @param  array<string, mixed>  $channel
     */
    private function writeSuccessFaxLog(OutboundFax $fax, array $channel): FaxLogs
    {
        $log = new FaxLogs();
        $log->fax_log_uuid = (string) Str::uuid();
        $log->domain_uuid = $fax->domain_uuid;
        $log->fax_uuid = $fax->fax_uuid;
        $log->outbound_fax_uuid = $fax->outbound_fax_uuid;
        $log->outbound_fax_attempt_uuid = $fax->current_attempt_uuid;
        $log->source = $fax->source;
        $log->destination = $fax->destination;
        $log->fax_success = '1';
        $log->fax_result_text = 'FreeSWITCH reported fax_success=1';
        $log->fax_file = $fax->file_path;
        $log->fax_retry_attempts = $fax->retry_count;
        $log->fax_retry_limit = $fax->retry_limit;
        $transferred = $this->pageCount($channel['transferred_pages'] ?? null);
        $total = $this->pageCount($channel['total_pages'] ?? null);
        if ($transferred !== null) {
            $log->fax_document_transferred_pages = $transferred;
        }
        if ($total !== null) {
            $log->fax_document_total_pages = $total;
        }
        $log->fax_duration = 0;
        $faxEpoch = time();
        $log->fax_date = Carbon::createFromTimestamp($faxEpoch, 'UTC');
        $log->fax_epoch = $faxEpoch;
        $log->save();

        fax_webhook_debug('CheckStuckFaxesJob: wrote fax_success=1 v_fax_logs row', [
            'fax_log_uuid' => $log->fax_log_uuid,
            'outbound_fax_uuid' => $fax->outbound_fax_uuid,
        ]);

        return $log;
    }

    private function pageCount(mixed $value): ?int
    {
        $text = FaxChannelGuard::norm($value);
        if ($text === '' || ! preg_match('/^\d+$/', $text)) {
            return null;
        }

        return (int) $text;
    }

    private function ensureConnected(FreeswitchEslService $esl): bool
    {
        try {
            if (! $esl->isConnected()) {
                $esl->reconnect();
            }

            return true;
        } catch (Throwable $e) {
            fax_webhook_debug('CheckStuckFaxesJob: ESL reconnect failed, leaving row sending', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  list<string>  $uuids
     */
    private function hangupChannels(FreeswitchEslService $esl, array $uuids): void
    {
        if ($uuids === []) {
            return;
        }

        if (! $this->ensureConnected($esl)) {
            return;
        }

        foreach ($uuids as $uuid) {
            if (! FaxChannelGuard::isUuid($uuid)) {
                continue;
            }

            try {
                $reply = $esl->executeCommand('uuid_kill '.$uuid, false);
                fax_webhook_debug('CheckStuckFaxesJob: uuid_kill sent', [
                    'call_uuid' => $uuid,
                    'reply' => trim((string) $reply),
                ]);
            } catch (Throwable $e) {
                fax_webhook_debug('CheckStuckFaxesJob: uuid_kill failed', [
                    'call_uuid' => $uuid,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
