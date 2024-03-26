<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\DomainGroupRelations;
use App\Models\DomainGroups;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DomainGroupsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Check permissions
        if (!isSuperAdmin()){
            return redirect('/');
        }

        //Check FusionPBX login status
        // session_start();
        // if(!isset($_SESSION['user'])) {
        //     return redirect()->route('logout');
        // }

        $groups = DomainGroups::get()->sortBy('group_name');

        //assign permissions
        $permissions['add_new'] = isSuperAdmin();
        $permissions['edit'] = isSuperAdmin();
        $permissions['delete'] = isSuperAdmin();

        return view('layouts.domains.groups.list')
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
        //Check FusionPBX login status
        // session_start();
        // if(!isset($_SESSION['user'])) {
        //     return redirect()->route('logout');
        // }

        // Check permissions
        if (!isSuperAdmin()){
            return redirect('/');
        }

        //get all active domains
        $all_domains = Domain::where('domain_enabled','true')
        ->get();

        $domain_group = new DomainGroups();

        $data=array();
        $data['all_domains'] = $all_domains;
        $data['domain_group'] = $domain_group;

        return view('layouts.domains.groups.createOrUpdate')->with($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, DomainGroups $domain_group)
    {
        $attributes = [
            // 'user_email' => 'email',
        ];

        $validator = Validator::make($request->all(), [
            'group_name' =>'required|string|max:100',
            'domains' => 'nullable', 
        ], [], $attributes);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();

        $domain_group->fill($attributes);
        $saved = $domain_group->save();  

        if (isset($attributes['domains'])) {
            foreach($attributes['domains'] as $domain){
                $domain_group_relation = new DomainGroupRelations();
                $domain_group_relation->domain_uuid=$domain;
                $domain_group->domain_group_relations()->save($domain_group_relation);
            }
        }

        if (!$saved){
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'There was an error saving some records',
                ],
            ]);
        }

        return response()->json([
            'domain_group' => $domain_group->domain_group_uuid,
            'redirect_url' => route('domaingroups.edit', $domain_group),
            'status' => 200,
            'success' => [
                'message' => 'Domain Group has been saved'
            ]
        ]);

    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\DomainGroups  $domainGroups
     * @return \Illuminate\Http\Response
     */
    public function show(DomainGroups $domainGroups)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\DomainGroups  $domainGroups
     * @return \Illuminate\Http\Response
     */
    public function edit(DomainGroups $domaingroup)
    {
        //Check FusionPBX login status
        // session_start();
        // if(!isset($_SESSION['user'])) {
        //     return redirect()->route('logout');
        // }

        // Check permissions
        if (!isSuperAdmin()){
            return redirect('/');
        }

        //get all active domains
        $all_domains = Domain::where('domain_enabled','true')
        ->get();

        $data=array();
        $data['all_domains'] = $all_domains;

        $data['assigned_domains'] = collect();
        foreach ($domaingroup->domain_group_relations as $domain_relation) {
            $data['assigned_domains'] ->push($domain_relation->domain);
        }

        $data['domain_group'] = $domaingroup;

        return view('layouts.domains.groups.createOrUpdate')->with($data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\DomainGroups  $domainGroups
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, DomainGroups $domaingroup)
    {
        $attributes = [
            // 'user_email' => 'email',
        ];

        $validator = Validator::make($request->all(), [
            'group_name' =>'required|string|max:100',
            'domains' => 'nullable', 
        ], [], $attributes);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();

        $saved = $domaingroup->update($attributes); 

        // Update domain group relation table
        foreach($domaingroup->domain_group_relations as $relation) {
            $relation->delete();
        }

        if (isset($attributes['domains'])) {
            foreach($attributes['domains'] as $domain){
                $domain_group_relation = new DomainGroupRelations();
                $domain_group_relation->domain_uuid=$domain;
                $domaingroup->domain_group_relations()->save($domain_group_relation);
            }
        }

        if (!$saved){
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'There was an error saving some records',
                ],
            ]);
        }

        return response()->json([
            'domain_group' => $domaingroup->domain_group_uuid,
            'redirect_url' => route('domaingroups.index', $domaingroup),
            'status' => 200,
            'success' => [
                'message' => 'Domain Group has been saved'
            ]
        ]);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\DomainGroups  $domainGroups
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $domain_group = DomainGroups::findOrFail($id);

        if(isset($domain_group)){
            $deleted = $domain_group->delete();

            if ($deleted){
                return response()->json([
                    'status' => 'success',
                    'id' => $id,
                    'message' => 'Selected domain groups have been deleted'
                ]);
            } else {
                return response()->json([
                    'error' => 401,
                    'message' => 'There was an error deleting this domain group'
                ]);
            }
        }
    }
}
