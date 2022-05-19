<?php

namespace App\Http\Controllers;

use cache;
use App\Models\User;
use App\Models\Extensions;
use App\Models\Destinations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Support\Facades\Session;
use Propaganistas\LaravelPhone\PhoneNumber;

class ExtensionsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Get all extensions
        $extensions = Extensions::where ('domain_uuid', Session::get('domain_uuid'))
        ->get()
        ->sortBy('extension')
        ->toArray();

        //dd($extensions);
        
        return view('layouts.extensions.list')
        ->with("extensions",$extensions);
        // ->with("conn_params", $conn_params);
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
                'destination_description',
                DB::Raw("coalesce(destination_description , 'n/a') as destination_description"),
            ])
            ->sortBy('destination_description')
            ->toArray();

        // Get logged user model and extensions associated with it
        $user = User::where('user_uuid', Session::get('user.user_uuid'))->first();
        $extensions = $user->extensions();

        //check if any of the extentions already have caller IDs assigend to them
        // if yes add TRUE column to the new array $phone_numbers
        $phone_numbers = array();
        foreach ($extensions as $extension){
            foreach ($destinations as $destination){
                if ($destination['destination_number'] == $extension->outbound_caller_id_number){
                    $destination['isCallerID'] = true;
                    $phone_numbers[] = $destination;
                } else {
                    $destination['isCallerID'] = false;
                    $phone_numbers[] = $destination;
                }

            }
        }

        // $format = PhoneNumberFormat::NATIONAL;
        // $phone_number = phone("6467052267","US",$format);
        // dd($phone_numbers);

        return view('layouts.extensions.callerid')
            ->with('destinations',$phone_numbers)
            ->with('national_phone_number_format',PhoneNumberFormat::NATIONAL);
    }

    /**
     * Update caller ID for user.
     *
     * @return \Illuminate\Http\Response
     */
    public function updateCallerID($id)
    {
        $destination = Destinations::find($id);
        if (!$destination){
            return response()->json([
                'error' => 401,
                'message' => 'Invalid phone number ID submitted']);
        }

        //Get logged in user and all extensions that belong to him
        $user = User::where('user_uuid', Session::get('user.user_uuid'))->first();
        $extensions = $user->extensions();

        // Update the caller ID field for each extension
        // If successful delete cache
        if (session_status() == PHP_SESSION_NONE  || session_id() == '') {
            session_start();
        }

        $cache = new cache;
        foreach ($extensions as $extension){
            $ext_model = Extensions::find($extension->extension_uuid);
            $ext_model->outbound_caller_id_number = $destination->destination_number;
            $ext_model->save();
            // dd($extension);
            $cache->delete("directory:".$extension->extension."@".$extension->user_context);
        }

        // If successful return success status
        if ($ext_model->outbound_caller_id_number = $destination->destination_number){
            return response()->json([
                'extension' => $ext_model->extension,
                'callerID' => $destination->destination_number,
                'message' => 'Caller ID sucesfully updated',
            ]);
        // Otherwise return failed status
        } else {
            return response()->json([
                'error' => 401,
                'message' => 'Unable to update Caller ID']);
        }

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
    public function show(Extensions $extentions)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Extentions  $extentions
     * @return \Illuminate\Http\Response
     */
    public function edit(Extensions $extentions)
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
    public function update(Request $request, Extensions $extentions)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Extentions  $extentions
     * @return \Illuminate\Http\Response
     */
    public function destroy(Extensions $extentions)
    {
        //
    }
}
