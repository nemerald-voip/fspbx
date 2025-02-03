<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class GreetingDeleted
{
    use Dispatchable;

    public $uuid;
    public $domain_uuid;
    public $file_name;

    public function __construct($uuid, $domain_uuid, $file_name)
    {
        $this->uuid = $uuid;
        $this->domain_uuid = $domain_uuid;
        $this->file_name = $file_name;
    }
}
