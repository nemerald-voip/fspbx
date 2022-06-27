<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FreeswitchSettings extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_settings";

    public $timestamps = false;

    protected $primaryKey = 'setting_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
}
