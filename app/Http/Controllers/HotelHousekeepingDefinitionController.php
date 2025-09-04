<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use App\Models\HotelHousekeepingDefinition;

class HotelHousekeepingDefinitionController extends Controller
{
    public function store(Request $request)
    {
        // logger($request->all());
        $domain = (string) session('domain_uuid');

        // Payload must be present; can be an empty array to "clear all"
        $validated = $request->validate([
            'housekeeping_options' => ['present', 'array'],
            'housekeeping_options.*.code'   => ['required', 'integer', 'between:0,99'],
            'housekeeping_options.*.label'  => ['required', 'string', 'max:64'],
            'housekeeping_options.*.enabled' => ['sometimes', 'boolean'],
        ]);

        // Block duplicate codes within the same payload
        $codes = array_map(fn($o) => (int)$o['code'], $validated['housekeeping_options']);
        $dupCodes = array_values(array_unique(array_diff_assoc($codes, array_unique($codes))));
        if (!empty($dupCodes)) {
            return response()->json([
                'messages' => ['error' => ['Duplicate housekeeping codes in payload: ' . implode(', ', $dupCodes)]],
            ], 422);
        }

        try {
            DB::beginTransaction();

            // 1) Delete all existing domain records
            HotelHousekeepingDefinition::query()
                ->where('domain_uuid', $domain)
                ->delete();

            // 2) Insert new set (if any)
            $saved = [];
            if (!empty($validated['housekeeping_options'])) {
                $now = now();
                $rows = [];

                foreach ($validated['housekeeping_options'] as $opt) {
                    $rows[] = [
                        'uuid'        => (string) Str::uuid(),
                        'domain_uuid' => $domain,
                        'code'        => (int) $opt['code'],
                        'label'       => $opt['label'],
                        'enabled'     => $opt['enabled'] ?? true,
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ];
                    $saved[] = [
                        'code'    => (int) $opt['code'],
                        'label'   => $opt['label'],
                        'enabled' => (bool) ($opt['enabled'] ?? true),
                    ];
                }

                // Bulk insert via Eloquent (no raw SQL)
                HotelHousekeepingDefinition::insert($rows);

                // Normalize output order by code
                usort($saved, fn($a, $b) => $a['code'] <=> $b['code']);
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Housekeeping codes updated successfully']],
                'items'    => $saved, // empty if you cleared all
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('HotelHousekeepingDefinitionController@store error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while saving housekeeping codes.']],
            ], 500);
        }
    }



    public function getItemOptions()
    {
        try {
            $routes = [];

            $currentDomain = (string) session('domain_uuid');

            // Housekeeping options 
            $defaultHousekeepingOptions = QueryBuilder::for(HotelHousekeepingDefinition::query())
                ->enabled()->globalOnly()
                ->defaultSort('code')
                ->get(['code', 'label'])
                ->map(function ($option) {
                    return [
                        'value' => $option->label,
                        'label' => $option->label,
                    ];
                });

            $housekeepingOptions = QueryBuilder::for(HotelHousekeepingDefinition::query())
                ->enabled()->forDomain($currentDomain)
                ->defaultSort('code')
                ->get(['code', 'label']);


            $routes = array_merge($routes, [
                'store_route' => route('housekeeping.store'),
            ]);

            return response()->json([
                'housekeeping_options' => $housekeepingOptions, // [{code:int, label:string}]
                'default_housekeeping_options' => $defaultHousekeepingOptions,
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
}
