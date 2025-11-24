<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
        'domain_uuid',
        'carrier',
        'description',
        'chatplan_detail_data',
        'email',
        'enabled',
    ];

    protected $appends = [
        'destination_formatted',
    ];

    public function getDestinationFormattedAttribute()
    {
        return $this->destination ? formatPhoneNumber($this->destination) : null;
    }

    /**
     * Get domain that this message settings belongs to 
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }


}
