<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DefaultSettings extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;
    
    protected $table = "v_default_settings";

    public $timestamps = false;

    protected $primaryKey = 'default_setting_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


}
