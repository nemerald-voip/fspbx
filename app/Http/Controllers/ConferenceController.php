<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConferenceRequest;
use App\Http\Requests\UpdateConferenceRequest;
use App\Models\Conferences;
use App\Services\ConferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ConferenceController extends Controller
{
    protected int $perPage = 50;

    public function index()
    {
        if (! userCheckPermission('conference_view')) {
            return redirect('/');
        }

        return Inertia::render('Conferences', [
            'routes' => [
                'current_page' => route('conferences.index'),
                'data_route' => route('conferences.data'),
                'select_all' => route('conferences.select.all'),
                'bulk_copy' => route('conferences.bulk.copy'),
                'bulk_delete' => route('conferences.bulk.delete'),
                'bulk_toggle' => route('conferences.bulk.toggle'),
                'store' => route('conferences.store'),
                'item_options' => route('conferences.item.options'),
                'active_conferences' => route('active-conferences.index'),
                'conference_profiles' => route('conference-profiles.index'),
                'interactive' => url('/active-conferences/:extension/interactive'),
                'cdr' => '/app/conference_cdr/conference_cdr.php?id=:uuid',
            ],
            'permissions' => $this->permissions(),
        ]);
    }

    public function store(StoreConferenceRequest $request, ConferenceService $service): JsonResponse
    {
        try {
            $conference = $service->save($request->validated());

            return response()->json([
                'messages' => ['success' => ['Conference created successfully.']],
                'conference_uuid' => $conference->conference_uuid,
            ], 201);
        } catch (\Throwable $e) {
            logger('ConferenceController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to create conference.']],
            ], 500);
        }
    }

    public function update(UpdateConferenceRequest $request, Conferences $conference, ConferenceService $service): JsonResponse
    {
        if ($conference->domain_uuid !== session('domain_uuid')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        try {
            $service->save($request->validated(), $conference);

            return response()->json([
                'messages' => ['success' => ['Conference updated successfully.']],
            ]);
        } catch (\Throwable $e) {
            logger('ConferenceController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Failed to update conference.']],
            ], 500);
        }
    }

    public function getItemOptions(Request $request): JsonResponse
    {
        $itemUuid = $request->input('itemUuid', $request->input('item_uuid'));

        if ($itemUuid && ! userCheckPermission('conference_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if (! $itemUuid && ! userCheckPermission('conference_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        if ($itemUuid) {
            $item = QueryBuilder::for(Conferences::class)
                ->where('domain_uuid', session('domain_uuid'))
                ->whereKey($itemUuid)
                ->firstOrFail();

        } else {
            $item = new Conferences();
            $item->conference_extension = $item->generateUniqueSequenceNumber();
            $item->conference_profile = 'default';
            $item->conference_order = 0;
            $item->conference_enabled = 'true';
        }

        return response()->json([
            'item' => $item,
            'profiles' => $this->conferenceProfiles(),
            'permissions' => $this->permissions(),
            'routes' => [
                'store_route' => route('conferences.store'),
                'update_route' => $itemUuid ? route('conferences.update', ['conference' => $item->conference_uuid]) : null,
            ],
        ]);
    }

    public function getData(Request $request)
    {
        if (! userCheckPermission('conference_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        return $this->scopedConferences($request)
            ->select([
                'domain_uuid',
                'conference_uuid',
                'dialplan_uuid',
                'conference_name',
                'conference_extension',
                'conference_pin_number',
                'conference_profile',
                'conference_order',
                'conference_enabled',
                'conference_description',
            ])
            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description');
            }])
            ->allowedSorts([
                'conference_name',
                'conference_extension',
                'conference_profile',
                'conference_order',
                'conference_enabled',
                'conference_description',
            ])
            ->defaultSort('conference_name')
            ->paginate($this->perPage);
    }

    public function selectAll(Request $request): JsonResponse
    {
        if (! userCheckPermission('conference_view')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->scopedConferences($request)
            ->select(['conference_uuid'])
            ->defaultSort('conference_name')
            ->pluck('conference_uuid');

        return response()->json([
            'items' => $items,
            'messages' => ['success' => ['All matching conferences selected.']],
        ]);
    }

    public function bulkCopy(Request $request, ConferenceService $service): JsonResponse
    {
        if (! userCheckPermission('conference_add')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->itemsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No conferences selected.']],
            ], 422);
        }

        $copied = $service->copy($items);

        return response()->json([
            'messages' => ['success' => ["Copied {$copied} conference(s)."]],
        ]);
    }

    public function bulkDelete(Request $request, ConferenceService $service): JsonResponse
    {
        if (! userCheckPermission('conference_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->itemsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No conferences selected.']],
            ], 422);
        }

        $deleted = $service->delete($items);

        return response()->json([
            'messages' => ['success' => ["Deleted {$deleted} conference(s)."]],
        ]);
    }

    public function bulkToggle(Request $request, ConferenceService $service): JsonResponse
    {
        if (! userCheckPermission('conference_edit')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        $items = $this->itemsFromRequest($request);
        if ($items->isEmpty()) {
            return response()->json([
                'messages' => ['error' => ['No conferences selected.']],
            ], 422);
        }

        $service->toggle($items);

        return response()->json([
            'messages' => ['success' => ['Conference status toggled.']],
        ]);
    }

    private function scopedConferences(Request $request): QueryBuilder
    {
        return QueryBuilder::for(Conferences::class)
            ->when(! userCheckPermission('conference_all') || ! $request->boolean('filter.showGlobal'), function ($query) {
                $query->where('domain_uuid', session('domain_uuid'));
            })
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    if ($needle === '') {
                        return;
                    }

                    $query->where(function ($query) use ($needle) {
                        $query->where('conference_uuid', 'ilike', "%{$needle}%")
                            ->orWhere('conference_name', 'ilike', "%{$needle}%")
                            ->orWhere('conference_extension', 'ilike', "%{$needle}%")
                            ->orWhere('conference_pin_number', 'ilike', "%{$needle}%")
                            ->orWhere('conference_profile', 'ilike', "%{$needle}%")
                            ->orWhere('conference_description', 'ilike', "%{$needle}%");
                    });
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) {}),
            ]);
    }

    private function itemsFromRequest(Request $request): Collection
    {
        $uuids = collect($request->input('items', []))
            ->filter(fn ($uuid) => is_string($uuid) && preg_match('/^[0-9a-fA-F-]{36}$/', $uuid))
            ->values()
            ->all();

        if (empty($uuids)) {
            return collect();
        }

        return QueryBuilder::for(Conferences::class)
            ->where('domain_uuid', session('domain_uuid'))
            ->whereIn('conference_uuid', $uuids)
            ->get();
    }

    private function conferenceProfiles(): array
    {
        return DB::table('v_conference_profiles')
            ->where('profile_enabled', 'true')
            ->where('profile_name', '<>', 'sla')
            ->orderBy('profile_name')
            ->pluck('profile_name')
            ->map(fn ($profile) => ['value' => $profile, 'label' => $profile])
            ->all();
    }

    private function permissions(): array
    {
        return [
            'create' => userCheckPermission('conference_add'),
            'update' => userCheckPermission('conference_edit'),
            'destroy' => userCheckPermission('conference_delete'),
            'copy' => userCheckPermission('conference_add'),
            'view_global' => userCheckPermission('conference_all'),
            'view_active' => userCheckPermission('conference_active_view'),
            'profile_view' => userCheckPermission('conference_profile_view'),
            'interactive_view' => userCheckPermission('conference_interactive_view'),
            'cdr_view' => userCheckPermission('conference_cdr_view'),
            'email_address' => userCheckPermission('conference_email_address'),
            'account_code' => userCheckPermission('conference_account_code'),
        ];
    }
}
