<?php

namespace App\Services;

use App\Models\Destinations;
use App\Models\Domain;
use App\Models\Extensions;
use App\Models\User;
use Illuminate\Support\Collection;

class ExtensionWelcomeEmailService
{
    public function options(
        array $extensionUuids,
        string $domainUuid,
        ?string $recipientOverride = null
    ): array {
        $extensionUuids = array_values(array_unique(array_map('strval', $extensionUuids)));
        $extensions = $this->extensions($extensionUuids, $domainUuid)->keyBy('extension_uuid');
        $directNumbers = $this->directNumbersForExtensions($extensions, $domainUuid);
        $single = count($extensionUuids) === 1;

        $items = collect($extensionUuids)->map(function (string $extensionUuid) use (
            $domainUuid,
            $extensions,
            $directNumbers,
            $recipientOverride,
            $single
        ): array {
            $extension = $extensions->get($extensionUuid);

            if (! $extension) {
                return [
                    'extension_uuid' => $extensionUuid,
                    'extension' => null,
                    'name' => null,
                    'recipient' => null,
                    'voicemail_id' => null,
                    'voicemail_pin' => null,
                    'direct_numbers' => [],
                    'eligible' => false,
                    'reason' => 'Extension not found.',
                ];
            }

            $recipient = $single && filled($recipientOverride)
                ? strtolower(trim((string) $recipientOverride))
                : strtolower(trim((string) ($extension->voicemail?->voicemail_mail_to ?? '')));
            $reason = $this->ineligibleReason($extension, $recipient);

            return [
                'extension_uuid' => (string) $extension->extension_uuid,
                'extension' => (string) $extension->extension,
                'name' => $this->extensionName($extension),
                'recipient' => $recipient,
                'voicemail_id' => $extension->voicemail?->voicemail_id,
                'voicemail_pin' => $extension->voicemail?->voicemail_password,
                'direct_numbers' => $directNumbers[(string) $extension->extension_uuid] ?? [],
                'eligible' => $reason === null,
                'reason' => $reason,
            ];
        })->values();

        return [
            'items' => $items->all(),
            'summary' => [
                'selected' => $items->count(),
                'eligible' => $items->where('eligible', true)->count(),
                'skipped' => $items->where('eligible', false)->count(),
            ],
        ];
    }

    public function attributesForSend(
        string $extensionUuid,
        string $domainUuid,
        string $recipient
    ): ?array {
        $extension = $this->extensions([$extensionUuid], $domainUuid)->first();
        $recipient = strtolower(trim($recipient));

        if (! $extension || $this->ineligibleReason($extension, $recipient) !== null) {
            return null;
        }

        $domain = Domain::query()
            ->whereKey($domainUuid)
            ->first(['domain_uuid', 'domain_name', 'domain_description']);
        $user = User::query()
            ->where('domain_uuid', $domainUuid)
            ->where('user_enabled', 'true')
            ->whereRaw('LOWER(user_email) = ?', [$recipient])
            ->first(['user_uuid', 'domain_uuid', 'extension_uuid', 'user_email']);

        return [
            'domain_uuid' => $domainUuid,
            'language' => $user?->language ?: (get_domain_setting('language', $domainUuid) ?: 'en-us'),
            'recipient_name' => $this->extensionName($extension),
            'extension' => (string) $extension->extension,
            'account_name' => $domain?->domain_description ?: $domain?->domain_name ?: '',
            'phone_system_address' => $domain?->domain_name ?: '',
            'direct_numbers' => $this->directNumbersForExtensions(
                collect([$extension]),
                $domainUuid
            )[(string) $extension->extension_uuid] ?? [],
            'voicemail_id' => (string) $extension->voicemail->voicemail_id,
            'voicemail_pin' => (string) $extension->voicemail->voicemail_password,
            'portal_email' => $user?->user_email,
            'portal_login_url' => $user ? route('login') : null,
            'password_request_url' => $user ? route('password.request') : null,
        ];
    }

    private function extensions(array $extensionUuids, string $domainUuid): Collection
    {
        return Extensions::query()
            ->where('domain_uuid', $domainUuid)
            ->whereIn('extension_uuid', $extensionUuids)
            ->select([
                'extension_uuid',
                'domain_uuid',
                'extension',
                'effective_caller_id_name',
                'directory_first_name',
                'directory_last_name',
            ])
            ->with([
                'voicemail' => function ($query) use ($domainUuid) {
                    $query->where('domain_uuid', $domainUuid)
                        ->select([
                            'voicemail_uuid',
                            'domain_uuid',
                            'voicemail_id',
                            'voicemail_password',
                            'voicemail_mail_to',
                            'voicemail_enabled',
                        ]);
                },
            ])
            ->get();
    }

    private function ineligibleReason(Extensions $extension, string $recipient): ?string
    {
        $voicemail = $extension->voicemail;

        if (! $voicemail) {
            return 'No voicemail mailbox is configured.';
        }

        if (! filter_var($voicemail->voicemail_enabled, FILTER_VALIDATE_BOOLEAN)) {
            return 'Voicemail is disabled.';
        }

        if (! filled($voicemail->voicemail_password)) {
            return 'The voicemail mailbox has no PIN.';
        }

        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return 'A valid voicemail email is required.';
        }

        return null;
    }

    private function extensionName(Extensions $extension): string
    {
        $directoryName = trim(
            (string) $extension->directory_first_name.' '.(string) $extension->directory_last_name
        );

        return $directoryName
            ?: trim((string) $extension->effective_caller_id_name)
            ?: (string) $extension->extension;
    }

    private function directNumbersForExtensions(Collection $extensions, string $domainUuid): array
    {
        $numbers = $extensions
            ->mapWithKeys(fn (Extensions $extension) => [
                (string) $extension->extension_uuid => [],
            ])
            ->all();

        if ($numbers === []) {
            return [];
        }

        $routing = new CallRoutingOptionsService($domainUuid);
        $destinations = Destinations::query()
            ->where('domain_uuid', $domainUuid)
            ->where('destination_enabled', 'true')
            ->whereNotNull('destination_actions')
            ->get([
                'destination_uuid',
                'domain_uuid',
                'destination_number',
                'destination_actions',
            ]);

        foreach ($destinations as $destination) {
            $options = collect(
                $routing->reverseEngineerDestinationActions($destination->destination_actions)
            );
            $number = $destination->destination_number_formatted
                ?: $destination->destination_number;

            foreach ($options as $option) {
                $extensionUuid = is_array($option) && ($option['type'] ?? null) === 'extensions'
                    ? (string) ($option['option'] ?? '')
                    : '';

                if ($number && array_key_exists($extensionUuid, $numbers)) {
                    $numbers[$extensionUuid][] = $number;
                }
            }
        }

        return array_map(
            fn (array $extensionNumbers) => array_values(array_unique($extensionNumbers)),
            $numbers
        );
    }
}
