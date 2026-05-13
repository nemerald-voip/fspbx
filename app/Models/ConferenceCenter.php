<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceCenter extends Model
{
    use HasFactory, \App\Models\Traits\GeneratesUniqueExtensions;

    protected $table = "v_conference_centers";

    public $timestamps = false;

    protected $primaryKey = 'conference_center_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'conference_center_uuid',
        'dialplan_uuid',
        'conference_center_name',
        'conference_center_extension',
        'conference_center_greeting',
        'conference_center_pin_length',
        'conference_center_enabled',
        'conference_center_description',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function dialplan()
    {
        return $this->belongsTo(Dialplans::class, 'dialplan_uuid', 'dialplan_uuid');
    }

    public function generateUniqueSequenceNumber(): ?string
    {
        return $this->firstAvailableExtensionInRange(9300, 9349);
    }
}
