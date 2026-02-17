<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Models\DefaultSettings;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;

abstract class BaseMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $attributes;

    public function __construct($attributes = [])
    {
        $this->attributes = $this->mergeDefaultSettings($attributes);
    }

    private function mergeDefaultSettings($attributes)
    {
        $settings = DefaultSettings::where('default_setting_category', 'email')->get();

        foreach ($settings as $setting) {
            switch ($setting->default_setting_subcategory) {
                case "smtp_from":
                    $attributes['unsubscribe_email'] = $setting->default_setting_value;
                    break;
                case "support_email":
                    $attributes['support_email'] = $setting->default_setting_value;
                    break;
                case "email_company_address":
                    $attributes['company_address'] = $setting->default_setting_value;
                    break;
                case "email_company_name":
                    $attributes['company_name'] = $setting->default_setting_value;
                    break;
                case "help_url":
                    $attributes['help_url'] = $setting->default_setting_value;
                    break;
            }
        }

        if (!isset($attributes['unsubscribe_email'])) {
            $attributes['unsubscribe_email'] = "";
        }

        return $attributes;
    }

    public function headers(): Headers
    {
        $text = [];

        if (!empty($this->attributes['unsubscribe_email'])) {
            $text['List-Unsubscribe']      = '<mailto:'.$this->attributes['unsubscribe_email'].'>';
        }
    
        if (!empty($this->attributes['logId'])) {
            $text['X-Email-Log-Id'] = $this->attributes['logId'];
        }
    
        return new Headers(text: $text);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $fromEmail = $this->attributes['from_email'] ?? config('mail.from.address');
        $fromName  = $this->attributes['from_name']  ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: $this->attributes['email_subject'] ?? '',
        );
    }
}
