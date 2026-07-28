<?php

namespace Tests\Unit;

use App\Services\OpenSearchFreeswitchLogService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenSearchFreeswitchLogServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.opensearch_logs.url' => 'https://search.test:9200',
            'services.opensearch_logs.index' => 'fs-pbx-freeswitch-*',
            'services.opensearch_logs.username' => 'search-user',
            'services.opensearch_logs.password' => 'search-pass',
            'services.opensearch_logs.verify_tls' => false,
            'services.opensearch_logs.timeout' => 30,
            'services.opensearch_logs.connect_timeout' => 3,
        ]);
    }

    public function test_it_searches_identifiers_inside_the_cdr_window_and_maps_hits(): void
    {
        Http::fake(fn () => Http::response([
            'took' => 12,
            'timed_out' => false,
            'hits' => [
                'total' => ['value' => 1, 'relation' => 'eq'],
                'hits' => [[
                    '_index' => 'fs-pbx-freeswitch-2026.07.22',
                    '_id' => 'hit-1',
                    '_source' => [
                        '@timestamp' => '2026-07-22T00:10:00Z',
                        'timestamp' => '2026-07-22 00:10:00.000000',
                        'level' => 'NOTICE',
                        'pbx_node' => 'pbxdev',
                        'log' => 'Call 11111111-2222-3333-4444-555555555555 entered routing.',
                    ],
                ]],
            ],
        ], 200));

        $result = (new OpenSearchFreeswitchLogService())->search(
            identifiers: collect(['11111111-2222-3333-4444-555555555555']),
            textSearch: 'entered routing',
            level: 'notice',
            timeWindow: [
                'start' => '2026-07-22T00:09:00Z',
                'end' => '2026-07-22T00:11:00Z',
            ],
            maxLines: 100,
            sort: 'asc',
        );

        $this->assertSame(1, $result['matched_lines']);
        $this->assertSame(12, $result['took_ms']);
        $this->assertSame('notice', $result['lines'][0]['level']);
        $this->assertSame('pbxdev', $result['lines'][0]['pbx_node']);
        $this->assertSame(['11111111-2222-3333-4444-555555555555'], $result['lines'][0]['matched_terms']);

        Http::assertSent(function ($request) {
            $query = $request['query'];

            return $request->method() === 'POST'
                && $request->url() === 'https://search.test:9200/fs-pbx-freeswitch-*/_search'
                && $request->hasHeader('Authorization')
                && $request['size'] === 100
                && data_get($query, 'bool.filter.0.term.level') === 'notice'
                && data_get($query, 'bool.filter.1.range.@timestamp.gte') === '2026-07-22T00:09:00Z'
                && count(data_get($query, 'bool.must', [])) === 1
                && data_get($query, 'bool.must.0.bool.minimum_should_match') === 1;
        });
    }

    public function test_text_search_literally_filters_correlated_results_without_using_the_index_analyzer(): void
    {
        Http::fake(fn () => Http::response([
            'hits' => [
                'total' => ['value' => 2],
                'hits' => [
                    [
                        '_index' => 'fs-pbx-freeswitch-2026.07.22',
                        '_id' => 'hit-1',
                        '_source' => ['log' => 'Dialing destination 3592000 for correlated call.'],
                    ],
                    [
                        '_index' => 'fs-pbx-freeswitch-2026.07.22',
                        '_id' => 'hit-2',
                        '_source' => ['log' => 'Another correlated call line.'],
                    ],
                ],
            ],
        ], 200));

        $result = (new OpenSearchFreeswitchLogService())->search(
            identifiers: collect(['11111111-2222-3333-4444-555555555555']),
            textSearch: '3592000',
            level: 'all',
            timeWindow: [
                'start' => '2026-07-22T00:09:00Z',
                'end' => '2026-07-22T00:11:00Z',
            ],
            maxLines: 100,
            sort: 'asc',
        );

        $this->assertCount(1, $result['lines']);
        $this->assertStringContainsString('3592000', $result['lines'][0]['message']);

        Http::assertSent(function ($request) {
            $query = $request['query'];
            $must = data_get($query, 'bool.must', []);

            return count($must) === 1
                && data_get($must, '0.bool.minimum_should_match') === 1
                && data_get($query, 'bool.filter.0.range.@timestamp.gte') === '2026-07-22T00:09:00Z';
        });
    }

    public function test_it_uses_match_all_when_no_filters_are_supplied(): void
    {
        Http::fake(fn () => Http::response([
            'hits' => ['total' => ['value' => 0], 'hits' => []],
        ], 200));

        (new OpenSearchFreeswitchLogService())->search(
            identifiers: collect(),
            textSearch: '',
            level: 'all',
            timeWindow: null,
            maxLines: 25,
            sort: 'desc',
        );

        Http::assertSent(fn ($request) => isset($request['query']['match_all']));
    }
}
