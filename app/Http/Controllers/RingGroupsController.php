<?php

namespace App\Http\Controllers;

use App\Models\IvrMenus;
use App\Models\Dialplans;
use App\Models\Extensions;
use App\Models\Recordings;
use App\Models\RingGroups;
use App\Models\Voicemails;
use App\Models\FusionCache;
use App\Models\MusicOnHold;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\FreeswitchSettings;
use App\Models\FollowMeDestinations;
use App\Models\RingGroupsDestinations;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Propaganistas\LaravelPhone\PhoneNumber;
use App\Http\Requests\StoreRingGroupRequest;
use App\Http\Requests\UpdateRingGroupRequest;
use App\Http\Requests\GetDestinationByCategoryRequest;

class RingGroupsController extends Controller
{
    /**
     * @param  Request  $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function index(Request $request)
    {
        //print '<pre>';
        //print_r(Session::get('permissions', false));
        //print '</pre>';
        // Check permissions
        if (!userCheckPermission("ring_group_all")) {
            return redirect('/');
        }

        //$timeZone = get_local_time_zone(Session::get('domain_uuid'));
        $ringGroups = RingGroups::query();
        $ringGroups
            ->where('domain_uuid', Session::get('domain_uuid'));
        $ringGroups = $ringGroups->orderBy('insert_date',)->paginate(10)->onEachSide(1);

        $permissions['delete'] = userCheckPermission('ring_group_delete');
        $permissions['view'] = userCheckPermission('ring_group_view');
        $permissions['edit'] = userCheckPermission('ring_group_edit');
        $permissions['add'] = userCheckPermission('ring_group_add');
        $data = [];
        $data['ringGroups'] = $ringGroups;

        return view('layouts.ringgroups.list')
            ->with($data)
            ->with('permissions', $permissions);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //check permissions
        if (!userCheckPermission('ring_group_add') || !userCheckPermission('ring_group_edit')) {
            return redirect('/');
        }

        $ringGroup = new RingGroups();

        $ringGroupRingMyPhoneTimeout = 0;
        $ringGroupDestinations = $ringGroup->getGroupDestinations();
        if ($ringGroupDestinations->count() > 0) {
            if ($ringGroupDestinations[0]->ring_group_uuid == $ringGroup->ring_group_uuid) {
                $ringGroupDestinations = $ringGroupDestinations[0]->destination_timeout;
                unset($ringGroupDestinations[0]);
            }
        }

        $moh = MusicOnHold::where('domain_uuid', Session::get('domain_uuid'))
            ->orWhere('domain_uuid', null)
            ->orderBy('music_on_hold_name', 'ASC')
            ->get()
            ->unique('music_on_hold_name');

        $recordings = Recordings::where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('recording_name', 'ASC')
            ->get();

        $timeoutDestinationsByCategory = [];
        foreach ([
            'ringgroup',
            'dialplans',
            'extensions',
            'timeconditions',
            'voicemails',
            'others'
        ] as $category) {
            $timeoutDestinationsByCategory[$category] = getDestinationByCategory($category)['list'];
        }

        return view('layouts.ringgroups.createOrUpdate')
            ->with('ringGroup', $ringGroup)
            ->with('moh', $moh)
            ->with('recordings', $recordings)
            ->with('extensions', $this->getDestinationExtensions())
            ->with('timeoutDestinationsByCategory', $timeoutDestinationsByCategory)
            ->with('destinationsByCategory', 'disabled')
            ->with('ringGroupRingMyPhoneTimeout', $ringGroupRingMyPhoneTimeout)
            ->with('ringGroupDestinations', $ringGroupDestinations);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreRingGroupRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreRingGroupRequest $request)
    {
        $attributes = $request->validated();

        if ($attributes['ring_group_forward']['all']['type'] == 'external') {
            $attributes['ring_group_forward_destination'] = PhoneNumber::make($attributes['ring_group_forward']['all']['target_external'], "US")->formatE164();
        } else {
            $attributes['ring_group_forward_destination'] = ($attributes['ring_group_forward']['all']['target_internal'] == '0') ? '' : $attributes['ring_group_forward']['all']['target_internal'];;
            if (empty($attributes['ring_group_forward_destination'])) {
                $attributes['ring_group_forward_enabled'] = 'false';
            }
        }

        $ringGroup = new RingGroups();
        $ringGroup->fill([
            'ring_group_name' => $attributes['ring_group_name'],
            'ring_group_extension' => $attributes['ring_group_extension'],
            'ring_group_greeting' => $attributes['ring_group_greeting'] ?? null,
            'ring_group_call_timeout' => $attributes['ring_group_call_timeout'],
            'ring_group_timeout_app' => ($attributes['timeout_category'] != 'disabled') ? 'transfer' : null,
            'ring_group_timeout_data' => $attributes['ring_group_timeout_data'],
            'ring_group_cid_name_prefix' => $attributes['ring_group_cid_name_prefix'] ?? null,
            'ring_group_cid_number_prefix' => $attributes['ring_group_cid_number_prefix'] ?? null,
            'ring_group_description' => $attributes['ring_group_description'],
            'ring_group_enabled' => $attributes['ring_group_enabled'],
            'ring_group_forward_enabled' => $attributes['ring_group_forward_enabled'],
            'ring_group_forward_destination' => $attributes['ring_group_forward_destination'],
            'ring_group_strategy' => $attributes['ring_group_strategy'],
            'ring_group_caller_id_name' => $attributes['ring_group_caller_id_name'] ?? null,
            'ring_group_caller_id_number' => $attributes['ring_group_caller_id_number'] ?? null,
            'ring_group_distinctive_ring' => $attributes['ring_group_distinctive_ring'],
            'ring_group_ringback' => $attributes['ring_group_ringback'],
            'ring_group_call_forward_enabled' => $attributes['ring_group_call_forward_enabled'],
            'ring_group_follow_me_enabled' => $attributes['ring_group_follow_me_enabled'],
            'ring_group_missed_call_data' => $attributes['ring_group_missed_call_data'],
            'ring_group_missed_call_app' => ($attributes['ring_group_missed_call_category'] == 'disabled') ? null : $attributes['ring_group_missed_call_category'],
            'ring_group_forward_toll_allow' => $attributes['ring_group_forward_toll_allow'],
            'ring_group_context' => $attributes['ring_group_context'] ?? null,
            'dialplan_uuid' => Str::uuid(),
        ]);

        $ringGroup->save();

        if (isset($attributes['ring_group_destinations']) && count($attributes['ring_group_destinations']) > 0) {
            $i = 0;
            foreach ($attributes['ring_group_destinations'] as $destination) {
                if ($i > 49) {
                    break;
                }
                $groupsDestinations = new RingGroupsDestinations();
                if ($destination['type'] == 'external') {
                    $groupsDestinations->destination_number = format_phone_or_extension($destination['target_external']);
                } else {
                    $groupsDestinations->destination_number = $destination['target_internal'];
                }
                $groupsDestinations->destination_delay = $destination['delay'];
                $groupsDestinations->destination_timeout = $destination['timeout'];
                if ($destination['prompt'] == 'true') {
                    $groupsDestinations->destination_prompt = 1;
                } else {
                    $groupsDestinations->destination_prompt = null;
                }
                //$groupsDestinations->follow_me_order = $i;
                $ringGroup->groupDestinations()->save($groupsDestinations);
                $i++;
            }
        }

        // Generate Dialplan XML file
        $xml = $this->generateDialPlanXML($ringGroup);

        return response()->json([
            'status' => 'success',
            'ring_group' => $ringGroup,
            'message' => 'RingGroup has been created.'
        ]);
    }

    public function generateDialPlanXML($ringGroup)
    {
        // Data to pass to the Blade template
        $data = [
            'ring_group' => $ringGroup,
        ];

        // Render the Blade template and get the XML content as a string
        $xml = view('layouts.xml.ring-group-dial-plan-template', $data)->render();

        $dialPlan = Dialplans::where('dialplan_uuid', $ringGroup->dialplan_uuid)->first();

        if (!$dialPlan) {
            $dialPlan = new Dialplans();
            $dialPlan->dialplan_uuid = $ringGroup->dialplan_uuid;
            $dialPlan->app_uuid = '1d61fb65-1eec-bc73-a6ee-a6203b4fe6f2';
            $dialPlan->domain_uuid = Session::get('domain_uuid');
            $dialPlan->dialplan_name = $ringGroup->ring_group_name;
            $dialPlan->dialplan_number = $ringGroup->ring_group_extension;
            if (isset($ringGroup->ring_group_context)) {
                $dialPlan->dialplan_context = $ringGroup->ring_group_context;
            }
            $dialPlan->dialplan_continue = 'false';
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_order = 101;
            $dialPlan->dialplan_enabled = $ringGroup->ring_group_enabled;
            $dialPlan->dialplan_description = $ringGroup->queue_description;
            $dialPlan->insert_date = date('Y-m-d H:i:s');
            $dialPlan->insert_user = Session::get('user_uuid');
        } else {
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_name = $ringGroup->ring_group_name;
            $dialPlan->dialplan_description = $ringGroup->queue_description;
            $dialPlan->update_date = date('Y-m-d H:i:s');
            $dialPlan->update_user = Session::get('user_uuid');
        }

        $dialPlan->save();

        $freeswitchSettings = FreeswitchSettings::first();
        $fp = event_socket_create(
            $freeswitchSettings['event_socket_ip_address'],
            $freeswitchSettings['event_socket_port'],
            $freeswitchSettings['event_socket_password']
        );
        event_socket_request($fp, 'bgapi reloadxml');

        //clear fusionpbx cache
        FusionCache::clear("dialplan:" . $ringGroup->ring_group_context);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  Request  $request
     * @param  RingGroups  $ringGroup
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|Response
     */
    public function edit(Request $request, RingGroups $ringGroup)
    {
        if (!userCheckPermission('ring_group_add') && !userCheckPermission('ring_group_edit')) {
            return redirect('/');
        }

        $moh = MusicOnHold::where('domain_uuid', Session::get('domain_uuid'))
            ->orWhere('domain_uuid', null)
            ->orderBy('music_on_hold_name', 'ASC')
            ->get()
            ->unique('music_on_hold_name');

        $recordings = Recordings::where('domain_uuid', Session::get('domain_uuid'))
            ->orderBy('recording_name', 'ASC')
            ->get();

        $destinationsByCategory = 'disabled';
        $timeoutDestinationsByCategory = [];
        foreach ([
            'ringgroup',
            'extensions',
            'timeconditions',
            'voicemails',
            'others'
        ] as $category) {
            $c = getDestinationByCategory($category, $ringGroup->ring_group_timeout_data);
            if ($c['selectedCategory']) {
                $destinationsByCategory = $c['selectedCategory'];
            }
            $timeoutDestinationsByCategory[$category] = $c['list'];
        }
        unset($c, $category);

        return view('layouts.ringgroups.createOrUpdate')
            ->with('ringGroup', $ringGroup)
            ->with('moh', $moh)
            ->with('recordings', $recordings)
            ->with('timeoutDestinationsByCategory', $timeoutDestinationsByCategory)
            ->with('destinationsByCategory', $destinationsByCategory)
            ->with('extensions', $this->getDestinationExtensions())
            ->with('ringGroupDestinations', $ringGroup->getGroupDestinations());
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateRingGroupRequest  $request
     * @param  RingGroups  $ringGroup
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(UpdateRingGroupRequest $request, RingGroups $ringGroup)
    {
        if (!userCheckPermission('ring_group_add') && !userCheckPermission('ring_group_edit')) {
            return redirect('/');
        }

        $attributes = $request->validated();
        // logger($attributes);

        if ($attributes['ring_group_forward']['all']['type'] == 'external') {
            $attributes['ring_group_forward_destination'] = PhoneNumber::make($attributes['ring_group_forward']['all']['target_external'], "US")->formatE164();
        } else {
            $attributes['ring_group_forward_destination'] = ($attributes['ring_group_forward']['all']['target_internal'] == '0') ? '' : $attributes['ring_group_forward']['all']['target_internal'];;
            if (empty($attributes['ring_group_forward_destination'])) {
                $attributes['ring_group_forward_enabled'] = 'false';
            }
        }

        $ringGroup->update([
            'ring_group_name' => $attributes['ring_group_name'],
            'ring_group_extension' => $attributes['ring_group_extension'],
            'ring_group_greeting' => $attributes['ring_group_greeting'] ?? null,
            'ring_group_call_timeout' => $attributes['ring_group_call_timeout'],
            'ring_group_timeout_app' => ($attributes['timeout_category'] != 'disabled') ? 'transfer' : null,
            'ring_group_timeout_data' => $attributes['ring_group_timeout_data'],
            'ring_group_cid_name_prefix' => $attributes['ring_group_cid_name_prefix'] ?? null,
            'ring_group_cid_number_prefix' => $attributes['ring_group_cid_number_prefix'] ?? null,
            'ring_group_description' => $attributes['ring_group_description'],
            'ring_group_enabled' => $attributes['ring_group_enabled'],
            'ring_group_forward_enabled' => $attributes['ring_group_forward_enabled'],
            'ring_group_forward_destination' => $attributes['ring_group_forward_destination'],
            'ring_group_strategy' => $attributes['ring_group_strategy'],
            'ring_group_caller_id_name' => $attributes['ring_group_caller_id_name'] ?? null,
            'ring_group_caller_id_number' => $attributes['ring_group_caller_id_number'] ?? null,
            'ring_group_distinctive_ring' => $attributes['ring_group_distinctive_ring'],
            'ring_group_ringback' => $attributes['ring_group_ringback'],
            'ring_group_call_forward_enabled' => $attributes['ring_group_call_forward_enabled'],
            'ring_group_follow_me_enabled' => $attributes['ring_group_follow_me_enabled'],
            'ring_group_missed_call_data' => $attributes['ring_group_missed_call_data'],
            'ring_group_missed_call_app' => ($attributes['ring_group_missed_call_category'] == 'disabled') ? null : $attributes['ring_group_missed_call_category'],
            'ring_group_forward_toll_allow' => $attributes['ring_group_forward_toll_allow'],
            'ring_group_context' => $attributes['ring_group_context'] ?? null
        ]);

        $ringGroup->groupDestinations()->delete();

        $ringGroup->save();

        if (isset($attributes['ring_group_destinations']) && count($attributes['ring_group_destinations']) > 0) {
            $i = 0;
            foreach ($attributes['ring_group_destinations'] as $destination) {
                if ($i > 49) {
                    break;
                }
                $groupsDestinations = new RingGroupsDestinations();
                if ($destination['type'] == 'external') {
                    $groupsDestinations->destination_number = format_phone_or_extension($destination['target_external']);
                } else {
                    $groupsDestinations->destination_number = $destination['target_internal'];
                }
                $groupsDestinations->destination_delay = $destination['delay'];
                $groupsDestinations->destination_timeout = $destination['timeout'];
                if ($destination['prompt'] == 'true') {
                    $groupsDestinations->destination_prompt = 1;
                } else {
                    $groupsDestinations->destination_prompt = null;
                }
                //$groupsDestinations->follow_me_order = $i;
                $ringGroup->groupDestinations()->save($groupsDestinations);
                $i++;
            }
        }

        logger("generating dialplan");
        $this->generateDialPlanXML($ringGroup);

        $freeswitchSettings = FreeswitchSettings::first();
        $fp = event_socket_create(
            $freeswitchSettings['event_socket_ip_address'],
            $freeswitchSettings['event_socket_port'],
            $freeswitchSettings['event_socket_password']
        );
        event_socket_request($fp, 'bgapi reloadxml');

        //clear fusionpbx cache
        FusionCache::clear("dialplan:" . $ringGroup->ring_group_context);

        return response()->json([
            'status' => 'success',
            'ring_group' => $ringGroup->ring_group_uuid,
            'message' => 'RingGroup has been saved'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  RingGroups  $ringGroup
     * @return Response
     */
    public function destroy(RingGroups $ringGroup)
    {
        if (!userCheckPermission('ring_group_delete')) {
            return redirect('/');
        }

        $deleted = $ringGroup->delete();
        $dialPlan = Dialplans::where('dialplan_uuid', $ringGroup->dialplan_uuid)->first();
        $dialPlan->delete();

        $freeswitchSettings = FreeswitchSettings::first();
        $fp = event_socket_create(
            $freeswitchSettings['event_socket_ip_address'],
            $freeswitchSettings['event_socket_port'],
            $freeswitchSettings['event_socket_password']
        );

        event_socket_request($fp, 'bgapi reloadxml');

        //clear fusionpbx cache
        FusionCache::clear("dialplan:" . $ringGroup->ring_group_context);

        if ($deleted) {
            return response()->json([
                'status' => 200,
                'success' => [
                    'message' => 'Selected Ring Groups have been deleted'
                ]
            ]);
        } else {
            return response()->json([
                'error' => 401,
                'error' => [
                    'message' => "There was an error deleting this Ring Group",
                ],
            ]);
        }
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

    // private function getDestinationByCategory($category, $data = null)
    // {
    //     $output = [];
    //     $selectedCategory = null;
    //     $rows = null;

    //     switch ($category) {
    //         case 'ringgroup':
    //             $rows = RingGroups::where('domain_uuid', Session::get('domain_uuid'))
    //                 ->where('ring_group_enabled', 'true')
    //                 //->whereNotIn('extension_uuid', [$extension->extension_uuid])
    //                 ->orderBy('ring_group_extension')
    //                 ->get();
    //             break;
    //         case 'dialplans':
    //             $rows = Dialplans::where('domain_uuid', Session::get('domain_uuid'))
    //                 ->where('dialplan_enabled', 'true')
    //                 ->where('dialplan_destination', 'true')
    //                 ->where('dialplan_number', '<>', '')
    //                 ->orderBy('dialplan_name')
    //                 ->get();
    //             break;
    //         case 'extensions':
    //             $rows = Extensions::where('domain_uuid', Session::get('domain_uuid'))
    //                 //->whereNotIn('extension_uuid', [$extension->extension_uuid])
    //                 ->orderBy('extension')
    //                 ->get();
    //             break;
    //         case 'timeconditions':
    //             $rows = [];
    //             break;
    //         case 'voicemails':
    //             $rows = Voicemails::where('domain_uuid', Session::get('domain_uuid'))
    //                 ->where('voicemail_enabled', 'true')
    //                 ->orderBy('voicemail_id')
    //                 ->get();
    //             break;
    //         case 'others':
    //             $rows = [
    //                 [
    //                     'id' => sprintf('*98 XML %s', Session::get('domain_name')),
    //                     'label' => 'Check Voicemail'
    //                 ], [
    //                     'id' => sprintf('*411 XML %s', Session::get('domain_name')),
    //                     'label' => 'Company Directory'
    //                 ], [
    //                     'id' => 'hangup',
    //                     'label' => 'Hangup'
    //                 ], [
    //                     'id' => sprintf('*732 XML %s', Session::get('domain_name')),
    //                     'label' => 'Record'
    //                 ]
    //             ];
    //             break;
    //         default:

    //     }

    //     if ($rows) {
    //         foreach ($rows as $row) {
    //             switch ($category) {
    //                 case 'ringgroup':
    //                     $id = sprintf('%s XML %s', $row->ring_group_extension, Session::get('domain_name'));
    //                     if($id == $data) {
    //                         $selectedCategory = $category;
    //                     }
    //                     $output[] = [
    //                         'id' => $id,
    //                         'label' => $row->ring_group_name
    //                     ];
    //                     break;
    //                 case 'dialplans':
    //                     $id = sprintf('%s XML %s', $row->dialplan_number, Session::get('domain_name'));
    //                     if($id == $data) {
    //                         $selectedCategory = $category;
    //                     }
    //                     $output[] = [
    //                         'id' => $id,
    //                         'label' => $row->dialplan_name
    //                     ];
    //                     break;
    //                 case 'extensions':
    //                     $id = sprintf('%s XML %s', $row->extension, Session::get('domain_name'));
    //                     if($id == $data) {
    //                         $selectedCategory = $category;
    //                     }
    //                     $output[] = [
    //                         'id' => $id,
    //                         'label' => $row->extension
    //                     ];
    //                     break;
    //                 case 'timeconditions':
    //                     //$output[$row->ring_group_uuid] = $row->ring_group_name;
    //                     break;
    //                 case 'voicemails':
    //                     $id = sprintf('*99%s XML %s', $row->voicemail_id, Session::get('domain_name'));
    //                     if($id == $data) {
    //                         $selectedCategory = $category;
    //                     }
    //                     $output[] = [
    //                         'id' => $id,
    //                         'label' => $row->voicemail_id
    //                     ];
    //                     break;
    //                 case 'others':
    //                     if($row['id'] == $data) {
    //                         $selectedCategory = $category;
    //                     }
    //                     $output[] = [
    //                         'id' => $row['id'],
    //                         'label' => $row['label']
    //                     ];
    //                     break;
    //                 default:

    //             }
    //         }
    //     }


    //     return [
    //         'selectedCategory' => $selectedCategory,
    //         'list' => $output
    //     ];
    // }
}
