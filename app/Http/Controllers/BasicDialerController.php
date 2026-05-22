<?php

namespace App\Http\Controllers;

use App\Models\BasicDialerCampaign;
use App\Models\BasicDialerCampaignAttempt;
use App\Models\BasicDialerCampaignRecipient;
use App\Models\BasicDialerContact;
use App\Models\BasicDialerContactList;
use App\Models\Destinations;
use App\Jobs\RunBasicDialerCampaignsJob;
use App\Services\BasicDialerService;
use App\Services\CallRoutingOptionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BasicDialerController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('basic_dialer_view')) {
            return redirect('/');
        }

        return Inertia::render('BasicDialer', [
            'routes' => [
                'current_page' => route('basic-dialer.index'),
                'overview' => route('basic-dialer.overview'),
                'campaign_data' => route('basic-dialer.campaigns.data'),
                'campaign_store' => route('basic-dialer.campaigns.store'),
                'campaign_item_options' => route('basic-dialer.campaigns.item.options'),
                'campaign_select_all' => route('basic-dialer.campaigns.select.all'),
                'campaign_bulk_delete' => route('basic-dialer.campaigns.bulk.delete'),
                'campaign_start' => route('basic-dialer.campaigns.start', ['campaign' => ':campaign']),
                'campaign_pause' => route('basic-dialer.campaigns.pause', ['campaign' => ':campaign']),
                'campaign_stop' => route('basic-dialer.campaigns.stop', ['campaign' => ':campaign']),
                'campaign_status' => route('basic-dialer.campaigns.status', ['campaign' => ':campaign']),
                'contact_list_data' => route('basic-dialer.contact-lists.data'),
                'contact_list_store' => route('basic-dialer.contact-lists.store'),
                'contact_list_item_options' => route('basic-dialer.contact-lists.item.options'),
                'contact_list_select_all' => route('basic-dialer.contact-lists.select.all'),
                'contact_list_bulk_delete' => route('basic-dialer.contact-lists.bulk.delete'),
                'get_routing_options' => route('routing.options'),
            ],
            'permissions' => [
                'create' => userCheckPermission('basic_dialer_create'),
                'update' => userCheckPermission('basic_dialer_update'),
                'destroy' => userCheckPermission('basic_dialer_delete'),
                'start' => userCheckPermission('basic_dialer_start'),
            ],
        ]);
    }

    public function startCampaign(BasicDialerCampaign $campaign, BasicDialerService $service): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_start') || $campaign->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (blank($campaign->destination_target)) {
            return response()->json([
                'messages' => ['error' => ['Choose a campaign destination before starting.']],
            ], 422);
        }

        if (blank($campaign->caller_id_number)) {
            return response()->json([
                'messages' => ['error' => ['Choose a caller ID number before starting.']],
            ], 422);
        }

        $service->startCampaign($campaign);
        RunBasicDialerCampaignsJob::dispatch($campaign->basic_dialer_campaign_uuid);

        return response()->json([
            'messages' => ['success' => ['Campaign started.']],
        ]);
    }

    public function pauseCampaign(BasicDialerCampaign $campaign, BasicDialerService $service): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_start') || $campaign->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $service->pauseCampaign($campaign);

        return response()->json([
            'messages' => ['success' => ['Campaign paused.']],
        ]);
    }

    public function stopCampaign(BasicDialerCampaign $campaign, BasicDialerService $service): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_start') || $campaign->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $service->stopCampaign($campaign);

        return response()->json([
            'messages' => ['success' => ['Campaign stopped.']],
        ]);
    }

    public function getOverview(): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $domainUuid = session('domain_uuid');
        $todayStart = Carbon::now()->startOfDay();
        $weekStart = Carbon::now()->subDays(6)->startOfDay();

        $campaignStatusCounts = BasicDialerCampaign::query()
            ->where('domain_uuid', $domainUuid)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalCampaigns = (int) $campaignStatusCounts->sum();

        $recipientStatusCounts = BasicDialerCampaignRecipient::query()
            ->where('domain_uuid', $domainUuid)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalRecipients = (int) $recipientStatusCounts->sum();

        $attemptsQuery = BasicDialerCampaignAttempt::query()->where('domain_uuid', $domainUuid);
        $totalAttempts = (int) (clone $attemptsQuery)->count();
        $attemptsToday = (int) (clone $attemptsQuery)->where('created_at', '>=', $todayStart)->count();
        $answeredToday = (int) (clone $attemptsQuery)
            ->where('created_at', '>=', $todayStart)
            ->whereNotNull('answered_at')
            ->count();
        $totalAnswered = (int) (clone $attemptsQuery)->whereNotNull('answered_at')->count();
        $totalTalkSeconds = (int) (clone $attemptsQuery)->sum('duration');

        $outcomeBreakdown = (clone $attemptsQuery)
            ->selectRaw("COALESCE(NULLIF(outcome, ''), 'unknown') as label, count(*) as count")
            ->groupBy('label')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->count])
            ->values();

        $hangupBreakdown = (clone $attemptsQuery)
            ->whereNotNull('hangup_cause')
            ->where('hangup_cause', '<>', '')
            ->selectRaw('hangup_cause as label, count(*) as count')
            ->groupBy('label')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->count])
            ->values();

        $dailyVolume = (clone $attemptsQuery)
            ->where('created_at', '>=', $weekStart)
            ->selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, count(*) as total, count(answered_at) as answered")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => $row->day,
                'total' => (int) $row->total,
                'answered' => (int) $row->answered,
            ])
            ->values();

        $recentActivity = BasicDialerCampaignAttempt::query()
            ->where('domain_uuid', $domainUuid)
            ->with([
                'recipient:basic_dialer_campaign_recipient_uuid,phone_number,contact_name',
                'campaign:basic_dialer_campaign_uuid,name',
            ])
            ->select([
                'basic_dialer_campaign_attempt_uuid',
                'basic_dialer_campaign_uuid',
                'basic_dialer_campaign_recipient_uuid',
                'attempt_number',
                'status',
                'outcome',
                'hangup_cause',
                'duration',
                'queued_at',
                'answered_at',
                'ended_at',
                'created_at',
            ])
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $activeCampaigns = BasicDialerCampaign::query()
            ->where('domain_uuid', $domainUuid)
            ->whereIn('status', ['running', 'paused'])
            ->select([
                'basic_dialer_campaign_uuid',
                'name',
                'status',
                'started_at',
                'last_run_at',
            ])
            ->withCount([
                'recipients',
                'recipients as pending_recipients_count' => fn ($q) => $q->where('status', 'pending'),
                'recipients as answered_recipients_count' => fn ($q) => $q->where('status', 'answered'),
                'recipients as failed_recipients_count' => fn ($q) => $q->where('status', 'failed'),
                'attempts',
            ])
            ->orderByDesc('last_run_at')
            ->limit(10)
            ->get();

        return response()->json([
            'kpis' => [
                'total_campaigns' => $totalCampaigns,
                'running_campaigns' => (int) ($campaignStatusCounts['running'] ?? 0),
                'paused_campaigns' => (int) ($campaignStatusCounts['paused'] ?? 0),
                'draft_campaigns' => (int) ($campaignStatusCounts['draft'] ?? 0),
                'completed_campaigns' => (int) ($campaignStatusCounts['completed'] ?? 0),
                'stopped_campaigns' => (int) ($campaignStatusCounts['stopped'] ?? 0),
                'total_recipients' => $totalRecipients,
                'pending_recipients' => (int) ($recipientStatusCounts['pending'] ?? 0),
                'answered_recipients' => (int) ($recipientStatusCounts['answered'] ?? 0),
                'failed_recipients' => (int) ($recipientStatusCounts['failed'] ?? 0),
                'total_attempts' => $totalAttempts,
                'attempts_today' => $attemptsToday,
                'answered_today' => $answeredToday,
                'total_answered' => $totalAnswered,
                'answer_rate' => $totalAttempts > 0 ? round(($totalAnswered / $totalAttempts) * 100, 1) : 0,
                'answer_rate_today' => $attemptsToday > 0 ? round(($answeredToday / $attemptsToday) * 100, 1) : 0,
                'total_talk_seconds' => $totalTalkSeconds,
            ],
            'campaign_status_counts' => $campaignStatusCounts,
            'recipient_status_counts' => $recipientStatusCounts,
            'outcome_breakdown' => $outcomeBreakdown,
            'hangup_breakdown' => $hangupBreakdown,
            'daily_volume' => $dailyVolume,
            'recent_activity' => $recentActivity,
            'active_campaigns' => $activeCampaigns,
        ]);
    }

    public function getCampaignStatus(BasicDialerCampaign $campaign): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_view') || $campaign->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $campaign->load(['contactList:basic_dialer_contact_list_uuid,name']);

        $recipientStatusCounts = $campaign->recipients()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $attemptStatusCounts = $campaign->attempts()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $outcomeBreakdown = $campaign->attempts()
            ->selectRaw("COALESCE(NULLIF(outcome, ''), 'unknown') as label, count(*) as count")
            ->groupBy('label')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->count])
            ->values();

        $hangupBreakdown = $campaign->attempts()
            ->whereNotNull('hangup_cause')
            ->where('hangup_cause', '<>', '')
            ->selectRaw('hangup_cause as label, count(*) as count')
            ->groupBy('label')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->count])
            ->values();

        $totalAttemptsAll = (int) $campaign->attempts()->count();
        $answeredAttemptsAll = (int) $campaign->attempts()->whereNotNull('answered_at')->count();
        $talkSeconds = (int) $campaign->attempts()->sum('duration');

        $recipients = $campaign->recipients()
            ->select([
                'basic_dialer_campaign_recipient_uuid',
                'phone_number',
                'contact_name',
                'status',
                'attempts_count',
                'last_attempt_at',
                'next_attempt_at',
                'completed_at',
                'last_outcome',
                'last_error',
            ])
            ->orderByRaw("CASE status WHEN 'dialing' THEN 0 WHEN 'retry_wait' THEN 1 WHEN 'pending' THEN 2 WHEN 'failed' THEN 3 WHEN 'answered' THEN 4 ELSE 5 END")
            ->orderByDesc('last_attempt_at')
            ->orderBy('phone_number')
            ->limit(500)
            ->get();

        $attempts = $campaign->attempts()
            ->with(['recipient:basic_dialer_campaign_recipient_uuid,phone_number,contact_name'])
            ->select([
                'basic_dialer_campaign_attempt_uuid',
                'basic_dialer_campaign_recipient_uuid',
                'call_uuid',
                'attempt_number',
                'status',
                'outcome',
                'hangup_cause',
                'duration',
                'queued_at',
                'started_at',
                'answered_at',
                'ended_at',
                'response',
            ])
            ->orderByDesc('created_at')
            ->limit(250)
            ->get();

        $totalRecipients = $recipients->count() >= 500
            ? (int) $campaign->recipients()->count()
            : (int) $recipients->count();

        return response()->json([
            'campaign' => [
                'basic_dialer_campaign_uuid' => $campaign->basic_dialer_campaign_uuid,
                'name' => $campaign->name,
                'status' => $campaign->status,
                'contact_list_name' => $campaign->contactList?->name,
                'destination_label' => $campaign->destination_label,
                'destination_type' => $campaign->destination_type,
                'caller_id_number' => $campaign->caller_id_number,
                'started_at' => $campaign->started_at,
                'completed_at' => $campaign->completed_at,
                'last_run_at' => $campaign->last_run_at,
            ],
            'summary' => [
                'recipients' => $recipientStatusCounts,
                'attempts' => $attemptStatusCounts,
                'total_recipients' => $totalRecipients,
                'total_attempts' => $totalAttemptsAll,
                'answered_attempts' => $answeredAttemptsAll,
                'answer_rate' => $totalAttemptsAll > 0 ? round(($answeredAttemptsAll / $totalAttemptsAll) * 100, 1) : 0,
                'talk_seconds' => $talkSeconds,
                'completion_percent' => $totalRecipients > 0
                    ? round((($totalRecipients - (int) ($recipientStatusCounts['pending'] ?? 0) - (int) ($recipientStatusCounts['retry_wait'] ?? 0)) / $totalRecipients) * 100, 1)
                    : 0,
            ],
            'outcome_breakdown' => $outcomeBreakdown,
            'hangup_breakdown' => $hangupBreakdown,
            'recipients' => $recipients,
            'attempts' => $attempts,
        ]);
    }

    public function storeCampaign(Request $request, BasicDialerService $service): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_create')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $validated = $this->validatedCampaign($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $campaign = $service->saveCampaign($validated);

        return response()->json([
            'messages' => ['success' => ['Campaign created.']],
            'basic_dialer_campaign_uuid' => $campaign->basic_dialer_campaign_uuid,
        ], 201);
    }

    public function updateCampaign(Request $request, BasicDialerCampaign $campaign, BasicDialerService $service): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_update') || $campaign->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $validated = $this->validatedCampaign($request, $campaign);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $service->saveCampaign($validated, $campaign);

        return response()->json([
            'messages' => ['success' => ['Campaign updated.']],
        ]);
    }

    public function storeContactList(Request $request, BasicDialerService $service): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_create')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $validated = $this->validatedContactList($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $contactList = $service->saveContactList($validated);

        return response()->json([
            'messages' => ['success' => ['Contact list created.']],
            'basic_dialer_contact_list_uuid' => $contactList->basic_dialer_contact_list_uuid,
        ], 201);
    }

    public function updateContactList(Request $request, BasicDialerContactList $contactList, BasicDialerService $service): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_update') || $contactList->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $validated = $this->validatedContactList($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $service->saveContactList($validated, $contactList);

        return response()->json([
            'messages' => ['success' => ['Contact list updated.']],
        ]);
    }

    public function getCampaignItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('basic_dialer_update')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (! $itemUuid && ! userCheckPermission('basic_dialer_create')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $item = $itemUuid
            ? BasicDialerCampaign::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($itemUuid)
                ->firstOrFail()
            : new BasicDialerCampaign([
                'enabled' => true,
                'status' => 'draft',
                'max_concurrent_calls' => 1,
                'seconds_between_calls' => 5,
                'retry_limit' => 0,
                'retry_delay_minutes' => 60,
                'originate_timeout' => 30,
            ]);

        return response()->json([
            'item' => $item,
            'contact_lists' => $this->contactListOptions(),
            'phone_numbers' => $this->phoneNumberOptions(),
            'routing_types' => (new CallRoutingOptionsService)->routingTypes,
            'destination_target' => $this->destinationTarget($item),
            'routes' => [
                'store_route' => route('basic-dialer.campaigns.store'),
                'update_route' => $itemUuid ? route('basic-dialer.campaigns.update', ['campaign' => $item->basic_dialer_campaign_uuid]) : null,
                'get_routing_options' => route('routing.options'),
            ],
        ]);
    }

    public function getContactListItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('basic_dialer_update')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if (! $itemUuid && ! userCheckPermission('basic_dialer_create')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $item = $itemUuid
            ? BasicDialerContactList::query()
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($itemUuid)
                ->firstOrFail()
            : new BasicDialerContactList(['enabled' => true]);

        return response()->json([
            'item' => $item,
            'contacts_total' => $itemUuid
                ? (int) BasicDialerContact::query()
                    ->where('domain_uuid', session('domain_uuid'))
                    ->where('basic_dialer_contact_list_uuid', $item->basic_dialer_contact_list_uuid)
                    ->count()
                : 0,
            'routes' => [
                'store_route' => route('basic-dialer.contact-lists.store'),
                'update_route' => $itemUuid ? route('basic-dialer.contact-lists.update', ['contactList' => $item->basic_dialer_contact_list_uuid]) : null,
                'contacts_route' => $itemUuid ? route('basic-dialer.contact-lists.contacts', ['contactList' => $item->basic_dialer_contact_list_uuid]) : null,
                'contact_delete_route' => $itemUuid ? route('basic-dialer.contact-lists.contacts.destroy', ['contactList' => $item->basic_dialer_contact_list_uuid, 'contact' => ':contact']) : null,
            ],
        ]);
    }

    public function getContactsForList(Request $request, BasicDialerContactList $contactList): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_view') || $contactList->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $perPage = max(5, min(100, (int) $request->input('per_page', 20)));

        return response()->json(
            QueryBuilder::for(BasicDialerContact::class)
                ->where('domain_uuid', session('domain_uuid'))
                ->where('basic_dialer_contact_list_uuid', $contactList->basic_dialer_contact_list_uuid)
                ->allowedFilters([
                    AllowedFilter::callback('search', function ($query, $value) {
                        $needle = trim((string) $value);

                        if ($needle === '') {
                            return;
                        }

                        $query->where(function ($q) use ($needle) {
                            $q->where('phone_number', 'ilike', "%{$needle}%")
                                ->orWhere('contact_name', 'ilike', "%{$needle}%")
                                ->orWhere('company', 'ilike', "%{$needle}%");
                        });
                    }),
                ])
                ->allowedSorts(['phone_number', 'contact_name', 'company', 'created_at'])
                ->defaultSort('contact_name')
                ->select(['basic_dialer_contact_uuid', 'phone_number', 'contact_name', 'company', 'enabled', 'created_at'])
                ->paginate($perPage)
        );
    }

    public function deleteContact(BasicDialerContactList $contactList, BasicDialerContact $contact): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_update') || $contactList->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        if ($contact->basic_dialer_contact_list_uuid !== $contactList->basic_dialer_contact_list_uuid
            || $contact->domain_uuid !== session('domain_uuid')) {
            return response()->json(['messages' => ['error' => ['Contact not found.']]], 404);
        }

        $contact->delete();

        return response()->json([
            'messages' => ['success' => ['Contact deleted.']],
        ]);
    }

    public function getCampaignData(Request $request)
    {
        if (! userCheckPermission('basic_dialer_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return $this->scopedCampaigns($request)
            ->select([
                'basic_dialer_campaign_uuid',
                'domain_uuid',
                'basic_dialer_contact_list_uuid',
                'name',
                'status',
                'enabled',
                'destination_type',
                'destination_label',
                'max_concurrent_calls',
                'seconds_between_calls',
                'retry_limit',
                'updated_at',
                'started_at',
                'paused_at',
                'stopped_at',
                'completed_at',
                'last_run_at',
            ])
            ->with(['contactList:basic_dialer_contact_list_uuid,name'])
            ->withCount([
                'recipients',
                'recipients as pending_recipients_count' => fn ($query) => $query->where('status', 'pending'),
                'attempts',
            ])
            ->allowedSorts(['name', 'status', 'enabled', 'updated_at'])
            ->defaultSort('name')
            ->paginate($this->perPage);
    }

    public function getContactListData(Request $request)
    {
        if (! userCheckPermission('basic_dialer_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return $this->scopedContactLists($request)
            ->select([
                'basic_dialer_contact_list_uuid',
                'domain_uuid',
                'name',
                'description',
                'enabled',
                'updated_at',
            ])
            ->withCount(['contacts', 'campaigns'])
            ->allowedSorts(['name', 'enabled', 'updated_at'])
            ->defaultSort('name')
            ->paginate($this->perPage);
    }

    public function selectAllCampaigns(Request $request): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return response()->json([
            'items' => $this->scopedCampaigns($request)
                ->defaultSort('name')
                ->pluck('basic_dialer_campaign_uuid'),
            'messages' => ['success' => ['All matching campaigns selected.']],
        ]);
    }

    public function selectAllContactLists(Request $request): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_view')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        return response()->json([
            'items' => $this->scopedContactLists($request)
                ->defaultSort('name')
                ->pluck('basic_dialer_contact_list_uuid'),
            'messages' => ['success' => ['All matching contact lists selected.']],
        ]);
    }

    public function bulkDeleteCampaigns(Request $request, BasicDialerService $service): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $items = $this->campaignsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json(['messages' => ['error' => ['No campaigns selected.']]], 422);
        }

        $deleted = $service->deleteCampaigns($items);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} campaign(s)."]],
        ]);
    }

    public function bulkDeleteContactLists(Request $request, BasicDialerService $service): JsonResponse
    {
        if (! userCheckPermission('basic_dialer_delete')) {
            return response()->json(['messages' => ['error' => ['Access denied.']]], 403);
        }

        $items = $this->contactListsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json(['messages' => ['error' => ['No contact lists selected.']]], 422);
        }

        $deleted = $service->deleteContactLists($items);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} contact list(s)."]],
        ]);
    }

    private function scopedCampaigns(Request $request): QueryBuilder
    {
        return QueryBuilder::for(BasicDialerCampaign::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('name', 'ilike', "%{$needle}%")
                            ->orWhere('status', 'ilike', "%{$needle}%")
                            ->orWhere('destination_label', 'ilike', "%{$needle}%")
                            ->orWhere('description', 'ilike', "%{$needle}%");
                    });
                }),
            ]);
    }

    private function scopedContactLists(Request $request): QueryBuilder
    {
        return QueryBuilder::for(BasicDialerContactList::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('name', 'ilike', "%{$needle}%")
                            ->orWhere('description', 'ilike', "%{$needle}%");
                    });
                }),
            ]);
    }

    private function campaignsFromRequest(Request $request): Collection
    {
        $uuids = $this->validatedUuids($request);

        if (empty($uuids)) {
            return collect();
        }

        return BasicDialerCampaign::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('basic_dialer_campaign_uuid', $uuids)
            ->get();
    }

    private function contactListsFromRequest(Request $request): Collection
    {
        $uuids = $this->validatedUuids($request);

        if (empty($uuids)) {
            return collect();
        }

        return BasicDialerContactList::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('basic_dialer_contact_list_uuid', $uuids)
            ->get();
    }

    private function validatedCampaign(Request $request, ?BasicDialerCampaign $campaign = null): array|JsonResponse
    {
        $phoneNumberValues = array_column($this->phoneNumberOptions(), 'value');

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'basic_dialer_contact_list_uuid' => ['nullable', 'uuid'],
            'description' => ['nullable', 'string'],
            'enabled' => ['nullable', 'boolean'],
            'caller_id_name' => ['nullable', 'string', 'max:255'],
            'caller_id_number' => ['required', 'string', 'max:64', Rule::in($phoneNumberValues)],
            'destination_type' => ['required', 'string', 'max:64'],
            'destination_target' => ['nullable'],
            'max_concurrent_calls' => ['required', 'integer', 'min:1', 'max:100'],
            'seconds_between_calls' => ['required', 'integer', 'min:0', 'max:3600'],
            'retry_limit' => ['required', 'integer', 'min:0', 'max:10'],
            'retry_delay_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'originate_timeout' => ['required', 'integer', 'min:5', 'max:300'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'messages' => ['error' => ['Invalid campaign details.']],
            ], 422);
        }

        $validated = $validator->validated();
        $destinationType = $validated['destination_type'] ?? null;
        $destinationTarget = $validated['destination_target'] ?? null;
        $destinationValue = is_array($destinationTarget)
            ? ($destinationTarget['extension'] ?? $destinationTarget['value'] ?? $destinationTarget['option'] ?? null)
            : $destinationTarget;

        if (
            ! in_array($destinationType, ['check_voicemail', 'company_directory', 'hangup'], true)
            && blank($destinationValue)
        ) {
            return response()->json([
                'errors' => ['destination_target' => ['Choose a target.']],
                'messages' => ['error' => ['Invalid campaign details.']],
            ], 422);
        }

        $contactListUuid = $validated['basic_dialer_contact_list_uuid'] ?? null;

        if ($contactListUuid && ! BasicDialerContactList::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->whereKey($contactListUuid)
            ->exists()) {
            return response()->json([
                'errors' => ['basic_dialer_contact_list_uuid' => ['Contact list not found.']],
                'messages' => ['error' => ['Invalid campaign details.']],
            ], 422);
        }

        return $validated;
    }

    private function validatedContactList(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'enabled' => ['nullable', 'boolean'],
            'contacts' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'messages' => ['error' => ['Invalid contact list details.']],
            ], 422);
        }

        return $validator->validated();
    }

    private function validatedUuids(Request $request): array
    {
        return collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && preg_match('/^[0-9a-fA-F-]{36}$/', $uuid))
            ->values()
            ->all();
    }

    private function contactListOptions(): array
    {
        return BasicDialerContactList::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('enabled', true)
            ->orderBy('name')
            ->get(['basic_dialer_contact_list_uuid', 'name'])
            ->map(fn (BasicDialerContactList $contactList) => [
                'value' => $contactList->basic_dialer_contact_list_uuid,
                'label' => $contactList->name,
            ])
            ->values()
            ->all();
    }

    private function phoneNumberOptions(): array
    {
        return QueryBuilder::for(Destinations::class)
            ->allowedFilters(['destination_number', 'destination_description'])
            ->allowedSorts('destination_number')
            ->where('destination_enabled', 'true')
            ->where('domain_uuid', session('domain_uuid'))
            ->get([
                'destination_uuid',
                'destination_number',
                'destination_description',
            ])
            ->each->append('label', 'destination_number_e164')
            ->map(fn (Destinations $destination) => [
                'value' => $destination->destination_number_e164,
                'label' => $destination->label,
            ])
            ->values()
            ->toArray();
    }

    private function destinationTarget(BasicDialerCampaign $campaign): ?array
    {
        if (blank($campaign->destination_target)) {
            return null;
        }

        return [
            'value' => $campaign->destination_target,
            'name' => $campaign->destination_label ?: $campaign->destination_target,
        ];
    }
}
