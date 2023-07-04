<?php

namespace App\Http\Livewire\Extensions;

use App\Models\User;
use App\Models\Groups;
use Livewire\Component;
use App\Models\UserGroup;
use App\Models\UserSetting;
use Illuminate\Support\Str;
use App\Models\UserAdvFields;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class MakeUser extends Component
{
    public $extension;

    public function render()
    {
        return view('livewire.extensions.make-user');
    }

    public function makeUser()
    {
        $group_name = 'user';

        // Generate a secure password 
        $attributes['password'] = Hash::make(Str::random(25));

        $attributes['domain_uuid'] = Session::get('domain_uuid');
        $attributes['add_user'] = Auth::user()->username;
        $attributes['insert_date'] = date('Y-m-d H:i:s');
        $attributes['insert_user'] = Session::get('user_uuid');
        $attributes['first_name'] = $this->extension->directory_first_name;
        $attributes['last_name'] = $this->extension->directory_last_name;
        //Make username by combining first name and last name
        $attributes['username'] = $attributes['first_name'];
        if (!empty($attributes['last_name'])) {
            $attributes['username'] .= '_' . $attributes['last_name'];
        }
        if ($this->extension->voicemail) {
            $attributes['user_email'] = $this->extension->voicemail->voicemail_mail_to;
        } else {
            $attributes['user_email'] = "";
        }
        $attributes['user_enabled'] = "true";

        $user = new User();
        $user->fill($attributes);
        $user->save();
        logger($user);

        $user_name_info = new UserAdvFields();
        $user_name_info->first_name = $attributes['first_name'];
        $user_name_info->last_name = $attributes['last_name'];
        $user->user_adv_fields()->save($user_name_info);
        logger($user_name_info);

        // Add user to the group
        $group = Groups::where('group_name', $group_name)->first();
        $user_group = new UserGroup();
        $user_group->domain_uuid = Session::get('domain_uuid');
        $user_group->group_name = $group_name;
        $user_group->group_uuid = $group->group_uuid;
        $user_group->insert_date = date('Y-m-d H:i:s');
        $user_group->insert_user = Session::get('user_uuid');
        $user->user_groups()->save($user_group);
        logger($user_group);

        $language = new UserSetting();
        $language->domain_uuid = Session::get('domain_uuid');;
        $language->user_setting_category = 'domain';
        $language->user_setting_subcategory = 'language';
        $language->user_setting_name = 'code';
        $language->user_setting_value = get_domain_setting('language');
        $language->user_setting_enabled = 't';
        logger($language);

        $time_zone = new UserSetting();
        $time_zone->domain_uuid = Session::get('domain_uuid');;
        $time_zone->user_setting_category = 'domain';
        $time_zone->user_setting_subcategory = 'time_zone';
        $time_zone->user_setting_name = 'name';
        $time_zone->user_setting_value = get_local_time_zone(Session::get('domain_uuid'));
        $time_zone->user_setting_enabled = 't';

        logger($time_zone);

        $user->setting()->saveMany([$language,$time_zone]);
    }
}
