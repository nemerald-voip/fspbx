<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WhitelistedNumbers extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "whitelisted_numbers";

    public $timestamps = true;

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * The booted method of the model
     *
     * Define all attributes here like normal code

     */
    protected static function booted()
    {

        static::saving(function ($model) {
            if (!$model->domain_uuid) {
                $model->domain_uuid = session('domain_uuid');
            }
            // unset($model->destroy_route);
        });

        static::retrieved(function ($model) {
            $model->destroy_route = route('whitelisted-numbers.destroy', $model);
            
            $time_zone = get_local_time_zone($model->domain_uuid);
            if ($model->created_at) {
                $model->created_at_formatted = $model->created_at
                    ->timezone($time_zone)
                    ->format('M d, Y g:i:s A');
            }
        });
    }



    /**
     * Get the domain to which this voicemail belongs
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }
}
