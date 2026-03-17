<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VmNotifyAttempt extends Model
{
    protected $table = 'vm_notify_attempts';
    protected $primaryKey = 'vm_notify_attempt_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'vm_notify_notification_uuid',
        'vm_notify_profile_recipient_uuid',
        'retry_number',
        'priority',
        'destination',
        'call_uuid',
        'status',
        'answered_at',
        'ended_at',
        'dtmf_sequence',
        'claim_attempted_at',
        'claim_result',
        'notes',
    ];

    protected $casts = [
        'retry_number' => 'integer',
        'priority' => 'integer',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'claim_attempted_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(
            VmNotifyNotification::class,
            'vm_notify_notification_uuid',
            'vm_notify_notification_uuid'
        );
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(
            VmNotifyProfileRecipient::class,
            'vm_notify_profile_recipient_uuid',
            'vm_notify_profile_recipient_uuid'
        );
    }
}