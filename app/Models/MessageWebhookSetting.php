<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageWebhookSetting extends Model
{
    use Traits\TraitUuid;

    protected $primaryKey = 'message_webhook_setting_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['signing_secret'];
    protected $casts = ['signing_secret' => 'encrypted'];
}
