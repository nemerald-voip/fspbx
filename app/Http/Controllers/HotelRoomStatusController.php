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
                $query->select('uuid', 'hotel_room_uuid', 'occupancy_status', 'housekeeping_status', 'guest_first_name', 'guest_last_name', 'arrival_date', 'departure_date')
                ->with('housekeepingDefinition:uuid,code,label');
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
                ->enabled()->forDomain($currentDomain)
                ->defaultSort('code')
                ->get(['uuid', 'label'])
                ->map(function ($option) {
                    return [
                        'value' => $option->uuid,
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

    public function bulkDelete(HotelRoomService $service)
    {
        try {
            $uuids = (array) request('items', []);
            if (empty($uuids)) {
                return response()->json([
                    'messages' => ['error' => ['No rooms selected.']]
                ], 422);
            }
    
            $rooms = HotelRoom::whereIn('uuid', $uuids)->get();
    
            $checkedOut = 0;
            $alreadyVacant = 0;
    
            foreach ($rooms as $room) {
                $deleted = $service->checkOut($room); // returns bool (deleted? true : false)
                if ($deleted) {
                    $checkedOut++;
                } else {
                    $alreadyVacant++;
                }
            }
    
            return response()->json([
                'messages' => [
                    'success' => [
                        sprintf(
                            'Checkout processed: %d room(s)%s.',
                            $checkedOut,
                            $alreadyVacant ? " ({$alreadyVacant} already vacant)" : ''
                        )
                    ]
                ],
            ]);
        } catch (\Throwable $e) {
            logger('HotelRoomController@bulkDelete error: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            return response()->json([
                'messages' => ['error' => ['An error occurred while checking out.']]
            ], 500);
        }
    }
    
}
