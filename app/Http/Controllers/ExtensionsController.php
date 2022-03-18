<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Extentions;
use App\Models\Destinations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ExtensionsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Display page with Caller ID options.
     *
     * @return \Illuminate\Http\Response
     */
    public function callerId()
    {
        // Get all active phone numbers 
        $destinations = Destinations::where('destination_enabled', 'true')
            ->where ('domain_uuid', Session::get('domain_uuid'))
            ->get([
                'destination_uuid',
                'destination_number',
                'destination_enabled',
                'destination_description'
            ])
            ->toArray();

        // Get logged user model and extensions associated with it
        // $user = User::where('user_uuid', Session::get('user.user_uuid'))->first();
        // $extensions = $user->extensions();

        

        return view('layouts.extensions.callerid');
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
     * @param  \App\Models\Extentions  $extentions
     * @return \Illuminate\Http\Response
     */
    public function show(Extentions $extentions)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Extentions  $extentions
     * @return \Illuminate\Http\Response
     */
    public function edit(Extentions $extentions)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Extentions  $extentions
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Extentions $extentions)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Extentions  $extentions
     * @return \Illuminate\Http\Response
     */
    public function destroy(Extentions $extentions)
    {
        //
    }
}
