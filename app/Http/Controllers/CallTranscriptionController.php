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
use App\Models\CallTranscriptionProviderConfig;
use App\Services\CallTranscriptionConfigService;
use App\Http\Requests\StoreAssemblyAiConfigRequest;
use App\Http\Requests\StoreTranscriptionOptionsRequest;
use App\Services\CallTranscription\CallTranscriptionService;

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


    public function storeAssemblyAiConfig(StoreAssemblyAiConfigRequest $request)
    {
        $validated  = $request->validated();
        $domainUuid = $validated['domain_uuid'] ?? null;

        // Resolve provider_uuid for AssemblyAI (adjust the key/column if needed)
        $provider = CallTranscriptionProvider::where('key', 'assemblyai')->where('is_active', true)->first();

        if (!$provider) {
            return response()->json([
                'messages' => ['error' => ['AssemblyAI provider is not configured or inactive.']],
            ], 422);
        }

        // Build config blob (remove meta/scope keys)
        $config = Arr::except($validated, ['domain_uuid']);

        try {
            DB::beginTransaction();

            CallTranscriptionProviderConfig::updateOrCreate(
                [
                    'provider_uuid' => $provider->uuid,
                    'domain_uuid'   => $domainUuid,       // NULL = system scope
                ],
                [
                    'provider_uuid' => $provider->uuid,   // explicit on create
                    'domain_uuid'   => $domainUuid,
                    'config'        => $config,
                ]
            );

            DB::commit();

            // Invalidate cache for this scope so effective settings refresh
            app(CallTranscriptionConfigService::class)->invalidate($domainUuid);

            return response()->json([
                'messages' => ['success' => ['AssemblyAI options saved']],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('AssemblyAiController@storeAssemblyAiConfig error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while saving.']],
            ], 500);
        }
    }

    public function getAssemblyAiConfig(Request $request)
    {
        $data = $request->validate([
            'domain_uuid' => ['nullable', 'uuid'],
        ]);
        $domainUuid = $data['domain_uuid'] ?? null;

        // Find the provider row (active AssemblyAI)
        $provider = CallTranscriptionProvider::where('key', 'assemblyai')
            ->where('is_active', true)
            ->first();

        if (!$provider) {
            return response()->json([
                'messages' => ['error' => ['AssemblyAI provider is not configured or inactive.']],
            ], 422);
        }

        // Prefer domain row if requested & exists
        if ($domainUuid) {
            $domainCfg = CallTranscriptionProviderConfig::where('provider_uuid', $provider->uuid)
                ->where('domain_uuid', $domainUuid)
                ->first()
                ->toArray();

            if ($domainCfg) {
                // Return flat config fields + meta
                return response()->json(array_merge([
                    'scope'       => 'domain',
                    'domain_uuid' => $domainUuid,
                ], $domainCfg['config'] ?? []));
            }
        }

        // Fall back to system row (domain_uuid = NULL)
        $systemCfg = CallTranscriptionProviderConfig::where('provider_uuid', $provider->uuid)
            ->whereNull('domain_uuid')
            ->first()
            ->toArray();

        return response()->json(array_merge([
            'scope'       => 'system',
            'domain_uuid' => $domainUuid,
        ], $systemCfg['config'] ?? []));
    }

    /**
     * Start a transcription for a CDR recording.
     * Route: POST /call-detail-records/recordings/{uuid}/transcribe
     *
     * Query/body params (optional):
     * - domain_uuid: uuid|null  (scope to a domain, otherwise system)
     * - options: array           (provider-specific overrides, forwarded as-is)
     */
    public function transcribe(Request $request, CallTranscriptionService $transcriptionService)
    {
        // Validate optional inputs
        $data = $request->validate([
            'uuid'        => ['required', 'uuid'],
            'domain_uuid' => ['nullable', 'uuid'],
            'options'     => ['nullable', 'array'],
        ]);

        $uuid       = $data['uuid'];
        $domainUuid = $data['domain_uuid'] ?? null;
        $overrides  = $data['options'] ?? [];

        try {
            // Kick off transcription
            $result = $transcriptionService->transcribeCdr($uuid, $domainUuid, $overrides);

            // Example $result: ['id' => 'transcript_xxx', 'status' => 'queued', ...]
            return response()->json([
                'messages' => ['success' => ['Transcription request created.']],
                'data'     => $result,
            ], 201);
        } catch (\Exception $e) {
            // Business-rule errors (disabled policy, missing provider config, missing recording URL, etc.)
            logger("CallTranscriptionController@transcribe error: {$e->getMessage()}");

            return response()->json([
                'messages' => ['error' => ['Something went wrong while starting transcription.']],
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
