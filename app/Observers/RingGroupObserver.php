<?php

namespace App\Observers;

use App\Models\Dialplans;
use App\Models\RingGroups;
use App\Models\FusionCache;
use Illuminate\Support\Facades\Auth;
use App\Services\FreeswitchEslService;

class RingGroupObserver
{
    public function created(RingGroups $ringGroup): void
    {
        $this->generateDialPlanXML($ringGroup);
    }

    public function updated(RingGroups $ringGroup): void
    {
        $this->generateDialPlanXML($ringGroup);
    }

    public function deleted(RingGroups $ringGroup): void
    {
        // Clear cache
        FusionCache::clear("dialplan:" . $ringGroup->ring_group_context);
    }

    public function generateDialPlanXML($ringGroup): void
    {
        // Data to pass to the Blade template
        $data = [
            'ring_group' => $ringGroup,
        ];

        // Render the Blade template and get the XML content as a string
        $xml = view('layouts.xml.ring-group-dial-plan-template', $data)->render();

        $dialPlan = Dialplans::where('dialplan_uuid', $ringGroup->dialplan_uuid)->first();

        if (!$dialPlan) {
            $dialPlan = new Dialplans();
            $dialPlan->dialplan_uuid = $ringGroup->dialplan_uuid;
            $dialPlan->app_uuid = '1d61fb65-1eec-bc73-a6ee-a6203b4fe6f2';
            $dialPlan->domain_uuid = $ringGroup->domain_uuid;
            $dialPlan->dialplan_name = $ringGroup->ring_group_name;
            $dialPlan->dialplan_number = $ringGroup->ring_group_extension;
            if (isset($ringGroup->ring_group_context)) {
                $dialPlan->dialplan_context = $ringGroup->ring_group_context;
            }
            $dialPlan->dialplan_continue = 'false';
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_order = 101;
            $dialPlan->dialplan_enabled = $ringGroup->ring_group_enabled;
            $dialPlan->dialplan_description = $ringGroup->ring_group_description;
            $dialPlan->insert_date = date('Y-m-d H:i:s');
            $dialPlan->insert_user = Auth::user()->user_uuid ?? '';
        } else {
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_name = $ringGroup->ring_group_name;
            $dialPlan->dialplan_number = $ringGroup->ring_group_extension;
            $dialPlan->dialplan_description = $ringGroup->ring_group_description;
            $dialPlan->update_date = date('Y-m-d H:i:s');
            $dialPlan->update_user = Auth::user()->user_uuid ?? '';
        }

        $dialPlan->save();

        $freeSwitchService = new FreeswitchEslService();
        $command = 'bgapi reloadxml';
        $result = $freeSwitchService->executeCommand($command);

        //clear fusionpbx cache
        FusionCache::clear("dialplan:" . $ringGroup->ring_group_context);
    }
}
