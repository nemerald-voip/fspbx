<?php

namespace App\Mail;

use App\Models\DefaultSettings;
use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

abstract class BaseMailable extends Mailable
{
    use Queueable, SerializesModels;

    public array $attributes;

    protected ?array $databaseTemplate = null;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $this->mergeDefaultSettings($attributes);
    }

    private function mergeDefaultSettings(array $attributes): array
    {
        $attributes['app_name'] = $attributes['app_name'] ?? config('app.name', 'FS PBX');
        $attributes['product_url'] = $attributes['product_url'] ?? config('app.url', '');
        $settings = DefaultSettings::where('default_setting_category', 'email')->get();

        foreach ($settings as $setting) {
            switch ($setting->default_setting_subcategory) {
                case 'smtp_from':
                    $attributes['unsubscribe_email'] = $setting->default_setting_value;
                    break;
                case 'support_email':
                    $attributes['support_email'] = $setting->default_setting_value;
                    break;
                case 'email_company_address':
                    $attributes['company_address'] = $setting->default_setting_value;
                    break;
                case 'email_company_name':
                    $attributes['company_name'] = $setting->default_setting_value;
                    break;
                case 'help_url':
                    $attributes['help_url'] = $setting->default_setting_value;
                    break;
            }
        }

        $attributes['unsubscribe_email'] ??= '';

        return $attributes;
    }

    public function headers(): Headers
    {
        $headers = [];

        if (! empty($this->attributes['unsubscribe_email'])) {
            $headers['List-Unsubscribe'] = '<mailto:'.$this->attributes['unsubscribe_email'].'>';
        }

        if (! empty($this->attributes['logId'])) {
            $headers['X-Email-Log-Id'] = $this->attributes['logId'];
        }

        return new Headers(text: $headers);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $fromEmail = $this->attributes['from_email'] ?? config('mail.from.address');
        $fromName = $this->attributes['from_name'] ?? config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: $this->attributes['email_subject'] ?? '',
        );
    }

    protected function useEmailTemplate(string $category, string $subcategory): void
    {
        $this->databaseTemplate = app(EmailTemplateService::class)->render(
            $category,
            $subcategory,
            $this->attributes['domain_uuid'] ?? null,
            $this->attributes,
            $this->attributes['language'] ?? 'en-us'
        );

        if ($this->databaseTemplate['available'] && filled($this->databaseTemplate['subject'])) {
            $this->attributes['email_subject'] = $this->databaseTemplate['subject'];
        }
    }

    protected function databaseTemplateContent(Content $fallback): Content
    {
        if (! $this->databaseTemplate || ! $this->databaseTemplate['available']) {
            return $fallback;
        }

        $text = $this->databaseTemplate['text'];

        return new Content(
            text: filled($text) ? 'emails.rendered-text' : $fallback->text,
            with: filled($text) ? ['renderedText' => $text] : [],
            htmlString: $this->databaseTemplate['html'],
        );
    }
}
