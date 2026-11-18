<?php

// A disposable loopback LDAP endpoint for exercising PHP's real LDAP extension.
// Only bind/search/unbind are implemented. Unconfigured bases return noSuchObject.
$configuration = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (! $server) {
    throw new RuntimeException($error);
}
echo stream_socket_get_name($server, false), "\n";
flush();
$socket = stream_socket_accept($server, 10);
if (! $socket) {
    exit(1);
}
stream_set_timeout($socket, 10);

function ber(int $tag, string $value): string
{
    $length = strlen($value);
    return chr($tag) . ($length < 128 ? chr($length) : "\x82" . pack('n', $length)) . $value;
}

function take(string &$buffer): array
{
    $tag = ord($buffer[0]);
    $length = ord($buffer[1]);
    $offset = 2;
    if ($length & 128) {
        $bytes = $length & 127;
        $length = 0;
        for ($index = 0; $index < $bytes; $index++) {
            $length = ($length << 8) | ord($buffer[$offset++]);
        }
    }
    $value = substr($buffer, $offset, $length);
    $buffer = substr($buffer, $offset + $length);
    return [$tag, $value];
}

function readBytes($socket, int $length): string
{
    $buffer = '';
    while (strlen($buffer) < $length) {
        $chunk = fread($socket, $length - strlen($buffer));
        if ($chunk === false || $chunk === '') {
            exit(0);
        }
        $buffer .= $chunk;
    }
    return $buffer;
}

function reply($socket, string $id, int $tag, string $value): void
{
    fwrite($socket, ber(0x30, ber(0x02, $id) . ber($tag, $value)));
}

function result(int $code): string
{
    return ber(0x0a, chr($code)) . ber(0x04, '') . ber(0x04, '');
}

while (true) {
    $header = readBytes($socket, 2);
    $length = ord($header[1]);
    if ($length & 128) {
        $bytes = readBytes($socket, $length & 127);
        $length = 0;
        foreach (str_split($bytes) as $byte) {
            $length = ($length << 8) | ord($byte);
        }
    }
    $message = readBytes($socket, $length);
    [, $id] = take($message);
    [$operation, $payload] = take($message);
    if ($operation === 0x60) {
        reply($socket, $id, 0x61, result($configuration['bind_code'] ?? 0));
    } elseif ($operation === 0x63) {
        [, $base] = take($payload);
        [, $scope] = take($payload);
        take($payload); // dereference policy
        [, $sizeLimit] = take($payload);
        take($payload); // time limit
        take($payload); // types only
        [$filterTag, $filter] = take($payload);
        [, $attributes] = take($payload);
        $attributeNames = [];
        while ($attributes !== '') {
            [, $attributeNames[]] = take($attributes);
        }
        echo json_encode(['base' => $base, 'scope' => ord($scope), 'limit' => ord($sizeLimit), 'attributes' => $attributeNames]), "\n";
        flush();
        $response = $configuration['bases'][$base] ?? ['code' => 32];
        for ($index = 0; $index < ($response['entries'] ?? 0); $index++) {
            reply($socket, $id, 0x64, ber(0x04, 'cn=entry' . $index . ',' . $base) . ber(0x30, ''));
        }
        reply($socket, $id, 0x65, result($response['code'] ?? 0));
    } elseif ($operation === 0x42) {
        break;
    } else {
        exit(2);
    }
}
fclose($socket);
fclose($server);
