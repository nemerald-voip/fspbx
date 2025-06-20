<?php

namespace App\Models\Sinch;

use App\Mail\SmsToEmail;
use App\Models\Messages;
use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;
use App\Jobs\SendSmsNotificationToSlack;
use App\Services\RingotelApiService;

/**
 * @property string|null $org_id
 * @property string|null $message_uuid
 * @property string|null $email
 * @property string|null $extension
 */
class SinchInboundSMS extends Model
{
    public $org_id;
    public $message_uuid;
    public $email;
    public $extension;

    /**
     * Send SMS/MMS message.
     *
     * @return bool
     */
    public function send()
    {
        $message = Messages::find($this->message_uuid);
    
        if (!$message) {
            logger("Could not find sms entity from " . $message->source . " to " . $this->extension);
            return false;
        }
    
        try {
            $ringotelApiService = new RingotelApiService();
    
            if ($message->type === 'mms') {
                $mediaUrls = $message->media;
                if (is_string($mediaUrls)) {
                    $mediaUrls = json_decode($mediaUrls, true);
                }
                if (is_array($mediaUrls) && count($mediaUrls) > 0) {
                    foreach ($mediaUrls as $mediaUrl) {
                        $params = [
                            'orgid' => $this->org_id,
                            'from' => $message->source,
                            'to' => $this->extension,
                            'content' => $mediaUrl,
                            'type' => 7,
                        ];
    
                        $response = $ringotelApiService->message($params);
                        // logger($response);
    
                        if (!isset($response['messageid'])) {
                            // Throw an exception to immediately break and jump to the catch block
                            throw new \Exception("No messageid returned for MMS (media: $mediaUrl). Response: " . json_encode($response));
                        }
                        // You may collect messageids if you wish
                    }
                    // Success: last response status update
                    $this->updateMessageStatus($message, $response);
                } else {
                    throw new \Exception("No media files to send for MMS message UUID: " . $this->message_uuid);
                }
            } else {
                // SMS
                $params = [
                    'orgid' => $this->org_id,
                    'from' => $message->source,
                    'to' => $this->extension,
                    'content' => $message->message,
                ];
                $response = $ringotelApiService->message($params);
                logger($response);
    
                if (!isset($response['messageid'])) {
                    throw new \Exception("No messageid returned for SMS. Response: " . json_encode($response));
                }
    
                $this->updateMessageStatus($message, $response);
            }
        } catch (\Throwable $e) {
            logger("Error delivering SMS/MMS to Ringotel: {$e->getMessage()}");
            SendSmsNotificationToSlack::dispatch(
                "*Sinch Inbound SMS/MMS Failed*. From: " . $this->source . " To: " . $this->extension .
                "\nError delivering to Ringotel: {$e->getMessage()}"
            )->onQueue('messages');
            $this->updateMessageStatus($message, null);
            return false;
        }
    
        return true;
    }
    

    private function updateMessageStatus($message, $response)
    {
        // if (isset($response['result']) && !empty($response['result'])) {
            if (isset($response['messageid'])) {
                $message->status = 'success';
                $message->reference_id = $response['messageid']; 
            } else {
                $message->status = 'failed';
                $errorDetail = json_encode($response);
                SendSmsNotificationToSlack::dispatch("*Sinch Inbound SMS Failed*.From: " . $this->source . " To: " . $this->extension . "\nRingotel API Error: No message ID received. Details: " . $errorDetail)->onQueue('messages');
            }
        // } else {
        //     $message->status = 'failed';
        //     $errorDetail = isset($response['error']) ? json_encode($response['error']) : 'Unknown error';
        //     SendSmsNotificationToSlack::dispatch("*Sinch Inbound SMS Failed*.From: " . $this->source . " To: " . $this->extension . "\nRingotel API Failure: " . $errorDetail)->onQueue('messages');
        // }
        $message->save();
    }


    /**
     * Send the outbound SMS message over email.
     *
     * @return bool
     */
    public function smsToEmail()
    {
        $message = Messages::find($this->message_uuid);

        if (!$message) {
            logger("Could not find sms entity from " . $message->source . " to " . $this->email);
            return false;
        }

        $settings = DefaultSettings::where('default_setting_category', 'sms')->get();
        if ($settings) {
            foreach ($settings as $setting) {
                if ($setting->default_setting_subcategory == "smtp_from") {
                    $attributes['smtp_from'] = $setting->default_setting_value;
                }
                if ($setting->default_setting_subcategory == "email_company_address") {
                    $attributes['company_address'] = $setting->default_setting_value;
                }
                if ($setting->default_setting_subcategory == "smtp_from_name") {
                    $attributes['smtp_from_name'] = $setting->default_setting_value;
                }
            }
        }
        $attributes['orgid'] = $this->org_id;
        $attributes['from'] = $message->source;
        $attributes['email_to'] = $this->email;
        $attributes['message'] = $message->message;
        $attributes['email_subject'] = 'SMS Notification: New Message from ' . $message->source;

        // Logic to deliver the SMS message using email
        // This method should return a boolean indicating whether the message was sent successfully.
        Mail::to($this->email)->send(new SmsToEmail($attributes));

        if ($message->status = "queued") {
            $message->status = 'emailed';
        }
        $message->save();
        //Log::alert($response);

        return true; // Change this to reflect the result of the API call.
    }

    /**
     * Determine if the outbound SMS message was sent successfully.
     *
     * @return bool
     */
    public function wasSent()
    {
        // Logic to determine if the message was sent successfully using a third-party API.

        return true; // Change this to reflect the result of the API call.
    }
}
