<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Throwable;
use App\Models\User;
use App\Models\Voicemails;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\DefaultSettings;
use App\Models\FaxAllowedEmails;
use Illuminate\Support\Facades\Log;
use App\Models\FaxAllowedDomainNames;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Jobs\SendFaxInvalidEmailNotification;
use App\Jobs\SendFaxInvalidDestinationNotification;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

class MailgunWebhookProfile implements WebhookProfile
{
    public function shouldProcess(Request $request): bool
    {

        // logger($request->all());
        // --- 1. Get and validate recipient (destination) ---
        $recipientEmail = $request['recipient'] ?? null;
        $phone_number = null;

        if ($recipientEmail && strpos($recipientEmail, '@') !== false) {
            $phone_number = strstr($recipientEmail, '@', true);
        } else {
            logger("MailgunWebhookProfile@shouldProcess error: o valid recipient found in request");
            return false;
        }

        // --- 2. Validate destination phone number ---
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        // $destination_number_valid = false;
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($phone_number, 'US');
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                // $destination_number_valid = true;
                $request['fax_destination'] = $phoneNumberUtil
                    ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::E164);
            } else {
                $request['fax_destination'] = $phone_number;
            }
        } catch (\Throwable $e) {
            $request['fax_destination'] = $phone_number;
        }

        // --- 3. Extract sender email robustly ---
        $fromRaw = $request['from'] ?? $request['From'] ?? null;
        $from_email = $this->extractEmail($fromRaw);

        if (!$from_email) {
            logger("MailgunWebhookProfile@shouldProcess error: No valid sender (from) email found in request");
            return false;
        }

        // --- 4. Authorization: is sender allowed? ---
        try {
            // 1. Check allowed domain
            $domain = explode("@", $from_email)[1] ?? null;
            if (!$domain) {
                throw new \Exception("No domain found in sender email");
            }
            $domain_name = FaxAllowedDomainNames::where('domain', '=', $domain)->first();
            if ($domain_name) {
                $request['fax_uuid'] = $domain_name->fax_uuid;
            } else {
                // 2. Check allowed user
                $users = User::where('user_email', '=', $from_email)->get();
                foreach ($users as $user) {
                    if (!$user->domain->faxes->isEmpty()) {
                        $request['fax_uuid'] = $user->domain->faxes->first()->fax_uuid;
                        break;
                    }
                }

                // 3. Check allowed voicemail
                if (!isset($request['fax_uuid'])) {
                    $voicemails = Voicemails::where('voicemail_mail_to', $from_email)->get();
                    foreach ($voicemails as $voicemail) {
                        if (!$voicemail->domain->faxes->isEmpty()) {
                            $request['fax_uuid'] = $voicemail->domain->faxes->first()->fax_uuid;
                            break;
                        }
                    }
                }

                // 4. Check allowed email
                if (!isset($request['fax_uuid'])) {
                    $email = FaxAllowedEmails::where('email', $from_email)->first();
                    if ($email) {
                        $request['fax_uuid'] = $email->fax_uuid;
                    }
                }

                if (!isset($request['fax_uuid'])) {
                    throw new \Exception("Sender email is not authorized for faxing.");
                }
            }
        } catch (\Throwable $e) {
            Log::alert("Fax sender not authorized: " . $e->getMessage());
            $data = [
                'from' => $from_email,
                'fax_destination' => $request['fax_destination'],
            ];
            SendFaxInvalidEmailNotification::dispatch($data)->onQueue('faxes');
            return false;
        }

        // Save attachments

        //Set default allowed extensions 
        $fax_allowed_extensions = Cache::remember('fax_allowed_extensions', now()->addDay(), function () {
            $extensions = DefaultSettings::where('default_setting_category', 'fax')
                ->where('default_setting_subcategory', 'allowed_extension')
                ->where('default_setting_enabled', 'true')
                ->pluck('default_setting_value')
                ->toArray();

            return !empty($extensions) ? $extensions : ['.pdf', '.tiff', '.tif'];
        });

        $attachments_meta = [];

        foreach ($request->allFiles() as $key => $file) {
            // Only process attachment fields (Mailgun: 'attachment-1', 'attachment-2', etc)
            if (!str_starts_with($key, 'attachment-')) continue;

            $original_name = $file->getClientOriginalName();

            $fax_file_extension = '.' . strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

            // Only allow whitelisted file types
            if (!in_array($fax_file_extension, $fax_allowed_extensions)) continue;

            $uuid_filename = Str::uuid()->toString();
            // $target_path = $request['domain_name'] . '/' . $request['fax_extension'] . '/temp/' . $uuid_filename . $fax_file_extension;

            try {
                // Save the file using Laravel Storage
                $stored = Storage::disk('fax')->putFileAs(
                    '/temp',
                    $file,
                    $uuid_filename . $fax_file_extension
                );

                $attachments_meta[] = [
                    'original_name' => $original_name,
                    'stored_path' => $stored,
                    'mime_type' => $file->getMimeType(),
                    'extension' => $fax_file_extension,
                ];
            } catch (\Throwable $e) {
                Log::alert("Failed to save fax attachment: " . $e->getMessage());
                // Optionally send notification, continue;
                continue;
            }
        }

        // Add attachments metadata to request, so it's stored in the payload
        $request->merge(['fax_attachments' => $attachments_meta]);

        return true;
    }

    function stripUploadedFiles($array)
    {
        foreach ($array as $key => $value) {
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                unset($array[$key]);
            } elseif (is_array($value)) {
                $array[$key] = $this->stripUploadedFiles($value);
            }
        }
        return $array;
    }

    // public function shouldProcess(Request $request): bool
    // {
    //     logger($request->all());

    //     $destination_number_valid = false;
    //     // Get destination fax number and check if it's valid
    //     $phone_number = strstr($request['ToFull'][0]['Email'], '@', true);
    //     //Get libphonenumber object
    //     $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
    //     try {
    //         $phoneNumberObject = $phoneNumberUtil->parse($phone_number, 'US');
    //         if ($phoneNumberUtil->isValidNumber($phoneNumberObject)){
    //             $destination_number_valid = true;
    //             $request['fax_destination'] = $phoneNumberUtil
    //                         ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::E164);
    //         } else {
    //             $request['fax_destination'] = $phone_number;
    //         }
    //     } catch (Throwable $e) {
    //         $destination_number_valid = false;
    //         $request['fax_destination'] = $phone_number;
    //     }

    //     // Get FROM email subject and check if it's authorized
    //     $from_email = strtolower($request['FromFull']['Email']);

    //     // Check if domain is whitelisted for sending faxes
    //     try {
    //         $email_address = explode("@",$from_email);
    //         $domain_name = FaxAllowedDomainNames::where('domain', '=', $email_address[1])->firstOrFail();
    //         $request['fax_uuid'] = $domain_name->fax_uuid;

    //     } catch (Throwable $e) {
    //         // If the domain not found check if this email is whitelisted for sending faxes
    //         // Check users first
    //         try {
    //             $users = User::where('user_email', '=', $from_email)->get();
    //             foreach ($users as $user) {
    //                 if (!$user->domain->faxes->isEmpty()) {
    //                     $request['fax_uuid'] = $user->domain->faxes->first()->fax_uuid;
    //                     break;
    //                 }
    //             }

    //             if (!isset($request['fax_uuid'])) {
    //                 // if user with this email doesn't exist check if there is an extension with this email
    //                 // Extension's emails are stored in Voicemail table
    //                 $voicemails = Voicemails::where('voicemail_mail_to', $from_email)->get();
    //                 foreach ($voicemails as $voicemail) {

    //                     if (!$voicemail->domain->faxes->isEmpty()) {
    //                         $request['fax_uuid'] = $voicemail->domain->faxes->first()->fax_uuid;
    //                         break;
    //                     }
    //                 }
    //             }

    //             $email = FaxAllowedEmails::where('email', $from_email)->firstOrFail();
    //             $request['fax_uuid'] = $email->fax_uuid;

    //         } catch (Throwable $e) {
    //                 Log::alert($e->getMessage());
    //                 // Send notification that email was not authorized
    //                 SendFaxInvalidEmailNotification::dispatch($request)->onQueue('faxes');

    //                 // Since the email was not found the request is not authorized to proceed 
    //                 return false;
    //         }


    //     }

    //     // Check if the fax destination number is valid
    //     if (!$destination_number_valid){

    //         $request['invalid_number'] = $phone_number;
    //         SendFaxInvalidDestinationNotification::dispatch($request)->onQueue('faxes');

    //         // Since the phone number is not valid the request is not authorized to proceed 
    //         return false;
    //     }

    //     // $this->request['fax_destination']

    //     return true;
    // }

    /**
     * Extracts the email address from a "Name <email>" or just "email" string.
     * Returns null if not found.
     */
    function extractEmail($raw)
    {
        if (!$raw || !is_string($raw)) return null;
        // Handles formats like "Name <email@domain.com>" or just "email@domain.com"
        if (preg_match('/<([^>]+)>/', $raw, $matches)) {
            return strtolower(trim($matches[1]));
        }
        // If raw looks like an email
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return strtolower(trim($raw));
        }
        // Try to find an email within the string, e.g., weird cases
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $raw, $matches)) {
            return strtolower(trim($matches[0]));
        }
        return null;
    }
}
