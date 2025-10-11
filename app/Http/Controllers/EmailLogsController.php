<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Models\HotelRoom;
use App\Models\Extensions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Http\Requests\UpdateHotelRoomRequest;

class EmailLogsController extends Controller
{
    public function index(Request $request)
    {
        $params = request()->all();
        $params['paginate'] = 50;
        $domain_uuid = session('domain_uuid');
        $params['domain_uuid'] = $domain_uuid;

        if (!empty(request('filter.dateRange'))) {
            $startPeriod = Carbon::parse(request('filter.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filter.dateRange')[1])->setTimeZone('UTC');
        }

        $params['filter']['startPeriod'] = $startPeriod;
        $params['filter']['endPeriod'] = $endPeriod;

        unset(
            $params['filter']['dateRange'],
        );


        $query = QueryBuilder::for(EmailLog::class, request()->merge($params))
            ->select([
                'uuid',
                'from',
                'to',
                'cc',
                'bcc',
                'subject',
                'status',
                'created_at',
                'sent_debug_info'
            ])
            ->allowedFilters([
                AllowedFilter::callback('startPeriod', function ($query, $value) {
                    $query->where('created_at', '>=', $value);
                }),
                AllowedFilter::callback('endPeriod', function ($query, $value) {
                    $query->where('created_at', '<=', $value);
                }),
                AllowedFilter::callback('search', function ($q, $value) {
                    if ($value === null || $value === '') return;
                    $q->where(function ($qq) use ($value) {
                        $qq->where('to', 'ILIKE', "%{$value}%")
                            ->orWhere('cc', 'ILIKE', "%{$value}%")
                            ->orWhere('bcc', 'ILIKE', "%{$value}%")
                            ->orWhere('subject', 'ILIKE', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['created_at'])
            ->defaultSort('created_at');


        if ($params['paginate']) {
            $data = $query->paginate($params['paginate']);
        } else {
            $data = $query->cursor();
        }


        return response()->json($data);
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
