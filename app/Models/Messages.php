<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use libphonenumber\NumberParseException;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Messages extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "messages";

    protected $primaryKey = 'message_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'message_uuid',
        'extension_uuid',
        'domain_uuid',
        'source',
        'destination',
        'message',
        'direction',
        'type',
        'reference_id',
        'status',
        'created_at',
        'updated_at',
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
            unset($model->created_at_formatted);
            unset($model->source_formatted);
            unset($model->destination_formatted);
            // unset($model->send_notify_path);
        });

        static::retrieved(function ($model) {
            if ($model->created_at && $model->domain_uuid) {
                $time_zone = get_local_time_zone($model->domain_uuid);

                $model->created_at_formatted = Carbon::parse($model->created_at)->setTimezone($time_zone)->format('g:i:s A M d, Y');


                if ($model->source) {
                    $model->source_formatted = $model->formatPhoneNumber($model->source);
                }

                if ($model->destination) {
                    $model->destination_formatted = $model->formatPhoneNumber($model->destination);
                }

            }
            // $model->destroy_route = route('devices.destroy', $model);

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


    public function formatPhoneNumber($value)
    {
        //Get libphonenumber object
        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        //try to convert phone number to National format
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($value, 'US');
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                $number_formatted = $phoneNumberUtil
                    ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
            } else {
                $number_formatted = $value;
            }
        } catch (NumberParseException $e) {
            // Do nothing and leave the numbner as is
            $number_formatted = $value;
        }

        return $number_formatted;
    }
}
