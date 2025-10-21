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
use App\Models\CallTranscriptionProvider;
use Exception;

class CallTranscriptionProviderController extends Controller
{

    public function index(Request $request)
    {

        $query = QueryBuilder::for(CallTranscriptionProvider::query())
            ->select([
                'uuid',
                'name',
            ])
            ->where('is_active', true)
            ->allowedSorts(['name'])
            ->defaultSort('name');

throw new Exception('hi');
        $data = $query->get()->map(function ($item) {
            return [
                'value' => $item->uuid,
                'label' => $item->name,
            ];
        })->toArray();;

        logger($data);

        return response()->json($data);
    }

    public function store(StoreHotelRoomRequest $request)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $hotelRoom = HotelRoom::create($data);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['New hotel room created']]
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('HotelRoomController@store error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while saving.']]
            ], 500);
        }
    }

    public function bulkStore(BulkStoreHotelRoomsRequest $request)
    {
        $data = $request->validated();
        $domainUuid = (string) $data['domain_uuid'];
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
                'messages' => ['success' => [count($created) . ' room(s) created']],
                // If helpful for the UI:
                // 'data' => collect($created)->map->only(['uuid','room_name','extension_uuid']),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('HotelRoomController@bulkStore error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while saving.']],
            ], 500);
        }
    }

    public function update(UpdateHotelRoomRequest $request, $uuid)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $hotelRoom = HotelRoom::find($uuid);
            if (!$hotelRoom) {
                return response()->json([
                    'messages' => ['error' => ['Hotel room not found.']]
                ], 404);
            }

            $hotelRoom->update($data);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Hotel room updated']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('HotelRoomController@update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while updating.']]
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

    public function getItemOptions()
    {
        try {
            $item = null;
            $routes = [];

            if (request()->filled('item_uuid')) {
                $item = HotelRoom::findOrFail(request('item_uuid'));

                $routes = array_merge($routes, [
                    'update_route' => route('hotel-rooms.update', ['hotel_room' => request('item_uuid')]),
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
                'errors' => ['server' => ['Failed to fetch item details']]
            ], 500);
        }
    }

    public function bulkDelete()
    {
        try {
            DB::beginTransaction();

            $uuids = request('items');

            $items = HotelRoom::whereIn('uuid', $uuids)
                ->get();

            foreach ($items as $item) {

                //delete hotel room status
                if (method_exists($item, 'status')) {
                    $item->status()->delete();
                }

                $item->delete();
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected hotel room(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('HotelRoomController@bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected hotel room(s).']]
            ], 500);
        }
    }
}
