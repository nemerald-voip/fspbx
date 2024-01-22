<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Models\DefaultSettings;
use Symfony\Component\Mime\Email;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AppCredentials extends Mailable
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

        $this->attributes['qrCode'] = QrCode::format('png')
            ->generate('{"domain":"' . $this->attributes["domain"] . '","username":"' . $this->attributes["username"] . '","password":"' . $this->attributes["password"] . '"}');

        // logger($this->attributes['qrCode']);


        // return $this->subject(config('app.name', 'Laravel') . ' App Credentials')->view('emails.app.credentials');
        return $this->subject(config('app.name', 'Laravel') . ' App Credentials');
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.app.credentials',
            text: 'emails.app.credentials-text'
        );
    }
}
