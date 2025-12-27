<?php

namespace App\Rules;

use App\Models\BusinessHour;
use Closure;
use App\Models\Faxes;
use App\Models\IvrMenus;
use App\Models\CallFlows;
use App\Models\Extensions;
use App\Models\RingGroups;
use App\Models\Voicemails;
use App\Models\Conferences;
use App\Models\CallCenterQueues;
use App\Models\ConferenceCenters;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueExtension implements ValidationRule
{
    protected $domainUuid;
    protected $currentUuid; // The Uuid to exclude


    public function __construct($currentUuid = null, ?string $domainUuid = null)
    {
        $this->domainUuid  = $domainUuid ?: session('domain_uuid');
        $this->currentUuid = $currentUuid;
    }

    /**
     * Validate the attribute.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, \Closure $fail): void
    {
        // Add validation for 911 as a reserved value
        if ($value == '911') {
            $fail('Extension 911 is reserved for emergency services and cannot be used.');
            return;
        }

        $subqueries = [];

        // Check for Extensions only if we are not validating a Voicemail
        if ($attribute !== 'voicemail_id') {
            $subqueries[] = Extensions::select('extension as extension') // Aliasing column to match others
                ->where('extension', $value)
                ->where('domain_uuid', $this->domainUuid)
                ->when($this->currentUuid, function ($query) {
                    return $query->where('extension_uuid', '!=', $this->currentUuid);
                });
        }

        // Check for Voicemails only if we are not validating an Extension
        if ($attribute !== 'extension') {
            $subqueries[] = Voicemails::select('voicemail_id as extension') // Aliasing column to match others
                ->where('voicemail_id', $value)
                ->where('domain_uuid', $this->domainUuid)
                ->when($this->currentUuid, function ($query) {
                    return $query->where('voicemail_uuid', '!=', $this->currentUuid);
                });
        }

        // Subquery for RingGroups
        $subqueries[] = RingGroups::select('ring_group_extension as extension') // Aliasing column to match others
            ->where('ring_group_extension', $value)
            ->where('domain_uuid', $this->domainUuid)
            ->when($this->currentUuid, function ($query) {
                return $query->where('ring_group_uuid', '!=', $this->currentUuid);
            });

        // Add this subquery for CallCenterQueues
        $subqueries[] = CallCenterQueues::select('queue_extension as extension') // Aliasing column to match others
            ->where('queue_extension', $value)
            ->where('domain_uuid', $this->domainUuid)
            ->when($this->currentUuid, function ($query) {
                return $query->where('call_center_queue_uuid', '!=', $this->currentUuid);
            });

        // Add this subquery for Faxes
        $subqueries[] = Faxes::select('fax_extension as extension') // Aliasing column to match others
            ->where('fax_extension', $value)
            ->where('domain_uuid', $this->domainUuid)
            ->when($this->currentUuid, function ($query) {
                return $query->where('fax_uuid', '!=', $this->currentUuid);
            });

        // Add this subquery for IvrMenus
        $subqueries[] = IvrMenus::select('ivr_menu_extension as extension') // Aliasing column to match others
            ->where('ivr_menu_extension', $value)
            ->where('domain_uuid', $this->domainUuid)
            ->when($this->currentUuid, function ($query) {
                return $query->where('ivr_menu_uuid', '!=', $this->currentUuid);
            });

        // Add this subquery for CallFlows
        $subqueries[] = CallFlows::select('call_flow_extension as extension') // Aliasing column to match others
            ->where('call_flow_extension', $value)
            ->where('domain_uuid', $this->domainUuid)
            ->when($this->currentUuid, function ($query) {
                return $query->where('call_flow_uuid', '!=', $this->currentUuid);
            });

        // Add this subquery for ConferenceCenters
        $subqueries[] = ConferenceCenters::select('conference_center_extension as extension') // Aliasing column to match others
            ->where('conference_center_extension', $value)
            ->where('domain_uuid', $this->domainUuid)
            ->when($this->currentUuid, function ($query) {
                return $query->where('conference_center_uuid', '!=', $this->currentUuid);
            });

        // Add this subquery for Conferences
        $subqueries[] = Conferences::select('conference_extension as extension') // Aliasing column to match others
            ->where('conference_extension', $value)
            ->where('domain_uuid', $this->domainUuid)
            ->when($this->currentUuid, function ($query) {
                return $query->where('conference_uuid', '!=', $this->currentUuid);
            });

        // Add this subquery for Business Hours
        $subqueries[] = BusinessHour::select('extension') // Aliasing column to match others
            ->where('extension', $value)
            ->where('domain_uuid', $this->domainUuid)
            ->when($this->currentUuid, function ($query) {
                return $query->where('uuid', '!=', $this->currentUuid);
            });

        // Combine all subqueries using UNION
        $combinedQuery = $subqueries[0];
        for ($i = 1; $i < count($subqueries); $i++) {
            $combinedQuery->union($subqueries[$i]);
        }

        if ($combinedQuery->exists()) {
            $fail('This extension number is already in use.');
            return;
        }
    }
}
