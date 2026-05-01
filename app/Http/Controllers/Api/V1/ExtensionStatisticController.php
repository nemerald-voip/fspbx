<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\CdrDataService;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Data\Api\V1\ExtensionStatisticData;
use App\Data\Api\V1\ExtensionStatisticListResponseData;

class ExtensionStatisticController extends Controller
{
    public function __construct(
        protected CdrDataService $cdrDataService
    ) {}

    /**
     * List extension statistics
     *
     * Returns extension-level call statistics for the specified domain.
     *
     * Access rules:
     * - Caller must have access to the target domain (domain scope).
     * - Caller must have the `xml_cdr_view` permission.
     *
     * Pagination (cursor-based):
     * - Both `limit` and `starting_after` are optional.
     * - If `limit` is not provided, it defaults to 50.
     * - If `starting_after` is not provided, results start from the beginning.
     *
     * Optional filters:
     * - `search` filters by extension number, extension label, and related call fields.
     * - `date_from` and `date_to` filter the reporting window using epoch seconds in UTC.
     * - If no date range is provided, the current day in the domain's local timezone is used.
     *
     * @group Extension Statistics
     * @authenticated
     *
     * @urlParam domain_uuid string required The domain UUID. Example: 7d58342b-2b29-4dcf-92d6-e9a9e002a4e5
     * @queryParam limit integer Optional. Number of results to return (min 1, max 100). Defaults to 50. Example: 50
     * @queryParam starting_after string Optional. Return results after this extension UUID. Example: c9a76140-0ca4-4ea3-95af-7e12c2ff0df5
     * @queryParam search string Optional. Search extension number, label, and related call fields. Example: 1001
     * @queryParam date_from integer Optional. Start of date range in epoch seconds (UTC). Example: 1777507200
     * @queryParam date_to integer Optional. End of date range in epoch seconds (UTC). Example: 1777593599
     *
     * @response 200 scenario="Success" {
     *   "object": "list",
     *   "url": "/api/v1/domains/7d58342b-2b29-4dcf-92d6-e9a9e002a4e5/extension-statistics",
     *   "has_more": false,
     *   "data": [
     *     {
     *       "extension_uuid": "c9a76140-0ca4-4ea3-95af-7e12c2ff0df5",
     *       "object": "extension_statistic",
     *       "domain_uuid": "7d58342b-2b29-4dcf-92d6-e9a9e002a4e5",
     *       "extension": "1001",
     *       "extension_label": "Front Desk",
     *       "call_count": 18,
     *       "inbound": 10,
     *       "outbound": 7,
     *       "missed": 1,
     *       "total_duration_seconds": 945,
     *       "total_duration_formatted": "00:15:45",
     *       "total_talk_time_seconds": 945,
     *       "total_talk_time_formatted": "00:15:45",
     *       "average_duration_seconds": 53,
     *       "average_duration_formatted": "00:00:53"
     *     }
     *   ]
     * }
     *
     * @response 400 scenario="Invalid domain UUID" {"error":{"type":"invalid_request_error","message":"Invalid domain UUID.","code":"invalid_request","param":"domain_uuid"}}
     * @response 400 scenario="Invalid starting_after UUID" {"error":{"type":"invalid_request_error","message":"Invalid starting_after UUID.","code":"invalid_request","param":"starting_after"}}
     * @response 400 scenario="Invalid date_from" {"error":{"type":"invalid_request_error","message":"Invalid date_from value.","code":"invalid_request","param":"date_from"}}
     * @response 400 scenario="Invalid date_to" {"error":{"type":"invalid_request_error","message":"Invalid date_to value.","code":"invalid_request","param":"date_to"}}
     * @response 400 scenario="Invalid date range" {"error":{"type":"invalid_request_error","message":"date_from must be less than or equal to date_to.","code":"invalid_request","param":"date_from"}}
     * @response 401 scenario="Unauthenticated" {"error":{"type":"authentication_error","message":"Unauthenticated.","code":"unauthenticated"}}
     * @response 404 scenario="Domain not found" {"error":{"type":"invalid_request_error","message":"Domain not found.","code":"resource_missing","param":"domain_uuid"}}
     */
    public function index(Request $request, string $domain_uuid)
    {
        $user = $request->user();
        if (! $user) {
            throw new ApiException(401, 'authentication_error', 'Unauthenticated.', 'unauthenticated');
        }

        $domain = $this->findDomainOrFail($domain_uuid);

        $startingAfter = (string) $request->input('starting_after', '');
        if ($startingAfter !== '' && ! preg_match('/^[0-9a-fA-F-]{36}$/', $startingAfter)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid starting_after UUID.', 'invalid_request', 'starting_after');
        }

        $limit = (int) $request->input('limit', 50);
        $limit = max(1, min(100, $limit));

        [$startPeriod, $endPeriod] = $this->resolveDateRange($request, $domain_uuid);

        $result = $this->cdrDataService->getApiExtensionStatistics([
            'domain_uuid' => $domain->domain_uuid,
            'paginate' => false,
            'limit' => $limit,
            'starting_after' => $startingAfter,
            'filter' => [
                'search' => (string) $request->input('search', ''),
                'showGlobal' => false,
                'startPeriod' => $startPeriod,
                'endPeriod' => $endPeriod,
            ],
        ]);

        $data = collect($result['data'])
            ->map(fn($row) => $this->toExtensionStatisticData($row, $domain_uuid))
            ->all();

        $payload = new ExtensionStatisticListResponseData(
            object: 'list',
            url: "/api/v1/domains/{$domain_uuid}/extension-statistics",
            has_more: (bool) ($result['has_more'] ?? false),
            data: $data,
        );

        return response()->json($payload->toArray(), 200);
    }

    protected function findDomainOrFail(string $domain_uuid): Domain
    {
        if (! preg_match('/^[0-9a-fA-F-]{36}$/', $domain_uuid)) {
            throw new ApiException(400, 'invalid_request_error', 'Invalid domain UUID.', 'invalid_request', 'domain_uuid');
        }

        $domain = Domain::query()
            ->where('domain_uuid', $domain_uuid)
            ->first(['domain_uuid']);

        if (! $domain) {
            throw new ApiException(404, 'invalid_request_error', 'Domain not found.', 'resource_missing', 'domain_uuid');
        }

        return $domain;
    }

    protected function resolveDateRange(Request $request, string $domain_uuid): array
    {
        $timezone = get_local_time_zone($domain_uuid);

        $startPeriod = Carbon::now($timezone)->startOfDay()->setTimezone('UTC');
        $endPeriod = Carbon::now($timezone)->endOfDay()->setTimezone('UTC');

        if ($request->filled('date_from')) {
            $dateFrom = $request->input('date_from');
            if (! is_numeric($dateFrom)) {
                throw new ApiException(400, 'invalid_request_error', 'Invalid date_from value.', 'invalid_request', 'date_from');
            }

            $startPeriod = Carbon::createFromTimestamp((int) $dateFrom, 'UTC');
        }

        if ($request->filled('date_to')) {
            $dateTo = $request->input('date_to');
            if (! is_numeric($dateTo)) {
                throw new ApiException(400, 'invalid_request_error', 'Invalid date_to value.', 'invalid_request', 'date_to');
            }

            $endPeriod = Carbon::createFromTimestamp((int) $dateTo, 'UTC');
        }

        if ($startPeriod->gt($endPeriod)) {
            throw new ApiException(400, 'invalid_request_error', 'date_from must be less than or equal to date_to.', 'invalid_request', 'date_from');
        }

        return [
            $startPeriod->getTimestamp(),
            $endPeriod->getTimestamp(),
        ];
    }

    protected function toExtensionStatisticData(array $row, string $domain_uuid): ExtensionStatisticData
    {
        return new ExtensionStatisticData(
            extension_uuid: (string) ($row['extension_uuid'] ?? ''),
            object: 'extension_statistic',
            domain_uuid: $domain_uuid,
            extension: $row['extension'] ?? null,
            extension_label: $row['extension_label'] ?? null,
            call_count: (int) ($row['call_count'] ?? 0),
            inbound: (int) ($row['inbound'] ?? 0),
            outbound: (int) ($row['outbound'] ?? 0),
            missed: (int) ($row['missed'] ?? 0),
            total_duration_seconds: (int) ($row['total_duration'] ?? 0),
            total_duration_formatted: (string) ($row['total_duration_formatted'] ?? '00:00:00'),
            total_talk_time_seconds: (int) ($row['total_talk_time'] ?? 0),
            total_talk_time_formatted: (string) ($row['total_talk_time_formatted'] ?? '00:00:00'),
            average_duration_seconds: (int) floor((float) ($row['average_duration'] ?? 0)),
            average_duration_formatted: (string) ($row['average_duration_formatted'] ?? '00:00:00'),
        );
    }
}
