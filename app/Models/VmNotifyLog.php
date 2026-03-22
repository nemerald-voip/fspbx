<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VmNotifyLog extends Model
{
    use \App\Models\Traits\TraitUuid;
    
    protected $table = 'vm_notify_logs';
    protected $primaryKey = 'vm_notify_log_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'vm_notify_notification_uuid',
        'level',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(
            VmNotifyNotification::class,
            'vm_notify_notification_uuid',
            'vm_notify_notification_uuid'
        );
    }
}