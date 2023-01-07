<?php

namespace App\Http\Controllers;


use cache;
use App\Models\Domain;
use App\Models\Extensions;
use App\Models\Voicemails;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use App\Models\Faxes;
class FaxesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Check permissions
        if (!userCheckPermission("fax_view")){
            return redirect('/');
        }
        // $list = Session::get('permissions', false);
        // pr($list);exit;
        $domain_uuid=Session::get('domain_uuid');
        $data['faxes']=Faxes::where('domain_uuid',$domain_uuid)->get();
        // pr($data);exit;
        $permissions['add_new'] = userCheckPermission('fax_add');
        $permissions['edit'] = userCheckPermission('fax_edit');
        $permissions['delete'] = userCheckPermission('fax_delete');
        $permissions['view'] = userCheckPermission('fax_view');
        $permissions['send'] = userCheckPermission('fax_send');
        
        return view('layouts.fax.list')
            ->with($data)
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
        //
    }
}
