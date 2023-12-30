<?php

namespace App\Http\Controllers;

use App\Models\CDR;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class Cdrs extends Controller
{
    public $filters;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
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

        $data = [];
        $data['page_title'] = "Call Detail Records";
        $data['breadcrumbs'] = [
            'Dashboard' => 'dashboard',
            'Call Detail Records' => ''
        ];
        $data['period'] = request()->get('period');
        // $period = periodHelper(request()->get('period'), Cache::get(auth()->user()->user_uuid.'_timeZone'));


        // Check if the request has the 'breadcrumbs' variable
        if (request()->has('breadcrumbs')) {
            // If the 'breadcrumbs' variable exists in the request, update the $data array
            $data['breadcrumbs'] = request()->input('breadcrumbs');
            $data['breadcrumbs']['Call Detail Records'] = '';
        }

        $this->filters = [
            'sourceNumber' => '123456789',
            'dateFrom' => '2023-12-25',
            'dateTo' => '2023-12-31'
        ];


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


            ]
        );
    }

    public function getCdrs()
    {
        return $this->builder($this->filters)->get();
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
                'sip_hangup_disposition'
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
    }

    protected function filterDateFrom($query, $value)
    {
        $startLocal = Carbon::createFromFormat('Y-m-d', $value, $this->getTimezone())->startOfDay();
        $startUTC = $startLocal->setTimezone('UTC');
        $query->where('start_stamp', '>=', $startUTC);
    }

    protected function filterDateTo($query, $value)
    {
        $endLocal = Carbon::createFromFormat('Y-m-d', $value, $this->getTimezone())->endOfDay();
        $endUTC = $endLocal->setTimezone('UTC');
        $query->where('start_stamp', '<=', $endUTC);
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
