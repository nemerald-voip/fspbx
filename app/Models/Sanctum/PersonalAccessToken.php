<?php

namespace App\Models\Sanctum;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    // This makes Sanctum generare UUID as ID instead of Bigint
    //public $incrementing = false;
    use \App\Models\Traits\TraitUuid;
    protected $primaryKey = "id";
    protected $keyType = "string";
}
