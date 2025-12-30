<?php

namespace App\Models;

use Spatie\Activitylog\LogOptions;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Database\Eloquent\Model;
use App\Services\CallRoutingOptionsService;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Extensions extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid, LogsActivity;

    protected $table = "v_extensions";

    public $timestamps = false;

    protected $primaryKey = 'extension_uuid';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $forwardOptionDetailsCache = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'extension_uuid',
        'domain_uuid',
        'extension',
        'number_alias',
        'password',
        'accountcode',
        'effective_caller_id_name',
        'effective_caller_id_number',
        'outbound_caller_id_name',
        'outbound_caller_id_number',
        'emergency_caller_id_name',
        'emergency_caller_id_number',
        'directory_first_name',
        'directory_last_name',
        'directory_visible',
        'directory_exten_visible',
        'max_registrations',
        'limit_max',
        'limit_destination',
        'missed_call_app',
        'missed_call_data',
        'user_context',
        'toll_allow',
        'call_timeout',
        'call_group',
        'call_screen_enabled',
        'user_record',
        'hold_music',
        'auth_acl',
        'cidr',
        'sip_force_contact',
        'nibble_account',
        'sip_force_expires',
        'mwi_account',
        'sip_bypass_media',
        'unique_id',
        'dial_string',
        'dial_user',
        'dial_domain',
        'do_not_disturb',
        'forward_all_destination',
        'forward_all_enabled',
        'forward_busy_destination',
        'forward_busy_enabled',
        'forward_no_answer_destination',
        'forward_no_answer_enabled',
        'forward_user_not_registered_destination',
        'forward_user_not_registered_enabled',
        'follow_me_uuid',
        'follow_me_enabled',
        'enabled',
        'description',
        'absolute_codec_string',
        'force_ping',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user',
    ];

    protected $appends = ['name_formatted', 'suspended', 'email'];
    protected $with = ['advSettings'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'advSettings'
    ];

    protected $attributes = [
        'enabled' => 'true',
        'do_not_disturb' => 'false',
        'call_timeout' => '25',
        'call_screen_enabled' => 'false',
        'limit_max' => '5',
        'limit_destination' => '!USER_BUSY',
        'force_ping' => 'false',
        'user_record' => null,
    ];

    // Accessor for name_formatted
    public function getNameFormattedAttribute()
    {
        return !empty($this->effective_caller_id_name)
            ? trim($this->extension . ' - ' . $this->effective_caller_id_name)
            : trim($this->extension);
    }

    // Accessor for suspended
    public function getSuspendedAttribute()
    {
        return $this->advSettings ? (bool) ($this->advSettings->suspended ?? false) : false;
    }

    public function getEmailAttribute()
    {
        if ($this->relationLoaded('voicemail') && $this->voicemail) {
            // Only return if domain matches
            if ($this->voicemail->domain_uuid === $this->domain_uuid) {
                return $this->voicemail->voicemail_mail_to;
            }
        }
        return null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('extension')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept([
                'create_date',
                'create_user',
                'update_date',
                'update_user',
                'password',
            ]);
        // Chain fluent methods for configuration options
    }

    public function getOutboundCallerIdNumberFormattedAttribute()
    {
        return formatPhoneNumber($this->outbound_caller_id_number, 'US', PhoneNumberFormat::NATIONAL);
    }

    public function getEmergencyCallerIdNumberE164Attribute()
    {
        return formatPhoneNumber($this->emergency_caller_id_number, 'US', PhoneNumberFormat::E164);
    }

    public function getOutboundCallerIdNumberE164Attribute()
    {
        return formatPhoneNumber($this->outbound_caller_id_number, 'US', PhoneNumberFormat::E164);
    }

    /**
     * This gets you “all possible” voicemails where ID matches extension, regardless of domain.
     * Further filtering by domain is REQUIRED to avoid false positives and PERFORMANCE ISSUES  
     */
    public function voicemail()
    {
        return $this->hasOne(Voicemails::class, 'voicemail_id', 'extension');
    }

    /**
     * Get the Extension User object associated with this extension.
     *  returns Eloqeunt Object
     */
    public function extension_users()
    {
        return $this->hasMany(ExtensionUser::class, 'extension_uuid', 'extension_uuid');
    }

    /**
     * Get the Mobile App object associated with this extension.
     *  returns Eloqeunt Object
     */
    public function mobile_app()
    {
        return $this->hasOne(MobileAppUsers::class, 'extension_uuid', 'extension_uuid');
    }

    /**
     * Get the advanced settings with this extension.
     *  returns Eloqeunt Object
     */
    public function advSettings()
    {
        return $this->hasOne(ExtensionAdvSettings::class, 'extension_uuid', 'extension_uuid');
    }

    /**
     * Get all of the users for the extension.
     */
    public function users()
    {
        $user_uuids = ExtensionUser::where('extension_uuid', $this->extension_uuid)->get();

        $users = collect();
        foreach ($user_uuids as $user_uuid) {
            $users->push($user_uuid->user);
        }

        return $users;
    }

    /**
     * Get the domain to which this extension belongs
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_uuid', 'domain_uuid');
    }

    // Pull all device lines for this extension in the same domain
    public function deviceLines()
    {
        return $this->hasMany(
            DeviceLines::class,
            // foreign key, local key
            'auth_id',
            'extension'
        );
    }

    // public function devices()
    // {
    //     return $this->belongsToMany(Devices::class, 'v_device_lines', 'user_id', 'device_uuid', 'extension')
    //         ->withPivot('user_id', 'line_number', 'device_line_uuid')
    //         ->where('v_devices.domain_uuid', $this->domain_uuid);
    // }

    // Devices through device lines
    // public function devices()
    // {
    //     // Many devices, through device lines, using a closure for domain matching
    //     return $this->hasManyThrough(
    //         Devices::class,
    //         DeviceLines::class,
    //         // First: DeviceLine local keys to match to this Extension
    //         'auth_id', // Foreign key on DeviceLine...
    //         'device_uuid', // Foreign key on Device...
    //         'extension', // Local key on Extension...
    //         'device_uuid' // Local key on DeviceLine...
    //     )
    //         ->whereColumn('v_device_lines.domain_uuid', 'v_extensions.domain_uuid');
    // }

    public function agent()
    {
        return $this->hasOne(CallCenterAgents::class, 'agent_id', 'extension')
            ->where('v_call_center_agents.domain_uuid', $this->domain_uuid);
    }

    public function followMe()
    {
        return $this->hasOne(FollowMe::class, 'follow_me_uuid', 'follow_me_uuid');
    }

    public function getId()
    {
        return $this->extension;
    }

    public function getName()
    {
        return $this->extension . ' - ' . ((!empty($this->effective_caller_id_name)) ? $this->effective_caller_id_name : $this->description);
    }

    /**
     * Get details for a given forwarding type.
     */
    protected function getForwardOptionDetails(string $type): ?array
    {
        // e.g., $type = 'forward_all', 'forward_busy', etc.
        if (isset($this->forwardOptionDetailsCache[$type])) {
            return $this->forwardOptionDetailsCache[$type];
        }


        $destinationField = "{$type}_destination";
        // $enabledField = "{$type}_enabled";

        if (empty($this->$destinationField)) {
            return $this->forwardOptionDetailsCache[$type] = [
                'type'      => null,
                'extension' => null,
                'option'    => null,
                'name'      => null,
            ];
        }

        $service = new CallRoutingOptionsService;
        $parsed = $service->reverseEngineerForwardAction($this->$destinationField);

        return $this->forwardOptionDetailsCache[$type] = $parsed;
    }

    public function getForwardAllOptionDetailsAttribute(): ?array
    {
        return $this->getForwardOptionDetails('forward_all');
    }
    public function getForwardAllTargetUuidAttribute(): ?string
    {
        return $this->forward_all_option_details['option'] ?? null;
    }
    public function getForwardAllActionAttribute(): ?string
    {
        return $this->forward_all_option_details['type'] ?? null;
    }
    public function getForwardAllActionDisplayAttribute(): ?string
    {
        $type = $this->forward_all_option_details['type'] ?? null;
        return $type ? (new CallRoutingOptionsService)->getFriendlyTypeName($type) : null;
    }
    public function getForwardAllTargetNameAttribute(): ?string
    {
        return $this->forward_all_option_details['name'] ?? null;
    }
    public function getForwardAllTargetExtensionAttribute(): ?string
    {
        return $this->forward_all_option_details['extension'] ?? null;
    }

    public function getForwardBusyOptionDetailsAttribute(): ?array
    {
        return $this->getForwardOptionDetails('forward_busy');
    }
    public function getForwardBusyTargetUuidAttribute(): ?string
    {
        return $this->forward_busy_option_details['option'] ?? null;
    }
    public function getForwardBusyActionAttribute(): ?string
    {
        return $this->forward_busy_option_details['type'] ?? null;
    }
    public function getForwardBusyActionDisplayAttribute(): ?string
    {
        $type = $this->forward_busy_option_details['type'] ?? null;
        return $type ? (new CallRoutingOptionsService)->getFriendlyTypeName($type) : null;
    }
    public function getForwardBusyTargetNameAttribute(): ?string
    {
        return $this->forward_busy_option_details['name'] ?? null;
    }
    public function getForwardBusyTargetExtensionAttribute(): ?string
    {
        return $this->forward_busy_option_details['extension'] ?? null;
    }

    public function getForwardNoAnswerOptionDetailsAttribute(): ?array
    {
        return $this->getForwardOptionDetails('forward_no_answer');
    }
    public function getForwardNoAnswerTargetUuidAttribute(): ?string
    {
        return $this->forward_no_answer_option_details['option'] ?? null;
    }
    public function getForwardNoAnswerActionAttribute(): ?string
    {
        return $this->forward_no_answer_option_details['type'] ?? null;
    }
    public function getForwardNoAnswerActionDisplayAttribute(): ?string
    {
        $type = $this->forward_no_answer_option_details['type'] ?? null;
        return $type ? (new CallRoutingOptionsService)->getFriendlyTypeName($type) : null;
    }
    public function getForwardNoAnswerTargetNameAttribute(): ?string
    {
        return $this->forward_no_answer_option_details['name'] ?? null;
    }
    public function getForwardNoAnswerTargetExtensionAttribute(): ?string
    {
        return $this->forward_no_answer_option_details['extension'] ?? null;
    }

    public function getForwardUserNotRegisteredOptionDetailsAttribute(): ?array
    {
        return $this->getForwardOptionDetails('forward_user_not_registered');
    }
    public function getForwardUserNotRegisteredTargetUuidAttribute(): ?string
    {
        return $this->forward_user_not_registered_option_details['option'] ?? null;
    }
    public function getForwardUserNotRegisteredActionAttribute(): ?string
    {
        return $this->forward_user_not_registered_option_details['type'] ?? null;
    }
    public function getForwardUserNotRegisteredActionDisplayAttribute(): ?string
    {
        $type = $this->forward_user_not_registered_option_details['type'] ?? null;
        return $type ? (new CallRoutingOptionsService)->getFriendlyTypeName($type) : null;
    }
    public function getForwardUserNotRegisteredTargetNameAttribute(): ?string
    {
        return $this->forward_user_not_registered_option_details['name'] ?? null;
    }
    public function getForwardUserNotRegisteredTargetExtensionAttribute(): ?string
    {
        return $this->forward_user_not_registered_option_details['extension'] ?? null;
    }

    public function generateUniqueSequenceNumber(): ?string
    {
        $domainUuid = session('domain_uuid');

        // Highest numeric extension in Extensions
        $highest = Extensions::where('domain_uuid', $domainUuid)
            ->pluck('extension')
            ->filter(fn ($e) => ctype_digit((string) $e))
            ->map(fn ($e) => (int) $e)
            ->max();

        $start = ($highest ?? 0) + 1;

        // Build a set of all used numeric strings (no * codes, digits only)
        $usedExtensions = collect()

            // Dialplans (exclude star codes)
            ->merge(
                Dialplans::where('domain_uuid', $domainUuid)
                    ->where('dialplan_number', 'not like', '*%')
                    ->pluck('dialplan_number')
            )

            // Voicemail IDs
            ->merge(
                Voicemails::where('domain_uuid', $domainUuid)
                    ->pluck('voicemail_id')
            )

            // Extensions
            ->merge(
                Extensions::where('domain_uuid', $domainUuid)
                    ->pluck('extension')
            )

            // Keep only numeric values and normalize to string
            ->filter(fn ($v) => ctype_digit((string) $v))
            ->map(fn ($v) => (string) (int) $v) // normalize "0010" -> "10"
            ->unique()
            ->flip(); // turn into a fast lookup set: ['1001' => 0, ...]

        // Search forward starting at highest+1 until we find a gap
        $ext = $start;
        $maxAttempts = 100; // safety guard
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            if (!isset($usedExtensions[(string) $ext])) {
                return (string) $ext;
            }
            $ext++;
            $attempts++;
        }

        return null;
    }

}
