<?php

namespace App\Jobs;

use App\Models\EmailLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use App\Models\DomainSettings;
use Illuminate\Support\Carbon;
use App\Models\DefaultSettings;
use App\Models\VoicemailMessages;
use App\Mail\VoicemailNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Services\FreeswitchEslService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendNewVoicemailNotificationByEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 1;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     *
     * @var bool
     */
    public $failOnTimeout = true;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [30, 60, 120, 300];

    /**
     * Delete the job if its models no longer exist.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    private array $params;
    private string $logId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($params)
    {
        $this->params = $params;
        $this->logId  = (string) Str::uuid();
        $this->onQueue('emails');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Allow only 2 tasks every 1 second
        Redis::throttle('email')->allow(2)->every(1)->then(function () {

            $message = VoicemailMessages::select('voicemail_message_uuid', 'domain_uuid', 'voicemail_uuid', 'created_epoch', 'caller_id_name', 'caller_id_number', 'message_length', 'message_transcription')
                ->with('voicemail:voicemail_uuid,voicemail_id,voicemail_mail_to,voicemail_transcription_enabled,voicemail_local_after_email,voicemail_description')
                ->with('domain:domain_uuid,domain_name')
                ->find($this->params['message_uuid']);

            // If already deleted (e.g., prior success cleanup), nothing to do
            if (!$message) return;

            $domain_uuid = $message->domain_uuid;
            // Defaults → simple [subcategory => cast(value)]
            $settings = [];
            DefaultSettings::query()
                ->select([
                    'default_setting_subcategory',
                    'default_setting_name',
                    'default_setting_value',
                    'default_setting_order',
                    'default_setting_enabled',
                ])
                ->where('default_setting_category', 'voicemail')
                ->where('default_setting_enabled', 'true')
                ->orderBy('default_setting_subcategory')
                ->get()
                ->each(function ($r) use (&$settings) {
                    $sub = (string) $r->default_setting_subcategory;
                    $settings[$sub] = $this->castSettingValue(
                        (string) $r->default_setting_name,
                        (string) $r->default_setting_value
                    );
                });

            // Domain overrides
            DomainSettings::query()
                ->select([
                    'domain_setting_subcategory',
                    'domain_setting_name',
                    'domain_setting_value',
                    'domain_setting_order',
                    'domain_setting_enabled',
                ])
                ->where('domain_uuid', $domain_uuid)
                ->where('domain_setting_category', 'voicemail')
                ->where('domain_setting_enabled', 'true')
                ->orderBy('domain_setting_subcategory')
                ->get()
                ->each(function ($r) use (&$settings) {
                    $sub = (string) $r->domain_setting_subcategory;
                    $settings[$sub] = $this->castSettingValue(
                        (string) $r->domain_setting_name,
                        (string) $r->domain_setting_value
                    );
                });


            $subcategory = (data_get($settings, 'transcribe_enabled') && data_get($message, 'voicemail.voicemail_transcription_enabled') === 'true')
                ? 'transcription'
                : 'default';

            $attachment_path = null; // Initialize attachment path as null

            // Get voicemail file path
            $base_path = ($message->domain?->domain_name ?? '') . '/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid;
            $wav_path = $base_path . '.wav';
            $mp3_path = $base_path . '.mp3';

            // Check for WAV file first, then fall back to MP3
            if (Storage::disk('voicemail')->exists($wav_path)) {
                $attachment_path = $wav_path;
            } elseif (Storage::disk('voicemail')->exists($mp3_path)) {
                $attachment_path = $mp3_path;
            } else {
                // Log an error. The user still gets the notification email even if the audio file is missing.
                logger()->error('Voicemail audio file not found for message: ' . $message->voicemail_message_uuid);
            }

            // If transcription is enabled and not already present, try to transcribe
            $shouldTranscribe = data_get($settings, 'transcribe_enabled')
                && (data_get($message, 'voicemail.voicemail_transcription_enabled') === 'true')
                && empty($message->message_transcription)
                && $attachment_path;

            if ($shouldTranscribe) {
                $result = app(\App\Services\VoicemailTranscriptionService::class)->transcribe([
                    'file_path'   => Storage::disk('voicemail')->path($attachment_path),
                    'provider'    => $settings['transcribe_provider'] ?? 'openai',
                    'language'    => $settings['transcribe_language'] ?? 'en-US',
                    'domain_uuid' => $domain_uuid,
                ]);

                // Extract text from normalized response
                $text = is_array($result) ? trim((string) Arr::get($result, 'message', '')) : '';
                if ($text !== '') {

                    // Persist without clobbering an existing value (idempotent on retries)
                    VoicemailMessages::where('voicemail_message_uuid', $message->voicemail_message_uuid)
                        ->whereNull('message_transcription')
                        ->update(['message_transcription' => $text]);

                    // Ensure in-memory instance is current for template variables below
                    $message->refresh();
                }
            }

            $template = EmailTemplate::where(function ($q) use ($domain_uuid) {
                $q->where('domain_uuid', $domain_uuid)
                    ->orWhereNull('domain_uuid');
            })
                ->select([
                    'template_subject',
                    'template_body',
                ])
                ->where('template_language', $this->params['default_language'] . '-' . $this->params['default_dialect'])
                ->where('template_category', 'voicemail')
                ->where('template_subcategory', $subcategory)
                ->where('template_enabled', 'true')
                // Prefer domain-specific rows over global rows:
                ->orderBy('domain_uuid', 'desc')
                ->first();


            $subjectTpl = $template->template_subject ?? 'New Voicemail from {{ caller_id_number }}';
            $bodyTpl    = $template->template_body ?? '<p>You have a new voicemail from {{ caller_id_number }}</p>';
            $timezone = get_local_time_zone($domain_uuid);

            $vars = [
                'caller_id_number' => (string) $message->caller_id_number,
                'caller_id_name'   => (string) $message->caller_id_name,
                'voicemail_id'     => (string) ($message->voicemail?->voicemail_id ?? ''),
                'voicemail_description'     => (string) ($message->voicemail?->voicemail_description ?? ''),
                'dialed_user' => (string) ($message->voicemail?->voicemail_id ?? ''),
                'message_date'       => Carbon::createFromTimestamp($message->created_epoch)->tz($timezone)->toDayDateTimeString(),
                'message_duration'   => gmdate('i\m s\s', (int) $message->message_length),
                'message_text'    => (string) $message->message_transcription,
                // add more as needed…
            ];

            $replacements = [];
            foreach ($vars as $k => $v) {
                $replacements['${' . $k . '}'] = e($v);
            }
            $subject = strtr($subjectTpl, $replacements);
            $bodyHtml = strtr($bodyTpl, $replacements);

            $attributes['email_subject'] = $subject;
            $attributes['bodyHtml'] = $bodyHtml;
            $attributes['domain_uuid'] = $domain_uuid;
            $attributes['logId'] = $this->logId;
            $attributes['attachment_path'] = ($message->voicemail?->voicemail_file ?? null) ? $attachment_path : null;

            $uuid    = (string) $message->voicemail_message_uuid;
            $sentKey = "vm:sent:$uuid";

            if (!Cache::has($sentKey)) {
                try {
                    // send; if it throws, the job RETRIES
                    Mail::to($message->voicemail?->voicemail_mail_to ?? null)
                        ->send(new VoicemailNotification($attributes));

                    // mark as sent so future retries won't resend
                    Cache::put($sentKey, 1, now()->addDays(1));
                } catch (\Throwable $e) {
                    logger()->error('Voicemail email send failed', ['uuid' => $uuid, 'error' => $e->getMessage()]);
                    throw $e; // ensure retry on failure
                }
            }

            // Delete local voicemail if required by voicemail settings
            if (($message->voicemail?->voicemail_local_after_email ?? 'true') !== 'true') {
                $this->deleteVoicemailSilently($message);
            }
        }, function () {
            // Could not obtain lock; this job will be re-queued
            return $this->release(15);
        });
    }

    public function failed(\Throwable $e): void
    {
        // Best effort: if the row exists, mark permanent failure and store the last error.
        EmailLog::where('uuid', $this->logId)->update([
            'status'      => 'permanent_failed',   // distinct from interim 'failed' if you want
            'sent_debug_info'  => $e->getMessage(),

        ]);
    }

    /**
     * Cast common types: text, numeric, boolean, json.
     * Falls back to string.
     */
    private function castSettingValue(string $type, string $value)
    {
        $t = strtolower($type);

        if ($t === 'boolean' || $t === 'bool' || $t === 'enabled') {
            return in_array(strtolower($value), ['true', 't', '1', 'yes', 'on'], true);
        }

        if ($t === 'numeric' || $t === 'number' || $t === 'integer' || $t === 'int' || $t === 'float') {
            return is_numeric($value) ? ($value + 0) : $value;
        }

        if ($t === 'json') {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        // text, password, select, label, etc.
        return $value;
    }

    private function deleteVoicemailSilently(\App\Models\VoicemailMessages $message): void
    {
        $domainName  = (string) ($message->domain?->domain_name ?? '');
        $voicemailId = (string) ($message->voicemail?->voicemail_id ?? '');
        $uuid        = (string) $message->voicemail_message_uuid;

        try {
            $base  = $domainName . '/' . $voicemailId . '/msg_' . $uuid;
            foreach ([$base . '.wav', $base . '.mp3'] as $p) {
                if (Storage::disk('voicemail')->exists($p)) {
                    Storage::disk('voicemail')->delete($p);
                }
            }
            $message->delete();

            // Best-effort MWI; never throw
            try {
                $esl = app(FreeswitchEslService::class);
                $esl->executeCommand(sprintf("bgapi luarun app.lua voicemail mwi '%s'@'%s'", $voicemailId, $domainName));
            } catch (\Throwable $e) {
                logger()->warning('MWI update failed after voicemail delete', ['v' => $voicemailId, 'd' => $domainName, 'e' => $e->getMessage()]);
            }
        } catch (\Throwable $e) {
            logger()->warning('Voicemail cleanup failed', ['uuid' => $uuid, 'e' => $e->getMessage()]);
        }
    }
}
