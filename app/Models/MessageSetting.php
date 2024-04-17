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
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'destination',
        'carrier',
        'description',
        'chatplan_detail_data',
        'email',
    ];


    /**
     * The booted method of the model
     *
     * Define all attributes here like normal code

     */
    protected static function booted()
    {
        static::saving(function ($model) {
            // Remove 'destination_formatted' attribute before saving to database
            unset($model->destination_formatted);
        });

        static::retrieved(function ($model) {
            //Get libphonenumber object
            $phoneNumberUtil = PhoneNumberUtil::getInstance();

            $value = $model->destination;

            //try to convert phone number to National format
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($value, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $model->destination_formatted = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                } else {
                    $model->destination_formatted = $value;
                }
            } catch (NumberParseException $e) {
                // Do nothing and leave the numbner as is
                $model->destination_formatted = $value;
            }
            return $model;
        });
    }

    /**
     * Get domain that this message settings belongs to 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    /**
     * Get extenion that this message settings belongs to 
     * and ensure that extension matches chatplan_detail_data
     */
    // public function extension()
    // {
    //     logger($this->domain_uuid);
    //     $result =  $this->hasMany(Extensions::class, 'extension', 'chatplan_detail_data');
    //         // ->where('domain_uuid', $this->domain_uuid);

    //     logger($result->toSql());

    //     return $result;
    // }

    // /**
    //  * Get all group permissions
    //  */
    // public function permissions()
    // {
    //     return $this->hasMany(GroupPermissions::class,'group_uuid','group_uuid');
    // }


}
