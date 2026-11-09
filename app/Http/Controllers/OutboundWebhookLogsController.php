<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexOutboundWebhookLogRequest;
use App\Models\OutboundWebhookLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OutboundWebhookLogsController extends Controller
{
    public function index(IndexOutboundWebhookLogRequest $request)
    {
        $params = $request->validated();
        $domains = collect(session('domains') ?: [])->pluck('domain_uuid')
            ->push(session('domain_uuid'))->filter()->unique()->values()->all();
        $domain = $request->input('filter.domain_uuid') ?: session('domain_uuid');
        abort_unless($domain === 'all' || in_array($domain, $domains, true), 403);
        abort_unless(Schema::hasTable('outbound_webhook_logs'), 503, __('Outbound webhook history is not available yet.'));

        $range = $request->input('filter.dateRange');
        $start = $range ? Carbon::parse($range[0])->utc()
            : Carbon::now(get_local_time_zone(session('domain_uuid')))->startOfDay()->utc();
        $end = $range ? Carbon::parse($range[1])->utc()
            : Carbon::now(get_local_time_zone(session('domain_uuid')))->endOfDay()->utc();
        data_forget($params, 'filter.dateRange');
        $request->replace($params);

        $base = OutboundWebhookLog::query()->with('domain:domain_uuid,domain_name,domain_description')
            ->whereBetween('created_at', [$start, $end]);
        if ($domain !== 'all') {
            $base->where('domain_uuid', $domain);
        } else {
            $system = isSuperAdmin();
            $base->where(function ($query) use ($domains, $system) {
                $query->whereIn('domain_uuid', $domains);
                if ($system) $query->orWhereNull('domain_uuid');
            });
        }

        return QueryBuilder::for($base, $request)->allowedFilters([
            AllowedFilter::callback('domain_uuid', function () { /* Scoped above. */ }),
            AllowedFilter::exact('status'),
            AllowedFilter::callback('search', function ($query) use ($request) {
                // Search is one validated string, including any literal commas.
                $value = $request->input('filter.search');
                $query->where(function ($query) use ($value) {
                    foreach (['event_id', 'event_type', 'related_id', 'endpoint_url'] as $column) {
                        $query->orWhereRaw('LOWER('.$column.') LIKE ?', ['%'.mb_strtolower($value).'%']);
                    }
                });
            }),
        ])->allowedSorts('created_at')->defaultSort('-created_at')->paginate(50);
    }
}
