<?php

namespace App\Http\Webhooks\WebhookProfiles;

use Throwable;
use App\Models\User;
use App\Models\Faxes;
use App\Models\Voicemails;
use Illuminate\Http\Request;
use App\Models\FaxAllowedEmails;
use Illuminate\Support\Facades\Log;
use App\Models\FaxAllowedDomainNames;
use libphonenumber\PhoneNumberFormat;
use App\Jobs\SendFaxInvalidEmailNotification;
use Spatie\WebhookClient\WebhookProfile\WebhookProfile;

/**
 * Base class for fax webhook profiles (Postmark, Mailgun, ...).
 *
 * Concrete profiles parse provider-specific request shapes and store
 * attachments; the shared work (authorizing the sender, normalizing the
 * destination phone number with the tenant's country setting, extracting
 * an email from a "Name <email>" string) lives here.
 *
 * Profiles must populate the request with these normalized keys before
 * returning true so the queue jobs can hand a uniform payload to
 * FaxSendService::send() regardless of provider:
 *   - fax_destination  (E.164 when possible, otherwise the raw input)
 *   - fax_uuid         (resolved from sender authorization)
 *   - from             (sender email, lowercased)
 *   - subject
 *   - body
 *   - fax_attachments  (array of metadata from FaxSendService::store*Attachment)
 */
abstract class FaxWebhookProfile implements WebhookProfile
{
    /**
     * Authorize the sender and set fax_uuid on the request. Looks the sender
     * up in this order: allowed-domain → user email → voicemail email →
     * allowed-email. Returns true if any lookup matches; otherwise dispatches
     * SendFaxInvalidEmailNotification and returns false.
     *
     * The raw destination string is passed in so the rejection email can
     * tell the sender which number they tried to fax to (this runs before
     * destination normalization, so request['fax_destination'] isn't set).
     */
    protected function resolveAuthorization(string $fromEmail, string $rawDestination, Request $request): bool
    {
        $fromEmail = strtolower($fromEmail);

        try {
            $domain = explode('@', $fromEmail)[1] ?? null;
            if (!$domain) {
                throw new \Exception("No domain found in sender email");
            }

            // 1. Allowed domain
            $domainRecord = FaxAllowedDomainNames::where('domain', $domain)->first();
            if ($domainRecord) {
                $request['fax_uuid'] = $domainRecord->fax_uuid;
                fax_webhook_debug('FaxWebhookProfile: sender authorized by allowed domain', [
                    'from'     => $fromEmail,
                    'domain'   => $domain,
                    'fax_uuid' => $request['fax_uuid'],
                ]);
                return true;
            }

            // 2. User email
            $users = User::where('user_email', $fromEmail)->get();
            foreach ($users as $user) {
                if (!$user->domain->faxes->isEmpty()) {
                    $request['fax_uuid'] = $user->domain->faxes->first()->fax_uuid;
                    fax_webhook_debug('FaxWebhookProfile: sender authorized by user email', [
                        'from'        => $fromEmail,
                        'user_uuid'   => $user->user_uuid ?? null,
                        'domain_uuid' => $user->domain_uuid ?? null,
                        'fax_uuid'    => $request['fax_uuid'],
                    ]);
                    break;
                }
            }

            // 3. Voicemail email
            if (!isset($request['fax_uuid'])) {
                $voicemails = Voicemails::where('voicemail_mail_to', $fromEmail)->get();
                foreach ($voicemails as $voicemail) {
                    if (!$voicemail->domain->faxes->isEmpty()) {
                        $request['fax_uuid'] = $voicemail->domain->faxes->first()->fax_uuid;
                        fax_webhook_debug('FaxWebhookProfile: sender authorized by voicemail email', [
                            'from'           => $fromEmail,
                            'voicemail_uuid' => $voicemail->voicemail_uuid ?? null,
                            'domain_uuid'    => $voicemail->domain_uuid ?? null,
                            'fax_uuid'       => $request['fax_uuid'],
                        ]);
                        break;
                    }
                }
            }

            // 4. Allowed-email table (only if still unresolved)
            if (!isset($request['fax_uuid'])) {
                $emailRecord = FaxAllowedEmails::where('email', $fromEmail)->first();
                if ($emailRecord) {
                    $request['fax_uuid'] = $emailRecord->fax_uuid;
                    fax_webhook_debug('FaxWebhookProfile: sender authorized by allowed email', [
                        'from'     => $fromEmail,
                        'fax_uuid' => $request['fax_uuid'],
                    ]);
                }
            }

            if (!isset($request['fax_uuid'])) {
                throw new \Exception("Sender email is not authorized for faxing.");
            }

            return true;
        } catch (Throwable $e) {
            Log::alert('Fax sender not authorized: ' . $e->getMessage());
            fax_webhook_debug('FaxWebhookProfile: sender not authorized', [
                'from'            => $fromEmail,
                'raw_destination' => $rawDestination,
                'error'           => $e->getMessage(),
            ]);

            SendFaxInvalidEmailNotification::dispatch([
                'from'            => $fromEmail,
                'fax_destination' => $rawDestination,
            ])->onQueue('faxes');

            return false;
        }
    }

    /**
     * Normalize the destination phone number using the resolved tenant's
     * country setting and store it on the request. formatPhoneNumber returns
     * the input unchanged when it can't be parsed; we set whatever it returns
     * and let the downstream fax pipeline decide what to do — an unparseable
     * number doesn't reject the webhook here.
     */
    protected function resolveDestination(string $phoneNumber, string $faxUuid, Request $request): void
    {
        $countryCode = $this->resolveCountryCode($faxUuid);

        $request['fax_destination'] = formatPhoneNumber(
            $phoneNumber,
            $countryCode,
            PhoneNumberFormat::E164
        );

        fax_webhook_debug('FaxWebhookProfile: destination normalized', [
            'fax_uuid'        => $faxUuid,
            'country_code'    => $countryCode,
            'raw_destination' => $phoneNumber,
            'fax_destination' => $request['fax_destination'],
        ]);
    }

    /**
     * Look up the tenant's country code from its domain settings.
     * Falls back to 'US' if the fax server, domain, or setting is missing.
     */
    protected function resolveCountryCode(string $faxUuid): string
    {
        $fax = Faxes::select('domain_uuid')->find($faxUuid);
        if (!$fax) {
            return 'US';
        }
        return get_domain_setting('country', $fax->domain_uuid) ?? 'US';
    }

    /**
     * Pull an email address out of a "Name <email>" string, a bare email, or
     * any string that contains one. Returns null if nothing email-shaped is
     * found.
     */
    protected function extractEmail(?string $raw): ?string
    {
        if (!$raw || !is_string($raw)) {
            return null;
        }
        if (preg_match('/<([^>]+)>/', $raw, $matches)) {
            return strtolower(trim($matches[1]));
        }
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return strtolower(trim($raw));
        }
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $raw, $matches)) {
            return strtolower(trim($matches[0]));
        }
        return null;
    }
}
