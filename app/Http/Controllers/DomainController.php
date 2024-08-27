<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Extensions;
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
     * Switch domain from one of the Laravel pages.
     * Called when domain search is performed and user requested to switch domain
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function switchDomain(Request $request)
    {
        $domain = Domain::where('domain_uuid', $request->domain_uuid)->first();

        // If current domain is not the same as requested domain proceed with the change
        if (Session::get('domain_uuid') != $domain->uuid) {

            Session::put('domain_uuid', $domain->domain_uuid);
            Session::put('domain_name', $domain->domain_name);
            Session::put('domain_description', !empty($domain->domain_description) ? $domain->domain_description : $domain->domain_name);
            $_SESSION["domain_name"] = $domain->domain_name;
            $_SESSION["domain_uuid"] = $domain->domain_uuid;
            $_SESSION["domain_description"] = !empty($domain->domain_description) ? $domain->domain_description : $domain->domain_name;

            //set the context
            Session::put('context', $_SESSION["domain_name"]);
            $_SESSION["context"] = $_SESSION["domain_name"];

            // unset destinations belonging to old domain
            unset($_SESSION["destinations"]["array"]);

            // This is a workaround to ensure the filters are reset when the domain changes.
            // Given that url()->previous() includes the filter options, it's necessary to pass a refreshed URL.
            if($request->redirect_url) {
                $url = $request->redirect_url;
            } else {
                $url = getFusionPBXPreviousURL(url()->previous());
                $url = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH);
            }

            return response()->json([
                'status' => 200,
                'redirectUrl' => $url,
                'success' => [
                    'message' => 'Domain has been switched'
                ]
            ]);
        }
    }

    /**
     * Switch domain from FusionPBX pages.
     * Called when domain search is performed and user requested to switch domain
     *
     * @return \Illuminate\Http\Response
     */
    public function switchDomainFusionPBX($domain_uuid)
    {
        $domain = Domain::where('domain_uuid', $domain_uuid)->first();

        // If current domain is not the same as requested domain proceed with the change
        if (Session::get('domain_uuid') != $domain->uuid){
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            };
            Session::put('domain_uuid', $domain->domain_uuid);
            Session::put('domain_name', $domain->domain_name);
            Session::put('domain_description', !empty($domain->domain_description) ? $domain->domain_description : $domain->domain_name);
            $_SESSION["domain_name"] = $domain->domain_name;
            $_SESSION["domain_uuid"] = $domain->domain_uuid;
            $_SESSION["domain_description"] = !empty($domain->domain_description) ? $domain->domain_description : $domain->domain_name;

            //set the context
            Session::put('context', $_SESSION["domain_name"]);
            $_SESSION["context"] = $_SESSION["domain_name"];

            // unset destinations belonging to old domain
            unset($_SESSION["destinations"]["array"]);

            $url = getFusionPBXPreviousURL(url()->previous());
            return redirect($url);
        }
    }

    /**
     * Filter domains from FusionPBX pages.
     * Called when domain search is performed and user requested to filter a list of domains
     *
     * @return \Illuminate\Http\Response
     */
    public function filterDomainsFusionPBX(Request $request)
    {
        // Retrieve domains from session
        $domains = Session::get('domains');

        // Check if domains exist in session
        if (!$domains) {
            return response()->json(['error' => 'Domains not found in session'], 404);
        }

        // Check if search parameter is provided
        if ($request->filled('search')) {
            $searchTerm = strtolower($request->search);
            // Filter domains based on search term in both domain_name and domain_description
            $domains = $domains->filter(function ($domain) use ($searchTerm) {
                return strpos(strtolower($domain->domain_name), $searchTerm) !== false || strpos(strtolower($domain->domain_description), $searchTerm) !== false;
            });
        }
        
        // logger($domains);

        $domains = $domains->map(function ($domain) {
            return $domain->toArray();
        })->values()->all();

        echo json_encode($domains, true);
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

    /**
     * get extension count for all domains.
     *
     *
     * @return \Illuminate\Http\Response
     */
    public function countExtensionsInDomains()
    {
        if (isSuperAdmin()) {


            $domains = Domain::get();


            foreach ($domains as $domain) {
                print $domain->domain_description;
                print "<br>";

                $extensions = Extensions::where('domain_uuid', $domain->domain_uuid)->get()->count();

                print $extensions;
                print "<br><br>";
            }
        } else {
            return redirect('dashboard');
        }
    }
}
