<?php

namespace App\Http\Webhooks\WebhookProfiles;

use App\Models\Domain;
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

            $setting = $domainModel->settings()
                ->where('domain_setting_category', 'app shell')
                ->where('domain_setting_subcategory', 'org_id')
                ->get('domain_setting_value')
                ->first();

            if (!$setting) {
                throw new \Exception('ORG ID is not found');
            }

            $request['domain_setting_value'] = $setting->domain_setting_value;
            $request['to'] = $smsDestinationModel->chatplan_detail_data;

            $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
            $phoneNumberObject = $phoneNumberUtil->parse($request['from'], 'US');
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)){
                $request['from'] = $phoneNumberUtil
                    ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::E164);
            }

            return true;
        } catch (\Throwable $e) {
            Log::alert($e->getMessage());
        }

        return false;
    }
}
