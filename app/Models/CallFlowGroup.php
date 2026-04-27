<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallFlowGroup extends Model
{
    use HasFactory, TraitUuid;

    protected $table = 'call_flow_groups';

    public $timestamps = false;

    protected $primaryKey = 'call_flow_group_uuid';

    protected $fillable = [
        'domain_uuid',
        'call_flow_group_name',
        'call_flow_group_description',
    ];
}
