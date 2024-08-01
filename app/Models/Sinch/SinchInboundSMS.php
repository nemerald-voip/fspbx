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
     * Send the outbound SMS message.
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

        // logger('orgid '. $this->org_id);
        // logger('from '. $message->source);
        // logger('to '. $this->extension);
        // logger('content '. $message->message);

        // Logic to deliver the SMS message using a third-party Ringotel API,
        // This method should return a boolean indicating whether the message was sent successfully.
        try {
            $response = Http::ringotel_api()
                ->withBody(json_encode([
                    'method' => 'message',
                    'params' => [
                        'orgid' => $this->org_id,
                        'from' => $message->source,
                        'to' => $this->extension,
                        'content' => $message->message
                    ]
                ]), 'application/json')
                ->post('/')
                ->throw()
                ->json();

            $this->updateMessageStatus($message, $response);
        } catch (\Throwable $e) {
            logger("Error delivering SMS to Ringotel: {$e->getMessage()}");
            SendSmsNotificationToSlack::dispatch("*Sinch Inbound SMS Failed*. From: " . $this->source . " To: " . $this->extension . "\nError delivering SMS to Ringotel")->onQueue('messages');
            return false;
        }

        return true;
    }

    private function updateMessageStatus($message, $response)
    {
        if (isset($response['result']) && !empty($response['result'])) {
            if (isset($response['result']['messageid'])) {
                $message->status = 'success';
                $message->reference_id = $response['result']['messageid']; 
            } else {
                $message->status = 'failed';
                $errorDetail = json_encode($response['result']);
                SendSmsNotificationToSlack::dispatch("*Sinch Inbound SMS Failed*.From: " . $this->source . " To: " . $this->extension . "\nRingotel API Error: No message ID received. Details: " . $errorDetail)->onQueue('messages');
            }
        } else {
            $message->status = 'failed';
            $errorDetail = isset($response['error']) ? json_encode($response['error']) : 'Unknown error';
            SendSmsNotificationToSlack::dispatch("*Sinch Inbound SMS Failed*.From: " . $this->source . " To: " . $this->extension . "\nRingotel API Failure: " . $errorDetail)->onQueue('messages');
        }
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
