<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VoicemailMessages extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_voicemail_messages";

    public $timestamps = false;

    protected $primaryKey = 'voicemail_message_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->insert_date = date('Y-m-d H:i:s');
            $model->insert_user = session('user_uuid');
        });

        static::saving(function ($model) {
            if (!$model->domain_uuid) {
                $model->domain_uuid = session('domain_uuid');
            }
            unset($model->destroy_route);
            unset($model->created_epoch);
            // unset($model->message_route);
        });

        static::retrieved(function ($model) {
            $model->destroy_route = route('voicemails.messages.destroy', $model);
            // $model->message_route = route('voicemail.message', $model);


            if ($model->created_epoch) {
                $time_zone = get_local_time_zone($model->domain_uuid);
                $model->created_epoch_formatted = Carbon::createFromTimestamp($model->created_epoch)
                ->setTimezone($time_zone)
                ->format('g:i:s A M d, Y');
            }

        });
    }


    /**
     * Get the voicemail this message belongs to.
     */
    public function voicemail()
    {
        return $this->hasOne(Voicemails::class,'voicemail_uuid','voicemail_uuid');
    }

    /**
     * Get the domain this message belongs to.
     */
    public function domain()
    {
        return $this->hasOne(Domain::class,'domain_uuid','domain_uuid');
    }
}
