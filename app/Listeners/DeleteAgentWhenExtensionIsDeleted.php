<?php

namespace App\Listeners;

use App\Models\CallCenterAgents;
use App\Models\Domain;
use App\Jobs\DeleteCallCenterAgent;
use Illuminate\Support\Facades\DB;

class DeleteAgentWhenExtensionIsDeleted
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        $attributes = $event->originalAttributes;
        $domainUuid = $attributes['domain_uuid'] ?? null;
        $domainName = $domainUuid ? Domain::where('domain_uuid', $domainUuid)->value('domain_name') : null;
        if (! $domainName) {
            return;
        }

        $contacts = collect([$attributes['extension'] ?? null, $attributes['number_alias'] ?? null])
            ->filter(fn ($number) => $number !== null && $number !== '')
            ->unique()->map(fn ($number) => 'user/'.$number.'@'.$domainName)->all();

        // Contact owns the extension relationship; an editable Agent ID does not.
        foreach (CallCenterAgents::where('domain_uuid', $domainUuid)->whereIn('agent_contact', $contacts)->get() as $agent) {
            $job = new DeleteCallCenterAgent($agent);
            // Explicitly defer dispatch; Laravel 10's sync driver ignores job afterCommit.
            DB::afterCommit(fn () => dispatch($job));
        }
    }
}
