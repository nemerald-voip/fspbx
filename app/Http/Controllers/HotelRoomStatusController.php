<?php

namespace App\Http\Controllers;

use App\Models\HotelRoom;
use Illuminate\Http\Request;
use App\Models\HotelRoomStatus;
use App\Services\HotelRoomService;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\HotelHousekeepingDefinition;
use App\Http\Requests\HotelRoomCheckInRequest;

class HotelRoomStatusController extends Controller
{
    public function index(Request $request)
    {

        $perPage = (int) $request->input('per_page', 50);

        $query = QueryBuilder::for(HotelRoom::query())
            ->select([
                'uuid',
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
            ->with(['status' => function ($query) {
                $query->select('uuid', 'hotel_room_uuid', 'occupancy_status', 'housekeeping_status', 'guest_first_name', 'guest_last_name', 'arrival_date', 'departure_date');
            }])
            ->allowedSorts(['room_name'])
            ->defaultSort('room_name');


        $rooms = $query->paginate($perPage)->appends($request->query());

        // logger($rooms);
        return response()->json($rooms);
    }

    public function store(HotelRoomCheckInRequest $request, HotelRoomService $service)
    {
        $payload = $request->validated();

        $room = HotelRoom::where('uuid', $payload['uuid'])->firstOrFail();
    
        try {
            $service->checkIn($room, $payload);
    
            return response()->json([
                'messages' => ['success' => ['Guest checked in']],
            ], 201);
        } catch (\Throwable $e) {
            logger('HotelRoomStatusController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'messages' => ['error' => ['Failed to check in guest']],
            ], 500);
        }
    }

    public function update(Request $request, string $uuid)
    {
        $data = $request->validate([
            'occupancy_status'    => ['nullable', 'string', 'in:vacant,reserved,occupied,ooo'],
            'housekeeping_status' => ['nullable', 'numeric', 'in:clean,dirty,inspected'],
            'guest_first_name'    => ['nullable', 'string', 'max:120'],
            'guest_last_name'     => ['nullable', 'string', 'max:120'],
            'arrival_date'        => ['nullable', 'date'],
            'departure_date'      => ['nullable', 'date', 'after_or_equal:arrival_date'],
        ]);

        try {
            DB::beginTransaction();

            $row = DB::table('hotel_room_status')->where('uuid', $uuid)->first();
            if (!$row) {
                return response()->json(['messages' => ['error' => ['Room status not found.']]], 404);
            }

            DB::table('hotel_room_status')->where('uuid', $uuid)
                ->update(array_merge($data, ['updated_at' => now()]));

            DB::commit();
            return response()->json(['messages' => ['success' => ['Room status updated']]]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('HotelRoomStatusController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json(['messages' => ['error' => ['Failed to update room status']]], 500);
        }
    }

    public function getItemOptions()
    {
        try {
            $item = null;
            $itemUuid = request('item_uuid') ?? null;
            $routes = [];

            if ($itemUuid) {
                $item =  QueryBuilder::for(HotelRoom::query())
                    ->select([
                        'uuid',
                        'room_name',
                    ])
                    ->with(['status' => function ($query) {
                        $query->select('uuid', 'hotel_room_uuid', 'occupancy_status', 'housekeeping_status', 'guest_first_name', 'guest_last_name', 'arrival_date', 'departure_date');
                    }])
                    ->whereKey($itemUuid)
                    ->firstOrFail();

                $routes = array_merge($routes, [
                    'update_route' => route('hotel-room-status.update', ['hotel_room_status' => request('item_uuid')]),
                ]);
            }

            $currentDomain = (string) session('domain_uuid');

            $defaultHousekeepingOptions = QueryBuilder::for(HotelHousekeepingDefinition::query())
                ->enabled()->globalOnly()
                ->defaultSort('code')
                ->get(['code', 'label'])
                ->map(function ($option) {
                    return [
                        'value' => $option->code,
                        'label' => $option->label,
                    ];
                });

            $routes = array_merge($routes, [
                'store_route' => route('hotel-room-status.store'),
            ]);

            return response()->json([
                'item' => $item,
                'housekeeping_options' => $defaultHousekeepingOptions,
                'routes' => $routes,
            ]);
        } catch (\Throwable $e) {
            logger('HotelRoomStatusController@getItemOptions ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to fetch item details']]
            ], 500);
        }
    }

    public function delete(Request $request, HotelRoomService $service)
    {
        $data = $request->validate([
            'uuid' => ['required', 'uuid', 'exists:hotel_rooms,uuid'],
        ]);

        $room = HotelRoom::query()
            ->where('uuid', $data['uuid'])
            ->firstOrFail();

        $service->checkOut($room);

        return response()->json([
            'messages' => ['success' => ['Guest checked out']],
        ], 201);
    }
}
