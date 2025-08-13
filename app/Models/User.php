<?php

namespace App\Models;

use App\Models\UserGroup;
use Laravel\Sanctum\HasApiTokens;
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
        'api_key',
        'extension_uuid',
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
        'user_adv_fields',
        'settings'
    ];

    // always eager-load the relation
    protected $with = ['user_adv_fields', 'settings'];

    /* Automatically include this computed attribute on every model
    */
    protected $appends = ['name_formatted', 'language', 'time_zone'];


    /**
     * The attributes that should be cast.
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Mutator: if no domain_uuid is explicitly set, pull it from session
     */
    public function setDomainUuidAttribute($value)
    {
        $this->attributes['domain_uuid'] = $value ?: session('domain_uuid');
    }

    /**
     * Accessor: build name_formatted from advanced fields if available,
     * otherwise fall back to username
     */
    public function getNameFormattedAttribute(): string
    {
        // if relationship not yet loaded, lazy‐load it
        $adv = $this->user_adv_fields;
        if ($adv && ($adv->first_name || $adv->last_name)) {
            return trim(($adv->first_name ?? '') . ' ' . ($adv->last_name ?? ''));
        }

        return $this->username;
    }

    /**
     * Accessor: build first_name from advanced fields if available,
     * otherwise return emptry string
     */
    public function getFirstNameAttribute(): string
    {
        // if relationship not yet loaded, lazy‐load it
        $adv = $this->user_adv_fields;
        if ($adv && $adv->first_name) {
            return trim($adv->first_name ?? '');
        }

        return '';
    }

    /**
     * Accessor: build last_name from advanced fields if available,
     * otherwise return empty string
     */
    public function getLastNameAttribute(): string
    {
        // if relationship not yet loaded, lazy‐load it
        $adv = $this->user_adv_fields;
        if ($adv && $adv->last_name) {
            return trim($adv->last_name ?? '');
        }

        return '';
    }

    /**
     * Accessor: get the 'language' setting under category 'domain'
     */
    public function getLanguageAttribute(): ?string
    {
        $setting = $this->settings
            ->where('user_setting_category', 'domain')
            ->firstWhere('user_setting_subcategory', 'language');

        return $setting->user_setting_value ?? null;
    }

    /**
     * Accessor: get the 'time_zone' setting under category 'domain'
     */
    public function getTimeZoneAttribute(): ?string
    {
        $setting = $this->settings
            ->where('user_setting_category', 'domain')
            ->firstWhere('user_setting_subcategory', 'time_zone');

        return $setting->user_setting_value ?? null;
    }



    /**
     * Get the extension assigned to the user
     *  returns Eloqeunt Collection
     */
    public function extension()
    {
        return $this->belongsTo(Extensions::class, 'extension_uuid', 'extension_uuid');
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

    public function settings()
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

    /**
     * Get all of the locations for this user.
     * The name 'locationable' is the key we defined in the pivot table.
     */
    public function locations()
    {
        return $this->morphToMany(
            \App\Models\Location::class,   // related model
            'locationable',                // morph name -> uses locationable_type + locationable_id
            'locationables',               // pivot table
            'locationable_id',             // this model's id column on pivot
            'location_uuid',               // related model's id column on pivot 
            'user_uuid',                   // this model's local key
            'location_uuid'                // related model's local key
        );
    }
}
