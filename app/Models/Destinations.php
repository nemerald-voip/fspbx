<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
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
        'destination_actions',
        'destination_conditions',
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

    public function __construct(array $attributes = [])
    {
        parent::__construct();
        $this->attributes['domain_uuid'] = Session::get('domain_uuid');
        $this->attributes['insert_date'] = date('Y-m-d H:i:s');
        $this->attributes['insert_user'] = Session::get('user_uuid');
        $this->fill($attributes);
    }

    /**
     * The booted method of the model
     *
     * Define all attributes here like normal code

     */
    protected static function booted()
    {
        static::saving(function ($model) {
            // Remove attributes before saving to database
            unset($model->destination_number_formatted);
            unset($model->destroy_route);
            unset($model->destination_actions_formatted);
            $model->update_date = date('Y-m-d H:i:s');
            $model->update_user = Session::get('user_uuid');

            if(!empty($model->destination_actions)) {
                $actions = [];
                foreach ($model->destination_actions as $action) {
                    if(! empty($action['value']['value'])) {
                        $actions[] = [
                            'destination_app' => 'transfer',
                            'destination_data' => $action['value']['value'],
                        ];
                    }
                }
                $model->destination_actions = json_encode($actions);
            } else {
                $model->destination_actions = null;
            }

            if(!empty($model->destination_conditions)) {
                $conditions = [];
                foreach ($model->destination_conditions as $condition) {
                    if(! empty($condition['value']['value'])) {
                        $conditions[] = [
                            'condition_field' => $condition['condition_field'],
                            'condition_expression' => $condition['condition_expression'],
                            'condition_app' => 'transfer',
                            'condition_data' => $condition['value']['value']
                        ];
                    }
                }
                $model->destination_conditions = json_encode($conditions);
            } else {
                $model->destination_conditions = null;
            }
        });

        static::retrieved(function ($model) {
            if ($model->destination_number) {
                $phoneNumberUtil = PhoneNumberUtil::getInstance();
                try {
                    $phoneNumberObject = $phoneNumberUtil->parse($model->destination_number, 'US');
                    if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                        $model->destination_number_formatted = $phoneNumberUtil
                            ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                    } else {
                        $model->destination_number_formatted = $model->destination_number;
                    }
                } catch (NumberParseException $e) {
                    $model->destination_number_formatted = $model->destination_number;
                }
            }

            //$timeoutDestinations = getTimeoutDestinations();
            //'timeout_destinations_categories' => array_values($timeoutDestinations['categories']),
            //'timeout_destinations_targets' => $timeoutDestinations['targets']

            if ($model->destination_actions) {
                $model->destination_actions_formatted = getTimeoutDestinationsLabels($model->destination_actions);
                $actions = [];
                foreach ($model->destination_actions as $action) {
                    if (!empty($action['destination_data'])) {
                        $actions[] = [
                            'value' => ['value' => $action['destination_data']],
                        ];
                    }
                }
                $model->destination_actions = $actions;
            }

            if ($model->destination_conditions) {
                $conditions = [];
                foreach ($model->destination_conditions as $condition) {
                    if (!empty($condition['condition_data'])) {
                        $conditions[] = [
                            'condition_field' => $condition['condition_field'],
                            'condition_expression' => $condition['condition_expression'],
                            'value' => ['value' => $condition['condition_data']],
                        ];
                    }
                }
                $model->destination_conditions = $conditions;
            }

            $model->destroy_route = route('phone-numbers.destroy', ['phone_number' => $model->destination_uuid]);

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

    public function fax()
    {
        return $this->belongsTo(Faxes::class, 'fax_uuid', 'fax_uuid');
    }

    /**
     * Force to use it, cause laravel's casting method doesn't determine string 'false' as a valid boolean value.
     *
     * @param  string|null  $value
     * @return bool
     */
    public function getDestinationEnabledAttribute(?string $value): bool
    {
        return $value === 'true';
    }

    /**
     * Force to use it, cause laravel's casting method doesn't determine string 'false' as a valid boolean value.
     *
     * @param  string|null  $value
     * @return bool
     */
    public function getDestinationRecordAttribute(?string $value): bool
    {
        return $value === 'true';
    }

    /**
     * Set the destination_enabled attribute.
     *
     * @param  bool $value
     * @return void
     */
    public function setDestinationEnabledAttribute($value): void
    {
        $this->attributes['destination_enabled'] = $value ? 'true' : 'false';
    }

    /**
     * Set the destination_record attribute.
     *
     * @param  bool $value
     * @return void
     */
    public function setDestinationRecordAttribute($value): void
    {
        $this->attributes['destination_record'] = $value ? 'true' : 'false';
    }

    // Ensure `destination_actions` is treated as an array.
    public function getDestinationActionsAttribute($value)
    {
        if(is_string($value)) {
            return json_decode($value, true);
        }

        return $value;
    }

    // Ensure `destination_conditions` is treated as an array.
    public function getDestinationConditionsAttribute($value)
    {
        if(is_string($value)) {
            return json_decode($value, true);
        }

        return $value;
    }

    // Ensure `destination_actions` is stored as json string in database.
    public function setDestinationActionsAttribute($value)
    {
        if(is_array($value)) {
            $this->attributes['destination_actions'] = json_encode($value);
        }else{
            $this->attributes['destination_actions'] = $value;
        }
    }

    // Ensure `destination_conditions` is stored as json string in database.
    public function setDestinationConditionsAttribute($value)
    {
        if(is_array($value)) {
            $this->attributes['destination_conditions'] = json_encode($value);
        }else{
            $this->attributes['destination_conditions'] = $value;
        }
    }

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
