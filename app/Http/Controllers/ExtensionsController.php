<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignDeviceRequest;
use App\Models\Devices;
use App\Models\DeviceLines;
use App\Models\DeviceVendor;
use App\Models\FollowMe;
use App\Models\FollowMeDestinations;
use cache;
use Propaganistas\LaravelPhone\Validation\Phone;
use Throwable;
use App\Models\User;
use App\Models\Extensions;
use App\Models\Recordings;
use App\Models\Voicemails;
use App\Jobs\DeleteAppUser;
use App\Models\MusicOnHold;
use Illuminate\Support\Str;
use App\Models\Destinations;
use Illuminate\Http\Request;
use App\Models\ExtensionUser;
use App\Models\MobileAppUsers;
use App\Jobs\UpdateAppSettings;
use App\Models\DefaultSettings;
use Illuminate\Validation\Rule;
use App\Imports\ExtensionsImport;
use App\Models\FreeswitchSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\VoicemailDestinations;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\HeadingRowImport;
use Illuminate\Support\Facades\Validator;
use Propaganistas\LaravelPhone\PhoneNumber;


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

        //Check FusionPBX login status
        session_start();
        if(!isset($_SESSION['user'])) {
            return redirect()->route('logout');
        }

        // Get all registered devices for this domain
        $registrations = get_registrations();

        // Get all extensions
        $extensions = Extensions::where ('domain_uuid', Session::get('domain_uuid'))
        ->orderBy('extension')
        ->paginate(50)->onEachSide(1);


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
            //check against registrations and add them to array
            $all_regs =[];
            foreach ($registrations as $registration) {
                if ($registration['sip-auth-user'] == $extension['extension']) {
                    array_push($all_regs,$registration);
                }
            }
            if (count($all_regs)>0) {
                $extension->setAttribute("registrations",$all_regs);
                unset($all_regs);
            }
        }

        $data=array();
        // $domain_uuid=Session::get('domain_uuid');
        $data['extensions'] = $extensions;

        //assign permissions
        $permissions['add_new'] = userCheckPermission('extension_add');
        // $permissions['edit'] = userCheckPermission('voicemail_edit');
        $permissions['delete'] = userCheckPermission('extension_delete');
        $permissions['import'] = isSuperAdmin();

        $data['permissions'] = $permissions;

        return view('layouts.extensions.list')
        ->with($data);
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
        $appUser = MobileAppUsers::where('user_id', $request->user)->first();

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
            ->sortBy('destination_description');

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

        //check if this extension already have caller IDs assigend to it
        // if yes, add TRUE column to the new array $phone_numbers
        $phone_numbers = array();
            foreach ($destinations as $destination){
                if (isset($extension->outbound_caller_id_number) && $extension->outbound_caller_id_number <> "") {
                    if (PhoneNumber::make($destination->destination_number, "US")->formatE164() == PhoneNumber::make($extension->outbound_caller_id_number, "US")->formatE164()){
                        $destination->isCallerID = true;
                    } else {
                        $destination->isCallerID = false;
                    }
                } else {
                    $destination->isCallerID = false;
                }

            }

        // $format = PhoneNumberFormat::NATIONAL;
        // $phone_number = phone("6467052267","US",$format);
        // dd($phone_numbers);

        return view('layouts.extensions.callerid')
            ->with('destinations',$destinations)
            ->with('national_phone_number_format',PhoneNumberFormat::NATIONAL)
            ->with ('extension',$extension);
    }

    /**
     * Update caller ID for user.
     *
     * @return \Illuminate\Http\Response
     */
    public function updateCallerID(Request $request, $extension_uuid)
    {
        // $request->destination_uuid = '4a40ab82-a9a8-4506-9f48-980cb902bcc4';
        // $request->extension_uuid = 'a2c612cc-0b8e-4e21-a8d1-81d75e8333f9';

        $destination = Destinations::find($request->destination_uuid);
        if (!$destination){
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'Invalid phone number ID submitted. Please, contact your administrator'
                ]
            ]);
        }

        $extension = Extensions::find ($extension_uuid);
        if (!$extension){
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'Invalid extension. Please, contact administrator'
                ]
            ]);
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
        if ($request->set == "true") {
            $extension->outbound_caller_id_number = PhoneNumber::make($destination->destination_number, "US")->formatE164();
        } else {
            $extension->outbound_caller_id_number = null;
        }
        $extension->save();
        // dd($extension);
        $cache->delete("directory:".$extension->extension."@".$extension->user_context);

        session_destroy();

        // If successful return success status
        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'The caller ID was successfully updated'
            ]
        ]);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //check permissions
	    if (!userCheckPermission('extension_add') || !userCheckPermission('extension_edit')) {
            return redirect('/');
	    }

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

        // Get music on hold
        $moh = MusicOnHold::where('domain_uuid', Session::get('domain_uuid'))
        ->orWhere('domain_uuid', null)
        ->orderBy('music_on_hold_name', 'ASC')
        ->get()
        ->unique('music_on_hold_name');

        $recordings = Recordings::where('domain_uuid', Session::get('domain_uuid'))
        ->orderBy('recording_name', 'ASC')
        ->get();

        $extension = new Extensions();
        $extension->directory_visible = "true";
        $extension->directory_exten_visible = "true";
        $extension->enabled = "true";
        $extension->user_context = Session::get('domain_name');
        $extension->accountcode = Session::get('domain_name');
        $extension->limit_destination = "!USER_BUSY";
        $extension->limit_max = "5";
        $extension->call_timeout = "25";
        $extension->forward_all_enabled = "false";
        $extension->forward_busy_enabled = "false";
        $extension->forward_no_answer_enabled = "false";
        $extension->forward_user_not_registered_enabled = "false";
        $extension->follow_me_enabled = "false";
        $extensions = Extensions::where ('domain_uuid', Session::get('domain_uuid'))->get();
        //dd($extension->domain->users);
        return view('layouts.extensions.createOrUpdate')
            -> with('extension', $extension)
            -> with('extensions', $extensions)
            -> with('destinations', $destinations)
            -> with('domain_users', $extension->domain->users)
            -> with ('moh', $moh)
            -> with ('recordings', $recordings)
            -> with('national_phone_number_format',PhoneNumberFormat::NATIONAL);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Extensions $extension)
    {
        $attributes = [
            'directory_first_name' => 'first name',
            'directory_last_name' => 'last name',
            'extension' =>'extension number',
            'voicemail_mail_to' => 'email address',
            'users' => 'users field',
            'voicemail_password' => 'voicemail pin',
            'outbound_caller_id_number' => 'external caller ID',
            'voicemail_description' => 'description',
            'domain_uuid' => 'domain',
            'user_context' => 'context',
            'max_registrations' => 'registrations',
            'accountcode' => 'account code',
            'limit_max' => 'total allowed outbound calls',
            'forward_all_enabled' => 'call forwarding always',
            'forward_all_destination' => 'field',
            'forward_busy_enabled' => 'call forwarding busy',
            'forward_busy_destination' => 'field',
            'forward_no_answer_enabled' => 'call forwarding no answer',
            'forward_no_answer_description' => 'field',
            'forward_user_not_registered_enabled' => 'call forwarding no user',
            'forward_user_not_registered_destination' => 'field'

        ];

        $validator = Validator::make($request->all(), [
            'directory_first_name' => 'required|string',
            'directory_last_name' => 'nullable|string',
            'extension' =>[
                'required',
                'numeric',
                Rule::unique('App\Models\Extensions','extension')
                    ->ignore($extension->extension_uuid,'extension_uuid')
                    ->where('domain_uuid', Session::get('domain_uuid')),
                Rule::unique('App\Models\Voicemails','voicemail_id')
                    ->where('domain_uuid', Session::get('domain_uuid')),
            ],
            'voicemail_mail_to' => 'nullable|email:rfc,dns',
            'users' => 'nullable|array',
            'directory_visible' => 'present',
            'directory_exten_visible' => 'present',
            'call_timeout' => "numeric",
            'enabled' => 'present',
            'description' => "nullable|string|max:100",
            'outbound_caller_id_number' => "present",
            'emergency_caller_id_number' => 'present',


            'domain_uuid' => 'required',
            'user_context' => 'required|string',
            'number_alias' => 'nullable',
            'accountcode' => 'nullable',
            'max_registrations' => 'nullable|numeric',
            'limit_max' => 'nullable|numeric',
            'limit_destination' => 'nullable|string',
            'toll_allow' => 'nullable|string',
            'call_group' => 'nullable|string',
            'call_screen_enabled' => 'nullable',
            'user_record' => 'nullable|string',
            'auth_acl' => 'nullable|string',
            'cidr' => 'nullable|string',
            'sip_force_contact' => 'nullable|string',
            'sip_force_expires' => 'nullable|numeric',
            'mwi_account' => 'nullable|string',
            'sip_bypass_media' => 'nullable|string',
            'absolute_codec_string' => 'nullable|string',
            'force_ping' => "nullable|string",
            'dial_string' => 'nullable|string',
            'hold_music' => 'nullable',
            'forward_all_enabled' => 'in:true,false',
            'forward_all_destination' => 'bail|required_if:forward_all_enabled,==,true|nullable|PhoneOrExtension:US',
            'forward_busy_enabled' => 'in:true,false',
            'forward_busy_destination' => 'bail|required_if:forward_busy_enabled,==,true|nullable|PhoneOrExtension:US',
            'forward_no_answer_enabled' => 'in:true,false',
            'forward_no_answer_destination' => 'bail|required_if:forward_no_answer_enabled,==,true|nullable|PhoneOrExtension:US',
            'forward_user_not_registered_enabled' => 'in:true,false',
            'forward_user_not_registered_destination' => 'bail|required_if:forward_user_not_registered_enabled,==,true|nullable|PhoneOrExtension:US',

            'follow_me_enabled' => 'in:true,false',
            'follow_me_ignore_busy' => 'in:true,false',
            'follow_me_destinations'  => 'nullable|array',
            'follow_me_destinations.*.target_external'  => [
                'required_if:follow_me_destinations.*.type,==,external',
                'nullable',
                'PhoneOrExtension:US',
            ],
            'follow_me_destinations.*.target_internal'  => [
                'required_if:follow_me_destinations.*.type,==,internal',
                'nullable',
                'numeric',
                Rule::exists('App\Models\Extensions','extension')
                    ->where('domain_uuid', Session::get('domain_uuid')),
            ],
            'follow_me_destinations.*.delay'  => 'numeric',
            'follow_me_destinations.*.timeout'  => 'numeric',
            'follow_me_destinations.*.prompt'  => 'in:true,false'
        ], [
            'phone_or_extension' => 'Should be valid US phone number or extension id'
        ], $attributes);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();
        $attributes['effective_caller_id_name'] = $attributes['directory_first_name'] . " " . $attributes['directory_last_name'];
        $attributes['effective_caller_id_number'] = $attributes['extension'];
        if (isset($attributes['directory_visible']) && $attributes['directory_visible']== "on")  $attributes['directory_visible'] = "true";
        if (isset($attributes['directory_exten_visible']) && $attributes['directory_exten_visible']== "on")  $attributes['directory_exten_visible'] = "true";
        if (isset($attributes['enabled']) && $attributes['enabled']== "on")  $attributes['enabled'] = "true";
        $attributes['voicemail_enabled'] = "true";
        $attributes['voicemail_transcription_enabled'] = "true";
        $attributes['voicemail_local_after_email'] = "true";
        $attributes['voicemail_tutorial'] = "true";
        $attributes['voicemail_id'] = $attributes['extension'];
        $attributes['voicemail_password'] = $attributes['extension'];
        if (isset($attributes['call_screen_enabled']) && $attributes['call_screen_enabled']== "on")  $attributes['call_screen_enabled'] = "true";
        $attributes['password'] = Str::random(25);
        if (isset($attributes['outbound_caller_id_number'])) $attributes['outbound_caller_id_number'] = PhoneNumber::make($attributes['outbound_caller_id_number'], "US")->formatE164();
        if (isset($attributes['emergency_caller_id_number'])) $attributes['emergency_caller_id_number'] = PhoneNumber::make($attributes['emergency_caller_id_number'], "US")->formatE164();
        $attributes['insert_date'] = date("Y-m-d H:i:s");
        $attributes['insert_user'] = Session::get('user_uuid');
        if (isset($attributes['forward_all_enabled']) && $attributes['forward_all_enabled']== "true")  $attributes['forward_all_enabled'] = "true";
        if (isset($attributes['forward_all_destination'])) $attributes['forward_all_destination'] = format_phone_or_extension($attributes['forward_all_destination']);
        if (isset($attributes['forward_busy_enabled']) && $attributes['forward_busy_enabled']== "true")  $attributes['forward_busy_enabled'] = "true";
        if (isset($attributes['forward_busy_destination'])) $attributes['forward_busy_destination'] = format_phone_or_extension($attributes['forward_busy_destination']);
        if (isset($attributes['forward_no_answer_enabled']) && $attributes['forward_no_answer_enabled']== "true")  $attributes['forward_no_answer_enabled'] = "true";
        if (isset($attributes['forward_no_answer_destination'])) $attributes['forward_no_answer_destination'] = format_phone_or_extension($attributes['forward_no_answer_destination']);
        if (isset($attributes['forward_user_not_registered_enabled']) && $attributes['forward_user_not_registered_enabled']== "true")  $attributes['forward_user_not_registered_enabled'] = "true";
        if (isset($attributes['forward_user_not_registered_destination'])) $attributes['forward_user_not_registered_destination'] = format_phone_or_extension($attributes['forward_user_not_registered_destination']);;

        $extension->fill($attributes);
        $extension->save();

        if (isset($attributes['users'])) {
            foreach($attributes['users'] as $ext_user){
                $extension_users = new ExtensionUser();
                $extension_users->user_uuid = $ext_user;
                $extension_users->domain_uuid = Session::get('domain_uuid');
                $extension->extension_users()->save($extension_users);
            }
        }

        $followMe = new FollowMe();
        $followMe->domain_uuid = Session::get('domain_uuid');
        $followMe->follow_me_enabled = $attributes['follow_me_enabled'];
        $followMe->follow_me_ignore_busy = $attributes['follow_me_ignore_busy'];
        $followMe->save();
        $extension->follow_me_uuid = $followMe->follow_me_uuid;
        $extension->save();

        if (isset($attributes['follow_me_destinations'])) {
            $i = 0;
            foreach($attributes['follow_me_destinations'] as $destination){
                if($i > 9) break;
                $followMeDest = new FollowMeDestinations();
                if($destination['type'] == 'external') {
                    $followMeDest->follow_me_destination = format_phone_or_extension($destination['target_external']);
                } else {
                    $followMeDest->follow_me_destination = $destination['target_internal'];
                }
                $followMeDest->follow_me_delay = $destination['delay'];
                $followMeDest->follow_me_timeout = $destination['timeout'];
                if($destination['prompt'] == 'true') {
                    $followMeDest->follow_me_prompt = 1;
                } else {
                    $followMeDest->follow_me_prompt = null;
                }
                $followMeDest->follow_me_order = $i;
                $followMe->followMeDestinations()->save($followMeDest);
                $i++;
            }
        }

        $extension->voicemail = new Voicemails();
        $extension->voicemail->fill($attributes);
        //dd($extension->voicemail);
        $extension->voicemail->save();

        if (session_status() == PHP_SESSION_NONE  || session_id() == '') {
            session_start();
        }

        if(isset($extension->extension)) {
            $cache = new cache;
            $cache->delete("directory:" . $extension->extension . "@" . $extension->user_context);
        }

        //clear the destinations session array
        if (isset($_SESSION['destinations']['array'])) {
            unset($_SESSION['destinations']['array']);
        }

        return response()->json([
            'extension' => $extension->extension_uuid,
            'redirect_url' => route('extensions.edit', $extension),
            'status' => 'success',
            'message' => 'User has been saved'
        ]);
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
     * Display SIP Credentials for specified resource.
     *
     * @param  \App\Models\Extentions  $extention
     * @return \Illuminate\Http\Response
     */
    public function sipShow(Request $request, Extensions $extension)
    {

        return response()->json([
            'username' => $extension->extension,
            'password' => $extension->password,
            'domain' => $extension->domain->domain_name,
            // 'user' => $response,
            'status' => 'success',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Extension  $extention
     * @return \Illuminate\Http\Response
     */
    public function edit(Extensions $extension)
    {

        //check permissions
	    if (!userCheckPermission('extension_add') || !userCheckPermission('extension_edit')) {
            return redirect('/');
	    }

        //Check FusionPBX login status
        session_start();
        if(session_status() === PHP_SESSION_NONE) {
            return redirect()->route('logout');
        }

        // $devices = Devices::query()
        //     ->select('v_devices.device_uuid', 'v_devices.device_mac_address')
        //     ->leftJoin('v_device_lines as dl', 'dl.device_uuid', 'v_devices.device_uuid')
        //     ->whereNull('dl.device_line_uuid')
        //     ->get();

        // dd($extension->devices);

        $vendors = DeviceVendor::query()->orderBy('name')->get();

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

        $vm_unavailable_file_exists = Storage::disk('voicemail')
            ->exists(Session::get('domain_name') .'/' . $extension->extension . '/greeting_1.wav');

        $vm_name_file_exists = Storage::disk('voicemail')
            ->exists(Session::get('domain_name') .'/' . $extension->extension . '/recorded_name.wav');

        // Get music on hold
        $moh = MusicOnHold::where('domain_uuid', Session::get('domain_uuid'))
            ->orWhere('domain_uuid', null)
            ->orderBy('music_on_hold_name', 'ASC')
            ->get()
            ->unique('music_on_hold_name');

        $recordings = Recordings::where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('recording_name', 'ASC')
            ->get();

        //Check if there is voicemail for this extension
        if (!isset($extension->voicemail)){
            $extension->voicemail = new Voicemails();
        }

        $extensions = Extensions::where ('domain_uuid', Session::get('domain_uuid'))->whereNotIn('extension_uuid', [$extension->extension_uuid])->get();
        // dd($vm_unavailable_file_exists);
        return view('layouts.extensions.createOrUpdate')
            -> with('extension',$extension)
            -> with('domain_users',$extension->domain->users)
            -> with('domain_voicemails', $extension->domain->voicemails)
            -> with('extensions', $extensions)
            -> with('extension_users',$extension->users())
            -> with('destinations',$destinations)
            -> with('vm_unavailable_file_exists', $vm_unavailable_file_exists)
            -> with('vm_name_file_exists', $vm_name_file_exists)
            -> with ('moh', $moh)
            -> with ('recordings', $recordings)
            -> with ('devices', $extension->devices)
            -> with ('vendors', $vendors)
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
            'voicemail_description' => 'description',
            'domain_uuid' => 'domain',
            'user_context' => 'context',
            'max_registrations' => 'registrations',
            'accountcode' => 'account code',
            'limit_max' => 'total allowed outbound calls',
            'forward_all_enabled' => 'call forwarding always',
            'forward_all_destination' => 'field',
            'forward_busy_enabled' => 'call forwarding busy',
            'forward_busy_destination' => 'field',
            'forward_no_answer_enabled' => 'call forwarding no answer',
            'forward_no_answer_description' => 'field',
            'forward_user_not_registered_enabled' => 'call forwarding no user',
            'forward_user_not_registered_destination' => 'field'
        ];

        $validator = Validator::make($request->all(), [
            'directory_first_name' => 'required|string',
            'directory_last_name' => 'nullable|string',
            'extension' =>[
                'required',
                'numeric',
                Rule::unique('App\Models\Extensions','extension')
                    ->ignore($extension->extension_uuid,'extension_uuid')
                    ->where('domain_uuid', Session::get('domain_uuid')),
                Rule::unique('App\Models\Voicemails','voicemail_id')
                    ->ignore($extension->voicemail->voicemail_uuid ?? 0,'voicemail_uuid')
                    ->where('domain_uuid', Session::get('domain_uuid')),
            ],
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
            'voicemail_password' => 'bail|required_if:voicemail_enabled,==,on|nullable|numeric|digits_between:3,10',
            'voicemail_file' => "nullable",
            'voicemail_transcription_enabled' => 'nullable',
            'voicemail_local_after_email' => 'nullable',
            'voicemail_description' => "nullable|string|max:100",
            'voicemail_alternate_greet_id' => "nullable|numeric",
            'voicemail_tutorial' => "nullable",
            'voicemail_destinations'  => 'nullable|array',

            'domain_uuid' => 'required',
            'user_context' => 'required|string',
            'number_alias' => 'nullable',
            'accountcode' => 'nullable',
            'max_registrations' => 'nullable|numeric',
            'limit_max' => 'nullable|numeric',
            'limit_destination' => 'nullable|string',
            'toll_allow' => 'nullable|string',
            'call_group' => 'nullable|string',
            'call_screen_enabled' => 'nullable',
            'user_record' => 'nullable|string',
            'auth_acl' => 'nullable|string',
            'cidr' => 'nullable|string',
            'sip_force_contact' => 'nullable|string',
            'sip_force_expires' => 'nullable|numeric',
            'mwi_account' => 'nullable|string',
            'sip_bypass_media' => 'nullable|string',
            'absolute_codec_string' => 'nullable|string',
            'force_ping' => "nullable|string",
            'dial_string' => 'nullable|string',
            'hold_music' => 'nullable',
            'forward_all_enabled' => 'in:true,false',
            'forward_all_destination' => 'bail|required_if:forward_all_enabled,==,true|nullable|PhoneOrExtension:US',
            'forward_busy_enabled' => 'in:true,false',
            'forward_busy_destination' => 'bail|required_if:forward_busy_enabled,==,true|nullable|PhoneOrExtension:US',
            'forward_no_answer_enabled' => 'in:true,false',
            'forward_no_answer_destination' => 'bail|required_if:forward_no_answer_enabled,==,true|nullable|PhoneOrExtension:US',
            'forward_user_not_registered_enabled' => 'in:true,false',
            'forward_user_not_registered_destination' => 'bail|required_if:forward_user_not_registered_enabled,==,true|nullable|PhoneOrExtension:US',

            'follow_me_enabled' => 'in:true,false',
            'follow_me_ignore_busy' => 'in:true,false',
            'follow_me_destinations'  => 'nullable|array',
            'follow_me_destinations.*.target_external'  => [
                'required_if:follow_me_destinations.*.type,==,external',
                'nullable',
                'PhoneOrExtension:US',
            ],
            'follow_me_destinations.*.target_internal'  => [
                'required_if:follow_me_destinations.*.type,==,internal',
                'nullable',
                'numeric',
                Rule::exists('App\Models\Extensions','extension')
                    ->where('domain_uuid', Session::get('domain_uuid')),
            ],
            'follow_me_destinations.*.delay'  => 'numeric',
            'follow_me_destinations.*.timeout'  => 'numeric',
            'follow_me_destinations.*.prompt'  => 'in:true,false'
        ], [
            'phone_or_extension' => 'Should be valid US phone number or extension id'
        ], $attributes);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()]);
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
        if (isset($attributes['call_screen_enabled']) && $attributes['call_screen_enabled']== "on")  $attributes['call_screen_enabled'] = "true";
        if (isset($attributes['outbound_caller_id_number'])) $attributes['outbound_caller_id_number'] = PhoneNumber::make($attributes['outbound_caller_id_number'], "US")->formatE164();
        if (isset($attributes['emergency_caller_id_number'])) $attributes['emergency_caller_id_number'] = PhoneNumber::make($attributes['emergency_caller_id_number'], "US")->formatE164();
        if (isset($attributes['forward_all_enabled']) && $attributes['forward_all_enabled']== "true")  $attributes['forward_all_enabled'] = "true";
        if (isset($attributes['forward_all_destination'])) $attributes['forward_all_destination'] = format_phone_or_extension($attributes['forward_all_destination']);
        if (isset($attributes['forward_busy_enabled']) && $attributes['forward_busy_enabled']== "true")  $attributes['forward_busy_enabled'] = "true";
        if (isset($attributes['forward_busy_destination'])) $attributes['forward_busy_destination'] = format_phone_or_extension($attributes['forward_busy_destination']);
        if (isset($attributes['forward_no_answer_enabled']) && $attributes['forward_no_answer_enabled']== "true")  $attributes['forward_no_answer_enabled'] = "true";
        if (isset($attributes['forward_no_answer_destination'])) $attributes['forward_no_answer_destination'] = format_phone_or_extension($attributes['forward_no_answer_destination']);
        if (isset($attributes['forward_user_not_registered_enabled']) && $attributes['forward_user_not_registered_enabled']== "true")  $attributes['forward_user_not_registered_enabled'] = "true";
        if (isset($attributes['forward_user_not_registered_destination'])) $attributes['forward_user_not_registered_destination'] = format_phone_or_extension($attributes['forward_user_not_registered_destination']);
        $attributes['update_date'] = date("Y-m-d H:i:s");
        $attributes['update_user'] = Session::get('user_uuid');

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
        if (isset($extension->voicemail)) {
            foreach($extension->voicemail->voicemail_destinations as $vm_destination) {
                $vm_destination->delete();
            }
        }

        if (isset($attributes['voicemail_destinations'])) {
            foreach($attributes['voicemail_destinations'] as $voicemail_destination){
                $destination = new VoicemailDestinations();
                $destination->voicemail_uuid_copy=$voicemail_destination;
                $destination->domain_uuid = Session::get('domain_uuid');
                $extension->voicemail->voicemail_destinations()->save($destination);
            }
        }

        // Update Sequential destinations
        foreach($extension->getFollowMeDestinations() as $followMeDest) {
            $followMeDest->delete();
        }

        if($followMe = $extension->followMe()->first()) {
            $followMe->delete();
        }

        $followMe = new FollowMe();
        $followMe->domain_uuid = Session::get('domain_uuid');
        $followMe->follow_me_enabled = $attributes['follow_me_enabled'];
        $followMe->follow_me_ignore_busy = $attributes['follow_me_ignore_busy'];
        $followMe->save();
        $extension->follow_me_uuid = $followMe->follow_me_uuid;
        $extension->save();

        if (isset($attributes['follow_me_destinations'])) {
            $i = 0;
            foreach($attributes['follow_me_destinations'] as $destination){
                if($i > 9) break;
                $followMeDest = new FollowMeDestinations();
                if($destination['type'] == 'external') {
                    $followMeDest->follow_me_destination = format_phone_or_extension($destination['target_external']);
                } else {
                    $followMeDest->follow_me_destination = $destination['target_internal'];
                }
                $followMeDest->follow_me_delay = $destination['delay'];
                $followMeDest->follow_me_timeout = $destination['timeout'];
                if($destination['prompt'] == 'true') {
                    $followMeDest->follow_me_prompt = 1;
                } else {
                    $followMeDest->follow_me_prompt = null;
                }
                $followMeDest->follow_me_order = $i;
                $followMe->followMeDestinations()->save($followMeDest);
                $i++;
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
        // return response()->json([
        //     'status' => 'success',
        //     'message' => 'Extension has been saved'
        // ]);
        // Delete cache and update extension
        if (session_status() == PHP_SESSION_NONE  || session_id() == '') {
            session_start();
        }

        if (isset($extension->cache)) {
            $cache = new cache();
            $cache->delete("directory:" . $extension->extension . "@" . $extension->user_context);
        }

        if (isset($extension->voicemail)) {
            $extension->voicemail->update($attributes);
        }

        $extension->update($attributes);

        //clear the destinations session array
        if (isset($_SESSION['destinations']['array'])) {
            unset($_SESSION['destinations']['array']);
        }

        // dispatch the job to update app user
        $mobile_app = $extension->mobile_app;
        if(isset($mobile_app)) {
            $mobile_app->name = $attributes['effective_caller_id_name'];
            $mobile_app->email = ($attributes['voicemail_mail_to']) ? $attributes['voicemail_mail_to'] : "";
            $mobile_app->ext = $attributes['extension'];
            $mobile_app->password = $extension->password;
            UpdateAppSettings::dispatch($mobile_app->attributesToArray())->onQueue('default');
        }

        return response()->json([
            'status' => 'success',
            'extension' => $extension->extension_uuid,
            'message' => 'Extension has been saved'
        ]);
    }


    /**
     * Import the specified resource
     *
     * @param  Request $request
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        try {

            $headings = (new HeadingRowImport)->toArray(request()->file('file'));

            // Excel::import(new ExtensionsImport, request()->file('file'));

            $import = new ExtensionsImport;
            $import->import(request()->file('file'));

            // Get array of failures and combine into html
            if ($import->failures()->isNotEmpty()) {
                $errormessage = 'Some errors were detected. Please, check the details: <ul>';
                foreach ($import->failures() as $failure) {
                    foreach ($failure->errors() as $error) {
                        $value = (isset($failure->values()[$failure->attribute()]) ? $failure->values()[$failure->attribute()] : "NULL");
                        $errormessage .= "<li>Skipping row <strong>" . $failure->row() . "</strong>. Invalid value <strong>'" . $value . "'</strong> for field <strong>'" . $failure->attribute() . "'</strong>. " . $error ."</li>";
                    }
                }
                $errormessage .= '</ul>';

                // Send response in format that Dropzone understands
                return response()->json([
                    'error' => $errormessage,
                ],400);
            }

        } catch (Throwable $e) {
            // Log::alert($e);
            // Send response in format that Dropzone understands
            return response()->json([
                'error' => $e->getMessage(),
            ],400);
        }


        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'Extensions were successfully uploaded'
            ]
        ]);

    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Extentions  $extentions
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $extension = Extensions::findOrFail($id);

        if(isset($extension)){
            if (isset($extension->voicemail)) {
                $deletedvm = $extension->voicemail->delete();
            }

            if (isset($extension->extension_users)) {
                $deleted = $extension->extension_users()->delete();
            }

            $deleted = $extension->delete();

            if ($deleted){
                // dispatch the job to remove app user
                DeleteAppUser::dispatch($extension->mobile_app)->onQueue('default');
                return response()->json([
                    'status' => 'success',
                    'id' => $id,
                    'message' => 'Selected extensions have been deleted'
                ]);
            } else {
                return response()->json([
                    'error' => 401,
                    'message' => 'There was an error deleting this extension'
                ]);
            }
        }
    }

    public function assignDevice(AssignDeviceRequest $request, Extensions $extension)
    {
        $inputs = $request->validated();

        $devicExist = DeviceLines::query()->where(['device_uuid' => $inputs['device_uuid']])->exists();

        if ($devicExist){
            return response()->json([
                'status' => 'alert',
                'message' => 'Device is already assigned.'
            ]);
        }

        $extension->deviceLines()->create([
            'device_uuid' => $inputs['device_uuid'],
            'line_number' => $inputs['line_number'] ?? '1',
            'server_address' => Session::get('domain_name'),
            'server_address_primary' => get_domain_setting('server_address_primary'),
            'server_address_secondary' => get_domain_setting('server_address_secondary'),
            'display_name' => $extension->extension,
            'user_id' => $extension->extension,
            'auth_id' => $extension->extension,
            'label' => $extension->extension,
            'password' => $extension->password,
            'sip_port' => get_domain_setting('line_sip_port'),
            'sip_transport' => get_domain_setting('line_sip_transport'),
            'register_expires' => get_domain_setting('line_register_expires'),
            'enabled' => 'true',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Device has been assigned successfully.'
        ]);
    }

    public function unAssignDevice(Extensions $extension, DeviceLines $deviceLine)
    {
        $deviceLine->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Device has been unassigned successfully.'
        ]);
    }

    public function clearCallforwardDestination(Extensions $extension, Request $request)
    {
        $type = $request->route('type');
        switch ($type) {
            case 'all':
                $extension->forward_all_destination = '';
                $extension->forward_all_enabled = 'false';
                break;
            case 'user_not_registered':
                $extension->forward_user_not_registered_destination = '';
                $extension->forward_user_not_registered_enabled = 'false';
                break;
            case 'no_answer':
                $extension->forward_no_answer_destination = '';
                $extension->forward_no_answer_enabled = 'false';
                break;
            case 'busy':
                $extension->forward_busy_destination = '';
                $extension->forward_busy_enabled = 'false';
                break;
            default:
                return response()->json([
                    'status' => 'alert',
                    'message' => 'Unknown type.'
                ]);
        }

        $extension->save();

        return response()->json([
            'status' => 'success',
            'message' => 'CallForward destination has been disabled successfully.'
        ]);
    }

}
