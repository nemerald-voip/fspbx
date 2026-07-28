<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenSearchFreeswitchLogService
{
    public function configured(): bool
    {
        return filled(config('services.opensearch_logs.url'))
            && filled(config('services.opensearch_logs.index'))
            && filled(config('services.opensearch_logs.username'))
            && filled(config('services.opensearch_logs.password'));
    }

    public function search(
        Collection $identifiers,
        string $textSearch,
        string $level,
        ?array $timeWindow,
        int $maxLines,
        string $sort,
    ): array {
        if (! $this->configured()) {
            throw new RuntimeException('External FreeSWITCH log search is not configured.');
        }

        $query = $this->buildQuery($identifiers, $level, $timeWindow);
        $response = $this->client()->post($this->searchUrl(), [
            'size' => $maxLines,
            'track_total_hits' => true,
            'sort' => [
                ['@timestamp' => ['order' => $sort, 'unmapped_type' => 'date']],
            ],
            'query' => $query,
        ]);

        if ($response->failed()) {
            $reason = data_get($response->json(), 'error.reason')
                ?: data_get($response->json(), 'error.root_cause.0.reason')
                ?: 'OpenSearch returned HTTP ' . $response->status() . '.';

            throw new RuntimeException($reason);
        }

        $payload = $response->json();
        $hits = collect(data_get($payload, 'hits.hits', []));
        $terms = $identifiers->filter()->unique()->values();
        $lines = $hits->map(function (array $hit) use ($terms) {
            $source = $hit['_source'] ?? [];
            $message = (string) ($source['log'] ?? $source['message'] ?? '');

            return [
                'file' => $hit['_index'] ?? null,
                'line_number' => null,
                'timestamp' => $source['timestamp'] ?? $source['@timestamp'] ?? null,
                'level' => isset($source['level']) ? strtolower((string) $source['level']) : null,
                'message' => $message,
                'matched_terms' => $terms
                    ->filter(fn ($term) => stripos($message, (string) $term) !== false)
                    ->values()
                    ->all(),
                'pbx_node' => $source['pbx_node'] ?? null,
                'index' => $hit['_index'] ?? null,
                'id' => $hit['_id'] ?? null,
            ];
        });

        // OpenSearch analyzers can treat phone numbers and UUID-like strings in
        // surprising ways. "Text contains" is intentionally literal, so apply
        // it to the correlated messages returned by the identifier query.
        if ($textSearch !== '') {
            $lines = $lines
                ->filter(fn (array $line) => stripos($line['message'], $textSearch) !== false)
                ->values();
        }

        return [
            'lines' => $lines->all(),
            'matched_lines' => (int) data_get($payload, 'hits.total.value', $hits->count()),
            'returned_lines' => $lines->count(),
            'took_ms' => (int) ($payload['took'] ?? 0),
            'timed_out' => (bool) ($payload['timed_out'] ?? false),
        ];
    }

    private function buildQuery(
        Collection $identifiers,
        string $level,
        ?array $timeWindow,
    ): array {
        $filter = [];
        $must = [];

        if ($level !== 'all') {
            $filter[] = ['term' => ['level' => $level]];
        }

        if ($timeWindow) {
            $filter[] = [
                'range' => [
                    '@timestamp' => [
                        'gte' => $timeWindow['start'],
                        'lte' => $timeWindow['end'],
                    ],
                ],
            ];
        }

        $identifierClauses = $identifiers
            ->filter()
            ->unique()
            ->flatMap(fn ($identifier) => [
                ['match_phrase' => ['log' => (string) $identifier]],
                ['match_phrase' => ['message' => (string) $identifier]],
            ])
            ->values()
            ->all();

        if ($identifierClauses) {
            $must[] = [
                'bool' => [
                    'should' => $identifierClauses,
                    'minimum_should_match' => 1,
                ],
            ];
        }

        if (! $must && ! $filter) {
            return ['match_all' => (object) []];
        }

        return ['bool' => array_filter([
            'must' => $must,
            'filter' => $filter,
        ])];
    }

    private function client(): PendingRequest
    {
        $verify = filter_var(config('services.opensearch_logs.verify_tls', true), FILTER_VALIDATE_BOOLEAN);
        $caBundle = trim((string) config('services.opensearch_logs.ca_bundle'));

        return Http::acceptJson()
            ->asJson()
            ->withBasicAuth(
                (string) config('services.opensearch_logs.username'),
                (string) config('services.opensearch_logs.password'),
            )
            ->timeout(max((int) config('services.opensearch_logs.timeout', 10), 1))
            ->connectTimeout(max((int) config('services.opensearch_logs.connect_timeout', 3), 1))
            ->withOptions([
                'verify' => $verify ? ($caBundle !== '' ? $caBundle : true) : false,
            ]);
    }

    private function searchUrl(): string
    {
        $baseUrl = rtrim((string) config('services.opensearch_logs.url'), '/');
        $index = trim((string) config('services.opensearch_logs.index'), '/');

        return $baseUrl . '/' . $index . '/_search';
    }
}
