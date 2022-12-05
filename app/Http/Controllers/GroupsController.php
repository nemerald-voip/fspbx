<?php

namespace App\Http\Controllers;

use App\Models\Groups;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use permissions;

class GroupsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Check permissions
        if (!userCheckPermission("group_view")){
            return redirect('/');
        }

        //Check FusionPBX login status
        session_start();
        if(!isset($_SESSION['user'])) {
            return redirect()->route('logout');
        }

        $groups = Groups::get()->sortBy('group_name');

        //assign permissions
        $permissions['add_new'] = userCheckPermission('group_add');
        $permissions['edit'] = userCheckPermission('group_edit');
        $permissions['delete'] = userCheckPermission('group_delete');
        $permissions['domain_groups'] = isSuperAdmin();

        return view('layouts.groups.list')
        ->with("groups",$groups)
        ->with('permissions',$permissions);
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
    public function edit($id)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $group = Groups::findOrFail($id);

        if(isset($group)){
            if ($group->permissions->isNotEmpty()) {
                $deleted = $group->permissions()->delete();
            }
            $deleted = $group->delete();

            if ($deleted){
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected groups have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected groups'
                    ]
                ]);
            }
        }
    }
}
