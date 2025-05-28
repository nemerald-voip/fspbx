<?php

namespace App\Http\Controllers;

use Throwable;
use Inertia\Inertia;
use App\Models\Devices;
use App\Data\DeviceData;
use App\Models\FollowMe;
use App\Models\IvrMenus;
use App\Models\Extensions;
use App\Models\RingGroups;
use App\Models\Voicemails;
use App\Data\VoicemailData;
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
use App\Data\ExtensionListData;
use App\Jobs\UpdateAppSettings;
use Illuminate\Validation\Rule;
use App\Data\ExtensionDetailData;
use App\Imports\ExtensionsImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use libphonenumber\PhoneNumberUtil;
use App\Models\FollowMeDestinations;
use App\Data\FollowMeDestinationData;
use App\Models\VoicemailDestinations;
use libphonenumber\PhoneNumberFormat;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\HeadingRowImport;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\AssignDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use Spatie\Activitylog\Contracts\Activity;
use App\Services\CallRoutingOptionsService;
use Propaganistas\LaravelPhone\PhoneNumber;
use App\Http\Requests\OldStoreDeviceRequest;
use App\Http\Requests\OldUpdateDeviceRequest;
use App\Http\Requests\UpdateExtensionRequest;
use Spatie\Activitylog\Facades\CauserResolver;
use Propaganistas\LaravelPhone\Exceptions\NumberParseException;


class ExtensionsController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Extensions';

    public function __construct()
    {
        $this->model = new Extensions();
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

        $perPage = 50;
        $currentDomain = session('domain_uuid');

        $extensions = QueryBuilder::for(Extensions::class)
            // only extensions in the current domain
            ->where('domain_uuid', $currentDomain)
            ->with(['voicemail' => function ($query) use ($currentDomain) {
                $query->where('domain_uuid', $currentDomain)
                    ->select('voicemail_id', 'domain_uuid', 'voicemail_mail_to');
            }])
            ->select([
                'extension_uuid',
                'domain_uuid',
                'extension',
                'effective_caller_id_name',
                'effective_caller_id_number',
                'outbound_caller_id_number',
                'emergency_caller_id_number',
                'directory_first_name',
                'directory_last_name',
                'directory_visible',
                'enabled',
                'do_not_disturb',
                'description',
                'forward_all_enabled',
                'forward_busy_enabled',
                'forward_no_answer_enabled',
                'forward_user_not_registered_enabled',
                'follow_me_enabled',
            ])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value)  use ($currentDomain) {
                    $query->where(function ($q) use ($value, $currentDomain) {
                        $q->where('extension', 'ilike', "%{$value}%")
                            ->orWhere('effective_caller_id_name', 'ilike', "%{$value}%")
                            ->orWhere('outbound_caller_id_number', 'ilike', "%{$value}%")
                            ->orWhere('directory_first_name', 'ilike', "%{$value}%")
                            ->orWhere('directory_last_name', 'ilike', "%{$value}%")
                            ->orWhere('description', 'ilike', "%{$value}%")
                            // Search related voicemail email
                            ->orWhereHas('voicemail', function ($q2) use ($value, $currentDomain) {
                                $q2->where('domain_uuid', $currentDomain)
                                    ->where('voicemail_mail_to', 'ilike', "%{$value}%");
                            });
                        // Add more fields if needed
                    });
                }),
                AllowedFilter::exact('enabled'), // Example: filter[enabled]=true
            ])
            // allow ?sort=-username or ?sort=add_date
            ->allowedSorts(['extension'])
            ->defaultSort('extension')
            ->paginate($perPage)
            ->appends($request->query());

        // wrap in your DTO
        $extensionsDto = ExtensionListData::collect($extensions);

        // logger($extensionsDto);

        return Inertia::render(
            $this->viewName,
            [
                'data' => $extensionsDto,

                'routes' => [
                    'current_page' => route('extensions.index'),
                    'item_options' => route('extensions.item.options'),
                    'bulk_delete' => route('extensions.bulk.delete'),
                    'select_all' => route('extensions.select.all'),
                    'registrations' => route('extensions.registrations'),
                ]
            ]
        );

        $searchString = $request->get('search');

        // Get all registered devices for this domain
        $registrations = get_registrations();


        $extensions = $extensions->paginate(50)->onEachSide(1);

        foreach ($extensions as $extension) {

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
    }

    public function registrations(FreeswitchEslService $esl)
    {
        // Check permissions if needed
        if (!userCheckPermission('extension_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        try {
            $registrations = $esl->getAllSipRegistrations();
            // Optionally, if you want to filter only for the current domain/extensions, do it here.
            $currentDomain = session('domain_name');
            // Filter by sip_auth_realm (domain)
            $domainRegistrations = $registrations->filter(function ($reg) use ($currentDomain) {
                return $reg['sip_auth_realm'] === $currentDomain;
            })->values();

            $grouped = $domainRegistrations
                ->groupBy('sip_auth_user')
                ->map(function ($items) {
                    return $items->map(function ($reg) {
                        return [
                            'agent'    => $reg['agent'],
                            'wan_ip'   => $reg['wan_ip'],
                            'transport' => $reg['transport'],
                            'expsecs'  => $reg['expsecs'],
                        ];
                    })->values();
                });

            return response()->json([
                'success' => true,
                'registrations' => $grouped,
            ]);
        } catch (\Throwable $e) {
            logger('ExtensionsController@registrations error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'messages' => ['error' => [$e->getMessage()]],
            ], 500);
        }
    }

    public function getItemOptions(Request $request)
    {
        $itemUuid = $request->input('item_uuid');

        $currentDomain = session('domain_uuid');

        $routes = [];
        // 1) Base payload: either an existing user DTO or a “new extension stub
        if ($itemUuid) {
            $extension = QueryBuilder::for(Extensions::class)
                ->select([
                    'extension_uuid',
                    'domain_uuid',
                    'extension',
                    'effective_caller_id_name',
                    'effective_caller_id_number',
                    'outbound_caller_id_number',
                    'emergency_caller_id_number',
                    'directory_first_name',
                    'directory_last_name',
                    'directory_visible',
                    'directory_exten_visible',
                    'enabled',
                    'do_not_disturb',
                    'description',
                    'do_not_disturb',
                    'forward_all_destination',
                    'forward_all_enabled',
                    'forward_busy_destination',
                    'forward_busy_enabled',
                    'forward_no_answer_destination',
                    'forward_no_answer_enabled',
                    'forward_user_not_registered_destination',
                    'forward_user_not_registered_enabled',
                    'follow_me_uuid',
                    'follow_me_enabled',
                ])
                ->with([
                    'voicemail' => function ($query) use ($currentDomain) {
                        $query->where('domain_uuid', $currentDomain)
                            ->select(
                                'voicemail_uuid',
                                'voicemail_id',
                                'domain_uuid',
                                'greeting_id',
                                'voicemail_password',
                                'voicemail_mail_to',
                                'voicemail_transcription_enabled',
                                'voicemail_file',
                                'voicemail_local_after_email',
                                'voicemail_enabled',
                                'voicemail_description',
                            )
                            ->with([
                                'voicemail_destinations' => function ($q) {
                                    $q->select('voicemail_destination_uuid', 'voicemail_uuid', 'voicemail_uuid_copy');
                                },
                                'greetings' => function ($query) use ($currentDomain) {
                                    $query->select('voicemail_id', 'greeting_id', 'greeting_name', 'greeting_description')
                                        ->where('domain_uuid', $currentDomain);
                                }
                            ]);
                    },
                    'followMe.followMeDestinations' => function ($q) {
                        $q->select([
                            'follow_me_destination_uuid',
                            'follow_me_uuid',
                            'follow_me_destination',
                            'follow_me_delay',
                            'follow_me_timeout',
                            'follow_me_prompt',
                            'follow_me_order',
                        ])->orderBy('follow_me_order');
                    }
                ])
                ->whereKey($itemUuid)
                ->firstOrFail()
                ->append([
                    'emergency_caller_id_number_e164',
                    'outbound_caller_id_number_e164',

                    // Unconditional forwarding (all)
                    'forward_all_target_uuid',
                    'forward_all_action',
                    'forward_all_action_display',
                    'forward_all_target_name',
                    'forward_all_target_extension',

                    // Busy forwarding
                    'forward_busy_target_uuid',
                    'forward_busy_action',
                    'forward_busy_action_display',
                    'forward_busy_target_name',
                    'forward_busy_target_extension',

                    // No answer forwarding
                    'forward_no_answer_target_uuid',
                    'forward_no_answer_action',
                    'forward_no_answer_action_display',
                    'forward_no_answer_target_name',
                    'forward_no_answer_target_extension',

                    // User not registered forwarding
                    'forward_user_not_registered_target_uuid',
                    'forward_user_not_registered_action',
                    'forward_user_not_registered_action_display',
                    'forward_user_not_registered_target_name',
                    'forward_user_not_registered_target_extension',
                ]);

            $extensionDto = ExtensionDetailData::from([
                ...$extension->toArray(),
                'follow_me_destinations' => $extension->followMe
                    ? FollowMeDestinationData::collect($extension->followMe->followMeDestinations->sortBy('follow_me_order'))
                    : [],
            ]);
            $updateRoute = route('extensions.update', ['extension' => $itemUuid]);
            $deviceRoute = route('extensions.devices', ['extension' => $itemUuid]);

            $voicemailDestinations = $extension->voicemail && $extension->voicemail->voicemail_destinations
                ? $extension->voicemail->voicemail_destinations->pluck('voicemail_uuid_copy')->values()->all()
                : [];

            $voicemailGreetings = $extension->voicemail && $extension->voicemail->greetings
                ? $extension->voicemail->greetings
                ->sortBy('greeting_id')
                ->map(function ($greeting) {
                    return [
                        'value' => $greeting->greeting_id,
                        'label' => $greeting->greeting_name,
                        'description' => $greeting->greeting_description,
                    ];
                })->toArray()
                : [];

            // Add the default options at the beginning of the array
            array_unshift(
                $voicemailGreetings,
                ['value' => '0', 'label' => 'None'],
                ['value' => '-1', 'label' => 'System Default']
            );

            $voicemailDto = VoicemailData::from([
                ...$extension->voicemail->toArray(),
                'voicemail_destinations' => $voicemailDestinations,
                'greetings' => $voicemailGreetings,
            ]);

            if ($extension->voicemail) {
                $routes = array_merge($routes, [
                    'text_to_speech_route' => route('voicemails.textToSpeech', $extension->voicemail),
                    'text_to_speech_route_for_name' => route('voicemails.textToSpeechForName', $extension->voicemail),
                    'greeting_route' => route('voicemail.greeting', $extension->voicemail),
                    'delete_greeting_route' => route('voicemails.deleteGreeting', $extension->voicemail),
                    'upload_greeting_route' => route('voicemails.uploadGreeting', $extension->voicemail),
                    'upload_greeting_route_for_name' => route('voicemails.uploadRecordedName', $extension->voicemail),
                    'recorded_name_route' => route('voicemail.recorded_name', $extension->voicemail),
                    'delete_recorded_name_route' => route('voicemails.deleteRecordedName', $extension->voicemail),
                    'upload_recorded_name_route' => route('voicemails.uploadRecordedName', $extension->voicemail),
                    'update_route' => route('voicemails.update', $extension->voicemail),
                    'device_item_options' => route('devices.item.options'),
                ]);
            }
        } else {
            // “New extension defaults
            $userDto     = new ExtensionDetailData(
                extension_uuid: '',
                user_email: '',
                name_formatted: '',
                first_name: '',
                last_name: '',
                language: 'en-us',
                time_zone: get_local_time_zone(),
                user_enabled: 'true',
                domain_uuid: session('domain_uuid'),
            );
            $updateRoute = null;
        }

        // 2) Permissions array (you’ll have to implement this)
        $permissions = $this->getUserPermissions();

        $phone_numbers = QueryBuilder::for(Destinations::class)
            ->allowedFilters(['destination_number', 'destination_description'])
            ->allowedSorts('destination_number')
            ->where('destination_enabled', 'true')
            ->where('domain_uuid', $currentDomain)
            ->get([
                'destination_uuid',
                'destination_number',
                'destination_description',
            ])
            ->each->append('label', 'destination_number_e164')
            ->map(function ($destination) {
                return [
                    'value' => $destination->destination_number_e164,
                    'label' => $destination->label,
                ];
            })
            ->prepend([
                'value' => '',
                'label' => 'Main Company Number',
            ])
            ->values()
            ->toArray();


        $allVoicemails =  Voicemails::where('domain_uuid', $currentDomain)
            ->with(['extension' => function ($query) use ($currentDomain) {
                $query->select('extension_uuid', 'extension', 'effective_caller_id_name')
                    ->where('domain_uuid', $currentDomain);
            }])
            ->select(
                'voicemail_uuid',
                'voicemail_id',
                'voicemail_description',

            )
            ->orderBy('voicemail_id', 'asc')
            ->get()
            ->map(function ($voicemail) {
                return [
                    'value' => $voicemail->voicemail_uuid,
                    'label' => $voicemail->extension
                        ? $voicemail->extension->name_formatted
                        : $voicemail->voicemail_id
                        . ' - Team Voicemail'
                        . ($voicemail->voicemail_description ? " ({$voicemail->voicemail_description})" : ''),
                ];
            })
            ->values()
            ->toArray();


        // 3) Any routes your front end needs
        $routes = array_merge($routes, [
            'store_route'  => route('extensions.store'),
            'update_route' => $updateRoute ?? null,
            'devices' => $deviceRoute ?? null,
            'get_routing_options' => route('routing.options'),
        ]);

        $routingOptionsService = new CallRoutingOptionsService;
        $forwardingTypes = $routingOptionsService->forwardingTypes;


        $extensions = Extensions::where('domain_uuid', $currentDomain)
            ->select('extension_uuid', 'extension', 'effective_caller_id_name')
            ->orderBy('extension', 'asc')
            ->get();


        $ringGroups = RingGroups::where('domain_uuid', $currentDomain)
            ->select('ring_group_uuid', 'ring_group_extension', 'ring_group_name')
            ->orderBy('ring_group_extension', 'asc')
            ->get();

        $followMeDestinationOptions = [
            [
                'groupLabel' => 'Extensions',
                'groupOptions' => $extensions->map(function ($extension) {
                    return [
                        'value' => $extension->extension_uuid,
                        'label' => $extension->name_formatted,
                        'destination' => $extension->extension,
                        'type' => 'extension',
                    ];
                })->toArray(),
            ],
            [
                'groupLabel' => 'Ring Groups',
                'groupOptions' => $ringGroups->map(function ($group) {
                    return [
                        'value' => $group->ring_group_uuid,
                        'label' => $group->name_formatted,
                        'destination' => $group->ring_group_extension,
                        'type' => 'ring_group',
                    ];
                })->toArray(),
            ]
        ];

        // Define the instructions for recording a voicemail greeting using a phone call
        $phoneCallInstructions = [
            'Dial <strong>*98</strong> from your phone.',
            'Enter the mailbox number and press <strong>#</strong>.',
            'Enter the voicemail password and press <strong>#</strong>.',
            'Press <strong>5</strong> for mailbox options.',
            'Press <strong>1</strong> to record an unavailable message.',
            'Choose a greeting number (1-9) to record, then follow the prompts.',
        ];

        // Define the instructions for recording a name using a phone call
        $phoneCallInstructionsForName = [
            'Dial <strong>*98</strong> from your phone.',
            'Enter the mailbox number and press <strong>#</strong>.',
            'Enter the voicemail password and press <strong>#</strong>.',
            'Press <strong>5</strong> for mailbox options.',
            'Press <strong>3</strong> to record your name, then follow the prompts.',
        ];

        $sampleMessage = 'Thank you for calling. Please, leave us a message and will call you back as soon as possible';

        $openAiService = app(\App\Services\OpenAIService::class);

        return response()->json([
            'item'        => $extensionDto,
            'voicemail' => $voicemailDto,
            'all_voicemails' => $allVoicemails,
            'permissions' => $permissions,
            'routes'      => $routes,
            'phone_numbers' => $phone_numbers,
            'forwarding_types' => $forwardingTypes,
            'follow_me_destination_options' => $followMeDestinationOptions,
            'voices' => $openAiService->getVoices(),
            'speeds' => $openAiService->getSpeeds(),
            'phone_call_instructions' => $phoneCallInstructions,
            'phone_call_instructions_for_name' => $phoneCallInstructionsForName,
            'sample_message' => $sampleMessage,
            'recorded_name' => Storage::disk('voicemail')->exists(session('domain_name') . '/' . $voicemailDto->voicemail_id . '/recorded_name.wav') ? 'Custom recording' : 'System Default',
        ]);
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

                if ($phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164) == (new PhoneNumber($extension->outbound_caller_id_number, "US"))->formatE164()) {
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
    public function updateCallerID($extension_uuid)
    {
        $extension = Extensions::find($extension_uuid);
        if (!$extension) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'Invalid extension. Please, contact administrator'
                ]
            ]);
        }

        $destination = Destinations::find(request('destination_uuid'));
        if (!$destination) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'Invalid phone number ID submitted. Please, contact your administrator'
                ]
            ]);
        }

        // set causer for activity log
        CauserResolver::setCauser($extension);

        // Update the caller ID field for user's extension
        // If successful delete cache
        if (request('set') == "true") {
            try {
                $extension->outbound_caller_id_number = (new PhoneNumber($destination->destination_number, "US"))->formatE164();
            } catch (NumberParseException $e) {
                $extension->outbound_caller_id_number = $destination->destination_number;
            }
        } else {
            $extension->outbound_caller_id_number = null;
        }
        $extension->save();

        //clear fusionpbx cache
        FusionCache::clear("directory:" . $extension->extension . "@" . $extension->user_context);


        // If successful return success status
        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'The caller ID was successfully updated'
            ]
        ]);
    }

    // /**
    //  * Show the form for creating a new resource.
    //  *
    //  * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\Response
    //  */
    // public function create()
    // {

    //     //check permissions
    //     if (!userCheckPermission('extension_add') || !userCheckPermission('extension_edit')) {
    //         return redirect('/');
    //     }

    //     // Get all phone numbers
    //     $destinations = Destinations::where('destination_enabled', 'true')
    //         ->where('domain_uuid', Session::get('domain_uuid'))
    //         ->get([
    //             'destination_uuid',
    //             'destination_number',
    //             'destination_enabled',
    //             'destination_description',
    //             DB::Raw("coalesce(destination_description , '') as destination_description"),
    //         ])
    //         ->sortBy('destination_number');

    //     //Get libphonenumber object
    //     $phoneNumberUtil = PhoneNumberUtil::getInstance();

    //     foreach ($destinations as $destination) {
    //         try {
    //             $phoneNumberObject = $phoneNumberUtil->parse($destination->destination_number, 'US');
    //             if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
    //                 $destination->destination_number = $phoneNumberUtil
    //                     ->format($phoneNumberObject, PhoneNumberFormat::E164);
    //             }

    //             // Set the label
    //             $phoneNumber = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
    //             $destination->label = isset($destination->destination_description) && !empty($destination->destination_description)
    //                 ? $phoneNumber . " - " . $destination->destination_description
    //                 : $phoneNumber;
    //         } catch (NumberParseException $e) {
    //             // Do nothing and leave the numbner as is

    //             //Set the label
    //             $destination->label = isset($destination->destination_description) && !empty($destination->destination_description)
    //                 ? $destination->destination_number . " - " . $destination->destination_description
    //                 : $destination->destination_number;
    //         }

    //         $destination->isCallerID = false;
    //         $destination->isEmergencyCallerID = false;
    //     }

    //     // Get music on hold
    //     $moh = MusicOnHold::where('domain_uuid', Session::get('domain_uuid'))
    //         ->orWhere('domain_uuid', null)
    //         ->orderBy('music_on_hold_name', 'ASC')
    //         ->get()
    //         ->unique('music_on_hold_name');

    //     $recordings = Recordings::where('domain_uuid', Session::get('domain_uuid'))
    //         ->orderBy('recording_name', 'ASC')
    //         ->get();

    //     $extension = new Extensions();
    //     $extension->directory_visible = "true";
    //     $extension->directory_exten_visible = "true";
    //     $extension->enabled = "true";
    //     $extension->user_context = Session::get('domain_name');
    //     $extension->accountcode = Session::get('domain_name');
    //     $extension->limit_destination = "!USER_BUSY";
    //     $extension->limit_max = "5";
    //     $extension->call_timeout = "25";
    //     $extension->forward_all_enabled = "false";
    //     $extension->forward_busy_enabled = "false";
    //     $extension->forward_no_answer_enabled = "false";
    //     $extension->forward_user_not_registered_enabled = "false";
    //     $extension->follow_me_enabled = "false";
    //     $extension->do_not_disturb = "false";

    //     return view('layouts.extensions.createOrUpdate')
    //         ->with('extension', $extension)
    //         ->with('extensions', $this->getDestinationExtensions())
    //         ->with('destinations', $destinations)
    //         ->with('follow_me_destinations', [])
    //         ->with('domain_users', $extension->domain->users)
    //         ->with('follow_me_ring_my_phone_timeout', 0)
    //         ->with('moh', $moh)
    //         ->with('recordings', $recordings)
    //         ->with('national_phone_number_format', PhoneNumberFormat::NATIONAL);
    // }

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
            'exclude_from_ringotel_stale_users' => "nullable|string",

            'forward_all_enabled' => 'in:true,false',
            'forward.all.type' => [
                'required_if:forward_all_enabled,==,true',
                'in:external,internal'
            ],
            'forward.all.target_external' => [
                'required_if:forward.all.type,==,external',
                'nullable',
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
        if (isset($attributes['force_ping']) && $attributes['force_ping'] == "on") $attributes['force_ping'] = "true";
        if (isset($attributes['exclude_from_ringotel_stale_users']) && $attributes['exclude_from_ringotel_stale_users'] == "on") $attributes['exclude_from_ringotel_stale_users'] = "true";
        $attributes['voicemail_enabled'] = "true";
        $attributes['voicemail_transcription_enabled'] = "true";
        $attributes['voicemail_recording_instructions'] = "true";
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

        $attributes['insert_date'] = date("Y-m-d H:i:s");
        $attributes['insert_user'] = Session::get('user_uuid');

        if (isset($attributes['forward_all_enabled']) && $attributes['forward_all_enabled'] == "true") $attributes['forward_all_enabled'] = "true";

        if ($attributes['forward']['all']['type'] == 'external') {
            $attributes['forward_all_destination'] = (new PhoneNumber($attributes['forward']['all']['target_external'], "US"))->formatE164();
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
            $attributes['forward_busy_destination'] = (new PhoneNumber($attributes['forward']['busy']['target_external'], "US"))->formatE164();
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
            $attributes['forward_no_answer_destination'] = (new PhoneNumber($attributes['forward']['no_answer']['target_external'], "US"))->formatE164();
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
            $attributes['forward_user_not_registered_destination'] = (new PhoneNumber($attributes['forward']['user_not_registered']['target_external'], "US"))->formatE164();
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
        // if($attributes['suspended']) $attributes['do_not_disturb'] = "true";

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
                    'target_internal' => $extension->extension,
                    'target_external' => null,
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
                if ($destination['target_external'] == 'external') {
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
            //clear fusionpbx cache
            FusionCache::clear("directory:" . $extension->extension . "@" . $extension->user_context);
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


    public function devices(Request $request, $extension_uuid)
    {

        $currentDomain = session('domain_uuid');
        try {

            $extension = QueryBuilder::for(\App\Models\Extensions::query())
            ->with([
                'deviceLines' => function ($q) use ($currentDomain) {
                    $q->where('domain_uuid', $currentDomain)
                        ->select('device_line_uuid', 'device_uuid', 'auth_id', 'domain_uuid')
                        ->with(['device' => function ($query) {
                            $query->select('device_uuid', 'device_profile_uuid', 'device_address', 'device_template')
                                  ->with(['profile' => function ($profileQuery) {
                                      $profileQuery->select('device_profile_uuid', 'device_profile_name'); // Add fields as needed
                                  }]);
                        }]);
                }
            ])
            ->select('extension_uuid', 'extension')
            ->where('extension_uuid', $extension_uuid)
            ->first();


            $devices = collect($extension->deviceLines)
            ->pluck('device')
            ->filter()            // Remove any nulls (just in case)
            ->values();           // Re-index array

            $devicesData = DeviceData::collect($devices);

            // logger($devicesData->toArray());

            return response()->json([
                'success'  => true,
                'data'  => $devicesData,
            ]);
        } catch (\Throwable $e) {
            logger('ExtensionsController@devices error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success'  => false,
                'messages' => ['error' => [$e->getMessage()]],
                'data'     => [],
            ], 500);
        }
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param Extension $extention
     * @return \Illuminate\Http\Response
     */
    // public function edit(Extensions $extension)
    // {

    //     //check permissions
    //     if (!userCheckPermission('extension_add') && !userCheckPermission('extension_edit')) {
    //         return redirect('/');
    //     }

    //     $devices = Devices::where('device_enabled', 'true')
    //         ->where('domain_uuid', Session::get('domain_uuid'))
    //         /*->whereNotExists( function ($query) {
    //             $query->select(DB::raw(1))
    //                 ->from('v_device_lines')
    //                 ->whereRaw('v_devices.device_uuid = v_device_lines.device_uuid');
    //         })*/
    //         ->get();

    //     $vendors = DeviceVendor::where('enabled', 'true')->orderBy('name')->get();
    //     $profiles = DeviceProfile::where('device_profile_enabled', 'true')
    //         ->where('domain_uuid', Session::get('domain_uuid'))
    //         ->orderBy('device_profile_name')->get();

    //     // Get all phone numbers
    //     $destinations = Destinations::where('destination_enabled', 'true')
    //         ->where('domain_uuid', Session::get('domain_uuid'))
    //         ->get([
    //             'destination_uuid',
    //             'destination_number',
    //             'destination_enabled',
    //             'destination_description',
    //             DB::Raw("coalesce(destination_description , '') as destination_description"),
    //         ])
    //         ->sortBy('destination_number');

    //     //Get libphonenumber object
    //     $phoneNumberUtil = PhoneNumberUtil::getInstance();

    //     //try to convert emergency caller ID to e164 format
    //     if ($extension->emergency_caller_id_number) {
    //         $extension->emergency_caller_id_number = formatPhoneNumber($extension->emergency_caller_id_number, "US", 0); // 0 is E164 format
    //         // try {
    //         //     $phoneNumberObject = $phoneNumberUtil->parse($extension->emergency_caller_id_number, 'US');
    //         //     if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
    //         //         $extension->emergency_caller_id_number = $phoneNumberUtil
    //         //             ->format($phoneNumberObject, PhoneNumberFormat::E164);
    //         //     }
    //         // } catch (NumberParseException $e) {
    //         //     // Do nothing and leave the numbner as is
    //         // }
    //     }

    //     //try to convert caller ID to e164 format
    //     if ($extension->outbound_caller_id_number) {
    //         $extension->outbound_caller_id_number = formatPhoneNumber($extension->outbound_caller_id_number, "US", 0); // 0 is E164 format
    //         // try {
    //         //     $phoneNumberObject = $phoneNumberUtil->parse($extension->outbound_caller_id_number, 'US');
    //         //     if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
    //         //         $extension->outbound_caller_id_number = $phoneNumberUtil
    //         //             ->format($phoneNumberObject, PhoneNumberFormat::E164);
    //         //     }
    //         // } catch (NumberParseException $e) {
    //         //     // Do nothing and leave the numbner as is
    //         // }
    //     }

    //     foreach ($destinations as $destination) {
    //         try {
    //             $phoneNumberObject = $phoneNumberUtil->parse($destination->destination_number, 'US');
    //             if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
    //                 $destination->destination_number = $phoneNumberUtil
    //                     ->format($phoneNumberObject, PhoneNumberFormat::E164);
    //             }

    //             // Set the label
    //             $phoneNumber = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
    //             $destination->label = isset($destination->destination_description) && !empty($destination->destination_description)
    //                 ? $phoneNumber . " - " . $destination->destination_description
    //                 : $phoneNumber;
    //         } catch (NumberParseException $e) {
    //             // Do nothing and leave the numbner as is

    //             //Set the label
    //             $destination->label = isset($destination->destination_description) && !empty($destination->destination_description)
    //                 ? $destination->destination_number . " - " . $destination->destination_description
    //                 : $destination->destination_number;
    //         }

    //         $destination->isCallerID = ($destination->destination_number === $extension->outbound_caller_id_number);
    //         $destination->isEmergencyCallerID = ($destination->destination_number === $extension->emergency_caller_id_number);
    //     }

    //     $vm_unavailable_file_exists = Storage::disk('voicemail')
    //         ->exists(Session::get('domain_name') . '/' . $extension->extension . '/greeting_1.wav');

    //     $vm_name_file_exists = Storage::disk('voicemail')
    //         ->exists(Session::get('domain_name') . '/' . $extension->extension . '/recorded_name.wav');

    //     // Get music on hold
    //     $moh = MusicOnHold::where('domain_uuid', Session::get('domain_uuid'))
    //         ->orWhere('domain_uuid', null)
    //         ->orderBy('music_on_hold_name', 'ASC')
    //         ->get()
    //         ->unique('music_on_hold_name');

    //     $recordings = Recordings::where('domain_uuid', Session::get('domain_uuid'))
    //         ->orderBy('recording_name', 'ASC')
    //         ->get();

    //     //Check if there is voicemail for this extension
    //     if (!isset($extension->voicemail)) {
    //         $extension->voicemail = new Voicemails();
    //     }

    //     $follow_me_ring_my_phone_timeout = 0;
    //     $follow_me_destinations = $extension->getFollowMeDestinations();
    //     if ($follow_me_destinations->count() > 0) {
    //         if ($follow_me_destinations[0]->follow_me_destination == $extension->extension) {
    //             $follow_me_ring_my_phone_timeout = $follow_me_destinations[0]->follow_me_timeout;
    //             unset($follow_me_destinations[0]);
    //         }
    //     }

    //     return view('layouts.extensions.createOrUpdate')
    //         ->with('extension', $extension)
    //         ->with('domain_users', $extension->domain->users)
    //         ->with('domain_voicemails', $extension->domain->voicemails)
    //         ->with('extensions', $this->getDestinationExtensions())
    //         ->with('extension_users', $extension->users())
    //         ->with('destinations', $destinations)
    //         ->with('follow_me_destinations', $follow_me_destinations)
    //         ->with('follow_me_ring_my_phone_timeout', $follow_me_ring_my_phone_timeout)
    //         ->with('vm_unavailable_file_exists', $vm_unavailable_file_exists)
    //         ->with('vm_name_file_exists', $vm_name_file_exists)
    //         ->with('moh', $moh)
    //         ->with('recordings', $recordings)
    //         ->with('devices', $devices)
    //         ->with('vendors', $vendors)
    //         ->with('profiles', $profiles);
    // }


    public function update(UpdateExtensionRequest $request, $id)
    {
        try {
            $data = $request->validated();
            logger($data);

            $currentDomain = session('domain_uuid');

            $extension = Extensions::with(['advSettings', 'followMe.followMeDestinations',])
                ->with(['voicemail' => function ($query) use ($currentDomain) {
                    $query->where('domain_uuid', $currentDomain);
                }])
                ->where('extension_uuid', $id)
                ->firstOrFail();

            // Build Forwarding destinations
            $forwardTypes = ['forward_all', 'forward_busy', 'forward_no_answer', 'forward_user_not_registered'];
            foreach ($forwardTypes as $type) {
                $enabledKey = "{$type}_enabled";
                $actionKey = "{$type}_action";
                $targetKey = "{$type}_target";
                $externalKey = "{$type}_external_target";
                $destinationKey = "{$type}_destination";

                if (
                    !empty($data[$enabledKey])
                    && !empty($data[$actionKey])
                    && (
                        !empty($data[$targetKey]) || !empty($data[$externalKey])
                    )
                ) {
                    $data[$destinationKey] = $this->buildForwardDestinationTarget($data, $type);
                }
            }

            // Handle Follow Me
            if (isset($data['follow_me_enabled'])) {
                $followMeUuid = $this->saveOrUpdateFollowMe($extension, $data);
                $data['follow_me_uuid'] = $followMeUuid;
            }

            $extension->update($data);

            // Update related models
            $extension->voicemail->update($data);
            $extension->advSettings->update($data);

            // Handle voicemail_destinations (copies)
            if (
                isset($data['voicemail_destinations'])
                && is_array($data['voicemail_destinations'])
                && $extension->voicemail
            ) {
                $extension->voicemail->syncCopies($data['voicemail_destinations']);
            }

            logger($extension->toArray());


            return response()->json([
                'messages' => ['success' => ['Extension updated successfully']],
                'extension' => $extension->fresh(['voicemail', 'advSettings']),
            ], 200);
        } catch (\Throwable $e) {
            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'messages' => ['error' => ['An error occurred while updating the extension.', $e->getMessage()]],
            ], 500);
        }
    }

    function saveOrUpdateFollowMe($extension, $data)
    {
        // If follow_me is disabled, wipe everything
        if (isset($data['follow_me_enabled']) && $data['follow_me_enabled'] === 'false') {
            if ($extension->followMe) {
                $extension->followMe->followMeDestinations()->delete();
                $extension->followMe->delete();
            }
            return null;
        }

        $currentDomain = $extension->domain_uuid; // or session('domain_uuid')
        $followMe = $extension->followMe;

        if (!$followMe) {
            $followMeUuid = Str::uuid()->toString();
            $extension->follow_me_uuid = $followMeUuid; // assign, but don't save yet
            $followMe = new FollowMe([
                'follow_me_uuid' => $followMeUuid,
                'domain_uuid' => $currentDomain,
            ]);
        } else {
            $followMeUuid = $followMe->follow_me_uuid;
        }

        $followMe->fill([
            'follow_me_enabled' => $data['follow_me_enabled'],
            // add other fields if needed
        ]);
        $followMe->save();

        // Build destinations
        $destinations = $data['follow_me_destinations'] ?? [];

        if (!empty($data['follow_me_ring_my_phone_timeout'])) {
            $mainTimeout = (int)$data['follow_me_ring_my_phone_timeout'];
            array_unshift($destinations, [
                'destination' => $data['extension'],
                'delay' => 0,
                'timeout' => $mainTimeout,
                'prompt' => false,
            ]);
            foreach ($destinations as $idx => &$dest) {
                if ($idx === 0) continue;
                $dest['delay'] = isset($dest['delay']) ? ((int)$dest['delay'] + $mainTimeout) : $mainTimeout;
            }
            unset($dest);
        }

        $followMe->followMeDestinations()->delete();
        foreach ($destinations as $idx => $dest) {
            $followMe->followMeDestinations()->create([
                'follow_me_destination' => $dest['destination'],
                'follow_me_delay' => $dest['delay'] ?? 0,
                'follow_me_timeout' => $dest['timeout'] ?? 30,
                'follow_me_prompt' => isset($dest['prompt']) && $dest['prompt'] ? 'true' : 'false',
                'follow_me_order' => $idx + 1,
            ]);
        }

        return $followMe->follow_me_uuid;
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Extentions $extentions
     * @return \Illuminate\Http\Response
     */
    public function update_old(UpdateExtensionRequest $request, Extensions $extension)
    {

        $data = $request->validated();
        logger($data);
        return;

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();

        $attributes['effective_caller_id_name'] = $attributes['directory_first_name'] . " " . $attributes['directory_last_name'];
        $attributes['effective_caller_id_number'] = $attributes['extension'];
        if (isset($attributes['directory_visible']) && $attributes['directory_visible'] == "on") $attributes['directory_visible'] = "true";
        if (isset($attributes['directory_exten_visible']) && $attributes['directory_exten_visible'] == "on") $attributes['directory_exten_visible'] = "true";
        if (isset($attributes['enabled']) && $attributes['enabled'] == "on") $attributes['enabled'] = "true";
        if (isset($attributes['suspended']) && $attributes['suspended'] == "on") $attributes['suspended'] = true;
        else  $attributes['suspended'] = false;
        if (isset($attributes['force_ping']) && $attributes['force_ping'] == "on") $attributes['force_ping'] = "true";
        if (isset($attributes['exclude_from_ringotel_stale_users']) && $attributes['exclude_from_ringotel_stale_users'] == "on") $attributes['exclude_from_ringotel_stale_users'] = "true";
        if (isset($attributes['voicemail_enabled']) && $attributes['voicemail_enabled'] == "on") $attributes['voicemail_enabled'] = "true";
        if (isset($attributes['voicemail_transcription_enabled']) && $attributes['voicemail_transcription_enabled'] == "on") $attributes['voicemail_transcription_enabled'] = "true";
        if (isset($attributes['voicemail_local_after_email']) && $attributes['voicemail_local_after_email'] == "false") $attributes['voicemail_local_after_email'] = "true";
        if (isset($attributes['voicemail_local_after_email']) && $attributes['voicemail_local_after_email'] == "on") $attributes['voicemail_local_after_email'] = "false";
        if (isset($attributes['voicemail_tutorial']) && $attributes['voicemail_tutorial'] == "on") $attributes['voicemail_tutorial'] = "true";
        if (isset($attributes['call_screen_enabled']) && $attributes['call_screen_enabled'] == "on") $attributes['call_screen_enabled'] = "true";
        if (isset($attributes['forward_all_enabled']) && $attributes['forward_all_enabled'] == "true") $attributes['forward_all_enabled'] = "true";



        if (isset($attributes['do_not_disturb']) && $attributes['do_not_disturb'] == "true") $attributes['do_not_disturb'] = "true";
        // if($attributes['suspended']) $attributes['do_not_disturb'] = "true";

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
        $extension->save();

        if (!isset($attributes['follow_me_destinations'])) {
            $attributes['follow_me_destinations'] = [];
        }

        if ($attributes['follow_me_ring_my_phone_timeout'] && $attributes['follow_me_ring_my_phone_timeout'] > 0) {
            $attributes['follow_me_destinations'] = array_merge([
                $extension->extension_uuid => [
                    'target_external' => null,
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
                if ($destination['target_external'] != '') {
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

        if ($extension->advSettings) {
            // Perform the update
            $extension->advSettings->update($attributes);
        } else {
            $extension->advSettings()->create($attributes);
        }

        //clear the destinations session array
        if (isset($_SESSION['destinations']['array'])) {
            unset($_SESSION['destinations']['array']);
        }

        // dispatch the job to update app user
        $mobile_app = $extension->mobile_app;
        if (isset($mobile_app) && isset($attributes['exclude_from_ringotel_stale_users'])) {
            if (Schema::hasColumn('mobile_app_users', 'exclude_from_stale_report')) {
                $mobile_app->exclude_from_stale_report = $attributes['exclude_from_ringotel_stale_users'];
                $mobile_app->save();
            }
            $mobile_app->name = $attributes['effective_caller_id_name'];
            $mobile_app->email = $attributes['voicemail_mail_to'] ?? "";
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
     * @return \Illuminate\Http\JsonResponse
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

    /**
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendEventNotifyAll(Request $request)
    {
        $selectedExtensionIds = $request->get('extensionIds') ?? [];
        $selectedScope = $request->get('scope') ?? 'local';
        if ($selectedScope == 'global') {
            $registrations = get_registrations('all');
        } else {
            $registrations = get_registrations();
        }
        $all_regs = [];
        if (!empty($selectedExtensionIds)) {
            foreach ($selectedExtensionIds as $extensionId) {
                $extension = Extensions::find($extensionId);
                if ($extension) {
                    foreach ($registrations as $registration) {
                        if ($registration['sip-auth-user'] == $extension['extension']) {
                            array_push($all_regs, $registration);
                        }
                    }
                }
            }
        } else {
            $all_regs = $registrations;
        }

        // logger($all_regs);

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
            } else {
                /**
                 * Sometimes it throws an exception
                 * "message": "Undefined variable $agent",
                 * "exception": "ErrorException",
                 * "file": "/var/www/freeswitchpbx/app/Http/Controllers/ExtensionsController.php",
                 *
                 * So this line prevents it
                 */
                $agent = "";
            }

            if (!empty($agent)) {
                $command = "fs_cli -x 'luarun app.lua event_notify " . $reg['sip_profile_name'] . " reboot " . $reg['user'] . " " . $agent . "'";
                // Queue a job to restart the phone
                logger($command);
                SendEventNotify::dispatch($command)->onQueue('default');
            }
        }

        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'Successfully submitted bulk restart request'
            ]
        ]);
    }

    /**
     * Helper function to build destination action based on exit action.
     */
    protected function buildForwardDestinationTarget(array $inputs, string $prefix)
    {
        $actionKey = "{$prefix}_action";
        $targetKey = "{$prefix}_target";
        $externalKey = "{$prefix}_external_target";

        switch ($inputs[$actionKey] ?? null) {
            case 'extensions':
            case 'ring_groups':
            case 'ivrs':
            case 'business_hours':
            case 'time_conditions':
            case 'contact_centers':
            case 'faxes':
            case 'call_flows':
                return $inputs[$targetKey] ?? null;

            case 'voicemails':
                return isset($inputs[$targetKey]) ? ('*99' . $inputs[$targetKey]) : null;

            case 'external':
                return $inputs[$externalKey] ?? null;

            default:
                return null;
        }
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
            'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
            'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
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
        if ($deviceLine->device->device_label == $extension->extension) {
            $deviceLine->device->device_label = "";
            $deviceLine->device->save();
        }

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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreDeviceRequest  $request
     * @return JsonResponse
     */
    public function oldStoreDevice(OldStoreDeviceRequest $request, Extensions $extension): JsonResponse
    {
        $inputs = $request->validated();

        if ($inputs['extension_uuid']) {
            $extension = Extensions::find($inputs['extension_uuid']);
        } else {
            $extension = null;
        }

        $device = new Devices();
        $device->fill([
            'device_address' => tokenizeMacAddress($inputs['device_address']),
            'device_label' => $extension->extension ?? null,
            'device_vendor' => explode("/", $inputs['device_template'])[0],
            'device_enabled' => 'true',
            'device_enabled_date' => date('Y-m-d H:i:s'),
            'device_template' => $inputs['device_template'],
            'device_profile_uuid' => $inputs['device_profile_uuid'],
            'device_description' => '',
        ]);
        $device->save();

        if ($extension) {
            // Create device lines
            $device->lines = new DeviceLines();
            $device->lines->fill([
                'device_uuid' => $device->device_uuid,
                'line_number' => '1',
                'server_address' => Session::get('domain_name'),
                'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
                'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
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
            $device->lines->save();
        }


        return response()->json([
            'status' => 'success',
            'device' => $device,
            'message' => 'Device has been created and assigned.'
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Request  $request
     * @param  Devices  $device
     * @return JsonResponse
     */
    public function oldEditDevice(Request $request, Extensions $extension, Devices $device): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json([
                'message' => 'XHR request expected'
            ], 405);
        }

        if ($device->extension()) {
            $device->extension_uuid = $device->extension()->extension_uuid;
        }

        $device->device_address = formatMacAddress($device->device_address);
        $device->update_path = route('devices.update', $device);
        $device->options = [
            'templates' => getVendorTemplateCollection(),
            'profiles' => getProfileCollection($device->domain_uuid),
            'extensions' => getExtensionCollection($device->domain_uuid)
        ];

        return response()->json([
            'status' => 'success',
            'device' => $device
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateDeviceRequest  $request
     * @param  Devices  $device
     * @return JsonResponse
     */
    public function oldUpdateDevice(OldUpdateDeviceRequest $request, Extensions $extension, Devices $device): JsonResponse
    {
        $inputs = $request->validated();
        $inputs['device_vendor'] = explode("/", $inputs['device_template'])[0];
        $device->update($inputs);

        if ($request['extension_uuid']) {
            $extension = Extensions::find($request['extension_uuid']);
            if (($device->extension() && $device->extension()->extension_uuid != $request['extension_uuid']) or !$device->extension()) {
                $deviceLinesExist = DeviceLines::query()->where(['device_uuid' => $device->device_uuid])->first();
                if ($deviceLinesExist) {
                    $deviceLinesExist->delete();
                }

                // Create device lines
                $deviceLines = new DeviceLines();
                $deviceLines->fill([
                    'device_uuid' => $device->device_uuid,
                    'line_number' => '1',
                    'server_address' => Session::get('domain_name'),
                    'outbound_proxy_primary' => get_domain_setting('outbound_proxy_primary'),
                    'outbound_proxy_secondary' => get_domain_setting('outbound_proxy_secondary'),
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
                    'domain_uuid' => $device->domain_uuid
                ]);
                $deviceLines->save();
                $device->device_label = $extension->extension;
                $device->save();
            }
        }

        return response()->json([
            'status' => 'success',
            'device' => $device,
            'message' => 'Device has been updated.'
        ]);
    }

    public function getUserPermissions()
    {
        $permissions = [];
        // $permissions['user_group_view'] = userCheckPermission('user_group_view');
        // $permissions['user_group_edit'] = userCheckPermission('user_group_edit');
        // $permissions['user_status'] = userCheckPermission('user_status');
        // $permissions['user_view_managed_accounts'] = userCheckPermission('user_view_managed_accounts');
        // $permissions['user_update_managed_accounts'] = userCheckPermission('user_update_managed_accounts');
        // $permissions['user_view_managed_account_groups'] = userCheckPermission('user_view_managed_account_groups');
        // $permissions['user_update_managed_account_groups'] = userCheckPermission('user_update_managed_account_groups');

        $permissions['manage_voicemail_copies'] = userCheckPermission('voicemail_forward');
        $permissions['manage_voicemail_transcription'] = userCheckPermission('voicemail_transcription_enabled');
        $permissions['manage_voicemail_auto_delete'] = userCheckPermission('voicemail_local_after_email');
        $permissions['manage_voicemail_recording_instructions'] = userCheckPermission('voicemail_recording_instructions');

        $permissions['extension_device_create'] = userCheckPermission('extension_device_create');
        $permissions['extension_device_update'] = userCheckPermission('extension_device_update');
        $permissions['extension_device_assign'] = userCheckPermission('extension_device_assign');
        $permissions['extension_device_unassign'] = userCheckPermission('extension_device_unassign');

        return $permissions;
    }
}
