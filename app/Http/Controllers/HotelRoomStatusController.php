<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\HotelRoomService;
use App\Services\HotelPortalAccess;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Models\HotelHousekeepingDefinition;
use App\Http\Requests\HotelRoomCheckInRequest;
use App\Http\Requests\HotelRoomItemOptionsRequest;
use App\Http\Requests\HotelRoomSelectionRequest;

class HotelRoomStatusController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(HotelPortalAccess::allows('hotel_room_status_list_view'), 403);
        $perPage = (int) $request->input('per_page', 50);

        $query = QueryBuilder::for(HotelPortalAccess::rooms())
            ->select([
                'uuid',
                'room_name',
            ])
            ->allowedFilters([
                // Accepted for compatibility with older portal builds.
                AllowedFilter::callback('domain_uuid', static fn ($query) => $query),
                AllowedFilter::callback('search', function ($q, $value) {
                    if ($value === null || $value === '') return;
                    $q->where(function ($qq) use ($value) {
                        $qq->where('room_name', 'ILIKE', "%{$value}%");
                    });
                }),
            ])
            ->with(['status' => function ($query) {
                $query->where('domain_uuid', session('domain_uuid'))
                    ->select('uuid', 'domain_uuid', 'hotel_room_uuid', 'occupancy_status', 'housekeeping_status', 'guest_first_name', 'guest_last_name', 'arrival_date', 'departure_date')
                    ->with(['housekeepingDefinition' => fn ($definition) => $definition
                        ->where(fn ($owners) => $owners->whereNull('domain_uuid')->orWhere('domain_uuid', session('domain_uuid')))
                        ->select('uuid', 'code', 'label')]);
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

        $room = HotelPortalAccess::rooms()->findOrFail($payload['uuid']);
    
        try {
            $service->checkIn($room, $payload);
    
            return response()->json([
                'messages' => ['success' => [__('Guest checked in')]],
            ], 201);
        } catch (\Throwable $e) {
            logger('HotelRoomStatusController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'messages' => ['error' => [__('Failed to check in guest')]],
            ], 500);
        }
    }

    public function getItemOptions(HotelRoomItemOptionsRequest $request)
    {
        $itemUuid = $request->validated('item_uuid');
        $currentDomain = session('domain_uuid');
        $item = $itemUuid ? HotelPortalAccess::rooms()
            ->with(['status' => fn ($query) => $query->where('domain_uuid', $currentDomain)
                ->select('uuid', 'domain_uuid', 'hotel_room_uuid', 'occupancy_status', 'housekeeping_status', 'guest_first_name', 'guest_last_name', 'arrival_date', 'departure_date')])
            ->findOrFail($itemUuid, ['uuid', 'room_name']) : null;
        try {
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

            return response()->json([
                'item' => $item,
                'housekeeping_options' => $defaultHousekeepingOptions,
                'routes' => ['store_route' => route('hotel-room-status.store')],
            ]);
        } catch (\Throwable $e) {
            logger('HotelRoomStatusController@getItemOptions ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to fetch item details')]]
            ], 500);
        }
    }

    public function bulkDelete(HotelRoomSelectionRequest $request, HotelRoomService $service)
    {
        $rooms = HotelPortalAccess::selectedRooms($request->validated('items'));
        try {
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
                        trans_choice('{1} Checkout processed: :count room. Already vacant: :vacant.|[0,*] Checkout processed: :count rooms. Already vacant: :vacant.', $checkedOut, ['vacant' => $alreadyVacant])
                    ]
                ],
            ]);
        } catch (\Throwable $e) {
            logger('HotelRoomController@bulkDelete error: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            return response()->json([
                'messages' => ['error' => [__('An error occurred while checking out.')]]
            ], 500);
        }
    }
    
}
