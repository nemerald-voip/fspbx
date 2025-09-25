<?php

namespace App\Http\Controllers;

use App\Models\CDR;
use Inertia\Inertia;
use App\Jobs\ExportCdrs;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\CdrDataService;

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
                    // 'export' => route('cdrs.export'),
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
        try {
            $params['paginate'] = false;
            $params['filterData'] = request()->filterData;
            $params['domain_uuid'] = session('domain_uuid');
            if (session('domains')) {
                $params['domains'] = session('domains')->pluck('domain_uuid');
            }
            $params['searchable'] = $this->searchable;

            if (!empty(request('filterData.dateRange'))) {
                $startPeriod = Carbon::parse(request('filterData.dateRange')[0])->setTimeZone('UTC');
                $endPeriod = Carbon::parse(request('filterData.dateRange')[1])->setTimeZone('UTC');
            } else {
                $domain_uuid = session('domain_uuid');
                $startPeriod = Carbon::now(get_local_time_zone($domain_uuid))->startOfDay()->setTimeZone('UTC');
                $endPeriod = Carbon::now(get_local_time_zone($domain_uuid))->endOfDay()->setTimeZone('UTC');
            }

            $params['filterData']['startPeriod'] = $startPeriod;
            $params['filterData']['endPeriod'] = $endPeriod;
            $params['filterData']['sortField'] = request()->get('sortField', 'start_epoch');
            $params['filterData']['sortOrder'] = request()->get('sortField', 'desc');

            $params['permissions']['xml_cdr_lose_race'] = userCheckPermission('xml_cdr_lose_race');

            $params['user_email'] = auth()->user()->user_email;

            // $cdrs = $this->getData(false); // returns lazy collection

            ExportCdrs::dispatch($params, $this->cdrDataService);

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Report is being generated in the background. We\'ll email you a link when it\'s ready to download.']],
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to export items']]
            ], 500); // 500 Internal Server Error for any other errors
        }

        return response()->json([
            'success' => false,
            'errors' => ['server' => ['Failed to export']]
        ], 500); // 500 Internal Server Error for any other errors
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
