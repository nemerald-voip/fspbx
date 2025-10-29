<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CallTranscription extends Model
{
    use HasFactory, TraitUuid;
    
    protected $table = 'call_transcriptions';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'request_payload'   => 'array',
        'response_payload'  => 'array',
        'result_payload'    => 'array',
        'requested_at'      => 'datetime',
        'completed_at'      => 'datetime',
    ];

    protected $fillable = [
        'uuid','xml_cdr_uuid','domain_uuid','provider_key','external_id','status',
        'error_message','request_payload','response_payload','result_payload',
        'requested_at','completed_at',
    ];
}
