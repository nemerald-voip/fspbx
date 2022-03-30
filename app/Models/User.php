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

}
