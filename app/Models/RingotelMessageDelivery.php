<?php

namespace App\Models;

class RingotelMessageDelivery extends RingotelMessageSync
{
    protected $table = 'ringotel_message_deliveries';
    use \App\Models\Traits\TraitUuid;

    protected $primaryKey = 'ringotel_message_delivery_uuid';
}
