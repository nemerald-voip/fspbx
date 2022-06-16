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
use Illuminate\Support\Facades\Validator;
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

        // Check permissions
        if (!userCheckPermission("extension_view")){
            return redirect('/');
        }

        // Get all extensions
        $extensions = Extensions::where ('domain_uuid', Session::get('domain_uuid'))
        ->get()
        ->sortBy('extension');
        // ->toArray();

        //Get libphonenumber object
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        foreach($extensions as $extension) {
            if ($extension['outbound_caller_id_number']){
                $phoneNumberObject = $phoneNumberUtil->parse($extension['outbound_caller_id_number'], 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)){
                    $extension->outbound_caller_id_number = $phoneNumberUtil
                        ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL);
                }
            }
        }

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
    public function show(Extensions $extensions)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  guid  $extention
     * @return \Illuminate\Http\Response
     */
    public function edit($extension_uuid)
    {

        //check permissions
	    if (!userCheckPermission('extension_add') || !userCheckPermission('extension_edit')) {
            return redirect('/');
	    }

        // get the extension
        $extension = Extensions::find($extension_uuid);

        // Get all phone numbers
        $destinations = Destinations::where('destination_enabled', 'true')
        ->where ('domain_uuid', Session::get('domain_uuid'))
        ->where ('destination_enabled', 'true')
        ->get([
            'destination_uuid',
            'destination_number',
            'destination_enabled',
            'destination_description',
            DB::Raw("coalesce(destination_description , '') as destination_description"),
        ])
        ->sortBy('destination_number');
        // dd($destinations);

        // dd($extension);
        return view('layouts.extensions.createOrUpdate')
            -> with('extension',$extension)
            -> with('domain_users',$extension->domain->users)
            -> with('extension_users',$extension->users())
            -> with('destinations',$destinations)
            -> with('national_phone_number_format',PhoneNumberFormat::NATIONAL);
            
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Extentions  $extentions
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Extensions $extension)
    {

        $attributes = [
            'directory_first_name' => 'first name',
            'directory_last_name' => 'last name',
            'extension' =>'extension number',
            'voicemail_mail_to' => 'email address',
            'users' => 'users field',
            'voicemail_password' => 'voicemail pin',
            'outbound_caller_id_number' => 'external caller ID'
        ];

        $validator = Validator::make($request->all(), [
            'directory_first_name' => 'required|string',
            'directory_last_name' => 'nullable|string',
            'extension' =>'required|numeric',
            'voicemail_mail_to' => 'required|email:rfc,dns',
            'users' => 'nullable|array',
            'directory_visible' => 'present',
            'directory_exten_visible' => 'present',
            'enabled' => 'present',
            'description' => "string|max:100",
            'outbound_caller_id_number' => "present",
            'emergency_caller_id_number' => 'present',
            
            'voicemail_id' => 'present',
            'voicemail_enabled' => "present",
            'call_timeout' => "numeric",
            'voicemail_password' => 'numeric|digits_between:3,10',
            'voicemail_file' => "present",

            

        ], [], $attributes);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();
        $attributes['effective_caller_id_name'] = $attributes['directory_first_name'] . " " . $attributes['directory_last_name'];
        $attributes['effective_caller_id_number'] = $attributes['extension'];
        if ($attributes['directory_visible']== "on")  $attributes['directory_visible'] = "true";
        if ($attributes['directory_exten_visible']== "on")  $attributes['directory_exten_visible'] = "true";
        if ($attributes['enabled']== "on")  $attributes['enabled'] = "true";
        if ($attributes['voicemail_enabled']== "on")  $attributes['voicemail_enabled'] = "true";

        // Check if voicemail directory needs to be renamed 
        if($attributes['voicemail_id'] != $attributes['extension']) {
            if (file_exists(getDefaultSetting('switch','voicemail')."/default/".Session::get('domain_name')."/".$attributes['voicemail_id'])) {
                rename(
                    getDefaultSetting('switch','voicemail')."/default/".Session::get('domain_name')."/".$attributes['voicemail_id'],
                    getDefaultSetting('switch','voicemail')."/default/".Session::get('domain_name')."/".$attributes['extension']
                );
            }
            $attributes['voicemail_id'] = $attributes['extension'];

        }

        // Delete cache and update extension
        if (session_status() == PHP_SESSION_NONE  || session_id() == '') {
            session_start();
        }
        $cache = new cache;
        $cache->delete("directory:".$extension->extension."@".$extension->user_context);
        $extension->voicemail->update($attributes);
        $extension->update($attributes);
        dd($extension->voicemail);

        //clear the destinations session array
        if (isset($_SESSION['destinations']['array'])) {
            unset($_SESSION['destinations']['array']);
        }

        return back()->with("success", "Extension saved");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Extentions  $extentions
     * @return \Illuminate\Http\Response
     */
    public function destroy(Extensions $extensions)
    {
        //
    }

}
