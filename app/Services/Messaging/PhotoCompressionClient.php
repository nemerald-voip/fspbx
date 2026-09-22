<?php

namespace App\Services\Messaging;

class PhotoCompressionClient
{
    public function available(): bool
    {
        try {
            return ($this->request(['op' => 'ping'], 1)['ok'] ?? false) === true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    public function compress(string $id, int $budget): array
    {
        return $this->prepare(['op' => 'compress', 'id' => $id, 'budget' => $budget]);
    }

    public function convert(string $id): array
    {
        return $this->prepare(['op' => 'convert', 'id' => $id]);
    }

    protected function prepare(array $payload): array
    {
        $result = $this->request($payload, 25);
        if (!($result['ok'] ?? false)) {
            throw new \RuntimeException(match ($result['error'] ?? '') {
                'dimensions' => __('Photo dimensions exceed the supported limit.'),
                'size' => __('The photo could not fit within the message attachment limit.'),
                'animated' => __('Animated or multipage images cannot be converted to a single photo. Attach a still image or a GIF.'),
                'format' => __('This photo format is not supported for conversion.'),
                'timeout' => __('Photo compression exceeded its time limit. Try a smaller photo.'),
                default => __('Photo compression failed or exceeded its memory limit. Try a smaller photo.'),
            });
        }
        return $result;
    }

    protected function request(array $payload, int $timeout): array
    {
        $socket = @stream_socket_client('unix://'.config('message_photos.socket'), $errno, $error, 1);
        if (!$socket) {
            throw new \RuntimeException(__('Photo compression is unavailable. Please contact your administrator.'));
        }
        try {
            stream_set_timeout($socket, $timeout);
            $request = json_encode($payload, JSON_THROW_ON_ERROR)."\n";
            if (fwrite($socket, $request) !== strlen($request)) {
                throw new \RuntimeException(__('Unable to start photo compression.'));
            }
            $line = fgets($socket, 4096);
            $result = $line === false ? null : json_decode($line, true);
            if (!is_array($result)) {
                throw new \RuntimeException(__('Photo compression failed or exceeded its memory limit. Try a smaller photo.'));
            }
            return $result;
        } finally {
            fclose($socket);
        }
    }
}
