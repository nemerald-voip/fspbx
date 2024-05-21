<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Activity as SpatieActivity;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class Activity extends SpatieActivity implements ActivityContract
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    public $incrementing = false;
    protected $keyType = 'string';

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
            if (!$model->domain_uuid) {
                $model->domain_uuid = session('domain_uuid');
            }

        });

        static::retrieved(function ($model) {
            if ($model->created_at && $model->domain_uuid) {
                $time_zone = get_local_time_zone($model->domain_uuid);

                $model->created_at_formatted = Carbon::parse($model->created_at)->setTimezone($time_zone)->format('g:i:s A M d, Y');

            }
            // $model->destroy_route = route('devices.destroy', $model);

            return $model;
        });
    }

    /**
     * Get domain that this model belongs to 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

}
