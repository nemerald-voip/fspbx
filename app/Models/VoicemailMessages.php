<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VoicemailMessages extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = 'v_voicemail_messages';

    public $timestamps = false;

    protected $primaryKey = 'voicemail_message_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $appends = [
        'created_epoch_formatted',
    ];

    protected function createdEpochFormatted(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->created_epoch) {
                    return null;
                }

                $timeZone = get_local_time_zone($this->domain_uuid);

                return Carbon::createFromTimestamp($this->created_epoch)
                    ->setTimezone($timeZone)
                    ->format('g:i:s A M d, Y');
            }
        );
    }

    public function voicemail()
    {
        return $this->hasOne(Voicemails::class, 'voicemail_uuid', 'voicemail_uuid');
    }

    public function domain()
    {
        return $this->hasOne(Domain::class, 'domain_uuid', 'domain_uuid');
    }
}