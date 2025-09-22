<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Spatie\WebhookClient\Models\WebhookCall;

class WhCall extends WebhookCall
{

    use \App\Models\Traits\TraitUuid;

    protected $table = "webhook_calls";

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $appends = [
        'created_at_formatted',
    ];

    public function getCreatedAtFormattedAttribute()
    {
        if (!$this->created_at) {
            return null;
        }
        $timeZone = auth()->user()->time_zone;
        return Carbon::parse($this->created_at)->setTimezone($timeZone)->format('g:i:s A M d, Y');
    }

}