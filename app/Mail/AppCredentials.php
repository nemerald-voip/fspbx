<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

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
        $settings = DefaultSettings::where('default_setting_category','email')->get();
        if ($settings) {
            foreach ($settings as $setting) {
                if ($setting->default_setting_subcategory == "smtp_from") {
                    $attributes['unsubscribe_email'] = $setting->default_setting_value;
                } else {
                    $attributes['unsubscribe_email'] = "";
                }
                if ($setting->default_setting_subcategory == "support_email") {
                    $attributes['support_email'] = $setting->default_setting_value;
                }
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
        $this->withSwiftMessage(function ($message) {
            $message->getHeaders()->addTextHeader('List-Unsubscribe', 'mailto:' . $this->attributes['unsubscribe_email']);
        });
        return $this->subject('Nemerald App Credentials')->view('emails.app.credentials');
    }
}
