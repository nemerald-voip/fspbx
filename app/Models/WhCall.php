<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Spatie\WebhookClient\Models\WebhookCall;

class WhCall extends WebhookCall
{

    use \App\Models\Traits\TraitUuid;

    protected $table = "webhook_calls";

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

}