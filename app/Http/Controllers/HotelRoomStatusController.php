<?php

namespace App\Http\Controllers;

use App\Models\HotelRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

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
                $query->select('uuid', 'hotel_room_uuid','occupancy_status','housekeeping_status','guest_first_name','guest_last_name','arrival_date','departure_date');
            }])
            ->allowedSorts(['room_name'])
            ->defaultSort('room_name');


        $rooms = $query->paginate($perPage)->appends($request->query());

        logger($rooms);
        return response()->json($rooms);
    }

    public function store(Request $request)
    {
        $domain = (string) session('domain_uuid');

        $data = $request->validate([
            'hotel_room_uuid'     => ['required', 'uuid', 'exists:hotel_rooms,uuid'],
            'occupancy_status'    => ['nullable', 'string', 'in:vacant,reserved,occupied,ooo'],
            'housekeeping_status' => ['nullable', 'string', 'in:clean,dirty,inspected'],
            'guest_first_name'    => ['nullable', 'string', 'max:120'],
            'guest_last_name'     => ['nullable', 'string', 'max:120'],
            'arrival_date'        => ['nullable', 'date'],
            'departure_date'      => ['nullable', 'date','after_or_equal:arrival_date'],
        ]);

        try {
            DB::beginTransaction();

            $data['domain_uuid'] = $domain;

            // ensure one status per room per domain; upsert by (domain_uuid, hotel_room_uuid)
            $exists = DB::table('hotel_room_status')
                ->where('domain_uuid', $domain)
                ->where('hotel_room_uuid', $data['hotel_room_uuid'])
                ->first();

            if ($exists) {
                DB::table('hotel_room_status')
                    ->where('uuid', $exists->uuid)
                    ->update(array_merge($data, ['updated_at' => now()]));
            } else {
                DB::table('hotel_room_status')->insert(array_merge($data, [
                    'uuid' => DB::raw('uuid_generate_v4()'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            DB::commit();
            return response()->json(['messages' => ['success' => ['Room status saved']]], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('HotelRoomStatusController@store error: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            return response()->json(['messages' => ['error' => ['Failed to save room status']]], 500);
        }
    }

    public function update(Request $request, string $uuid)
    {
        $data = $request->validate([
            'occupancy_status'    => ['nullable', 'string', 'in:vacant,reserved,occupied,ooo'],
            'housekeeping_status' => ['nullable', 'string', 'in:clean,dirty,inspected'],
            'guest_first_name'    => ['nullable', 'string', 'max:120'],
            'guest_last_name'     => ['nullable', 'string', 'max:120'],
            'arrival_date'        => ['nullable', 'date'],
            'departure_date'      => ['nullable', 'date','after_or_equal:arrival_date'],
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
            logger('HotelRoomStatusController@update error: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            return response()->json(['messages' => ['error' => ['Failed to update room status']]], 500);
        }
    }

    public function getItemOptions()
    {
        try {
            $item = null;
            $routes = [];

            if (request()->filled('item_uuid')) {
                $item = DB::table('hotel_room_status')->where('uuid', request('item_uuid'))->first();
                $routes = array_merge($routes, [
                    'update_route' => route('hotel-room-status.update', ['hotel_room_status' => request('item_uuid')]),
                ]);
            }

            $currentDomain = (string) session('domain_uuid');

            // Rooms in this domain (for dropdown)
            $rooms = QueryBuilder::for(DB::table('hotel_rooms')->where('domain_uuid', $currentDomain))
                ->select(['uuid', 'room_name'])
                ->defaultSort('room_name')
                ->get()
                ->map(fn($r) => ['value' => $r->uuid, 'label' => $r->room_name]);

            $routes = array_merge($routes, [
                'store_route' => route('hotel-room-status.store'),
            ]);

            return response()->json([
                'item' => $item,
                'rooms' => $rooms,
                'routes' => $routes,
            ]);
        } catch (\Throwable $e) {
            logger('HotelRoomStatusController@getItemOptions '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
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

            $uuids = (array) request('items', []);
            DB::table('hotel_room_status')->whereIn('uuid', $uuids)->delete();

            DB::commit();
            return response()->json([
                'messages' => ['success' => ['Selected room status record(s) deleted.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('HotelRoomStatusController@bulkDelete error: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected record(s).']]
            ], 500);
        }
    }
}
