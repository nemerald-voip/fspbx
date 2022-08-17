<?php

namespace App\Http\Controllers;

use App\Jobs\SendAppCredentials;
use App\Models\Domain;
use App\Models\Extensions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppCredentialsGenerated;
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
     * Submit request to update organization to Ringotel
     *
     * @return \Illuminate\Http\Response
     */
    public function updateOrganization(Request $request)
    {

        $data = array(
            'method' => 'createOrganization',
            'params' => array(
                'name' => 'Test Organization',
                'region' => '2',
                'domain' => "testorganization"
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


        // If successful return success status
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
                    'nosrtp' => false,
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
                        'Ringback2' => [
                            'Ringback 1'
                        ],
                        'Progress' => [
                            'Progress 1'
                        ],
                        'Ringback' => [
                            'United States'
                        ],
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
     * Return Ringotel app user settings
     *
     * @return \Illuminate\Http\Response
     */
    public function mobileAppUserSettings(Request $request, Extensions $extension)
    {


        // If user doesn't exist create a new one
        $org_id = appsGetOrganizationDetails($extension->domain_uuid);

        // Get all connections for this organization
        $data = array(
            'method' => 'getBranches',
            'params' => array(
                'orgid' => $org_id,
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
                    'message' => 'Unable to get organization']);
                })
            ->json();

        // If successful continue 
        if (isset($response['result'])){
            $connections = $response['result'];
            $app_domain = $response['result'][0]['domain'];

        // Otherwise return failed status
        } elseif (isset($response['error'])) {
            return response()->json([
                'error' => 401,
                'message' => $response['error']['message']]);
        } else {
            return response()->json([
                'error' => 401,
                'message' => 'Unknown error']);
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


        // If user doesn't exist create a new one
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

        // If successful continue 
        if (isset($response['result'])){
            // $connections = $response['result'];
            // $app_domain = $response['result'][0]['domain'];


        //Otherwise return failed status
        } elseif (isset($response['error'])) {
            return response()->json([
                'error' => 401,
                'message' => $response['error']['message']]);
        } else {
            return response()->json([
                'error' => 401,
                'message' => 'Unknown error']);
        }

        // If success send user email with credentials 
        SendAppCredentials::dispatch($response['result'])->onQueue('emails');
        // Log::info('Dispatched email ');

        return response()->json([
            'request' => $request->all(),
            'user' => $response,
            'status' => 'success',
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

        Log::info('Dispatched email ');
        return 'Dispatched email ';
    }
}
