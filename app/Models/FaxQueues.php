<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FaxQueues extends Model
{
    use HasFactory, TraitUuid;

    protected $table = "v_fax_queue";

    public $timestamps = false;

    protected $primaryKey = 'fax_queue_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = ['fax_queue_uuid'];


    /**
     * The booted method of the model
     *
     * Define all attributes here like normal code

     */
    protected static function booted()
    {
        static::saving(function ($model) {
            // Remove attributes before saving to database
            unset($model->fax_date_formatted);
            unset($model->fax_retry_date_formatted);
            unset($model->fax_notify_date_formatted);
        });

        static::retrieved(function ($model) {
            $time_zone = get_local_time_zone($model->domain_uuid);
            if ($model->fax_date && $model->domain_uuid) {
                $model->fax_date_formatted = Carbon::parse($model->fax_date)->setTimezone($time_zone)->format('g:i:s A M d, Y');
            }

            if ($model->fax_retry_date && $model->domain_uuid) {
                $model->fax_retry_date_formatted = Carbon::parse($model->fax_retry_date)->setTimezone($time_zone)->format('g:i:s A M d, Y');
            }

            if ($model->fax_notify_date && $model->domain_uuid) {
                $model->fax_notify_date_formatted = Carbon::parse($model->fax_notify_date)->setTimezone($time_zone)->format('g:i:s A M d, Y');
            }

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

    public function faxFile()
    {
        return $this->hasOne(FaxFiles::class,  'fax_file_path', 'fax_file');
    }

    public function getFaxFile()
    {
        return $this->faxFile()->first();
    }
}
