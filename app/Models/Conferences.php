<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conferences extends Model
{
    use HasFactory, \App\Models\Traits\GeneratesUniqueExtensions;

    protected $table = "v_conferences";

    public $timestamps = false;

    protected $primaryKey = 'conference_uuid';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'conference_uuid',
        'dialplan_uuid',
        'conference_name',
        'conference_extension',
        'conference_pin_number',
        'conference_profile',
        'conference_email_address',
        'conference_account_code',
        'conference_flags',
        'conference_order',
        'conference_description',
        'conference_enabled',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    public function dialplan(): BelongsTo
    {
        return $this->belongsTo(Dialplans::class, 'dialplan_uuid', 'dialplan_uuid');
    }

    public function generateUniqueSequenceNumber(): ?string
    {
        return $this->firstAvailableExtensionInRange(9350, 9399);
    }
}
