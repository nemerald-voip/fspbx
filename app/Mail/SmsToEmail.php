<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Models\DefaultSettings;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Content;


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

        $settings = DefaultSettings::where('default_setting_category', 'email')->get();
        if ($settings) {
            foreach ($settings as $setting) {
                if ($setting->default_setting_subcategory == "smtp_from") {
                    $attributes['unsubscribe_email'] = $setting->default_setting_value;
                }
                if ($setting->default_setting_subcategory == "support_email") {
                    $attributes['support_email'] = $setting->default_setting_value;
                }
            }
            if (!isset($attributes['unsubscribe_email'])) {
                $attributes['unsubscribe_email'] = "";
            }
        }

        $this->attributes = $attributes;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->withSymfonyMessage(function ($message) {
            $message->getHeaders()->addTextHeader('List-Unsubscribe', 'mailto:' . $this->attributes['unsubscribe_email']);
        });

        return $this->subject('SMS Notification: New Message from ' . $this->attributes['from'])
            ->from($this->attributes['smtp_from'], $this->attributes['smtp_from_name']);

    }

        /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.messages.message',
            text: 'emails.messages.message-text'
        );
    }
}
