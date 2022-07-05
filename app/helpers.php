<?php

use Illuminate\Http\Request;
use App\Models\DomainSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

if (!function_exists('userCheckPermission')){
    function userCheckPermission($permission){
        $list = Session::get('permissions', false);

        if (!$list) return false;
        
        foreach ($list as $item){
            if ($item->permission_name == $permission){
                return true;
            }
        }
        return false;
    }

}

// Check if currenlty signed in user a superadmin
if (!function_exists('isSuperAdmin')){
    function isSuperAdmin(){
        foreach (Session::get('user.groups') as $group) {
            if ($group->group_name == "superadmin" && $group->group_level >= 80) {
                return true;
            }
        }
        return false;
    }

}

if (!function_exists('getDefaultSetting')){
    function getDefaultSetting($category,$subcategory){
        $settings = Session::get('default_settings', false);

        if (!$settings) return null;
        
        foreach ($settings as $setting){
            if ($setting['default_setting_category'] == $category &&
                $setting['default_setting_subcategory'] == $subcategory){
                return $setting ['default_setting_value'];
            }
        }
        return null;
    }

}

if (!function_exists('getFusionPBXPreviousURL')){
    function getFusionPBXPreviousURL($previous_url) {
        if (strpos($previous_url, "time_condition_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "time_condition_edit.php")) . "time_conditions.php";}
        elseif (strpos($previous_url, "destination_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "destination_edit.php")) . "destinations.php";}
        elseif (strpos($previous_url, "extension_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "extension_edit.php")) . "extensions.php";}
        elseif (strpos($previous_url, "ring_group_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "ring_group_edit.php")) . "ring_groups.php";}
        elseif (strpos($previous_url, "device_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "device_edit.php")) . "devices.php";}
        elseif (strpos($previous_url, "dialplan_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "dialplan_edit.php")) . "dialplans.php";}
        elseif (strpos($previous_url, "ivr_menu_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "ivr_menu_edit.php")) . "ivr_menus.php";}
        elseif (strpos($previous_url, "voicemail_edit.php")) {$url = substr($previous_url,0,strpos(url()->previous(), "voicemail_edit.php")) . "voicemails.php";}
        else $url = $previous_url;
        return $url;
    }
}

if (!function_exists('appsStoreOrganizationDetails')){
    function appsStoreOrganizationDetails(Request $request) {

        // Delete any existing records
        $deleted = DB::table('v_domain_settings')
        ->where ('domain_uuid','=', $request->organization_uuid)
        ->where('domain_setting_category','=', 'app shell')
        ->delete();

        // Store new records
        $domainSettingsModel = DomainSettings::create([
            'domain_uuid' => $request->organization_uuid,
            'domain_setting_category' => 'app shell',
            'domain_setting_subcategory' => 'org_id',
            'domain_setting_name' => 'text',
            'domain_setting_value' => $request->org_id,
            'domain_setting_enabled' => true,
        ]);
        $saved = $domainSettingsModel->save();
        if ($saved){
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('appsStoreConnectionDetails')){
    function appsStoreConnectionDetails(Request $request) {

        // Store new records
        $domainSettingsModel = DomainSettings::create([
            'domain_uuid' => $request->connection_organization_uuid,
            'domain_setting_category' => 'app shell',
            'domain_setting_subcategory' => 'conn_id',
            'domain_setting_name' => 'array',
            'domain_setting_value' => $request->conn_id,
            'domain_setting_enabled' => true,
        ]);
        $saved = $domainSettingsModel->save();
        if ($saved){
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('event_socket_create')){
    function event_socket_create($host, $port, $password) {
        $esl = new event_socket;
        if ($esl->connect($host, $port, $password)) {
            return $esl->reset_fp();
        }
        return false;
    }
}

if (!function_exists('event_socket_request')){
    function event_socket_request($fp, $cmd) {
        $esl = new event_socket($fp);
        $result = $esl->request($cmd);
        $esl->reset_fp();
        return $result;
    }
}

if (!function_exists('event_socket_request_cmd')){
    function event_socket_request_cmd($cmd) {
        //get the database connection
        require_once "resources/classes/database.php";
        $database = new database;
        $database->connect();
        $db = $database->db;

        if (file_exists($_SERVER["PROJECT_ROOT"]."/app/settings/app_config.php")) {
            $sql = "select * from v_settings ";
            $database = new database;
            $row = $database->select($sql, null, 'row');
            if (is_array($row) && @sizeof($row) != 0) {
                $event_socket_ip_address = $row["event_socket_ip_address"];
                $event_socket_port = $row["event_socket_port"];
                $event_socket_password = $row["event_socket_password"];
            }
            unset($sql, $row);
        }

        $esl = new event_socket;
        if (!$esl->connect($event_socket_ip_address, $event_socket_port, $event_socket_password)) {
            return false;
        }
        $response = $esl->request($cmd);
        $esl->close();
        return $response;
    }
}
if (!function_exists('pr')){
    function pr($arr){
        echo '<pre>';
        print_r($arr);
        echo '</pre>';
    }
}

if (!function_exists('setDefaultS3')){
    function setDefaultS3($arr){
       
    }
}

if (!function_exists('getCredentialKey')){
    function getCredentialKey($string){
       switch($string){
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


