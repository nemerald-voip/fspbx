<?php

namespace App\Models;

use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContactPhones extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_contact_phones";

    public $timestamps = false;

    protected $primaryKey = 'contact_phone_uuid';
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
        'phone_type_voice',
        'phone_number',
        'phone_speed_dial',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user'
    ];

    protected $appends = ['phone_number_formatted'];

    /**
     * Accessor: Get phone number formatted
     */
    public function getPhoneNumberFormattedAttribute()
    {
        return formatPhoneNumber($this->phone_number, "US", PhoneNumberFormat::NATIONAL);
    }

    /**
     * Get the Device Lines objects associated with this device.
     *  returns Eloquent Object
     */
    public function contact()
    {
        return $this->hasOne(Contact::class, 'contact_uuid', 'contact_uuid');
    }
}
