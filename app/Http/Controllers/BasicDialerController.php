<?php

namespace App\Http\Controllers;

use App\Models\BasicDialerCampaign;
use App\Models\BasicDialerContact;
use App\Models\BasicDialerContactList;
use App\Models\Destinations;
use App\Jobs\RunBasicDialerCampaignsJob;
use App\Services\BasicDialerService;
use App\Services\CallRoutingOptionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                'campaign_data' => route('basic-dialer.campaigns.data'),
                'campaign_store' => route('basic-dialer.campaigns.store'),
                'campaign_item_options' => route('basic-dialer.campaigns.item.options'),
                'campaign_select_all' => route('basic-dialer.campaigns.select.all'),
                'campaign_bulk_delete' => route('basic-dialer.campaigns.bulk.delete'),
                'campaign_start' => route('basic-dialer.campaigns.start', ['campaign' => ':campaign']),
                'campaign_pause' => route('basic-dialer.campaigns.pause', ['campaign' => ':campaign']),
                'campaign_stop' => route('basic-dialer.campaigns.stop', ['campaign' => ':campaign']),
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
            'contacts' => $itemUuid ? $this->contactsForList($item) : [],
            'routes' => [
                'store_route' => route('basic-dialer.contact-lists.store'),
                'update_route' => $itemUuid ? route('basic-dialer.contact-lists.update', ['contactList' => $item->basic_dialer_contact_list_uuid]) : null,
            ],
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
            'destination_type' => ['nullable', 'string', 'max:64'],
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

    private function contactsForList(BasicDialerContactList $contactList): array
    {
        return BasicDialerContact::query()
            ->where('domain_uuid', session('domain_uuid'))
            ->where('basic_dialer_contact_list_uuid', $contactList->basic_dialer_contact_list_uuid)
            ->orderBy('contact_name')
            ->limit(250)
            ->get(['basic_dialer_contact_uuid', 'phone_number', 'contact_name', 'company', 'enabled'])
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
