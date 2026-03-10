<?php

namespace App\Services;

use App\DTO\RingotelUserDTO;
use App\DTO\RingotelRegionDTO;
use App\Models\DomainSettings;
use App\Models\DefaultSettings;
use App\DTO\RingotelConnectionDTO;
use Illuminate\Support\Facades\DB;
use App\DTO\RingotelOrganizationDTO;
use App\Models\MobileAppUsers;
use Illuminate\Support\Facades\Http;

class RingotelApiService
{
    // protected $apiUrl;
    protected $timeout = 30;

    public function __construct()
    {
        // $this->apiUrl = config('services.third_party_api.url');
    }

    /**
     * Retrieve the configuration value for Ringotel settings with fallback.
     *
     * @return mixed
     */
    public function getRingotelApiToken()
    {
        // Check the DefaultSettings table
        $value = DefaultSettings::where([
            ['default_setting_category', '=', 'mobile_apps'],
            ['default_setting_subcategory', '=', 'ringotel_api_token'],
            ['default_setting_enabled', '=', 'true'],
        ])->value('default_setting_value');

        if ($value !== null) {
            return $value;
        }

        // Fallback to config and .env
        return config("ringotel.token", '');
    }

    /**
     * Ensure that the API token exists before making API calls.
     *
     * @throws \Exception
     * @return string
     */
    protected function ensureApiTokenExists(): string
    {
        $token = $this->getRingotelApiToken();

        if (empty($token)) {
            throw new \Exception("API token is missing.");
        }

        return $token;
    }


    public function createOrganization($params)
    {
        $this->ensureApiTokenExists();
        // Prepare the payload
        $data = [
            'method' => 'createOrganization',
            'params' => [
                'name' => $params['organization_name'],
                'region' => $params['region'],
                'domain' => $params['organization_domain'],
                'packageid' => (int) $params['package'],
                'params' => [
                    'hidePassInEmail' => $params['dont_send_user_credentials'],
                ],
            ],
        ];

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to activate organization");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        return $response['result'];
    }

    public function updateOrganization($params)
    {
        $this->ensureApiTokenExists();

        // Prepare the payload
        $data = [
            'method' => 'updateOrganization',
            'params' => [
                'id' => $params['organization_id'],
                'name' => $params['organization_name'],
                'packageid' => (int) $params['package'],
                'params' => [
                    'hidePassInEmail' => $params['dont_send_user_credentials'],
                ],
            ],
        ];

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to update organization");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        // Handle empty response
        if (!$response) {
            return ['success' => true, 'message' => 'Organization updated successfully'];
        }

        return $response['result'];
    }

    public function getOrganization($org_id)
    {
        $this->ensureApiTokenExists();

        // Prepare the payload
        $data = array(
            'method' => 'getOrganization',
            'params' => array(
                'id' => $org_id,
            )
        );

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to fetch organization");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        // Transform the result into OrganizationDTO
        return RingotelOrganizationDTO::fromArray($response['result']);
    }

    public function deleteOrganization($org_id)
    {
        $this->ensureApiTokenExists();
        // Prepare the payload
        $data = [
            'method' => 'deleteOrganization',
            'params' => [
                'id' => $org_id,
            ],
        ];

        // Send the request
        $response = Http::ringotel() // Ensure `ringotel` is configured in the HTTP client
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function ($response) {
                throw new \Exception("Failed to delete organization: {$response->body()}");
            })
            ->json();

        // Check for errors in the response
        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        // Handle empty response
        if (!$response) {
            return ['success' => true, 'message' => 'Organization and its connections were successfully deleted.'];
        }

        return $response['result'];
    }


    public function getOrganizations()
    {
        $this->ensureApiTokenExists();
        $data = array(
            'method' => 'getOrganizations',
        );

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to retrieve organizations");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        return collect($response['result'])->map(function ($item) {
            return RingotelOrganizationDTO::fromArray($item);
        });
    }

    public function createConnection($params)
    {
        $this->ensureApiTokenExists();

        // Build codecs array based on enabled flags, preserving user order
        $codecs = [];
        
        if (!empty($params['codecs']) && is_array($params['codecs'])) {
            foreach ($params['codecs'] as $codecItem) {
                if (!empty($codecItem['enabled'])) {
                    $codecName = $codecItem['name'];
                    if (strtolower($codecName) === 'opus') {
                        $codecName = 'OPUS';
                    }
                    $codecs[] = [
                        'codec' => $codecName,
                        'frame' => (int) ($codecItem['frame'] ?? 20),
                    ];
                }
            }
        }
        // Build data array
        $data = array(
            'method' => 'createBranch',
            'params' => array(
                "orgid" => $params['org_id'],
                'name' => $params['connection_name'],
                'address' => $params['domain'] . ":" . $params['port'],
                'provision' => array(
                    'protocol' => $params['protocol'],
                    'noverify' => $params['dont_verify_server_certificate'],
                    'nosrtp' => $params['disable_srtp'],
                    'multitenant' => $params['multitenant'],
                    'norec' => !$params['allow_call_recording'],
                    // 'internal' => false,
                    // 'sms' => false,
                    'maxregs' => (int) $params['max_registrations'],
                    // 'private' => false,
                    'dtmfmode' => 'rfc2833',
                    'regexpires' => (int) $params['registration_ttl'],
                    'proxy' => array(
                        'paddr' => $params['proxy'],
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
                    '1push' => $params['one_push'],
                    'noptions' => !$params['show_call_settings'],
                    'norecents' => $params['disable_iphone_recents'],
                    'novideo' => !$params['allow_video_calls'],
                    'nostates' => !$params['allow_state_change'],
                    'nochats' => !$params['allow_internal_chat'],
                    'calldelay' => $params['call_delay'],
                    'pcdelay' => $params['desktop_app_delay'],
                    'features' => $params['pbx_features'] ? 'pbx' : '',
                    "speeddial" => array(
                        [
                            'number' => $params['voicemail_extension'],
                            'title' => 'Voicemail'
                        ]
                    ),
                    'vmail' => [
                        'ext' => $params['voicemail_extension'],
                        'name' => 'Voicemail',
                        'mess' => 'You have a new message',
                        'off' => '',
                        'on' => '',
                        'spref' => ''
                    ],
                    'dnd' => [
                        'off' => $params['dnd_on_code'] ?? '',
                        'on' => $params['dnd_off_code'] ?? ''
                    ],
                    'forwarding' => [
                        'cfuon' => '',
                        'cfboff' => '',
                        'cfon' => $params['cf_on_code'] ?? '',
                        'cfbon' => '',
                        'cfuoff' => '',
                        'cfoff' => $params['cf_off_code'] ?? ''
                    ],

                    'codecs' => $codecs,
                    'app' => array(
                        'g711' => !$params['app_opus_codec'],

                    ),

                )
            )
        );

        // logger($data);

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to create connection");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        return $response['result'];
    }

    public function updateConnection($params)
    {
        $this->ensureApiTokenExists();
        // Build codecs array based on enabled flags, preserving user order
        $codecs = [];
        
        if (!empty($params['codecs']) && is_array($params['codecs'])) {
            foreach ($params['codecs'] as $codecItem) {
                if (!empty($codecItem['enabled'])) {
                    $codecName = $codecItem['name'];
                    if (strtolower($codecName) === 'opus') {
                        $codecName = 'OPUS';
                    }
                    $codecs[] = [
                        'codec' => $codecName,
                        'frame' => (int) ($codecItem['frame'] ?? 20),
                    ];
                }
            }
        }

        // Build data array
        $data = array(
            'method' => 'updateBranch',
            'params' => array(
                "id" => $params['conn_id'],
                "orgid" => $params['org_id'],
                'name' => $params['connection_name'],
                'address' => $params['domain'] . ":" . $params['port'],
                'provision' => array(
                    'protocol' => $params['protocol'],
                    'noverify' => $params['dont_verify_server_certificate'],
                    'nosrtp' => $params['disable_srtp'],
                    'multitenant' => $params['multitenant'],
                    'norec' => !$params['allow_call_recording'],
                    // 'internal' => false,
                    // 'sms' => false,
                    'maxregs' => (int) $params['max_registrations'],
                    // 'private' => false,
                    'dtmfmode' => 'rfc2833',
                    'regexpires' => (int) $params['registration_ttl'],
                    'proxy' => array(
                        'paddr' => $params['proxy'],
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
                    '1push' => $params['one_push'],
                    'noptions' => !$params['show_call_settings'],
                    'norecents' => $params['disable_iphone_recents'],
                    'novideo' => !$params['allow_video_calls'],
                    'nostates' => !$params['allow_state_change'],
                    'nochats' => !$params['allow_internal_chat'],
                    'calldelay' => $params['call_delay'],
                    'pcdelay' => $params['desktop_app_delay'],
                    'features' => $params['pbx_features'] ? 'pbx' : '',
                    "speeddial" => array(
                        [
                            'number' => $params['voicemail_extension'],
                            'title' => 'Voicemail'
                        ]
                    ),
                    'vmail' => [
                        'ext' => $params['voicemail_extension'],
                        'name' => 'Voicemail',
                        'mess' => 'You have a new message',
                        'off' => '',
                        'on' => '',
                        'spref' => ''
                    ],
                    'dnd' => [
                        'off' => $params['dnd_on_code'],
                        'on' => $params['dnd_off_code']
                    ],
                    'forwarding' => [
                        'cfuon' => '',
                        'cfboff' => '',
                        'cfon' => $params['cf_on_code'],
                        'cfbon' => '',
                        'cfuoff' => '',
                        'cfoff' => $params['cf_off_code']
                    ],

                    'codecs' => $codecs,
                    'app' => array(
                        'g711' => !$params['app_opus_codec'],

                    ),

                )
            )
        );

        // logger($data);

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to update connection");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        // Handle empty response
        if (!$response) {
            return ['success' => true, 'message' => 'Connection updated successfully'];
        }

        return $response['result'];
    }

    public function deleteConnection($params)
    {
        $this->ensureApiTokenExists();
        $data = array(
            'method' => 'deleteBranch',
            'params' => array(
                'id' => $params['conn_id'],
                'orgid' => $params['org_id'],
            )
        );

        // logger($data);

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to delete connection");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        return $response['result'];
    }

    public function getConnections($org_id)
    {
        $this->ensureApiTokenExists();
        $data = array(
            'method' => 'getBranches',
            'params' => array(
                'orgid' => $org_id,
            )
        );

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function () {
                throw new \Exception("Unable to retrieve connections");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        return collect($response['result'])->map(function ($item) {
            return RingotelConnectionDTO::fromArray($item);
        });
    }

    public function getUsersByOrgId($orgId)
    {
        $this->ensureApiTokenExists();

        $data = [
            'method' => 'getUsers',
            'params' => array(
                'orgid' => $orgId,
            )
        ];

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) use ($orgId) {
                throw new \Exception("Unable to retrieve users for organization ID: $orgId");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }
        // return $response['result'];

        return collect($response['result'])->map(function ($item) {
            return RingotelUserDTO::fromArray($item);
        });
    }

    public function getUsers($org_id, $conn_id)
    {
        $this->ensureApiTokenExists();

        $data = array(
            'method' => 'getUsers',
            'params' => array(
                'orgid' => $org_id,
                'branchid' => $conn_id,
            )
        );

        $response = Http::ringotel()
            //->dd()
            ->timeout(30)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) use ($org_id) {
                throw new \Exception("Unable to retrieve users for organization ID: $org_id");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }

        return collect($response['result'])->map(function ($item) {
            return RingotelUserDTO::fromArray($item);
        });
    }

    function createUser($params)
    {
        $this->ensureApiTokenExists();

        $data = array(
            'method' => 'createUser',
            'params' => array(
                'orgid' => $params['org_id'],
                'branchid' => $params['conn_id'],
                'name' => $params['name'],
                'email' => $params['email'],
                'extension' => $params['ext'],
                'username' => $params['username'],
                // 'domain' => $params['domain'],
                'authname' => $params['authname'],
                'password' => $params['password'],
                'status' => $params['status'],
                'noemail' => $params['noemail'] ?? true
            )
        );

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                throw new \Exception("Unable to create user.");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }
        // return $response['result'];

        return $response['result'];
    }

    // Update mobile app user via Ringotel API call
    function updateUser($params)
    {
        $data = array(
            'method' => 'updateUser',
            'params' => array(
                'id' => $params['user_id'],
                'orgid' => $params['org_id'],
                'name' => $params['name'],
                'email' => $params['email'],
                'extension' => $params['ext'],
                'username' => $params['ext'],
                'authname' => $params['ext'],
                'password' => $params['password'],
                'status' => $params['status'],
                'noemail' => $params['no_email'] ?? true
            )
        );

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                throw new \Exception("Unable to create user.");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }
        // return $response['result'];

        return $response['result'];
    }



    function deleteUser($params)
    {
        $this->ensureApiTokenExists();

        $data = array(
            'method' => 'deleteUser',
            'params' => array(
                'id' => $params['user_id'],
                'orgid' => $params['org_id'],
            )
        );

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                throw new \Exception("Unable to create user.");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }
        // return $response['result'];

        return $response['result'];
    }


    // Reset password for mobile app user via Ringotel API call
    function resetPassword($params)
    {
        $data = array(
            'method' => 'resetUserPassword',
            'params' => array(
                'id' => $params['user_id'],
                'orgid' => $params['org_id'],
                'noemail' => $params['noemail'] ?? true
            )
        );

        $response = Http::ringotel()
            //->dd()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => "Unable to reset password",
                    ],
                ])->getData(true);
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }
        // return $response['result'];

        return $response['result'];
    }


    function deactivateUser($params)
    {
        $this->ensureApiTokenExists();

        $data = array(
            'method' => 'deactivateUser',
            'params' => array(
                'id' => $params['user_id'],
                'orgid' => $params['org_id'],
            )
        );

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                throw new \Exception("Unable to create user.");
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }
        // return $response['result'];

        return $response['result'];
    }


    public function getRegions()
    {
        $this->ensureApiTokenExists();

        $data = [
            'method' => 'getRegions',
        ];

        $response = Http::ringotel()
            ->timeout($this->timeout)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                throw new \Exception("Unable to retrieve regions: " . $response->body());
            })
            ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }

        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred while fetching regions");
        }

        return collect($response['result'])->map(function ($item) {
            return RingotelRegionDTO::fromArray($item);
        });
    }


    public function matchLocalDomains($organizations)
    {

        $orgs = DB::table('v_domain_settings')
            ->join('v_domains', 'v_domains.domain_uuid', '=', 'v_domain_settings.domain_uuid')
            ->where('domain_setting_category', 'app shell')
            ->where('domain_setting_subcategory', 'org_id')
            ->get([
                'v_domain_settings.domain_uuid',
                'domain_setting_value AS org_id',
                'domain_name',
                'domain_description',
            ]);

        $orgArray = $organizations->map(function ($organization) use ($orgs) {
            foreach ($orgs as $org) {
                if ($organization->id == $org->org_id) {
                    $organization->domain_uuid = $org->domain_uuid;
                    $organization->domain_name = $org->domain_name;
                    $organization->domain_description = $org->domain_description;
                }
            }
            return $organization;
        });

        return $orgArray;
    }

    public function getOrgIdByDomainUuid($domain_uuid)
    {
        return DomainSettings::where([
            ['domain_uuid', '=', $domain_uuid],
            ['domain_setting_category', '=', 'app shell'],
            ['domain_setting_subcategory', '=', 'org_id'],
            ['domain_setting_enabled', '=', true],
        ])->value('domain_setting_value');
    }

    public function getUserRegistrationsHistory($orgId, $userId, $beginTimestamp, $endTimestamp)
    {
        $this->ensureApiTokenExists();

        // Prepare request payload
        $data = [
            'method' => 'getUserRegistrationsHistory',
            'params' => [
                'orgid' => $orgId,
                'userid' => $userId,
                'begin' => $beginTimestamp,
                'end' => $endTimestamp,
            ],
        ];

        try {
            $response = Http::ringotel()
                ->timeout($this->timeout)
                ->withBody(json_encode($data), 'application/json')
                ->post('/')
                ->json();


            if (isset($response['error'])) {
                // logger("Ringotel API error: " . $response['error']['message'], ['error' => $response['error']]);
                throw new \Exception($response['error']['message']);
            }

            if (!isset($response['result']) || empty($response['result'])) {
                // logger("No registration history returned for user ID: {$userId} in org ID: {$orgId}");
                return [];
            }

            return $response['result'];
        } catch (\Exception $e) {
            // logger("Failed to retrieve user registration history from Ringotel API", ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getStaleUsers($staleThresholdDays)
    {
        // Generate timestamps (milliseconds since epoch)
        $endTimestamp = now()->timestamp * 1000; // Current time in milliseconds
        $beginTimestamp = now()->subDays($staleThresholdDays)->timestamp * 1000; // Convert days to milliseconds

        // Fetch all organizations
        $organizations = $this->getOrganizations();

        if (!$organizations || $organizations->isEmpty()) {
            logger("Failed to fetch organizations from Ringotel API.");
            return [];
        }

        // Array to hold stale users
        $staleUsers = [];

        // Fetch all MobileAppUsers and store them in an associative array
        $local_mobile_app_users = MobileAppUsers::select('user_id', 'exclude_from_stale_report')->get()
            ->keyBy('user_id'); // Key by user_id for quick lookup


        // Loop through organizations and get users
        foreach ($organizations as $organization) {
            $orgId = $organization->id;
            $users = $this->getUsersByOrgId($orgId);

            foreach ($users as $user) {
                // Ignore unactivated users (-1) and park extensions (-2)
                if ($user->status != -1 && $user->status != -2) {

                    // Ensure the user exists in MobileAppUsers
                    if (!isset($local_mobile_app_users[$user->id])) {
                        continue; // Skip users not found in MobileAppUsers
                    }

                    // Skip users that should be excluded from the stale report
                    if ($local_mobile_app_users[$user->id]->exclude_from_stale_report) {
                        continue;
                    }

                    $history = $this->getUserRegistrationsHistory($orgId, $user->id, $beginTimestamp, $endTimestamp);

                    // If history is empty, user is stale
                    if (empty($history)) {
                        $staleUsers[] = [
                            'org_id' => $organization->id,
                            'org_name' => $organization->name,
                            'user_id' => $user->id,
                            'user_name' => $user->name,
                            'extension' => $user->extension,
                            'email' => $user->email ?? 'N/A',
                        ];
                    }
                }
            }
        }

        return $staleUsers;
    }

    public function message($params)
    {
        $this->ensureApiTokenExists();

        $response = Http::ringotel_api()
        ->withBody(json_encode([
            'method' => 'message',
            'params' => $params,
        ]), 'application/json')
        ->post('/')
        ->throw()
        ->json();

        if (isset($response['error'])) {
            throw new \Exception($response['error']['message']);
        }
    
        if (!isset($response['result'])) {
            throw new \Exception("An unknown error has occurred");
        }
        // return $response['result'];
    
        return $response['result'];
    }

}
