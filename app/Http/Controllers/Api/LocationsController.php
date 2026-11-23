<?php

namespace App\Http\Controllers\Api;

use App\Models\Location;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Http\Requests\BulkDeleteLocationRequest;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class LocationsController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->currentDomain();
            $permission = match ($request->route()->getActionMethod()) {
                'index' => 'location_view',
                'store' => 'location_create',
                'update' => 'location_update',
                'bulkDelete' => 'location_delete',
                default => null,
            };
            abort_unless($permission && userCheckPermission($permission), 403);

            return $next($request);
        });
    }

    private function currentDomain(): string
    {
        $domain = session('domain_uuid');
        abort_unless(is_string($domain) && Str::isUuid($domain), 403);

        return $domain;
    }

    public function index()
    {
        $domain_uuid = $this->currentDomain();
        abort_if(request()->has('domain_uuid') && request('domain_uuid') !== $domain_uuid, 403);

        $locations = Location::where('domain_uuid', $domain_uuid)
            ->orderBy('name')
            ->get();

        return response()->json($locations);
    }

    public function store(StoreLocationRequest $request)
    {
        $data = $request->validated();
        $data['domain_uuid'] = $this->currentDomain();

        try {
            DB::beginTransaction();

            $location = Location::create($data);

            DB::commit();

            return response()->json([
                'messages' => ['success' => [__('New location created')]]
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('Location store error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('Something went wrong while saving.')]]
            ], 500);
        }
    }

    public function update(UpdateLocationRequest $request, Location $location)
    {
        try {
            $location->update($request->safe()->except('domain_uuid'));

            return response()->json([
                'messages' => ['success' => [__('Location updated')]]
            ]);
        } catch (\Throwable $e) {
            logger('Location update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('Something went wrong while updating.')]]
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

    public function bulkDelete(BulkDeleteLocationRequest $request)
    {
        $uuids = $request->validated('items');

        try {
            DB::beginTransaction();

            $items = Location::where('domain_uuid', $this->currentDomain())
                ->whereKey($uuids)
                ->get();
            abort_unless($items->count() === count($uuids), 404);

            foreach ($items as $item) {
                $item->delete();
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => [__('Selected location(s) were deleted successfully.')]]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            logger('Location bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('An error occurred while deleting the selected location(s).')]]
            ], 500);
        }
    }
}
