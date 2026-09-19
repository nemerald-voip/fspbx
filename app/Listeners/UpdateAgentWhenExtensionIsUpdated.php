<?php

namespace App\Listeners;

use App\Events\ExtensionUpdated;
use App\Jobs\RefreshCallCenterAgent;
use App\Models\CallCenterAgents;
use App\Models\Domain;
use Illuminate\Support\Facades\DB;

class UpdateAgentWhenExtensionIsUpdated
{
    public function handle(ExtensionUpdated $event): void
    {
        $old = $event->originalAttributes;
        $new = $event->extension;
        $domainUuid = $old['domain_uuid'] ?? null;
        if (! $domainUuid || $domainUuid !== ($new['domain_uuid'] ?? null)
            || empty($old['extension']) || empty($new['extension'])) {
            return;
        }
        if (! collect(['extension', 'number_alias', 'effective_caller_id_name'])
            ->contains(fn ($field) => ($old[$field] ?? null) !== ($new[$field] ?? null))) {
            return;
        }
        $domain = Domain::whereKey($domainUuid)->value('domain_name');
        if (! $domain) {
            return;
        }

        // Contact owns the extension relationship; Agent ID is editable.
        $contacts = ['user/'.$old['extension'].'@'.$domain => 'user/'.$new['extension'].'@'.$domain];
        if (! empty($old['number_alias']) && $old['number_alias'] !== $old['extension']) {
            $contacts['user/'.$old['number_alias'].'@'.$domain] = 'user/'.(($new['number_alias'] ?? null) ?: $new['extension']).'@'.$domain;
        }

        DB::transaction(function () use ($domainUuid, $contacts, $new) {
            $agents = CallCenterAgents::where('domain_uuid', $domainUuid)
                ->whereIn('agent_contact', array_keys($contacts))
                ->orderBy('call_center_agent_uuid')->lockForUpdate()->get();
            foreach ($agents as $agent) {
                $agent->agent_name = $new['effective_caller_id_name'] ?? null;
                $agent->agent_contact = $contacts[$agent->agent_contact];
                // Retain the existing extension-driven Agent ID/password behavior.
                $agent->agent_id = $new['extension'];
                $agent->agent_password = $new['extension'];
                $agent->save();
            }
            if ($agents->isEmpty()) {
                return;
            }
            $agentUuids = $agents->modelKeys();
            DB::afterCommit(function () use ($agentUuids, $domainUuid) {
                // Enabled HA observers already dispatch durable local and peer work.
                if (class_exists(\Modules\ContactCenter\Services\Ha\HaSettings::class)
                    && app(\Modules\ContactCenter\Services\Ha\HaSettings::class)->enabled()) {
                    return;
                }
                foreach ($agentUuids as $agentUuid) {
                    // Queue stable identities so a failed runtime refresh can retry
                    // even though the old extension contact no longer exists.
                    RefreshCallCenterAgent::dispatch($agentUuid, $domainUuid);
                }
            });
        });
    }
}
