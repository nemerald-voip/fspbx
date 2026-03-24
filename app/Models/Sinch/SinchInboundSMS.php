<?php

namespace App\Models\Sinch;

use App\Mail\SmsToEmail;
use App\Models\Messages;
use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;
use App\Jobs\SendSmsNotificationToSlack;
use App\Services\RingotelApiService;

class SinchInboundSMS extends Model
{
    public $org_id;
    public $message_uuid;
    public $email;
    public $extension;

    public function send()
    {
        $message = Messages::where('message_uuid', $this->message_uuid)->first();

        if (!$message) {
            logger("Could not find sms entity for message_uuid " . $this->message_uuid);
            return false;
        }

        try {
            $ringotelApiService = new RingotelApiService();

            if ($message->type === 'mms') {
                $mediaUrls = $this->buildMediaUrlsForRingotel($message);

                if (empty($mediaUrls)) {
                    throw new \Exception("No media files to send for MMS message UUID: " . $this->message_uuid);
                }

                $lastResponse = null;

                foreach ($mediaUrls as $mediaUrl) {
                    $params = [
                        'orgid' => $this->org_id,
                        'from' => $message->source,
                        'to' => $this->extension,
                        'content' => $mediaUrl,
                        'type' => 7,
                    ];

                    $lastResponse = $ringotelApiService->message($params);

                    if (!isset($lastResponse['messageid'])) {
                        throw new \Exception(
                            "No messageid returned for MMS (media: {$mediaUrl}). Response: " . json_encode($lastResponse)
                        );
                    }
                }

                $this->updateMessageStatus($message, $lastResponse);
            } else {
                $params = [
                    'orgid' => $this->org_id,
                    'from' => $message->source,
                    'to' => $this->extension,
                    'content' => $message->message,
                    'type' => 1,
                ];

                $response = $ringotelApiService->message($params);

                if (!isset($response['messageid'])) {
                    throw new \Exception("No messageid returned for SMS. Response: " . json_encode($response));
                }

                $this->updateMessageStatus($message, $response);
            }
        } catch (\Throwable $e) {
            logger("Error delivering SMS/MMS to Ringotel: {$e->getMessage()}");

            SendSmsNotificationToSlack::dispatch(
                "*Sinch Inbound SMS/MMS Failed*: From: " . $message->source . " To: " . $this->extension .
                    "\nError delivering to Ringotel: {$e->getMessage()}"
            )->onQueue('messages');

            $this->updateMessageStatus($message, null);

            return false;
        }

        return true;
    }

    private function buildMediaUrlsForRingotel(Messages $message): array
    {
        $media = is_array($message->media) ? $message->media : [];

        if (empty($media)) {
            return [];
        }

        $urls = [];

        foreach ($media as $item) {
            if (is_string($item) && !empty($item)) {
                if ($this->shouldSkipMediaItem($item, null)) {
                    continue;
                }

                $urls[] = $item;
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            if ($this->shouldSkipMediaItem($item['access_path'] ?? null, $item['mime_type'] ?? null)) {
                continue;
            }

            if (!empty($item['access_path'])) {
                $urls[] = filter_var($item['access_path'], FILTER_VALIDATE_URL)
                    ? $item['access_path']
                    : url($item['access_path']);
                continue;
            }

            if (!empty($item['url'])) {
                if ($this->shouldSkipMediaItem($item['url'], $item['mime_type'] ?? null)) {
                    continue;
                }

                $urls[] = $item['url'];
                continue;
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    private function shouldSkipMediaItem(?string $pathOrUrl, ?string $mimeType = null): bool
    {
        if ($mimeType) {
            $mimeType = strtolower($mimeType);

            if (in_array($mimeType, ['application/smil', 'application/smil+xml', 'text/smil', 'text/html'], true)) {
                return true;
            }
        }

        if (!$pathOrUrl) {
            return false;
        }

        $path = parse_url($pathOrUrl, PHP_URL_PATH) ?? $pathOrUrl;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $extension === 'smil';
    }

    private function updateMessageStatus($message, $response)
    {
        if (isset($response['messageid'])) {
            $message->status = 'success';
            $message->reference_id = $response['messageid'];
        } else {
            $message->status = 'failed';
            $errorDetail = json_encode($response);

            SendSmsNotificationToSlack::dispatch(
                "*Sinch Inbound SMS Failed*: From: " . $message->source . " To: " . $this->extension .
                    "\nRingotel API Error: No message ID received. Details: " . $errorDetail
            )->onQueue('messages');
        }

        $message->save();
    }

    public function smsToEmail()
    {
        $message = Messages::where('message_uuid', $this->message_uuid)->first();

        if (!$message) {
            logger("Could not find sms entity for message_uuid " . $this->message_uuid);
            return false;
        }

        $settings = DefaultSettings::where('default_setting_category', 'sms')->get();

        if ($settings) {
            foreach ($settings as $setting) {
                if ($setting->default_setting_subcategory == "smtp_from") {
                    $attributes['smtp_from'] = $setting->default_setting_value;
                }
                if ($setting->default_setting_subcategory == "email_company_address") {
                    $attributes['company_address'] = $setting->default_setting_value;
                }
                if ($setting->default_setting_subcategory == "smtp_from_name") {
                    $attributes['smtp_from_name'] = $setting->default_setting_value;
                }
            }
        }

        $attributes['orgid'] = $this->org_id;
        $attributes['from'] = $message->source;
        $attributes['email_to'] = $this->email;
        $attributes['message'] = $message->message;
        $attributes['email_subject'] = 'SMS Notification: New Message from ' . $message->source;

        Mail::to($this->email)->send(new SmsToEmail($attributes));

        if ($message->status == "queued") {
            $message->status = 'emailed';
        }

        $message->save();

        return true;
    }

    public function wasSent()
    {
        return true;
    }
}
