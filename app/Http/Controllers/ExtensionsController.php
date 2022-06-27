<?php

namespace App\Http\Controllers;

use cache;
use App\Models\User;
use App\Models\Extensions;
use App\Models\Voicemails;
use App\Models\Destinations;
use Illuminate\Http\Request;
use App\Models\ExtensionUser;
use App\Models\NemeraldAppUsers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\VoicemailDestinations;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Propaganistas\LaravelPhone\PhoneNumber;
use App\Models\DefaultSettings;
use App\Models\FreeswitchSettings;

class ExtensionsController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth')->except(['callerId','updateCallerID']);
    }

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
    public function callerId(Request $request)
    {
        // Find user trying to access the page
        $appUser = NemeraldAppUsers::where('user_id', $request->user)->first();

        // If user not found throw an error
        if (!isset($appUser)){
            abort(403, 'Unauthorized user. Contact your administrator');
        }

        // Get all active phone numbers 
        $destinations = Destinations::where('destination_enabled', 'true')
            ->where ('domain_uuid', $appUser->domain_uuid)
            ->get([
                'destination_uuid',
                'destination_number',
                'destination_enabled',
                'destination_description',
                DB::Raw("coalesce(destination_description , 'n/a') as destination_description"),
            ])
            ->sortBy('destination_description')
            ->toArray();

        // If destinaions not found throw an error
        if (!isset($destinations)){
            abort(403, 'Unauthorized action. Contact your administrator1');
        }

        // Get extension for user accessing the page
        $extension = Extensions::find($appUser->extension_uuid);
 
        // If extension not found throw an error
        if (!isset($extension)){
            abort(403, 'Unauthorized extension. Contact your administrator');
        }

        //check if any of the extentions already have caller IDs assigend to them
        // if yes add TRUE column to the new array $phone_numbers
        $phone_numbers = array();
        // foreach ($extensions as $extension){
            foreach ($destinations as $destination){
                if ($destination['destination_number'] == $extension->outbound_caller_id_number){
                    $destination['isCallerID'] = true;
                    $phone_numbers[] = $destination;
                } else {
                    $destination['isCallerID'] = false;
                    $phone_numbers[] = $destination;
                }

            }
        // }

        // $format = PhoneNumberFormat::NATIONAL;
        // $phone_number = phone("6467052267","US",$format);
        // dd($phone_numbers);

        return view('layouts.extensions.callerid')
            ->with('destinations',$phone_numbers)
            ->with('national_phone_number_format',PhoneNumberFormat::NATIONAL)
            ->with ('extension',$extension);
    }

    /**
     * Update caller ID for user.
     *
     * @return \Illuminate\Http\Response
     */
    public function updateCallerID(Request $request)
    {
        // $request->destination_uuid = '4a40ab82-a9a8-4506-9f48-980cb902bcc4';
        // $request->extension_uuid = 'a2c612cc-0b8e-4e21-a8d1-81d75e8333f9';

        $destination = Destinations::find($request->destination_uuid);
        if (!$destination){
            return response()->json([
                'error' => 401,
                'message' => 'Invalid phone number ID submitted']);
        }

        if (!isset($destination)){
            return response()->json([
                'error' => 401,
                'message' => 'Unable to update Caller ID']);
        }

        // Get extension for user accessing the page
        $extension = Extensions::find($request->extension_uuid);


        if (!isset($extension)){
            return response()->json([
                'error' => 401,
                'message' => 'Unable to update Caller ID']);
        }

        // Update the caller ID field for user's extension
        // If successful delete cache
        if (session_status() == PHP_SESSION_NONE  || session_id() == '') {
            $method_setting = DefaultSettings::where('default_setting_enabled','true')
            ->where('default_setting_category','cache')
            ->where('default_setting_subcategory','method')
            ->get()
            ->first();

            $location_setting = DefaultSettings::where('default_setting_enabled','true')
            ->where('default_setting_category','cache')
            ->where('default_setting_subcategory','location')
            ->get()
            ->first();

            $freeswitch_settings = FreeswitchSettings::first();

            session_start();
//  dd($freeswitch_settings);
            $_SESSION['cache']['method']['text'] = $method_setting->default_setting_value;
            $_SESSION['cache']['location']['text'] = $location_setting->default_setting_value;
            $_SESSION['event_socket_ip_address'] = $freeswitch_settings['event_socket_ip_address'];
            $_SESSION['event_socket_port'] = $freeswitch_settings['event_socket_port'];
            $_SESSION['event_socket_password'] = $freeswitch_settings['event_socket_password'];
        }

        $cache = new cache;
        $extension->outbound_caller_id_number = $destination->destination_number;
        $extension->save();
        // dd($extension);
        $cache->delete("directory:".$extension->extension."@".$extension->user_context);

        session_destroy();

        // If successful return success status
        if ($extension->outbound_caller_id_number = $destination->destination_number){
            return response()->json([
                'extension' => $extension->extension,
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
        ->get([
            'destination_uuid',
            'destination_number',
            'destination_enabled',
            'destination_description',
            DB::Raw("coalesce(destination_description , '') as destination_description"),
        ])
        ->sortBy('destination_number');


        // dd($extension);
        return view('layouts.extensions.createOrUpdate')
            -> with('extension',$extension)
            -> with('domain_users',$extension->domain->users)
            -> with('domain_voicemails', $extension->domain->voicemails)
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
            'outbound_caller_id_number' => 'external caller ID',
            'voicemail_description' => 'description'
        ];

        $validator = Validator::make($request->all(), [
            'directory_first_name' => 'required|string',
            'directory_last_name' => 'nullable|string',
            'extension' =>'required|numeric',
            'voicemail_mail_to' => 'nullable|email:rfc,dns',
            'users' => 'nullable|array',
            'directory_visible' => 'present',
            'directory_exten_visible' => 'present',
            'enabled' => 'present',
            'description' => "nullable|string|max:100",
            'outbound_caller_id_number' => "present",
            'emergency_caller_id_number' => 'present',
            
            'voicemail_id' => 'present',
            'voicemail_enabled' => "present",
            'call_timeout' => "numeric",
            'voicemail_password' => 'numeric|digits_between:3,10',
            'voicemail_file' => "present",
            'voicemail_transcription_enabled' => 'nullable',
            'voicemail_local_after_email' => 'present',
            'voicemail_description' => "nullable|string|max:100",
            'voicemail_alternate_greet_id' => "nullable|numeric",   
            'voicemail_tutorial' => "present",
            'voicemail_destinations'  => 'nullable|array',

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
        if (isset($attributes['directory_visible']) && $attributes['directory_visible']== "on")  $attributes['directory_visible'] = "true";
        if (isset($attributes['directory_exten_visible']) && $attributes['directory_exten_visible']== "on")  $attributes['directory_exten_visible'] = "true";
        if (isset($attributes['enabled']) && $attributes['enabled']== "on")  $attributes['enabled'] = "true";
        if (isset($attributes['voicemail_enabled']) && $attributes['voicemail_enabled']== "on")  $attributes['voicemail_enabled'] = "true";
        if (isset($attributes['voicemail_transcription_enabled']) && $attributes['voicemail_transcription_enabled']== "on")  $attributes['voicemail_transcription_enabled'] = "true";
        if (isset($attributes['voicemail_local_after_email']) && $attributes['voicemail_local_after_email']== "false")  $attributes['voicemail_local_after_email'] = "true";
        if (isset($attributes['voicemail_local_after_email']) && $attributes['voicemail_local_after_email']== "on")  $attributes['voicemail_local_after_email'] = "false";
        if (isset($attributes['voicemail_tutorial']) && $attributes['voicemail_tutorial']== "on")  $attributes['voicemail_tutorial'] = "true";

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

        // Update Voicemail Destinations table
        foreach($extension->voicemail->voicemail_destinations as $vm_destination) {
            $vm_destination->delete();
        }
        if (isset($attributes['voicemail_destinations'])) {
            foreach($attributes['voicemail_destinations'] as $voicemail_destination){
                $destination = new VoicemailDestinations();
                $destination->voicemail_uuid_copy=$voicemail_destination;
                $destination->domain_uuid = Session::get('domain_uuid');
                $extension->voicemail->voicemail_destinations()->save($destination);
            }
        }

        // Update Extension users table
        foreach($extension->extension_users as $ext_user) {
            $ext_user->delete();
        }

        if (isset($attributes['users'])) {
            foreach($attributes['users'] as $ext_user){
                $extension_users = new ExtensionUser();
                $extension_users->user_uuid = $ext_user;
                $extension_users->domain_uuid = Session::get('domain_uuid');
                $extension->extension_users()->save($extension_users);
            }
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