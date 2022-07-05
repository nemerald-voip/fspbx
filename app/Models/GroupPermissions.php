<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupPermissions extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_group_permissions";

    public $timestamps = false;

    protected $primaryKey = 'group_permission_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

}
