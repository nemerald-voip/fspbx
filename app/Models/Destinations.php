<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class Destinations extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_destinations";

    public $timestamps = false;

    protected $primaryKey = 'destination_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'destination_uuid',
        'domain_uuid',
        'dialplan_uuid',
        'fax_uuid',
        'user_uuid',
        'destination_type',
        'destination_number',
        'destination_trunk_prefix',
        'destination_area_code',
        'destination_prefix',
        'destination_condition_field',
        'destination_number_regex',
        'destination_caller_id_name',
        'destination_caller_id_number',
        'destination_cid_name_prefix',
        'destination_context',
        'destination_record',
        'destination_hold_music',
        'destination_accountcode',
        'destination_type_voice',
        'destination_type_fax',
        'destination_type_emergency',
        'destination_type_text',
        'destination_app',
        'destination_data',
        'destination_alternate_app',
        'destination_alternate_data',
        'destination_order',
        'destination_enabled',
        'destination_description',
        'group_uuid',
    ];

    // public function getDestinationNumberAttribute($value): ?string
    // {
    //     //Get libphonenumber object
    //     $phoneNumberUtil = PhoneNumberUtil::getInstance();

    //     //try to convert phone number to National format
    //     try {
    //         $phoneNumberObject = $phoneNumberUtil->parse($value, 'US');
    //         if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
    //             return $phoneNumberUtil
    //                 ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
    //         } else {
    //             return $value;
    //         }
    //     } catch (NumberParseException $e) {

    //         // Do nothing and leave the number as is
    //         return $value;
    //     }
    // }

    // public function getDestinationCallerIdNumberAttribute($value): ?string
    // {
    //     //Get libphonenumber object
    //     $phoneNumberUtil = PhoneNumberUtil::getInstance();

    //     //try to convert phone number to National format
    //     try {
    //         $phoneNumberObject = $phoneNumberUtil->parse($value, 'US');
    //         if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
    //             return $phoneNumberUtil
    //                 ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
    //         } else {
    //             return $value;
    //         }
    //     } catch (NumberParseException $e) {
    //         // Do nothing and leave the number as is
    //         return $value;
    //     }
    // }
}
