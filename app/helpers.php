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
function pr($arr){
    echo '<pre>';
    print_r($arr);
    echo '</pre>';
}

