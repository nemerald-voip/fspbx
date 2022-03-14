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

