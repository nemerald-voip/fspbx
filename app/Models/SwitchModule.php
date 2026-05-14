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
}
