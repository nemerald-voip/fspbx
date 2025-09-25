<?php
namespace App\Services;

use App\Models\Dialplans;
use App\Models\FusionCache;
use App\Models\Destinations;
use App\Models\DialplanDetails;

class DialplanBuilderService
{
    public function buildDialplanForPhoneNumber(Destinations $phoneNumber, $domainName): void
    {

        // logger($phoneNumber);
        // Data to pass to the Blade template
        $data = [
            'phone_number' => $phoneNumber,
            'domain_name' => $domainName,
            'fax_data' => $phoneNumber->fax()->first() ?? null,
            'dialplan_continue' => 'false',
            'destination_condition_field' => get_domain_setting('destination'),
        ];

        // Render the Blade template and get the XML content as a string
        $xml = trim(view('layouts.xml.phone-number-dial-plan-template', $data)->render());

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;  // Removes extra spaces
        $dom->loadXML($xml);
        $dom->formatOutput = true;         // Formats XML properly
        $xml = $dom->saveXML($dom->documentElement);


        $dialPlan = Dialplans::where('dialplan_uuid', $phoneNumber->dialplan_uuid)->first();

        if (!$dialPlan) {
            $dialPlan = new Dialplans();
            $dialPlan->dialplan_uuid = $phoneNumber->dialplan_uuid;
            $dialPlan->app_uuid = 'c03b422e-13a8-bd1b-e42b-b6b9b4d27ce4';
            $dialPlan->domain_uuid = $phoneNumber->domain_uuid;
            $dialPlan->dialplan_name = $phoneNumber->destination_number;
            $dialPlan->dialplan_number = $phoneNumber->destination_number;
            if (isset($phoneNumber->destination_context)) {
                $dialPlan->dialplan_context = $phoneNumber->destination_context;
            }
            $dialPlan->dialplan_continue = $data['dialplan_continue'];
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_order = 100;
            $dialPlan->dialplan_enabled = $phoneNumber->destination_enabled;
            $dialPlan->dialplan_description = $phoneNumber->destination_description;
            $dialPlan->insert_date = date('Y-m-d H:i:s');
            $dialPlan->insert_user = $phoneNumber->insert_user;
        } else {
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_name = $phoneNumber->destination_number;
            $dialPlan->dialplan_number = $phoneNumber->destination_number;
            $dialPlan->dialplan_enabled = $phoneNumber->destination_enabled;
            $dialPlan->dialplan_description = $phoneNumber->destination_description;
            $dialPlan->domain_uuid = $phoneNumber->domain_uuid;
            $dialPlan->update_date = date('Y-m-d H:i:s');
            $dialPlan->update_user = $phoneNumber->update_user;
        }

        $dialPlan->save();

        $this->generateDialplanDetailsForPhoneNumber($phoneNumber, $dialPlan);

        //clear fusionpbx cache
        $this->clearCacheForPhoneNumber($phoneNumber);
    }

    protected function generateDialplanDetailsForPhoneNumber(Destinations $phoneNumber, Dialplans $dialPlan): void
    {
        // Remove existing device lines
        if ($dialPlan->dialplan_details()->exists()) {
            $dialPlan->dialplan_details()->delete();
        }

        $detailOrder = 20;
        $detailGroup = 0;

        $destination_condition_field = get_domain_setting('destination');

        if ($phoneNumber->destination_conditions) {
            $conditions = json_decode($phoneNumber->destination_conditions);
            foreach ($conditions as $condition) {
                $dialPlanDetails = new DialplanDetails();
                $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
                $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
                $dialPlanDetails->dialplan_detail_tag = "condition";
                $dialPlanDetails->dialplan_detail_type = 'regex';
                $dialPlanDetails->dialplan_detail_data = 'all';
                $dialPlanDetails->dialplan_detail_break = 'never';
                $dialPlanDetails->dialplan_detail_group = $detailGroup;
                $dialPlanDetails->dialplan_detail_order = $detailOrder;
                $dialPlanDetails->save();

                $detailOrder += 10;

                $dialPlanDetails = new DialplanDetails();
                //check the destination number
                $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
                $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
                $dialPlanDetails->dialplan_detail_tag = "regex";
                /*if (!empty($condition->condition_app)) {
                    $dialPlanDetails->dialplan_detail_type = $condition->condition_app;
                } else {
                    $dialPlanDetails->dialplan_detail_type = "regex";
                }*/
                $dialPlanDetails->dialplan_detail_type = $destination_condition_field;
                $dialPlanDetails->dialplan_detail_data = $phoneNumber->destination_number_regex;
                $dialPlanDetails->dialplan_detail_group = $detailGroup;
                $dialPlanDetails->dialplan_detail_order = $detailOrder;
                $dialPlanDetails->save();

                //die;

                $detailOrder += 10;

                $dialPlanDetails = new DialplanDetails();
                $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
                $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
                $dialPlanDetails->dialplan_detail_tag = "regex";
                $dialPlanDetails->dialplan_detail_type = $condition->condition_field;
                $dialPlanDetails->dialplan_detail_data = '^\+?' . $phoneNumber->destination_prefix . '?' . $condition->condition_expression . '$';
                $dialPlanDetails->dialplan_detail_group = $detailGroup;
                $dialPlanDetails->dialplan_detail_order = $detailOrder;
                $dialPlanDetails->save();

                $detailOrder += 10;

                $dialPlanDetails = new DialplanDetails();
                $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
                $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
                $dialPlanDetails->dialplan_detail_tag = "action";
                $dialPlanDetails->dialplan_detail_type = $condition->condition_app;
                $dialPlanDetails->dialplan_detail_data = $condition->condition_data;
                $dialPlanDetails->dialplan_detail_group = $detailGroup;
                $dialPlanDetails->dialplan_detail_order = $detailOrder;
                $dialPlanDetails->save();

                $detailOrder += 10;
                $detailGroup += 10;
            }
        }

        //check the destination number
        $dialPlanDetails = new DialplanDetails();
        $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
        $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
        $dialPlanDetails->dialplan_detail_tag = "condition";
        $dialPlanDetails->dialplan_detail_type = $destination_condition_field;
        //$dialPlanDetails->dialplan_detail_type = 'destination_number';
        $dialPlanDetails->dialplan_detail_data = $phoneNumber->destination_number_regex;
        $dialPlanDetails->dialplan_detail_group = $detailGroup;
        $dialPlanDetails->dialplan_detail_order = $detailOrder;
        $dialPlanDetails->save();

        $detailOrder += 10;

        if (!empty($phoneNumber->destination_cid_name_prefix)) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "effective_caller_id_name=" . $phoneNumber->destination_cid_name_prefix . "#\${caller_id_name}";
            $dialPlanDetails->dialplan_detail_inline = "false";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_cid_name_prefix)) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "cnam_prefix=" . $phoneNumber->destination_cid_name_prefix;
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_accountcode)) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "accountcode=" . $phoneNumber->destination_accountcode;
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_type_fax) && $phoneNumber->destination_type_fax == 1) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "fax_enable_t38=true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_type_fax) && $phoneNumber->destination_type_fax == 1) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "fax_enable_t38_request=true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_type_fax) && $phoneNumber->destination_type_fax == 1) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "fax_use_ecm=true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_type_fax) && $phoneNumber->destination_type_fax == 1) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "inbound-proxy-media=true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            $detailOrder += 10;
        }


        if (!empty($phoneNumber->destination_hold_music)) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "hold_music=" . $phoneNumber->destination_hold_music;
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;
        }

        if (!empty($phoneNumber->destination_distinctive_ring)) {
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "export";
            $dialPlanDetails->dialplan_detail_data = "sip_h_Alert-Info=" . $phoneNumber->destination_distinctive_ring;
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;
        }

        if (!empty($phoneNumber->fax_uuid)) {

            //add set tone detect_hits=1
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "tone_detect_hits=1";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //execute on tone detect
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "execute_on_tone_detect=transfer " . $phoneNumber->fax()->first()->fax_extension . " XML \${domain_name}";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //add tone_detect fax 1100 r +5000
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "tone_detect";
            $dialPlanDetails->dialplan_detail_data = "fax 1100 r +5000";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;
        }

        if ($phoneNumber->destination_record == 'true') {
            //add a variable
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "record_path=\${recordings_dir}/\${domain_name}/archive/\${strftime(%Y)}/\${strftime(%b)}/\${strftime(%d)}";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //add a variable
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "record_name=\${uuid}.\${record_ext}";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //add a variable
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "record_append=true";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //add a variable
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "record_in_progress=true";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //add a variable
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "set";
            $dialPlanDetails->dialplan_detail_data = "recording_follow_transfer=true";
            $dialPlanDetails->dialplan_detail_inline = "true";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;

            //add a variable
            $dialPlanDetails = new DialplanDetails();
            $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
            $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
            $dialPlanDetails->dialplan_detail_tag = "action";
            $dialPlanDetails->dialplan_detail_type = "record_session";
            $dialPlanDetails->dialplan_detail_data = "\${record_path}/\${record_name}";
            $dialPlanDetails->dialplan_detail_inline = "false";
            $dialPlanDetails->dialplan_detail_group = $detailGroup;
            $dialPlanDetails->dialplan_detail_order = $detailOrder;
            $dialPlanDetails->save();

            //increment the dialplan detail order
            $detailOrder += 10;
        }

        if ($phoneNumber->destination_actions) {
            $actions = json_decode($phoneNumber->destination_actions);
            foreach ($actions as $action) {
                //add to the dialplan_details array
                $dialPlanDetails = new DialplanDetails();
                $dialPlanDetails->domain_uuid = $dialPlan->domain_uuid;
                $dialPlanDetails->dialplan_uuid = $dialPlan->dialplan_uuid;
                $dialPlanDetails->dialplan_detail_tag = "action";
                $dialPlanDetails->dialplan_detail_type = $action->destination_app;
                $dialPlanDetails->dialplan_detail_data = $action->destination_data;
                $dialPlanDetails->dialplan_detail_group = $detailGroup;
                $dialPlanDetails->dialplan_detail_order = $detailOrder;
                $dialPlanDetails->save();
                $detailOrder += 10;
            }
        }
    }

    public function clearCacheForPhoneNumber(Destinations $phoneNumber): void
    {
        // Handling for multiple dialplan mode
        FusionCache::clear("dialplan:public");

        // Handling for single dialplan mode
        if (isset($phoneNumber->destination_prefix) && is_numeric($phoneNumber->destination_prefix) && isset($phoneNumber->destination_number) && is_numeric($phoneNumber->destination_number)) {
            //  logger("dialplan:". $phoneNumber->destination_context.":".$phoneNumber->destination_prefix.$phoneNumber->destination_number);
            FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":" . $phoneNumber->destination_prefix . $phoneNumber->destination_number);
            //logger("dialplan:". $phoneNumber->destination_context.":+".$phoneNumber->destination_prefix.$phoneNumber->destination_number);
            FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":+" . $phoneNumber->destination_prefix . $phoneNumber->destination_number);
        }
        if (isset($phoneNumber->destination_number) && str_starts_with($phoneNumber->destination_number, '+') && is_numeric(str_replace('+', '', $phoneNumber->destination_number))) {
            //logger("dialplan:". $phoneNumber->destination_context.":".$phoneNumber->destination_number);
            FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":" . $phoneNumber->destination_number);
        }
        if (isset($phoneNumber->destination_number) && is_numeric($phoneNumber->destination_number)) {
            //logger("dialplan:". $phoneNumber->destination_context.":".$phoneNumber->destination_number);
            FusionCache::clear("dialplan:" . $phoneNumber->destination_context . ":" . $phoneNumber->destination_number);
        }

    }
}
