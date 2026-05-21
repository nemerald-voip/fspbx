<?php

namespace App\Services;

use App\Models\BasicDialerCampaign;
use App\Models\BasicDialerCampaignAttempt;
use App\Models\BasicDialerCampaignRecipient;
use App\Models\BasicDialerContact;
use App\Models\BasicDialerContactList;
use App\Models\CDR;
use App\Models\Domain;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class BasicDialerService
{
    private const ACTIVE_ATTEMPT_STATUSES = ['dialing'];
    private const DUE_RECIPIENT_STATUSES = ['pending', 'retry_wait'];
    private const TERMINAL_RECIPIENT_STATUSES = ['answered', 'failed', 'stopped'];

    public function saveContactList(array $validated, ?BasicDialerContactList $contactList = null): BasicDialerContactList
    {
        return DB::transaction(function () use ($validated, $contactList) {
            $contactList ??= new BasicDialerContactList();

            $contactList->forceFill([
                'domain_uuid' => session('domain_uuid'),
                'name' => $validated['name'],
                'description' => $this->blankToNull($validated['description'] ?? null),
                'enabled' => $validated['enabled'] ?? true,
            ])->save();

            if (filled($validated['contacts'] ?? null)) {
                $this->importContacts($contactList, $validated['contacts']);
            }

            return $contactList->refresh();
        });
    }

    public function saveCampaign(array $validated, ?BasicDialerCampaign $campaign = null): BasicDialerCampaign
    {
        return DB::transaction(function () use ($validated, $campaign) {
            $campaign ??= new BasicDialerCampaign();
            $destination = $this->destinationSelection($validated['destination_target'] ?? null);

            $campaign->forceFill([
                'domain_uuid' => session('domain_uuid'),
                'basic_dialer_contact_list_uuid' => $this->blankToNull($validated['basic_dialer_contact_list_uuid'] ?? null),
                'name' => $validated['name'],
                'description' => $this->blankToNull($validated['description'] ?? null),
                'status' => $campaign->status ?: 'draft',
                'enabled' => $validated['enabled'] ?? true,
                'caller_id_name' => $this->blankToNull($validated['caller_id_name'] ?? null),
                'caller_id_number' => $this->normalizePhoneNumber($validated['caller_id_number'] ?? null),
                'destination_type' => $this->blankToNull($validated['destination_type'] ?? null),
                'destination_target' => $this->defaultDestinationTarget($validated['destination_type'] ?? null, $destination['value']),
                'destination_label' => $destination['label'] ?: $this->defaultDestinationLabel($validated['destination_type'] ?? null),
                'max_concurrent_calls' => (int) ($validated['max_concurrent_calls'] ?? 1),
                'seconds_between_calls' => (int) ($validated['seconds_between_calls'] ?? 5),
                'retry_limit' => (int) ($validated['retry_limit'] ?? 0),
                'retry_delay_minutes' => (int) ($validated['retry_delay_minutes'] ?? 60),
                'originate_timeout' => (int) ($validated['originate_timeout'] ?? 30),
            ])->save();

            $this->syncCampaignRecipients($campaign);

            return $campaign->refresh();
        });
    }

    public function deleteCampaigns(Collection $campaigns): int
    {
        return DB::transaction(function () use ($campaigns) {
            $campaignUuids = $campaigns->pluck('basic_dialer_campaign_uuid');

            BasicDialerCampaignAttempt::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('basic_dialer_campaign_uuid', $campaignUuids)
                ->delete();

            BasicDialerCampaignRecipient::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('basic_dialer_campaign_uuid', $campaignUuids)
                ->delete();

            return BasicDialerCampaign::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('basic_dialer_campaign_uuid', $campaignUuids)
                ->delete();
        });
    }

    public function deleteContactLists(Collection $contactLists): int
    {
        return DB::transaction(function () use ($contactLists) {
            $contactListUuids = $contactLists->pluck('basic_dialer_contact_list_uuid');
            $contactUuids = BasicDialerContact::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('basic_dialer_contact_list_uuid', $contactListUuids)
                ->pluck('basic_dialer_contact_uuid');
            $recipientUuids = BasicDialerCampaignRecipient::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('basic_dialer_contact_uuid', $contactUuids)
                ->pluck('basic_dialer_campaign_recipient_uuid');

            BasicDialerCampaign::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('basic_dialer_contact_list_uuid', $contactListUuids)
                ->update(['basic_dialer_contact_list_uuid' => null]);

            BasicDialerCampaignAttempt::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('basic_dialer_campaign_recipient_uuid', $recipientUuids)
                ->delete();

            BasicDialerCampaignRecipient::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('basic_dialer_campaign_recipient_uuid', $recipientUuids)
                ->delete();

            BasicDialerContact::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('basic_dialer_contact_list_uuid', $contactListUuids)
                ->delete();

            return BasicDialerContactList::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereIn('basic_dialer_contact_list_uuid', $contactListUuids)
                ->delete();
        });
    }

    public function startCampaign(BasicDialerCampaign $campaign): BasicDialerCampaign
    {
        DB::transaction(function () use ($campaign) {
            $campaign->forceFill([
                'status' => 'running',
                'enabled' => true,
                'started_at' => $campaign->started_at ?: now(),
                'paused_at' => null,
                'stopped_at' => null,
                'completed_at' => null,
            ])->save();

            $this->syncCampaignRecipients($campaign);
        });

        return $campaign->refresh();
    }

    public function pauseCampaign(BasicDialerCampaign $campaign): BasicDialerCampaign
    {
        $campaign->forceFill([
            'status' => 'paused',
            'paused_at' => now(),
        ])->save();

        return $campaign->refresh();
    }

    public function stopCampaign(BasicDialerCampaign $campaign): BasicDialerCampaign
    {
        DB::transaction(function () use ($campaign) {
            $campaign->forceFill([
                'status' => 'stopped',
                'stopped_at' => now(),
            ])->save();

            BasicDialerCampaignRecipient::query()
                ->where('domain_uuid', $campaign->domain_uuid)
                ->where('basic_dialer_campaign_uuid', $campaign->basic_dialer_campaign_uuid)
                ->whereIn('status', ['pending', 'retry_wait'])
                ->update([
                    'status' => 'stopped',
                    'completed_at' => now(),
                    'last_outcome' => 'stopped',
                    'updated_at' => now(),
                ]);
        });

        return $campaign->refresh();
    }

    public function runCampaignCycle(string $campaignUuid, FreeswitchEslService $esl): ?int
    {
        $campaign = BasicDialerCampaign::query()
            ->whereKey($campaignUuid)
            ->first();

        if (! $campaign || $campaign->status !== 'running' || ! $campaign->enabled) {
            return null;
        }

        $this->reconcileCampaignAttempts($campaign, $esl);
        $this->runCampaign($campaign, $esl);

        $campaign->refresh();

        if ($campaign->status !== 'running' || ! $this->campaignHasOutstandingWork($campaign)) {
            return null;
        }

        return $this->nextCampaignDelaySeconds($campaign);
    }

    public function reconcileCampaignAttempts(BasicDialerCampaign $campaign, FreeswitchEslService $esl): void
    {
        BasicDialerCampaignAttempt::query()
            ->where('domain_uuid', $campaign->domain_uuid)
            ->where('basic_dialer_campaign_uuid', $campaign->basic_dialer_campaign_uuid)
            ->where('status', 'dialing')
            ->where('started_at', '<=', now()->subSeconds(30))
            ->with(['campaign', 'recipient'])
            ->limit(100)
            ->get()
            ->each(function (BasicDialerCampaignAttempt $attempt) use ($campaign, $esl) {
                $deadline = (clone $attempt->started_at)->addSeconds(((int) $campaign->originate_timeout) + 30);
                if ($deadline->isFuture()) {
                    return;
                }

                if ($this->callStillExists($esl, $attempt->call_uuid)) {
                    return;
                }

                $this->completeAttemptFromCdr($attempt);
            });

        $this->completeCampaignIfDone($campaign);
    }

    private function runCampaign(BasicDialerCampaign $campaign, FreeswitchEslService $esl): void
    {
        $campaign->refresh();

        if ($campaign->status !== 'running' || ! $campaign->enabled) {
            return;
        }

        if (blank($campaign->destination_target)) {
            $campaign->forceFill([
                'status' => 'paused',
                'paused_at' => now(),
            ])->save();

            return;
        }

        $activeCount = BasicDialerCampaignAttempt::query()
            ->where('domain_uuid', $campaign->domain_uuid)
            ->where('basic_dialer_campaign_uuid', $campaign->basic_dialer_campaign_uuid)
            ->whereIn('status', self::ACTIVE_ATTEMPT_STATUSES)
            ->count();

        $availableSlots = max(0, (int) $campaign->max_concurrent_calls - $activeCount);
        if ($availableSlots === 0) {
            return;
        }

        if ($campaign->last_run_at && $campaign->last_run_at->gt(now()->subSeconds((int) $campaign->seconds_between_calls))) {
            return;
        }

        $domain = Domain::query()
            ->where('domain_uuid', $campaign->domain_uuid)
            ->first(['domain_uuid', 'domain_name']);

        if (! $domain) {
            $campaign->forceFill([
                'status' => 'paused',
                'paused_at' => now(),
            ])->save();

            return;
        }

        BasicDialerCampaignRecipient::query()
            ->where('domain_uuid', $campaign->domain_uuid)
            ->where('basic_dialer_campaign_uuid', $campaign->basic_dialer_campaign_uuid)
            ->whereIn('status', self::DUE_RECIPIENT_STATUSES)
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('created_at')
            ->limit($availableSlots)
            ->get()
            ->each(function (BasicDialerCampaignRecipient $recipient) use ($campaign, $domain, $esl) {
                if ($campaign->last_run_at && $campaign->last_run_at->gt(now()->subSeconds((int) $campaign->seconds_between_calls))) {
                    return false;
                }

                $this->originateRecipient($campaign, $recipient, $domain->domain_name, $esl);
            });

        $this->completeCampaignIfDone($campaign);
    }

    private function originateRecipient(
        BasicDialerCampaign $campaign,
        BasicDialerCampaignRecipient $recipient,
        string $domainName,
        FreeswitchEslService $esl
    ): void {
        $claim = BasicDialerCampaignRecipient::query()
            ->whereKey($recipient->basic_dialer_campaign_recipient_uuid)
            ->whereIn('status', self::DUE_RECIPIENT_STATUSES)
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->update([
                'status' => 'dialing',
                'attempts_count' => DB::raw('attempts_count + 1'),
                'last_attempt_at' => now(),
                'next_attempt_at' => null,
                'updated_at' => now(),
            ]);

        if ($claim === 0) {
            return;
        }

        $recipient->refresh();

        $attempt = BasicDialerCampaignAttempt::query()->create([
            'domain_uuid' => $campaign->domain_uuid,
            'basic_dialer_campaign_uuid' => $campaign->basic_dialer_campaign_uuid,
            'basic_dialer_campaign_recipient_uuid' => $recipient->basic_dialer_campaign_recipient_uuid,
            'call_uuid' => (string) Str::uuid(),
            'attempt_number' => $recipient->attempts_count,
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        if (blank($campaign->caller_id_number)) {
            $attempt->forceFill([
                'status' => 'failed',
                'outcome' => 'missing_caller_id',
                'response' => 'Campaign caller ID number is required.',
                'ended_at' => now(),
            ])->save();

            $recipient->forceFill([
                'status' => 'failed',
                'last_attempt_at' => now(),
                'last_error' => 'Campaign caller ID number is required.',
                'completed_at' => now(),
            ])->save();

            return;
        }

        $command = $this->buildOriginateCommand($campaign, $recipient, $attempt, $domainName);

        if ($command === null) {
            $attempt->forceFill([
                'status' => 'failed',
                'outcome' => 'no_outbound_route',
                'response' => 'No outbound route matched recipient.',
                'ended_at' => now(),
            ])->save();

            $this->scheduleRecipientRetry($campaign, $recipient, 'no_outbound_route', 'No outbound route matched recipient.');
            return;
        }

        try {
            if (! $esl->isConnected()) {
                $esl->reconnect();
            }

            $response = (string) $esl->executeCommand($command, false);
            $accepted = stripos($response, '+OK') !== false;

            $attempt->forceFill([
                'command' => $command,
                'response' => $response,
                'status' => $accepted ? 'dialing' : 'rejected',
                'started_at' => $accepted ? now() : null,
                'ended_at' => $accepted ? null : now(),
                'outcome' => $accepted ? null : 'originate_rejected',
            ])->save();

            $campaign->forceFill(['last_run_at' => now()])->save();

            if (! $accepted) {
                $this->scheduleRecipientRetry($campaign, $recipient, 'originate_rejected', $response);
            }
        } catch (Throwable $e) {
            $attempt->forceFill([
                'command' => $command,
                'response' => 'ESL error: ' . $e->getMessage(),
                'status' => 'failed',
                'outcome' => 'esl_error',
                'ended_at' => now(),
            ])->save();

            $this->scheduleRecipientRetry($campaign, $recipient, 'esl_error', $e->getMessage());
        }
    }

    private function buildOriginateCommand(
        BasicDialerCampaign $campaign,
        BasicDialerCampaignRecipient $recipient,
        BasicDialerCampaignAttempt $attempt,
        string $domainName
    ): ?string {
        $callerIdName = $campaign->caller_id_name ?: 'Basic Dialer';
        $callerIdNumber = $campaign->caller_id_number;
        $channelVariables = [
            'outbound_caller_id_name' => $callerIdName,
            'outbound_caller_id_number' => $callerIdNumber,
            'effective_caller_id_name' => $callerIdName,
            'effective_caller_id_number' => $callerIdNumber,
            'origination_caller_id_name' => $callerIdName,
            'origination_caller_id_number' => $callerIdNumber,
        ];

        $routeResult = outbound_route_to_bridge(
            $campaign->domain_uuid,
            $recipient->phone_number,
            $channelVariables,
            true,
            null,
            false
        );
        $routes = $routeResult['bridges'] ?? [];

        if ($routes === []) {
            return null;
        }

        $recipientEndpoint = $routes[0];
        $application = $this->answeredApplication($campaign, $domainName);
        $vars = [
            'origination_uuid' => $attempt->call_uuid,
            'basic_dialer_campaign_uuid' => $campaign->basic_dialer_campaign_uuid,
            'basic_dialer_campaign_recipient_uuid' => $recipient->basic_dialer_campaign_recipient_uuid,
            'basic_dialer_campaign_attempt_uuid' => $attempt->basic_dialer_campaign_attempt_uuid,
            'domain_uuid' => $campaign->domain_uuid,
            'domain_name' => $domainName,
            'call_direction' => 'outbound',
            'originate_timeout' => (string) $campaign->originate_timeout,
            'outbound_caller_id_name' => $callerIdName,
            'outbound_caller_id_number' => $callerIdNumber,
            'origination_caller_id_name' => $callerIdName,
            'origination_caller_id_number' => $callerIdNumber,
            'effective_caller_id_name' => $callerIdName,
            'effective_caller_id_number' => $callerIdNumber,
            'caller_destination' => $recipient->phone_number,
            'ignore_early_media' => 'true',
            'hangup_after_bridge' => 'true',
            'continue_on_fail' => 'true',
        ];

        return sprintf('bgapi originate {%s}%s %s', $this->encodeChannelVariables($vars), $recipientEndpoint, $application);
    }

    private function answeredApplication(BasicDialerCampaign $campaign, string $domainName): string
    {
        if ($campaign->destination_type === 'bridges') {
            return sprintf('&bridge(%s)', $campaign->destination_target);
        }

        if ($campaign->destination_type === 'hangup') {
            return '&hangup()';
        }

        return sprintf('&transfer(%s XML %s)', $campaign->destination_target, $domainName);
    }

    private function defaultDestinationTarget(?string $destinationType, ?string $target): ?string
    {
        if (filled($target)) {
            return $target;
        }

        return match ($destinationType) {
            'check_voicemail' => '*98',
            'company_directory' => '*411',
            'hangup' => 'hangup',
            default => null,
        };
    }

    private function defaultDestinationLabel(?string $destinationType): ?string
    {
        return match ($destinationType) {
            'check_voicemail' => 'Check Voicemail',
            'company_directory' => 'Company Directory',
            'hangup' => 'Hang up',
            default => null,
        };
    }

    private function completeAttemptFromCdr(BasicDialerCampaignAttempt $attempt): void
    {
        $cdr = CDR::query()
            ->where('domain_uuid', $attempt->domain_uuid)
            ->where('xml_cdr_uuid', $attempt->call_uuid)
            ->first([
                'xml_cdr_uuid',
                'duration',
                'billsec',
                'answer_epoch',
                'hangup_cause',
            ]);

        $answered = $cdr && ((int) $cdr->billsec > 0 || (int) $cdr->answer_epoch > 0);
        $outcome = $answered ? 'answered' : ($cdr?->hangup_cause ?: 'no_answer');
        $status = $answered ? 'completed' : 'failed';

        $attempt->forceFill([
            'xml_cdr_uuid' => $cdr?->xml_cdr_uuid,
            'status' => $status,
            'outcome' => $outcome,
            'hangup_cause' => $cdr?->hangup_cause,
            'duration' => $cdr?->duration,
            'answered_at' => $answered ? now() : null,
            'ended_at' => now(),
        ])->save();

        $recipient = $attempt->recipient;
        $campaign = $attempt->campaign;

        if (! $recipient || ! $campaign) {
            return;
        }

        if ($answered) {
            $recipient->forceFill([
                'status' => 'answered',
                'completed_at' => now(),
                'last_outcome' => $outcome,
                'last_error' => null,
            ])->save();

            return;
        }

        $this->scheduleRecipientRetry($campaign, $recipient, $outcome, null);
    }

    private function scheduleRecipientRetry(
        BasicDialerCampaign $campaign,
        BasicDialerCampaignRecipient $recipient,
        string $outcome,
        ?string $error
    ): void {
        $hasRetry = (int) $recipient->attempts_count <= (int) $campaign->retry_limit;

        $recipient->forceFill([
            'status' => $hasRetry ? 'retry_wait' : 'failed',
            'next_attempt_at' => $hasRetry ? now()->addMinutes((int) $campaign->retry_delay_minutes) : null,
            'completed_at' => $hasRetry ? null : now(),
            'last_outcome' => $outcome,
            'last_error' => $error,
        ])->save();
    }

    private function completeCampaignIfDone(BasicDialerCampaign $campaign): void
    {
        $campaign->refresh();

        if ($campaign->status !== 'running') {
            return;
        }

        $remaining = BasicDialerCampaignRecipient::query()
            ->where('domain_uuid', $campaign->domain_uuid)
            ->where('basic_dialer_campaign_uuid', $campaign->basic_dialer_campaign_uuid)
            ->whereNotIn('status', self::TERMINAL_RECIPIENT_STATUSES)
            ->exists();

        if (! $remaining) {
            $campaign->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();
        }
    }

    private function campaignHasOutstandingWork(BasicDialerCampaign $campaign): bool
    {
        return BasicDialerCampaignRecipient::query()
            ->where('domain_uuid', $campaign->domain_uuid)
            ->where('basic_dialer_campaign_uuid', $campaign->basic_dialer_campaign_uuid)
            ->whereNotIn('status', self::TERMINAL_RECIPIENT_STATUSES)
            ->exists();
    }

    private function nextCampaignDelaySeconds(BasicDialerCampaign $campaign): int
    {
        $activeAttempts = BasicDialerCampaignAttempt::query()
            ->where('domain_uuid', $campaign->domain_uuid)
            ->where('basic_dialer_campaign_uuid', $campaign->basic_dialer_campaign_uuid)
            ->whereIn('status', self::ACTIVE_ATTEMPT_STATUSES)
            ->count();
        $availableSlots = max(0, (int) $campaign->max_concurrent_calls - $activeAttempts);
        $dueRecipientsExist = BasicDialerCampaignRecipient::query()
            ->where('domain_uuid', $campaign->domain_uuid)
            ->where('basic_dialer_campaign_uuid', $campaign->basic_dialer_campaign_uuid)
            ->whereIn('status', self::DUE_RECIPIENT_STATUSES)
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->exists();

        if ($availableSlots > 0 && $dueRecipientsExist) {
            if (! $campaign->last_run_at) {
                return 1;
            }

            $nextPacingSlot = (clone $campaign->last_run_at)->addSeconds((int) $campaign->seconds_between_calls);

            return max(1, now()->diffInSeconds($nextPacingSlot, false));
        }

        if ($activeAttempts > 0) {
            return min(120, max(10, ((int) $campaign->originate_timeout) + 30));
        }

        $nextRecipientAt = BasicDialerCampaignRecipient::query()
            ->where('domain_uuid', $campaign->domain_uuid)
            ->where('basic_dialer_campaign_uuid', $campaign->basic_dialer_campaign_uuid)
            ->whereIn('status', self::DUE_RECIPIENT_STATUSES)
            ->min('next_attempt_at');

        if ($nextRecipientAt) {
            return max(1, now()->diffInSeconds(Carbon::parse($nextRecipientAt), false));
        }

        return max(1, (int) $campaign->seconds_between_calls);
    }

    private function callStillExists(FreeswitchEslService $esl, ?string $callUuid): bool
    {
        if (blank($callUuid)) {
            return false;
        }

        try {
            if (! $esl->isConnected()) {
                $esl->reconnect();
            }

            $reply = strtolower(trim((string) $esl->executeCommand('uuid_exists ' . $callUuid, false)));

            return str_contains($reply, 'true') || $reply === '1';
        } catch (Throwable) {
            return true;
        }
    }

    private function importContacts(BasicDialerContactList $contactList, string $rawContacts): void
    {
        collect(preg_split('/\r\n|\r|\n/', $rawContacts))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->map(fn ($line) => $this->parseContactImportRow($line))
            ->each(function (array $row) use ($contactList) {
                $phoneNumber = $this->normalizePhoneNumber($row[0] ?? null);

                if (blank($phoneNumber)) {
                    return;
                }

                $contact = BasicDialerContact::query()->firstOrNew([
                    'domain_uuid' => session('domain_uuid'),
                    'basic_dialer_contact_list_uuid' => $contactList->basic_dialer_contact_list_uuid,
                    'phone_number' => $phoneNumber,
                ]);

                $contact->forceFill([
                    'basic_dialer_contact_uuid' => $contact->basic_dialer_contact_uuid ?: (string) Str::uuid(),
                    'contact_name' => $this->blankToNull($row[1] ?? null),
                    'company' => $this->blankToNull($row[2] ?? null),
                    'enabled' => true,
                ])->save();
            });
    }

    private function syncCampaignRecipients(BasicDialerCampaign $campaign): void
    {
        if (blank($campaign->basic_dialer_contact_list_uuid)) {
            BasicDialerCampaignRecipient::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->where('basic_dialer_campaign_uuid', $campaign->basic_dialer_campaign_uuid)
                ->where('status', 'pending')
                ->delete();

            return;
        }

        $contacts = BasicDialerContact::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('basic_dialer_contact_list_uuid', $campaign->basic_dialer_contact_list_uuid)
            ->where('enabled', true)
            ->get();

        BasicDialerCampaignRecipient::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('basic_dialer_campaign_uuid', $campaign->basic_dialer_campaign_uuid)
            ->where('status', 'pending')
            ->whereNotIn('basic_dialer_contact_uuid', $contacts->pluck('basic_dialer_contact_uuid'))
            ->delete();

        $contacts->each(function (BasicDialerContact $contact) use ($campaign) {
            BasicDialerCampaignRecipient::query()->firstOrCreate(
                [
                    'domain_uuid' => session('domain_uuid'),
                    'basic_dialer_campaign_uuid' => $campaign->basic_dialer_campaign_uuid,
                    'basic_dialer_contact_uuid' => $contact->basic_dialer_contact_uuid,
                ],
                [
                    'basic_dialer_campaign_recipient_uuid' => (string) Str::uuid(),
                        'phone_number' => $contact->phone_number,
                        'contact_name' => $contact->contact_name,
                        'status' => 'pending',
                        'attempts_count' => 0,
                    ]
                );
        });
    }

    private function destinationSelection(mixed $selection): array
    {
        if (is_array($selection)) {
            $value = $selection['extension'] ?? $selection['value'] ?? $selection['option'] ?? null;
            $label = $selection['name'] ?? $selection['label'] ?? $selection['extension'] ?? $value;

            return [
                'value' => $this->blankToNull($value),
                'label' => $this->blankToNull($label),
            ];
        }

        return [
            'value' => $this->blankToNull($selection),
            'label' => $this->blankToNull($selection),
        ];
    }

    private function parseContactImportRow(string $line): array
    {
        if (str_contains($line, "\t")) {
            return str_getcsv($line, "\t");
        }

        if (str_contains($line, ';') && ! str_contains($line, ',')) {
            return str_getcsv($line, ';');
        }

        return str_getcsv($line);
    }

    private function normalizePhoneNumber(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $prefix = str_starts_with($value, '+') ? '+' : '';
        $digits = preg_replace('/\D+/', '', $value);

        return $digits ? $prefix . $digits : null;
    }

    private function blankToNull(mixed $value): mixed
    {
        return blank($value) ? null : $value;
    }

    private function encodeChannelVariables(array $vars): string
    {
        return collect($vars)
            ->map(fn ($value, $key) => $key . "='" . $this->escapeChannelValue($value) . "'")
            ->implode(',');
    }

    private function escapeChannelValue(mixed $value): string
    {
        return str_replace(["\\", "'", ",", "{", "}"], ["\\\\", "\\'", "\\,", '', ''], (string) $value);
    }

    private function escapeEndpoint(string $value): string
    {
        return str_replace([' ', "'", '"', '{', '}'], '', $value);
    }
}
