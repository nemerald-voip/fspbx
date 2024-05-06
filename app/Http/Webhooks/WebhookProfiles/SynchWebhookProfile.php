<?php

namespace App\Http\Webhooks\WebhookProfiles;

use App\Models\Domain;
use App\Models\Messages;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\SmsDestinations;
use Illuminate\Support\Facades\Log;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use App\Jobs\SendSmsNotificationToSlack;
use libphonenumber\NumberParseException;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class SynchWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        logger($request);
        try {
            // $slack_message = 'SMS from ' . $request['from'] . ' to ' . $request['to'] . '\n';
            // $slack_message = "*Commio Inbound SMS* From: " . $request['from'] . ", To:" . $request['to'] ."\n";

            // //convert all numbers to e.164 format
            // //Get libphonenumber object
            // $phoneNumberUtil = PhoneNumberUtil::getInstance();
            // try {
            //     $phoneNumberObject = $phoneNumberUtil->parse($request['from'], 'US');
            //     if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
            //         $request['from'] = $phoneNumberUtil
            //             ->format($phoneNumberObject, PhoneNumberFormat::E164);
            //     }
            // } catch (NumberParseException $e) {
            //     // Do nothing and leave the numner as is
            // }

            // try {
            //     $phoneNumberObject = $phoneNumberUtil->parse($request['to'], 'US');
            //     if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
            //         $request['to'] = $phoneNumberUtil
            //             ->format($phoneNumberObject, PhoneNumberFormat::E164);
            //     }
            // } catch (NumberParseException $e) {
            //     // Do nothing and leave the numner as is
            // }

            // // Get domain UUID using destination number from the request
            // $smsDestinationModel = SmsDestinations::where('destination', $request['to'])
            //     ->where('enabled', 'true')
            //     ->first();
            // if (!$smsDestinationModel) {
            //     throw new \Exception('SMS Destination for '.$request['to'].' is not found');
            // }

            // $domainModel = Domain::find($smsDestinationModel->domain_uuid);

            // if (!$domainModel) {
            //     throw new \Exception('Domain '.$smsDestinationModel->domain_uuid.' is not found');
            // }

            // $extensionModel = Extensions::where('domain_uuid', $smsDestinationModel->domain_uuid)
            //     ->where('extension', $smsDestinationModel->chatplan_detail_data)
            //     ->first();

            // if (!$extensionModel && (is_null($smsDestinationModel->email) ||  $smsDestinationModel->email =="")) {
            //     throw new \Exception('Unable to find extension or email destination for '. $request["to"]);
            // }

            // if (!is_null($smsDestinationModel->email) &&  $smsDestinationModel->email !="") {
            //     $email = $smsDestinationModel->email;
            // } else {
            //     $email = "";
            // }

            // if (!is_null($smsDestinationModel->chatplan_detail_data) &&  $smsDestinationModel->chatplan_detail_data !="") {
            //     $ext = $smsDestinationModel->chatplan_detail_data;
            // } else {
            //     $ext = "";
            // }

            // $setting = $domainModel->settings()
            //     ->where('domain_setting_category', 'app shell')
            //     ->where('domain_setting_subcategory', 'org_id')
            //     ->get('domain_setting_value')
            //     ->first();

            // if (!$setting && (is_null($smsDestinationModel->email) ||  $smsDestinationModel->email =="")) {
            //     throw new \Exception('Ringotel Org ID is missing for - ' . $domainModel->domain_description . ' (' . $smsDestinationModel->domain_uuid . ')');
            // }

            // // Store message in database
            // $messageModel = new Messages();
            // $messageModel->extension_uuid = (isset($extensionModel->extension_uuid)) ? $extensionModel->extension_uuid : null;
            // $messageModel->domain_uuid = (isset($smsDestinationModel->domain_uuid)) ? $smsDestinationModel->domain_uuid : null;
            // $messageModel->source = $request['from'];
            // $messageModel->destination = $request['to'];
            // $messageModel->message = $request['message'];
            // $messageModel->direction = 'in';
            // $messageModel->type = 'sms';
            // $messageModel->status = 'Queued';
            // $messageModel->save();

            // $request['org_id'] = $setting->domain_setting_value;
            // $request['to'] = $ext;
            // $request['email_to'] = $email;
            // $request['message_uuid'] = $messageModel->message_uuid;

            return true;
        } catch (\Throwable $e) {
            Log::alert($e->getMessage());
            SendSmsNotificationToSlack::dispatch($slack_message . $e->getMessage())->onQueue('messages');
        }

        return false;
    }
}
