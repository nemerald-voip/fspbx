<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Activitylog\Traits\CausesActivity;
use App\Models\Traits\Fortify\EmailChallengable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, \App\Models\Traits\TraitUuid, TwoFactorAuthenticatable, EmailChallengable, CausesActivity;

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
        'add_user',
        'api_key'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_key',
        'insert_date',
        'insert_user',
        'salt',
        'update_date',
        'update_user',
        'add_date',
        'add_user',
        'contact_uuid',
        'user_totp_secret',
        'user_type',
        'user_status',
    ];

    /**
     * The booted method of the model
     *
     * Define all attributes here like normal code

     */
    protected static function booted()
    {
        static::saving(function ($model) {
            // Remove attributes before saving to database
            unset($model->name_formatted);
            if (!$model->domain_uuid) {
                $model->domain_uuid = session('domain_uuid');
            }
        });

        static::retrieved(function ($model) {
            if (Schema::hasTable('users_adv_fields')) {
                if ($model->user_adv_fields && ($model->user_adv_fields->first_name || $model->user_adv_fields->last_name)) {
                    $model->name_formatted = trim(($model->user_adv_fields->first_name ?? '') . ' ' . ($model->user_adv_fields->last_name ?? ''));
                } else {
                    $model->name_formatted = $model->username;
                }
                // $model->destroy_route = route('devices.destroy', $model);
            } else {
                $model->name_formatted = $model->username;
            }
            return $model;
        });
    }

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
            ->join('v_extension_users', 'v_extension_users.extension_uuid', '=', 'v_extensions.extension_uuid')
            ->where('v_extension_users.user_uuid', '=', $this->user_uuid)
            ->get([
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
        return $this->belongsTo(Contact::class, 'contact_uuid', 'contact_uuid');
    }

    /**
     * Get the all group the user belongs to
     *  returns Eloqeunt Object
     */
    public function user_groups()
    {
        return $this->hasMany(UserGroup::class, 'user_uuid', 'user_uuid');
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
     * Get all of user's advanced fields such as first name and last name stored in a separate table.
     */
    public function user_adv_fields()
    {
        return $this->hasOne(UserAdvFields::class, 'user_uuid', 'user_uuid');
    }

    public function getTwoFactorSecretAttribute()
    {
        // Attempt to load the two_factor_secret from UserAdvFields if not loaded already.
        // This uses lazy loading; consider eager loading in the query if performance is a concern.
        return $this->user_adv_fields->two_factor_secret ?? null;
    }

    public function getTwoFactorConfirmedAtAttribute()
    {
        // Attempt to load the two_factor_confirmed_at from UserAdvFields if not loaded already.
        // This uses lazy loading; consider eager loading in the query if performance is a concern.
        return $this->user_adv_fields->two_factor_confirmed_at ?? null;
    }

    public function getTwoFactorRecoveryCodesAttribute()
    {
        // Attempt to load the two_factor_recovery_codes from UserAdvFields if not loaded already.
        // This uses lazy loading; consider eager loading in the query if performance is a concern.
        return $this->user_adv_fields->two_factor_recovery_codes ?? null;
    }

    public function getTwoFactorCookiesAttribute()
    {
        // Attempt to load the two_factor_cookies from UserAdvFields if not loaded already.
        // This uses lazy loading; consider eager loading in the query if performance is a concern.
        return $this->user_adv_fields->two_factor_cookies ?? null;
    }

    public function setting()
    {
        return $this->hasMany(UserSetting::class, 'user_uuid', 'user_uuid');
    }

    /**
     * Get the domain to which this user belongs 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    /**
     * Get all domain to which the user has reseller permissions
     */
    public function domain_permissions()
    {
        return $this->hasMany(UserDomainPermission::class, 'user_uuid', 'user_uuid');
    }

    /**
     * Get all domain groups to which the user has permissions
     */
    public function domain_group_permissions()
    {
        return $this->hasMany(UserDomainGroupPermissions::class, 'user_uuid', 'user_uuid');
    }
}
