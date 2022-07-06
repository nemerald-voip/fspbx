<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, \App\Models\Traits\TraitUuid;

    protected $table = "v_users";

    public const CREATED_AT = 'add_date';
    public const UPDATED_AT = null;

    protected $primaryKey = 'user_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $rememberTokenName = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'user_email',
        'password',
        'domain_uuid',
        'user_enabled',
        'add_user'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_key'
    ];

    /**
     * The attributes that should be cast.
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];


    /**
     * Get the extensions associated with the user.
     *  returns Eloqeunt Collection
     */
    public function extensions()
    {
        $extensions = DB::table('v_extensions')
        -> join ('v_extension_users', 'v_extension_users.extension_uuid', '=', 'v_extensions.extension_uuid')
            -> where ('v_extension_users.user_uuid', '=', $this->user_uuid)
                -> get([
                    'v_extensions.extension_uuid',
                    'v_extensions.extension',
                    'v_extensions.outbound_caller_id_number',
                    'v_extensions.user_context',
                    'v_extensions.description',
                ]);
     
        return $extensions;
    }

    public function getEmailAttribute()
    {
        return $this->user_email;
    }

    
    public function contact()
    {
        return $this->belongsTo(Contact::class,'contact_uuid','contact_uuid');
    }
    
    /**
     * Get the all group the user belongs to
     *  returns Eloqeunt Object
     */
    public function user_groups()
    {
        return $this->hasMany(UserGroup::class,'user_uuid','user_uuid');
    }

    /**
     * Get all of the permission groups for the user.
     */
    public function groups()
    {
        $group_uuids = UserGroup::where('user_uuid', $this->user_uuid)->get();

        $groups = collect();
        foreach ($group_uuids as $group_uuid) {
            $groups->push($group_uuid->group);
        }

        return $groups;

    }

    /**
     * Get a collection of all domains for reseller
     */
    public function reseller_domains()
    {
        $domain_uuids = UserDomainPermission::where('user_uuid', $this->user_uuid)->get();

        $domains = collect();
        foreach ($domain_uuids as $domain_uuid) {
            $domains->push($domain_uuid->domain);
        }

        return $domains;

    }

    /**
     * Get all of user's advanced fields such as first name and last name stored in a separate table.
     */
    public function user_adv_fields()
    {
        return $this->hasOne(UserAdvFields::class,'user_uuid','user_uuid');
    }

     public function setting()
    {
        return $this->hasMany(UserSetting::class,'user_uuid','user_uuid');
    }

    /**
     * Get the domain to which this user belongs 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class,'domain_uuid','domain_uuid');
    }

    /**
     * Get all domain to which the user has reseller permissions
     */
    public function reseller_domain_permissions()
    {
        return $this->hasMany(UserDomainPermission::class,'user_uuid','user_uuid');
    }

}
