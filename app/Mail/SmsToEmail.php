<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class SmsToEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $attributes;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->withSwiftMessage(function ($message) {
            $message->getHeaders()
                ->addTextHeader('List-Unsubscribe', 'mailto:' . $this->attributes['smtp_from']);
        });
        return $this->subject('SMS Notification: New Message from ' .$this->attributes['from'])
            ->from($this->attributes['smtp_from'], $this->attributes['smtp_from_name'])
        ->view('emails.plain_email');
    }
}
