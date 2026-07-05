<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LaravelLogController extends Controller
{
    private const DEFAULT_LOG_FILE = 'laravel.log';
    private const DEFAULT_SIZE_KB = 5120;
    private const MAX_SIZE_KB = 10240;
    private const DEFAULT_MAX_LINES = 200;
    private const MAX_LINES = 5000;

    public function index(Request $request): JsonResponse
    {
        if (! userCheckPermission('log_view')) {
            return response()->json([
                'messages' => ['error' => ['Permission denied.']],
            ], 403);
        }

        $logDirectory = $this->logDirectory();
        $approvedFiles = $this->approvedLogFiles($logDirectory);

        if ($approvedFiles->isEmpty()) {
            return response()->json([
                'files' => [],
                'lines' => [],
                'meta' => [
                    'errors' => ['No Laravel log files were found.'],
                    'log_dir' => $this->visibleLogDirectory($logDirectory),
                ],
            ]);
        }

        $validated = $request->validate([
            'log_file' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'in:all,debug,info,notice,warning,err,crit,alert'],
            'size_kb' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_SIZE_KB],
            'max_lines' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LINES],
            'sort' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $selectedFile = $validated['log_file'] ?? $this->defaultLogFile($approvedFiles);
        $filesToSearch = $this->selectedFiles($approvedFiles, $selectedFile);

        if ($filesToSearch->isEmpty()) {
            return response()->json([
                'files' => $this->fileOptions($approvedFiles),
                'lines' => [],
                'meta' => [
                    'errors' => ['The selected log file is not available.'],
                    'log_dir' => $this->visibleLogDirectory($logDirectory),
                ],
            ], 422);
        }

        $result = $this->searchFiles(
            files: $filesToSearch,
            textSearch: trim((string) ($validated['search'] ?? '')),
            level: $validated['level'] ?? 'all',
            sizeKb: (int) ($validated['size_kb'] ?? self::DEFAULT_SIZE_KB),
            maxLines: (int) ($validated['max_lines'] ?? self::DEFAULT_MAX_LINES),
            sort: $validated['sort'] ?? 'asc',
        );

        return response()->json([
            'files' => $this->fileOptions($approvedFiles),
            'lines' => $result['lines'],
            'filters' => [
                'log_file' => $selectedFile,
                'search' => $validated['search'] ?? '',
                'level' => $validated['level'] ?? 'all',
                'size_kb' => (int) ($validated['size_kb'] ?? self::DEFAULT_SIZE_KB),
                'max_lines' => (int) ($validated['max_lines'] ?? self::DEFAULT_MAX_LINES),
                'sort' => $validated['sort'] ?? 'asc',
            ],
            'meta' => [
                'bytes_read' => $result['bytes_read'],
                'files_searched' => $filesToSearch->pluck('basename')->values(),
                'log_dir' => $this->visibleLogDirectory($logDirectory),
                'matched_lines' => $result['matched_lines'],
                'truncated_matches' => $result['truncated_matches'],
                'errors' => $result['errors'],
            ],
        ]);
    }

    private function logDirectory(): string
    {
        return storage_path('logs');
    }

    private function visibleLogDirectory(string $logDirectory): ?string
    {
        return userCheckPermission('log_path_view') ? $logDirectory : null;
    }

    private function approvedLogFiles(string $logDirectory): Collection
    {
        $realDirectory = realpath($logDirectory);

        if (! $realDirectory || ! is_dir($realDirectory)) {
            return collect();
        }

        $files = glob($realDirectory . '/*') ?: [];

        return collect($files)
            ->filter(fn ($path) => is_file($path) && $this->isApprovedBasename(basename($path)))
            ->map(fn ($path) => [
                'path' => $path,
                'basename' => basename($path),
                'size' => filesize($path) ?: 0,
                'modified_at' => filemtime($path) ? Carbon::createFromTimestamp(filemtime($path))->toIso8601String() : null,
                'readable' => is_readable($path),
            ])
            ->sortBy(fn ($file) => $this->sortKey($file['basename']))
            ->values();
    }

    private function isApprovedBasename(string $basename): bool
    {
        return (bool) preg_match('/^laravel(?:-\d{4}-\d{2}-\d{2})?\.log(?:\.\d+)?$/', $basename);
    }

    private function sortKey(string $basename): string
    {
        if ($basename === self::DEFAULT_LOG_FILE) {
            return '00-' . $basename;
        }

        if (preg_match('/^laravel-(\d{4})-(\d{2})-(\d{2})\.log/', $basename, $matches)) {
            return '01-' . (99999999 - (int) ($matches[1] . $matches[2] . $matches[3]));
        }

        return '10-' . $basename;
    }

    private function fileOptions(Collection $files): array
    {
        return $files
            ->map(fn ($file) => [
                'value' => $file['basename'],
                'label' => $file['basename'],
                'size' => $file['size'],
                'modified_at' => $file['modified_at'],
                'readable' => $file['readable'],
            ])
            ->prepend([
                'value' => 'all',
                'label' => 'All Laravel logs',
                'size' => $files->sum('size'),
                'modified_at' => null,
                'readable' => $files->contains(fn ($file) => $file['readable']),
            ])
            ->values()
            ->all();
    }

    private function defaultLogFile(Collection $files): string
    {
        return $files->contains(fn ($file) => $file['basename'] === self::DEFAULT_LOG_FILE)
            ? self::DEFAULT_LOG_FILE
            : (string) ($files->first()['basename'] ?? self::DEFAULT_LOG_FILE);
    }

    private function selectedFiles(Collection $files, string $selectedFile): Collection
    {
        if ($selectedFile === 'all') {
            return $files->values();
        }

        return $files->where('basename', basename($selectedFile))->values();
    }

    private function searchFiles(
        Collection $files,
        string $textSearch,
        string $level,
        int $sizeKb,
        int $maxLines,
        string $sort,
    ): array {
        $limitBytes = min(max($sizeKb, 1), self::MAX_SIZE_KB) * 1024;
        $maxLines = min(max($maxLines, 1), self::MAX_LINES);
        $lines = [];
        $errors = [];
        $bytesRead = 0;

        foreach ($files as $file) {
            if (! $file['readable']) {
                $errors[] = $file['basename'] . ' is not readable by the web server.';
                continue;
            }

            $handle = @fopen($file['path'], 'rb');
            if (! $handle) {
                $errors[] = 'Unable to open ' . $file['basename'] . '.';
                continue;
            }

            $fileSize = $file['size'];
            $offset = max($fileSize - $limitBytes, 0);

            if ($offset > 0) {
                fseek($handle, $offset);
                fgets($handle);
            }

            $lineNumber = 0;

            while (($lineOffset = ftell($handle)) !== false && ($line = fgets($handle)) !== false) {
                $lineNumber++;
                $bytesRead += strlen($line);
                $line = rtrim($line, "\r\n");
                $parsed = $this->parseLine($line);

                if (! $this->matchesLine($line, $parsed['level'], $textSearch, $level)) {
                    continue;
                }

                $lines[] = [
                    'file' => $file['basename'],
                    'line_number' => $lineNumber,
                    'byte_offset' => $lineOffset,
                    'timestamp' => $parsed['timestamp'],
                    'level' => $parsed['level'],
                    'message' => $line,
                ];
            }

            fclose($handle);
        }

        $matchedLines = count($lines);
        $truncated = $matchedLines > $maxLines;

        if ($truncated) {
            $lines = array_slice($lines, -$maxLines);
        }

        if ($sort === 'desc') {
            $lines = array_reverse($lines);
        }

        return [
            'lines' => $lines,
            'bytes_read' => $bytesRead,
            'matched_lines' => $matchedLines,
            'truncated_matches' => $truncated,
            'errors' => $errors,
        ];
    }

    private function parseLine(string $line): array
    {
        preg_match('/^\[(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\]\s+\w+\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY):/i', $line, $match);

        if (! $match) {
            return [
                'timestamp' => null,
                'level' => null,
            ];
        }

        return [
            'timestamp' => $match[1],
            'level' => $this->normalizeLevel($match[2]),
        ];
    }

    private function normalizeLevel(string $level): string
    {
        return match (strtolower($level)) {
            'debug' => 'debug',
            'info' => 'info',
            'notice' => 'notice',
            'warning' => 'warning',
            'error' => 'err',
            'critical', 'emergency' => 'crit',
            'alert' => 'alert',
            default => 'info',
        };
    }

    private function matchesLine(string $line, ?string $lineLevel, string $textSearch, string $level): bool
    {
        if ($level !== 'all' && $lineLevel !== $level) {
            return false;
        }

        if ($textSearch !== '' && stripos($line, $textSearch) === false) {
            return false;
        }

        return true;
    }
}
