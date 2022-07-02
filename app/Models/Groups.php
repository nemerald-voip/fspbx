<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Groups extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;
    
    protected $table = "v_groups";

    public $timestamps = false;

    protected $primaryKey = 'group_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


}
