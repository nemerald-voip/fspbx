<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Jobs\SendAppCredentials;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppCredentialsGenerated;
use App\Models\MobileAppUsers;
use Aws\Mobile\MobileClient;
use Illuminate\Support\Facades\Session;

class AppsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Check all domains for registration in Ringotel Shell

        $domains = Domain::where('domain_enabled','true')
            ->orderBy('domain_description')
            ->get();

        foreach($domains as $domain) {
            $settings = $domain->settings()
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'org_id')
            ->get();

            if ($settings->count() > 0) {
                $domain->setAttribute('status', 'true');
            } else {
                $domain->setAttribute('status', 'false');
            }

        }

        $conn_params = [
            "connection_port" => env("RINGOTEL_CONNECTION_PORT"),
            "outbound_proxy" => env("RINGOTEL_OUTBOUND_PROXY")
        ];

        return view('layouts.apps.list')
            ->with("domains",$domains)
            ->with("conn_params", $conn_params);
    }

    /**
     * Submit request to create a new organization to Ringotel
     *
     * @return \Illuminate\Http\Response
     */
    public function createOrganization(Request $request)
    {

        $data = array(
            'method' => 'createOrganization',
            'params' => array(
                'name' => $request->organization_name,
                'region' => $request->organization_region,
                'domain' => $request->organization_domain
            )
         );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'error' => 401,
                    'message' => 'Unable to create organization']);
             })
             ->json();
        
        //dd(isset($response['error']));


        // If successful store Org ID and return success status
        if (isset($response['result'])){

            // Add recieved OrgID to the request and store it in database
            $request->merge(['org_id' => $response['result']['id']]);

            if (!appsStoreOrganizationDetails($request)){
                return response()->json([
                    'organization_name' => $request->organization_name,
                    'organization_domain' => $request->organization_domain,
                    'organization_region' => $request->organization_region,
                    'org_id' => $response['result']['id'],
                    'message' => 'Organization was created succesfully, but unable to store Org ID in database',
                ]);
            }

            return response()->json([
                'organization_name' => $request->organization_name,
                'organization_domain' => $request->organization_domain,
                'organization_region' => $request->organization_region,
                'org_id' => $response['result']['id'],
                'message' => 'Organization created succesfully',
            ]);
        // Otherwise return failed status
        } elseif (isset($response['error'])) {
            return response()->json([
                'error' => 401,
                'organization_name' => $request->organization_name,
                'organization_domain' => $request->organization_domain,
                'organization_region' => $request->organization_region,
                'message' => $response['error']['message']]);
        } else {
            return response()->json([
                'error' => 401,
                'organization_name' => $request->organization_name,
                'organization_domain' => $request->organization_domain,
                'organization_region' => $request->organization_region,
                'message' => 'Unknown error']);
        }
    }

    /**
     * Submit request to destroy organization to Ringotel
     *
     * @return \Illuminate\Http\Response
     */
    public function destroyOrganization(Request $request, Domain $domain)
    {

        // Get Org ID from database
        $org_id = appsGetOrganizationDetails($domain->domain_uuid);

        //Get all connections
        $response = appsGetConnections($org_id);

        if (isset($response['error'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => $response['error']['message'],
                ],
                'domain' => $domain->domain_name,
            ]);
        }

        //Delete all connections
        foreach ($response['result'] as $conn) {
            $response = appsDeleteConnection($org_id, $conn['id']);
            if (isset($response['error'])) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => $response['error']['message'],
                    ],
                    'domain' => $domain->domain_name,
                ]);
            }
        }

        // Delete organization
        $response = appsDeleteOrganization($org_id);
        if (isset($response['error'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => $response['error']['message'],
                ],
                'domain' => $domain->domain_name,
            ]);
        }
        //Detele records from database
        $appOrgID = DomainSettings::where('domain_uuid',$domain->domain_uuid)
        ->where ('domain_setting_category', 'app shell')
        ->where ('domain_setting_subcategory', 'org_id')
        ->first();

        Log::info($appOrgID);

        $appOrgID->delete();


        return response()->json([
            'org_details' => $org_id,
            'connections' => $response,
            // 'organization_domain' => $request->organization_domain,
            // 'organization_region' => $request->organization_region,
            // 'org_id' => $response['result']['id'],
            'message' => 'Success',
        ]);




        // If successful store Org ID and return success status
        if (isset($response['result'])){

            // Add recieved OrgID to the request and store it in database
            $request->merge(['org_id' => $response['result']['id']]);

            if (!appsStoreOrganizationDetails($request)){
                return response()->json([
                    'organization_name' => $request->organization_name,
                    'organization_domain' => $request->organization_domain,
                    'organization_region' => $request->organization_region,
                    'org_id' => $response['result']['id'],
                    'message' => 'Organization was created succesfully, but unable to store Org ID in database',
                ]);
            }

            return response()->json([
                'organization_name' => $request->organization_name,
                'organization_domain' => $request->organization_domain,
                'organization_region' => $request->organization_region,
                'org_id' => $response['result']['id'],
                'message' => 'Organization created succesfully',
            ]);
        // Otherwise return failed status
        } elseif (isset($response['error'])) {
            return response()->json([
                'error' => 401,
                'organization_name' => $request->organization_name,
                'organization_domain' => $request->organization_domain,
                'organization_region' => $request->organization_region,
                'message' => $response['error']['message']]);
        } else {
            return response()->json([
                'error' => 401,
                'organization_name' => $request->organization_name,
                'organization_domain' => $request->organization_domain,
                'organization_region' => $request->organization_region,
                'message' => 'Unknown error']);
        }
    }



    /**
     * Submit request to update organization to Ringotel
     *
     * @return \Illuminate\Http\Response
     */
    public function updateOrganization(Request $request)
    {

    }



    /**
     * Submit request to create connection to Ringotel
     *
     * @return \Illuminate\Http\Response
     */
    public function createConnection (Request $request)
    {
        // Build data array
        $data = array(
            'method' => 'createBranch',
            'params' => array(
                "orgid" => $request->org_id,
                'name' => $request->connection_name,
                'address' => $request->connection_domain . ":" . $request->connection_port,
                'provision' => array(
                    'protocol' => $request->connection_protocol,
                    'noverify' => true,
                    'multitenant' => true,
                    'nosrtp' => true,
                    'norec' => true,
                    'internal' => false,
                    'sms' => false,
                    'maxregs' => 4,
                    'private' => false,
                    'dtmfmode' => 'rfc2833',
                    'regexpires' => $request->connection_ttl,
                    'proxy' => array(
                        'paddr' => $request->connection_proxy_address,
                        'pauth' => '',
                        'ppass' => '',
                    ),
                    'httpsproxy' => array(
                        'address' => '',
                    ),
                    'certificate' => '',
                    'tones' => array(
                        'Ringback2' => 'Ringback 1',
                        'Progress' => 'Progress 1',
                        'Ringback' => 'United States',
                    ),

                )
            )
        );
     
        
        // Add codecs
        if (isset($request->connection_codec_u711)){
            $codec = array(
                    'codec' => 'G.711 Ulaw',
                    'frame' => 20
                );
            $codecs[]=$codec;          
        }


        if (isset($request->connection_codec_a711)){
            $codec = array(
                    'codec' => 'G.711 Alaw',
                    'frame' => 20
                );
            $codecs[]=$codec; 
        }

        if (isset($request->connection_codec_729)){
            $codec = array(
                    'codec' => 'G.729',
                    'frame' => 20
                );
            $codecs[]=$codec; 
        }

        if (isset($request->connection_codec_opus)){
            $codec = array(
                    'codec' => 'OPUS',
                    'frame' => 20
                );
            $codecs[]=$codec; 
        }

        $data['params']['provision']['codecs'] = $codecs;

        // Send request to create Connecion to Ringotel
        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'error' => 401,
                    'message' => 'Unable to create connection']);
             })
             ->json();


        // If successful return success status
        if (isset($response['result'])){
            // Add recieved OrgID to the request and store it in database
            $request->merge(['conn_id' => $response['result']['id']]);

            if (!appsStoreConnectionDetails($request)){
                return response()->json([
                    'connection_name' => $request->connection_name,
                    'connection_domain' => $request->connection_domain,
                    'org_id' => $request->org_id,
                    'conn_id' => $response['result']['id'],
                    'message' => 'Connection was created succesfully, but unable to store Conn ID in database',
                ]);
            }
            
            return response()->json([
                'connection_name' => $request->connection_name,
                'connection_domain' => $request->connection_domain,
                'org_id' => $request->org_id,
                'conn_id' => $response['result']['id'],
                'message' => 'Connection created succesfully',
            ]);
        // Otherwise return failed status
        } elseif (isset($response['error'])) {
            return response()->json([
                'error' => 401,
                'connection_name' => $request->connection_name,
                'connection_domain' => $request->connection_domain,
                'org_id' => $request->org_id,
                'conn_id' => $response['result']['id'],
                'message' => $response['error']['message']]);
        } else {
            return response()->json([
                'error' => 401,
                'connection_name' => $request->connection_name,
                'connection_domain' => $request->connection_domain,
                'org_id' => $request->org_id,
                'conn_id' => $response['result']['id'],
                'message' => 'Unknown error']);
        }
    }


    /**
     * Submit request to update connection to Ringotel
     *
     * @return \Illuminate\Http\Response
     */
    public function updateConnection(Request $request)
    {
 
    }


    /**
     * Return Ringotel app user settings
     *
     * @return \Illuminate\Http\Response
     */
    public function mobileAppUserSettings(Request $request, Extensions $extension)
    {
        // Check if the user already exists
        $mobile_app = $extension->mobile_app;
        if ($mobile_app) {
            return response()->json([
                'mobile_app' => $mobile_app,
                'extension' => $extension->extension,
                'name' => $extension->effective_caller_id_name,
                'status' => '200',
            ]);
        }

        // If the user doesn't exist prepare to create a new one
        $org_id = appsGetOrganizationDetails($extension->domain_uuid);

        // Get all connections for this organization
        $response = appsGetConnections($org_id);
           
        // If successful continue 
        if (isset($response['result'])){
            $connections = $response['result'];
            $app_domain = $response['result'][0]['domain'];

        // Otherwise return failed status
        } elseif (isset($response['error'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => $response['error']['message'],
                ],
            ]);
        } else {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => 'Unknown error',
                ],
            ]);
        }

        // If success return response with values
        return response()->json([
            'app_domain' => $app_domain,
            'connections' => $connections,
            'org_id' => $org_id,
            'extension_uuid' => $extension->extension_uuid,
            'status' => 'success',
        ]);
    }


    /**
     * Submit new user request to Ringotel API
     *
     * @return \Illuminate\Http\Response
     */
    public function createUser(Request $request)
    {
        $extension = Extensions::find($request->extension_uuid);

        // Create a new user
        $data = array(
            'method' => 'createUser',
            'params' => array(
                'orgid' => $request->org_id,
                'branchid' => $request->connection,
                'name' => $extension->effective_caller_id_name,
                'email' => $extension->voicemail->voicemail_mail_to,
                'extension' => $extension->extension,
                'username' => $extension->extension,
                'domain' => $request->app_domain,
                'authname' => $extension->extension,
                'password' => $extension->password,
                'status' => ($request->activate == 'on') ? 1 : 2,
                )
            );

        $response = Http::ringotel()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode($data),'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'error' => 401,
                    'message' => 'Unable to create a new user']);
                })
            ->json();

        //If there is an error return failed status
        if (isset($response['error'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => $response['error']['message'],
                ],
            ])->getData(true);
        } elseif (!isset($response['result'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => "An unknown error has occured",
                ],
            ])->getData(true);
        }

        // If success and user is activated send user email with credentials 
        if ($response['result']['status'] == 1 && isset($extension->voicemail->voicemail_mail_to)){
            SendAppCredentials::dispatch($response['result'])->onQueue('emails');
        }

        // Delete any prior info from database
        $appUser = MobileAppUsers::where('extension_uuid', $extension->extension_uuid)->delete();

        // Save returned user info in database
        $appUser = new MobileAppUsers();
        $appUser->extension_uuid = $extension->extension_uuid;
        $appUser->domain_uuid = $extension->domain_uuid;
        $appUser->org_id = $request->org_id;
        $appUser->conn_id = $request->connection;
        $appUser->user_id = $response['result']['id'];
        $appUser->status = $response['result']['status'];
        $appUser->save();
        // Log::info($response);

        return response()->json([
            'user' => $response['result'],
            'status' => 'success',
            'message' => 'The user has been successfully created'
        ]);
    }

    /**
     * Submit delete user request to Ringotel API
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteUser(Request $request, Extensions $extension)
    {

        $mobile_app = $request->mobile_app;

        // Send request to delеte user
        $response = appsDeleteUser($mobile_app['org_id'], $mobile_app['user_id']);
 
        //If there is an error return failed status
        if (isset($response['error'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => $response['error']['message'],
                ],
            ])->getData(true);
        } elseif (!isset($response['result'])) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => "An unknown error has occured",
                ],
            ])->getData(true);
        }


        // Delete any prior info from database
        $appUser = $extension->mobile_app;
        if ($appUser) $appUser->delete();

        return response()->json([
            'user' => $response['result'],
            'status' => 200,
            'success' => [
                'message' => 'The user has been successfully deleted'
            ]
        ]);
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

    public function emailUser (){

        //Mail::to("info@nemerald.com")->send(new AppCredentialsGenerated());
        SendAppCredentials::dispatch()->onQueue('emails');

        //Log::info('Dispatched email ');
        return 'Dispatched email ';
    }
}
