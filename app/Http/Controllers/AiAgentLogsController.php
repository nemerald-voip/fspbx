<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexAiAgentLogRequest;
use App\Models\AiToolInvocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AiAgentLogsController extends Controller
{
    public function index(IndexAiAgentLogRequest $request): JsonResponse
    {
        $params = $request->validated();
        $domainUuid = $this->requestedDomainUuid($request);
        $dateRange = $request->input('filter.dateRange');
        $sessionDomainUuid = (string) session('domain_uuid');

        $startPeriod = $dateRange
            ? Carbon::parse($dateRange[0])->utc()
            : Carbon::now(get_local_time_zone($sessionDomainUuid))->startOfDay()->utc();
        $endPeriod = $dateRange
            ? Carbon::parse($dateRange[1])->utc()
            : Carbon::now(get_local_time_zone($sessionDomainUuid))->endOfDay()->utc();

        data_forget($params, 'filter.dateRange');
        data_set($params, 'filter.startPeriod', $startPeriod);
        data_set($params, 'filter.endPeriod', $endPeriod);

        $query = QueryBuilder::for(AiToolInvocation::class, $request->merge($params))
            ->select([
                'ai_tool_invocation_uuid',
                'domain_uuid',
                'ai_agent_uuid',
                'provider_call_id',
                'tool_name',
                'request_payload',
                'status',
                'last_error',
                'sent_at',
                'created_at',
            ])
            ->with(['agent:ai_agent_uuid,name'])
            ->when(
                $domainUuid,
                fn ($query) => $query->where('domain_uuid', $domainUuid),
                fn ($query) => $query->whereIn('domain_uuid', $this->allowedDomainUuids())
            )
            ->allowedFilters([
                AllowedFilter::callback('domain_uuid', function ($query, $value) {
                    // Domain scope is validated and applied before QueryBuilder filters run.
                }),
                AllowedFilter::callback('startPeriod', function ($query, $value) {
                    $query->where('created_at', '>=', $value);
                }),
                AllowedFilter::callback('endPeriod', function ($query, $value) {
                    $query->where('created_at', '<=', $value);
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $query->where(function ($query) use ($value) {
                        $query->where('provider_call_id', 'ILIKE', "%{$value}%")
                            ->orWhere('tool_name', 'ILIKE', "%{$value}%")
                            ->orWhere('status', 'ILIKE', "%{$value}%")
                            ->orWhere('last_error', 'ILIKE', "%{$value}%")
                            ->orWhereRaw('request_payload::text ILIKE ?', ["%{$value}%"])
                            ->orWhere('ai_agent_uuid', 'ILIKE', "%{$value}%")
                            ->orWhereHas('agent', function ($agentQuery) use ($value) {
                                $agentQuery->where('name', 'ILIKE', "%{$value}%");
                            });
                    });
                }),
            ])
            ->allowedSorts(['created_at'])
            ->defaultSort('-created_at');

        return response()->json($query->paginate(50));
    }

    protected function requestedDomainUuid(IndexAiAgentLogRequest $request): ?string
    {
        $requested = $request->input('filter.domain_uuid') ?: session('domain_uuid');

        if ($requested === 'all') {
            return null;
        }

        return in_array((string) $requested, $this->allowedDomainUuids(), true)
            ? (string) $requested
            : (string) session('domain_uuid');
    }

    protected function allowedDomainUuids(): array
    {
        $domains = Session::get('domains');

        if ($domains) {
            return collect($domains)
                ->pluck('domain_uuid')
                ->filter()
                ->map(fn ($uuid) => (string) $uuid)
                ->values()
                ->all();
        }

        return array_values(array_filter([(string) session('domain_uuid')]));
    }
}
