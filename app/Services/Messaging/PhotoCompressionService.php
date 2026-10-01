<?php

namespace App\Services\Messaging;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessSignaledException;

class PhotoCompressionService
{
    public function available(): bool
    {
        if (!$this->hasRuntime()) return false;
        try {
            $deadline = microtime(true) + 3;
            $version = $this->run([config('message_photos.vips_binary'), '--version'], base_path(), $deadline);
            if (!preg_match('/vips-(\d+\.\d+\.\d+)/', $version, $matches)
                || version_compare($matches[1], '8.13.0', '<')) return false;
            $operations = $this->run([config('message_photos.vips_binary'), '-l', 'foreign'], base_path(), $deadline);
            foreach (['jpegload', 'pngload', 'webpload', 'heifload', 'tiffload', 'jpegsave'] as $operation) {
                if (!str_contains($operations, '('.$operation.')')) return false;
            }
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function compress(string $id, int $budget): array
    {
        if ($budget < 16000 || $budget > config('message_photos.budget_bytes')) {
            throw new RuntimeException(__('The photo could not fit within the message attachment limit.'));
        }
        return $this->prepare($id, $budget);
    }

    public function convert(string $id): array
    {
        return $this->prepare($id, null);
    }

    protected function hasRuntime(): bool
    {
        foreach (['vips_binary', 'vipsheader_binary', 'nice_binary', 'prlimit_binary'] as $binary) {
            if (!is_executable((string) config('message_photos.'.$binary))) return false;
        }
        return true;
    }

    protected function prepare(string $id, ?int $budget): array
    {
        if (!$this->hasRuntime()) {
            throw new RuntimeException(__('Photo compression is unavailable. Please contact your administrator.'));
        }
        $directory = config('message_photos.spool').'/'.$id;
        $input = $directory.'/input';
        if (!Str::isUuid($id) || is_link($directory) || !is_dir($directory)
            || is_link($input) || !is_file($input)
            || file_exists($directory.'/output.jpg') || is_link($directory.'/output.jpg')) {
            throw new RuntimeException(__('Unable to start photo compression.'));
        }
        $deadline = microtime(true) + (float) config('message_photos.process_timeout');
        $bytes = filesize($input);
        $maxBytes = min(50 * 1024 * 1024, (int) config('message_photos.max_input_bytes'));
        if (!$bytes || $bytes > $maxBytes) {
            throw new RuntimeException(__('The photo exceeds the 50 MB input limit.'));
        }
        $format = $this->inputFormat($input);
        // A fixed private filename prevents libvips options in uploaded names.
        // Read the summary only: embedded text metadata is never parsed as fields.
        $summary = $this->run([config('message_photos.vipsheader_binary'), 'input[access=sequential,fail_on=error]'], $directory, $deadline);
        if (!preg_match('/\Ainput: (\d+)x(\d+) [^,\r\n]+, \d+ bands?, [a-zA-Z0-9_-]+, ([a-z0-9_]+)\s*\z/', $summary, $matches)
            || $matches[3] !== $format.'load') $this->failed();
        $width = (int) $matches[1];
        $height = (int) $matches[2];
        if ($width <= 0 || $height <= 0 || max($width, $height) > 30000 || $width * $height > 100000000) {
            throw new RuntimeException(__('Photo dimensions exceed the supported limit.'));
        }
        if (in_array($format, ['webp', 'heif', 'tiff'], true)) {
            $pages = $this->run([config('message_photos.vipsheader_binary'), '-f', 'n-pages', 'input[access=sequential,fail_on=error]'], $directory, $deadline, true);
            if (!preg_match('/\A\d+\s*\z/', $pages)) $this->failed();
            if ((int) $pages > 1) {
                throw new RuntimeException(__('Animated or multipage images cannot be converted to a single photo. Attach a still image or a GIF.'));
            }
        }
        if (($format === 'jpeg' && $bytes <= ($budget ?? $maxBytes)) || ($format === 'png' && $budget === null)) {
            return ['ok' => true, 'unchanged' => true, 'bytes' => $bytes, 'encodes' => 0];
        }

        $edge = $budget === null ? max($width, $height) : min(1600, max($width, $height));
        $this->thumbnail($directory, $edge, $budget === null ? 90 : 80, $deadline);
        $size = $this->outputBytes($directory);
        $encodes = 1;
        if ($budget !== null && $size > $budget) {
            $scale = min(0.8, sqrt($budget / $size) * 0.85);
            // Decode the original again at the smaller size. Never recompress
            // the first lossy JPEG or spill an uncompressed intermediate image.
            $this->thumbnail($directory, max(1, (int) round($edge * $scale)), 70, $deadline);
            $size = $this->outputBytes($directory);
            $encodes++;
        }
        if ($size > ($budget ?? $maxBytes)) {
            throw new RuntimeException(__('The photo could not fit within the message attachment limit.'));
        }

        return ['ok' => true, 'bytes' => $size, 'encodes' => $encodes];
    }

    protected function thumbnail(string $directory, int $edge, int $quality, float $deadline): void
    {
        $this->run([
            config('message_photos.vips_binary'), 'thumbnail', 'input[fail_on=error]',
            "output.jpg[Q={$quality},strip,optimize_coding=false,interlace=false,subsample_mode=on,background=255 255 255]",
            (string) $edge, '--height', (string) $edge, '--size', 'down', '--export-profile', 'srgb',
        ], $directory, $deadline);
    }

    protected function outputBytes(string $directory): int
    {
        $path = $directory.'/output.jpg';
        clearstatcache(true, $path);
        $info = is_file($path) && !is_link($path) ? @getimagesize($path) : false;
        if (!$info || $info[2] !== IMAGETYPE_JPEG) $this->failed();
        return filesize($path);
    }

    protected function run(array $command, string $directory, float $deadline, bool $optionalPages = false): string
    {
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) $this->timedOut();
        $process = null;
        $outputBytes = 0;
        $cleaningUp = false;
        $output = function (string $type, string $buffer) use (&$outputBytes, &$process, &$cleaningUp) {
            $outputBytes += strlen($buffer);
            // start() can read output before returning the process handle.
            // During cleanup, drain remaining output without throwing again.
            if ($outputBytes > 65536 && $process && !$cleaningUp) $this->failed();
        };
        try {
            $pending = Process::path($directory)->env([
                'VIPS_CONCURRENCY' => '1', 'OMP_NUM_THREADS' => '1', 'VIPS_BLOCK_UNTRUSTED' => '1',
                'TMPDIR' => $directory, 'MALLOC_ARENA_MAX' => '2', 'LC_ALL' => 'C',
            ]);
            // Laravel 10's timeout() casts fractions to int (zero disables it).
            // Preserve the shared deadline through PendingProcess's public option.
            $pending->timeout = $remaining;
            $process = $pending->start([
                config('message_photos.nice_binary'), '-n', '10', config('message_photos.prlimit_binary'),
                '--as='.(int) config('message_photos.process_memory_bytes'),
                '--cpu='.(int) ceil($remaining), '--fsize=67108864', '--core=0', '--', ...$command,
                '--vips-cache-max=0',
            ], $output);
            if ($outputBytes > 65536) $this->failed();
            $result = $process->wait();
            if (!$result->successful()) {
                if ($optionalPages && $result->exitCode() === 1
                    && trim($result->errorOutput()) === 'vipsheader: vips_image_get: field "n-pages" not found') return '1';
                $this->failed();
            }
            return $result->output();
        } catch (ProcessTimedOutException) {
            $this->timedOut();
        } catch (ProcessSignaledException) {
            $this->failed();
        } finally {
            $cleaningUp = true;
            if ($process && $process->running()) {
                $process->signal(SIGKILL);
                try { $process->wait(); } catch (ProcessSignaledException|ProcessTimedOutException) {}
            }
        }
    }

    protected function inputFormat(string $input): string
    {
        $header = file_get_contents($input, false, null, 0, 32);
        return match (true) {
            str_starts_with($header, "\xff\xd8\xff") => 'jpeg',
            str_starts_with($header, "\x89PNG\r\n\x1a\n") => 'png',
            substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WEBP' => 'webp',
            substr($header, 4, 4) === 'ftyp' && in_array(substr($header, 8, 4), [
                'heic', 'heix', 'hevc', 'hevx', 'mif1', 'msf1', 'avif', 'avis',
            ], true) => 'heif',
            in_array(substr($header, 0, 4), ["II\x2a\x00", "MM\x00\x2a", "II\x2b\x00", "MM\x00\x2b"], true) => 'tiff',
            default => throw new RuntimeException(__('This photo format is not supported for conversion.')),
        };
    }

    protected function timedOut(): never
    {
        throw new RuntimeException(__('Photo compression exceeded its time limit. Try a smaller photo.'));
    }

    protected function failed(): never
    {
        throw new RuntimeException(__('Photo compression failed or exceeded its memory limit. Try a smaller photo.'));
    }
}
