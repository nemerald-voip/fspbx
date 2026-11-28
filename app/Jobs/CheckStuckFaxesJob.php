<?php

namespace App\Jobs;

use App\Models\OutboundFax;
use App\Services\FaxChannelInspector;
use App\Services\FreeswitchEslService;
use App\Services\Ha\ActiveNodeResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

class CheckStuckFaxesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 120;

    private const GRACE_SECONDS = 300;

    public function __construct()
    {
        $this->onQueue('faxes');
    }

    public function handle(FreeswitchEslService $esl, ActiveNodeResolver $nodes): void
    {
        $inspector = new FaxChannelInspector($esl);
        $scanTime = now();
        try {
            $scan = $inspector->scan();
            $cutoff = $scanTime->copy()->subSeconds(self::GRACE_SECONDS);
            $rows = OutboundFax::where(function ($query) use ($scan, $cutoff) {
                // The channel list can identify orphans even after the fax was sent.
                $query->whereIn('outbound_fax_uuid', array_keys($scan['carriers']));
                if ($scan['complete']) {
                    $query->orWhere(fn ($q) => $q->where('status', 'sending')->where('retry_at', '<=', $cutoff));
                } elseif ($scan['listed']) {
                    $query->orWhere(fn ($q) => $q->where('status', 'sending')->whereNotNull('channel_missing_since'));
                }
            })->lazyById(100, 'outbound_fax_uuid');

            $host = $nodes->hostFingerprint();
            $standalone = null;
            foreach ($rows as $fax) {
                $setupListed = isset($scan['listed'][$fax->call_uuid]);
                if ($setupListed && !$scan['complete']) {
                    $this->clearMissing($fax);
                }
                $carriers = $scan['carriers'][$fax->getKey()] ?? [];
                $carrierAbsent = $this->inspectCarriers($inspector, $fax, $carriers, $scan['complete'], $scanTime);
                if (!$carrierAbsent || $fax->status !== 'sending'
                    || !$fax->current_attempt_uuid || !$fax->retry_at || $fax->retry_at->gt($cutoff)) {
                    continue;
                }
                if ($fax->sending_host === null) {
                    $standalone ??= $this->verifiedStandalone($nodes);
                    if (!$standalone) {
                        continue;
                    }
                } elseif ($fax->sending_host !== $host) {
                    continue;
                }
                $this->checkSetupOrAbsence($inspector, $fax, $scanTime, $setupListed);
            }
        } finally {
            $esl->disconnect();
        }
        $this->phaseD();
    }

    private function verifiedStandalone(ActiveNodeResolver $nodes): bool
    {
        try {
            $state = $nodes->resolve();
            // A subscription using libpq service= can have no discoverable
            // host endpoint and still belong to a replicated installation.
            return $state['source'] === 'standalone'
                && ($state['subscription_discovery']['readable'] ?? false)
                && ($state['subscription_discovery']['subscriptions'] ?? null) === [];
        } catch (Throwable $e) {
            fax_webhook_debug('Fax watchdog could not verify standalone installation', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** Returns true when the scan rules out a carrier for the current attempt. */
    private function inspectCarriers(FaxChannelInspector $inspector, OutboundFax $fax, array $carriers, bool $complete, Carbon $scanTime): bool
    {
        $absent = $complete;
        foreach ($carriers as $attempt => $matches) {
            $current = $attempt === $fax->current_attempt_uuid;
            if ($current && in_array($fax->domain_uuid, array_column($matches, 'domain_uuid'), true)) {
                $absent = false;
                $this->clearMissing($fax);
            }
            if (!$complete || count($matches) !== 1 || $matches[0]['domain_uuid'] !== $fax->domain_uuid) {
                $absent = false;
                continue;
            }
            if (!$current && !$fax->logs()->where('domain_uuid', $fax->domain_uuid)
                ->where('outbound_fax_attempt_uuid', $attempt)->exists()) {
                $absent = false;
                continue;
            }
            $this->inspectCarrier($inspector, $fax, $matches[0], $current, $scanTime);
        }
        return $absent;
    }

    private function inspectCarrier(FaxChannelInspector $inspector, OutboundFax $fax, array $channel, bool $current, Carbon $scanTime): void
    {
        $started = $current ? $fax->retry_at?->timestamp : null;
        $epoch = $channel['created_epoch'];
        if ($started === null && (is_int($epoch) || (is_string($epoch) && ctype_digit($epoch)))) {
            $started = (int) $epoch;
        }
        if (!$this->overdue($fax, $started, $scanTime) || ($current && !in_array($fax->status, ['sending', 'sent'], true))) {
            return;
        }
        $outcome = $this->outcome($channel);
        $sending = $current && $fax->status === 'sending';
        if ($outcome === 'inconclusive' || (!$sending && $outcome === 'unfinished')) {
            return;
        }

        $live = $inspector->dump($channel['uuid']);
        if (!$this->matchesAttempt($live, $fax, $channel['outbound_fax_attempt_uuid']) || $live['current_application'] !== 'txfax') {
            return;
        }
        $freshOutcome = $this->outcome($live);
        if ($freshOutcome === 'inconclusive' || ($outcome === 'delivered' && $freshOutcome !== 'delivered')) {
            return;
        }
        if ($sending && $freshOutcome === 'delivered') {
            if (!(new HandleFaxTxEventJob($live))->recordResult()) {
                return;
            }
        } elseif ($sending) {
            if (!$this->sendingAttempt($fax)->exists()) {
                return;
            }
        } elseif ($freshOutcome === 'unfinished') {
            return;
        }

        $inspector->hangup($live['uuid']);
    }

    private function outcome(array $channel): string
    {
        $success = (string) ($channel['fax_success'] ?? '');
        $code = (string) ($channel['fax_result_code'] ?? '');
        if ($success === '1') {
            $total = filter_var($channel['fax_document_total_pages'] ?? null, FILTER_VALIDATE_INT);
            $sent = filter_var($channel['fax_document_transferred_pages'] ?? null, FILTER_VALIDATE_INT);
            return $code === '0' && $total > 0 && $sent === $total ? 'delivered' : 'inconclusive';
        }
        if ($success === '0' && ctype_digit($code) && (int) $code > 0) {
            return 'failed';
        }
        return in_array($success, ['', '0'], true) && $code === '' ? 'unfinished' : 'inconclusive';
    }

    private function checkSetupOrAbsence(FaxChannelInspector $inspector, OutboundFax $fax, Carbon $scanTime, bool $setupListed): void
    {
        if ($fax->call_uuid !== null) {
            $exists = $inspector->exists($fax->call_uuid);
            // A listed setup leg that vanished during this scan may just
            // have bowed out into a carrier created after the listing.
            if ($setupListed && $exists !== true) {
                $this->clearMissing($fax);
                return;
            }
            if ($exists === null) {
                return;
            }
            if ($exists) {
                $this->clearMissing($fax);
                if ($this->overdue($fax, $fax->retry_at->timestamp, $scanTime) && $this->sendingAttempt($fax)->exists()) {
                    $live = $inspector->dump($fax->call_uuid);
                    if ($this->matchesAttempt($live, $fax, $fax->current_attempt_uuid)
                        && $live['current_application'] !== 'txfax' && $this->outcome($live) === 'unfinished') {
                        $inspector->hangup($live['uuid']);
                    }
                }
                return;
            }
        }

        $confirmedAt = now();
        if (!$fax->channel_missing_since) {
            $this->sendingAttempt($fax)->whereNull('channel_missing_since')->update(['channel_missing_since' => $confirmedAt]);
        } elseif ($fax->channel_missing_since->timestamp + self::GRACE_SECONDS <= $confirmedAt->timestamp) {
            (new HandleFaxTxEventJob([
                'domain_uuid' => $fax->domain_uuid,
                'outbound_fax_uuid' => $fax->getKey(),
                'outbound_fax_attempt_uuid' => $fax->current_attempt_uuid,
                'fax_success' => '0',
                'fax_result_text' => 'FreeSWITCH fax channels disappeared before the result was received',
            ]))->recordResult($fax);
        }
    }

    private function matchesAttempt(?array $data, OutboundFax $fax, string $attempt): bool
    {
        return $data !== null && $data['domain_uuid'] === $fax->domain_uuid
            && $data['outbound_fax_uuid'] === $fax->getKey() && $data['outbound_fax_attempt_uuid'] === $attempt;
    }

    private function overdue(OutboundFax $fax, ?int $started, Carbon $scanTime): bool
    {
        $budget = $fax->total_pages > 0 ? max(900, 90 * $fax->total_pages) : 21600;
        return $started > 0 && $scanTime->timestamp - $started >= $budget;
    }

    private function sendingAttempt(OutboundFax $fax): Builder
    {
        return OutboundFax::whereKey($fax->getKey())->where('status', 'sending')
            ->where('current_attempt_uuid', $fax->current_attempt_uuid)->where('call_uuid', $fax->call_uuid)
            ->where('sending_host', $fax->sending_host);
    }

    private function clearMissing(OutboundFax $fax): void
    {
        if ($fax->channel_missing_since !== null) {
            $this->sendingAttempt($fax)->where('channel_missing_since', $fax->channel_missing_since)
                ->update(['channel_missing_since' => null]);
        }
    }

    /** Recover pending faxes whose original or delayed dispatch was lost. */
    private function phaseD(): void
    {
        $rows = OutboundFax::whereIn('status', ['waiting', 'trying', 'busy'])
            ->where(function ($q) {
                $q->whereNull('retry_at')->orWhere('retry_at', '<', now()->subSeconds(self::GRACE_SECONDS));
            })
            ->limit(100)
            ->get(['outbound_fax_uuid']);

        foreach ($rows as $fax) {
            SendFaxJob::dispatch($fax->outbound_fax_uuid);
        }
    }
}
