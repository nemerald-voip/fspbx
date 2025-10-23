<?php

namespace App\Http\Controllers;

use App\Models\HotelRoom;
use App\Models\Extensions;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use App\Models\CallTranscriptionPolicy;
use App\Models\CallTranscriptionProvider;
use App\Http\Requests\StoreHotelRoomRequest;
use App\Http\Requests\UpdateHotelRoomRequest;
use App\Services\CallTranscriptionConfigService;
use App\Http\Requests\BulkStoreHotelRoomsRequest;
use App\Http\Requests\StoreTranscriptionOptionsRequest;

class CallTranscriptionController extends Controller
{

    public function getProviders(Request $request)
    {

        $query = QueryBuilder::for(CallTranscriptionProvider::query())
            ->select([
                'uuid',
                'name',
            ])
            ->where('is_active', true)
            ->allowedSorts(['name'])
            ->defaultSort('name');

        $data = $query->get()->map(function ($item) {
            return [
                'value' => $item->uuid,
                'label' => $item->name,
            ];
        })->toArray();;

        return response()->json($data);
    }

    public function getPolicy(Request $request)
    {
        $data = $request->validate([
            'domain_uuid' => ['nullable', 'uuid'],
        ]);
        $domainUuid = $data['domain_uuid'] ?? null;

        $rows = CallTranscriptionPolicy::query()
            ->where(function ($q) use ($domainUuid) {
                $q->whereNull('domain_uuid');
                if (!empty($domainUuid)) {
                    $q->orWhere('domain_uuid', $domainUuid);
                }
            })
            ->get()
            ->keyBy(fn($r) => $r->domain_uuid === null ? 'system' : 'domain');

        $system = $rows->get('system'); // may be null
        $domain = $rows->get('domain'); // may be null

        // Effective: domain overrides if set; else system
        $enabled       = $domain?->enabled ?? ($system?->enabled ?? false);
        $providerUuid  = $domain?->provider_uuid ?? ($system?->provider_uuid ?? null);

        return response()->json([
            'scope'         => $domain ? 'domain' : 'system',
            'domain_uuid'   => $domainUuid,
            'enabled'       => (bool) $enabled,
            'provider_uuid' => $providerUuid,
        ]);
    }


    public function storePolicy(StoreTranscriptionOptionsRequest $request)
    {
        $data       = $request->validated();
        $domainUuid = $data['domain_uuid'] ?? null;

        try {
            DB::beginTransaction();

            // Upsert one row per scope (system = domain_uuid NULL)
            CallTranscriptionPolicy::updateOrCreate(
                ['domain_uuid' => $domainUuid],
                [
                    'enabled'       => (bool) $data['enabled'],
                    // In domain scope this may be null to inherit system provider
                    'provider_uuid' => $data['provider_uuid'] ?? null,
                ]
            );

            DB::commit();

            // Invalidate cached effective config for this scope
            app(CallTranscriptionConfigService::class)->invalidate($domainUuid);

            return response()->json([
                'messages' => ['success' => ['Call transcription options saved']],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('CallTranscriptionPolicyController@storePolicy error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while saving.']],
            ], 500);
        }
    }

    public function destroyPolicy(Request $request)
    {
        $data = $request->validate([
            'domain_uuid' => ['required', 'uuid'],
        ]);

        $domainUuid = $data['domain_uuid'];

        try {
            DB::beginTransaction();

            // Remove the domain override (if present)
            $deleted = CallTranscriptionPolicy::where('domain_uuid', $domainUuid)->delete();

            DB::commit();

            // Invalidate cached effective config for this domain
            app(CallTranscriptionConfigService::class)->invalidate($domainUuid);

            return response()->json([
                'messages' => ['success' => [
                    $deleted ? 'Reverted to defaults.' : 'No custom options found; already using defaults.'
                ]],
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger(
                'CallTranscriptionPolicyController@destroyPolicy error: ' .
                    $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine()
            );

            return response()->json([
                'messages' => ['error' => ['Something went wrong while reverting to defaults.']],
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
}
