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

    // public function __construct(array $attributes = [])
    // {
    //     parent::__construct();
    //     $this->attributes['domain_uuid'] = Session::get('domain_uuid');
    //     $this->attributes['insert_date'] = date('Y-m-d H:i:s');
    //     $this->attributes['insert_user'] = Session::get('user_uuid');
    //     $this->fill($attributes);
    // }

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
