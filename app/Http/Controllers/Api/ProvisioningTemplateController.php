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
                    $q->where(function ($qq) use ($value) {
                        $qq->where('domain_uuid', $value)
                           ->orWhereNull('domain_uuid');
                    });
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

        // logger($templates);

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
                'messages' => ['success' => ['New provisioning template created']]
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
                    'messages' => ['error' => ['Provisioning template not found.']]
                ], 404);
            }

            $ProvisioningTemplate->update($data);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Provisioning template updated']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('ProvisioningTemplate update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

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
                $item = ProvisioningTemplate::findOrFail(request('item_uuid'));

            }

            $defaultTemplates = QueryBuilder::for(ProvisioningTemplate::query())
                ->select([
                    'template_uuid',
                    'name',
                ])
                ->where('type','default')
                ->defaultSort('name')
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->template_uuid,
                        'name' => $item->name,
                    ];
                });

                $routes = array_merge($routes, [
                    'template_content' => route('provisioning-templates.content'),    
                ]);

                // logger($defaultTemplates);
            return response()->json([
                'item' => $item,
                'default_templates' => $defaultTemplates,
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

    public function getTemplateContent()
    {
        try {
            $uuid = request('template_uuid'); // ✅ correct key
            $item = $uuid ? ProvisioningTemplate::findOrFail($uuid) : null;

            return response()->json([
                'item' => $item ?? null,
            ]);
        } catch (\Throwable $e) {
            logger('getTemplateContent@ProvisioningTemplateController: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to fetch template content']]
            ], 500);
        }
    }


    public function bulkDelete()
    {
        try {
            DB::beginTransaction();

            $uuids = request('items');

            $items = ProvisioningTemplate::whereIn('template_uuid', $uuids)
                ->get();

            foreach ($items as $item) {
                $item->delete();
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected provisioning template(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('ProvisioningTemplate bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected provisioning template(s).']]
            ], 500);
        }
    }
}
