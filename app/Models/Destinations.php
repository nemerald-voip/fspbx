<?php

namespace App\Models;

use App\Services\ActionsService;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use App\Services\DestinationDataService;
use libphonenumber\NumberParseException;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'destination_distinctive_ring',
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
            unset($model->routing_options);
            $model->update_date = date('Y-m-d H:i:s');
            $model->update_user = Session::get('user_uuid');
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

            if (!empty($model->destination_actions)) {

                $destinationDataService = new DestinationDataService();

                $model->routing_options = $destinationDataService->reverseEngineerDestinationActions($model->destination_actions);

                logger($model->routing_options);
            }

            if (!empty($model->destination_conditions)) {
                $model->destination_conditions = json_decode($model->destination_conditions, true);
                if(is_array($model->destination_conditions)) {
                    $conditions = null;
                    foreach ($model->destination_conditions as $condition) {
                        if (!empty($condition['condition_data'])) {
                            $conditions[] = [
                                'condition_field' => $condition['condition_field'],
                                'condition_expression' => $condition['condition_expression'],
                                'condition_target' => [
                                    'targetValue' => $condition['condition_data']
                                ],
                            ];
                        }
                    }
                    $model->destination_conditions = $conditions;
                }
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
}
