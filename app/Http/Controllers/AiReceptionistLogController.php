<?php

namespace App\Http\Controllers;

use App\Models\AiReceptionistSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AiReceptionistLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! userCheckPermission('logs_list_view')) {
            abort(403);
        }

        $domainUuids = $this->requestedDomainUuids($request);
        $timezone = get_local_time_zone(session('domain_uuid'));
        $dateRange = $request->input('filter.dateRange', []);
        $search = trim((string) $request->input('filter.search', ''));
        $status = (string) $request->input('filter.status', 'all');

        $query = AiReceptionistSession::query()
            ->with([
                'domain:domain_uuid,domain_name,domain_description',
                'receptionist:ai_receptionist_uuid,name,extension',
                'toolRuns' => fn ($query) => $query->orderBy('started_at')->orderBy('created_at'),
                'warmTransfers' => fn ($query) => $query->orderBy('started_at')->orderBy('created_at'),
            ])
            ->whereIn('domain_uuid', $domainUuids)
            ->when($status !== '' && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->when(is_array($dateRange) && count($dateRange) >= 2, function ($query) use ($dateRange) {
                $query->whereBetween('started_at', [
                    Carbon::parse($dateRange[0])->utc(),
                    Carbon::parse($dateRange[1])->utc(),
                ]);
            })
            ->when($search !== '', function ($query) use ($search) {
                $needle = '%' . $search . '%';
                $query->where(function ($query) use ($needle) {
                    $query->where('session_uuid', 'ilike', $needle)
                        ->orWhere('freeswitch_uuid', 'ilike', $needle)
                        ->orWhere('openai_call_id', 'ilike', $needle)
                        ->orWhere('sip_call_id', 'ilike', $needle)
                        ->orWhere('caller_id_name', 'ilike', $needle)
                        ->orWhere('caller_id_number', 'ilike', $needle)
                        ->orWhere('destination_number', 'ilike', $needle)
                        ->orWhere('transfer_label', 'ilike', $needle)
                        ->orWhere('transfer_target', 'ilike', $needle)
                        ->orWhereHas('receptionist', fn ($query) => $query->where('name', 'ilike', $needle));
                });
            })
            ->orderByDesc('started_at')
            ->orderByDesc('created_at');

        $logs = $query->paginate((int) $request->input('per_page', 25))->withQueryString();

        $logs->getCollection()->transform(fn (AiReceptionistSession $session) => $this->sessionPayload($session, $timezone));

        return response()->json($logs);
    }

    private function sessionPayload(AiReceptionistSession $session, string $timezone): array
    {
        $startedAt = $session->started_at ?: $session->created_at;
        $endedAt = $session->ended_at;
        $warmTransfers = $session->warmTransfers;
        $toolRuns = $session->toolRuns;

        return [
            'session_uuid' => $session->session_uuid,
            'domain_uuid' => $session->domain_uuid,
            'freeswitch_uuid' => $session->freeswitch_uuid,
            'openai_call_id' => $session->openai_call_id,
            'sip_call_id' => $session->sip_call_id,
            'domain' => $session->domain ? [
                'domain_name' => $session->domain->domain_name,
                'domain_description' => $session->domain->domain_description,
            ] : null,
            'receptionist' => $session->receptionist ? [
                'name' => $session->receptionist->name,
                'extension' => $session->receptionist->extension,
            ] : null,
            'started_at' => optional($startedAt)->toISOString(),
            'started_at_formatted' => $startedAt
                ? $startedAt->copy()->timezone($timezone)->format('Y-m-d H:i:s')
                : null,
            'duration' => $startedAt ? $this->durationText($startedAt, $endedAt ?: now()) : null,
            'status' => $session->status,
            'engine' => $session->engine,
            'caller_id_name' => $session->caller_id_name,
            'caller_id_number' => $session->caller_id_number,
            'destination_number' => $session->destination_number,
            'transfer_type' => $session->transfer_type,
            'transfer_target' => $session->transfer_target,
            'transfer_label' => $session->transfer_label,
            'error_message' => $session->error_message,
            'transcript' => $session->transcript,
            'tool_runs_count' => $toolRuns->count(),
            'failed_tool_runs_count' => $toolRuns->where('status', 'failed')->count(),
            'warm_transfers_count' => $warmTransfers->count(),
            'latest_warm_transfer_status' => optional($warmTransfers->last())->status,
            'tool_runs' => $toolRuns->map(fn ($toolRun) => [
                'tool_run_uuid' => $toolRun->tool_run_uuid,
                'tool_name' => $toolRun->tool_name,
                'status' => $toolRun->status,
                'started_at_formatted' => $toolRun->started_at
                    ? $toolRun->started_at->copy()->timezone($timezone)->format('Y-m-d H:i:s')
                    : null,
                'duration' => $toolRun->started_at ? $this->durationText($toolRun->started_at, $toolRun->ended_at ?: now()) : null,
                'request_payload' => $toolRun->request_payload,
                'response_payload' => $toolRun->response_payload,
                'error_message' => $toolRun->error_message,
            ])->values()->all(),
            'warm_transfers' => $warmTransfers->map(fn ($transfer) => [
                'warm_transfer_uuid' => $transfer->warm_transfer_uuid,
                'status' => $transfer->status,
                'destination_label' => $transfer->destination_label,
                'destination_type' => $transfer->destination_type,
                'destination_target' => $transfer->destination_target,
                'handoff_summary' => $transfer->handoff_summary,
                'caller_uuid' => $transfer->caller_uuid,
                'openai_uuid' => $transfer->openai_uuid,
                'recipient_uuid' => $transfer->recipient_uuid,
                'started_at_formatted' => $transfer->started_at
                    ? $transfer->started_at->copy()->timezone($timezone)->format('Y-m-d H:i:s')
                    : null,
                'answered_at_formatted' => $transfer->answered_at
                    ? $transfer->answered_at->copy()->timezone($timezone)->format('Y-m-d H:i:s')
                    : null,
                'completed_at_formatted' => $transfer->completed_at
                    ? $transfer->completed_at->copy()->timezone($timezone)->format('Y-m-d H:i:s')
                    : null,
                'cancelled_at_formatted' => $transfer->cancelled_at
                    ? $transfer->cancelled_at->copy()->timezone($timezone)->format('Y-m-d H:i:s')
                    : null,
                'metadata' => $transfer->metadata,
                'error_message' => $transfer->error_message,
            ])->values()->all(),
        ];
    }

    private function requestedDomainUuids(Request $request): array
    {
        $accessible = $this->accessibleDomains()->pluck('domain_uuid')->filter()->values();
        $requested = (string) $request->input('filter.domain_uuid', session('domain_uuid'));

        if ($requested === 'all') {
            return $accessible->all();
        }

        if ($accessible->contains($requested)) {
            return [$requested];
        }

        return array_filter([(string) session('domain_uuid')]);
    }

    private function accessibleDomains(): Collection
    {
        $domains = collect(session('domains') ?: []);

        if ($domains->isEmpty() && session('domain_uuid')) {
            $domains = collect([
                ['domain_uuid' => session('domain_uuid')],
            ]);
        }

        return $domains;
    }

    private function durationText(Carbon $start, Carbon $end): string
    {
        $seconds = max(0, $start->diffInSeconds($end));

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
