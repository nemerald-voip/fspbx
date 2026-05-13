<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use App\Services\CallRoutingOptionsService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RingGroups extends Model
{
    use HasFactory, \App\Models\Traits\GeneratesUniqueExtensions, \App\Models\Traits\TraitUuid;

    protected $table = "v_ring_groups";

    public $timestamps = false;

    protected $primaryKey = 'ring_group_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_uuid',
        'ring_group_extension',
        'ring_group_greeting',
        'ring_group_strategy',
        'ring_group_name',
        'ring_group_call_timeout',
        'ring_group_timeout_app',
        'ring_group_timeout_data',
        'ring_group_cid_name_prefix',
        'ring_group_cid_number_prefix',
        'ring_group_description',
        'ring_group_enabled',
        'ring_group_context',
        'ring_group_forward_enabled',
        'ring_group_forward_destination',
        'ring_group_strategy',
        'ring_group_caller_id_name',
        'ring_group_caller_id_number',
        'ring_group_distinctive_ring',
        'ring_group_ringback',
        'ring_group_call_forward_enabled',
        'ring_group_follow_me_enabled',
        'ring_group_missed_call_app',
        'ring_group_missed_call_data',
        'ring_group_forward_toll_allow',
        'ring_group_forward_context',
        'dialplan_uuid',
        'insert_date',
        'insert_user',
        'update_date',
        'update_user'
    ];


    protected function timeoutOptionDetails(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->computeTimeoutOptionDetails()
        )->shouldCache();
    }

    protected function forwardOptionDetails(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->computeForwardOptionDetails()
        )->shouldCache();
    }


    /**
     * Reverse‐engineer ring_group_timeout_app + ring_group_timeout_data
     * into a single array of details.
     */

    protected function computeTimeoutOptionDetails(): array
    {
        $service = new CallRoutingOptionsService($this->domain_uuid);
        return $this->timeoutOptionDetailsCache = $service
            ->reverseEngineerRingGroupExitAction(
                trim("{$this->ring_group_timeout_app} {$this->ring_group_timeout_data}")
            ) ?? [
                'type' => null,
                'extension' => null,
                'option' => null,
                'name' => null
            ];
    }

    public function getTimeoutTargetUuidAttribute(): ?string
    {
        return $this->timeout_option_details['option'] ?? null;
    }

    public function getTimeoutActionAttribute(): ?string
    {
        return $this->timeout_option_details['type'] ?? null;
    }

    public function getTimeoutActionDisplayAttribute(): ?string
    {
        if (empty($this->timeout_option_details['type'])) {
            return null;
        }

        return (new CallRoutingOptionsService)
            ->getFriendlyTypeName($this->timeout_option_details['type']);
    }

    public function getTimeoutTargetNameAttribute(): ?string
    {
        return $this->timeout_option_details['name'] ?? null;
    }

    public function getTimeoutTargetExtensionAttribute(): ?string
    {
        return $this->timeout_option_details['extension'] ?? null;
    }


    public function getDestroyRouteAttribute(): string
    {
        return route('ring-groups.destroy', $this);
    }

    protected function computeForwardOptionDetails(): array
    {
        $service = new CallRoutingOptionsService($this->domain_uuid);
        return $this->forwardDetailsCache = $service
            ->reverseEngineerForwardAction($this->ring_group_forward_destination)
            ?? [
                'type' => null,
                'extension' => null,
                'option' => null,
                'name' => null
            ];
    }

    public function getForwardTargetUuidAttribute(): ?string
    {
        return $this->forward_option_details['option'] ?? null;
    }

    public function getForwardActionAttribute(): ?string
    {
        return $this->forward_option_details['type'] ?? null;
    }

    public function getForwardActionDisplayAttribute(): ?string
    {
        if (! $this->forward_option_details['type']) {
            return null;
        }

        return (new CallRoutingOptionsService)
            ->getFriendlyTypeName($this->forward_option_details['type']);
    }

    public function getForwardTargetNameAttribute(): ?string
    {
        return $this->forward_option_details['name'] ?? null;
    }

    public function getForwardTargetExtensionAttribute(): ?string
    {
        return $this->forward_option_details['extension'] ?? null;
    }


    public function getId()
    {
        return $this->ring_group_extension;
    }

    public function getName()
    {
        return $this->ring_group_extension . ' - ' . $this->ring_group_name;
    }

    public function getNameFormattedAttribute()
    {
        return $this->ring_group_extension . ' - ' . $this->ring_group_name;
    }

    public function getGroupDestinations()
    {
        return $this->belongsTo(RingGroupsDestinations::class, 'ring_group_uuid', 'ring_group_uuid')->orderBy('destination_delay')->get();
    }

    public function destinations()
    {
        return $this->hasMany(RingGroupsDestinations::class, 'ring_group_uuid', 'ring_group_uuid');
    }

    public function generateUniqueSequenceNumber(): ?string
    {
        return $this->firstAvailableExtensionInRange(9000, 9099);
    }
}
