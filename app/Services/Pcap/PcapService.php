<?php

namespace App\Services\Pcap;

use App\Models\Extensions;
use App\Models\PcapCallLeg;
use App\Models\PcapCapture;
use App\Models\PcapSession;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PcapService
{
    // Must match the capture filter in install/fspbx-pcap-capture.sh.
    public const SIP_PORT_RANGE = '5060-5091';

    public const BASE_DIR = '/var/lib/fspbx-pcap';
    public const TOOLS = ['tcpdump', 'mergecap', 'tshark'];

    // Pause capturing below 10% free disk and resume once 12% is free again.
    public const PAUSE_BELOW_FREE_PERCENT = 10;
    public const RESUME_ABOVE_FREE_PERCENT = 12;

    // Wait this long after the last leg hangs up before cutting the call.
    public const SETTLE_SECONDS = 30;

    // Always keep this much recent spool so late call data can still be cut.
    public const SPOOL_KEEP_SECONDS = 600;
    public const SPOOL_MAX_SECONDS = 86400;
    public const SPOOL_FALLBACK_SECONDS = 21600;

    private const CAPTURING_DOMAINS_CACHE = 'pcap:capturing_domains';
    private const SIPTRACE_STATE_CACHE = 'pcap:siptrace_state';
    private const SIPTRACE_LEVEL = 'debug';
    private const PAUSED_CACHE = 'pcap:paused';

    public function spoolDir(): string
    {
        return self::BASE_DIR . '/spool';
    }

    public function flagFile(): string
    {
        return self::BASE_DIR . '/capture.on';
    }

    /**
     * The capture service also records FreeSWITCH's HEP stream on this port.
     */
    public function hepPortFile(): string
    {
        return self::BASE_DIR . '/hep.port';
    }

    /**
     * UDP port of the SIP Capture (HEP) stream, or null when SIP Capture is off.
     * HEP carries readable SIP for phones registered over TLS.
     */
    public function hepPort(): ?int
    {
        try {
            $settings = app(\App\Services\SipCaptureService::class)->settings();
        } catch (\Throwable $e) {
            return null;
        }

        $port = (int) ($settings['collector_port'] ?? 0);

        return !empty($settings['enabled']) && ($settings['transport'] ?? null) === 'udp' && $port > 0 && $port <= 65535
            ? $port
            : null;
    }

    public function node(): string
    {
        return gethostname() ?: 'localhost';
    }

    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * @return string[] Capture tools that are not installed.
     */
    public function missingTools(): array
    {
        return array_values(array_filter(self::TOOLS, fn ($tool) => !$this->findTool($tool)));
    }

    public function isPaused(): bool
    {
        return (bool) Cache::get(self::PAUSED_CACHE, false);
    }

    public static function expiresAt(string $duration, \DateTimeInterface $start): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::instance($start)->addMinutes(PcapSession::DURATIONS[$duration]);
    }

    /**
     * Files are kept for the chosen hours after capturing ends, never more than 72.
     */
    public static function deleteAt(\DateTimeInterface $captureEnd, int $keepHours): \Illuminate\Support\Carbon
    {
        return \Illuminate\Support\Carbon::instance($captureEnd)->addHours(min($keepHours, max(PcapSession::KEEP_HOURS)));
    }

    /**
     * Accounts with at least one session capturing right now. Cached briefly
     * because the event listener asks on every hangup.
     *
     * @return string[]
     */
    public function capturingDomains(): array
    {
        return Cache::remember(self::CAPTURING_DOMAINS_CACHE, 10, function () {
            return PcapSession::query()->capturing()->distinct()->pluck('domain_uuid')->all();
        });
    }

    public function forgetCapturingDomains(): void
    {
        Cache::forget(self::CAPTURING_DOMAINS_CACHE);
    }

    /**
     * Turn a CHANNEL_HANGUP_COMPLETE event into a call leg row, or null when the
     * event lacks a channel UUID.
     *
     * @param callable(string): ?string $header
     */
    public static function legFromEvent(callable $header, string $node): ?array
    {
        $value = fn (string $name) => ($v = trim((string) $header($name))) === '' ? null : $v;

        $channel = $value('Unique-ID');
        if (!$channel) {
            return null;
        }

        $numbers = array_values(array_unique(array_filter([
            $value('variable_extension_uuid'),
            $value('variable_dialed_user'),
            $value('variable_user_name'),
            $value('variable_sip_auth_username'),
            $value('Caller-Caller-ID-Number'),
            $value('Caller-Destination-Number'),
        ])));

        $created = (int) ($value('variable_start_epoch') ?? 0);
        if ($created <= 0) {
            $created = intdiv((int) ($value('Caller-Channel-Created-Time') ?? 0), 1000000);
        }
        $hangup = (int) ($value('variable_end_epoch') ?? 0);
        if ($hangup <= 0) {
            $hangup = intdiv((int) ($value('Event-Date-Timestamp') ?? 0), 1000000) ?: time();
        }

        $port = fn (?string $port) => $port !== null && ctype_digit($port) ? (int) $port : null;

        // Every UUID FreeSWITCH reports as related to this channel. Legs of one
        // call share at least one, which is how they are grouped later.
        $linked = array_values(array_unique(array_filter([
            $value('Channel-Call-UUID'),
            $value('Other-Leg-Unique-ID'),
            $value('variable_originator'),
            $value('variable_originating_leg_uuid'),
            $value('variable_bridge_uuid'),
            $value('variable_signal_bond'),
            $value('variable_last_bridge_to'),
        ], fn ($uuid) => $uuid !== $channel && Str::isUuid((string) $uuid))));

        return [
            'call_uuid' => $value('Channel-Call-UUID') ?? $channel,
            'channel_uuid' => $channel,
            'domain_uuid' => Str::isUuid((string) $value('variable_domain_uuid')) ? $value('variable_domain_uuid') : null,
            'node' => $node,
            'linked_uuids' => $linked,
            'channel_direction' => $value('Call-Direction'),
            'direction' => $value('variable_call_direction'),
            'caller_id_name' => Str::limit((string) $value('Caller-Caller-ID-Name'), 250, ''),
            'caller_id_number' => Str::limit((string) $value('Caller-Caller-ID-Number'), 250, ''),
            'destination_number' => Str::limit((string) $value('Caller-Destination-Number'), 250, ''),
            'numbers' => $numbers,
            'sip_call_id' => Str::limit((string) $value('variable_sip_call_id'), 250, '') ?: null,
            'local_media_port' => $port($value('variable_local_media_port')),
            'remote_media_ip' => $value('variable_remote_media_ip'),
            'remote_media_port' => $port($value('variable_remote_media_port')),
            'created_epoch' => $created ?: $hangup,
            'hangup_epoch' => $hangup,
        ];
    }

    /**
     * Store a hung-up leg when its account is capturing. Legs without an
     * account are kept too; the call's A-leg decides which account it belongs to.
     */
    public function recordLeg(array $leg): bool
    {
        if ($leg['domain_uuid'] !== null && !in_array($leg['domain_uuid'], $this->capturingDomains(), true)) {
            return false;
        }

        if ($leg['domain_uuid'] === null && !$this->capturingDomains()) {
            return false;
        }

        PcapCallLeg::query()->updateOrCreate(['channel_uuid' => $leg['channel_uuid']], $leg);

        return true;
    }

    /**
     * Whether a call (all of its legs) matches a session's call types and extensions.
     *
     * @param array<int, array> $legs
     * @param string[] $extensionKeys extension UUIDs and numbers chosen for the session
     */
    public static function matches(PcapSession $session, array $aLeg, array $legs, array $extensionKeys): bool
    {
        if (!$session->coversEpoch((int) $aLeg['created_epoch'])) {
            return false;
        }

        $direction = $aLeg['direction'] ?? null;
        if (!in_array($direction, $session->directions ?? [], true)) {
            return false;
        }

        if ($session->scope !== PcapSession::SCOPE_EXTENSIONS) {
            return true;
        }

        foreach ($legs as $leg) {
            if (array_intersect($leg['numbers'] ?? [], $extensionKeys)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Everything the scheduler does each minute.
     *
     * @return array<string, int|bool>
     */
    public function maintain(): array
    {
        $result = [
            'expired' => $this->deleteExpiredSessions(),
            'orphans' => $this->deleteOrphanedFiles(),
            'paused' => $this->checkDiskSpace(),
        ];

        // Read trace output before syncing, so turning trace off keeps the last calls' SIP.
        $result['sip_traced'] = $this->collectSipTrace();
        $result['capturing'] = $this->syncCaptureFlag();
        $result['captured'] = $this->missingTools() ? 0 : $this->processFinishedCalls();
        $result['spool_deleted'] = $this->cleanSpool();
        $result['legs_deleted'] = PcapCallLeg::query()
            ->where('hangup_epoch', '<', time() - self::SPOOL_MAX_SECONDS)
            ->delete();

        return $result;
    }

    /**
     * Sessions are removed with all of their files once their keep time is up.
     */
    public function deleteExpiredSessions(): int
    {
        $count = 0;
        PcapSession::query()
            ->where('delete_at', '<=', now())
            ->each(function (PcapSession $session) use (&$count) {
                $this->deleteSession($session);
                $count++;
            });

        return $count;
    }

    public function deleteSession(PcapSession $session): void
    {
        $this->disk()->deleteDirectory($session->storageDirectory());
        $session->delete();
        $this->forgetCapturingDomains();
    }

    /**
     * On redundant servers each node keeps its own files, so remove local
     * folders whose session was deleted elsewhere.
     */
    public function deleteOrphanedFiles(): int
    {
        $count = 0;
        foreach ($this->disk()->directories('pcaps') as $domainDir) {
            $sessionDirs = $this->disk()->directories($domainDir);
            if (!$sessionDirs) {
                $this->disk()->deleteDirectory($domainDir);
                continue;
            }

            $uuids = array_map('basename', $sessionDirs);
            $existing = PcapSession::query()->whereIn('uuid', array_filter($uuids, fn ($uuid) => Str::isUuid($uuid)))->pluck('uuid')->all();
            foreach ($sessionDirs as $dir) {
                if (!in_array(basename($dir), $existing, true)) {
                    $this->disk()->deleteDirectory($dir);
                    $count++;
                }
            }
        }

        return $count;
    }

    public function checkDiskSpace(): bool
    {
        $path = is_dir(self::BASE_DIR) ? self::BASE_DIR : storage_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if (!$total || $free === false) {
            return $this->isPaused();
        }

        $percent = $free / $total * 100;
        $paused = $this->isPaused();

        if (!$paused && $percent < self::PAUSE_BELOW_FREE_PERCENT) {
            logger()->warning(sprintf('PCAP capture paused: %.1f%% disk space free.', $percent));
            $paused = true;
        } elseif ($paused && $percent >= self::RESUME_ABOVE_FREE_PERCENT) {
            logger()->info(sprintf('PCAP capture resumed: %.1f%% disk space free.', $percent));
            $paused = false;
        }

        Cache::forever(self::PAUSED_CACHE, $paused);

        return $paused;
    }

    /**
     * The root capture service runs tcpdump only while this file exists.
     */
    public function syncCaptureFlag(): bool
    {
        $capturing = !$this->isPaused() && PcapSession::query()->capturing()->exists();

        if (!is_dir(self::BASE_DIR)) {
            return false;
        }

        $hepPort = $this->hepPort();
        if ($hepPort) {
            if (@file_get_contents($this->hepPortFile()) !== (string) $hepPort) {
                @file_put_contents($this->hepPortFile(), (string) $hepPort);
            }
        } elseif (is_file($this->hepPortFile())) {
            @unlink($this->hepPortFile());
        }

        if ($capturing) {
            @touch($this->flagFile());
        } elseif (is_file($this->flagFile())) {
            @unlink($this->flagFile());
        }

        $this->syncSipTrace($capturing);

        return $capturing;
    }

    /**
     * Group legs into calls: legs that share any channel or linked UUID belong together.
     *
     * @param iterable<int, array|PcapCallLeg> $legs
     * @return array<int, array<int, array|PcapCallLeg>>
     */
    public static function groupLegs(iterable $legs): array
    {
        $legs = is_array($legs) ? array_values($legs) : collect($legs)->values()->all();
        $parent = [];
        $find = function (string $uuid) use (&$parent, &$find): string {
            if (!isset($parent[$uuid])) {
                $parent[$uuid] = $uuid;
            }
            if ($parent[$uuid] !== $uuid) {
                $parent[$uuid] = $find($parent[$uuid]);
            }
            return $parent[$uuid];
        };

        foreach ($legs as $leg) {
            $root = $find($leg['channel_uuid']);
            foreach ($leg['linked_uuids'] ?? [] as $uuid) {
                $parent[$find($uuid)] = $root;
            }
        }

        $groups = [];
        foreach ($legs as $leg) {
            $groups[$find($leg['channel_uuid'])][] = $leg;
        }

        return array_values($groups);
    }

    /**
     * The leg that started the call: the earliest, preferring a leg FreeSWITCH received.
     */
    public static function firstLeg(array $group): array|PcapCallLeg
    {
        usort($group, fn ($a, $b) => [(int) $a['created_epoch'], ($a['channel_direction'] ?? null) === 'inbound' ? 0 : 1]
            <=> [(int) $b['created_epoch'], ($b['channel_direction'] ?? null) === 'inbound' ? 0 : 1]);

        return $group[0];
    }

    /**
     * Keep FreeSWITCH SIP trace on while capturing, so calls from phones on TLS
     * get readable signalling. It is re-sent every minute because a FreeSWITCH
     * restart turns it off. The previous trace level is restored afterwards.
     */
    public function syncSipTrace(bool $capturing): void
    {
        $state = Cache::get(self::SIPTRACE_STATE_CACHE);

        if ($capturing) {
            if (!$state) {
                $level = $this->eslApi('sofia tracelevel');
                $previous = $level !== null && preg_match('/tracelevel is (\w+)/i', $level, $m) ? strtolower($m[1]) : 'console';
                $collector = $this->sipTraceCollector();
                $collector?->startAtEnd();
                Cache::forever(self::SIPTRACE_STATE_CACHE, ['previous_level' => $previous]);
            }
            $this->eslApi('sofia tracelevel ' . self::SIPTRACE_LEVEL);
            $this->eslApi('sofia global siptrace on');

            return;
        }

        if ($state) {
            $this->collectSipTrace();
            $this->eslApi('sofia global siptrace off');
            $this->eslApi('sofia tracelevel ' . preg_replace('/[^a-z]/', '', $state['previous_level'] ?? 'console'));
            $this->sipTraceCollector()?->stop();
            Cache::forget(self::SIPTRACE_STATE_CACHE);
        }
    }

    /**
     * Convert new SIP trace output into spool packets.
     */
    public function collectSipTrace(): int
    {
        if (!Cache::get(self::SIPTRACE_STATE_CACHE) || !is_dir($this->spoolDir())) {
            return 0;
        }

        try {
            return $this->sipTraceCollector()?->collect($this->spoolLinkType()) ?? 0;
        } catch (\Throwable $e) {
            logger('PCAP SIP trace collection failed: ' . $e->getMessage());
            return 0;
        }
    }

    protected function sipTraceCollector(): ?SipTraceCollector
    {
        $logDir = Cache::remember('pcap:fs_log_dir', 3600, fn () => trim((string) $this->eslApi('global_getvar log_dir')) ?: '/var/log/freeswitch');
        if (!str_starts_with($logDir, '/')) {
            $logDir = '/var/log/freeswitch';
        }

        return new SipTraceCollector(
            rtrim($logDir, '/') . '/freeswitch.log',
            $this->spoolDir() . '/sip',
            $this->tlsListeners(),
            self::systemTimezone(),
        );
    }

    /**
     * FreeSWITCH TLS listeners from `sofia xmlstatus`.
     *
     * @return array<int, array{family: int, ip: string, port: int, internal: bool}>
     */
    protected function tlsListeners(): array
    {
        return Cache::remember('pcap:tls_listeners', 600, function () {
            $xml = $this->eslApi('sofia xmlstatus');
            if (!$xml || !preg_match_all('/<name>([^<]+)<\/name>\s*<type>profile<\/type>\s*<data>[^<]*@(\[[^\]]+\]|[^:<]+):(\d+)<\/data>\s*<state>[^<]*\(TLS\)<\/state>/', $xml, $matches, PREG_SET_ORDER)) {
                return [];
            }

            return array_map(fn ($m) => [
                'family' => str_starts_with($m[2], '[') ? 6 : 4,
                'ip' => trim($m[2], '[]'),
                'port' => (int) $m[3],
                'internal' => str_starts_with($m[1], 'internal'),
            ], $matches);
        });
    }

    /**
     * FreeSWITCH writes its log in the server's local time.
     */
    public static function systemTimezone(): \DateTimeZone
    {
        $candidates = [];
        if (is_link('/etc/localtime') && preg_match('#zoneinfo/(.+)$#', (string) readlink('/etc/localtime'), $m)) {
            $candidates[] = $m[1];
        }
        if (is_readable('/etc/timezone')) {
            $candidates[] = trim((string) file_get_contents('/etc/timezone'));
        }
        foreach ($candidates as $name) {
            try {
                return new \DateTimeZone($name);
            } catch (\Throwable $e) {
            }
        }

        return new \DateTimeZone(date_default_timezone_get());
    }

    /**
     * Link type of the newest spool file, so trace packets merge with it.
     */
    protected function spoolLinkType(): int
    {
        $chunks = glob($this->spoolDir() . '/*.pcap') ?: [];
        rsort($chunks, SORT_NATURAL);
        foreach ($chunks as $chunk) {
            $header = @file_get_contents($chunk, false, null, 0, 24);
            if ($header !== false && strlen($header) === 24) {
                $format = in_array(bin2hex(substr($header, 0, 4)), ['d4c3b2a1', '4d3cb2a1'], true) ? 'V' : 'N';
                return unpack($format, substr($header, 20, 4))[1] & 0xFFFF;
            }
        }

        // Linux cooked v2, which `tcpdump -i any` writes.
        return 276;
    }

    /**
     * Raw FreeSWITCH API reply, or null when FreeSWITCH could not be asked.
     */
    protected function eslApi(string $command): ?string
    {
        try {
            $esl = app(FreeswitchEslService::class);
            if (!$esl->isConnected()) {
                return null;
            }
            $reply = $esl->connection()->api($command);
            $esl->disconnect();

            return $reply ? (string) $reply->getBody() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Cut a PCAP for each finished call recorded on this server.
     */
    public function processFinishedCalls(int $limit = 2000): int
    {
        $legs = PcapCallLeg::query()
            ->where('node', $this->node())
            ->whereNull('processed_at')
            ->orderBy('hangup_epoch')
            ->limit($limit)
            ->get();
        if ($legs->isEmpty()) {
            return 0;
        }

        $channels = $this->activeChannels();
        $live = $channels === false ? null : collect($channels)->flatMap(fn ($row) => [$row['uuid'] ?? null, $row['call_uuid'] ?? null])->filter()->flip();

        $captured = 0;
        foreach (self::groupLegs($legs) as $group) {
            $group = collect($group);
            $lastHangup = (int) $group->max('hangup_epoch');
            if ($lastHangup > time() - self::SETTLE_SECONDS) {
                continue;
            }

            // Wait while any related channel is still up. If FreeSWITCH can't be asked, wait longer.
            $uuids = $group->flatMap(fn ($leg) => array_merge([$leg->channel_uuid], $leg->linked_uuids ?? []));
            if ($live === null ? $lastHangup > time() - 600 : $uuids->contains(fn ($uuid) => $live->has($uuid))) {
                continue;
            }

            $callUuid = self::firstLeg($group->all())->channel_uuid;
            try {
                $captured += $this->captureCall($callUuid, $group);
            } catch (\Throwable $e) {
                logger('PCAP capture failed for call ' . $callUuid . ': ' . $e->getMessage());
            }

            PcapCallLeg::query()->whereIn('uuid', $group->pluck('uuid'))->update(['processed_at' => now()]);
        }

        return $captured;
    }

    /**
     * @param Collection<int, PcapCallLeg> $legs
     */
    public function captureCall(string $callUuid, Collection $legs): int
    {
        $legRows = $legs->map(fn (PcapCallLeg $leg) => $leg->only([
            'channel_uuid', 'channel_direction', 'domain_uuid', 'direction', 'caller_id_name', 'caller_id_number', 'destination_number',
            'numbers', 'sip_call_id', 'local_media_port', 'created_epoch', 'hangup_epoch',
        ]))->values()->all();

        $aLeg = collect($legRows)->firstWhere('channel_uuid', $callUuid);
        $domainUuid = $aLeg['domain_uuid'] ?? collect($legRows)->pluck('domain_uuid')->filter()->first();
        if (!$aLeg || !$domainUuid) {
            return 0;
        }

        $sessions = PcapSession::query()
            ->where('domain_uuid', $domainUuid)
            ->where('started_at', '<=', \Illuminate\Support\Carbon::createFromTimestamp($aLeg['created_epoch']))
            ->get()
            ->filter(fn (PcapSession $session) => self::matches($session, $aLeg, $legRows, $this->extensionKeys($session)));

        if ($sessions->isEmpty()) {
            return 0;
        }

        $from = (int) collect($legRows)->min('created_epoch') - 5;
        $to = (int) collect($legRows)->max('hangup_epoch') + 5;
        $tools = [];
        foreach (self::TOOLS as $tool) {
            $tools[$tool] = $this->findTool($tool) ?? $tool;
        }
        $extractor = new PcapExtractor($this->spoolDir(), storage_path('app/pcap-tmp'), $tools, $this->hepPort());
        $fileName = $this->fileName($aLeg, $callUuid);

        $captured = 0;
        foreach ($sessions as $session) {
            if (PcapCapture::query()->where('pcap_session_uuid', $session->uuid)->where('call_uuid', $callUuid)->exists()) {
                continue;
            }

            $relative = $session->storageDirectory() . '/' . $fileName;
            $status = PcapCapture::STATUS_EMPTY;
            $packets = 0;
            $error = null;

            try {
                $packets = $extractor->extract($legRows, $from, $to, $this->disk()->path($relative));
                $status = $packets > 0 ? PcapCapture::STATUS_READY : PcapCapture::STATUS_EMPTY;
            } catch (\Throwable $e) {
                $status = PcapCapture::STATUS_FAILED;
                $error = Str::limit($e->getMessage(), 1000);
            }

            if ($status !== PcapCapture::STATUS_READY) {
                $this->disk()->delete($relative);
            }

            PcapCapture::query()->create([
                'pcap_session_uuid' => $session->uuid,
                'domain_uuid' => $domainUuid,
                'call_uuid' => $callUuid,
                'node' => $this->node(),
                'direction' => $aLeg['direction'],
                'caller_id_name' => $aLeg['caller_id_name'],
                'caller_id_number' => $aLeg['caller_id_number'],
                'destination_number' => $aLeg['destination_number'],
                'call_started_at' => \Illuminate\Support\Carbon::createFromTimestamp($aLeg['created_epoch']),
                'duration' => max(0, (int) $aLeg['hangup_epoch'] - (int) $aLeg['created_epoch']),
                'legs' => count($legRows),
                'status' => $status,
                'file_path' => $status === PcapCapture::STATUS_READY ? $relative : null,
                'file_size' => $status === PcapCapture::STATUS_READY ? (int) $this->disk()->size($relative) : 0,
                'packet_count' => $packets,
                'error' => $error,
            ]);
            $captured++;
        }

        return $captured;
    }

    /**
     * Extension UUIDs and numbers chosen for a session.
     *
     * @return string[]
     */
    public function extensionKeys(PcapSession $session): array
    {
        if ($session->scope !== PcapSession::SCOPE_EXTENSIONS || empty($session->extension_uuids)) {
            return [];
        }

        $numbers = Extensions::query()
            ->where('domain_uuid', $session->domain_uuid)
            ->whereIn('extension_uuid', $session->extension_uuids)
            ->pluck('extension')
            ->all();

        return array_values(array_unique(array_merge($session->extension_uuids, $numbers)));
    }

    /**
     * Remove spool files no longer needed by an active or unprocessed call.
     */
    public function cleanSpool(): int
    {
        $now = time();
        $keepFrom = $now - self::SPOOL_KEEP_SECONDS;

        $oldestLeg = PcapCallLeg::query()->where('node', $this->node())->whereNull('processed_at')->min('created_epoch');
        if ($oldestLeg) {
            $keepFrom = min($keepFrom, (int) $oldestLeg - 60);
        }

        $oldestChannel = $this->oldestActiveChannelEpoch();
        if ($oldestChannel === false) {
            $keepFrom = min($keepFrom, $now - self::SPOOL_FALLBACK_SECONDS);
        } elseif ($oldestChannel !== null) {
            $keepFrom = min($keepFrom, $oldestChannel - 60);
        }

        $keepFrom = max($keepFrom, $now - self::SPOOL_MAX_SECONDS);

        $chunks = [];
        foreach (glob($this->spoolDir() . '/*.pcap') ?: [] as $path) {
            $chunks[(int) basename($path, '.pcap')] = $path;
        }
        ksort($chunks);
        $starts = array_keys($chunks);

        $deleted = 0;
        foreach ($starts as $index => $start) {
            $next = $starts[$index + 1] ?? null;
            // Never delete the file tcpdump is writing to.
            if ($next !== null && $next < $keepFrom && @unlink($chunks[$start])) {
                $deleted++;
            }
        }

        foreach (glob($this->spoolDir() . '/sip/*.pcap') ?: [] as $path) {
            if (preg_match('/^\d+-(\d+)-/', basename($path), $m) && (int) $m[1] < $keepFrom && @unlink($path)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Oldest live channel start, null when idle, false when FreeSWITCH could not be asked.
     */
    protected function oldestActiveChannelEpoch(): int|null|false
    {
        $channels = $this->activeChannels();
        if ($channels === false) {
            return false;
        }

        $epochs = collect($channels)->pluck('created_epoch')->filter()->map(fn ($epoch) => (int) $epoch);

        return $epochs->isEmpty() ? null : $epochs->min();
    }

    /**
     * Live FreeSWITCH channels, or false when FreeSWITCH could not be asked.
     */
    protected function activeChannels(): array|false
    {
        try {
            $data = app(FreeswitchEslService::class)->executeCommand('show channels as json');
            if (!is_array($data) || isset($data['error'])) {
                return false;
            }

            return array_values($data['rows'] ?? []);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function fileName(array $aLeg, string $callUuid): string
    {
        $part = fn ($value) => trim(preg_replace('/[^A-Za-z0-9+*#._-]+/', '', (string) $value), '.') ?: 'unknown';

        return date('Ymd-His', (int) $aLeg['created_epoch'])
            . '_' . $part($aLeg['caller_id_number'])
            . '_' . $part($aLeg['destination_number'])
            . '_' . substr($callUuid, 0, 8) . '.pcap';
    }

    private function findTool(string $tool): ?string
    {
        foreach (['/usr/bin', '/usr/sbin', '/bin', '/sbin', '/usr/local/bin'] as $dir) {
            if (is_executable("{$dir}/{$tool}")) {
                return "{$dir}/{$tool}";
            }
        }

        return null;
    }
}
