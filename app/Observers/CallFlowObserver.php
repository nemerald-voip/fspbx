<?php

namespace App\Observers;

use App\Models\CallFlows;
use App\Models\Dialplans;
use App\Models\FusionCache;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Facades\Auth;

class CallFlowObserver
{
    public function created(CallFlows $callFlow): void
    {
        $this->generateDialPlanXML($callFlow);
    }

    public function updated(CallFlows $callFlow): void
    {
        $this->generateDialPlanXML($callFlow);
    }

    public function deleted(CallFlows $callFlow): void
    {
        FusionCache::clear("dialplan:" . $callFlow->call_flow_context);
    }

    public function generateDialPlanXML(CallFlows $callFlow): void
    {
        $destinationExtension = str_replace(['*', '+'], ['\*', '\+'], (string) $callFlow->call_flow_extension);
        $destinationFeature = $callFlow->call_flow_feature_code;

        if (filled($destinationFeature) && substr($destinationFeature, 0, 5) !== 'flow+') {
            $destinationFeature = '(?:flow+)?' . $destinationFeature;
        }

        $destinationFeature = str_replace(['*', '+'], ['\*', '\+'], (string) $destinationFeature);

        $xml = view('layouts.xml.call-flow-dial-plan-template', [
            'call_flow' => $callFlow,
            'destination_extension' => $destinationExtension,
            'destination_feature' => $destinationFeature,
        ])->render();

        $dialPlan = Dialplans::where('dialplan_uuid', $callFlow->dialplan_uuid)->first();

        if (!$dialPlan) {
            $dialPlan = new Dialplans();
            $dialPlan->dialplan_uuid = $callFlow->dialplan_uuid;
            $dialPlan->app_uuid = 'b1b70f85-6b42-429b-8c5a-60c8b02b7d14';
            $dialPlan->domain_uuid = $callFlow->domain_uuid;
            $dialPlan->dialplan_continue = 'false';
            $dialPlan->dialplan_order = 333;
            $dialPlan->insert_date = date('Y-m-d H:i:s');
            $dialPlan->insert_user = Auth::user()->user_uuid ?? '';
        } else {
            $dialPlan->update_date = date('Y-m-d H:i:s');
            $dialPlan->update_user = Auth::user()->user_uuid ?? '';
        }

        $dialPlan->dialplan_name = $callFlow->call_flow_name;
        $dialPlan->dialplan_number = $callFlow->call_flow_extension;
        $dialPlan->dialplan_context = $callFlow->call_flow_context;
        $dialPlan->dialplan_xml = $xml;
        $dialPlan->dialplan_enabled = $callFlow->call_flow_enabled === 'true';
        $dialPlan->dialplan_description = $callFlow->call_flow_description;
        $dialPlan->save();

        $freeSwitchService = new FreeswitchEslService();
        $freeSwitchService->executeCommand('bgapi reloadxml');

        FusionCache::clear("dialplan:" . $callFlow->call_flow_context);
    }
}
