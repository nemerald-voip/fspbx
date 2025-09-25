<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\WhCall;
use App\Jobs\ExportCdrs;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class InboundWebhooksController extends Controller
{
    protected $viewName = 'InboundWebhooks';


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
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
                    // 'current_page' => route('inbound-webhhooks.index'),
                    'data_route' => route('inbound-webhooks.data'),
                ]

            ]
        );
    }

    public function getData()
    {
        $params = request()->all();
        $params['paginate'] = 50;
        $domain_uuid = session('domain_uuid');
        $params['domain_uuid'] = $domain_uuid;

        if (!empty(request('filter.dateRange'))) {
            $startPeriod = Carbon::parse(request('filter.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filter.dateRange')[1])->setTimeZone('UTC');
        }

        $params['filter']['startPeriod'] = $startPeriod;
        $params['filter']['endPeriod'] = $endPeriod;

        unset(
            $params['filter']['dateRange'],
        );

        // $this->filters = [
        //     'startPeriod' => $startPeriod,
        //     'endPeriod' => $endPeriod,
        // ];

        // logger($params);

        $webhooks = QueryBuilder::for(WhCall::class, request()->merge($params))
            ->select([
                'id',
                'name',
                'url',
                'headers',
                'payload',
                'exception',
                'created_at',
            ])
         
            ->allowedFilters([
                AllowedFilter::callback('startPeriod', function ($query, $value) {
                    $query->where('created_at', '>=', $value);
                }),
                AllowedFilter::callback('endPeriod', function ($query, $value) {
                    $query->where('created_at', '<=', $value);
                }),
                AllowedFilter::callback('name', function ($query, $value) {
                    $query->where('name',  $value);
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('payload', 'ilike', "%{$value}%")
                            ->orWhere('exception', 'ilike', "%{$value}%");
                    });
                }),
            ])
            // Sorting
            ->allowedSorts(['created_at']) // add more if needed
            ->defaultSort('-created_at');

        if ($params['paginate']) {
            $webhooks = $webhooks->paginate($params['paginate']);
        } else {
            $webhooks = $webhooks->cursor();
        }

        // logger($webhooks);

        return $webhooks;

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
