<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Models\Domain;
use App\Models\Contact;
use App\Models\UserGroup;
use App\Models\UserSetting;
use Illuminate\Http\Request;
use App\Models\UserAdvFields;
use Illuminate\Support\Facades\DB;
use App\Models\UserDomainPermission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Session;

class UsersController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

   /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        $data=array();
        $domain_uuid=Session::get('domain_uuid');
        $data['users']=User::where('domain_uuid',$domain_uuid)->orderBy('username','asc')->get();

        return view('layouts.users.list')->with($data);
    }


    /**
     * Show the create user form.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function createUser(Request $request)
    {
        $data=array();
        $data['domains']=Domain::get();
        $data['user_group']=DB::table('v_groups')->get();
        $data['user_domain_name']=Session::get("domain_name");
        $data['languages']=DB::table('v_languages')->get();
        
        return view('layouts.users.create')->with($data);

    }
    public function getDomainID($domain_name){
        return Domain::where('domain_name',$domain_name)->pluck('domain_uuid')->first();
    }
    public function editUser(Request $request)
    {
        if(base64_decode($request->route('id'))){
            $id=base64_decode($request->route('id'));
            $user=User::find($id);
            if(!empty($user))
            {
                $user_language=[];
                $user_time_zone=[];
                if(!empty($user->setting)){
                    foreach($user->setting as $setting){
                        if($setting->user_setting_subcategory=='language'){
                            $user_language=$setting;
                        }
                        if($setting->user_setting_subcategory=='time_zone'){
                            $user_time_zone=$setting;
                        }
                    }
                }
                $domain_permission=[];
                if(!empty($user->user_domain)){
                    foreach($user->user_domain as $permission){
                        $domain_permission[]=$permission['domain_uuid'];
                    }
                }

                $group_permission=[];
                if(!empty($user->group)){
                    foreach($user->group as $group){
                        $group_permission[]=$group['group_uuid'];
                    }
                }
                // $contact=Contact::find($user['contact_uuid']);
                $data=array();
                $data['domains']=Domain::get();
                $data['user_group']=DB::table('v_groups')->get();
                $data['user_domain_name']=Session::get("domain_name");
                $data['languages']=DB::table('v_languages')->get();
                $data['user']=$user;
                // $data['contact']=$contact;
                $data['user_language']=$user_language;
                $data['user_time_zone']=$user_time_zone;
                $data['domain_permission']=$domain_permission;
                $data['group_permission']=$group_permission;
                $records=$user->setting()->where('user_setting_name','!=','system_default')->orderBy('user_setting_category')->get();
                
                $data['settings']=$records;
                return view('layouts.users.update')->with($data);
            }
        }
        
        return abort(404);
    }

    public function saveUser(Request $request){
        $response=array('success'=>false,'data'=>['error'=>'Something went wrong!']);
        $input=$request->input();

        $domain_uuid=Session::get('domain_uuid');

        if(!$this->checkEmailExist($input['email'])){

            // $contact=new Contact;
            // $contact->domain_uuid=$domain_uuid;
            // $contact->contact_name_given=$input['first_name'];
            // $contact->contact_name_family=$input['last_name'];
            // $contact->save();

            $user=new User;
            $user->domain_uuid=$domain_uuid;
            $user_name=$input['first_name'];
            if(!empty($input['last_name'])){
                if(!empty($input['first_name'])){
                    $user_name.='_';    
                }
                $user_name.=$input['last_name'];
            }
            
            $user->username=$user_name;
            $user->password=bcrypt($input['password']);
            $user->user_email=$input['email'];
            // $user->contact_uuid=$contact->contact_uuid;
            $user->user_enabled=($input['account_status']=='on')?'true':'false';
            $user->add_user=Auth::user()->username;
            $user->save();
            // $contact->user()->save($user);

            $user_name_info=new UserAdvFields();
            $user_name_info->first_name=$input['first_name'];
            $user_name_info->last_name=$input['last_name'];
            $user->user_adv_fields()->save($user_name_info);

            $group_name=DB::table('v_groups')->where('group_uuid',$input['group'])->pluck('group_name')->first();
            $user_group=new UserGroup();
            $user_group->domain_uuid=$domain_uuid;
            $user_group->group_name=$group_name;
            $user_group->group_uuid=$input['group'];
            $user->group()->save($user_group);

            if($input['group']=='191b8429-1d88-405a-8d64-7bbbe9ef84b2'){
                foreach($input['reseller_domain'] as $res_domain){
                    $dom_per=new UserDomainPermission();
                    $dom_per->domain_uuid=$res_domain;
                    $user->user_domain()->save($dom_per);
                }
            }

            $language=new UserSetting();
            $language->domain_uuid=$domain_uuid;
            $language->user_setting_category='domain';
            $language->user_setting_subcategory='language';
            $language->user_setting_name='system_default';
            $language->user_setting_value=$input['language'];
            $language->user_setting_enabled='t';
            
            $time_zone=new UserSetting();
            $time_zone->domain_uuid=$domain_uuid;
            $time_zone->user_setting_category='domain';
            $time_zone->user_setting_subcategory='time_zone';
            $time_zone->user_setting_name='system_default';
            $time_zone->user_setting_value=$input['time_zone'];
            $time_zone->user_setting_enabled='t';

            $user->setting()->saveMany([$language,$time_zone]);

            if(!empty($user)){
                $response['success']=true;
                $response['data']=$user;
            }
        } else {
            $response['data']['error']='Email already exist!';
        }


        echo json_encode($response);
        exit();
    }

    function updateUser(Request $request){
        // $contact_id=base64_decode($request->contact_id);
        $user_id=base64_decode($request->user_id);



        $response=array('success'=>false,'data'=>['error'=>'Something went wrong!']);
        $input=$request->input();

        
        if(!$this->checkEmailExist($input['email'],$user_id)){
            $domain_uuid=$input['user_site'];

            // $contact=Contact::find($contact_id);
            // $contact->domain_uuid=$domain_uuid;
            // $contact->contact_name_given=$input['first_name'];
            // $contact->contact_name_family=$input['last_name'];
            // $contact->save();

            $user=User::find($user_id);
            $user->domain_uuid=$domain_uuid;
            $user_name=$input['first_name'];
            if(!empty($input['last_name'])){
                if(!empty($input['first_name'])){
                    $user_name.='_';    
                }
                $user_name.=$input['last_name'];
            }
            
            $user->username=$user_name;
            $user->user_email=$input['email'];
            // $user->contact_uuid=$contact->contact_uuid;
            $user->user_enabled=($input['account_status']=='on')?'true':'false';
            $user->add_user=Auth::user()->username;
            $user->save();
            // $contact->user()->save($user);

            $user_name_info=$user->user_adv_fields;
            if(empty($user_name_info)){
                $user_name_info=new UserAdvFields();
            }
            $user_name_info->first_name=$input['first_name'];
            $user_name_info->last_name=$input['last_name'];
            $user->user_adv_fields()->save($user_name_info);

            if(!empty($input['group'])){
            $user->group()->delete();
                foreach($input['group'] as $group){
                    $group_name=DB::table('v_groups')->where('group_uuid',$group)->pluck('group_name')->first();
                    $user_group=new UserGroup();
                    $user_group->domain_uuid=$domain_uuid;
                    $user_group->group_name=$group_name;
                    $user_group->group_uuid=$group;
                    $user->group()->save($user_group);
                }
                
            
            }
            
            
            $user->user_domain()->delete();
            if(in_array('191b8429-1d88-405a-8d64-7bbbe9ef84b2',$input['group'])){
                foreach($input['reseller_domain'] as $res_domain){
                    $dom_per=new UserDomainPermission();
                    $dom_per->domain_uuid=$res_domain;
                    $user->user_domain()->save($dom_per);
                }
            }

            $user->setting()->delete();
            $language=new UserSetting();
            $language->domain_uuid=$domain_uuid;
            $language->user_setting_category='domain';
            $language->user_setting_subcategory='language';
            $language->user_setting_name='system_default';
            $language->user_setting_value=$input['language'];
            $language->user_setting_enabled='t';
            
            $time_zone=new UserSetting();
            $time_zone->domain_uuid=$domain_uuid;
            $time_zone->user_setting_category='domain';
            $time_zone->user_setting_subcategory='time_zone';
            $time_zone->user_setting_name='system_default';
            $time_zone->user_setting_value=$input['time_zone'];
            $time_zone->user_setting_enabled='t';

            $user->setting()->saveMany([$language,$time_zone]);

            if(!empty($user)){
                $response['success']=true;
                $response['data']=$user;
            }
        } else {
            $response['data']['error']='Email already exist!';
        }


        echo json_encode($response);
        exit();
    
    }

    function checkEmailExist($email,$id=''){
        $query=User::where('user_email',$email);
        if(!empty($id)){
            $query=$query->where('user_uuid','!=',$id);
        }
        $query=$query->first();
        if(!empty($query)){
            return true;
        }
        return false;
    }

    function deleteUser(Request $request){
        
        $response=array('success'=>false,'data'=>['error'=>'Something went wrong!']);
        $contact_id=$request->contact_id;
        if(!is_array($contact_id)){
            $contact_id=[$request->contact_id];
        }
        foreach($contact_id as $id){
            $user=User::find($id);
            if(!empty($user)){
            // $contact=Contact::find($user['contact_uuid']);
            $user->delete();
            // if(!empty($contact)){
            //     $contact->delete();
            // }
            }
        }
        
        $response=array('success'=>true,'data'=>'Deleted Successfully!'); 
        echo json_encode($response);
        exit();
    }
    function deleteSetting(Request $request){
        
        $response=array('success'=>false,'data'=>['error'=>'Something went wrong!']);
        $setting_id=$request->setting_id;
        if(!is_array($setting_id)){
            $setting_id=[$request->setting_id];
        }
        foreach($setting_id as $id){
            $setting=UserSetting::find($id);
            if(!empty($setting)){
            $setting->delete();
            }
        }
        
        $response=array('success'=>true,'data'=>'Deleted Successfully!'); 
        echo json_encode($response);
        exit();
    }


    public function addSetting(Request $request){
        $response=array('success'=>false,'data'=>['error'=>'Something went wrong!']);
        $input=$request->input();
        $user_id=base64_decode($input['user_id']);
        $user=User::find($user_id);
        if(!empty($user)){
            $setting=new UserSetting();
            $setting->domain_uuid=$user->domain_uuid;
            $setting->user_setting_category=$input['category'];
            $setting->user_setting_subcategory=$input['subcategory'];
            $setting->user_setting_name=$input['setting_type'];
            $setting->user_setting_value=$input['setting_value'];
            $setting->user_setting_description=$input['setting_description'];
            $setting->user_setting_enabled=($input['status']=='on')?'t':'f';
            $user->setting()->save($setting);
            $response=array('success'=>true,'data'=>'Setting saved successfully');
        }
        return $response;
    }


    

}
