<?php

namespace App\Http\Controllers;

use App\Models\TigerTmsApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TigerTmsLogsController extends Controller
{
    public function index(Request $request)
    {
        if (! userCheckPermission('logs_list_view')) {
            return response()->json([
                'errors' => ['server' => ['Permission denied.']],
            ], 403);
        }

        if (! $this->tigerTmsConfigured()) {
            return response()->json([
                'errors' => ['server' => ['TigerTMS is not configured.']],
            ], 404);
        }

        $params = $request->all();
        $params['paginate'] = 50;

        $domainUuid = $this->requestedDomainUuid();

        if (! empty($request->input('filter.dateRange'))) {
            $startPeriod = Carbon::parse($request->input('filter.dateRange.0'))->setTimeZone('UTC');
            $endPeriod = Carbon::parse($request->input('filter.dateRange.1'))->setTimeZone('UTC');
        } else {
            $sessionDomainUuid = session('domain_uuid');
            $startPeriod = Carbon::now(get_local_time_zone($sessionDomainUuid))->startOfDay()->setTimeZone('UTC');
            $endPeriod = Carbon::now(get_local_time_zone($sessionDomainUuid))->endOfDay()->setTimeZone('UTC');
        }

        $params['filter']['startPeriod'] = $startPeriod;
        $params['filter']['endPeriod'] = $endPeriod;

        unset($params['filter']['dateRange']);

        $query = QueryBuilder::for(TigerTmsApiLog::class, $request->merge($params))
            ->select([
                'uuid',
                'domain_uuid',
                'method',
                'endpoint',
                'url',
                'request_context',
                'request_payload',
                'response_status',
                'response_body',
                'error',
                'duration_ms',
                'created_at',
            ])
            ->with(['domain:domain_uuid,domain_name,domain_description'])
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
                        $query->where('method', 'ILIKE', "%{$value}%")
                            ->orWhere('endpoint', 'ILIKE', "%{$value}%")
                            ->orWhere('url', 'ILIKE', "%{$value}%")
                            ->orWhere('error', 'ILIKE', "%{$value}%")
                            ->orWhereRaw('request_context::text ILIKE ?', ["%{$value}%"])
                            ->orWhereRaw('request_payload::text ILIKE ?', ["%{$value}%"])
                            ->orWhereRaw('response_body::text ILIKE ?', ["%{$value}%"]);
                    });
                }),
            ])
            ->allowedSorts(['created_at'])
            ->defaultSort('-created_at');

        return response()->json($query->paginate($params['paginate']));
    }

    protected function requestedDomainUuid(): ?string
    {
        $requested = request('filter.domain_uuid') ?: session('domain_uuid');

        if ($requested === 'all') {
            return null;
        }

        $allowedDomainUuids = $this->allowedDomainUuids();

        return in_array((string) $requested, $allowedDomainUuids, true)
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

    protected function tigerTmsConfigured(): bool
    {
        return filled(config('tigertms.base_url'))
            && filled(config('tigertms.username'))
            && filled(config('tigertms.password'));
    }
}
