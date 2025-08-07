<?php

namespace App\Http\Controllers\Api;

use App\Models\Location;
use App\Models\Extensions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

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

    public function store(Request $request)
    {
        $domain_uuid = session('domain_uuid');
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:255',
            'state'   => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $location = Location::create([
                'location_uuid' => Str::uuid(),
                'domain_uuid'   => $domain_uuid,
                'name'          => $validated['name'],
                'address'       => $validated['address'] ?? null,
                'city'          => $validated['city'] ?? null,
                'state'         => $validated['state'] ?? null,
                'country'       => $validated['country'] ?? null,
            ]);

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

    public function update(Request $request, $location_uuid)
    {
        $domain_uuid = session('domain_uuid');
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:255',
            'state'   => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            $location = Location::where('domain_uuid', $domain_uuid)
                ->where('location_uuid', $location_uuid)
                ->firstOrFail();

            $location->update([
                'name'    => $validated['name'],
                'address' => $validated['address'] ?? null,
                'city'    => $validated['city'] ?? null,
                'state'   => $validated['state'] ?? null,
                'country' => $validated['country'] ?? null,
            ]);

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

    public function getItemOptions()
    {
        try {
            $domain_uuid = session('domain_uuid');

            $item = null;
            $updateRoute = null;

            if (request()->has('item_uuid')) {
                $item = Location::where('domain_uuid', $domain_uuid)
                    ->where('location_uuid', request('item_uuid'))
                    ->first();

                $updateRoute = $item
                    ? route('locations.update', $item->location_uuid)
                    : null;
            }

            // Optionally: add a list of assignable extensions/devices/etc.
            $extensions = Extensions::where('domain_uuid', $domain_uuid)
                ->select('extension_uuid', 'extension', 'effective_caller_id_name')
                ->orderBy('extension')
                ->get()
                ->map(function ($ext) {
                    return [
                        'value' => $ext->extension_uuid,
                        'name' => $ext->extension . ' - ' . $ext->effective_caller_id_name,
                    ];
                });

            return response()->json([
                'item' => $item,
                'extensions' => $extensions,
                'routes' => [
                    'update_route' => $updateRoute,
                ],
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

            $domain_uuid = auth()->user()->domain_uuid ?? session('domain_uuid');
            $uuids = request('items');

            $items = Location::where('domain_uuid', $domain_uuid)
                ->whereIn('location_uuid', $uuids)
                ->get();

            foreach ($items as $item) {
                // Optionally: cleanup references in other tables here
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
