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

class HotelRoomController extends Controller
{

    public function index(Request $request)
    {
        $perPage    = (int) $request->input('per_page', 50);

        $query = QueryBuilder::for(HotelRoom::query())
            ->select([
                'uuid',
                'extension_uuid',
                'room_name',
            ])
            ->allowedFilters([
                AllowedFilter::callback('domain_uuid', function ($q, $value) {
                    $q->where('domain_uuid', $value);
                }),
                AllowedFilter::callback('search', function ($q, $value) {
                    if ($value === null || $value === '') return;
                    $q->where(function ($qq) use ($value) {
                        $qq->where('room_name', 'ILIKE', "%{$value}%");
                    });
                }),
            ])
            ->with(['extension' => function ($query) {
                $query->select('extension_uuid', 'extension');
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

            $extensions = QueryBuilder::for(Extensions::class)
                // only extensions in the current domain
                ->where('domain_uuid', $currentDomain)
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
