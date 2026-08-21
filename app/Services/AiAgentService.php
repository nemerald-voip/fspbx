<?php

namespace App\Services;

use App\Contracts\AiProviderClient;
use App\Exceptions\AiProviderException;
use App\Jobs\QueueAiProviderToolSyncs;
use App\Models\AiAgent;
use App\Models\DialplanDetails;
use App\Models\Dialplans;
use App\Models\Domain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class AiAgentService
{
    private const APP_UUID = '7bc57f0c-00a2-4f72-9f41-7ebebc1c318c';

    public function __construct(private readonly AiProviderRegistry $providers)
    {
    }

    public function create(array $validated): AiAgent
    {
        $agent = new AiAgent();
        $agent->forceFill($this->attributes($validated) + [
            'ai_agent_uuid' => (string) Str::uuid(),
            'dialplan_uuid' => (string) Str::uuid(),
            'provider' => $validated['provider'],
            'provisioning_status' => 'provisioning',
        ])->save();

        try {
            $this->writeDialplan($agent);
            $providerPhoneNumber = $this->provider($agent)->provision($agent);
            $agent->forceFill([
                'provider_phone_number' => $providerPhoneNumber,
                'provisioning_status' => 'synced',
                'provisioning_error' => null,
                'last_synced_at' => now(),
            ])->save();
            $this->writeDialplan($agent);
        } catch (Throwable $exception) {
            $this->markFailed($agent, $exception);
            throw $exception;
        }

        $this->queueToolSync($agent);

        return $agent->refresh()->load('domain');
    }

    public function update(AiAgent $agent, array $validated): AiAgent
    {
        if ($validated['provider'] !== $agent->provider) {
            throw new \InvalidArgumentException('An AI agent provider cannot be changed after provisioning.');
        }

        $agent->forceFill($this->attributes($validated));

        try {
            $this->provider($agent)->synchronize($agent);
            $agent->forceFill([
                'provisioning_status' => 'synced',
                'provisioning_error' => null,
                'last_synced_at' => now(),
            ])->save();
            $this->writeDialplan($agent);
        } catch (Throwable $exception) {
            $agent->refresh();
            $this->markFailed($agent, $exception);
            throw $exception;
        }

        $this->queueToolSync($agent);

        return $agent->refresh()->load('domain');
    }

    public function retry(AiAgent $agent): AiAgent
    {
        try {
            if ($agent->provider_phone_number) {
                $this->provider($agent)->synchronize($agent);
            } else {
                try {
                    $agent->provider_phone_number = $this->provider($agent)->provision($agent);
                } catch (AiProviderException $exception) {
                    if (! $exception->isConflict()) {
                        throw $exception;
                    }

                    $agent->provider_phone_number = $agent->ai_agent_uuid;
                    $this->provider($agent)->synchronize($agent);
                }
            }

            $agent->forceFill([
                'provisioning_status' => 'synced',
                'provisioning_error' => null,
                'last_synced_at' => now(),
            ])->save();
            $this->writeDialplan($agent);
        } catch (Throwable $exception) {
            $this->markFailed($agent, $exception);
            throw $exception;
        }

        $this->queueToolSync($agent);

        return $agent->refresh()->load('domain');
    }

    public function refresh(AiAgent $agent): AiAgent
    {
        try {
            $providerPhoneNumber = $this->provider($agent)->refresh($agent);
            $agent->forceFill([
                'provider_phone_number' => $providerPhoneNumber,
                'provisioning_status' => 'synced',
                'provisioning_error' => null,
                'last_synced_at' => now(),
            ])->save();
            $this->writeDialplan($agent);
        } catch (Throwable $exception) {
            $this->markFailed($agent, $exception);
            throw $exception;
        }

        return $agent->refresh()->load('domain');
    }

    public function toggle(AiAgent $agent): AiAgent
    {
        $enabled = ! $agent->enabled;

        try {
            $this->provider($agent)->synchronize($agent, $enabled);
            $agent->forceFill([
                'enabled' => $enabled,
                'provisioning_status' => 'synced',
                'provisioning_error' => null,
                'last_synced_at' => now(),
            ])->save();
            $this->writeDialplan($agent);
        } catch (Throwable $exception) {
            $this->markFailed($agent, $exception);
            throw $exception;
        }

        return $agent->refresh()->load('domain');
    }

    public function delete(AiAgent $agent): void
    {
        try {
            $this->provider($agent)->delete($agent);
        } catch (AiProviderException $exception) {
            if (! $exception->isNotFound()) {
                throw $exception;
            }
        }

        $domainName = $this->domainName($agent->domain_uuid);

        DB::transaction(function () use ($agent) {
            if ($agent->dialplan_uuid) {
                DialplanDetails::query()->where('dialplan_uuid', $agent->dialplan_uuid)->delete();
                Dialplans::query()->where('dialplan_uuid', $agent->dialplan_uuid)->delete();
            }

            $agent->delete();
        });

        app(DialplanService::class)->clearDialplanCache($domainName);
    }

    private function attributes(array $validated): array
    {
        return [
            'domain_uuid' => $validated['domain_uuid'],
            'provider' => $validated['provider'],
            'name' => trim($validated['name']),
            'extension' => trim($validated['extension']),
            'inbound_agent_id' => trim($validated['inbound_agent_id']),
            'inbound_agent_name' => $this->nullable($validated['inbound_agent_name'] ?? null),
            'outbound_agent_id' => $this->nullable($validated['outbound_agent_id'] ?? null),
            'outbound_agent_name' => $this->nullable($validated['outbound_agent_name'] ?? null),
            'recording_policy' => $validated['recording_policy'],
            'enabled' => (bool) $validated['enabled'],
            'description' => $this->nullable($validated['description'] ?? null),
        ];
    }

    private function provider(AiAgent $agent): AiProviderClient
    {
        return $this->providers->client($agent->provider);
    }

    private function markFailed(AiAgent $agent, Throwable $exception): void
    {
        $agent->forceFill([
            'provisioning_status' => 'failed',
            'provisioning_error' => Str::limit($exception->getMessage(), 2000),
        ])->save();

        try {
            $this->writeDialplan($agent);
        } catch (Throwable $dialplanException) {
            report($dialplanException);
        }
    }

    private function writeDialplan(AiAgent $agent): void
    {
        $domainName = $this->domainName($agent->domain_uuid);
        $dialplan = Dialplans::query()->find($agent->dialplan_uuid) ?? new Dialplans();
        $isNew = ! $dialplan->exists;
        $enabled = $agent->isRoutable();

        $dialplan->forceFill([
            'domain_uuid' => $agent->domain_uuid,
            'dialplan_uuid' => $agent->dialplan_uuid,
            'app_uuid' => self::APP_UUID,
            'dialplan_name' => 'AI Agent: ' . $agent->name,
            'dialplan_number' => $agent->extension,
            'dialplan_context' => $domainName,
            'dialplan_continue' => 'false',
            'dialplan_xml' => $this->dialplanXml($agent),
            'dialplan_order' => 333,
            'dialplan_enabled' => $enabled,
            'dialplan_description' => $agent->description,
            $isNew ? 'insert_date' : 'update_date' => now(),
            $isNew ? 'insert_user' : 'update_user' => session('user_uuid'),
        ])->save();

        app(DialplanService::class)->clearDialplanCache($domainName);
    }

    private function dialplanXml(AiAgent $agent): string
    {
        $name = htmlspecialchars('AI Agent: ' . $agent->name, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $uuid = htmlspecialchars($agent->ai_agent_uuid, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $dialplanUuid = htmlspecialchars($agent->dialplan_uuid, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $extension = preg_quote($agent->extension, '/');

        return <<<XML
<extension name="{$name}" continue="false" uuid="{$dialplanUuid}">
	<condition field="destination_number" expression="^{$extension}$">
		<action application="lua" data="ai_agent.lua {$uuid}"/>
	</condition>
</extension>
XML;
    }

    private function domainName(string $domainUuid): string
    {
        $name = Domain::query()->whereKey($domainUuid)->value('domain_name');

        if (! $name) {
            throw new \RuntimeException('The selected account was not found.');
        }

        return $name;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function queueToolSync(AiAgent $agent): void
    {
        try {
            QueueAiProviderToolSyncs::dispatch(
                false,
                'ai-agent-save',
                $agent->inbound_agent_id,
            )->afterCommit();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
