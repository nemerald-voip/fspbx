<?php

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

if (!function_exists('event_socket_create')){
    function event_socket_create($host, $port, $password) {
        $esl = new event_socket;
        if ($esl->connect($host, $port, $password)) {
            return $esl->reset_fp();
        }
        return false;
    }
}

function event_socket_request($fp, $cmd) {
	$esl = new event_socket($fp);
	$result = $esl->request($cmd);
	$esl->reset_fp();
	return $result;
}

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

