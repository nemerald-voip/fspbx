<?php

namespace App\Http\Controllers;

use App\Models\HotelRoom;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Services\HotelRoomService;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Http\Requests\StoreHotelRoomRequest;
use App\Http\Requests\UpdateHotelRoomRequest;
use App\Http\Requests\BulkStoreHotelRoomsRequest;
use App\Http\Requests\HotelRoomItemOptionsRequest;
use App\Http\Requests\HotelRoomSelectionRequest;
use App\Services\HotelPortalAccess;

class HotelRoomController extends Controller
{

    public function index(Request $request)
    {
        abort_unless(HotelPortalAccess::allows('hotel_rooms_list_view'), 403);
        $perPage    = (int) $request->input('per_page', 50);

        $query = QueryBuilder::for(HotelPortalAccess::rooms())
            ->select([
                'uuid',
                'extension_uuid',
                'room_name',
            ])
            ->allowedFilters([
                // Older portal builds send this filter. The session already scopes the query.
                AllowedFilter::callback('domain_uuid', static fn ($query) => $query),
                AllowedFilter::callback('search', function ($q, $value) {
                    if ($value === null || $value === '') return;
                    $q->where(function ($qq) use ($value) {
                        $qq->where('room_name', 'ILIKE', "%{$value}%");
                    });
                }),
            ])
            ->with(['extension' => function ($query) {
                $query->where('domain_uuid', session('domain_uuid'))->select('extension_uuid', 'extension');
            }])
            ->allowedSorts(['room_name'])
            ->defaultSort('room_name');


        $rooms = $query->paginate($perPage)->appends($request->query());

        // logger($rooms);

        return response()->json($rooms);
    }

    public function store(StoreHotelRoomRequest $request)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            HotelRoom::create($data + ['domain_uuid' => session('domain_uuid')]);

            DB::commit();

            return response()->json([
                'messages' => ['success' => [__('New hotel room created')]]
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('HotelRoomController@store error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('Something went wrong while saving.')]]
            ], 500);
        }
    }

    public function bulkStore(BulkStoreHotelRoomsRequest $request)
    {
        $data = $request->validated();
        $domainUuid = session('domain_uuid');
        $uuids = $data['extensions'];

        try {
            DB::beginTransaction();

            // Pull extension number + uuid for the provided UUIDs (scoped to domain)
            $extensions = Extensions::query()
                ->where('domain_uuid', $domainUuid)
                ->whereIn('extension_uuid', $uuids)
                ->select(['extension_uuid', 'extension'])
                ->get();

            $created = [];

            foreach ($extensions as $ext) {
                $created[] = HotelRoom::create([
                    'domain_uuid'    => $domainUuid,
                    'extension_uuid' => $ext->extension_uuid,
                    'room_name'      => (string) $ext->extension, // as requested
                ]);
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => [trans_choice('{1} :count room created|[0,*] :count rooms created', count($created))]],
                // If helpful for the UI:
                // 'data' => collect($created)->map->only(['uuid','room_name','extension_uuid']),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('HotelRoomController@bulkStore error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('Something went wrong while saving.')]],
            ], 500);
        }
    }

    public function update(UpdateHotelRoomRequest $request, $uuid)
    {
        $data = $request->validated();
        $hotelRoom = HotelPortalAccess::rooms()->findOrFail($uuid);

        try {
            DB::beginTransaction();

            $hotelRoom->update($data);

            DB::commit();

            return response()->json([
                'messages' => ['success' => [__('Hotel room updated')]]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('HotelRoomController@update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('Something went wrong while updating.')]]
            ], 500);
        }
    }

    public function toggleDnd(HotelRoom $room, HotelRoomService $svc)
    {
        $room->update(['dnd' => !$room->dnd]);
        $svc->applyDndToExtension($room); // sync to FreeSWITCH
        return response()->json(['dnd' => $room->dnd]);
    }

    public function setHousekeeping(Request $req, HotelRoom $room)
    {
        $status = $req->validate(['housekeeping_status' => 'required|in:clean,dirty,inspected']);
        $room->update($status);
        return $room->fresh();
    }

    public function checkIn(Request $req, HotelRoom $room, HotelRoomService $svc)
    {
        $payload = $req->validate([
            'reservation_id' => 'nullable|uuid',
            'guest_first_name' => 'required|string|max:120',
            'guest_last_name' => 'required|string|max:120',
            'departure_date' => 'required|date'
        ]);

        $svc->checkIn($room, $payload);
        return $room->fresh();
    }

    public function checkOut(HotelRoom $room, HotelRoomService $svc)
    {
        $svc->checkOut($room);
        return $room->fresh();
    }

    public function getItemOptions(HotelRoomItemOptionsRequest $request)
    {
        $uuid = $request->validated('item_uuid');
        $item = $uuid ? HotelPortalAccess::rooms()->findOrFail($uuid) : null;
        try {
            $routes = [];

            if ($item) {
                $routes = array_merge($routes, [
                    'update_route' => route('hotel-rooms.update', ['hotel_room' => $item->uuid]),
                ]);
            }

            $currentDomain = session('domain_uuid');

            // Subquery of extensions already tied to a room (in this domain)
            $assignedExtensionsSub = HotelRoom::query()
                ->select('extension_uuid')
                ->whereNotNull('extension_uuid')
                ->where('domain_uuid', $currentDomain);

            // Base: current-domain extensions
            $base = Extensions::query()
                ->where('domain_uuid', $currentDomain);

            // For "add new": exclude all assigned.
            // For "edit": exclude assigned EXCEPT keep the current room's extension.
            $extensionsQuery = $base->when(
                $item,
                fn($q) => $q->where(function ($q2) use ($assignedExtensionsSub, $item) {
                    $q2->whereNotIn('extension_uuid', $assignedExtensionsSub)
                        ->orWhere('extension_uuid', $item->extension_uuid);
                }),
                fn($q) => $q->whereNotIn('extension_uuid', $assignedExtensionsSub)
            );

            $extensions = QueryBuilder::for($extensionsQuery)
                ->select([
                    'extension_uuid',
                    'extension',
                    'effective_caller_id_name',
                ])
                ->defaultSort('extension')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->extension_uuid,
                        'label' => $item->name_formatted ?? '',
                    ];
                });

            $routes = array_merge($routes, [
                'store_route'  => route('hotel-rooms.store'),
                'bulk_store_route' => route('hotel-rooms.bulk.store'),
            ]);

            // logger($extensions);

            return response()->json([
                'item' => $item,
                'extensions' => $extensions,
                'routes' => $routes,
            ]);
        } catch (\Throwable $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to fetch item details')]]
            ], 500);
        }
    }

    public function bulkDelete(HotelRoomSelectionRequest $request)
    {
        $items = HotelPortalAccess::selectedRooms($request->validated('items'));
        try {
            DB::beginTransaction();

            foreach ($items as $item) {
                $item->status()->where('domain_uuid', $item->domain_uuid)->delete();
                $item->delete();
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => [__('Selected hotel room(s) were deleted successfully.')]]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('HotelRoomController@bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('An error occurred while deleting the selected hotel room(s).')]]
            ], 500);
        }
    }
}
