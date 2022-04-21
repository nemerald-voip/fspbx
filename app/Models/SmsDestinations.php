<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsDestinations extends Model
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
        'enabled',
        'description',
        'chatplan_detail_data',
        'email'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [

    ];

    /**
     * Get the domain that owns this sms destination.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

}
