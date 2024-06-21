<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Models\DefaultSettings;
use Illuminate\Queue\SerializesModels;

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

    protected function buildMessageHeaders()
    {
        $this->withSymfonyMessage(function ($message) {
            $message->getHeaders()->addTextHeader('List-Unsubscribe', 'mailto:' . $this->attributes['unsubscribe_email']);
        });
    }
}
