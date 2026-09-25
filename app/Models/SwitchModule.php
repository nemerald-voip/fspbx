<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwitchModule extends Model
{
    protected $table = 'v_modules';

    protected $primaryKey = 'module_uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    // Retained in the legacy schema, but not used by the module API.
    protected $hidden = ['module_default_enabled'];
}
