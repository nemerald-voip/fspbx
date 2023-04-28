<?php

namespace App\Http\Webhooks\WebhookProfiles;

use App\Models\Domain;
use App\Models\Extensions;
use App\Models\Messages;
use App\Models\SmsDestinations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class CommioWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {
        try {
            // Get domain UUID using destination number from the request
            $smsDestinationModel = SmsDestinations::where('destination', $request['to'])
                ->where('enabled', 'true')
                ->first();
            if (!$smsDestinationModel) {
                throw new \Exception('SMS Destination '.$request['to'].' is not found');
            }

            $domainModel = Domain::find($smsDestinationModel->domain_uuid);

            if (!$domainModel) {
                throw new \Exception('Domain '.$smsDestinationModel->domain_uuid.' is not found');
            }

            $extensionModel = Extensions::where('domain_uuid', $smsDestinationModel->domain_uuid)
                ->where('extension', $smsDestinationModel->chatplan_detail_data)
                ->first();

            if (!$extensionModel) {
                throw new \Exception('Extension '.$smsDestinationModel->chatplan_detail_data.' is not found');
            }

            $setting = $domainModel->settings()
                ->where('domain_setting_category', 'app shell')
                ->where('domain_setting_subcategory', 'org_id')
                ->get('domain_setting_value')
                ->first();

            if (!$setting) {
                throw new \Exception('ORG ID is not found');
            }

            // Store message in database
            $messageModel = new Messages();
            $messageModel->extension_uuid = (isset($extensionModel->extension_uuid)) ? $extensionModel->extension_uuid : null;
            $messageModel->domain_uuid = (isset($smsDestinationModel->domain_uuid)) ? $smsDestinationModel->domain_uuid : null;
            $messageModel->source = $request['from'];
            $messageModel->destination = $smsDestinationModel->chatplan_detail_data;
            $messageModel->message = $request['message'];
            $messageModel->direction = 'in';
            $messageModel->type = 'sms';
            $messageModel->status = 'Queued';
            $messageModel->save();

            $request['domain_setting_value'] = $setting->domain_setting_value;
            $request['to'] = $smsDestinationModel->chatplan_detail_data;
            $request['message_uuid'] = $messageModel->message_uuid;

            return true;
        } catch (\Throwable $e) {
            Log::alert($e->getMessage());
        }

        return false;
    }
}
