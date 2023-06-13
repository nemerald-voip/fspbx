<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRingGroupRequest;
use App\Models\FaxQueues;
use App\Models\RingGroups;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class RingGroupsController extends Controller
{
    /**
     * @param Request $request
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
        $ringGroups = $ringGroups->orderBy('insert_date', )->paginate(10)->onEachSide(1);

        $permissions['delete'] = userCheckPermission('ring_group_delete');
        $permissions['view'] = userCheckPermission('ring_group_view');
        $permissions['edit'] = userCheckPermission('ring_group_edit');
        $data = [];
        $data['ringGroups'] = $ringGroups;

        return view('layouts.ringgroups.list')
            ->with($data)
            ->with('permissions', $permissions);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
/*
 * ring_group_destination_delete
extension_dial_string
extension_absolute_codec_string
ring_group_view
ring_group_add
ring_group_edit
ring_group_delete
ring_group_forward
ring_group_prompt
ring_group_destination_view
ring_group_destination_add
ring_group_user_view
ring_group_user_add
ring_group_user_edit
ring_group_user_delete
ring_group_missed_call
ring_group_forward_toll_allow
ring_group_caller_id_name
ring_group_caller_id_number
ring_group_context
ring_group_all
ring_group_destinations
ring_group_destination_edit
 */

        //check permissions
        if (!userCheckPermission('ring_group_add') || !userCheckPermission('ring_group_edit')) {
            return redirect('/');
        }
die('ssss');
        $ringGroup = new RingGroups();

        return view('layouts.ringgroups.createOrUpdate')
            ->with('ringGroup', $ringGroup);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreRingGroupRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRingGroupRequest $request)
    {
        die('ssss');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(StoreRingGroupRequest $request, $id)
    {
        if (!userCheckPermission('ring_group_edit')) {
            return redirect('/');
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!userCheckPermission('ring_group_edit')) {
            return redirect('/');
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!userCheckPermission('ring_group_delete')) {
            return redirect('/');
        }
    }
}
