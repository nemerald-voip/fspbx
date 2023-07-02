<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRingGroupRequest;
use App\Http\Requests\UpdateRingGroupRequest;
use App\Models\Extensions;
use App\Models\FollowMeDestinations;
use App\Models\IvrMenus;
use App\Models\MusicOnHold;
use App\Models\Recordings;
use App\Models\RingGroups;
use App\Models\RingGroupsDestinations;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Session;

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
        if($ringGroupDestinations->count() > 0) {
            if($ringGroupDestinations[0]->ring_group_uuid == $ringGroup->ring_group_uuid) {
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

        return view('layouts.ringgroups.createOrUpdate')
            ->with('ringGroup', $ringGroup)
            ->with('moh', $moh)
            ->with('recordings', $recordings)
            ->with('extensions', $this->getDestinationExtensions())
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

        $ringGroups = new RingGroups();
        $ringGroups->fill([
            'ring_group_name' => $attributes['ring_group_name'],
            'ring_group_extension' => $attributes['ring_group_extension'],
            'ring_group_greeting' => $attributes['ring_group_greeting'] ?? null,
            'ring_group_call_timeout' => $attributes['ring_group_call_timeout'],
            'ring_group_timeout_action' => $attributes['ring_group_timeout_action'],
            'ring_group_cid_name_prefix' => $attributes['ring_group_cid_name_prefix'],
            'ring_group_cid_number_prefix' => $attributes['ring_group_cid_number_prefix'],
            'ring_group_description' => $attributes['ring_group_description'],
            'ring_group_enabled' => $attributes['ring_group_enabled'],
            'ring_group_forward_enabled' => $attributes['ring_group_forward_enabled'],
            'ring_group_strategy' => $attributes['ring_group_strategy'],
            'ring_group_caller_id_name' => $attributes['ring_group_caller_id_name'],
            'ring_group_caller_id_number' => $attributes['ring_group_caller_id_number'],
            'ring_group_distinctive_ring' => $attributes['ring_group_distinctive_ring'],
            'ring_group_ringback' => $attributes['ring_group_ringback'],
            'ring_group_call_forward_enabled' => $attributes['ring_group_call_forward_enabled'],
            'ring_group_follow_me_enabled' => $attributes['ring_group_follow_me_enabled'],
            'ring_group_missed_call_data' => $attributes['ring_group_missed_call_data'],
            'ring_group_forward_toll_allow' => $attributes['ring_group_forward_toll_allow'],
            'ring_group_forward_context' => $attributes['ring_group_forward_context']
        ]);

        $ringGroups->save();

        if (count($attributes['ring_group_destinations']) > 0) {
            $i = 0;
            foreach ($attributes['ring_group_destinations'] as $destination) {
                if ($i > 49) break;
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
                $ringGroups->groupDestinations()->save($groupsDestinations);
                $i++;
            }
        }

        return response()->json([
            'status' => 'success',
            'ring_group' => $ringGroups,
            'message' => 'RingGroup has been created.'
        ]);
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

        $ringGroupRingMyPhoneTimeout = 0;
        $ringGroupDestinations = $ringGroup->getGroupDestinations();
        if($ringGroupDestinations->count() > 0) {
            if($ringGroupDestinations[0]->ring_group_uuid == $ringGroup->ring_group_uuid) {
                $ringGroupRingMyPhoneTimeout = $ringGroupDestinations[0]->destination_timeout;
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

        return view('layouts.ringgroups.createOrUpdate')
            ->with('ringGroup', $ringGroup)
            ->with('moh', $moh)
            ->with('recordings', $recordings)
            ->with('extensions', $this->getDestinationExtensions())
            ->with('ringGroupRingMyPhoneTimeout', $ringGroupRingMyPhoneTimeout)
            ->with('ringGroupDestinations', $ringGroupDestinations);
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

        $ringGroup->update([
            'ring_group_name' => $attributes['ring_group_name'],
            'ring_group_greeting' => $attributes['ring_group_greeting'] ?? null,
            'ring_group_call_timeout' => $attributes['ring_group_call_timeout'],
            'ring_group_timeout_action' => $attributes['ring_group_timeout_action'],
            'ring_group_cid_name_prefix' => $attributes['ring_group_cid_name_prefix'],
            'ring_group_cid_number_prefix' => $attributes['ring_group_cid_number_prefix'],
            'ring_group_description' => $attributes['ring_group_description'],
            'ring_group_enabled' => $attributes['ring_group_enabled'],
            'ring_group_forward_enabled' => $attributes['ring_group_forward_enabled'],
            'ring_group_strategy' => $attributes['ring_group_strategy'],
            'ring_group_caller_id_name' => $attributes['ring_group_caller_id_name'],
            'ring_group_caller_id_number' => $attributes['ring_group_caller_id_number'],
            'ring_group_distinctive_ring' => $attributes['ring_group_distinctive_ring'],
            'ring_group_ringback' => $attributes['ring_group_ringback'],
            'ring_group_call_forward_enabled' => $attributes['ring_group_call_forward_enabled'],
            'ring_group_follow_me_enabled' => $attributes['ring_group_follow_me_enabled'],
            'ring_group_missed_call_data' => $attributes['ring_group_missed_call_data'],
            'ring_group_forward_toll_allow' => $attributes['ring_group_forward_toll_allow'],
            'ring_group_forward_context' => $attributes['ring_group_forward_context']
        ]);

        $ringGroup->groupDestinations()->delete();

        $ringGroup->save();

        if (count($attributes['ring_group_destinations']) > 0) {
            $i = 0;
            foreach ($attributes['ring_group_destinations'] as $destination) {
                if ($i > 49) break;
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

        if ($deleted) {
            return response()->json([
                'status' => 'success',
                'id' => $ringGroup->ring_group_uuid,
                'message' => 'Selected Ring Group have been deleted'
            ]);
        } else {
            return response()->json([
                'error' => 401,
                'message' => 'There was an error deleting this Ring Group'
            ]);
        }
    }

    private function getDestinationExtensions() {
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
