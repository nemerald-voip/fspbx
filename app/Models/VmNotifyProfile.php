<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VmNotifyProfile extends Model
{
    protected $table = 'vm_notify_profiles';
    protected $primaryKey = 'vm_notify_profile_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'voicemail_uuid',
        'name',
        'description',
        'enabled',
        'outbound_cid_mode',
        'fixed_caller_id_number',
        'fixed_caller_id_name',
        'internal_caller_id_name',
        'retry_count',
        'retry_delay_minutes',
        'priority_delay_minutes',
        'email_from',
        'email_success',
        'email_fail',
        'email_attach',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'email_attach' => 'boolean',
        'email_success' => 'array',
        'email_fail' => 'array',
        'retry_count' => 'integer',
        'retry_delay_minutes' => 'integer',
        'priority_delay_minutes' => 'integer',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(
            VmNotifyProfileRecipient::class,
            'vm_notify_profile_uuid',
            'vm_notify_profile_uuid'
        )->orderByRaw('COALESCE(priority, 0), COALESCE(sort_order, 0), created_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(
            VmNotifyNotification::class,
            'vm_notify_profile_uuid',
            'vm_notify_profile_uuid'
        );
    }

    public function enabledRecipients(): HasMany
    {
        return $this->recipients()->where('enabled', true);
    }
}