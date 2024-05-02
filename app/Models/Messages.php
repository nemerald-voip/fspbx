<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
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
            // unset($model->destroy_route);
            // unset($model->send_notify_path);
        });

        static::retrieved(function ($model) {
            if ($model->created_at && $model->domain_uuid) {
                $time_zone = get_local_time_zone($model->domain_uuid);

                $model->created_at_formatted = Carbon::parse($model->created_at)->setTimezone($time_zone);

            }
            // $model->destroy_route = route('devices.destroy', $model);

            return $model;
        });
    }

}