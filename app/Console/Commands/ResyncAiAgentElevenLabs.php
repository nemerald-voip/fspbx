<?php

namespace App\Console\Commands;

use App\Models\AiAgent;
use App\Models\Domain;
use App\Models\FusionCache;
use App\Services\ElevenLabsConvaiService;
use Illuminate\Console\Command;

class ResyncAiAgentElevenLabs extends Command
{
    protected $signature = 'ai-agents:resync-elevenlabs {--uuid= : Only resync this specific AI agent uuid}';

    protected $description = 'Backfill ElevenLabs SIP trunk phone numbers for existing AI agents (delete + recreate with inbound_trunk_config + reassign agent + invalidate dialplan cache).';

    public function handle(ElevenLabsConvaiService $convai): int
    {
        $allowedAddresses = config('services.elevenlabs.sip_allowed_addresses', []);
        if (empty($allowedAddresses)) {
            $this->error('config services.elevenlabs.sip_allowed_addresses is empty. Set ELEVENLABS_SIP_ALLOWED_ADDRESSES in .env first.');
            return self::FAILURE;
        }

        $query = AiAgent::query();
        if ($uuid = $this->option('uuid')) {
            $query->where('ai_agent_uuid', $uuid);
        }

        $agents = $query->get();
        if ($agents->isEmpty()) {
            $this->warn('No AI agents found.');
            return self::SUCCESS;
        }

        $this->line("Resyncing {$agents->count()} agent(s) with allowlist: " . implode(', ', $allowedAddresses));

        $failures = 0;
        foreach ($agents as $agent) {
            $this->line("→ {$agent->agent_extension} {$agent->agent_name} ({$agent->ai_agent_uuid})");

            if (!$agent->elevenlabs_agent_id) {
                $this->warn('  skip: no elevenlabs_agent_id');
                continue;
            }

            try {
                $created = $convai->replaceSipTrunkPhoneNumber(
                    $agent->elevenlabs_phone_number_id,
                    'Voxra Agent: ' . $agent->agent_name,
                    (string) $agent->agent_extension,
                    $allowedAddresses,
                    $agent->elevenlabs_agent_id
                );

                $newId = $created['phone_number_id'] ?? null;
                if (!$newId) {
                    throw new \RuntimeException('No phone_number_id returned from ElevenLabs');
                }

                $agent->elevenlabs_phone_number_id = $newId;
                $agent->update_date = date('Y-m-d H:i:s');
                $agent->save();

                // Invalidate the dialplan cache for this agent's domain so the
                // bridge action picks up the regenerated XML on the next call.
                $domain = Domain::where('domain_uuid', $agent->domain_uuid)->first();
                if ($domain) {
                    FusionCache::clear('dialplan.' . $domain->domain_name);
                }

                $this->info("  ok: phone_number_id={$newId}");
            } catch (\Throwable $e) {
                $failures++;
                $this->error('  fail: ' . $e->getMessage());
            }
        }

        if ($failures > 0) {
            $this->error("{$failures} agent(s) failed.");
            return self::FAILURE;
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
