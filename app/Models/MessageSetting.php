<?php

namespace App\Models;

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use libphonenumber\NumberParseException;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MessageSetting extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_sms_destinations";

    public $timestamps = false;

    protected $primaryKey = 'sms_destination_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The booted method of the model
     *
     * Define all attributes here like normal code

     */
    protected static function booted()
    {
        static::retrieved(function ($messageSetting) {
            //Get libphonenumber object
            $phoneNumberUtil = PhoneNumberUtil::getInstance();

            $value = $messageSetting->destination;

            //try to convert phone number to National format
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($value, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $messageSetting->destination_formatted = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                } else {
                    $messageSetting->destination_formatted = $value;
                }
            } catch (NumberParseException $e) {
                // Do nothing and leave the numbner as is
                $messageSetting->destination_formatted = $value;
            }
            return $messageSetting;
        });
    }

    /**
     * Get domain that this message settings belongs to 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    // /**
    //  * Get all group permissions
    //  */
    // public function permissions()
    // {
    //     return $this->hasMany(GroupPermissions::class,'group_uuid','group_uuid');
    // }


}
