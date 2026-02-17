<?php

use App\Models\IvrMenus;
use App\Models\Dialplans;
use App\Models\Extensions;
use App\Models\Recordings;
use App\Models\RingGroups;
use App\Models\Voicemails;
use App\Models\MusicOnHold;
use App\Models\SipProfiles;
use Illuminate\Support\Str;
use App\Models\DeviceVendor;
use App\Models\MusicStreams;
use App\Models\DeviceProfile;
use App\Models\DomainSettings;
use App\Models\GatewaySetting;
use App\Models\PaymentGateway;
use App\Models\SwitchVariable;
use App\Models\DefaultSettings;
use libphonenumber\PhoneNumberUtil;
use App\Models\ProvisioningTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use libphonenumber\NumberParseException;

if (!function_exists('userCheckPermission')) {
    function userCheckPermission($permission)
    {
        $list = Session::get('permissions', false);
        if (!$list) {
            return false;
        }

        foreach ($list as $item) {
            if ($item->permission_name == $permission) {
                return true;
            }
        }
        return false;
    }
}

// Check if currenlty signed in user a superadmin
if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin()
    {
        foreach (Session::get('user.groups') as $group) {
            if ($group->group_name == "superadmin" && $group->group_level >= 80) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('getDefaultSetting')) {
    function getDefaultSetting($category, $subcategory)
    {
        $settings = Session::get('default_settings', false);

        if (!$settings) {
            return null;
        }

        foreach ($settings as $setting) {
            if (
                $setting['default_setting_category'] == $category &&
                $setting['default_setting_subcategory'] == $subcategory
            ) {
                return $setting['default_setting_value'];
            }
        }
        return null;
    }
}

if (!function_exists('getFusionPBXPreviousURL')) {
    function getFusionPBXPreviousURL($previous_url)
    {
        if (strpos($previous_url, "time_condition_edit.php")) {
            $url = substr($previous_url, 0, strpos(url()->previous(), "time_condition_edit.php")) . "time_conditions.php";
        } elseif (strpos($previous_url, "destination_edit.php")) {
            $url = substr($previous_url, 0, strpos(url()->previous(), "destination_edit.php")) . "destinations.php";
        } elseif (strpos($previous_url, "extension_edit.php")) {
            $url = substr($previous_url, 0, strpos(url()->previous(), "extension_edit.php")) . "extensions.php";
        } elseif (strpos($previous_url, "ring_group_edit.php")) {
            $url = substr($previous_url, 0, strpos(url()->previous(), "ring_group_edit.php")) . "ring_groups.php";
        } elseif (strpos($previous_url, "device_edit.php")) {
            $url = substr($previous_url, 0, strpos(url()->previous(), "device_edit.php")) . "devices.php";
        } elseif (strpos($previous_url, "dialplan_edit.php")) {
            $url = substr($previous_url, 0, strpos(url()->previous(), "dialplan_edit.php")) . "dialplans.php";
        } elseif (strpos($previous_url, "ivr_menu_edit.php")) {
            $url = substr($previous_url, 0, strpos(url()->previous(), "ivr_menu_edit.php")) . "ivr_menus.php";
        } elseif (strpos($previous_url, "voicemail_edit.php")) {
            $url = substr($previous_url, 0, strpos(url()->previous(), "voicemail_edit.php")) . "voicemails.php";
        } elseif (strpos($previous_url, "/extensions/")) {
            $url = substr($previous_url, 0, strpos(url()->previous(), "/extensions/")) . "/extensions";
        } elseif (strpos($previous_url, "/faxes/")) {
            $url = substr($previous_url, 0, strpos(url()->previous(), "/faxes/")) . "/faxes";
        } elseif (strpos($previous_url, "/voicemails/")) {
            $url = substr($previous_url, 0, strpos(url()->previous(), "/voicemails/")) . "/voicemails";
        } elseif (strpos($previous_url, "/contact-center/")) {
            $url = substr($previous_url, 0, strpos(url()->previous(), "/contact-center/")) . "/dashboard1";
        } else {
            $url = $previous_url;
        }
        return $url;
    }
}


// Set Status for mobile app user via Ringotel API call
if (!function_exists('appsSetStatus')) {
    function appsSetStatus($org_id, $user_id, $status)
    {
        $data = array(
            'method' => 'setUserStatus',
            'params' => array(
                'id' => $user_id,
                'orgid' => $org_id,
                'status' => $status,
            )
        );

        $response = Http::ringotel()
            //->dd()
            ->timeout(30)
            ->withBody(json_encode($data), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => "Unable to set new status",
                    ],
                ])->getData(true);
            })
            ->json();

        return $response;
    }
}


if (!function_exists('event_socket_create')) {
    function event_socket_create($host, $port, $password)
    {
        $esl = new event_socket;
        if ($esl->connect($host, $port, $password)) {
            return $esl->reset_fp();
        }
        return false;
    }
}

if (!function_exists('event_socket_request')) {
    function event_socket_request($fp, $cmd)
    {
        $esl = new event_socket($fp);
        $result = $esl->request($cmd);
        $esl->reset_fp();
        return $result;
    }
}

if (!function_exists('event_socket_request_cmd')) {
    function event_socket_request_cmd($cmd)
    {

        $esl = new event_socket;
        if (!$esl->connect(
            config('eventsocket.ip'),
            config('eventsocket.port'),
            config('eventsocket.password')
        )) {
            return false;
        }
        $response = $esl->request($cmd);

        $esl->close();
        return $response;
    }
}
if (!function_exists('outbound_route_to_bridge')) {
    function outbound_route_to_bridge($domain_uuid, $destination_number, array $channel_variables = null)
    {

        $destination_number = trim($destination_number);
        preg_match('/^[\*\+0-9]*$/', $destination_number, $matches, PREG_OFFSET_CAPTURE);
        if (count($matches) > 0) {
            //not found, continue to process the function
        } else {
            //not a number, brige_array and exit the function
            $bridge_array[0] = $destination_number;
            return $bridge_array;
        }

        //get the hostname
        $hostname = trim(event_socket_request_cmd('api switchname'));
        if (strlen($hostname) == 0) {
            $hostname = 'unknown';
        }

        $dialplans = Dialplans::where('dialplan_enabled', 'true')
            ->where('app_uuid', '8c914ec3-9fc0-8ab5-4cda-6c9288bdc9a3')
            ->where(function ($q) use ($domain_uuid) {
                $q->where('domain_uuid', $domain_uuid)
                    ->orWhere('domain_uuid', null);
            })
            ->where(function ($q) use ($hostname) {
                $q->where('hostname', $hostname)
                    ->orWhere('hostname', null);
            })
            ->get([
                'dialplan_uuid',
                'dialplan_continue',
                'dialplan_name'
            ]);

        // if (is_array($result) && @sizeof($result) != 0) {
        //     foreach ($result as &$row) {
        //         $dialplan_uuid = $row["dialplan_uuid"];
        //         $dialplan_detail_uuid = $row["dialplan_detail_uuid"];
        //         $outbound_routes[$dialplan_uuid][$dialplan_detail_uuid]["dialplan_detail_tag"] = $row["dialplan_detail_tag"];
        //         $outbound_routes[$dialplan_uuid][$dialplan_detail_uuid]["dialplan_detail_type"] = $row["dialplan_detail_type"];
        //         $outbound_routes[$dialplan_uuid][$dialplan_detail_uuid]["dialplan_detail_data"] = $row["dialplan_detail_data"];
        //         $outbound_routes[$dialplan_uuid]["dialplan_continue"] = $row["dialplan_continue"];
        //     }
        // }

        if ($dialplans->isEmpty()) {
            return;
        }

        $x = 0;
        foreach ($dialplans as $dialplan) {
            $condition_match = false;
            foreach ($dialplan->dialplan_details as $dialplan_detail) {
                if ($dialplan_detail['dialplan_detail_tag'] == "condition") {

                    if ($dialplan_detail['dialplan_detail_type'] == "destination_number") {
                        $pattern = '/' . $dialplan_detail['dialplan_detail_data'] . '/';
                        preg_match($pattern, $destination_number, $matches, PREG_OFFSET_CAPTURE);
                        if (count($matches) == 0) {
                            $condition_match = 'false';
                        } else {
                            $condition_match = 'true';
                            if (isset($matches[1])) {
                                $regex_match_1 = $matches[1][0];
                            }
                            if (isset($matches[2])) {
                                $regex_match_2 = $matches[2][0];
                            }
                            if (isset($matches[3])) {
                                $regex_match_3 = $matches[1][0];
                            }
                            if (isset($matches[4])) {
                                $regex_match_4 = $matches[4][0];
                            }
                            if (isset($matches[5])) {
                                $regex_match_5 = $matches[5][0];
                            }
                        }
                    } elseif ($dialplan_detail['dialplan_detail_type'] == "\${toll_allow}") {
                        $pattern = '/' . $dialplan_detail['dialplan_detail_data'] . '/';
                        preg_match($pattern, $channel_variables['toll_allow'], $matches, PREG_OFFSET_CAPTURE);
                        if (count($matches) == 0) {
                            $condition_match = 'false';
                        } else {
                            $condition_match = 'true';
                        }
                    }
                }
            }

            if ($condition_match == 'true') {
                foreach ($dialplan->dialplan_details as $dialplan_detail) {
                    // log::alert($dialplan_detail);
                    $dialplan_detail_data = $dialplan_detail['dialplan_detail_data'];
                    if ($dialplan_detail['dialplan_detail_tag'] == "action" && $dialplan_detail['dialplan_detail_type'] == "bridge" && $dialplan_detail_data != "\${enum_auto_route}") {
                        if (isset($regex_match_1)) {
                            $dialplan_detail_data = str_replace("\$1", $regex_match_1, $dialplan_detail_data);
                        }
                        if (isset($regex_match_2)) {
                            $dialplan_detail_data = str_replace("\$2", $regex_match_2, $dialplan_detail_data);
                        }
                        if (isset($regex_match_3)) {
                            $dialplan_detail_data = str_replace("\$3", $regex_match_3, $dialplan_detail_data);
                        }
                        if (isset($regex_match_4)) {
                            $dialplan_detail_data = str_replace("\$4", $regex_match_4, $dialplan_detail_data);
                        }
                        if (isset($regex_match_5)) {
                            $dialplan_detail_data = str_replace("\$5", $regex_match_5, $dialplan_detail_data);
                        }
                        $bridge_array[$x] = $dialplan_detail_data;
                        $x++;
                    }
                }

                if ($dialplan["dialplan_continue"] == "false") {
                    break;
                }
            }
        }

        return $bridge_array ?? [];
    }
}

if (!function_exists('getDestinationByCategory')) {
    /**
     * @deprecated Please consider to use Services/ActionsService instead
     */
    function getDestinationByCategory($category, $data = null)
    {
        $output = [];
        $selectedCategory = null;
        $selectedDestination = null;
        $rows = null;

        switch ($category) {
            case 'ringgroup':
                $rows = RingGroups::where('domain_uuid', Session::get('domain_uuid'))
                    ->where('ring_group_enabled', 'true')
                    //->whereNotIn('extension_uuid', [$extension->extension_uuid])
                    ->orderBy('ring_group_extension')
                    ->get();
                break;
            case 'dialplans':
                $rows = Dialplans::where('domain_uuid', Session::get('domain_uuid'))
                    ->where('dialplan_enabled', 'true')
                    ->where('dialplan_destination', 'true')
                    ->where('dialplan_number', '<>', '')
                    ->orderBy('dialplan_name')
                    ->get();
                break;
            case 'extensions':
                $rows = Extensions::where('domain_uuid', Session::get('domain_uuid'))
                    //->whereNotIn('extension_uuid', [$extension->extension_uuid])
                    ->orderBy('extension')
                    ->get();
                break;
            case 'ivrs':
                $rows = IvrMenus::where('domain_uuid', Session::get('domain_uuid'))
                    //->whereNotIn('extension_uuid', [$extension->extension_uuid])
                    ->orderBy('ivr_menu_extension')
                    ->get();
                break;
            case 'recordings':
                $rows = Recordings::where('domain_uuid', Session::get('domain_uuid'))
                    //->whereNotIn('extension_uuid', [$extension->extension_uuid])
                    ->orderBy('recording_name')
                    ->get();
                break;
            case 'voicemails':
                $rows = Voicemails::where('domain_uuid', Session::get('domain_uuid'))
                    ->where('voicemail_enabled', 'true')
                    ->orderBy('voicemail_id')
                    ->get();
                break;
            case 'others':
                $rows = [
                    [
                        'id' => sprintf('*98 XML %s', Session::get('domain_name')),
                        'label' => 'Check Voicemail'
                    ],
                    [
                        'id' => sprintf('*411 XML %s', Session::get('domain_name')),
                        'label' => 'Company Directory'
                    ],
                    [
                        'id' => 'hangup:',
                        'label' => 'Hangup'
                    ],
                    [
                        'id' => sprintf('*732 XML %s', Session::get('domain_name')),
                        'label' => 'Record'
                    ]
                ];
                break;
            default:
        }

        if ($rows) {
            foreach ($rows as $row) {
                switch ($category) {
                    case 'ringgroup':
                        $id = sprintf('%s XML %s', $row->ring_group_extension, Session::get('domain_name'));
                        $label = $row->ring_group_extension . " - " . $row->ring_group_name;
                        $app_name = "Ring Group";
                        break;
                    case 'extensions':
                        $id = sprintf('%s XML %s', $row->extension, Session::get('domain_name'));
                        $label = $row->extension . " - " . $row->effective_caller_id_name;
                        $app_name = "Extension";
                        break;
                    case 'voicemails':
                        $id = sprintf('*99%s XML %s', $row->voicemail_id, Session::get('domain_name'));
                        $label = $row->voicemail_id;
                        if ($row->extension) {
                            $label .= " - " . $row->extension->effective_caller_id_name;
                        } elseif ($row->voicemail_description != '') {
                            $label .= " - " . $row->voicemail_description;
                        }
                        $app_name = "Voicemail";
                        break;
                    case 'ivrs':
                        $id = sprintf('%s XML %s', $row->ivr_menu_extension, Session::get('domain_name'));
                        $label = $row->ivr_menu_extension . " - " . $row->ivr_menu_name;
                        $app_name = "Auto Receptionist";
                        break;
                    case 'recordings':
                        $id = sprintf('streamfile.lua %s', $row->recording_filename);
                        $label = $row->recording_name;
                        $app_name = "Recordings";
                        break;
                    case 'others':
                        $id = $row['id'];
                        $label = $row['label'];
                        $app_name = "Miscellaneous";
                        break;
                    default:
                        break; // Skip unknown categories
                }

                if (isset($id)) {
                    // Check if the id matches the data
                    if ($id == $data || 'transfer:' . $id == $data) {
                        $selectedCategory = $category;
                        $selectedDestination = $id;
                    }

                    // Add to the output array
                    $output[] = [
                        'id' => $id,
                        'label' => $label,
                        'app_name' => $app_name,
                    ];
                }
            }
        }

        return [
            'selectedCategory' => $selectedCategory,
            'selectedDestination' => $selectedDestination,
            'list' => $output
        ];
    }
}

if (!function_exists('getTimeoutDestinations')) {
    /**
     * @deprecated Please consider to use Services/ActionsService instead
     */
    function getTimeoutDestinations($domain = null)
    {
        if ($domain !== null) {
            logger('getTimeoutDestinations does not support $domain argument yet. ' . __FILE__);
        }
        // TODO: refactor the getDestinationByCategory function to use $domain        // TODO: refactor the getDestinationByCategory function to use $domain
        $output = [
            'categories' => [],
            'targets' => [],
        ];
        foreach (
            [
                'ringgroup',
                'dialplans',
                'extensions',
                'timeconditions',
                'voicemails',
                'ivrs',
                'others'
            ] as $i => $category
        ) {
            $data = getDestinationByCategory($category)['list'];
            $data = getDestinationByCategory($category)['list'];
            foreach ($data as $b => $d) {
                $output['categories'][$category] = [
                    'name' => $d['app_name'],
                    'value' => $category
                ];

                //[$i] = ;
                //$output['categories'][$i] = ;
                $output['targets'][$category][] = [
                    'name' => $d['label'],
                    'value' => $d['id']
                ];
            }
        }

        return $output;
    }
}

if (!function_exists('getTimeoutDestinationsLabels')) {
    /**
     * @deprecated Please consider to use Services/ActionsService instead
     */
    function getTimeoutDestinationsLabels(array $actions, $domain = null): array
    {
        $destinations = getTimeoutDestinations($domain);
        $output = [];
        foreach ($actions as $action) {
            foreach ($destinations["targets"] as $category => $values) {
                foreach ($values as $data) {
                    if ($data["value"] == $action['destination_data']) {
                        $output[] = $destinations["categories"][(string) $category]["name"] . ' ' . $data["name"];
                    }
                }
            }
        }
        return $output;
    }
}

// * depreciated
if (!function_exists('get_registrations')) {
    function get_registrations($show = null)
    {
        //create the event socket connection
        $fp = event_socket_create(
            config('eventsocket.ip'),
            config('eventsocket.port'),
            config('eventsocket.password')
        );

        $sip_profiles = SipProfiles::where('sip_profile_enabled', 'true')
            ->get();

        $registrations = array();
        $id = 0;
        foreach ($sip_profiles as $sip_profile) {
            $cmd = "api sofia xmlstatus profile '" . $sip_profile['sip_profile_name'] . "' reg";
            $xml_response = trim(event_socket_request($fp, $cmd));
            if (function_exists('iconv')) {
                $xml_response = iconv("utf-8", "utf-8//IGNORE", $xml_response);
            }
            $xml_response = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $xml_response);
            if ($xml_response == "Invalid Profile!") {
                $xml_response = "<error_msg>" . 'Error' . "</error_msg>";
            }
            $xml_response = str_replace("<profile-info>", "<profile_info>", $xml_response);
            $xml_response = str_replace("</profile-info>", "</profile_info>", $xml_response);
            $xml_response = str_replace("&lt;", "", $xml_response);
            $xml_response = str_replace("&gt;", "", $xml_response);
            if (strlen($xml_response) > 101) {
                try {
                    $xml = new SimpleXMLElement($xml_response);
                } catch (Exception $e) {
                    echo basename(__FILE__) . "<br />\n";
                    echo "line: " . __line__ . "<br />\n";
                    echo "error: " . $e->getMessage() . "<br />\n";
                    //echo $xml_response;
                    exit;
                }
                $array = json_decode(json_encode($xml), true);
            }
            //Log::alert($array);
            //normalize the array
            if (isset($array) && !isset($array['registrations']['registration'][0])) {
                $row = $array['registrations']['registration'];
                unset($array['registrations']['registration']);
                $array['registrations']['registration'][0] = $row;
            }

            //set the registrations array
            if (isset($array)) {
                foreach ($array['registrations']['registration'] as $row) {

                    //build the registrations array
                    //$registrations[0] = $row;
                    $user_array = explode('@', $row['user']);
                    $registrations[$id]['user'] = $row['user'] ?: '';
                    $registrations[$id]['call-id'] = $row['call-id'] ?: '';
                    $registrations[$id]['contact'] = $row['contact'] ?: '';
                    $registrations[$id]['sip-auth-user'] = $row['sip-auth-user'] ?: '';
                    $registrations[$id]['agent'] = $row['agent'] ?: '';
                    $registrations[$id]['host'] = $row['host'] ?: '';
                    $registrations[$id]['network-port'] = $row['network-port'] ?: '';
                    $registrations[$id]['sip-auth-realm'] = $row['sip-auth-realm'] ?: '';
                    $registrations[$id]['mwi-account'] = $row['mwi-account'] ?: '';
                    $registrations[$id]['status'] = $row['status'] ?: '';
                    $registrations[$id]['ping-time'] = $row['ping-time'] ?: '';
                    $registrations[$id]['sip_profile_name'] = $sip_profile['sip_profile_name'];

                    //get network-ip to url or blank
                    if (isset($row['network-ip'])) {
                        $registrations[$id]['network-ip'] = $row['network-ip'];
                    } else {
                        $registrations[$id]['network-ip'] = '';
                    }

                    //get the LAN IP address if it exists replace the external ip
                    $call_id_array = explode('@', $row['call-id']);
                    if (isset($call_id_array[1])) {
                        $agent = $row['agent'];
                        $lan_ip = $call_id_array[1];
                        if (false !== stripos($agent, 'grandstream')) {
                            $lan_ip = str_ireplace(
                                array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'),
                                array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9'),
                                $lan_ip
                            );
                        } elseif (1 === preg_match('/\ACL750A/', $agent)) {
                            //required for GIGASET Sculpture CL750A puts _ in it's lan ip account
                            $lan_ip = preg_replace('/_/', '.', $lan_ip);
                        }
                        $registrations[$id]['lan-ip'] = $lan_ip;
                    } else {
                        if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $row['contact'], $ip_match)) {
                            $lan_ip = preg_replace('/_/', '.', $ip_match[0]);
                            $registrations[$id]['lan-ip'] = "$lan_ip";
                        } else {
                            $registrations[$id]['lan-ip'] = '';
                        }
                    }

                    //remove unrelated domains
                    if (!userCheckPermission('registration_all') || $show != 'all') {
                        if ($registrations[$id]['sip-auth-realm'] == Session::get('domain_name')) {
                        } else {
                            if ($user_array[1] == Session::get('domain_name')) {
                            } else {
                                unset($registrations[$id]);
                            }
                        }
                    }

                    //increment the array id
                    $id++;
                }

                unset($array);
            }
        }
        return $registrations;
    }
}




if (!function_exists('pr')) {
    function pr($arr)
    {
        echo '<pre>';
        print_r($arr);
        echo '</pre>';
    }
}

if (!function_exists('setDefaultS3')) {
    function setDefaultS3($arr) {}
}

if (!function_exists('getCredentialKey')) {
    function getCredentialKey($string)
    {
        switch ($string) {
            case 'region':
                return 'region';
            case 'secret_key':
                return 'secret';
            case 'bucket_name':
                return 'bucket';
            case 'access_key':
                return 'key';
            default:
                return $string;
        }
    }
}


if (!function_exists('getSignedURL')) {
    function getSignedURL($s3Client, $bucket, $key)
    {
        //  $s3Client = new Aws\S3\S3Client($sharedConfig);

        $cmd = $s3Client->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key' => $key
        ]);

        $request = $s3Client->createPresignedRequest($cmd, '+20 minutes');
        $presignedUrl = (string) $request->getUri();
        return $presignedUrl;
    }
}

if (!function_exists('arr_to_map')) {
    function arr_to_map(&$arr)
    {
        if (is_array($arr)) {
            $map = array();
            foreach ($arr as &$val) {
                $map[$val] = true;
            }
            return $map;
        }
        return false;
    }
}

if (!function_exists('get_local_time_zone')) {
    /**
     * Get tenant's local time zone or return the system default setting
     *
     * @return string
     */
    function get_local_time_zone($domain_uuid = null)
    {
        if (!$domain_uuid) {
            $domain_uuid = session('domain_uuid');
        }
        $cacheKey = "{$domain_uuid}_timeZone";

        return Cache::remember($cacheKey, 86400, function () use ($domain_uuid) {
            return get_domain_setting('time_zone', $domain_uuid) ?? 'UTC';
        });
    }
}


if (!function_exists('get_domain_setting')) {
    /**
     * Get requested domain setting or fallback to default
     *
     * @return DomainSettings $setting
     */
    function get_domain_setting($setting_name, $domain_uuid = null)
    {
        if (!$domain_uuid) {
            $domain_uuid = session('domain_uuid');
        }

        $setting = DomainSettings::where('domain_uuid', $domain_uuid)
            ->where('domain_setting_subcategory', $setting_name)
            ->where('domain_setting_enabled', 'true')
            ->first();

        if (isset($setting)) {
            return $setting->domain_setting_value;
        }

        $setting = DefaultSettings::where('default_setting_subcategory', $setting_name)
            ->where('default_setting_enabled', 'true')
            ->first();

        if (isset($setting)) {
            return $setting->default_setting_value;
        }

        //Otherwise
        return null;
    }
}

if (!function_exists('generate_password')) {
    /**
     * Generate a secure password
     *
     * @return string
     */
    function generate_password()
    {
        $characters = str_split('!^$%*?.');
        $random_keys = array_rand($characters, 3);
        $random_characters = array();

        foreach ($random_keys as $key) {
            $random_characters[] = $characters[$key];
        }
        $random_string = Str::random(25);
        foreach ($random_characters as $character) {
            $random_string = substr_replace($random_string, $character, mt_rand(0, strlen($random_string)), 1);
        }

        $password = $random_string;
        return $password;
    }
}

if (!function_exists('formatPhoneNumber')) {
    function formatPhoneNumber($phoneNumber, $countryCode = 'US', $format = PhoneNumberFormat::NATIONAL)
    {
        // If it starts with +1 (US E.164), normalize to national format
        if (preg_match('/^\+1\d{10}$/', $phoneNumber)) {
            $phoneNumberUtil = PhoneNumberUtil::getInstance();
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($phoneNumber, 'US');
                return $phoneNumberUtil->format($phoneNumberObject, $format);
            } catch (NumberParseException $e) {
                return $phoneNumber; // fallback
            }
        }

        // If truly international (+ but not +1) or 011-prefixed, keep as-is
        if (preg_match('/^\s*(\+|011)/', $phoneNumber) && !preg_match('/^\+1\d{10}$/', $phoneNumber)) {
            return $phoneNumber;
        }

        // Default: parse and format
        $phoneNumberUtil = PhoneNumberUtil::getInstance();
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($phoneNumber, $countryCode);
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                return $phoneNumberUtil->format($phoneNumberObject, $format);
            }
        } catch (NumberParseException $e) {
            // ignore and fallback
        }

        return $phoneNumber;
    }
}

if (!function_exists('debugEloquentSqlWithBindings')) {
    function debugEloquentSqlWithBindings($query)
    {
        return vsprintf(
            str_replace('?', '%s', $query->toSql()),
            collect($query->getBindings())->map(function ($binding) {
                return is_numeric($binding) ? $binding : "'{$binding}'";
            })->toArray()
        );
    }
}

/**
 * Return legacy filesystem templates + DB-backed templates
 * [
 *   ['name' => 'polycom/Poly_VVX',      'value' => 'polycom/Poly_VVX'],        // legacy (path)
 *   ['name' => 'polycom/Poly_VVX',      'value' => '0d2e...-uuid'],            // DB default (latest)
 *   ['name' => 'polycom/ACME Custom 1', 'value' => 'a1b2...-uuid'],            // DB custom (domain/global)
 * ]
 */
if (!function_exists('getVendorTemplateCollection')) {
    function getVendorTemplateCollection(): array
    {
        $vendors = DeviceVendor::where('enabled', 'true')->orderBy('name')->get();
        $templates = [];

        // 1) Legacy filesystem (unchanged)
        $legacyBase = public_path('resources/templates/provision');
        foreach ($vendors as $vendor) {
            $vname = $vendor->name;
            $vdir  = $legacyBase . '/' . $vname;
            if (!is_dir($vdir)) continue;

            foreach (scandir($vdir) as $dir) {
                if ($dir === '.' || $dir === '..' || $dir[0] === '.') continue;
                if (!is_dir($vdir . '/' . $dir)) continue;

                $templates[] = [
                    'name'  => $vname . '/' . $dir,
                    'value' => $vname . '/' . $dir,   // legacy value is the path-like string
                ];
            }
        }

        $domainUuid = $domainUuid ?? session('domain_uuid');

        // limit DB query to enabled vendors (by name, lowercase match)
        $vendorNames = $vendors->pluck('name')->map(fn($n) => strtolower($n))->all();

        $rows = ProvisioningTemplate::query()
            ->select('template_uuid', 'vendor', 'name', 'type', 'version', 'revision', 'domain_uuid', 'created_at')
            ->whereIn('vendor', $vendorNames)
            ->where(function ($q) use ($domainUuid) {
                // defaults are global (domain_uuid NULL); customs can be global or domain-scoped
                $q->whereNull('domain_uuid');
                if ($domainUuid) {
                    $q->orWhere('domain_uuid', $domainUuid);
                }
            })
            ->orderBy('vendor')
            ->orderBy('name')
            ->orderByDesc('created_at')
            ->get();

        // latest DEFAULT per (vendor,name)
        $latestDefaults = $rows->where('type', 'default')
            ->groupBy(fn($r) => $r->vendor . '|' . $r->name)
            ->map->first();

        foreach ($latestDefaults as $r) {
            $templates[] = [
                'name'  => "{$r->vendor}/{$r->name} v{$r->version}",
                'value' => (string) $r->template_uuid,
            ];
        }

        // include each CUSTOM row (unique by template_uuid)
        foreach ($rows->where('type', 'custom') as $r) {
            $templates[] = [
                'name'  => "{$r->vendor}/{$r->name}",
                'value' => (string) $r->template_uuid,
            ];
        }

        // Sort alphabetically (case-insensitive, natural)
        return collect($templates)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}

if (!function_exists('getProfileCollection')) {
    function getProfileCollection(string $domain = null): array
    {
        $profilesCollection = DeviceProfile::where('device_profile_enabled', 'true');
        if ($domain) {
            $profilesCollection->where('domain_uuid', $domain);
        }
        $profilesCollection = $profilesCollection->orderBy('device_profile_name')->get();

        $profiles = [];
        foreach ($profilesCollection as $profile) {
            $profiles[] = [
                'name' => $profile->device_profile_name,
                'value' => $profile->device_profile_uuid
            ];
        }
        unset($profilesCollection, $profile);
        return $profiles;
    }
}

if (!function_exists('getRingBackTonesCollection')) {
    function getRingBackTonesCollection(string $domain = null): array
    {
        $musicOnHold = [];
        $musicOnHoldCollection = MusicOnHold::query();
        if ($domain) {
            $musicOnHoldCollection->where('domain_uuid', $domain)
                ->orWhere('domain_uuid', null);
        }
        $musicOnHoldCollection = $musicOnHoldCollection->orderBy('music_on_hold_name')->get()->unique('music_on_hold_name');
        foreach ($musicOnHoldCollection as $item) {
            $musicOnHold[] = [
                'name' => $item->music_on_hold_name,
                'value' => 'local_stream://' . $item->music_on_hold_name
            ];
        }

        $recordings = [];
        $recordingsCollection = Recordings::query()
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name'); // Select only the fields you need from the domain
            }])
            ->select('domain_uuid', 'recording_filename', 'recording_name');
        if ($domain) {
            $recordingsCollection->where('domain_uuid', $domain);
        }
        $recordingsCollection = $recordingsCollection->orderBy('recording_name')->get();
        $recording_path = DefaultSettings::where('default_setting_category', 'switch')
            ->where('default_setting_subcategory', 'recordings')
            ->where('default_setting_enabled', true)
            ->value('default_setting_value');

        // logger($recordingsCollection);
        foreach ($recordingsCollection as $item) {
            $recordings[] = [
                'name' => $item->recording_name,
                'value' => $recording_path . '/' . $item->domain->domain_name . '/' . $item->recording_filename
            ];
        }
        // logger($recordings);

        $ringtonesCollection = SwitchVariable::where('var_category', 'Ringtones')
            ->where('var_enabled', 'true')
            ->orderBy('var_name')
            ->select('var_uuid', 'var_name', 'var_value')
            ->get();
        $ringtones = [];
        foreach ($ringtonesCollection as $item) {
            $ringtones[] = [
                'name' => $item->var_name,
                'value' => '${' . $item->var_name . '}'
            ];
        }

        $streamsCollection = MusicStreams::where('stream_enabled', 'true')
            ->orderBy('stream_name')
            ->select('stream_uuid', 'stream_name', 'stream_location');
        if ($domain) {
            $streamsCollection->where('domain_uuid', $domain)
                ->orWhere('domain_uuid', null);
        }
        $streamsCollection = $streamsCollection->get();
        $streams = [];
        foreach ($streamsCollection as $item) {
            $streams[] = [
                'name' => $item->stream_name,
                'value' => $item->stream_location
            ];
        }

        unset($musicOnHoldCollection, $recordingsCollection, $ringtonesCollection, $item);
        return [
            'Music on Hold' => $musicOnHold,
            'Recordings' => $recordings,
            'Ringtones' => $ringtones,
            'Streams' => $streams,
        ];
    }
}

if (!function_exists('getMusicOnHoldCollection')) {
    function getMusicOnHoldCollection(string $domain = null): array
    {
        $musicOnHold = [];
        $musicOnHoldCollection = MusicOnHold::query();
        if ($domain) {
            $musicOnHoldCollection->where('domain_uuid', $domain)
                ->orWhere('domain_uuid', null);
        }
        $musicOnHoldCollection = $musicOnHoldCollection->orderBy('music_on_hold_name')->get()->unique('music_on_hold_name');
        foreach ($musicOnHoldCollection as $item) {
            $musicOnHold[] = [
                'label' => $item->music_on_hold_name,
                'value' => 'local_stream://' . $item->music_on_hold_name
            ];
        }

        $recordings = [];
        $recordingsCollection = Recordings::query()
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name'); // Select only the fields you need from the domain
            }])
            ->select('domain_uuid', 'recording_filename', 'recording_name');
        if ($domain) {
            $recordingsCollection->where('domain_uuid', $domain);
        }
        $recordingsCollection = $recordingsCollection->orderBy('recording_name')->get();
        $recording_path = DefaultSettings::where('default_setting_category', 'switch')
            ->where('default_setting_subcategory', 'recordings')
            ->where('default_setting_enabled', true)
            ->value('default_setting_value');

        // logger($recordingsCollection);
        foreach ($recordingsCollection as $item) {
            $recordings[] = [
                'label' => $item->recording_name,
                'value' => $recording_path . '/' . $item->domain->domain_name . '/' . $item->recording_filename
            ];
        }
        // logger($recordings);

        $streamsCollection = MusicStreams::where('stream_enabled', 'true')
            ->orderBy('stream_name')
            ->select('stream_uuid', 'stream_name', 'stream_location');
        if ($domain) {
            $streamsCollection->where('domain_uuid', $domain)
                ->orWhere('domain_uuid', null);
        }
        $streamsCollection = $streamsCollection->get();
        $streams = [];
        foreach ($streamsCollection as $item) {
            $streams[] = [
                'label' => $item->stream_name,
                'value' => $item->stream_location
            ];
        }

        unset($musicOnHoldCollection, $recordingsCollection, $ringtonesCollection, $item);
        return [
            [
                'label' => 'Music on Hold',
                'items' => array_map(function ($item) {
                    return [
                        'label' => $item['label'],
                        'value' => $item['value'],
                    ];
                }, $musicOnHold),
            ],
            [
                'label' => 'Recordings',
                'items' => array_map(function ($item) {
                    return [
                        'label' => $item['label'],
                        'value' => $item['value'],
                    ];
                }, $recordings),
            ],
            [
                'label' => 'Streams',
                'items' => array_map(function ($item) {
                    return [
                        'label' => $item['label'],
                        'value' => $item['value'],
                    ];
                }, $streams),
            ],
        ];
    }
}

if (! function_exists('getRingBackTonesCollectionGrouped')) {
    function getRingBackTonesCollectionGrouped(string $domain = null): array
    {
        // — Music on Hold —
        $musicOnHold = MusicOnHold::when($domain, function ($q) use ($domain) {
            $q->where('domain_uuid', $domain)
                ->orWhereNull('domain_uuid');
        })
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name');
            }])
            ->orderBy('music_on_hold_name')
            ->get()
            ->unique('music_on_hold_name')
            ->values()
            ->map(fn($m) => [
                'label' => $m->music_on_hold_name,
                'value' => $m->domain_uuid ? 'local_stream://' . $m->domain->domain_name . '/' . $m->music_on_hold_name : 'local_stream://' . $m->music_on_hold_name,
            ])
            ->toArray();

        // — Recordings —
        $recordingPath = DefaultSettings::where('default_setting_category', 'switch')
            ->where('default_setting_subcategory', 'recordings')
            ->where('default_setting_enabled', true)
            ->value('default_setting_value');

        $recordings = Recordings::with(['domain:domain_uuid,domain_name'])
            ->when($domain, fn($q) => $q->where('domain_uuid', $domain))
            ->orderBy('recording_name')
            ->get()
            ->map(fn($r) => [
                'label' => $r->recording_name,
                'value' => "{$recordingPath}/{$r->domain->domain_name}/{$r->recording_filename}",
            ])
            ->toArray();

        // — Ringtones —
        $ringtones = SwitchVariable::where('var_category', 'Ringtones')
            ->where('var_enabled', 'true')
            ->orderBy('var_name')
            ->get(['var_name'])
            ->map(fn($v) => [
                'label' => $v->var_name,
                'value' => '${' . $v->var_name . '}',
            ])
            ->toArray();

        // — Streams —
        $streams = MusicStreams::when($domain, function ($q) use ($domain) {
            $q->where('domain_uuid', $domain)
                ->orWhereNull('domain_uuid');
        })
            ->where('stream_enabled', 'true')
            ->orderBy('stream_name')
            ->get(['stream_name', 'stream_location'])
            ->map(fn($s) => [
                'label' => $s->stream_name,
                'value' => $s->stream_location,
            ])
            ->toArray();

        // — Assemble groups —
        $groups = [];

        if (! empty($musicOnHold)) {
            $groups[] = [
                'label' => 'Music on Hold',
                'items' => $musicOnHold,
            ];
        }

        if (! empty($recordings)) {
            $groups[] = [
                'label' => 'Recordings',
                'items' => $recordings,
            ];
        }

        if (! empty($ringtones)) {
            $groups[] = [
                'label' => 'Ringtones',
                'items' => $ringtones,
            ];
        }

        if (! empty($streams)) {
            $groups[] = [
                'label' => 'Streams',
                'items' => $streams,
            ];
        }

        return $groups;
    }
}


if (!function_exists('getSoundsCollection')) {
    function getSoundsCollection(string $domain = null): array
    {
        $recordings = [];
        $recordingsCollection = Recordings::query()
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name'); // Select only the fields you need from the domain
            }])
            ->select('domain_uuid', 'recording_filename', 'recording_name');
        if ($domain) {
            $recordingsCollection->where('domain_uuid', $domain);
        }
        $recordingsCollection = $recordingsCollection->orderBy('recording_name')->get();

        // logger($recordingsCollection);
        foreach ($recordingsCollection as $item) {
            $recordings[] = [
                'name' => $item->recording_name,
                'value' => $item->recording_filename
            ];
        }
        // logger($recordings);


        $variables = SwitchVariable::whereIn('var_name', ['default_language', 'default_dialect', 'default_voice'])
            ->pluck('var_value', 'var_name');
        // Extract values
        $defaultLanguage = $variables['default_language'] ?? 'en'; // Fallback to 'en' if not found
        $defaultDialect = $variables['default_dialect'] ?? 'us';  // Fallback to 'us' if not found
        $defaultVoice = $variables['default_voice'] ?? 'callie';  // Fallback to 'callie' if not found

        $sounds = Storage::disk('sounds')->allFiles($defaultLanguage . "/" . $defaultDialect . "/" . $defaultVoice);

        $sounds = collect($sounds)
            ->map(function ($file) {
                // Remove the "en/us/callie" prefix and subdirectories with numbers or specific names like 'flac'
                $cleanedFile = preg_replace('#^en/us/callie/#', '', $file); // Remove "en/us/callie"
                $cleanedFile = preg_replace('#/(\d+|flac)/#', '/', $cleanedFile);
                return [
                    'name' => $cleanedFile,
                    'value' => $cleanedFile,
                ];
            })
            ->unique('name') // Ensure uniqueness by 'name'
            ->values()
            ->all();



        unset($musicOnHoldCollection, $recordingsCollection, $ringtonesCollection, $item);
        return [
            '' => [
                [
                    'name' => 'None',
                    'value' => 'silence_stream://100',
                ],
            ],
            'Recordings' => $recordings,
            'Sounds' => $sounds,
        ];
    }
}

if (!function_exists('getExtensionCollection')) {
    function getExtensionCollection(string $domain = null): array
    {
        $extensionsCollection = Extensions::query();
        if ($domain) {
            $extensionsCollection->where('domain_uuid', $domain);
        }
        $extensionsCollection = $extensionsCollection->orderBy('extension')->get();

        $extensions = [];
        foreach ($extensionsCollection as $extension) {
            $extensions[] = [
                'name' => $extension->extension . (($extension->effective_caller_id_name) ? ' (' . trim($extension->effective_caller_id_name) . ')' : ''),
                'value' => $extension->extension_uuid
            ];
        }
        unset($extensionsCollection, $extension);
        return $extensions;
    }
}

if (!function_exists('tokenizeMacAddress')) {
    function tokenizeMacAddress(string $macAddress): string
    {
        return str_replace([':', '.', '-'], '', trim(strtolower($macAddress)));
    }
}

if (!function_exists('formatMacAddress')) {
    function formatMacAddress(string $macAddress, $uppercase = true): string
    {
        $macAddress = ($uppercase) ? strtoupper($macAddress) : strtolower($macAddress);
        return implode(":", str_split($macAddress, 2));
    }
}


if (! function_exists('gateway_setting')) {
    function gateway_setting(string $slug, string $key, $default = null)
    {
        $cacheKey = "gateways:{$slug}:{$key}";

        // Fast path: cache hit
        $cached = Cache::tags(['gateways', $slug])->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Fallback: DB -> cache for a week
        $uuid = PaymentGateway::where('slug', $slug)
            ->where('is_enabled', true)
            ->value('uuid');
        if (! $uuid) return $default;

        $value = GatewaySetting::where('gateway_uuid', $uuid)
            ->where('setting_key', $key)
            ->value('setting_value');

        if ($value !== null) {
            Cache::tags(['gateways', $slug])->put($cacheKey, $value, now()->addWeek());
            return $value;
        }

        return $default;
    }
}


if (!function_exists('getGroupedTimezones')) {
    function getGroupedTimezones()
    {
        // 1) build an associative map of regions → options
        $groupedTimezones = [];

        foreach (DateTimeZone::listIdentifiers() as $tz) {
            [$region] = explode('/', $tz, 2);
            $offset = (new DateTime('now', new DateTimeZone($tz)))->format('P');

            $groupedTimezones[$region][] = [
                'value' => $tz,
                'label'  => "(UTC {$offset}) {$tz}",
            ];
        }

        ksort($groupedTimezones);

        // 2) prepend “System Default”
        $groupedTimezones = ['System Default' => [
            ['value' => null, 'label' => 'System Default'],
        ]] + $groupedTimezones;

        // 3) transform into indexed array of { groupLabel, groupOptions }
        $result = [];
        foreach ($groupedTimezones as $region => $options) {
            $result[] = [
                'label'   => $region,
                'items' => $options,
            ];
        }

        return $result;
    }
}

/**
 * Helper function to build destination action based on routing option type.
 */
if (!function_exists('buildDestinationAction')) {
    function buildDestinationAction($option, $domain_name = null)
    {
        $domain_name = $domain_name ?? session('domain_name');
        switch ($option['type']) {
            case 'extensions':
            case 'ring_groups':
            case 'ivrs':
            case 'business_hours':
            case 'time_conditions':
            case 'contact_centers':
            case 'conferences':
            case 'faxes':
            case 'call_flows':
                return [
                    'destination_app' => 'transfer',
                    'destination_data' => $option['extension'] . ' XML ' . $domain_name,
                ];

            case 'voicemails':
                return [
                    'destination_app' => 'transfer',
                    'destination_data' => '*99' . $option['extension'] . ' XML ' . $domain_name,
                ];

            case 'check_voicemail':
                return [
                    'destination_app' => 'transfer',
                    'destination_data' => '*98 XML ' . $domain_name,
                ];

            case 'company_directory':
                return [
                    'destination_app' => 'transfer',
                    'destination_data' => '*411 XML ' . $domain_name,
                ];

            case 'recordings':
                // Handle recordings with 'lua' destination app
                return [
                    'destination_app' => 'lua',
                    'destination_data' => 'streamfile.lua ' . $option['extension'],
                ];

            case 'hangup':
                return [
                    'destination_app' => 'hangup',
                    'destination_data' => '',
                ];

                // Add other cases as necessary for different types
            default:
                return [];
        }
    }

    if (!function_exists('get_limit_setting')) {
        /**
         * Get a numeric limit for a given category/subcategory, checking domain_settings first, then default_settings.
         *
         * @param string $subcategory     E.g., 'extensions', 'devices', 'gateways', etc.
         * @param string|null $domain_uuid  If null, will not check domain_settings.
         * @return int|null   Limit value, or null if unlimited.
         */
        function get_limit_setting($subcategory, $domain_uuid = null)
        {
            // 1. Check domain_settings first if domain_uuid is provided
            if ($domain_uuid) {
                $domainLimit = \App\Models\DomainSettings::where([
                    ['domain_setting_category', '=', 'limit'],
                    ['domain_setting_subcategory', '=', $subcategory],
                    ['domain_setting_enabled', '=', 'true'],
                    ['domain_uuid', '=', $domain_uuid]
                ])->value('domain_setting_value');

                if ($domainLimit !== null && $domainLimit !== '' && is_numeric($domainLimit)) {
                    return (int)$domainLimit;
                }
            }

            // 2. Fallback to default_settings
            $defaultLimit = \App\Models\DefaultSettings::where([
                ['default_setting_category', '=', 'limit'],
                ['default_setting_subcategory', '=', $subcategory],
                ['default_setting_enabled', '=', 'true'],
            ])->value('default_setting_value');

            if ($defaultLimit !== null && $defaultLimit !== '' && is_numeric($defaultLimit)) {
                return (int)$defaultLimit;
            }

            // 3. Unlimited if not found
            return null;
        }
    }

    if (!function_exists('fspbx_vendor_key_type_code')) {
        function fspbx_vendor_key_type_code(string $vendor, string $simple_type, ?string $category = null): string
        {
            $v = strtolower(trim($vendor));
            $t = strtolower(trim($simple_type));
            $cat = strtolower(trim((string)$category));

            // Yealink
            if ($v === 'yealink') {
                return match ($t) {
                    'line' => '15',
                    'speed_dial' => '13',
                    'blf', 'check_voicemail' => '16',
                    'park' => '16',
                    '' => '0',
                    default => '0',
                };
            }

            // Polycom
            if ($v === 'polycom') {
                return match ($t) {
                    'line' => 'line',
                    'speed_dial' => 'blf',
                    'blf' => 'normal',
                    'check_voicemail' => 'normal',
                    'park' => 'automata',
                    '' => 'unassigned',
                    default => 'unassigned',
                };
            }

            // Cisco
            if ($v === 'cisco') {
                return match ($t) {
                    'line' => 'line',
                    'park' => 'blf',
                    '' => 'disabled',
                    default => $t,
                };
            }

            // Fanvil
            if ($v === 'fanvil') {
                return match ($t) {
                    'line' => '1',
                    'speed_dial' => 'f',
                    'park' => 'c',
                    'blf', 'check_voicemail' => 'bc',
                    '' => '3',
                    default => $t,
                };
            }

            // Escene
            if ($v === 'escene') {
                return match ($t) {
                    'speed_dial' => '5',
                    'park' => '7',
                    'blf', 'check_voicemail' => '1',
                    default => $t,
                };
            }

            // Flyingvoice
            if ($v === 'flyingvoice') {
                return match ($t) {
                    'line' => '15',
                    'speed_dial' => '13',
                    'park' => '10',
                    'blf', 'check_voicemail' => '16',
                    '' => '0',
                    default => $t,
                };
            }

            if ($v === 'grandstream') {
                return match ($t) {
                    'speed_dial'      => 'speed dial',
                    'check_voicemail' => 'blf',
                    'park'            => 'monitored call park',
                    '' => 'none',
                    default           => $t,
                };
            }

            // Htek
            if ($v === 'htek') {
                return match ($t) {
                    'line' => '1',
                    'speed_dial' => '2',
                    'park' => '8',
                    'blf', 'check_voicemail' => '3',
                    '' => '0',
                    default => $t,
                };
            }

            // Linksys
            if ($v === 'linksys') {
                return match ($t) {
                    '' => 'disabled',
                    default           => $t,
                };
            }

            // mitel
            if ($v === 'mitel') {
                return match ($t) {
                    'line' => '6',
                    'speed_dial' => '1',
                    'park' => '27',
                    'blf', 'check_voicemail' => '27',
                    '' => '0',
                    default => $t,
                };
            }

            // sangoma
            if ($v === 'sangoma') {
                return match ($t) {
                    'line' => '1',
                    'speed_dial' => '2',
                    'park' => '8',
                    'blf', 'check_voicemail' => '3',
                    '' => '0',
                    default => $t,
                };
            }

            // snom
            if ($v === 'snom') {
                return match ($t) {
                    'speed_dial' => 'speed',
                    'park' => 'orbit',
                    'check_voicemail' => 'blf',
                    '' => 'none',
                    default => $t,
                };
            }

            return match ($t) {
                default           => $t,
            };
        }
    }

    if (!function_exists('fspbx_map_simple_key_to_fusion_row')) {
        /**
         * Convert simplified device_keys row to FusionPBX legacy row format
         *
         * If key_type is blank => clear slot (remove existing id).
         */
        function fspbx_map_simple_key_to_fusion_row(
            array $nk,
            string $device_uuid,
            string $vendor,
            array $device_lines,
            string $domain_uuid,
            array $blfLabelMap = []
        ): array {
            $id = (int)($nk['key_index'] ?? 0);
            if ($id <= 0) return [];

            $rawType = strtolower(trim((string)($nk['key_type'] ?? '')));

            $category = 'line';

            // Vendor-coded type 
            $device_key_type = fspbx_vendor_key_type_code($vendor, $rawType, $category);

            $value = $nk['key_value'] ?? null;
            $label = $nk['key_label'] ?? null;

            if ($vendor == 'grandstream') {
                $line = $value-1;
            } else {
                $line = 1;
            }

            // Build the row with the SAME keys FusionPBX uses
            $row = [
                'device_key_id'        => $id,
                'device_key_category'  => $category,
                'device_key_vendor'    => strtolower(trim($vendor)),
                'device_key_type'      => $device_key_type,
                'device_key_subtype'   => '',
                'device_key_line'      => $line,
                'device_key_value'     => '',
                'device_key_extension' => '',
                'device_key_protected' => '',
                'device_key_label'     => '',
                'device_key_icon'      => '',
            ];

            // Fill fields according to simplified type
            if ($rawType === 'line') {
                $acct = (int)($nk['key_value'] ?? 1);
                if ($acct <= 0) $acct = 1;

                // $row['device_key_line'] = $acct;
                $row['device_key_value'] = '';

                // If the label is currently empty, look inside $device_lines
                if ($row['device_key_label'] === '' && isset($device_lines[$acct])) {
                    // Try 'label' (custom label field)
                    if (!empty($device_lines[$acct]['label'])) {
                        $row['device_key_label'] = $device_lines[$acct]['label'];
                    }
                    // Fallback to 'display_name' (caller ID name)
                    elseif (!empty($device_lines[$acct]['display_name'])) {
                        $row['device_key_label'] = $device_lines[$acct]['display_name'];
                    }
                    // Fallback to 'user_id' (extension number)
                    elseif (!empty($device_lines[$acct]['user_id'])) {
                        $row['device_key_label'] = $device_lines[$acct]['user_id'];
                    }
                }
            } elseif ($rawType === 'speed_dial') {
                $row['device_key_value'] = ($value !== null ? (string)$value : '');
                $row['device_key_label'] = (strlen((string)$label) ? (string)$label : '');
            } elseif ($rawType === 'blf') {
                $row['device_key_value'] = ($value !== null ? (string)$value : '');

                if (!strlen((string)$label)) {
                    $row['device_key_label'] = $blfLabelMap[$row['device_key_value']] ?? '';
                } else {
                    $row['device_key_label'] = (string)$label;
                }
            } elseif ($rawType === 'check_voicemail') {
                $val = ($value !== null ? (string)$value : '');
                if ($val !== '' && ctype_digit($val)) {
                    $val = 'vm' . $val; // allow storing "101" in DB but emitting "vm101"
                }
                $row['device_key_value'] = $val;

                if (strlen((string)$label)) {
                    $row['device_key_label'] = (string)$label;
                } else {
                    $digits = preg_replace('/\D+/', '', $val);
                    $row['device_key_label'] = ($digits !== '') ? ('VM ' . $digits) : '';
                }
            } elseif ($rawType === 'park') {
                $val = ($value !== null ? (string)$value : '');
                if ($val !== '' && ctype_digit($val)) {
                    $val = 'park+*' . $val;
                }
                $row['device_key_value'] = $val;
                $row['device_key_label'] = (strlen((string)$label) ? (string)$label : 'Park');
            } else {
                // Unknown type => safest is disabled slot
                $row['device_key_type'] = fspbx_vendor_key_type_code($vendor, '', $category); // => 0 for yealink
                $row['device_key_value'] = '';
                $row['device_key_label'] = '';
            }

            return $row;
        }
    }

    if (!function_exists('fspbx_apply_new_keys_override')) {
        /**
         * Apply new device_keys rows as strongest override onto FusionPBX $device_keys structure.
         */
        function fspbx_apply_new_keys_override(
            ?array &$device_keys,
            array $new_keys_rows,
            string $device_uuid,
            string $vendor,
            array $device_lines = [],
            string $domain_uuid
        ): void {
            if ($device_keys === null) {
                $device_keys = [];
            }

            if ($device_lines === null) {
                $device_lines = [];
            }
            $blfLabelMap = fspbx_prefetch_extension_labels($domain_uuid, $new_keys_rows);

            $polycomLineCounts = [];
            $polycomFirstIndex = [];

            if ($vendor === 'polycom') {
                foreach ($new_keys_rows as $nk) {
                    $type = strtolower(trim((string)($nk['key_type'] ?? '')));
                    if ($type !== 'line') continue;

                    $acct = (int)($nk['key_value'] ?? 1);
                    if ($acct <= 0) $acct = 1;

                    $polycomLineCounts[$acct] = ($polycomLineCounts[$acct] ?? 0) + 1;

                    $idx = (int)($nk['key_index'] ?? 0);
                    if ($idx > 0 && (!isset($polycomFirstIndex[$acct]) || $idx < $polycomFirstIndex[$acct])) {
                        $polycomFirstIndex[$acct] = $idx;
                    }
                }
            }

            $polycomEmptyCount = 0;
            $polycomEmptyLeaderIdx = null;

            if ($vendor === 'polycom') {
                foreach ($new_keys_rows as $nk) {
                    $type = strtolower(trim((string)($nk['key_type'] ?? '')));
                    if ($type !== '') continue;

                    $polycomEmptyCount++;

                    $idx = (int)($nk['key_index'] ?? 0);
                    if ($idx > 0 && ($polycomEmptyLeaderIdx === null || $idx < $polycomEmptyLeaderIdx)) {
                        $polycomEmptyLeaderIdx = $idx;
                    }
                }
            }

            foreach ($new_keys_rows as $nk) {

                // Pass $device_lines down
                $row = fspbx_map_simple_key_to_fusion_row(
                    $nk,
                    $device_uuid,
                    $vendor,
                    $device_lines,
                    $domain_uuid,
                    $blfLabelMap
                );

                if (empty($row)) continue;

                // --- Polycom special handling for line keys ---
                if ($vendor === 'polycom' && $row['device_key_type'] === 'line') {
                    $acct = (int)($nk['key_value'] ?? 1);
                    if ($acct <= 0) $acct = 1;

                    $count = (int)($polycomLineCounts[$acct] ?? 1);
                    if ($count <= 0) $count = 1;

                    $idx = (int)($nk['key_index'] ?? 0);
                    $leaderIdx = (int)($polycomFirstIndex[$acct] ?? $idx);

                    if ($idx !== $leaderIdx) {
                        continue;
                    }

                    // Leader row carries the count
                    $row['device_key_type']  = 'line';
                    $row['device_key_line']  = $acct;
                    $row['device_key_value'] = (string)$count;

                    if (!isset($device_lines[$acct]) || !is_array($device_lines[$acct])) {
                        $device_lines[$acct] = [];
                    }
                    $device_lines[$acct]['line_keys'] = (string)$count;
                    $device_lines[$acct]['device_key_owner'] = 'device';
                }

                // --- Polycom special handling for empty/unassigned keys ---
                if ($vendor === 'polycom' && $row['device_key_type'] === 'unassigned') {
                    // If there are multiple blanks, only emit one leader row
                    $idx = (int)($nk['key_index'] ?? 0);

                    // No leader (shouldn't happen unless key_index missing), just treat as 1
                    if ($polycomEmptyLeaderIdx === null) {
                        $polycomEmptyLeaderIdx = $idx;
                        $polycomEmptyCount = max(1, $polycomEmptyCount);
                    }

                    if ($idx !== $polycomEmptyLeaderIdx) {
                        continue;
                    }

                    // Leader row carries the count
                    $row['device_key_type']  = fspbx_vendor_key_type_code($vendor, '', 'line'); // => 'unassigned'
                    $row['device_key_line']  = 0;
                    $row['device_key_value'] = (string)max(1, (int)$polycomEmptyCount);
                    $row['device_key_label'] = '';
                }

                $id = (int)($row['device_key_id'] ?? 0);
                if ($id <= 0) continue;

                $cat = $row['device_key_category'] ?: 'line';
                $device_keys[$cat][$id] = $row;

                // This line ensures backwards compatibility with older templates
                $device_keys[$id] = $row;
                $device_keys[$id]['device_key_owner'] = 'device';
            }
        }
    }


    if (!function_exists('fspbx_prefetch_extension_labels')) {
        function fspbx_prefetch_extension_labels(string $domain_uuid, array $new_keys_rows): array
        {
            $exts = [];

            foreach ($new_keys_rows as $nk) {
                $type  = strtolower(trim((string)($nk['key_type'] ?? '')));
                $label = (string)($nk['key_label'] ?? '');
                $val   = (string)($nk['key_value'] ?? '');

                if ($type === 'blf' && $label === '' && $val !== '') {
                    $exts[$val] = true; // unique
                }
            }

            $exts = array_keys($exts);
            if (empty($exts)) return [];

            // Build IN (:e0,:e1,...)
            $placeholders = [];
            $params = ['domain_uuid' => $domain_uuid];

            foreach ($exts as $i => $ext) {
                $ph = 'e' . $i;
                $placeholders[] = ':' . $ph;
                $params[$ph] = $ext;
            }

            $sql = "select extension, effective_caller_id_name
                from v_extensions
                where domain_uuid = :domain_uuid
                  and extension in (" . implode(',', $placeholders) . ")";

            $database = new database;
            $rows = $database->select($sql, $params, 'all');

            $map = [];
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $map[(string)$r['extension']] = (string)($r['effective_caller_id_name'] ?? '');
                }
            }

            return $map;
        }
    }

    if (!function_exists('fspbx_prefetch_blf_labels')) {
        function fspbx_prefetch_blf_labels(string $domain_uuid, array $keys): array
        {
            // Collect unique extensions that need labels
            $need = [];
            foreach ($keys as $row) {
                $type  = strtolower((string)($row['device_key_type'] ?? ''));
                $value = (string)($row['device_key_value'] ?? '');
                $label = (string)($row['device_key_label'] ?? '');

                // Only BLF (match your system’s BLF type values; legacy Fusion uses 'blf' string here)
                if ($value !== '' && $label === '') {
                    $need[$value] = true;
                }
            }

            $exts = array_keys($need);
            if (!$exts) return [];

            // Build IN list safely
            $params = ['domain_uuid' => $domain_uuid];
            $ph = [];
            foreach ($exts as $i => $ext) {
                $k = "e{$i}";
                $ph[] = ":{$k}";
                $params[$k] = $ext;
            }

            $sql = "select extension, effective_caller_id_name
            from v_extensions
            where domain_uuid = :domain_uuid
              and extension in (" . implode(',', $ph) . ")";

            $database = new database;
            $rows = $database->select($sql, $params, 'all');

            $map = [];
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $map[(string)$r['extension']] = (string)($r['effective_caller_id_name'] ?? '');
                }
            }

            return $map;
        }
    }
}
