<?php

namespace App\Http\Controllers;

use App\Exports\ExtensionStatisticsExport;
use App\Http\Requests\ExportExtensionStatisticsRequest;
use App\Http\Requests\ExtensionStatisticsRequest;
use App\Services\CdrDataService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExtensionStatisticsController extends Controller
{
    public function __construct(
        protected CdrDataService $cdrDataService
    ) {}

    public function index(ExtensionStatisticsRequest $request): Response
    {
        $domainUuid = (string) session('domain_uuid');
        $timezone = get_local_time_zone($domainUuid);
        [$startPeriod, $endPeriod] = $this->defaultDateRange($timezone);

        return Inertia::render('ExtensionStatistics', [
            'startPeriod' => $startPeriod->toIso8601String(),
            'endPeriod' => $endPeriod->toIso8601String(),
            'timezone' => $timezone,
            'routes' => [
                'current_page' => route('extension-statistics.index'),
                'data_route' => route('extension-statistics.data'),
                'export' => route('extension-statistics.export'),
            ],
            'permissions' => [
                'export' => userCheckPermission('xml_cdr_export'),
            ],
            'pagination' => [
                'per_page' => fspbx_pagination_per_page($request),
                'per_page_options' => fspbx_pagination_options(),
            ],
        ]);
    }

    public function getData(ExtensionStatisticsRequest $request): LengthAwarePaginator
    {
        return $this->cdrDataService->getExtensionStatistics(
            $this->statisticsParams($request)
        );
    }

    public function export(ExportExtensionStatisticsRequest $request): BinaryFileResponse
    {
        $rows = $this->cdrDataService->getExtensionStatisticsCollection(
            $this->statisticsParams($request)
        );

        return Excel::download(
            new ExtensionStatisticsExport($rows),
            'extension_statistics.csv',
            ExcelWriter::CSV
        );
    }

    private function statisticsParams(ExtensionStatisticsRequest $request): array
    {
        $validated = $request->validated();
        $domainUuid = (string) session('domain_uuid');
        [$startPeriod, $endPeriod] = $this->resolveDateRange(
            data_get($validated, 'filter.dateRange'),
            $domainUuid
        );

        return [
            'domain_uuid' => $domainUuid,
            'paginate' => false,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 50),
            'filter' => [
                'search' => trim((string) data_get($validated, 'filter.search', '')),
                'showGlobal' => false,
                'startPeriod' => $startPeriod->getTimestamp(),
                'endPeriod' => $endPeriod->getTimestamp(),
            ],
        ];
    }

    private function resolveDateRange(?array $dateRange, string $domainUuid): array
    {
        if ($dateRange !== null) {
            return [
                Carbon::parse($dateRange[0])->setTimezone('UTC'),
                Carbon::parse($dateRange[1])->setTimezone('UTC'),
            ];
        }

        return $this->defaultDateRange(get_local_time_zone($domainUuid));
    }

    private function defaultDateRange(string $timezone): array
    {
        $now = Carbon::now($timezone);

        return [
            $now->copy()->startOfDay()->setTimezone('UTC'),
            $now->copy()->endOfDay()->setTimezone('UTC'),
        ];
    }
}
