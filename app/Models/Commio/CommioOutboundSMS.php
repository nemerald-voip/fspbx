<?php

namespace App\Models\Commio;

use App\Jobs\SendCommioSMS;
use Illuminate\Database\Eloquent\Model;

class CommioOutboundSMS extends Model
{
    protected $fillable = ['to_did', 'from_did', 'message'];

    /**
     * CommioOutboundSMS constructor.
     *
     * @param string $to_did
     * @param string $from_did
     * @param string $message
     */
    public function __construct(string $to_did, string $from_did, string $message)
    {
        parent::__construct();

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

        SendCommioSMS::dispatch($message)->onQueue('faxes');

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
