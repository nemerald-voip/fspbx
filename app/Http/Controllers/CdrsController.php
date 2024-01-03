<?php

namespace App\Http\Controllers;

use App\Models\CDR;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class CdrsController extends Controller
{
    public $filters;
    public $sortField;
    public $sortOrder;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("extension_view")) {
            return redirect('/');
        }

        //Check FusionPBX login status
        session_start();
        if (!isset($_SESSION['user'])) {
            return redirect()->route('logout');
        }


        if (!empty($request->filterData['dateRange'])) {
            $startPeriod = $request->filterData['dateRange'][0];
            $endPeriod = $request->filterData['dateRange'][1];
        } else {
            $startPeriod = Carbon::now($this->getTimezone())->startOfDay()->setTimeZone('UTC');
            $endPeriod = Carbon::now($this->getTimezone())->endOfDay()->setTimeZone('UTC');
        }

        $this->filters = [
            'startPeriod' => $startPeriod,
            'endPeriod' => $endPeriod
        ];

        // Check if search parameter is present and not empty
        if (!empty($request->filterData['search'])) {
            $this->filters['search'] = $request->filterData['search'];
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'start_epoch'); // Default to 'start_epoch'
        $this->sortOrder = request()->get('sortOrder', 'desc'); // Default to ascending


        // return view('layouts.cdrs.index')->with($data);


        return Inertia::render(
            'Cdrs',
            [
                'data' => function () {
                    return $this->getCdrs();
                },
                'menus' => function () {
                    return Session::get('menu');
                },
                'domainSelectPermission' => function () {
                    return Session::get('domain_select');
                },
                'selectedDomain' => function () {
                    return Session::get('domain_name');
                },
                'selectedDomainUuid' => function () {
                    return Session::get('domain_uuid');
                },
                'domains' => function () {
                    return Session::get("domains");
                },
                'startPeriod' => function () {
                    return $this->filters['startPeriod'];
                },
                'endPeriod' => function () {
                    return $this->filters['endPeriod'];
                },
                'timezone' => function () {
                    return $this->getTimezone();
                }

            ]
        );
    }

    public function getCdrs()
    {
        $cdrs = $this->builder($this->filters)->get();
        // foreach ($cdrs as $cdr) {
        //     logger($cdr->start_date);

        // }

        $cdrs = $cdrs->map(function ($cdr) {
            // Perform any additional processing on start_date if needed
            // For example, format start_date or add additional data

            // Add or modify attributes as needed
            $cdr->start_date = $cdr->start_date;
            $cdr->start_time = $cdr->start_time;

            return $cdr;
        });
        return $cdrs;
    }

    public function builder($filters = [])
    {

        $cdrs =  CDR::query()
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->select(
                'xml_cdr_uuid',
                'direction',
                'caller_id_name',
                'caller_id_number',
                'caller_destination',
                'destination_number',
                'domain_uuid',
                'extension_uuid',
                'sip_call_id',
                'source_number',
                'start_stamp',
                'start_epoch',
                'answer_stamp',
                'answer_epoch',
                'end_epoch',
                'end_stamp',
                'duration',
                'record_path',
                'record_name',
                'leg',
                'voicemail_message',
                'missed_call',
                'call_center_queue_uuid',
                'cc_side',
                'cc_queue_joined_epoch',
                'cc_queue',
                'cc_agent',
                'cc_agent_bridged',
                'cc_queue_answered_epoch',
                'cc_queue_terminated_epoch',
                'cc_queue_canceled_epoch',
                'cc_cancel_reason',
                'cc_cause',
                'waitsec',
                'hangup_cause',
                'hangup_cause_q850',
                'sip_hangup_disposition',
                'status'
            );

        //exclude legs that were not answered
        if (!userCheckPermission('xml_cdr_lose_race')) {
            $cdrs->where('hangup_cause', '!=', 'LOSE_RACE');
        }

        foreach ($filters as $field => $value) {
            if (method_exists($this, $method = "filter" . ucfirst($field))) {
                $this->$method($cdrs, $value);
            }
        }

        // Apply sorting
        $cdrs->orderBy($this->sortField, $this->sortOrder);

        return $cdrs;
    }

    protected function getTimezone()
    {
        if (!Cache::has(auth()->user()->user_uuid . '_timeZone')) {
            $timezone = get_local_time_zone(Session::get('domain_uuid'));
            Cache::put(auth()->user()->user_uuid . '_timeZone', $timezone, 600);
        } else {
            $timezone = Cache::get(auth()->user()->user_uuid . '_timeZone');
        }
        return $timezone;
    }

    protected function filterStartPeriod($query, $value)
    {
        $query->where('start_stamp', '>=', $value);
    }

    protected function filterEndPeriod($query, $value)
    {
        $query->where('start_stamp', '<=', $value);
    }

    protected function filterSearch($query, $value)
    {
        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value) {
            $query->where('caller_id_name', 'ilike', '%' . $value . '%')
                ->orWhere('caller_id_number', 'ilike', '%' . $value . '%')
                ->orWhere('caller_destination', 'ilike', '%' . $value . '%')
                ->orWhere('destination_number', 'ilike', '%' . $value . '%');
        });
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