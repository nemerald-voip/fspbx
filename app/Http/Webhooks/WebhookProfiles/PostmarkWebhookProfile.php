<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Throwable;
use App\Models\User;
use App\Models\Voicemails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;
use App\Jobs\SendFaxInvalidEmailNotification;
use App\Jobs\SendFaxInvalidDestinationNotification;

class PostmarkWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {

        // Get destination fax number and check if it's valid
        $phone_number = strstr($request['ToFull'][0]['Email'], '@', true);
        //Get libphonenumber object
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($phone_number, 'US');
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)){
                $destination_number_valid = true;
                $request['fax_destination'] = $phoneNumberUtil
                            ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::E164);
            } else {
                $request['fax_destination'] = $phone_number;
            }
        } catch (Throwable $e) {
            $destination_number_valid = false;
            $request['fax_destination'] = $phone_number;
        }

        // Get FROM email subject and check if it's authorized
        $from_email = $request['From'];

        try {
            $user = User::where('user_email', '=', $from_email)->firstOrFail();
            $request['domain_uuid'] = $user->domain_uuid;

        } catch (Throwable $e) {
            // if user with this email doesn't exist check if there is an extension with this email
            // Extension's emails are stored in Voicmeail table
            try {
                $voicemail = Voicemails::where('voicemail_mail_to', $from_email)->firstOrFail();
                $request['domain_uuid'] = $voicemail->domain_uuid; 
    
            } catch (Throwable $e) {
                // Notification::route('slack', env('SLACK_FAX_HOOK'))
                // ->notify(new SendSlackFaxNotification());
                SendFaxInvalidEmailNotification::dispatch($request)->onQueue('faxes');

                // Since the email was not found the request is not authorized to proceed 
                return false;
            }

        }

        // Check if the fax destination number is valid
        if (!$destination_number_valid){

            $request['invalid_number'] = $phone_number;
            SendFaxInvalidDestinationNotification::dispatch($request)->onQueue('faxes');

            // Since the phone number is not valid the request is not authorized to proceed 
            return false;
        }

        // $this->request['fax_destination']

        return true;
    }
}
