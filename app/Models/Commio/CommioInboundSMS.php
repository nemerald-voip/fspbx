<?php

namespace App\Models\Commio;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

/**
 * @property string|null $domain_setting_value
 * @property string|null $to_did
 * @property string|null $from_did
 * @property string|null $message
 */
class CommioInboundSMS extends Model
{
    protected $fillable = [
        'domain_setting_value',
        'to_did',
        'from_did',
        'message'
    ];

    /**
     * CommioOutboundSMS constructor.
     *
     * @param  string|null  $domain_setting_value
     * @param  string|null  $to_did
     * @param  string|null  $from_did
     * @param  string|null  $message
     */
    public function __construct(
        string $domain_setting_value = null,
        string $to_did = null,
        string $from_did = null,
        string $message = null
    ) {
        $this->domain_setting_value = $domain_setting_value;
        $this->to_did = $to_did;
        $this->from_did = $from_did;
        $this->message = $message;
    }

    /**
     * Send the outbound SMS message.
     *
     * @return bool
     */
    public function send()
    {
        // Logic to send the SMS message using a third-party Commio API,
        // This method should return a boolean indicating whether the message was sent successfully.

        Log::alert('Touching ringotel with params'.print_r([
                $this->to_did, $this->from_did, $this->message, $this->domain_setting_value
            ], true));

        $response = Http::ringotel_api()
            //->dd()
            ->timeout(5)
            ->withBody(json_encode([
                'method' => 'message',
                'params' => [
                    'orgid' => $this->domain_setting_value,
                    'from' => $this->from_did,
                    'to' => '100',//$this->to_did,
                    'content' => $this->message
                ]
            ]), 'application/json')
            ->post('/')
            ->throw(function ($response, $e) {
                Notification::route('mail', 'dexter@stellarvoip.com')
                    ->notify(new StatusUpdate("error"));
                return false;
            })
            ->json();

        Log::alert('============');
        Log::alert($response);

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
