<?php

namespace App\Models\Commio;

use App\Mail\SmsToEmail;
use App\Models\Messages;
use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;
use App\Jobs\SendSmsNotificationToSlack;

/**
 * @property string|null $domain_setting_value
 * @property string|null $to_did
 * @property string|null $from_did
 * @property string|null $message
 * @property string|null $message_uuid
 */
class SynchInboundSMS extends Model
{
    public $org_id;
    public $to_did;
    public $from_did;
    public $message;
    public $message_uuid;
    public $email_to;

    /**
     * Send the outbound SMS message.
     *
     * @return bool
     */
    public function send()
    {
        $message = Messages::find($this->message_uuid);

        if (!$message) {
            Log::alert("Could not find sms entity from ".$this->from_did." to ".$this->to_did);
        }

        // Logic to deliver the SMS message using a third-party Ringotel API,
        // This method should return a boolean indicating whether the message was sent successfully.
        $response = Http::ringotel_api()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode([
                'method' => 'message',
                'params' => [
                    'orgid' => $this->org_id,
                    'from' => $this->from_did,
                    'to' => $this->to_did,
                    'content' => $this->message
                ]
            ]), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                // Notification::route('mail', 'dexter@stellarvoip.com')
                //     ->notify(new StatusUpdate("error"));
                Log::alert("Error delivering SMS to Ringotel");
                SendSmsNotificationToSlack::dispatch("Error delivering SMS to Ringotel")->onQueue('messages');
                return false;
            })
            ->json();

        if ($message) {
            if (isset($response['result'])) {
                $message->status = 'success';
            } elseif (isset($response['error'])) {
                $message->status = json_encode($response['error']);
                SendSmsNotificationToSlack::dispatch("Ringotel API Error: " . $message->status .". Commio Inbound SMS from " . $message->source . " to " . $message->destination )->onQueue('messages');
            } else {
                $message->status = 'unknown';
                SendSmsNotificationToSlack::dispatch("Ringotel API Unknown Error. Commio Inbound SMS from " . $message->source . " to " . $message->destination )->onQueue('messages');
            }
            $message->save();
        }
        //Log::alert($response);

        return true; // Change this to reflect the result of the API call.
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
            Log::alert("Could not find sms entity from ".$this->from_did." to ".$this->to_did);
        }

        $settings = DefaultSettings::where('default_setting_category','sms')->get();
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
        $attributes['from'] = $this->from_did;
        $attributes['email_to'] = $this->email_to;
        $attributes['message'] = $this->message;

        // Logic to deliver the SMS message using email
        // This method should return a boolean indicating whether the message was sent successfully.
       Mail::to($this->email_to)->send(new SmsToEmail($attributes));

        if ($message->status = "Queued") {
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
