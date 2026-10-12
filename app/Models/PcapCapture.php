<?php

namespace App\Models;

use App\Models\Traits\TraitUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PcapCapture extends Model
{
    use HasFactory, TraitUuid;

    public const STATUS_READY = 'ready';
    public const STATUS_EMPTY = 'empty';
    public const STATUS_FAILED = 'failed';

    protected $table = 'pcap_captures';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'pcap_session_uuid',
        'domain_uuid',
        'call_uuid',
        'node',
        'direction',
        'caller_id_name',
        'caller_id_number',
        'destination_number',
        'call_started_at',
        'duration',
        'legs',
        'status',
        'file_path',
        'file_size',
        'packet_count',
        'error',
    ];

    protected $casts = [
        'call_started_at' => 'datetime',
        'duration' => 'integer',
        'legs' => 'integer',
        'file_size' => 'integer',
        'packet_count' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(PcapSession::class, 'pcap_session_uuid', 'uuid');
    }
}
