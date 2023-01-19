<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Throwable;
use App\Models\User;
use App\Models\Voicemails;
use Illuminate\Http\Request;
use App\Models\FaxAllowedEmails;
use Illuminate\Support\Facades\Log;
use App\Models\FaxAllowedDomainNames;
use App\Jobs\SendFaxInvalidEmailNotification;
use App\Jobs\SendFaxInvalidDestinationNotification;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

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

        // Check if domain is whitelisted for sending faxes
        try {
            $email_address = explode("@",$from_email);
            $domain_name = FaxAllowedDomainNames::where('domain', '=', $email_address[1])->firstOrFail();
            $request['fax_uuid'] = $domain_name->fax_uuid;

        } catch (Throwable $e) {
            // If the domain not found check if this email is whitelisted for sending faxes
            // Check users first
            try {
                $users = User::where('user_email', '=', $from_email)->get();
                foreach ($users as $user) {
                    if (!$user->domain->faxes->isEmpty()) {
                        $request['fax_uuid'] = $user->domain->faxes->first()->fax_uuid;
                        break;
                    }
                }

                if (!isset($request['fax_uuid'])) {
                    // if user with this email doesn't exist check if there is an extension with this email
                    // Extension's emails are stored in Voicemail table
                    $voicemails = Voicemails::where('voicemail_mail_to', $from_email)->get();
                    foreach ($voicemails as $voicemail) {

                        if (!$voicemail->domain->faxes->isEmpty()) {
                            $request['fax_uuid'] = $voicemail->domain->faxes->first()->fax_uuid;
                            break;
                        }
                    }
                }

                $email = FaxAllowedEmails::where('email', $from_email)->firstOrFail();
                $request['fax_uuid'] = $email->fax_uuid;
    
            } catch (Throwable $e) {
                    Log::alert($e->getMessage());
                    // Send notification that email was not authorized
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
