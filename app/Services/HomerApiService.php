<?php

namespace App\Services;

use App\Models\CDR;
use App\Models\HomerApiSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class HomerApiService
{
    private const MAX_BYTES = 16777216;

    public static function canView(): bool
    {
        // PCAP permission grants the full correlated trace; the controller scopes the selected CDR.
        return userCheckPermission('xml_cdr_view')
            && userCheckPermission('xml_cdr_view_all_records')
            && userCheckPermission('xml_cdr_pcap');
    }

    public static function canManage(): bool
    {
        return isSuperAdmin() && userCheckPermission('homer_api_manage');
    }

    public function settings(): HomerApiSettings
    {
        return Schema::hasTable('homer_api_settings')
            ? (HomerApiSettings::find(HomerApiSettings::CONNECTION_UUID)
                ?? new HomerApiSettings(['homer_api_setting_uuid' => HomerApiSettings::CONNECTION_UUID]))
            : new HomerApiSettings(['homer_api_setting_uuid' => HomerApiSettings::CONNECTION_UUID]);
    }

    public function publicSettings(): array
    {
        $settings = $this->settings();

        return [
            'enabled' => (bool) $settings->enabled,
            'base_url' => $settings->base_url ?? '',
            'has_api_token' => filled($settings->getRawOriginal('api_token')),
        ];
    }

    public static function validBaseUrl(string $url): bool
    {
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && ($parts['scheme'] ?? '') === 'https'
            && ! empty($parts['host'])
            && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! isset($parts['query']) && ! isset($parts['fragment'])
            && ! preg_match('/[\x00-\x20\x7f\\\\]/', $url);
    }

    public function configured(array $data): HomerApiSettings
    {
        $settings = $this->settings();
        $url = rtrim((string) ($data['base_url'] ?? ''), '/');
        $token = $data['api_token'] ?? null;

        if (filled($token)) {
            $settings->api_token = $token;
        } elseif ($url !== rtrim((string) $settings->base_url, '/') || ($settings->auth_type ?? 'auth_token') !== 'auth_token') {
            // Never forward a saved credential to a changed destination.
            $settings->api_token = null;
        }

        $settings->fill(['enabled' => $data['enabled'], 'base_url' => $url, 'auth_type' => 'auth_token']);
        if ($settings->enabled && (! $url || ! $settings->api_token)) {
            throw ValidationException::withMessages(['api_token' => [__('Enter an API token for this HOMER connection.')]]);
        }

        return $settings;
    }

    public function testConnection(HomerApiSettings $settings): void
    {
        // Exercise the authenticated read path, without retrieving a real call.
        $this->messages($settings, [
            'session_id' => 'fspbx-connection-test-'.Str::uuid(),
            'proto_type' => 1, 'event_type' => 'call',
            'timestamp' => ['from' => (time() - 60) * 1000, 'to' => time() * 1000],
        ]);
    }

    public function callRequest(CDR $cdr): array
    {
        $callId = trim((string) $cdr->sip_call_id);
        $start = (int) $cdr->start_epoch;
        $end = max((int) $cdr->end_epoch, $start + max(0, (int) $cdr->duration));
        if ($callId === '' || strlen($callId) > 1024 || preg_match('/[\x00-\x20\x7f]/', $callId)
            || $start <= 0 || $end - $start > 604800) {
            throw new RuntimeException(__('This call does not have usable SIP capture lookup details.'));
        }

        return [
            'session_id' => $callId, 'proto_type' => 1, 'event_type' => 'call',
            'timestamp' => ['from' => max(1, $start - 60) * 1000, 'to' => ($end + 60) * 1000],
        ];
    }

    public function trace(CDR $cdr, bool $download = false, string $format = 'pcap'): array|string
    {
        if (! in_array($format, ['pcap', 'txt'], true)) {
            throw new RuntimeException(__('Unsupported SIP capture export format.'));
        }
        $settings = $this->settings();
        if (! $settings->enabled) {
            throw new RuntimeException(__('The HOMER Call History integration is disabled.'));
        }
        $request = $this->callRequest($cdr);
        $messages = $this->messages($settings, $request);
        if (! $messages) {
            throw new RuntimeException(__('No SIP capture was found. It may not have been captured or may have expired.'));
        }

        if (! $download) {
            return $messages;
        }

        // HOMER's export does not run Lua correlation. Resolve the legs through
        // /messages first and pass the resulting exact IDs to the export endpoint.
        $ids = array_values(array_unique(array_column($messages, 'session_id')));
        if (count($ids) > 50 || in_array('', $ids, true)) {
            throw new RuntimeException(__('The SIP trace contains too many or invalid call legs to export.'));
        }
        if ($format === 'txt') {
            // Export the server's complete result, never a browser-selected message or page.
            $text = "SIP trace\r\n"
                ."CDR: ".$cdr->xml_cdr_uuid."\r\n"
                ."Messages: ".count($messages)." | Call legs: ".count($ids)."\r\n"
                ."Lookup window (Unix milliseconds): ".$request['timestamp']['from'].' - '.$request['timestamp']['to']."\r\n"
                ."All captured correlated messages within this call's lookup window. SIP signaling only.\r\n\r\n";
            foreach ($messages as $index => $message) {
                $text .= '--- Message '.($index + 1)." ---\r\n"
                    .$message['timestamp']." UTC\r\n"
                    .$message['src_ip'].':'.$message['src_port'].' -> '.$message['dst_ip'].':'.$message['dst_port']."\r\n"
                    .'Call-ID: '.$message['session_id']."\r\n\r\n"
                    .$message['payload']."\r\n\r\n";
                if (strlen($text) > self::MAX_BYTES) {
                    throw new RuntimeException(__('This SIP trace is too large to display or export safely.'));
                }
            }
            return $text;
        }
        unset($request['session_id']);
        $request['session_ids'] = $ids;
        $body = $this->request($settings, 'transactions/export/pcap', $request);
        if (strlen($body) <= 24 || ! in_array(bin2hex(substr($body, 0, 4)), ['d4c3b2a1', 'a1b2c3d4', '4d3cb2a1', 'a1b23c4d'], true)) {
            throw new RuntimeException(__('HOMER returned an invalid or empty PCAP file.'));
        }

        return $body;
    }

    private function messages(HomerApiSettings $settings, array $request): array
    {
        $data = json_decode($this->request($settings, 'transactions/messages', $request), true);
        $rows = $data['data']['items'] ?? null;
        // HOMER represents an empty result as null on some builds.
        if (is_array($data['data'] ?? null) && array_key_exists('items', $data['data']) && $rows === null) {
            return [];
        }
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new RuntimeException(__('HOMER returned an unexpected SIP trace response.'));
        }
        if (count($rows) >= 10000) {
            throw new RuntimeException(__('This SIP trace is too large to display or export safely.'));
        }

        return array_map(function ($row) {
            if (! is_array($row)) {
                throw new RuntimeException(__('HOMER returned an unexpected SIP trace response.'));
            }
            $message = [];
            foreach (['session_id', 'timestamp', 'src_ip', 'src_port', 'dst_ip', 'dst_port', 'payload'] as $key) {
                if (isset($row[$key]) && ! is_scalar($row[$key])) {
                    throw new RuntimeException(__('HOMER returned an unexpected SIP trace response.'));
                }
                $message[$key] = (string) ($row[$key] ?? '');
            }
            return $message;
        }, $rows);
    }

    private function request(HomerApiSettings $settings, string $path, array $payload): string
    {
        if (! self::validBaseUrl((string) $settings->base_url) || ! $settings->api_token
            || $settings->auth_type !== 'auth_token') {
            throw new RuntimeException(__('Configure the HOMER API URL and token first.'));
        }

        try {
            $client = Http::connectTimeout(5)->timeout(30)->withOptions([
                'allow_redirects' => false,
                'verify' => true,
                'decode_content' => false,
                'progress' => function ($total, $downloaded) {
                    if (max($total, $downloaded) > self::MAX_BYTES) {
                        throw new RuntimeException('Response exceeds size limit.');
                    }
                },
            ])->withHeaders(['Accept-Encoding' => 'identity']);
            $client = $client->withHeaders(['Auth-Token' => $settings->api_token]);
            $response = $client->post(rtrim($settings->base_url, '/').'/api/v4/'.$path, $payload);
            if (! $response->successful() || strlen($response->body()) > self::MAX_BYTES) {
                throw new RuntimeException('Upstream request failed.');
            }
            return $response->body();
        } catch (\Throwable $exception) {
            // Do not log upstream bodies, tokens, SIP payloads, or credentialed URLs.
            throw new RuntimeException(__('Unable to retrieve data from HOMER. Check the connection, API token, and server limits.'));
        }
    }
}
