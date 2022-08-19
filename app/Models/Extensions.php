<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Extensions extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_extensions";

    public $timestamps = false;

    protected $primaryKey = 'extension_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'extension_uuid',
        'domain_uuid',
        'extension',
        'number_alias',
        'password',
        'accountcode',
        'effective_caller_id_name',
        'effective_caller_id_number',
        'outbound_caller_id_name',
        'outbound_caller_id_number',
        'emergency_caller_id_name',
        'emergency_caller_id_number',
        'directory_first_name',
        'directory_last_name',
        'directory_visible',
        'directory_exten_visible',
        'max_registrations',
        'limit_max',
        'limit_destination',
        'missed_call_app',
        'missed_call_data',
        'user_context',
        'toll_allow',
        'call_timeout',
        'call_group',
        'call_screen_enabled',
        'user_record',
        'hold_music',
        'auth_acl',
        'cidr',
        'sip_force_contact',
        'nibble_account',
        'sip_force_expires',
        'mwi_account',
        'sip_bypass_media',
        'unique_id',
        'dial_string',
        'dial_user',
        'dial_domain',
        'do_not_disturb',
        'forward_all_destination',
        'forward_all_enabled',
        'forward_busy_destination',
        'forward_busy_enabled',
        'forward_no_answer_destination',
        'forward_no_answer_enabled',
        'forward_user_not_registered_destination',
        'forward_user_not_registered_enabled',
        'follow_me_uuid',
        'follow_me_enabled',
        'follow_me_destinations',
        'enabled',
        'description',
        'absolute_codec_string',
        'force_ping',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->attributes['domain_uuid'] = Session::get('domain_uuid');
    }

    /**
     * Get the voicemail associated with this extension.
     *  returns Eloqeunt Object
     */
    // public function voicemail()
    // {
    //     return $this->hasOne(Voicemails::class,'voicemail_id','extension');
    // }

    /**
     * Get the voicemail associated with this extension.
     *  returns Eloqeunt Object
     */
    public function voicemail()
    {
        return $this->hasOne(Voicemails::class,'voicemail_id','extension')
            ->where('domain_uuid', $this->domain_uuid);
    }

    /**
     * Get the Extension User object associated with this extension.
     *  returns Eloqeunt Object
     */
    public function extension_users()
    {
        return $this->hasMany(ExtensionUser::class,'extension_uuid','extension_uuid');
    }

    /**
     * Get the Mobile App object associated with this extension.
     *  returns Eloqeunt Object
     */
    public function mobile_app()
    {
        return $this->hasOne(MobileAppUsers::class,'extension_uuid','extension_uuid');
    }

    /**
     * Get all of the users for the extension.
     */
    public function users()
    {
        $user_uuids = ExtensionUser::where('extension_uuid', $this->extension_uuid)->get();

        $users = collect();
        foreach ($user_uuids as $user_uuid) {
            $users->push($user_uuid->user);
        }

        return $users;

    }

    /**
     * Get the domain to which this extension belongs 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class,'domain_uuid','domain_uuid');
    }
}
