<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DialplanDetails extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;
    
    protected $table = "v_dialplan_details";

    public $timestamps = false;

    protected $primaryKey = 'dialplan_detail_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


}
