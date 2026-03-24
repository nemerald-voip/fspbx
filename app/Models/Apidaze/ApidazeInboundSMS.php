<?php

namespace App\Models\Apidaze;

use App\Mail\SmsToEmail;
use App\Models\Messages;
use App\Models\DefaultSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use App\Jobs\SendSmsNotificationToSlack;
use App\Services\RingotelApiService;

/**
 * @property string|null $org_id
 * @property string|null $message_uuid
 * @property string|null $email
 * @property string|null $extension
 */
class ApidazeInboundSMS extends Model
{
    public $org_id;
    public $message_uuid;
    public $email;
    public $extension;

    /**
     * Send SMS/MMS message to Ringotel.
     */
    public function send(): bool
    {
        $message = Messages::where('message_uuid', $this->message_uuid)->first();

        if (!$message) {
            logger("Could not find message entity for message_uuid {$this->message_uuid}");
            return false;
        }

        try {
            $ringotelApiService = new RingotelApiService();

            if ($message->type === 'mms') {
                $mediaUrls = $this->buildMediaUrlsForRingotel($message);

                if (empty($mediaUrls)) {
                    throw new \Exception("No media files to send for MMS message UUID: {$this->message_uuid}");
                }

                foreach ($mediaUrls as $mediaUrl) {
                    $params = [
                        'orgid'   => $this->org_id,
                        'from'    => $message->source,
                        'to'      => $this->extension,
                        'content' => $mediaUrl,
                        'type'    => 7,
                    ];

                    $response = $ringotelApiService->message($params);

                    if (!isset($response['messageid'])) {
                        throw new \Exception(
                            "No messageid returned for MMS (media: {$mediaUrl}). Response: " . json_encode($response)
                        );
                    }
                }

                // If the MMS also contains text, send it separately as SMS.
                if (!empty($message->message)) {
                    $params = [
                        'orgid'   => $this->org_id,
                        'from'    => $message->source,
                        'to'      => $this->extension,
                        'content' => $message->message,
                    ];

                    $response = $ringotelApiService->message($params);

                    if (!isset($response['messageid'])) {
                        throw new \Exception(
                            "No messageid returned for MMS text portion. Response: " . json_encode($response)
                        );
                    }
                }

                $this->updateMessageStatus($message, $response ?? null);
            } else {
                $params = [
                    'orgid'   => $this->org_id,
                    'from'    => $message->source,
                    'to'      => $this->extension,
                    'content' => $message->message,
                ];

                $response = $ringotelApiService->message($params);

                if (!isset($response['messageid'])) {
                    throw new \Exception("No messageid returned for SMS. Response: " . json_encode($response));
                }

                $this->updateMessageStatus($message, $response);
            }
        } catch (\Throwable $e) {
            logger("Error delivering Apidaze SMS/MMS to Ringotel: {$e->getMessage()}");

            SendSmsNotificationToSlack::dispatch(
                "*Apidaze Inbound SMS/MMS Failed*. From: {$message->source} To: {$this->extension}"
                . "\nError delivering to Ringotel: {$e->getMessage()}"
            )->onQueue('messages');

            $this->updateMessageStatus($message, null);

            return false;
        }

        return true;
    }

    private function updateMessageStatus(Messages $message, $response): void
    {
        if (isset($response['messageid'])) {
            $message->status = 'success';
            $message->reference_id = $response['messageid'];
        } else {
            $message->status = 'failed';

            $errorDetail = json_encode($response);

            SendSmsNotificationToSlack::dispatch(
                "*Apidaze Inbound SMS Failed*. From: {$message->source} To: {$this->extension}"
                . "\nRingotel API Error: No message ID received. Details: {$errorDetail}"
            )->onQueue('messages');
        }

        $message->save();
    }

    /**
     * Send the inbound SMS/MMS over email.
     */
    public function smsToEmail(): bool
    {
        $message = Messages::where('message_uuid', $this->message_uuid)->first();

        if (!$message) {
            logger("Could not find message entity for message_uuid {$this->message_uuid}");
            return false;
        }

        $attributes = $this->getEmailSettings();
        $attributes['orgid'] = $this->org_id;
        $attributes['from'] = $message->source;
        $attributes['email_to'] = $this->email;
        $attributes['message'] = $message->message;
        $attributes['email_subject'] = 'SMS Notification: New Message from ' . $message->source;
        $attributes['media_urls'] = $this->buildMediaUrlsForEmail($message);

        if (!empty($attributes['media_urls'])) {
            $attributes['message'] .= "\n\nMedia:\n" . implode("\n", $attributes['media_urls']);
        }

        Mail::to($this->email)->send(new SmsToEmail($attributes));

        if ($message->status === 'queued') {
            $message->status = 'emailed';
            $message->save();
        }

        return true;
    }

    public function wasSent(): bool
    {
        return true;
    }

    private function getEmailSettings(): array
    {
        $attributes = [];

        $settings = DefaultSettings::where('default_setting_category', 'sms')->get();

        foreach ($settings as $setting) {
            if ($setting->default_setting_subcategory === 'smtp_from') {
                $attributes['smtp_from'] = $setting->default_setting_value;
            }

            if ($setting->default_setting_subcategory === 'email_company_address') {
                $attributes['company_address'] = $setting->default_setting_value;
            }

            if ($setting->default_setting_subcategory === 'smtp_from_name') {
                $attributes['smtp_from_name'] = $setting->default_setting_value;
            }
        }

        return $attributes;
    }

    /**
     * URLs for Ringotel MMS delivery.
     * Prefer access_path because your controller now streams the actual file bytes.
     */
    private function buildMediaUrlsForRingotel(Messages $message): array
    {
        $media = $this->decodeMedia($message->media);

        if (empty($media)) {
            return [];
        }

        $urls = [];

        foreach ($media as $item) {
            // Backward compatibility: old format may be plain URL strings
            if (is_string($item) && !empty($item)) {
                $urls[] = $item;
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            // Preferred for new private-storage setup:
            // send app URL that streams bytes directly with correct Content-Type
            if (!empty($item['access_path'])) {
                $urls[] = url($item['access_path']);
                continue;
            }

            // Legacy direct URL support
            if (!empty($item['url'])) {
                $urls[] = $item['url'];
                continue;
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * URLs for email body.
     * Same approach as Ringotel for now.
     */
    private function buildMediaUrlsForEmail(Messages $message): array
    {
        $media = $this->decodeMedia($message->media);

        if (empty($media)) {
            return [];
        }

        $urls = [];

        foreach ($media as $item) {
            if (is_string($item) && !empty($item)) {
                $urls[] = $item;
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            if (!empty($item['access_path'])) {
                $urls[] = url($item['access_path']);
                continue;
            }

            if (!empty($item['url'])) {
                $urls[] = $item['url'];
                continue;
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    private function decodeMedia($media): array
    {
        if (is_string($media)) {
            $media = json_decode($media, true);
        }

        return is_array($media) ? $media : [];
    }
}