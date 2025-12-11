<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\User;
use Inertia\Inertia;
use App\Models\Groups;
use App\Models\Devices;
use App\Data\DeviceData;
use App\Models\FollowMe;
use App\Models\UserGroup;
use App\Models\Extensions;
use App\Models\RingGroups;
use App\Models\Voicemails;
use App\Data\MobileAppData;
use App\Data\VoicemailData;
use App\Jobs\DeleteAppUser;
use App\Models\FusionCache;
use Illuminate\Support\Str;
use App\Jobs\SuspendAppUser;
use App\Models\Destinations;
use Illuminate\Http\Request;
use App\Jobs\SendEventNotify;
use App\Models\MobileAppUsers;
use App\Data\ExtensionListData;
use App\Jobs\UpdateAppSettings;
use App\Data\ExtensionDetailData;
use App\Imports\ExtensionsImport;
use Illuminate\Support\Facades\DB;
use App\Exports\ExtensionsTemplate;
use Nwidart\Modules\Facades\Module;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use App\Data\FollowMeDestinationData;
use Illuminate\Support\Facades\Route;
use libphonenumber\PhoneNumberFormat;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\HeadingRowImport;
use App\Services\CallRoutingOptionsService;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Propaganistas\LaravelPhone\PhoneNumber;
use App\Http\Requests\StoreExtensionRequest;
use App\Http\Requests\UpdateExtensionRequest;
use extension;
use Spatie\Activitylog\Facades\CauserResolver;
use Propaganistas\LaravelPhone\Exceptions\NumberParseException;
use App\Traits\ChecksLimits;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Exports\ExtensionsExport;

class ExtensionsController extends Controller
{
    use ChecksLimits;

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Extensions';

public function export(Request $request)
{
    if (!userCheckPermission("extension_export")) {
        abort(403);
    }
    return \Maatwebsite\Excel\Facades\Excel::download(
        new ExtensionsExport,
        'extensions.csv',
        \Maatwebsite\Excel\Excel::CSV
    );
}

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
            ->with(['mobile_app' => function ($query) {
                $query->select('mobile_app_user_uuid', 'extension_uuid', 'status');
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
                'user_record',
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
                    'download_template' => route('extensions.template.download'),
                    'import' => route('extensions.import'),
                    'create_user' => route('extensions.make.user'),
                    'create_contact_center_user' => (Module::has('ContactCenter') && Module::collections()->has('ContactCenter') && Route::has('contact-center.user.store')) ? route('contact-center.user.store') : null,
                    'export' => route('extensions.export'),
                ]
            ]
        );
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
                'errors' => ['error' => [$e->getMessage()]],
            ], 500);
        }
    }

    public function getItemOptions(Request $request)
    {
        $itemUuid = $request->input('item_uuid');

        //Check for limits
                if (!$itemUuid) {
            if ($resp = $this->enforceLimit(
                'extensions',
                \App\Models\Extensions::class
            )) {
                return $resp;
            }
        }


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
                    'outbound_caller_id_name',
                    'emergency_caller_id_name',
                    'directory_first_name',
                    'directory_last_name',
                    'directory_visible',
                    'directory_exten_visible',
                    'enabled',
                    'do_not_disturb',
                    'description',
                    'call_timeout',
                    'do_not_disturb',
                    'call_screen_enabled',
                    'max_registrations',
                    'limit_max',
                    'limit_destination',
                    'toll_allow',
                    'call_group',
                    'hold_music',
                    'cidr',
                    'auth_acl',
                    'sip_force_contact',
                    'sip_force_expires',
                    'sip_bypass_media',
                    'mwi_account',
                    'absolute_codec_string',
                    'dial_string',
                    'force_ping',
                    'user_context',
                    'accountcode',
                    'user_record',
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
                                'voicemail_sms_to',
                                'voicemail_transcription_enabled',
                                'voicemail_file',
                                'voicemail_local_after_email',
                                'voicemail_enabled',
                                'voicemail_description',
                                'voicemail_tutorial',
                                'voicemail_recording_instructions',
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
                    'mobile_app' => function ($q) {
                        $q->select([
                            'mobile_app_user_uuid',
                            'extension_uuid',
                            'org_id',
                            'conn_id',
                            'user_id',
                            'status',
                            'exclude_from_stale_report',
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

                'mobile_app' => $extension->mobile_app ? MobileAppData::from($extension->mobile_app->toArray()) : null,

            ]);
            $updateRoute = route('extensions.update', ['extension' => $itemUuid]);
            $deviceRoute = route('extensions.devices', ['extension' => $itemUuid]);
            $routes['sip_credentials'] = route('extensions.sip.credentials', $extension);
            $routes['regenerate_sip_credentials'] = route('extensions.sip.credentials.regenerate', $extension);
            $routes['mobile_app_options'] = route('apps.user.options', $extension);
            $routes['create_mobile_app'] = route('apps.user.create');
            $routes['delete_mobile_app'] = route('apps.user.delete');
            $routes['reset_mobile_app'] = route('apps.user.reset');
            $routes['activate_mobile_app'] = route('apps.user.activate');
            $routes['deactivate_mobile_app'] = route('apps.user.deactivate');
            $routes['device_item_options'] = route('devices.item.options');

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

            if ($extension->voicemail) {
                $voicemailDto = VoicemailData::from([
                    ...$extension->voicemail->toArray(),
                    'voicemail_destinations' => $voicemailDestinations,
                    'greetings' => $voicemailGreetings,
                ]);
            } else {
                $voicemailDto = VoicemailData::from([
                    'voicemail_enabled' => 'false',
                    'voicemail_id' => $extension->extension,
                    'voicemail_password' => get_domain_setting('password_complexity') == 'true' ? $attributes['voicemail_password'] = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT) : $extension->extension,
                    'voicemail_transcription_enabled' => 'true',
                    'voicemail_file' => 'attach',
                    'voicemail_local_after_email' => 'true',
                    'greeting_id' => '-1',
                    'voicemail_transcription_enabled' => 'true',
                    'voicemail_recording_instructions' => 'true',
                    'voicemail_tutorial' => 'false',
                    'greetings' => $voicemailGreetings,
                ]);
            }

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
                    // 'update_route' => route('voicemails.update', $extension->voicemail),
                ]);
            }

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

            $allDevices =  QueryBuilder::for(Devices::class)
                ->where('domain_uuid', $currentDomain)
                ->with(['lines' => function ($query) use ($currentDomain) {
                    $query->select('device_line_uuid', 'device_uuid', 'auth_id', 'domain_uuid')
                        ->with([
                            'extension' => function ($q) use ($currentDomain) {
                                $q->select('extension_uuid', 'extension', 'effective_caller_id_name')
                                    ->where('domain_uuid', $currentDomain);
                            },

                        ]);
                }])
                ->select(
                    'device_uuid',
                    'device_address',
                )
                ->orderBy('device_address', 'asc')
                ->get()
                ->map(function ($device) {
                    // Get all extensions as name_formatted
                    $extensions = collect($device->lines)
                        ->pluck('extension.name_formatted')
                        ->filter() // Remove nulls
                        ->all();

                    // Build the label
                    $label = $device->device_address_formatted;
                    if (count($extensions)) {
                        $label .= ' (' . implode(', ', $extensions) . ')';
                    }

                    return [
                        'value' => $device->device_address,
                        'label' => $label,
                    ];
                })
                ->values()
                ->toArray();


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

            $routingOptionsService = new CallRoutingOptionsService;
            $forwardingTypes = $routingOptionsService->forwardingTypes;

            $music_on_hold_options = getMusicOnHoldCollection(session('domain_uuid'));



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

            $recordedName = 'System Default';
            if ($voicemailDto && $voicemailDto->voicemail_id) {
                $filePath = session('domain_name') . '/' . $voicemailDto->voicemail_id . '/recorded_name.wav';
                if (Storage::disk('voicemail')->exists($filePath)) {
                    $recordedName = 'Custom recording';
                }
            }
        } else {

            // New extension defaults
            $extensionDto = ExtensionDetailData::from([
                'extension_uuid' => '',
                'extension' => '',
                'email' => '',
                'directory_first_name' => '',
                'directory_last_name' => '',
            ]);
            $updateRoute = null;
        }

        // 2) Permissions array
        $permissions = $this->getUserPermissions();

        // 3) Any routes your front end needs
        $routes = array_merge($routes, [
            'store_route'  => route('extensions.store'),
            'update_route' => $updateRoute ?? null,
            'devices' => $deviceRoute ?? null,
            'get_routing_options' => route('routing.options'),
            'device_bulk_unassign' => route('devices.bulk.unassign'),
            'update_password_route' => route('extensions.password.update'),
        ]);



        return response()->json([
            'item'        => $extensionDto,
            'voicemail' => $voicemailDto ?? null,
            'all_voicemails' => $allVoicemails ?? null,
            'all_devices' => $allDevices ?? null,
            'permissions' => $permissions,
            'routes'      => $routes,
            'phone_numbers' => $phone_numbers ?? null,
            'forwarding_types' => $forwardingTypes ?? null,
            'follow_me_destination_options' => $followMeDestinationOptions ?? null,
            'voices' => isset($openAiService) && $openAiService ? $openAiService->getVoices() : null,
            'default_voice' => isset($openAiService) && $openAiService ? $openAiService->getDefaultVoice() : null,
            'speeds' => isset($openAiService) && $openAiService ? $openAiService->getSpeeds() : null,
            'phone_call_instructions' => $phoneCallInstructions ?? null,
            'phone_call_instructions_for_name' => $phoneCallInstructionsForName ?? null,
            'sample_message' => $sampleMessage ?? null,
            'recorded_name' => $recordedName ?? null,
            'music_on_hold_options' => $music_on_hold_options ?? null,
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


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreExtensionRequest $request)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            // 1) Create the extension
            $extension = Extensions::create($data);

            // 2) Create the voicemail entry for this extension
            if (!empty($data['voicemail_id'])) {
                $voicemail = Voicemails::create($data);
            }

            DB::commit();

            // Clear FusionPBX cache for the extension
            if (isset($extension->extension)) {
                FusionCache::clear("directory:" . $extension->extension . "@" . $extension->user_context);
            }

            // Clear the destinations session array if present
            if (isset($_SESSION['destinations']['array'])) {
                unset($_SESSION['destinations']['array']);
            }

            return response()->json([
                'extension_uuid'    => $extension->extension_uuid,
                'messages' => ['success' => ['Extension has been created']],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('Extension create error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'status' => 'error',
                'messages' => ['error' => ['Something went wrong while creating the extension.']]
            ], 500);
        }
    }

    /**
     * Display SIP Credentials for specified resource.
     *
     * @param \App\Models\Extentions $extention
     * @return \Illuminate\Http\Response
     */
    public function sipCredentials($extension_uuid)
    {
        try {
            $extension = QueryBuilder::for(Extensions::class)
                ->select([
                    'extension_uuid',
                    'extension',
                    'password',
                    'user_context',
                ])
                ->whereKey($extension_uuid)
                ->firstOrFail();


            return response()->json([
                'success'  => true,
                'data'  => [
                    'extension' => $extension->extension,
                    'password' => $extension->password,
                    'context' => $extension->user_context,
                ],
            ]);
        } catch (\Throwable $e) {
            logger('ExtensionsController@sipCredentials error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success'  => false,
                'messages' => ['error' => [$e->getMessage()]],
                'data'     => [],
            ], 500);
        }
    }

    /**
     * Create new SIP Credentials for specified resource.
     *
     * @param \App\Models\Extentions $extention
     * @return \Illuminate\Http\Response
     */
    public function regenerateSipCredentials($extension_uuid)
    {
        try {
            DB::beginTransaction();
            $currentDomain = session('domain_uuid');

            $extension = Extensions::with([
                'deviceLines' => function ($q) use ($currentDomain) {
                    $q->where('domain_uuid', $currentDomain)
                        ->select('device_line_uuid', 'device_uuid', 'auth_id', 'domain_uuid', 'password');
                }
            ])->whereKey($extension_uuid)->firstOrFail();

            // Generate new password
            $newPassword = generate_password();

            // Update the extension password
            $extension->password = $newPassword;
            $extension->save();

            // Update all related device line passwords for this domain
            foreach ($extension->deviceLines as $deviceLine) {
                $deviceLine->password = $newPassword; // Use correct field name!
                $deviceLine->save();
            }

            DB::commit();

            // Return new credentials
            return response()->json([
                'success'  => true,
                'data'  => [
                    'extension' => $extension->extension,
                    'password'  => $extension->password,
                    'context'   => $extension->user_context,
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollback();
            logger('ExtensionsController@regenerateSipCredentials error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success'  => false,
                'errors' => ['error' => [$e->getMessage()]],
                'data'     => [],
            ], 500);
        }
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
                                $query->select('device_uuid', 'device_profile_uuid', 'device_address', 'device_template', 'device_template_uuid')
                                    ->with(['profile' => function ($profileQuery) {
                                        $profileQuery->select('device_profile_uuid', 'device_profile_name'); // Add fields as needed
                                    }])
                                    ->with(['template' => function ($query) {
                                        $query->select('template_uuid', 'domain_uuid', 'vendor','name');
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
                ->sortBy('device_address')
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


    public function update(UpdateExtensionRequest $request, $id)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            // logger($data);

            $currentDomain = session('domain_uuid');

            $extension = Extensions::with(['advSettings', 'followMe.followMeDestinations',])
                ->with(['voicemail' => function ($query) use ($currentDomain) {
                    $query->where('domain_uuid', $currentDomain);
                }])
                ->with([
                    'mobile_app' => function ($q) {
                        $q->select([
                            'mobile_app_user_uuid',
                            'extension_uuid',
                            'org_id',
                            'conn_id',
                            'user_id',
                            'status',
                            'exclude_from_stale_report',
                        ]);
                    },
                ])
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
                    (!empty($data[$enabledKey]) && $data[$enabledKey] == 'true')
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
            if ($extension->voicemail) {
                $extension->voicemail->update($data);
            } else {
                // If enabling voicemail and no voicemail exists, create one
                if ($data['voicemail_enabled'] == 'true') {
                    $data['extension_uuid'] = $extension->extension_uuid;
                    $data['domain_uuid'] = $currentDomain;
                    $voicemail = Voicemails::create($data);
                    // logger($voicemail);
                }
            }

            $extension->advSettings()->updateOrCreate(
                ['extension_uuid' => $extension->extension_uuid],
                $data
            );

            // Handle voicemail_destinations (copies)
            if (
                isset($data['voicemail_destinations'])
                && is_array($data['voicemail_destinations'])
                && $extension->voicemail
            ) {
                $extension->voicemail->syncCopies($data['voicemail_destinations']);
            }

            // update mobile app
            if ($extension->mobile_app) {
                // Update only actual DB columns
                if (isset($data['exclude_from_ringotel_stale_users']) && Schema::hasColumn('mobile_app_users', 'exclude_from_stale_report')) {
                    $extension->mobile_app->exclude_from_stale_report = $data['exclude_from_ringotel_stale_users'];
                    if ($extension->mobile_app->isDirty()) {
                        $extension->mobile_app->save();
                    }
                }

                // Prepare payload for API/job 
                $mobileAppPayload = [
                    'user_id'   => $extension->mobile_app->user_id,
                    'org_id'    => $extension->mobile_app->org_id,
                    'conn_id'   => $extension->mobile_app->conn_id,
                    'status'    => $extension->mobile_app->status,
                    'no_email'  => $extension->mobile_app->no_email ?? true,
                    'name'      => $data['effective_caller_id_name'] ?? '',
                    'email'     => $data['voicemail_mail_to'] ?? "",
                    'ext'       => $data['extension'],
                    'password'  => $extension->password,
                ];

                // Dispatch job
                UpdateAppSettings::dispatch($mobileAppPayload)->onQueue('default');

                if ($data['suspended'] && $extension->mobile_app->status != -1) {
                    // logger('suspended');
                    SuspendAppUser::dispatch($mobileAppPayload)->onQueue('default');
                }
            }

            DB::commit();

            //clear fusionpbx cache
            FusionCache::clear("directory:" . $extension->extension . "@" . $extension->user_context);

            //clear the destinations session array
            if (isset($_SESSION['destinations']['array'])) {
                unset($_SESSION['destinations']['array']);
            }



            // logger($extension->toArray());
            return response()->json([
                'messages' => ['success' => ['Extension updated successfully']],
                'extension' => $extension->fresh(['voicemail', 'advSettings']),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('ExtensionsController@update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'messages' => ['error' => ['An error occurred while updating the extension.', $e->getMessage()]],
            ], 500);
        }
    }

    function saveOrUpdateFollowMe($extension, $data)
    {
        // If follow_me is disabled, wipe everything
        // if (isset($data['follow_me_enabled']) && $data['follow_me_enabled'] === 'false') {
        //     if ($extension->followMe) {
        //         $extension->followMe->followMeDestinations()->delete();
        //         $extension->followMe->delete();
        //     }
        //     return null;
        // }

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

        //Return early if follow me is disabled
        if (isset($data['follow_me_enabled']) && $data['follow_me_enabled'] === 'false') {
            return $followMeUuid;
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
                'follow_me_prompt' => $dest['prompt'],
                'follow_me_order' => $idx + 1,
            ]);
        }

        return $followMe->follow_me_uuid;
    }


    /**
     * Import the specified resource
     *
     * @return \Illuminate\Http\Response
     */
    public function import()
    {
        if (! userCheckPermission('extension_import')) {
        abort(403);
    }
        try {

            $file = request()->file('file');
            $domain_uuid = session('domain_uuid');

            // 1. Count how many rows will be imported
            $rows = Excel::toCollection(new ExtensionsImport, $file)->first(); // Get first sheet
            $importCount = $rows->count();

            // 2. Check current count and limit
            $currentCount = \App\Models\Extensions::where('domain_uuid', $domain_uuid)->count();
            $maxLimit = get_limit_setting('extensions', $domain_uuid);

            if ($maxLimit !== null && ($currentCount + $importCount) > $maxLimit) {
                return response()->json([
                    'success' => false,
                    'errors' => [
                        'extension' => [
                            "Importing this file would exceed your extension limit of $maxLimit. " .
                                "You currently have $currentCount extensions and are trying to import $importCount."
                        ]
                    ]
                ], 422);
            }

            $headings = (new HeadingRowImport)->toArray(request()->file('file'));

            $import = new ExtensionsImport;
            $import->import($file);

            if ($import->failures()->isNotEmpty()) {

                // Transform each failure into a readable error message
                $errors = [];
                foreach ($import->failures() as $failure) {
                    $row = $failure->row(); // Row number
                    $attr = $failure->attribute(); // Column/field name
                    $errList = $failure->errors(); // Array of error messages

                    foreach ($errList as $errMsg) {
                        $errors[] = "Row {$row}, '{$attr}': {$errMsg}";
                    }
                }

                return response()->json([
                    'success' => false,
                    'errors' => ['server' => $errors]
                ], 500);
            }

            return response()->json([
                'success' => true,
                'messages' => ['success' => ['Extensions have been successfully uploaded.']]
            ], 200);
        } catch (Throwable $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Send response in format that Dropzone understands
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }


    public function bulkDelete(Request $request)
    {
        if (! userCheckPermission('extension_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        $domain_uuid = session('domain_uuid');
        $uuids = $request->input('items', []);

        try {
            DB::beginTransaction();

            // Eager load all relationships to avoid N+1 issues
            $extensions = Extensions::with([
                'followMe.followMeDestinations',
                'extension_users',
                'mobile_app',
                'advSettings',
            ])
                ->with([
                    'deviceLines' => function ($q) use ($domain_uuid) {
                        $q->where('domain_uuid', $domain_uuid)
                            ->select('device_line_uuid', 'device_uuid', 'auth_id', 'domain_uuid', 'password');
                    }
                ])
                ->with(['voicemail' => function ($query) use ($domain_uuid) {
                    $query->where('domain_uuid', $domain_uuid);
                }])
                ->where('domain_uuid', $domain_uuid)
                ->whereIn('extension_uuid', $uuids)
                ->get();



            foreach ($extensions as $extension) {
                // 1. Delete voicemail
                if ($extension->voicemail) {
                    $extension->voicemail->delete();
                }

                // 2. Delete followMe and destinations
                if ($extension->followMe) {
                    $extension->followMe->followMeDestinations()->delete();
                    $extension->followMe->delete();
                }

                // 3. Delete extension users
                if ($extension->extension_users) {
                    $extension->extension_users()->delete();
                }

                // 4. Unassign device lines (deviceLines) only from this domain
                $extension->deviceLines()
                    ->where('domain_uuid', $domain_uuid)
                    ->delete();

                // 5. Mobile app users: dispatch job, then delete
                if ($extension->mobile_app) {
                    // Prepare payload for API/job (this data is NOT saved to DB, just sent to the API)
                    $mobileAppPayload = [
                        'mobile_app_user_uuid' => $extension->mobile_app_user_uuid,
                        'user_id'   => $extension->mobile_app->user_id,
                        'org_id'    => $extension->mobile_app->org_id,
                    ];

                    // Dispatch job
                    DeleteAppUser::dispatch($mobileAppPayload)->onQueue('default');
                }

                // 6. delete advanced settings (advSettings)
                if ($extension->advSettings) {
                    $extension->advSettings()->delete();
                }

                // 7. Delete the extension itself
                $extension->delete();

                // 7. Clear FusionPBX cache for this extension
                if ($extension->extension && $extension->user_context) {
                    FusionCache::clear("directory:" . $extension->extension . "@" . $extension->user_context);
                }
            }

            // 8. Clear the destinations session array if present
            if (isset($_SESSION['destinations']['array'])) {
                unset($_SESSION['destinations']['array']);
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected extension(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('Extension bulkDelete error: '
                . $e->getMessage()
                . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected extension(s).']]
            ], 500);
        }
    }

    public function makeUser()
    {
        $group_name = request('role');

        try {
            DB::beginTransaction();

            $currentDomain = session('domain_uuid');

            $extension = QueryBuilder::for(Extensions::class)
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
                    'directory_first_name',
                    'directory_last_name',
                ])
                ->whereKey(request('extension_uuid'))
                ->firstOrFail();


            // Check if user exists
            if (User::where('user_email', $extension->email)->exists()) {
                throw new \Exception('A user with this email already exists.');
            }

            // Create a new user
            $user = new User();

            $user->password      = Hash::make(Str::random(25));
            $user->domain_uuid   = $currentDomain;
            $user->add_user      = Auth::user()->username;
            $user->insert_date   = now();
            $user->insert_user   = session('user_uuid');
            $user->username      = trim($user->first_name . (!empty($user->last_name) ? '_' . $user->last_name : ''));
            $user->user_email    = $extension->email ?? '';
            $user->user_enabled  = 'true';

            $user->save();

            $user->user_adv_fields()->create([
                'user_uuid'   => $user->user_uuid,
                'first_name'  => $extension->directory_first_name,
                'last_name'   => $extension->directory_last_name,
            ]);

            $user->settings()->createMany([
                [
                    'user_uuid'                => $user->user_uuid,
                    'domain_uuid'              => $user->domain_uuid,
                    'user_setting_category'    => 'domain',
                    'user_setting_subcategory' => 'language',
                    'user_setting_name'        => 'code',
                    'user_setting_value'       => get_domain_setting('language'),
                    'user_setting_enabled'     => true,
                    'insert_date'              => now(),
                    'insert_user'              => session('user_uuid'),
                ],
                [
                    'user_uuid'                => $user->user_uuid,
                    'domain_uuid'              => $user->domain_uuid,
                    'user_setting_category'    => 'domain',
                    'user_setting_subcategory' => 'time_zone',
                    'user_setting_name'        => 'name',
                    'user_setting_value'       => get_local_time_zone($currentDomain),
                    'user_setting_enabled'     => true,
                    'insert_date'              => now(),
                    'insert_user'              => session('user_uuid'),
                ]
            ]);

            $group = Groups::where('group_name', $group_name)->first();

            if ($group) {
                UserGroup::firstOrCreate(
                    [
                        'group_uuid' => $group->group_uuid,
                        'user_uuid'  => $user->user_uuid,
                    ],
                    [
                        'domain_uuid' => $currentDomain,
                        'group_name'  => $group_name,
                        'insert_date' => now(),
                        'insert_user' => session('user_uuid'),
                    ]
                );
            }



            DB::commit();

            return response()->json([
                'messages' => ['success' => [ucfirst($group_name) . ' created successfully']],
                'agent' => $agent ?? null,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('UserController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['error' => [$e->getMessage()]],
            ], 500);
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

    public function downloadTemplate()
    {
        // Download as CSV (third parameter sets the writer type)
        return Excel::download(new ExtensionsTemplate, 'extensions_template.csv', ExcelWriter::CSV);
    }

    public function updatePassword()
    {
        if (! userCheckPermission('extension_password')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        try {
            DB::beginTransaction();

            $extension = Extensions::find(request('extension_uuid'));
            $extension->update(request()->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'messages' => ['success' => ['Password updated successfully.']],
                'extension_uuid' => $extension->extension_uuid,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('UserController@updatePassword error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['error' => [$e->getMessage()]],
            ], 500);
        }
    }


    public function getUserPermissions()
    {
        $permissions = [];

        $permissions['extension_enabled'] = userCheckPermission('extension_enabled');
        $permissions['extension_extension'] = userCheckPermission('extension_extension');
        $permissions['extension_password'] = userCheckPermission('extension_password');
        $permissions['extension_suspended'] = userCheckPermission('extension_suspended');
        $permissions['extension_do_not_disturb'] = userCheckPermission('extension_do_not_disturb');
        $permissions['extension_user_record'] = userCheckPermission('extension_user_record');

        $permissions['extension_advanced'] = userCheckPermission('extension_advanced');
        $permissions['extension_absolute_codec_string'] = userCheckPermission('extension_absolute_codec_string');
        $permissions['extension_accountcode'] = userCheckPermission('extension_accountcode');
        $permissions['extension_call_group'] = userCheckPermission('extension_call_group');
        $permissions['extension_call_screen'] = userCheckPermission('extension_call_screen');
        $permissions['extension_cidr'] = userCheckPermission('extension_cidr');
        $permissions['extension_dial_string'] = userCheckPermission('extension_dial_string');
        $permissions['extension_directory'] = userCheckPermission('extension_directory');
        $permissions['extension_force_ping'] = userCheckPermission('extension_force_ping');
        $permissions['extension_hold_music'] = userCheckPermission('extension_hold_music');
        $permissions['extension_limit'] = userCheckPermission('extension_limit');
        $permissions['extension_max_registrations'] = userCheckPermission('extension_max_registrations');
        $permissions['extension_toll'] = userCheckPermission('extension_toll');

        $permissions['manage_external_caller_id_number'] = userCheckPermission('outbound_caller_id_number');
        $permissions['manage_external_caller_id_name'] = userCheckPermission('outbound_caller_id_name');
        $permissions['manage_emergency_caller_id_number'] = userCheckPermission('emergency_caller_id_number');
        $permissions['manage_emergency_caller_id_name'] = userCheckPermission('emergency_caller_id_name');
        $permissions['extension_forward_all'] = userCheckPermission('extension_forward_all');
        $permissions['extension_forward_busy'] = userCheckPermission('extension_forward_busy');
        $permissions['extension_forward_no_answer']  = userCheckPermission('extension_forward_no_answer');
        $permissions['extension_forward_not_registered'] = userCheckPermission('extension_forward_not_registered');
        $permissions['extension_call_sequence'] = userCheckPermission('extension_call_sequence');
        $permissions['manage_forwarding'] = $permissions['extension_forward_all'] ||  $permissions['extension_forward_busy']  ||  $permissions['extension_forward_busy'] || $permissions['extension_forward_no_answer'] || $permissions['extension_forward_not_registered'] || $permissions['extension_call_sequence'];

        $permissions['manage_voicemail_copies'] = userCheckPermission('voicemail_forward');
        $permissions['manage_voicemail_transcription'] = userCheckPermission('voicemail_transcription_enabled');
        $permissions['manage_voicemail_auto_delete'] = userCheckPermission('voicemail_local_after_email');
        $permissions['manage_voicemail_recording_instructions'] = userCheckPermission('voicemail_recording_instructions');
        $permissions['manage_voicemail_mobile_notifications'] = userCheckPermission('voicemail_sms_edit');

        $permissions['extension_device_create'] = userCheckPermission('extension_device_create');
        $permissions['extension_device_update'] = userCheckPermission('extension_device_update');
        $permissions['extension_device_assign'] = userCheckPermission('extension_device_assign');
        $permissions['extension_device_unassign'] = userCheckPermission('extension_device_unassign');

        $permissions['manage_mobile_app'] = userCheckPermission('extension_mobile_app_settings');

        $permissions['is_superadmin'] = isSuperAdmin();

        return $permissions;
    }
}
