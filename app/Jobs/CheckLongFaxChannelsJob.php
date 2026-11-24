<?php

namespace App\Jobs;

use App\Services\Fax\FaxChannelGuard;
use App\Services\Fax\FaxChannelProbe;
use App\Services\FreeswitchEslService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Hangs up a local txfax channel that is past its page budget. When
 * FAX_LONG_CHANNEL_ALERT_TO is set, mails that address once per channel and
 * mails again when the channel is gone. There is no default address.
 *
 * Runs on every node. It is not HA-coordinated: each node can see only its
 * own FreeSWITCH channels. A blank alert address is logged and the channel
 * is still hung up.
 */
class CheckLongFaxChannelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 120;

    public function __construct()
    {
        $this->onQueue('faxes');
    }

    public function handle(FreeswitchEslService $esl): void
    {
        $node = $this->nodeName();

        try {
            if (! $esl->isConnected()) {
                $esl->reconnect();
            }

            $channels = (new FaxChannelProbe($esl))->txfaxChannels();
            $now = time();
            $over = [];
            foreach ($channels as $channel) {
                if (! FaxChannelGuard::overBudget($channel, $now)) {
                    continue;
                }

                $channel['age_seconds'] = $now - (int) $channel['created_epoch'];
                $over[$channel['uuid']] = $channel;
            }

            try {
                $this->notify($node, $over);
            } catch (Throwable $e) {
                logger('CheckLongFaxChannelsJob: alert bookkeeping failed: '.$e->getMessage());
            }

            foreach ($over as $channel) {
                $this->hangup($esl, (string) $channel['uuid']);
            }
        } catch (Throwable $e) {
            // An unread channel list must not send resolved or hang anything up.
            logger('CheckLongFaxChannelsJob: channel scan failed: '.$e->getMessage());
        } finally {
            try {
                $esl->disconnect();
            } catch (Throwable $e) {
                logger('CheckLongFaxChannelsJob: disconnect failed: '.$e->getMessage());
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $over
     */
    private function notify(string $node, array $over): void
    {
        $cacheKey = 'fax-long-channels:'.$node;
        $previous = Cache::get($cacheKey, []);
        if (! is_array($previous)) {
            $previous = [];
        }

        $transitions = FaxChannelGuard::alertTransitions(array_keys($previous), array_keys($over));
        $to = $this->alertAddress();
        $stored = [];

        foreach ($over as $uuid => $channel) {
            $prior = $previous[$uuid] ?? null;
            $seen = is_array($prior);
            $alreadyMailed = $seen && ! empty($prior['mailed']);
            $mailed = $alreadyMailed;
            if ($to === '') {
                if (! $seen) {
                    logger('CheckLongFaxChannelsJob: alert address is blank; hanging up '.$uuid.' on '.$node.' without an alert');
                }
            } elseif (! $alreadyMailed) {
                $mailed = $this->sendAlert($to, $node, $channel, 'triggered');
            }

            $stored[$uuid] = $channel;
            $stored[$uuid]['mailed'] = $mailed;
        }

        foreach ($transitions['resolve'] as $uuid) {
            $snapshot = is_array($previous[$uuid] ?? null) ? $previous[$uuid] : ['uuid' => $uuid];
            if ($to !== '' && ! empty($snapshot['mailed'])) {
                $this->sendAlert($to, $node, $snapshot, 'resolved');
            }
        }

        Cache::forever($cacheKey, $stored);
    }

    /**
     * @param  array<string, mixed>  $channel
     */
    private function sendAlert(string $to, string $node, array $channel, string $status): bool
    {
        $uuid = FaxChannelGuard::norm($channel['uuid'] ?? '');
        $subject = 'fspbx fax channel '.$node.' '.$uuid;
        try {
            Mail::raw(
                FaxChannelGuard::alertBody($channel, $node, $status),
                function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
                }
            );

            return true;
        } catch (Throwable $e) {
            logger('CheckLongFaxChannelsJob: alert mail failed for '.$uuid.' on '.$node.': '.$e->getMessage());

            return false;
        }
    }

    private function hangup(FreeswitchEslService $esl, string $uuid): void
    {
        if (! FaxChannelGuard::isUuid($uuid)) {
            return;
        }

        try {
            if (! $esl->isConnected()) {
                $esl->reconnect();
            }

            $reply = $esl->executeCommand('uuid_kill '.$uuid, false);
            logger('CheckLongFaxChannelsJob: uuid_kill '.$uuid.' reply '.FaxChannelGuard::norm($reply));
        } catch (Throwable $e) {
            logger('CheckLongFaxChannelsJob: uuid_kill failed for '.$uuid.': '.$e->getMessage());
        }
    }

    private function alertAddress(): string
    {
        $to = trim((string) config('fax.long_channel_alert_to', ''));
        if ($to === '' || str_contains($to, "\n") || str_contains($to, "\r") || ! str_contains($to, '@')) {
            return '';
        }

        return $to;
    }

    private function nodeName(): string
    {
        $name = gethostname();
        if (! is_string($name) || $name === '') {
            $name = php_uname('n');
        }

        return str_replace(["\r", "\n"], '', $name);
    }
}
