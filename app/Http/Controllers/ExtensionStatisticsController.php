<?php

namespace App\Http\Controllers;

use App\Models\CDR;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\CdrDataService;
use App\Exports\ExtensionStatisticsExport;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;

class ExtensionStatisticsController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'ExtensionStatistics';
    protected $searchable = ['extension.extension', 'extension.effective_caller_id_name'];
    public $item_domain_uuid;
    protected $cdrDataService;

    public function __construct(CdrDataService $cdrDataService)
    {
        $this->cdrDataService = $cdrDataService;
        $this->model = new CDR();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // logger($request->all());
        // Check permissions
        if (!userCheckPermission("xml_cdr_view")) {
            return redirect('/');
        }

        $domain_uuid = session('domain_uuid');
        $startPeriod = Carbon::now(get_local_time_zone($domain_uuid))->startOfDay()->setTimeZone('UTC');
        $endPeriod = Carbon::now(get_local_time_zone($domain_uuid))->endOfDay()->setTimeZone('UTC');

        return Inertia::render(
            $this->viewName,
            [
                'startPeriod' => function () use ($startPeriod) {
                    return $startPeriod;
                },
                'endPeriod' => function ()  use ($endPeriod) {
                    return $endPeriod;
                },
                'timezone' => function () use ($domain_uuid) {
                    return get_local_time_zone($domain_uuid);
                },
                'routes' => [
                    'current_page' => route('extension-statistics.index'),
                    'data_route' => route('extension-statistics.data'),
                    'export' => route('extension-statistics.export'),
                ]

            ]
        );
    }

    //Most of this function has been moved to CdrDataService service container
    public function getData()
    {
        $params = request()->all();
        $params['paginate'] = false;
        $domain_uuid = session('domain_uuid');
        $params['domain_uuid'] = $domain_uuid;

        if (!empty(request('filter.dateRange'))) {
            $startPeriod = Carbon::parse(request('filter.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filter.dateRange')[1])->setTimeZone('UTC');
        }

        $params['filter']['startPeriod'] = $startPeriod->getTimestamp();
        $params['filter']['endPeriod'] = $endPeriod->getTimestamp();

        unset(
            $params['filter']['dateRange'],
        );

        $this->filters = [
            'startPeriod' => $startPeriod,
            'endPeriod' => $endPeriod,
        ];

        // logger($params);

        // Fetch CDR data
        $cdrData = $this->cdrDataService->getExtensionStatistics($params);

        // logger($cdrData);

        return $cdrData;

    }

    // This function has been moved to CdrDataService service container
    // public function builder($filters = [])
    // {
    // }


    /**
     * Get all items
     *
     * @return \Illuminate\Http\Response
     */

        public function export()
        {
            if (!userCheckPermission('xml_cdr_export') && !userCheckPermission('cdrs_export')) {
                abort(403);
            }

            $params = request()->all();
            $params['paginate'] = false;

            $domain_uuid = session('domain_uuid');
            $params['domain_uuid'] = $domain_uuid;

            if (!empty(request('filter.dateRange'))) {
                $startPeriod = Carbon::parse(request('filter.dateRange')[0])->setTimeZone('UTC');
                $endPeriod = Carbon::parse(request('filter.dateRange')[1])->setTimeZone('UTC');
            } else {
                $startPeriod = Carbon::now(get_local_time_zone($domain_uuid))->startOfDay()->setTimeZone('UTC');
                $endPeriod = Carbon::now(get_local_time_zone($domain_uuid))->endOfDay()->setTimeZone('UTC');
            }

            $params['filter']['startPeriod'] = $startPeriod->getTimestamp();
            $params['filter']['endPeriod'] = $endPeriod->getTimestamp();

            unset($params['filter']['dateRange']);

            return Excel::download(
                new ExtensionStatisticsExport($params, $this->cdrDataService),
                'extension_statistics.csv',
                ExcelWriter::CSV
            );
        }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\CDR  $cDR
     * @return \Illuminate\Http\Response
     */
    public function show(CDR $cDR)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\CDR  $cDR
     * @return \Illuminate\Http\Response
     */
    public function edit(CDR $cDR)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\CDR  $cDR
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, CDR $cDR)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\CDR  $cDR
     * @return \Illuminate\Http\Response
     */
    public function destroy(CDR $cDR)
    {
        //
    }
}
