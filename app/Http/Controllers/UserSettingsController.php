<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserSettingsController extends Controller
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request,User $user)
    {

        $attributes = [        
            'user_setting_category' =>'category',
            'user_setting_subcategory' => 'subcategoty',
            'user_setting_name' => 'type',
            'user_setting_value' => 'value',
            'user_setting_description' => 'description',
            'user_setting_enabled' => 'enabled', 
        ];

        $validator = Validator::make($request->all(), [
            'user_setting_category' =>'required|string|max:50',
            'user_setting_subcategory' => 'required|string|max:50',
            'user_setting_name' => 'required|string|max:50',
            'user_setting_value' => 'required|string|max:70',
            'user_setting_description' => 'nullable',
            'user_setting_enabled' => 'present',         
  
        ], [], $attributes);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();
        if (isset($attributes['user_setting_enabled']) && $attributes['user_setting_enabled']== "on")  $attributes['user_setting_enabled'] = "t";

        $setting=new UserSetting();
        $setting->domain_uuid=$user->domain_uuid;
        $setting->user_setting_category=$attributes['user_setting_category'];
        $setting->user_setting_subcategory=$attributes['user_setting_subcategory'];
        $setting->user_setting_name=$attributes['user_setting_name'];
        $setting->user_setting_value=$attributes['user_setting_value'];
        $setting->user_setting_description=$attributes['user_setting_description'];
        $setting->user_setting_enabled=$attributes['user_setting_enabled'];
        $user->setting()->save($setting);

        return response()->json([
            'status' => 'success',
            'message' => 'New setting has been saved'
        ]);

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

        $userSetting = UserSetting::findOrFail($id);

        if(isset($userSetting)){
            $deleted = $userSetting->delete();

            if ($deleted){
                return response()->json([
                    'status' => 'success',
                    'id' => $id,
                    'message' => 'Selected settings have been deleted'
                ]);
            } else {
                return response()->json([
                    'error' => 401,
                    'message' => 'There was an error deleting this setting'
                ]);
            }
        }
    }
}
