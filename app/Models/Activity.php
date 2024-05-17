<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Activity as SpatieActivity;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

class Activity extends SpatieActivity implements ActivityContract
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    public $incrementing = false;
    protected $keyType = 'string';

}
