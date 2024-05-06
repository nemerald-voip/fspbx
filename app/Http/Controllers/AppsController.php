<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Extensions;
use Aws\Mobile\MobileClient;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Models\MobileAppUsers;
use App\Jobs\SendAppCredentials;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\AppCredentialsGenerated;
use Illuminate\Support\Facades\Session;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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

        return view('layouts.apps.list')
            ->with("domains",$domains);
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

            // Add received OrgID to the request and store it in database
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

            // Get connection port from database or env file
            $protocol = get_domain_setting('mobile_app_conn_protocol', $request->organization_uuid);
            $port = get_domain_setting('line_sip_port', $request->organization_uuid);
            $proxy = get_domain_setting('mobile_app_proxy', $request->organization_uuid);

            return response()->json([
                'status' => 200,
                'organization_name' => $request->organization_name,
                'organization_domain' => $request->organization_domain,
                'organization_region' => $request->organization_region,
                'org_id' => $response['result']['id'],
                'protocol' => ($protocol) ? $protocol : "",
                'connection_port' => ($port) ? $port : config("ringotel.connection_port"),
                'outbound_proxy' => ($proxy) ? $proxy : config("ringotel.outbound_proxy"),
                'success' => [
                    'message' => 'Organization created succesfully',
                ]
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
                    'features' => 'pbx',
                    "speeddial" => array(
                        [
                            'number' => '*97',
                            'title' => 'Voicemail'
                        ]
                    ),
                    'vmail' => [
                        'ext' => '*97',
                        'name' => 'Voicemail',
                        'mess' => 'You have a new message',
                        'off' => '',
                        'on' => ''
                    ],
                    'dnd' => [
                        'off' => '*79',
                        'on' => '*78'
                    ],
                    'forwarding' => [
                        'cfuon' => '',
                        'cfboff' => '',
                        'cfon' => '*72',
                        'cfbon' => '',
                        'cfuoff' => '',
                        'cfoff' => '*73'
                    ],

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
     * Submit getOrganizations request to Ringotel API
     *
     * @return \Illuminate\Http\Response
     */
    public function getOrganizations(Request $request)
    {

        // Send request to get all Organizations
        $response = appsGetOrganizations();

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
                    'message' => "An unknown error has occurred",
                ],
            ])->getData(true);
        }

        $orgs = DB::table('v_domain_settings')
        -> join('v_domains', 'v_domains.domain_uuid', '=', 'v_domain_settings.domain_uuid')
        -> where('domain_setting_category', 'app shell')
        -> where ('domain_setting_subcategory', 'org_id')
        -> get([
            'v_domain_settings.domain_uuid',
            'domain_setting_value AS org_id',
            'domain_name',
            'domain_description',
        ]);

        $domains = Session::get('domains');


        $org_array = array();
        foreach ($response['result'] as $organization) {
            foreach ($orgs as $org) {
                if ($organization['id'] == $org->org_id) {
                    $organization['domain_uuid'] = $org->domain_uuid;
                    $organization['domain_name'] = $org->domain_name;
                    $organization['domain_description'] = $org->domain_description;
                }
            }
            $org_array[] = $organization;
        }

        // Log::alert($org_array);

        return response()->json([
            'cloud_orgs' => $org_array,
            'local_orgs' => $domains,
            'status' => 200,
            'success' => [
                'message' => 'The request processed successfully'
            ]
        ]);
    }


    /**
     * Return Ringotel app user settings
     *
     * @return \Illuminate\Http\Response
     */
    public function syncOrganizations(Request $request)
    {

        $app_array = $request->get('app_array');

        foreach ($app_array as $id=>$domain_uuid) {
             // Store new record
            $domainSettings = DomainSettings::create([
                'domain_uuid' => $domain_uuid,
                'domain_setting_category' => 'app shell',
                'domain_setting_subcategory' => 'org_id',
                'domain_setting_name' => 'text',
                'domain_setting_value' => $id,
                'domain_setting_enabled' => true,
            ]);

            $saved = $domainSettings->save();
            if (!$saved){
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error saving some records',
                    ],
                ]);
            }
        }

        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'All organizations were successfully synced'
            ]
        ]);

    }

    /**
     * Sync Ringotel app users from the cloud
     *
     * @return \Illuminate\Http\Response
     */
    public function syncUsers(Request $request, Domain $domain)
    {

        // Get Org ID from database
        $org_id = appsGetOrganizationDetails($domain->domain_uuid);

        // Delete any prior info from database
        $deleted = MobileAppUsers::where('org_id', $org_id)->delete();

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

        foreach ($connections as $connection){
            //Get all users for this connection
            $response = appsGetUsers($org_id, $connection['id']);

            // If successful continue
            if (isset($response['result'])){
                $users = $response['result'];

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

            foreach ($users as $user){
                // Get each user's extension
                $extension = Extensions::where('extension', $user['extension'])
                    ->where ('domain_uuid', $domain->domain_uuid)
                    ->first();
                if ($extension) {
                    // Save returned user info in database
                    $appUser = new MobileAppUsers();
                    $appUser->extension_uuid = $extension->extension_uuid;
                    $appUser->domain_uuid = $extension->domain_uuid;
                    $appUser->org_id = $org_id;
                    $appUser->conn_id = $user['branchid'];
                    $appUser->user_id = $user['id'];
                    $appUser->status = $user['status'];

                    $appUser->save();
                }
            }

        }

        return response()->json([
            'status' => 200,
            'success' => [
                'message' => 'Apps have been synced successfully'
            ]
        ]);

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

        // If Organization isn't set up return
        if(!isset($org_id)) {
            return response()->json([
                'status' => 401,
                'error' => [
                    'message' => "Mobile apps are not activated. Please, contact your administrator",
                ],
            ]);
        }

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

        $mobile_app = [
            'org_id' => $request->org_id,
            'conn_id' => $request->connection,
            'name' => $extension->effective_caller_id_name,
            'email' => ($extension->voicemail) ? $extension->voicemail->voicemail_mail_to : "",
            'ext' => $extension->extension,
            'username' => $extension->extension,
            'domain' => $request->app_domain,
            'authname' => $extension->extension,
            'password' => $extension->password,
            'status' => ($request->activate == 'on') ? 1 : -1,
        ];

        // Send request to create user
        $response = appsCreateUser($mobile_app);

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
                    'message' => "An unknown error has occurred",
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

        $qrcode = "";
        if ($request->activate == 'on'){
            // Generate QR code
            $qrcode = QrCode::format('png')->generate('{"domain":"' . $response['result']['domain'] .
                '","username":"' .$response['result']['username'] . '","password":"'.  $response['result']['password'] . '"}');
        }

        return response()->json([
            'user' => $response['result'],
            'qrcode' => base64_encode($qrcode),
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

        // Delete any prior info from database
        $appUser = $extension->mobile_app;
        if ($appUser) $appUser->delete();

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
                    'message' => "An unknown error has occurred",
                ],
            ])->getData(true);
        }

        return response()->json([
            'user' => $response['result'],
            'status' => 200,
            'success' => [
                'message' => 'The mobile app user has been successfully deleted'
            ]
        ]);
    }


    /**
     * Submit password reset request to Ringotel API
     *
     * @return \Illuminate\Http\Response
     */
    public function resetPassword(Request $request, Extensions $extension)
    {

        $mobile_app = $request->mobile_app;

        // Send request to reset password
        $response = appsResetPassword($mobile_app['org_id'], $mobile_app['user_id']);

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
                    'message' => "An unknown error has occurred",
                ],
            ])->getData(true);
        }

        // If success and user is activated send user email with credentials
        if (isset($extension->voicemail->voicemail_mail_to)){
            SendAppCredentials::dispatch($response['result'])->onQueue('emails');
        }

        // Generate QR code
        $qrcode = QrCode::format('png')->generate('{"domain":"' . $response['result']['domain'] .
            '","username":"' .$response['result']['username'] . '","password":"'.  $response['result']['password'] . '"}');

        return response()->json([
            'user' => $response['result'],
            'qrcode' => base64_encode($qrcode),
            'status' => 200,
            'success' => [
                'message' => 'The mobile app password was succesfully reset'
            ]
        ]);
    }


    /**
     * Submit set status request to Ringotel API
     *
     * @return \Illuminate\Http\Response
     */
    public function setStatus(Request $request, Extensions $extension)
    {

        $mobile_app = $request->mobile_app;
        $mobile_app['status'] = (int)$mobile_app['status'];

        $mobile_app['name'] = $extension['effective_caller_id_name'];
        $mobile_app['email'] = ($extension['voicemail_mail_to']) ? $extension['voicemail_mail_to'] : "";
        $mobile_app['ext'] = $extension['extension'];
        $mobile_app['password'] = $extension->password;

        $appUser = $extension->mobile_app;

        if ($mobile_app['status']==1) {
            // Send request to update user settings
            $response = appsUpdateUser($mobile_app);

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
                    'result' => $response,
                    'error' => [
                        'message' => "An unknown error has occurred",
                    ],
                ])->getData(true);
            }

            // Update user info in database
            if ($appUser) {
                $appUser->status = $mobile_app['status'];
                $appUser->save();
            }

        } else if ($mobile_app['status']==-1) {

            // Send request to delete user first and then recreate it
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
                    'result' => $response,
                    'error' => [
                        'message' => "An unknown error has occurred",
                    ],
                ])->getData(true);
            }

            // Delete any prior info from database
            if ($appUser) $appUser->delete();

            // Send request to get org details
            $response = appsGetOrganization($mobile_app['org_id']);

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
                    'result' => $response,
                    'error' => [
                        'message' => "An unknown error has occurred",
                    ],
                ])->getData(true);
            }

            // Send request to create a new deactivated user
            $mobile_app['username'] = $extension->extension;
            $mobile_app['authname'] = $extension->extension;
            $mobile_app['domain'] = $response['result']['domain'];
            $response = appsCreateUser($mobile_app);

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
                    'result' => $response,
                    'error' => [
                        'message' => "An unknown error has occurred",
                    ],
                ])->getData(true);
            }

            // Save returned user info in database
            $appUser = new MobileAppUsers();
            $appUser->extension_uuid = $extension->extension_uuid;
            $appUser->domain_uuid = $extension->domain_uuid;
            $appUser->org_id = $mobile_app['org_id'];
            $appUser->conn_id = $mobile_app['conn_id'];
            $appUser->user_id = $response['result']['id'];
            $appUser->status = $response['result']['status'];
            $appUser->save();

        }


        $message = ($mobile_app['status'] == 1) ? 'The mobile app has been activated successfully' : "The mobile app has been deactivated";
        return response()->json([
            //'user' => $response['result'],
            'status' => 200,
            'success' => [
                'message' => $message,
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
