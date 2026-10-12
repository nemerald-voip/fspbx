<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Model;

class PcapCallLeg extends Model
{
    use TraitUuid;

    protected $table = 'pcap_call_legs';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'call_uuid',
        'channel_uuid',
        'domain_uuid',
        'node',
        'linked_uuids',
        'channel_direction',
        'direction',
        'caller_id_name',
        'caller_id_number',
        'destination_number',
        'numbers',
        'sip_call_id',
        'local_media_port',
        'remote_media_ip',
        'remote_media_port',
        'created_epoch',
        'hangup_epoch',
        'processed_at',
    ];

    protected $casts = [
        'numbers' => 'array',
        'linked_uuids' => 'array',
        'local_media_port' => 'integer',
        'remote_media_port' => 'integer',
        'created_epoch' => 'integer',
        'hangup_epoch' => 'integer',
        'processed_at' => 'datetime',
    ];
}
