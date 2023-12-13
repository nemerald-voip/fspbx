<?php

namespace App\Http\Controllers;

use cache;
use Throwable;
use App\Models\Devices;
use App\Models\FollowMe;
use App\Models\IvrMenus;
use App\Jobs\SuspendUser;
use App\Models\Extensions;
use App\Models\Recordings;
use App\Models\RingGroups;
use App\Models\Voicemails;
use App\Jobs\DeleteAppUser;
use App\Models\DeviceLines;
use App\Models\FusionCache;
use App\Models\MusicOnHold;
use Illuminate\Support\Str;
use App\Models\Destinations;
use App\Models\DeviceVendor;
use Illuminate\Http\Request;
use App\Jobs\SendEventNotify;
use App\Models\DeviceProfile;
use App\Models\ExtensionUser;
use App\Models\MobileAppUsers;
use App\Jobs\UpdateAppSettings;
use App\Models\DefaultSettings;
use Illuminate\Validation\Rule;
use App\Events\ExtensionUpdated;
use App\Imports\ExtensionsImport;
use App\Models\FreeswitchSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use libphonenumber\PhoneNumberUtil;
use App\Models\FollowMeDestinations;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\VoicemailDestinations;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\HeadingRowImport;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\AssignDeviceRequest;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Exceptions\NumberParseException;


class ExtensionsController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth')->except(['callerId', 'updateCallerID']);
    }

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

        $searchString = $request->get('search');

        // Get all registered devices for this domain
        $registrations = get_registrations();

        // Get all extensions
        $extensions = Extensions::where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('extension');

        if ($searchString) {
            $extensions->where(function ($query) use ($searchString) {
                $query->where('extension', 'ilike', '%' . str_replace('-', '', $searchString) . '%')
                    ->orWhere('effective_caller_id_name', 'ilike', '%' . str_replace('-', '', $searchString) . '%')
                    ->orWhere('directory_first_name', 'ilike', '%' . str_replace('-', '', $searchString) . '%')
                    ->orWhere('directory_last_name', 'ilike', '%' . str_replace('-', '', $searchString) . '%')
                    ->orWhere('description', 'ilike', '%' . str_replace('-', '', $searchString) . '%');
            });
        }
        $extensions = $extensions->paginate(50)->onEachSide(1);

        //Get libphonenumber object
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        foreach ($extensions as $extension) {
            if ($extension['outbound_caller_id_number']) {
                try {
                    $phoneNumberObject = $phoneNumberUtil->parse($extension['outbound_caller_id_number'], 'US');
                    if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                        $extension->outbound_caller_id_number = $phoneNumberUtil
                            ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL);
                    }
                } catch (NumberParseException $e) {
                    // Do nothing and leave the numner as is
                }
            }
            //check against registrations and add them to array
            $all_regs = [];
            foreach ($registrations as $registration) {
                if ($registration['sip-auth-user'] == $extension['extension']) {
                    array_push($all_regs, $registration);
                }
            }
            if (count($all_regs) > 0) {
                $extension->setAttribute("registrations", $all_regs);
                unset($all_regs);
            }
        }

        $data = array();
        // $domain_uuid=Session::get('domain_uuid');
        $data['searchString'] = $searchString;
        $data['extensions'] = $extensions;

        //assign permissions
        $permissions['add_new'] = userCheckPermission('extension_add');
        // $permissions['edit'] = userCheckPermission('voicemail_edit');
        $permissions['delete'] = userCheckPermission('extension_delete');
        $permissions['import'] = isSuperAdmin();
        $permissions['device_restart'] = isSuperAdmin();
        $permissions['add_user'] = userCheckPermission('user_add');
        $permissions['contact_center_agent_create'] = (isSuperAdmin() || userCheckPermission('contact_center_agent_create')) ? true : false;
        $permissions['contact_center_admin_create'] = (isSuperAdmin() || userCheckPermission('contact_center_admin_create')) ? true : false;
        $permissions['contact_center_supervisor_create'] = (isSuperAdmin() || userCheckPermission('contact_center_supervisor_create')) ? true : false;

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
        if (!isset($appUser)) {
            abort(403, 'Unauthorized user. Contact your administrator');
        }

        // Get all active phone numbers
        $destinations = Destinations::where('destination_enabled', 'true')
            ->where('domain_uuid', $appUser->domain_uuid)
            ->get([
                'destination_uuid',
                'destination_number',
                'destination_enabled',
                'destination_description',
                DB::Raw("coalesce(destination_description , 'n/a') as destination_description"),
            ])
            ->sortBy('destination_description');

        // If destinaions not found throw an error
        if (!isset($destinations)) {
            abort(403, 'Unauthorized action. Contact your administrator1');
        }

        // Get extension for user accessing the page
        $extension = Extensions::find($appUser->extension_uuid);

        // If extension not found throw an error
        if (!isset($extension)) {
            abort(403, 'Unauthorized extension. Contact your administrator');
        }

        //Get libphonenumber object
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        //check if this extension already have caller IDs assigend to it
        // if yes, add TRUE column to the new array $phone_numbers
        $phone_numbers = array();
        foreach ($destinations as $destination) {
            if (isset($extension->outbound_caller_id_number) && $extension->outbound_caller_id_number <> "") {
                try {
                    $phoneNumberObject = $phoneNumberUtil->parse($destination->destination_number, 'US');
                    if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                        $destination->destination_number = $phoneNumberUtil
                            ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL);
                    }
                } catch (NumberParseException $e) {
                    // Do nothing and leave the numner as is
                }

                if (PhoneNumber::make($destination->destination_number, "US")->formatE164() == PhoneNumber::make($extension->outbound_caller_id_number, "US")->formatE164()) {
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
            ->with('destinations', $destinations)
            ->with('national_phone_number_format', PhoneNumberFormat::NATIONAL)
            ->with('extension', $extension);
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
        if (!$destination) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'Invalid phone number ID submitted. Please, contact your administrator'
                ]
            ]);
        }

        $extension = Extensions::find($extension_uuid);
        if (!$extension) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'Invalid extension. Please, contact administrator'
                ]
            ]);
        }

        // Update the caller ID field for user's extension
        // If successful delete cache
        if (session_status() == PHP_SESSION_NONE || session_id() == '') {
            $method_setting = DefaultSettings::where('default_setting_enabled', 'true')
                ->where('default_setting_category', 'cache')
                ->where('default_setting_subcategory', 'method')
                ->get()
                ->first();

            $location_setting = DefaultSettings::where('default_setting_enabled', 'true')
                ->where('default_setting_category', 'cache')
                ->where('default_setting_subcategory', 'location')
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
            try {
                $extension->outbound_caller_id_number = PhoneNumber::make($destination->destination_number, "US")->formatE164();
            } catch (NumberParseException $e) {
                $extension->outbound_caller_id_number = $destination->destination_number;
            }
        } else {
            $extension->outbound_caller_id_number = null;
        }
        $extension->save();
        // dd($extension);
        $cache->delete("directory:" . $extension->extension . "@" . $extension->user_context);

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
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\Response
     */
    public function create()
    {
        //check permissions
        if (!userCheckPermission('extension_add') || !userCheckPermission('extension_edit')) {
            return redirect('/');
        }

        // Get all phone numbers
        $destinations = Destinations::where('destination_enabled', 'true')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->get([
                'destination_uuid',
                'destination_number',
                'destination_enabled',
                'destination_description',
                DB::Raw("coalesce(destination_description , '') as destination_description"),
            ])
            ->sortBy('destination_number');

        //Get libphonenumber object
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        foreach ($destinations as $destination) {
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($destination->destination_number, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $destination->destination_number = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::E164);
                }

                // Set the label
                $phoneNumber = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                $destination->label = isset($destination->destination_description) && !empty($destination->destination_description)
                    ? $phoneNumber . " - " . $destination->destination_description
                    : $phoneNumber;
            } catch (NumberParseException $e) {
                // Do nothing and leave the numbner as is

                //Set the label
                $destination->label = isset($destination->destination_description) && !empty($destination->destination_description)
                    ? $destination->destination_number . " - " . $destination->destination_description
                    : $destination->destination_number;
            }

            $destination->isCallerID = false;
            $destination->isEmergencyCallerID = false;
        }

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
        $extension->do_not_disturb = "false";

        return view('layouts.extensions.createOrUpdate')
            ->with('extension', $extension)
            ->with('extensions', $this->getDestinationExtensions())
            ->with('destinations', $destinations)
            ->with('follow_me_destinations', [])
            ->with('domain_users', $extension->domain->users)
            ->with('follow_me_ring_my_phone_timeout', 0)
            ->with('moh', $moh)
            ->with('recordings', $recordings)
            ->with('national_phone_number_format', PhoneNumberFormat::NATIONAL);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Extensions $extension)
    {
        $attributes = [
            'directory_first_name' => 'first name',
            'directory_last_name' => 'last name',
            'extension' => 'extension number',
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
            'extension' => [
                'required',
                'numeric',
                Rule::unique('App\Models\Extensions', 'extension')
                    ->ignore($extension->extension_uuid, 'extension_uuid')
                    ->where('domain_uuid', Session::get('domain_uuid')),
                Rule::unique('App\Models\Voicemails', 'voicemail_id')
                    ->ignore($extension->voicemail->voicemail_uuid ?? 0, 'voicemail_uuid')
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
            'do_not_disturb' => 'in:true,false',

            'domain_uuid' => 'required',
            'user_context' => 'required|string',
            'number_alias' => 'nullable',
            'accountcode' => 'nullable',
            'max_registrations' => 'nullable|numeric',
            'limit_max' => 'nullable|numeric',
            'limit_destination' => 'nullable|string',
            'call_timeout' => "numeric",
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
            'forward.all.type' => [
                'required_if:forward_all_enabled,==,true',
                'in:external,internal'
            ],
            'forward.all.target_external' => [
                'required_if:forward.all.type,==,external',
                'nullable',
                'phone:US',
            ],
            'forward.all.target_internal' => [
                'required_if:forward_all_enabled,==,true,forward.all.type,==,internal',
                'nullable',
                'numeric',
                'ExtensionExists:' . Session::get('domain_uuid')
            ],

            'forward_busy_enabled' => 'in:true,false',
            'forward.busy.type' => [
                'required_if:forward_busy_enabled,==,true',
                'in:external,internal'
            ],
            'forward.busy.target_external' => [
                'required_if:forward.busy.type,==,external',
                'nullable',
                'phone:US',
            ],
            'forward.busy.target_internal' => [
                'required_if:forward_busy_enabled,==,true,forward.busy.type,==,internal',
                'nullable',
                'numeric',
                'ExtensionExists:' . Session::get('domain_uuid')
            ],

            'forward_no_answer_enabled' => 'in:true,false',
            'forward.no_answer.type' => [
                'required_if:forward_no_answer_enabled,==,true',
                'in:external,internal'
            ],
            'forward.no_answer.target_external' => [
                'required_if:forward.no_answer.type,==,external',
                'nullable',
                'phone:US',
            ],
            'forward.no_answer.target_internal' => [
                'required_if:forward_no_answer_enabled,==,true,forward.no_answer.type,==,internal',
                'nullable',
                'numeric',
                'ExtensionExists:' . Session::get('domain_uuid')
            ],

            'forward_user_not_registered_enabled' => 'in:true,false',
            'forward.user_not_registered.type' => [
                'required_if:forward_user_not_registered_enabled,==,true',
                'in:external,internal'
            ],
            'forward.user_not_registered.target_external' => [
                'required_if:forward.user_not_registered.type,==,external',
                'nullable',
                'phone:US',
            ],
            'forward.user_not_registered.target_internal' => [
                'required_if:forward_user_not_registered_enabled,==,true,forward.user_not_registered.type,==,internal',
                'nullable',
                'numeric',
                'ExtensionExists:' . Session::get('domain_uuid')
            ],

            'follow_me_enabled' => 'in:true,false',
            'follow_me_ignore_busy' => 'in:true,false',
            'follow_me_ring_my_phone_timeout' => 'nullable|numeric',
            'follow_me_destinations' => 'nullable|array',
            'follow_me_destinations.*.target_external' => [
                'required_if:follow_me_destinations.*.type,==,external',
                'nullable',
                'phone:US',
            ],
            'follow_me_destinations.*.target_internal' => [
                'required_if:follow_me_destinations.*.type,==,internal',
                'nullable',
                'numeric',
                'ExtensionExists:' . Session::get('domain_uuid')
            ],
            'follow_me_destinations.*.delay' => 'numeric',
            'follow_me_destinations.*.timeout' => 'numeric',
            'follow_me_destinations.*.prompt' => 'in:true,false'
        ], [
            'phone' => 'Should be valid US phone number or extension id',
            'required_if' => 'This is the required field',
            'ExtensionExists' => 'Should be valid destination'
        ], $attributes);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();
        $attributes['effective_caller_id_name'] = $attributes['directory_first_name'] . " " . $attributes['directory_last_name'];
        $attributes['effective_caller_id_number'] = $attributes['extension'];
        if (isset($attributes['directory_visible']) && $attributes['directory_visible'] == "on") $attributes['directory_visible'] = "true";
        if (isset($attributes['directory_exten_visible']) && $attributes['directory_exten_visible'] == "on") $attributes['directory_exten_visible'] = "true";
        if (isset($attributes['enabled']) && $attributes['enabled'] == "on") $attributes['enabled'] = "true";
        $attributes['voicemail_enabled'] = "true";
        $attributes['voicemail_transcription_enabled'] = "true";
        $attributes['voicemail_file'] = "attach";
        $attributes['voicemail_local_after_email'] = "true";
        $attributes['voicemail_tutorial'] = "true";
        $attributes['voicemail_id'] = $attributes['extension'];
        if (get_domain_setting('password_complexity')) {
            $attributes['voicemail_password'] = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        } else {
            $attributes['voicemail_password'] = $attributes['extension'];
        }
        if (isset($attributes['call_screen_enabled']) && $attributes['call_screen_enabled'] == "on") $attributes['call_screen_enabled'] = "true";
        $attributes['password'] = generate_password();
        // if (isset($attributes['outbound_caller_id_number'])) $attributes['outbound_caller_id_number'] = PhoneNumber::make($attributes['outbound_caller_id_number'], "US")->formatE164();
        // if (isset($attributes['emergency_caller_id_number'])) $attributes['emergency_caller_id_number'] = PhoneNumber::make($attributes['emergency_caller_id_number'], "US")->formatE164();
        $attributes['insert_date'] = date("Y-m-d H:i:s");
        $attributes['insert_user'] = Session::get('user_uuid');

        if (isset($attributes['forward_all_enabled']) && $attributes['forward_all_enabled'] == "true") $attributes['forward_all_enabled'] = "true";

        if ($attributes['forward']['all']['type'] == 'external') {
            $attributes['forward_all_destination'] = PhoneNumber::make($attributes['forward']['all']['target_external'], "US")->formatE164();
        } else {
            $attributes['forward_all_destination'] = ($attributes['forward']['all']['target_internal'] == '0') ? '' : $attributes['forward']['all']['target_internal'];;
            if (empty($attributes['forward_all_destination'])) {
                $attributes['forward_all_enabled'] = 'false';
            }
        }

        if ($attributes['forward_all_enabled'] == 'false') {
            $attributes['forward_all_destination'] = '';
        }

        if (isset($attributes['forward_busy_enabled']) && $attributes['forward_busy_enabled'] == "true") $attributes['forward_busy_enabled'] = "true";

        if ($attributes['forward']['busy']['type'] == 'external') {
            $attributes['forward_busy_destination'] = PhoneNumber::make($attributes['forward']['busy']['target_external'], "US")->formatE164();
        } else {
            $attributes['forward_busy_destination'] = ($attributes['forward']['busy']['target_internal'] == '0') ? '' : $attributes['forward']['busy']['target_internal'];;
            if (empty($attributes['forward_busy_destination'])) {
                $attributes['forward_busy_enabled'] = 'false';
            }
        }

        if ($attributes['forward_busy_enabled'] == 'false') {
            $attributes['forward_busy_destination'] = '';
        }

        if (isset($attributes['forward_no_answer_enabled']) && $attributes['forward_no_answer_enabled'] == "true") $attributes['forward_no_answer_enabled'] = "true";

        if ($attributes['forward']['no_answer']['type'] == 'external') {
            $attributes['forward_no_answer_destination'] = PhoneNumber::make($attributes['forward']['no_answer']['target_external'], "US")->formatE164();
        } else {
            $attributes['forward_no_answer_destination'] = ($attributes['forward']['no_answer']['target_internal'] == '0') ? '' : $attributes['forward']['no_answer']['target_internal'];
            if (empty($attributes['forward_no_answer_destination'])) {
                $attributes['forward_no_answer_enabled'] = 'false';
            }
        }

        if ($attributes['forward_no_answer_enabled'] == 'false') {
            $attributes['forward_no_answer_destination'] = '';
        }

        if (isset($attributes['forward_user_not_registered_enabled']) && $attributes['forward_user_not_registered_enabled'] == "true") $attributes['forward_user_not_registered_enabled'] = "true";

        if ($attributes['forward']['user_not_registered']['type'] == 'external') {
            $attributes['forward_user_not_registered_destination'] = PhoneNumber::make($attributes['forward']['user_not_registered']['target_external'], "US")->formatE164();
        } else {
            $attributes['forward_user_not_registered_destination'] = ($attributes['forward']['user_not_registered']['target_internal'] == '0') ? '' : $attributes['forward']['user_not_registered']['target_internal'];;
            if (empty($attributes['forward_user_not_registered_destination'])) {
                $attributes['forward_user_not_registered_enabled'] = 'false';
            }
        }

        if ($attributes['forward_user_not_registered_enabled'] == 'false') {
            $attributes['forward_user_not_registered_destination'] = '';
        }

        if (isset($attributes['do_not_disturb']) && $attributes['do_not_disturb'] == "true") $attributes['do_not_disturb'] = "true";

        $extension->fill($attributes);

        if (isset($attributes['users'])) {
            foreach ($attributes['users'] as $ext_user) {
                $extension_users = new ExtensionUser();
                $extension_users->user_uuid = $ext_user;
                $extension_users->domain_uuid = Session::get('domain_uuid');
                $extension->extension_users()->save($extension_users);
            }
        }

        $followMe = new FollowMe();
        $followMe->domain_uuid = Session::get('domain_uuid');
        $followMe->follow_me_enabled = $attributes['follow_me_enabled'];
        $followMe->follow_me_ignore_busy = ($attributes['follow_me_ignore_busy'] == 'true') ? 'false' : 'true';
        $followMe->save();
        $extension->follow_me_uuid = $followMe->follow_me_uuid;
        $extension->save();

        if (!isset($attributes['follow_me_destinations'])) {
            $attributes['follow_me_destinations'] = [];
        }

        if ($attributes['follow_me_ring_my_phone_timeout'] && $attributes['follow_me_ring_my_phone_timeout'] > 0) {
            $attributes['follow_me_destinations'] = array_merge([
                $extension->extension_uuid => [
                    'type' => 'internal',
                    'target_internal' => $extension->extension,
                    'delay' => 0,
                    'timeout' => $attributes['follow_me_ring_my_phone_timeout'],
                    'prompt' => 'false'
                ]
            ], $attributes['follow_me_destinations']);
        }

        if (count($attributes['follow_me_destinations']) > 0) {
            $i = 0;
            foreach ($attributes['follow_me_destinations'] as $destination) {
                if ($i > 9) break;
                $followMeDest = new FollowMeDestinations();
                if ($destination['type'] == 'external') {
                    $followMeDest->follow_me_destination = format_phone_or_extension($destination['target_external']);
                } else {
                    $followMeDest->follow_me_destination = $destination['target_internal'];
                }
                $followMeDest->follow_me_delay = $destination['delay'];
                $followMeDest->follow_me_timeout = $destination['timeout'];
                if ($destination['prompt'] == 'true') {
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

        if (session_status() == PHP_SESSION_NONE || session_id() == '') {
            session_start();
        }

        if (isset($extension->extension)) {
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
     * @param \App\Models\Extentions $extentions
     * @return \Illuminate\Http\Response
     */
    public function show(Extensions $extensions)
    {
        //
    }


    /**
     * Display SIP Credentials for specified resource.
     *
     * @param \App\Models\Extentions $extention
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
     * @param Extension $extention
     * @return \Illuminate\Http\Response
     */
    public function edit(Extensions $extension)
    {

        //check permissions
        if (!userCheckPermission('extension_add') && !userCheckPermission('extension_edit')) {
            return redirect('/');
        }

        //Check FusionPBX login status
        session_start();
        if (!isset($_SESSION['user'])) {
            return redirect()->route('logout');
        }

        $devices = Devices::where('device_enabled', 'true')
            ->where('domain_uuid', Session::get('domain_uuid'))
            /*->whereNotExists( function ($query) {
                $query->select(DB::raw(1))
                    ->from('v_device_lines')
                    ->whereRaw('v_devices.device_uuid = v_device_lines.device_uuid');
            })*/
            ->get();

        $vendors = DeviceVendor::where('enabled', 'true')->orderBy('name')->get();
        $profiles = DeviceProfile::where('device_profile_enabled', 'true')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('device_profile_name')->get();

        // Get all phone numbers
        $destinations = Destinations::where('destination_enabled', 'true')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->get([
                'destination_uuid',
                'destination_number',
                'destination_enabled',
                'destination_description',
                DB::Raw("coalesce(destination_description , '') as destination_description"),
            ])
            ->sortBy('destination_number');

        //Get libphonenumber object
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        //try to convert emergency caller ID to e164 format
        if ($extension->emergency_caller_id_number) {
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($extension->emergency_caller_id_number, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $extension->emergency_caller_id_number = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::E164);
                }
            } catch (NumberParseException $e) {
                // Do nothing and leave the numbner as is
            }
        }

        //try to convert caller ID to e164 format
        if ($extension->outbound_caller_id_number) {
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($extension->outbound_caller_id_number, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $extension->outbound_caller_id_number = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::E164);
                }
            } catch (NumberParseException $e) {
                // Do nothing and leave the numbner as is
            }
        }

        foreach ($destinations as $destination) {
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($destination->destination_number, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $destination->destination_number = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::E164);
                }

                // Set the label
                $phoneNumber = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                $destination->label = isset($destination->destination_description) && !empty($destination->destination_description)
                    ? $phoneNumber . " - " . $destination->destination_description
                    : $phoneNumber;
            } catch (NumberParseException $e) {
                // Do nothing and leave the numbner as is

                //Set the label
                $destination->label = isset($destination->destination_description) && !empty($destination->destination_description)
                    ? $destination->destination_number . " - " . $destination->destination_description
                    : $destination->destination_number;
            }

            $destination->isCallerID = ($destination->destination_number === $extension->outbound_caller_id_number);
            $destination->isEmergencyCallerID = ($destination->destination_number === $extension->emergency_caller_id_number);
        }

        $vm_unavailable_file_exists = Storage::disk('voicemail')
            ->exists(Session::get('domain_name') . '/' . $extension->extension . '/greeting_1.wav');

        $vm_name_file_exists = Storage::disk('voicemail')
            ->exists(Session::get('domain_name') . '/' . $extension->extension . '/recorded_name.wav');

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
        if (!isset($extension->voicemail)) {
            $extension->voicemail = new Voicemails();
        }

        $follow_me_ring_my_phone_timeout = 0;
        $follow_me_destinations = $extension->getFollowMeDestinations();
        if ($follow_me_destinations->count() > 0) {
            if ($follow_me_destinations[0]->follow_me_destination == $extension->extension) {
                $follow_me_ring_my_phone_timeout = $follow_me_destinations[0]->follow_me_timeout;
                unset($follow_me_destinations[0]);
            }
        }

        return view('layouts.extensions.createOrUpdate')
            ->with('extension', $extension)
            ->with('domain_users', $extension->domain->users)
            ->with('domain_voicemails', $extension->domain->voicemails)
            ->with('extensions', $this->getDestinationExtensions())
            ->with('extension_users', $extension->users())
            ->with('destinations', $destinations)
            ->with('follow_me_destinations', $follow_me_destinations)
            ->with('follow_me_ring_my_phone_timeout', $follow_me_ring_my_phone_timeout)
            ->with('vm_unavailable_file_exists', $vm_unavailable_file_exists)
            ->with('vm_name_file_exists', $vm_name_file_exists)
            ->with('moh', $moh)
            ->with('recordings', $recordings)
            ->with('devices', $devices)
            ->with('vendors', $vendors)
            ->with('profiles', $profiles);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Extentions $extentions
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Extensions $extension)
    {
        $attributes = [
            'directory_first_name' => 'first name',
            'directory_last_name' => 'last name',
            'extension' => 'extension number',
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
            'forward_user_not_registered_destination' => 'field',
        ];

        $validator = Validator::make($request->all(), [
            'directory_first_name' => 'required|string',
            'directory_last_name' => 'nullable|string',
            'extension' => [
                'required',
                'numeric',
                Rule::unique('App\Models\Extensions', 'extension')
                    ->ignore($extension->extension_uuid, 'extension_uuid')
                    ->where('domain_uuid', Session::get('domain_uuid')),
                Rule::unique('App\Models\Voicemails', 'voicemail_id')
                    ->ignore($extension->voicemail->voicemail_uuid ?? 0, 'voicemail_uuid')
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
            'do_not_disturb' => 'in:true,false',

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
            'voicemail_destinations' => 'nullable|array',

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
            'forward.all.type' => [
                'required_if:forward_all_enabled,==,true',
                'in:external,internal'
            ],
            'forward.all.target_external' => [
                'required_if:forward.all.type,==,external',
                'nullable',
                'phone:US',
            ],
            'forward.all.target_internal' => [
                'required_if:forward_all_enabled,==,true,forward.all.type,==,internal',
                'nullable',
                'numeric',
                'ExtensionExists:' . Session::get('domain_uuid')
            ],

            'forward_busy_enabled' => 'in:true,false',
            'forward.busy.type' => [
                'required_if:forward_busy_enabled,==,true',
                'in:external,internal'
            ],
            'forward.busy.target_external' => [
                'required_if:forward.busy.type,==,external',
                'nullable',
                'phone:US',
            ],
            'forward.busy.target_internal' => [
                'required_if:forward_busy_enabled,==,true,forward.busy.type,==,internal',
                'nullable',
                'numeric',
                'ExtensionExists:' . Session::get('domain_uuid')
            ],

            'forward_no_answer_enabled' => 'in:true,false',
            'forward.no_answer.type' => [
                'required_if:forward_no_answer_enabled,==,true',
                'in:external,internal'
            ],
            'forward.no_answer.target_external' => [
                'required_if:forward.no_answer.type,==,external',
                'nullable',
                'phone:US',
            ],
            'forward.no_answer.target_internal' => [
                'required_if:forward_no_answer_enabled,==,true,forward.no_answer.type,==,internal',
                'nullable',
                'numeric',
                'ExtensionExists:' . Session::get('domain_uuid')
            ],

            'forward_user_not_registered_enabled' => 'in:true,false',
            'forward.user_not_registered.type' => [
                'required_if:forward_user_not_registered_enabled,==,true',
                'in:external,internal'
            ],
            'forward.user_not_registered.target_external' => [
                'required_if:forward.user_not_registered.type,==,external',
                'nullable',
                'phone:US',
            ],
            'forward.user_not_registered.target_internal' => [
                'required_if:forward_user_not_registered_enabled,==,true,forward.user_not_registered.type,==,internal',
                'nullable',
                'numeric',
                'ExtensionExists:' . Session::get('domain_uuid')
            ],

            'follow_me_enabled' => 'in:true,false',
            'follow_me_ignore_busy' => 'in:true,false',
            'follow_me_ring_my_phone_timeout' => 'nullable|numeric',
            'follow_me_destinations' => 'nullable|array',
            'follow_me_destinations.*.target_external' => [
                'required_if:follow_me_destinations.*.type,==,external',
                'nullable',
                'phone:US',
            ],
            'follow_me_destinations.*.target_internal' => [
                'required_if:follow_me_destinations.*.type,==,internal',
                'nullable',
                'numeric',
                'ExtensionExists:' . Session::get('domain_uuid')
            ],
            'follow_me_destinations.*.delay' => 'numeric',
            'follow_me_destinations.*.timeout' => 'numeric',
            'follow_me_destinations.*.prompt' => 'in:true,false'
        ], [
            'phone' => 'Should be valid US phone number or extension id',
            'required_if' => 'This is the required field',
            'ExtensionExists' => 'Should be valid destination'
        ], $attributes);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();

        $attributes['effective_caller_id_name'] = $attributes['directory_first_name'] . " " . $attributes['directory_last_name'];
        $attributes['effective_caller_id_number'] = $attributes['extension'];
        if (isset($attributes['directory_visible']) && $attributes['directory_visible'] == "on") $attributes['directory_visible'] = "true";
        if (isset($attributes['directory_exten_visible']) && $attributes['directory_exten_visible'] == "on") $attributes['directory_exten_visible'] = "true";
        if (isset($attributes['enabled']) && $attributes['enabled'] == "on") $attributes['enabled'] = "true";
        if (isset($attributes['voicemail_enabled']) && $attributes['voicemail_enabled'] == "on") $attributes['voicemail_enabled'] = "true";
        if (isset($attributes['voicemail_transcription_enabled']) && $attributes['voicemail_transcription_enabled'] == "on") $attributes['voicemail_transcription_enabled'] = "true";
        if (isset($attributes['voicemail_local_after_email']) && $attributes['voicemail_local_after_email'] == "false") $attributes['voicemail_local_after_email'] = "true";
        if (isset($attributes['voicemail_local_after_email']) && $attributes['voicemail_local_after_email'] == "on") $attributes['voicemail_local_after_email'] = "false";
        if (isset($attributes['voicemail_tutorial']) && $attributes['voicemail_tutorial'] == "on") $attributes['voicemail_tutorial'] = "true";
        if (isset($attributes['call_screen_enabled']) && $attributes['call_screen_enabled'] == "on") $attributes['call_screen_enabled'] = "true";
        // if (isset($attributes['outbound_caller_id_number'])) $attributes['outbound_caller_id_number'] = PhoneNumber::make($attributes['outbound_caller_id_number'], "US")->formatE164();
        // if (isset($attributes['emergency_caller_id_number'])) $attributes['emergency_caller_id_number'] = PhoneNumber::make($attributes['emergency_caller_id_number'], "US")->formatE164();

        if (isset($attributes['forward_all_enabled']) && $attributes['forward_all_enabled'] == "true") $attributes['forward_all_enabled'] = "true";

        if ($attributes['forward']['all']['type'] == 'external') {
            $attributes['forward_all_destination'] = PhoneNumber::make($attributes['forward']['all']['target_external'], "US")->formatE164();
        } else {
            $attributes['forward_all_destination'] = ($attributes['forward']['all']['target_internal'] == '0') ? '' : $attributes['forward']['all']['target_internal'];;
            if (empty($attributes['forward_all_destination'])) {
                $attributes['forward_all_enabled'] = 'false';
            }
        }

        if ($attributes['forward_all_enabled'] == 'false') {
            $attributes['forward_all_destination'] = '';
        }

        if (isset($attributes['forward_busy_enabled']) && $attributes['forward_busy_enabled'] == "true") $attributes['forward_busy_enabled'] = "true";

        if ($attributes['forward']['busy']['type'] == 'external') {
            $attributes['forward_busy_destination'] = PhoneNumber::make($attributes['forward']['busy']['target_external'], "US")->formatE164();
        } else {
            $attributes['forward_busy_destination'] = ($attributes['forward']['busy']['target_internal'] == '0') ? '' : $attributes['forward']['busy']['target_internal'];;
            if (empty($attributes['forward_busy_destination'])) {
                $attributes['forward_busy_enabled'] = 'false';
            }
        }

        if ($attributes['forward_busy_enabled'] == 'false') {
            $attributes['forward_busy_destination'] = '';
        }

        if (isset($attributes['forward_no_answer_enabled']) && $attributes['forward_no_answer_enabled'] == "true") $attributes['forward_no_answer_enabled'] = "true";

        if ($attributes['forward']['no_answer']['type'] == 'external') {
            $attributes['forward_no_answer_destination'] = PhoneNumber::make($attributes['forward']['no_answer']['target_external'], "US")->formatE164();
        } else {
            $attributes['forward_no_answer_destination'] = ($attributes['forward']['no_answer']['target_internal'] == '0') ? '' : $attributes['forward']['no_answer']['target_internal'];
            if (empty($attributes['forward_no_answer_destination'])) {
                $attributes['forward_no_answer_enabled'] = 'false';
            }
        }

        if ($attributes['forward_no_answer_enabled'] == 'false') {
            $attributes['forward_no_answer_destination'] = '';
        }

        if (isset($attributes['forward_user_not_registered_enabled']) && $attributes['forward_user_not_registered_enabled'] == "true") $attributes['forward_user_not_registered_enabled'] = "true";

        if ($attributes['forward']['user_not_registered']['type'] == 'external') {
            $attributes['forward_user_not_registered_destination'] = PhoneNumber::make($attributes['forward']['user_not_registered']['target_external'], "US")->formatE164();
        } else {
            $attributes['forward_user_not_registered_destination'] = ($attributes['forward']['user_not_registered']['target_internal'] == '0') ? '' : $attributes['forward']['user_not_registered']['target_internal'];;
            if (empty($attributes['forward_user_not_registered_destination'])) {
                $attributes['forward_user_not_registered_enabled'] = 'false';
            }
        }

        if ($attributes['forward_user_not_registered_enabled'] == 'false') {
            $attributes['forward_user_not_registered_destination'] = '';
        }

        if (isset($attributes['do_not_disturb']) && $attributes['do_not_disturb'] == "true") $attributes['do_not_disturb'] = "true";
        $attributes['update_date'] = date("Y-m-d H:i:s");
        $attributes['update_user'] = Session::get('user_uuid');

        // Check if voicemail directory needs to be renamed
        if ($attributes['voicemail_id'] != $attributes['extension']) {
            if (file_exists(getDefaultSetting('switch', 'voicemail') . "/default/" . Session::get('domain_name') . "/" . $attributes['voicemail_id'])) {
                rename(
                    getDefaultSetting('switch', 'voicemail') . "/default/" . Session::get('domain_name') . "/" . $attributes['voicemail_id'],
                    getDefaultSetting('switch', 'voicemail') . "/default/" . Session::get('domain_name') . "/" . $attributes['extension']
                );
            }
            $attributes['voicemail_id'] = $attributes['extension'];
        }

        $extension->fill($attributes);
        $extension->save();

        // Update Voicemail Destinations table
        if (isset($extension->voicemail)) {
            foreach ($extension->voicemail->voicemail_destinations as $vm_destination) {
                $vm_destination->delete();
            }
        }

        if (isset($attributes['voicemail_destinations'])) {
            foreach ($attributes['voicemail_destinations'] as $voicemail_destination) {
                $destination = new VoicemailDestinations();
                $destination->voicemail_uuid_copy = $voicemail_destination;
                $destination->domain_uuid = Session::get('domain_uuid');
                $extension->voicemail->voicemail_destinations()->save($destination);
            }
        }

        // Update Sequential destinations
        foreach ($extension->getFollowMeDestinations() as $followMeDest) {
            $followMeDest->delete();
        }

        $followMe = $extension->followMe()->first();
        if (!$followMe) {
            $followMe = new FollowMe();
            $followMe->domain_uuid = Session::get('domain_uuid');
        }
        $followMe->follow_me_enabled = $attributes['follow_me_enabled'];
        $followMe->follow_me_ignore_busy = ($attributes['follow_me_ignore_busy'] == 'true') ? 'false' : 'true';
        $followMe->update_date = date('Y-m-d H:i:s');
        $followMe->update_user = Session::get('user_uuid');
        $followMe->save();
        $extension->follow_me_uuid = $followMe->follow_me_uuid;

        if (!isset($attributes['follow_me_destinations'])) {
            $attributes['follow_me_destinations'] = [];
        }

        if ($attributes['follow_me_ring_my_phone_timeout'] && $attributes['follow_me_ring_my_phone_timeout'] > 0) {
            $attributes['follow_me_destinations'] = array_merge([
                $extension->extension_uuid => [
                    'type' => 'internal',
                    'target_internal' => $extension->extension,
                    'delay' => 0,
                    'timeout' => $attributes['follow_me_ring_my_phone_timeout'],
                    'prompt' => 'false'
                ]
            ], $attributes['follow_me_destinations']);
        }

        if (count($attributes['follow_me_destinations']) > 0) {
            $i = 0;
            foreach ($attributes['follow_me_destinations'] as $destination) {
                if ($i > 9) break;
                $followMeDest = new FollowMeDestinations();
                if ($destination['type'] == 'external') {
                    $followMeDest->follow_me_destination = format_phone_or_extension($destination['target_external']);
                } else {
                    $followMeDest->follow_me_destination = $destination['target_internal'];
                }
                $followMeDest->follow_me_delay = $destination['delay'];
                $followMeDest->follow_me_timeout = $destination['timeout'];
                if ($destination['prompt'] == 'true') {
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
        foreach ($extension->extension_users as $ext_user) {
            $ext_user->delete();
        }

        if (isset($attributes['users'])) {
            foreach ($attributes['users'] as $ext_user) {
                $extension_users = new ExtensionUser();
                $extension_users->user_uuid = $ext_user;
                $extension_users->domain_uuid = Session::get('domain_uuid');
                $extension->extension_users()->save($extension_users);
            }
        }

        //clear fusionpbx cache
        FusionCache::clear("directory:" . $extension->extension . "@" . $extension->user_context);

        if (isset($extension->voicemail)) {
            $extension->voicemail->update($attributes);
        }

        //clear the destinations session array
        if (isset($_SESSION['destinations']['array'])) {
            unset($_SESSION['destinations']['array']);
        }

        // dispatch the job to update app user
        $mobile_app = $extension->mobile_app;
        if (isset($mobile_app)) {
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
     * @param Request $request
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
                        $errormessage .= "<li>Skipping row <strong>" . $failure->row() . "</strong>. Invalid value <strong>'" . $value . "'</strong> for field <strong>'" . $failure->attribute() . "'</strong>. " . $error . "</li>";
                    }
                }
                $errormessage .= '</ul>';

                // Send response in format that Dropzone understands
                return response()->json([
                    'error' => $errormessage,
                ], 400);
            }
        } catch (Throwable $e) {
            // Log::alert($e);
            // Send response in format that Dropzone understands
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
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
     * @param \App\Models\Extentions $extentions
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $extension = Extensions::findOrFail($id);

        if (isset($extension)) {
            if (isset($extension->voicemail)) {
                $deletedvm = $extension->voicemail->delete();
            }

            if (isset($extension->extension_users)) {
                $deleted = $extension->extension_users()->delete();
            }

            $deleted = $extension->delete();

            if ($deleted) {
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


    /**
     * Restart devices for selected extensions.
     *
     * @param \App\Models\Extentions $extention
     * @return \Illuminate\Http\Response
     */
    public function sendEventNotify(Request $request, Extensions $extension)
    {

        // Get all registered devices for this domain
        $registrations = get_registrations();

        //check against registrations and add them to array
        $all_regs = [];
        foreach ($registrations as $registration) {
            if ($registration['sip-auth-user'] == $extension['extension']) {
                array_push($all_regs, $registration);
            }
        }

        // Log::alert($all_regs);

        foreach ($all_regs as $reg) {
            // Get the agent name
            if (preg_match('/Bria|Push|Ringotel/i', $reg['agent']) > 0) {
                $agent = "";
            } elseif (preg_match('/polycom|polyedge/i', $reg['agent']) > 0) {
                $agent = "polycom";
            } elseif (preg_match("/yealink/i", $reg['agent'])) {
                $agent = "yealink";
            } elseif (preg_match("/grandstream/i", $reg['agent'])) {
                $agent = "grandstream";
            }

            if ($agent != "") {
                $command = "fs_cli -x 'luarun app.lua event_notify " . $reg['sip_profile_name'] . " reboot " . $reg['user'] . " " . $agent . "'";

                // Queue a job to restart the phone
                SendEventNotify::dispatch($command)->onQueue('default');
            }
        }


        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'Successfully submitted restart request'
            ]
        ]);
    }

    public function assignDevice(AssignDeviceRequest $request, Extensions $extension)
    {
        $inputs = $request->validated();

        $deviceExist = DeviceLines::query()->where(['device_uuid' => $inputs['device_uuid']])->first();

        if ($deviceExist) {
            $deviceExist->delete();
            /*return response()->json([
                'status' => 'alert',
                'message' => 'Device is already assigned.'
            ]);*/
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

        $device = Devices::where('device_uuid', $inputs['device_uuid'])->firstOrFail();
        $device->device_label = $extension->extension;
        $device->save();

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

    private function getDestinationExtensions()
    {
        $extensions = Extensions::where('domain_uuid', Session::get('domain_uuid'))
            //->whereNotIn('extension_uuid', [$extension->extension_uuid])
            ->orderBy('extension')
            ->get();
        $ivrMenus = IvrMenus::where('domain_uuid', Session::get('domain_uuid'))
            //->whereNotIn('extension_uuid', [$extension->extension_uuid])
            ->orderBy('ivr_menu_extension')
            ->get();
        $ringGroups = RingGroups::where('domain_uuid', Session::get('domain_uuid'))
            //->whereNotIn('extension_uuid', [$extension->extension_uuid])
            ->orderBy('ring_group_extension')
            ->get();

        /* NOTE: disabling voicemails as a call forward destination
         * $voicemails = Voicemails::where('domain_uuid', Session::get('domain_uuid'))
            //->whereNotIn('extension_uuid', [$extension->extension_uuid])
            ->orderBy('voicemail_id')
            ->get();*/
        return [
            'Extensions' => $extensions,
            'Ivr Menus' => $ivrMenus,
            'Ring Groups' => $ringGroups,
            //'Voicemails' => $voicemails
        ];
    }
}
