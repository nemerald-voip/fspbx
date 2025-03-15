<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactUsers extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_contact_users";

    public $timestamps = false;

    protected $primaryKey = 'contact_user_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'contact_uuid',
        'user_uuid',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user'
    ];

    /**
     * Get the Device Lines objects associated with this device.
     *  returns Eloquent Object
     */
    public function contact()
    {
        return $this->hasOne(Contact::class, 'contact_uuid', 'contact_uuid');
    }

    /**
     * Get the Device Lines objects associated with this device.
     *  returns Eloquent Object
     */
    public function user()
    {
        return $this->hasOne(User::class, 'user_uuid', 'user_uuid');
    }

}
