<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VmNotifyNotification extends Model
{
    use \App\Models\Traits\TraitUuid;

    protected $table = 'vm_notify_notifications';
    protected $primaryKey = 'vm_notify_notification_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'vm_notify_profile_uuid',
        'voicemail_uuid',
        'voicemail_message_uuid',
        'status',
        'accepted_by_recipient_uuid',
        'accepted_by_number',
        'accepted_at',
        'current_retry',
        'current_priority',
        'max_retry_count',
        'retry_delay_minutes',
        'priority_delay_minutes',
        'caller_id_name',
        'caller_id_number',
        'mailbox',
        'message_length_seconds',
        'message_left_at',
        'message_file_path',
        'message_ext',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'message_left_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'success_email_sent_at' => 'datetime',
        'failure_email_sent_at' => 'datetime',
        'current_retry' => 'integer',
        'current_priority' => 'integer',
        'max_retry_count' => 'integer',
        'retry_delay_minutes' => 'integer',
        'priority_delay_minutes' => 'integer',
        'message_length_seconds' => 'integer',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(
            VmNotifyProfile::class,
            'vm_notify_profile_uuid',
            'vm_notify_profile_uuid'
        );
    }

    public function acceptedByRecipient(): BelongsTo
    {
        return $this->belongsTo(
            VmNotifyProfileRecipient::class,
            'accepted_by_recipient_uuid',
            'vm_notify_profile_recipient_uuid'
        );
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(
            VmNotifyAttempt::class,
            'vm_notify_notification_uuid',
            'vm_notify_notification_uuid'
        );
    }

    public function logs(): HasMany
    {
        return $this->hasMany(
            VmNotifyLog::class,
            'vm_notify_notification_uuid',
            'vm_notify_notification_uuid'
        )->orderBy('created_at');
    }

    public function latestLog(): HasMany
    {
        return $this->hasMany(
            VmNotifyLog::class,
            'vm_notify_notification_uuid',
            'vm_notify_notification_uuid'
        )->latest('created_at');
    }
}
