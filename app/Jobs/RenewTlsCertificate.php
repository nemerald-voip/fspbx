<?php

namespace App\Jobs;

use App\Services\LetsEncryptService;
use App\Services\ScheduledAnnouncements\AuthoritativeDnsActiveNodeGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Renews the FreeSWITCH Let's Encrypt certificate when close to expiry, hot-
 * reloads it via `reloadcert`, and (in a cluster) replicates it to peer nodes.
 * Scheduled daily from the console Kernel and gated by the
 * `renew_tls_certificates` scheduled-jobs setting.
 *
 * Multi-node behaviour:
 *  - Runs on every node, but only the node the failover DNS currently points to
 *    (AuthoritativeDnsActiveNodeGuard) actually proceeds.
 *  - When the cert is healthy but peers may be behind (a previous push failed),
 *    it re-pushes the existing cert instead of re-issuing, so a transient peer
 *    outage doesn't burn Let's Encrypt rate limits.
 */
class RenewTlsCertificate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 300;

    /** Renew when fewer than this many days remain on the current cert. */
    protected int $renewBeforeDays = 30;

    public function __construct(protected bool $force = false) {}

    public function handle(LetsEncryptService $service, AuthoritativeDnsActiveNodeGuard $guard): void
    {
        $status = $service->status();
        $config = $status['config'];
        $cert = $status['certificate'];
        $domain = $config['domain'] ?: $service->defaultDomain();

        if (! $domain) {
            logger('RenewTlsCertificate: no domain configured, skipping.');

            return;
        }

        // In a cluster, only the node the failover DNS points at renews/pushes.
        // The active-node check is driven by LE's OWN failover hostname (the
        // primary SAN) and this node's auto-detected IPs — it does NOT read any
        // scheduled_announcements_* settings. Single-node installs (no peers)
        // always proceed; the DNS check must never block a lone node's renewal.
        if ($service->isClustered()) {
            $primary = $service->parseDomains($domain)[0] ?? $domain;
            $active = $guard->canExecute(null, $primary, $service->localIps());

            if (! ($active['active'] ?? false)) {
                logger('RenewTlsCertificate: not the active node ('.($active['status'] ?? '?')
                    .': '.($active['reason'] ?? '').'); skipping.');

                return;
            }
        }

        $daysRemaining = $cert['days_remaining'];
        $needsRenewal = $this->force
            || ! $cert['installed']
            || ! $cert['is_lets_encrypt']
            || $daysRemaining === null
            || $daysRemaining <= $this->renewBeforeDays;

        try {
            if ($needsRenewal) {
                $result = $service->issue($domain, $config['account_email'] ?: null);

                logger('RenewTlsCertificate: renewed '.$domain
                    .' (valid_to='.($result['valid_to'] ?? 'unknown').').');

                $this->notifySuccess($service, $result);
            } elseif ($service->isClustered()) {
                // Cert is healthy; ensure peers still hold it. This retries a
                // previously-failed push without re-issuing.
                $service->assertPeersInSync($service->pushToPeers());
            }
        } catch (Throwable $e) {
            $service->recordError($e);

            logger('RenewTlsCertificate: failed for '.$domain.': '.$e->getMessage()
                .' at '.$e->getFile().':'.$e->getLine());

            $this->notifyFailure($service, $domain, $e);

            throw $e;
        }
    }

    protected function notifySuccess(LetsEncryptService $service, array $result): void
    {
        $to = $service->config()['account_email'] ?? null;
        if (! $to) {
            return;
        }

        $domains = implode(', ', $result['domains'] ?? []);
        $validTo = ! empty($result['valid_to']) ? date('M j, Y H:i', strtotime($result['valid_to'])) : 'unknown';
        $env = ($result['staging'] ?? false) ? ' (staging/test)' : '';
        $peers = $result['peers'] ?? [];

        $body = "The FreeSWITCH TLS certificate was renewed successfully{$env}.\n\n"
            ."Hostnames (SANs): {$domains}\n"
            ."Valid until: {$validTo}\n"
            .(! empty($peers) ? 'Replicated to '.count($peers)." peer node(s).\n" : '')
            ."\nServer: ".(gethostname() ?: php_uname('n'));

        $this->send($to, 'TLS certificate renewed: '.($result['primary'] ?? $domains), $body);
    }

    protected function notifyFailure(LetsEncryptService $service, string $domain, Throwable $e): void
    {
        $to = $service->config()['account_email'] ?? null;
        if (! $to) {
            return;
        }

        // Re-read remaining life so the alert states the real urgency.
        $daysRemaining = $service->status()['certificate']['days_remaining'] ?? null;

        $expiryNote = is_int($daysRemaining)
            ? ($daysRemaining > 0
                ? "The currently installed certificate is still valid for {$daysRemaining} day(s)."
                : 'The currently installed certificate has EXPIRED.')
            : 'Current certificate validity could not be determined.';

        $body = "The FreeSWITCH TLS certificate renewal FAILED.\n\n"
            ."Hostnames (SANs): {$domain}\n"
            ."Error: ".$e->getMessage()."\n\n"
            ."{$expiryNote}\n"
            ."Days remaining: ".($daysRemaining === null ? 'unknown' : $daysRemaining)."\n"
            ."It will be retried automatically on the next scheduled run (tomorrow).\n\n"
            ."Server: ".(gethostname() ?: php_uname('n'));

        $this->send($to, 'TLS certificate renewal FAILED: '.$domain, $body);
    }

    protected function send(string $to, string $subject, string $body): void
    {
        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (Throwable $e) {
            logger('RenewTlsCertificate: unable to send notification email: '.$e->getMessage());
        }
    }
}
