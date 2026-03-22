<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VmNotifyProfileRecipient extends Model
{
    use \App\Models\Traits\TraitUuid;
    
    protected $table = 'vm_notify_profile_recipients';
    protected $primaryKey = 'vm_notify_profile_recipient_uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'domain_uuid',
        'vm_notify_profile_uuid',
        'recipient_type',
        'extension_uuid',
        'phone_number',
        'display_name',
        'priority',
        'sort_order',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'priority' => 'integer',
        'sort_order' => 'integer',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(
            VmNotifyProfile::class,
            'vm_notify_profile_uuid',
            'vm_notify_profile_uuid'
        );
    }

    public function notificationAttempts()
    {
        return $this->hasMany(
            VmNotifyAttempt::class,
            'vm_notify_profile_recipient_uuid',
            'vm_notify_profile_recipient_uuid'
        );
    }

    public function extension(): BelongsTo
    {
        return $this->belongsTo(
            Extensions::class,
            'extension_uuid',
            'extension_uuid'
        );
    }

    public function getResolvedDestinationAttribute(): ?string
    {
        if ($this->recipient_type === 'extension' && $this->extension) {
            return $this->extension->extension ?? null;
        }

        return $this->phone_number;
    }
}