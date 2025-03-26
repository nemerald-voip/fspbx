<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contact extends Model
{
    use HasApiTokens, HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_contacts";

    public $timestamps = false;

    protected $primaryKey = 'contact_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'contact_parent_uuid',
        'contact_type',
        'contact_organization',
        'contact_name_given',
        'contact_name_family',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user'
    ];


    /**
     * Get the Contact Phones objects associated with this contact.
     *  returns Eloquent Object
     */
    public function phones()
    {
        return $this->hasMany(ContactPhones::class, 'contact_uuid', 'contact_uuid');
    }

    public function primaryPhone()
    {
        return $this->hasOne(ContactPhones::class, 'contact_uuid', 'contact_uuid')->orderBy('insert_date', 'asc');
    }

    /**
     * Get the Contact Users objects associated with this contact.
     *  returns Eloquent Object
     */
    public function contact_users()
    {
        return $this->hasMany(ContactUsers::class, 'contact_uuid', 'contact_uuid');
    }
}
