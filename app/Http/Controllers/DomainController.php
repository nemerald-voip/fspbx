<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\StoreDomainRequest;
use App\Http\Requests\UpdateDomainRequest;

class DomainController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Switch domain. Called when domain search is performed and user requested 
     * to switch domain
     *
     * @return \Illuminate\Http\Response
     */
    public function switchDomain(Request $request)
    {
        $domain = Domain::where('domain_uuid', $request->domain_uuid)->first();

        // If current domain is not the same as requested domain proceed with the change
        if (Session::get('domain_uuid') != $domain->uuid){
            session_start();
            Session::put('domain_uuid', $domain->domain_uuid);
            Session::put('domain_name', $domain->domain_name);
            $_SESSION["domain_name"] = $domain->domain_name;
            $_SESSION["domain_uuid"] = $domain->domain_uuid;
            return redirect()->back();
        }
        
    }

    /**
     * Switch domain from FusionPBX. Called when domain search is performed and user requested 
     * to switch domain
     *
     * @return \Illuminate\Http\Response
     */
    public function switchDomainFusionPBX($domain_uuid)
    {
        $domain = Domain::where('domain_uuid', $domain_uuid)->first();

        // If current domain is not the same as requested domain proceed with the change
        if (Session::get('domain_uuid') != $domain->uuid){
            session_start();
            Session::put('domain_uuid', $domain->domain_uuid);
            Session::put('domain_name', $domain->domain_name);
            $_SESSION["domain_name"] = $domain->domain_name;
            $_SESSION["domain_uuid"] = $domain->domain_uuid;
            return redirect()->back();
        }
        
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
     * @param  \App\Http\Requests\StoreDomainRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreDomainRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Domain  $domain
     * @return \Illuminate\Http\Response
     */
    public function show(Domain $domain)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Domain  $domain
     * @return \Illuminate\Http\Response
     */
    public function edit(Domain $domain)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateDomainRequest  $request
     * @param  \App\Models\Domain  $domain
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateDomainRequest $request, Domain $domain)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Domain  $domain
     * @return \Illuminate\Http\Response
     */
    public function destroy(Domain $domain)
    {
        //
    }
}
