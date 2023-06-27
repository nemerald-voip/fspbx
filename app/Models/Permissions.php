<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permissions extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_permissions";

    public $timestamps = false;

    protected $primaryKey = 'permission_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

}
