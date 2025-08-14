<?php

namespace App\Http\Controllers\Api;

use App\Models\Location;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;

class LocationsController extends Controller
{
    public function index()
    {
        $domain_uuid = request('domain_uuid');

        $locations = Location::where('domain_uuid', $domain_uuid)
            ->orderBy('name')
            ->get();

        return response()->json($locations);
    }

    public function store(StoreLocationRequest $request)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $location = Location::create($data);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['New location created']]
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('Location store error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while saving.']]
            ], 500);
        }
    }

    public function update(UpdateLocationRequest $request, $location_uuid)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $location = Location::find($location_uuid);
            if (!$location) {
                return response()->json([
                    'messages' => ['error' => ['Location not found.']]
                ], 404);
            }

            $location->update($data);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Location updated']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('Location update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while updating.']]
            ], 500);
        }
    }

    // public function getItemOptions()
    // {
    //     try {
    //         $domain_uuid = session('domain_uuid');

    //         $item = null;
    //         $updateRoute = null;

    //         if (request()->has('item_uuid')) {
    //             $item = Location::where('domain_uuid', $domain_uuid)
    //                 ->where('location_uuid', request('item_uuid'))
    //                 ->first();

    //             $updateRoute = $item
    //                 ? route('locations.update', $item->location_uuid)
    //                 : null;
    //         }

    //         // Optionally: add a list of assignable extensions/devices/etc.
    //         $extensions = Extensions::where('domain_uuid', $domain_uuid)
    //             ->select('extension_uuid', 'extension', 'effective_caller_id_name')
    //             ->orderBy('extension')
    //             ->get()
    //             ->map(function ($ext) {
    //                 return [
    //                     'value' => $ext->extension_uuid,
    //                     'name' => $ext->extension . ' - ' . $ext->effective_caller_id_name,
    //                 ];
    //             });

    //         return response()->json([
    //             'item' => $item,
    //             'extensions' => $extensions,
    //             'routes' => [
    //                 'update_route' => $updateRoute,
    //             ],
    //         ]);
    //     } catch (\Throwable $e) {
    //         logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

    //         return response()->json([
    //             'success' => false,
    //             'errors' => ['server' => ['Failed to fetch item details']]
    //         ], 500);
    //     }
    // }

    public function bulkDelete()
    {
        try {
            DB::beginTransaction();

            $uuids = request('items');

            $items = Location::whereIn('location_uuid', $uuids)
                ->get();

            foreach ($items as $item) {
                $item->delete();
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected location(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('Location bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected location(s).']]
            ], 500);
        }
    }
}
