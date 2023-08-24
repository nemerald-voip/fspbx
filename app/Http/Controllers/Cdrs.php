<?php

namespace App\Http\Controllers;

use App\Models\CDR;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class Cdrs extends Controller
{
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

        logger(request()->all());
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

        logger(request()->input('breadcrumbs'));

        return view('layouts.cdrs.index')->with($data);
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
