<?php

namespace App\Jobs;

use App\Models\Voicemails;
use App\Models\SmsDestinations;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\Messaging\Outbound\CreateOutboundMessageService;
use App\Services\Messaging\Outbound\Data\CreateOutboundMessageData;
use libphonenumber\PhoneNumberFormat;

class SendNewVoicemailNotificationBySms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $data;

    public $tries = 10;
    public $maxExceptions = 3;
    public $timeout = 120;
    public $failOnTimeout = true;
    public $backoff = [30, 60, 120, 300];
    public $deleteWhenMissingModels = true;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->onQueue('messages');
    }

    public function handle(CreateOutboundMessageService $outbound): void
    {
        messaging_webhook_debug('SendNewVoicemailNotificationBySms started', [
            'data' => $this->data,
        ]);

        Redis::throttle('messages')->allow(2)->every(1)->then(function () use ($outbound) {
            $data = $this->data;

            $domainUuid = $data['domain_uuid'] ?? null;

            if (!$domainUuid) {
                throw new \RuntimeException('Missing domain_uuid in voicemail SMS notification payload');
            }

            $voicemail = Voicemails::where('domain_uuid', $domainUuid)
                ->where('voicemail_id', $data['voicemail_id'] ?? null)
                ->select(['voicemail_uuid', 'voicemail_sms_to'])
                ->firstOrFail();

            messaging_webhook_debug('Voicemail lookup complete', [
                'domain_uuid' => $domainUuid,
                'voicemail_id' => $data['voicemail_id'] ?? null,
                'voicemail_uuid' => $voicemail->voicemail_uuid,
                'voicemail_sms_to' => $voicemail->voicemail_sms_to,
            ]);

            if (empty($voicemail->voicemail_sms_to)) {
                messaging_webhook_debug('Voicemail SMS notification skipped: no destination configured', [
                    'voicemail_uuid' => $voicemail->voicemail_uuid,
                ]);

                return;
            }

            $countryCode = get_domain_setting('country', $domainUuid) ?? 'US';

            $source = get_domain_setting('sms_notification_from_number', $domainUuid);

            if (empty($source)) {
                throw new \RuntimeException('sms_notification_from_number is not configured');
            }

            $normalizedSource = formatPhoneNumber($source, $countryCode, PhoneNumberFormat::E164) ?: $source;
            $normalizedDestination = formatPhoneNumber(
                $voicemail->voicemail_sms_to,
                $countryCode,
                PhoneNumberFormat::E164
            ) ?: $voicemail->voicemail_sms_to;

            $phoneNumberSmsConfig = $this->getPhoneNumberSmsConfig($normalizedSource, $domainUuid);

            messaging_webhook_debug('SMS config resolved for voicemail notification', [
                'source' => $normalizedSource,
                'destination' => $normalizedDestination,
                'carrier' => $phoneNumberSmsConfig->carrier,
                'sms_config_domain_uuid' => $phoneNumberSmsConfig->domain_uuid,
            ]);

            if (!empty($data['message_date'])) {
                $data['message_date'] = Carbon::createFromTimestamp($data['message_date'])
                    ->setTimezone(get_local_time_zone($domainUuid))
                    ->format('Y-m-d H:i');
            }

            $textTemplate = get_domain_setting('sms_notification_text', $domainUuid) ?? '';

            if ($textTemplate === '') {
                throw new \RuntimeException('sms_notification_text is not configured');
            }

            if (!empty($data['transcription'])) {
                $textTemplate .= "\n\nTranscript: " . $data['transcription'];
            }

            $messageBody = preg_replace_callback(
                '/\$\{([a-zA-Z0-9_]+)\}/',
                function ($matches) use ($data) {
                    return $data[$matches[1]] ?? '';
                },
                $textTemplate
            );

            messaging_webhook_debug('Voicemail notification message rendered', [
                'message_preview' => mb_substr((string) $messageBody, 0, 120),
            ]);

            $message = $outbound->create(CreateOutboundMessageData::from([
                'domainUuid' => $phoneNumberSmsConfig->domain_uuid,
                'extensionUuid' => null,
                'source' => $normalizedSource,
                'destination' => $normalizedDestination,
                'message' => (string) $messageBody,
                'origin' => 'voicemail_notification',
                'carrier' => $phoneNumberSmsConfig->carrier,
                'media' => [],
                'meta' => [
                    'voicemail_uuid' => $voicemail->voicemail_uuid,
                    'voicemail_id' => $data['voicemail_id'] ?? null,
                    'notification_type' => 'new_voicemail_sms',
                ],
            ]));

            messaging_webhook_debug('Voicemail notification outbound message created', [
                'message_uuid' => $message->message_uuid,
                'status' => $message->status,
                'carrier' => $phoneNumberSmsConfig->carrier,
            ]);
        }, function () {
            messaging_webhook_debug('SendNewVoicemailNotificationBySms throttled, releasing', [
                'data' => $this->data,
            ]);

            return $this->release(15);
        });
    }

    private function getPhoneNumberSmsConfig(string $from, string $domainUuid): SmsDestinations
    {
        $phoneNumberSmsConfig = SmsDestinations::where('domain_uuid', $domainUuid)
            ->where('destination', $from)
            ->where('enabled', 'true')
            ->first();

        if (!$phoneNumberSmsConfig) {
            throw new \RuntimeException("SMS configuration not found for phone number {$from}");
        }

        return $phoneNumberSmsConfig;
    }
}