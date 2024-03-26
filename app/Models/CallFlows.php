<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallFlows extends Model
{
    use HasFactory;

    protected $table = "v_call_flows";

    public $timestamps = false;

    protected $primaryKey = 'call_flow_uuid';

    protected $keyType = 'string';
}
