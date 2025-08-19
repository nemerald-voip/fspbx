<?php

namespace App\Http\Controllers\Api;

use App\Models\ProvisioningTemplate;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProvisioningTemplateRequest;
use App\Http\Requests\UpdateProvisioningTemplateRequest;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class ProvisioningTemplateController extends Controller
{
    public function index(Request $request)
    {
        $perPage    = (int) $request->input('per_page', 50);

        $query = QueryBuilder::for(ProvisioningTemplate::query())
            ->select([
                'template_uuid',
                'vendor',
                'name',
                'type',
                'version',
                'revision',
                'base_template',
                'base_version',
                'domain_uuid',
            ])
            ->allowedFilters([
                AllowedFilter::callback('domain_uuid', function ($q, $value) {
                    $q->where('domain_uuid', $value)
                        ->orWhereNull('domain_uuid');
                }),
                AllowedFilter::exact('type'),
                AllowedFilter::callback('search', function ($q, $value) {
                    if ($value === null || $value === '') return;
                    $q->where(function ($qq) use ($value) {
                        $qq->where('vendor', 'ILIKE', "%{$value}%")
                            ->orWhere('name', 'ILIKE', "%{$value}%")
                            ->orWhere('version', 'ILIKE', "%{$value}%")
                            ->orWhere('base_template', 'ILIKE', "%{$value}%")
                            ->orWhere('base_version', 'ILIKE', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['vendor', 'name', 'version', 'revision'])
            ->defaultSort('vendor', 'name');


        $templates = $query->paginate($perPage)->appends($request->query());

        logger($templates);

        return response()->json($templates);
    }

    public function store(StoreProvisioningTemplateRequest $request)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $ProvisioningTemplate = ProvisioningTemplate::create($data);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['New ProvisioningTemplate created']]
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('ProvisioningTemplate store error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['Something went wrong while saving.']]
            ], 500);
        }
    }

    public function update(UpdateProvisioningTemplateRequest $request, $ProvisioningTemplate_uuid)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $ProvisioningTemplate = ProvisioningTemplate::find($ProvisioningTemplate_uuid);
            if (!$ProvisioningTemplate) {
                return response()->json([
                    'messages' => ['error' => ['ProvisioningTemplate not found.']]
                ], 404);
            }

            $ProvisioningTemplate->update($data);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['ProvisioningTemplate updated']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('ProvisioningTemplate update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

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
    //             $item = ProvisioningTemplate::where('domain_uuid', $domain_uuid)
    //                 ->where('ProvisioningTemplate_uuid', request('item_uuid'))
    //                 ->first();

    //             $updateRoute = $item
    //                 ? route('ProvisioningTemplates.update', $item->ProvisioningTemplate_uuid)
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

            $items = ProvisioningTemplate::whereIn('ProvisioningTemplate_uuid', $uuids)
                ->get();

            foreach ($items as $item) {
                $item->delete();
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected ProvisioningTemplate(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('ProvisioningTemplate bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected ProvisioningTemplate(s).']]
            ], 500);
        }
    }
}
