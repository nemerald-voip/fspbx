<?php

namespace App\Http\Controllers;

use App\Models\CDR;
use App\Models\DefaultSettings;
use App\Services\FreeswitchEslService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FreeswitchLogController extends Controller
{
    private const DEFAULT_LOG_DIR = '/var/log/freeswitch';
    private const DEFAULT_SIZE_KB = 102400;
    private const MAX_SIZE_KB = 102400;
    private const DEFAULT_MAX_LINES = 3000;
    private const MAX_LINES = 5000;
    private const DEFAULT_CORRELATION_PADDING_MINUTES = 5;
    private const MAX_CORRELATION_PADDING_MINUTES = 60;
    private const MAX_SIP_TRACE_BLOCK_LINES = 180;

    public function index(Request $request): JsonResponse
    {
        if (! userCheckPermission('log_view')) {
            return response()->json([
                'messages' => ['error' => ['Permission denied.']],
            ], 403);
        }

        $request->merge([
            'whole_call' => filter_var(
                $request->input('whole_call', true),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? true,
        ]);

        $logDirectory = $this->logDirectory();
        $approvedFiles = $this->approvedLogFiles($logDirectory);

        if ($approvedFiles->isEmpty()) {
            return response()->json([
                'files' => [],
                'lines' => [],
                'correlation' => $this->emptyCorrelation(),
                'meta' => [
                    'errors' => ['No FreeSWITCH log files were found.'],
                    'log_dir' => $this->visibleLogDirectory($logDirectory),
                ],
            ]);
        }

        $validated = $request->validate([
            'log_file' => ['nullable', 'string', 'max:255'],
            'seed_uuid' => ['nullable', 'string', 'max:255'],
            'whole_call' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'in:all,debug,info,notice,warning,err,crit,alert'],
            'size_kb' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_SIZE_KB],
            'max_lines' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LINES],
            'sort' => ['nullable', 'string', 'in:asc,desc'],
            'correlation_padding_minutes' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_CORRELATION_PADDING_MINUTES],
        ]);

        $selectedFile = $validated['log_file'] ?? 'freeswitch.log';
        $filesToSearch = $this->selectedFiles($approvedFiles, $selectedFile);

        if ($filesToSearch->isEmpty()) {
            return response()->json([
                'files' => $this->fileOptions($approvedFiles),
                'lines' => [],
                'correlation' => $this->emptyCorrelation($validated['seed_uuid'] ?? null),
                'meta' => [
                    'errors' => ['The selected log file is not available.'],
                    'log_dir' => $this->visibleLogDirectory($logDirectory),
                ],
            ], 422);
        }

        $seed = trim((string) ($validated['seed_uuid'] ?? ''));
        $wholeCall = (bool) ($validated['whole_call'] ?? true);
        $correlationPaddingMinutes = (int) ($validated['correlation_padding_minutes'] ?? self::DEFAULT_CORRELATION_PADDING_MINUTES);
        $correlation = $seed !== ''
            ? $this->correlateCall($seed, $wholeCall, $correlationPaddingMinutes)
            : $this->emptyCorrelation();

        $searchTerms = collect($correlation['uuids'])
            ->merge($correlation['sip_call_ids'])
            ->filter()
            ->unique()
            ->values();

        $result = $this->searchFiles(
            files: $filesToSearch,
            searchTerms: $searchTerms,
            textSearch: trim((string) ($validated['search'] ?? '')),
            level: $validated['level'] ?? 'all',
            sizeKb: (int) ($validated['size_kb'] ?? self::DEFAULT_SIZE_KB),
            maxLines: (int) ($validated['max_lines'] ?? self::DEFAULT_MAX_LINES),
            sort: $validated['sort'] ?? 'asc',
        );

        return response()->json([
            'files' => $this->fileOptions($approvedFiles),
            'lines' => $result['lines'],
            'correlation' => $correlation,
            'filters' => [
                'log_file' => $selectedFile,
                'whole_call' => $wholeCall,
                'size_kb' => (int) ($validated['size_kb'] ?? self::DEFAULT_SIZE_KB),
                'max_lines' => (int) ($validated['max_lines'] ?? self::DEFAULT_MAX_LINES),
                'sort' => $validated['sort'] ?? 'asc',
                'level' => $validated['level'] ?? 'all',
                'correlation_padding_minutes' => $correlationPaddingMinutes,
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

    public function sipTrace(Request $request, FreeswitchEslService $eslService): JsonResponse
    {
        if (! userCheckPermission('log_view')) {
            return response()->json([
                'messages' => ['error' => ['Permission denied.']],
            ], 403);
        }

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        if (! $eslService->isConnected()) {
            return response()->json([
                'messages' => ['error' => ['FreeSWITCH event socket is unavailable.']],
            ], 503);
        }

        $enabled = (bool) $validated['enabled'];
        $commands = $enabled
            ? ['sofia global siptrace on', 'sofia tracelevel info']
            : ['sofia global siptrace off'];
        $sipTraceApplied = false;

        try {
            foreach ($commands as $command) {
                $response = $eslService->executeCommand($command, false);

                if ($this->eslResponseFailed($response)) {
                    if ($enabled && $sipTraceApplied) {
                        $eslService->executeCommand('sofia global siptrace off', false);
                    }

                    return response()->json([
                        'messages' => [
                            'error' => ['FreeSWITCH returned an error.'],
                            'error_1' => [$this->cleanEslError((string) $response)],
                        ],
                    ], 500);
                }

                if ($command === 'sofia global siptrace on') {
                    $sipTraceApplied = true;
                }
            }
        } finally {
            $eslService->disconnect();
        }

        return response()->json([
            'messages' => [
                'success' => [
                    $enabled
                        ? 'SIP packet logging enabled.'
                        : 'SIP packet logging disabled.',
                ],
            ],
            'enabled' => $enabled,
        ]);
    }

    private function logDirectory(): string
    {
        $setting = DefaultSettings::query()
            ->where('default_setting_category', 'switch')
            ->where('default_setting_subcategory', 'log')
            ->where('default_setting_name', 'dir')
            ->where('default_setting_enabled', 'true')
            ->value('default_setting_value');

        $directory = trim((string) ($setting ?: self::DEFAULT_LOG_DIR));

        return rtrim($directory, '/') ?: self::DEFAULT_LOG_DIR;
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

        $files = glob($realDirectory . '/freeswitch.log*') ?: [];

        return collect($files)
            ->filter(fn ($path) => is_file($path) && str_starts_with(basename($path), 'freeswitch.log'))
            ->map(fn ($path) => [
                'path' => $path,
                'basename' => basename($path),
                'size' => filesize($path) ?: 0,
                'modified_at' => filemtime($path) ? Carbon::createFromTimestamp(filemtime($path))->toIso8601String() : null,
                'readable' => is_readable($path),
            ])
            ->sortBy(function ($file) {
                return $file['basename'] === 'freeswitch.log' ? '000000' : $file['basename'];
            })
            ->values();
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
                'label' => 'All rotated logs',
                'size' => $files->sum('size'),
                'modified_at' => null,
                'readable' => $files->contains(fn ($file) => $file['readable']),
            ])
            ->values()
            ->all();
    }

    private function selectedFiles(Collection $files, string $selectedFile): Collection
    {
        if ($selectedFile === 'all') {
            return $files
                ->sortByDesc(fn ($file) => $this->rotationIndex($file['basename']))
                ->values();
        }

        return $files->where('basename', basename($selectedFile))->values();
    }

    private function rotationIndex(string $basename): int
    {
        if ($basename === 'freeswitch.log') {
            return 0;
        }

        if (preg_match('/^freeswitch\.log\.(\d+)$/', $basename, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function correlateCall(string $seed, bool $wholeCall, int $paddingMinutes): array
    {
        $seed = trim($seed);
        $paddingSeconds = min(max($paddingMinutes, 1), self::MAX_CORRELATION_PADDING_MINUTES) * 60;

        if (! $wholeCall) {
            return [
                'seed' => $seed,
                'whole_call' => false,
                'padding_minutes' => null,
                'uuids' => $this->looksLikeUuid($seed) ? [$seed] : [],
                'sip_call_ids' => $this->looksLikeUuid($seed) ? [] : [$seed],
                'cdrs' => [],
                'time_window' => null,
            ];
        }

        $seedRows = $this->seedCdrRows($seed);
        $correlationWindow = $this->timeWindow($seedRows, $paddingSeconds);
        $uuidTerms = collect($this->looksLikeUuid($seed) ? [$seed] : []);
        $sipCallIds = collect($this->looksLikeUuid($seed) ? [] : [$seed]);
        $seen = $seedRows;

        if ($seedRows->isEmpty()) {
            return [
                'seed' => $seed,
                'whole_call' => true,
                'padding_minutes' => (int) ($paddingSeconds / 60),
                'uuids' => $uuidTerms->values()->all(),
                'sip_call_ids' => $sipCallIds->values()->all(),
                'cdrs' => [],
                'time_window' => null,
            ];
        }

        for ($i = 0; $i < 8; $i++) {
            $before = $uuidTerms->count() + $sipCallIds->count();

            $rows = $this->relatedCdrRowsFromTerms($uuidTerms, $sipCallIds, $correlationWindow)
                ->unique('xml_cdr_uuid')
                ->values();

            $seen = $seen->merge($rows);

            $uuidTerms = $uuidTerms
                ->merge($rows->pluck('xml_cdr_uuid'))
                ->merge($rows->pluck('bridge_uuid'))
                ->merge($rows->pluck('originating_leg_uuid'))
                ->merge($rows->pluck('cc_member_session_uuid'))
                ->filter(fn ($value) => $this->looksLikeUuid((string) $value))
                ->unique()
                ->values();

            $sipCallIds = $sipCallIds
                ->merge($rows->pluck('sip_call_id'))
                ->filter()
                ->unique()
                ->values();

            if (($uuidTerms->count() + $sipCallIds->count()) === $before) {
                break;
            }
        }

        $seen = $seen->unique('xml_cdr_uuid')->values();

        return [
            'seed' => $seed,
            'whole_call' => true,
            'padding_minutes' => (int) ($paddingSeconds / 60),
            'uuids' => $uuidTerms->values()->all(),
            'sip_call_ids' => $sipCallIds->values()->all(),
            'cdrs' => $seen->map(fn ($cdr) => [
                'xml_cdr_uuid' => $cdr->xml_cdr_uuid,
                'bridge_uuid' => $cdr->bridge_uuid,
                'originating_leg_uuid' => $cdr->originating_leg_uuid,
                'cc_member_session_uuid' => $cdr->cc_member_session_uuid,
                'sip_call_id' => $cdr->sip_call_id,
                'direction' => $cdr->direction,
                'start_epoch' => $cdr->start_epoch,
                'end_epoch' => $cdr->end_epoch,
            ])->all(),
            'time_window' => $this->timeWindow($seen),
        ];
    }

    private function seedCdrRows(string $seed): Collection
    {
        $uuidSeed = $this->looksLikeUuid($seed);

        if ($uuidSeed) {
            $primary = CDR::query()
                ->select($this->correlationColumns())
                ->where('xml_cdr_uuid', $seed)
                ->limit(1)
                ->get();

            if ($primary->isNotEmpty()) {
                return $primary;
            }
        }

        return CDR::query()
            ->select($this->correlationColumns())
            ->where(function ($query) use ($seed, $uuidSeed) {
                if ($uuidSeed) {
                    $query->where('bridge_uuid', $seed)
                        ->orWhere('originating_leg_uuid', $seed)
                        ->orWhere('cc_member_session_uuid', $seed);
                }

                $query->orWhere('sip_call_id', $seed);
            })
            ->limit(250)
            ->get();
    }

    private function relatedCdrRowsFromTerms(Collection $uuids, Collection $sipCallIds, ?array $timeWindow): Collection
    {
        if ($uuids->isEmpty() && $sipCallIds->isEmpty()) {
            return collect();
        }

        $query = CDR::query()
            ->select($this->correlationColumns())
            ->where(function ($query) use ($uuids, $sipCallIds) {
                if ($uuids->isNotEmpty()) {
                    $query->whereIn('xml_cdr_uuid', $uuids)
                        ->orWhereIn('bridge_uuid', $uuids)
                        ->orWhereIn('originating_leg_uuid', $uuids)
                        ->orWhereIn('cc_member_session_uuid', $uuids);
                }

                if ($sipCallIds->isNotEmpty()) {
                    $query->orWhereIn('sip_call_id', $sipCallIds);
                }
            });

        if ($timeWindow) {
            $this->applyCdrTimeWindow($query, $timeWindow);
        }

        return $query
            ->limit(500)
            ->get();
    }

    private function correlationColumns(): array
    {
        return [
            'xml_cdr_uuid',
            'bridge_uuid',
            'originating_leg_uuid',
            'cc_member_session_uuid',
            'sip_call_id',
            'direction',
            'start_epoch',
            'end_epoch',
        ];
    }

    private function applyCdrTimeWindow($query, array $timeWindow): void
    {
        $start = (int) $timeWindow['start_epoch'];
        $end = (int) $timeWindow['end_epoch'];

        $query->where(function ($query) use ($start, $end) {
            $query->whereBetween('start_epoch', [$start, $end])
                ->orWhereBetween('end_epoch', [$start, $end])
                ->orWhere(function ($query) use ($start, $end) {
                    $query->where('start_epoch', '<=', $start)
                        ->where('end_epoch', '>=', $end);
                });
        });
    }

    private function timeWindow(Collection $cdrs, int $paddingSeconds = 60): ?array
    {
        $starts = $cdrs->pluck('start_epoch')->filter(fn ($value) => is_numeric($value));
        $ends = $cdrs->pluck('end_epoch')->filter(fn ($value) => is_numeric($value));

        if ($starts->isEmpty() && $ends->isEmpty()) {
            return null;
        }

        $start = (int) ($starts->min() ?: $ends->min());
        $end = (int) ($ends->max() ?: $starts->max());

        return [
            'start_epoch' => max($start - $paddingSeconds, 0),
            'end_epoch' => $end + $paddingSeconds,
            'start' => Carbon::createFromTimestamp(max($start - $paddingSeconds, 0), 'UTC')->toIso8601String(),
            'end' => Carbon::createFromTimestamp($end + $paddingSeconds, 'UTC')->toIso8601String(),
        ];
    }

    private function searchFiles(
        Collection $files,
        Collection $searchTerms,
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
        $matchedLines = 0;

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
            $entries = [];

            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $bytesRead += strlen($line);
                $line = rtrim($line, "\r\n");

                $parsed = $this->parseLine($line);
                $entries[] = [
                    'file' => $file['basename'],
                    'line_number' => $lineNumber,
                    'timestamp' => $parsed['timestamp'],
                    'level' => $parsed['level'],
                    'message' => $line,
                    'matched_terms' => [],
                ];
            }

            fclose($handle);

            $sipTraceBlocks = $this->sipTraceBlocks($entries);
            $included = [];

            foreach ($entries as $index => $entry) {
                if (! $this->matchesLine($entry['message'], $entry['level'], $searchTerms, $textSearch, $level)) {
                    continue;
                }

                $block = $sipTraceBlocks[$index] ?? ['start' => $index, 'end' => $index];

                for ($blockIndex = $block['start']; $blockIndex <= $block['end']; $blockIndex++) {
                    if (! isset($entries[$blockIndex])) {
                        continue;
                    }

                    $lineKey = $entries[$blockIndex]['line_number'];
                    if (isset($included[$lineKey])) {
                        continue;
                    }

                    $included[$lineKey] = true;
                    $entries[$blockIndex]['matched_terms'] = $this->matchedTerms($entries[$blockIndex]['message'], $searchTerms);
                    $lines[] = $entries[$blockIndex];
                    $matchedLines++;

                    if (count($lines) > $maxLines) {
                        array_shift($lines);
                    }
                }
            }
        }

        $truncated = $matchedLines > $maxLines;

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

    private function sipTraceBlocks(array $entries): array
    {
        $blocks = [];
        $entryCount = count($entries);

        for ($index = 0; $index < $entryCount; $index++) {
            if (! $this->isSipTraceStart($entries[$index]['message'])) {
                continue;
            }

            $end = $index;
            $boundaryCount = 0;
            $maxEnd = min($entryCount - 1, $index + self::MAX_SIP_TRACE_BLOCK_LINES);

            for ($blockIndex = $index + 1; $blockIndex <= $maxEnd; $blockIndex++) {
                if ($this->isSipTraceStart($entries[$blockIndex]['message'])) {
                    $end = $blockIndex - 1;
                    break;
                }

                if ($blockIndex > $index + 1 && $boundaryCount < 2 && $this->isTimestampedLogLine($entries[$blockIndex]['message'])) {
                    $end = $blockIndex - 1;
                    break;
                }

                $end = $blockIndex;

                if ($this->isSipTraceBoundary($entries[$blockIndex]['message'])) {
                    $boundaryCount++;

                    if ($boundaryCount >= 2) {
                        break;
                    }
                }
            }

            for ($blockIndex = $index; $blockIndex <= $end; $blockIndex++) {
                $blocks[$blockIndex] = ['start' => $index, 'end' => $end];
            }

            $index = max($index, $end);
        }

        return $blocks;
    }

    private function isSipTraceStart(string $line): bool
    {
        return (bool) preg_match('/\b(?:send|recv)\s+\d+\s+bytes\s+(?:to|from)\s+/i', $line);
    }

    private function isSipTraceBoundary(string $line): bool
    {
        return (bool) preg_match('/^-{20,}$/', trim($line));
    }

    private function isTimestampedLogLine(string $line): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}(?:\.\d+)?\s+\[[A-Z]+\]/', $line);
    }

    private function eslResponseFailed($response): bool
    {
        return $response === null || str_starts_with(ltrim((string) $response), '-ERR');
    }

    private function cleanEslError(string $message): string
    {
        return trim(preg_replace('/^-ERR\s*/', '', $message)) ?: 'FreeSWITCH returned no response.';
    }

    private function parseLine(string $line): array
    {
        preg_match('/^(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}(?:\.\d+)?)/', $line, $timestampMatch);
        preg_match('/\[(DEBUG|INFO|NOTICE|WARNING|ERR|CRIT|ALERT)\]/i', $line, $levelMatch);

        return [
            'timestamp' => $timestampMatch[1] ?? null,
            'level' => isset($levelMatch[1]) ? strtolower($levelMatch[1]) : null,
        ];
    }

    private function matchesLine(string $line, ?string $lineLevel, Collection $terms, string $textSearch, string $level): bool
    {
        if ($level !== 'all' && $lineLevel !== $level) {
            return false;
        }

        if ($terms->isNotEmpty() && ! $this->containsAny($line, $terms)) {
            return false;
        }

        if ($textSearch !== '' && stripos($line, $textSearch) === false) {
            return false;
        }

        return true;
    }

    private function containsAny(string $haystack, Collection $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && stripos($haystack, (string) $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function matchedTerms(string $line, Collection $terms): array
    {
        return $terms
            ->filter(fn ($term) => $term !== '' && stripos($line, (string) $term) !== false)
            ->values()
            ->all();
    }

    private function emptyCorrelation(?string $seed = null): array
    {
        return [
            'seed' => $seed,
            'whole_call' => false,
            'padding_minutes' => null,
            'uuids' => [],
            'sip_call_ids' => [],
            'cdrs' => [],
            'time_window' => null,
        ];
    }

    private function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value);
    }
}
