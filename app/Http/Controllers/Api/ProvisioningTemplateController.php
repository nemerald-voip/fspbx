<?php

namespace App\Http\Controllers\Api;

use App\Models\DeviceVendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\ProvisioningTemplate;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Http\Requests\StoreProvisioningTemplateRequest;
use App\Http\Requests\UpdateProvisioningTemplateRequest;
use App\Http\Requests\BulkDeleteProvisioningTemplateRequest;
use App\Http\Requests\ProvisioningTemplateContentRequest;
use App\Http\Requests\ProvisioningTemplateItemOptionsRequest;
use App\Services\ProvisioningTemplateAccessService;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ProvisioningTemplateController extends Controller
{
    public function __construct(private readonly ProvisioningTemplateAccessService $access)
    {
        $this->middleware(function ($request, $next) {
            $this->access->currentDomain();
            abort_unless(userCheckPermission('provisioning_templates_view'), 403);
            $action = match ($request->route()->getActionMethod()) {
                'store' => 'create',
                'update' => 'update',
                'bulkDelete' => 'delete',
                default => null,
            };
            if ($action) {
                abort_unless(userCheckPermission('provisioning_templates_'.$action), 403);
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $perPage    = (int) $request->input('per_page', 50);

        $query = QueryBuilder::for($this->access->visibleQuery())
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
        $templates->getCollection()->each(function ($template) {
            $template->can_update = $this->access->canModify($template, 'update');
            $template->can_delete = $this->access->canModify($template, 'delete');
        });

        // logger($templates);

        return response()->json($templates);
    }

    public function store(StoreProvisioningTemplateRequest $request)
    {
        $data = $request->safe()->except('global');

        try {
            DB::beginTransaction();

            $ProvisioningTemplate = ProvisioningTemplate::create($data);

            DB::commit();

            return response()->json([
                'item' => [
                    'template_uuid' => $ProvisioningTemplate->template_uuid,
                ],
                'routes' => [
                    'update_route' => route('provisioning-templates.update', [
                        'provisioning_template' => $ProvisioningTemplate->template_uuid,
                    ]),
                ],
                'messages' => ['success' => [__('New provisioning template created')]]
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            logger('ProvisioningTemplate store error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('Something went wrong while saving.')]]
            ], 500);
        }
    }

    public function update(UpdateProvisioningTemplateRequest $request, ProvisioningTemplate $provisioning_template)
    {
        $data = $request->safe()->except('global');

        try {
            $provisioning_template->update($data);

            return response()->json([
                'messages' => ['success' => [__('Provisioning template updated')]]
            ]);
        } catch (\Throwable $e) {
            logger('ProvisioningTemplate update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('Something went wrong while updating.')]]
            ], 500);
        }
    }

    public function getItemOptions(ProvisioningTemplateItemOptionsRequest $request)
    {
        $uuid = $request->validated('item_uuid');
        $item = $uuid ? $this->access->visibleQuery()->findOrFail($uuid) : null;

        try {
            $routes = [];

            if ($item && $this->access->canModify($item, 'update')) {
                $routes['update_route'] = route('provisioning-templates.update', [
                    'provisioning_template' => $item->template_uuid,
                ]);
            } elseif (! $item) {
                $routes['store_route'] = route('provisioning-templates.store');
            }

            $defaultTemplates = QueryBuilder::for(ProvisioningTemplate::query())
                ->select([
                    'template_uuid',
                    'name',
                    'vendor',
                ])
                ->where('type', 'default')
                ->whereNull('domain_uuid')
                ->defaultSort('vendor', 'name')   // vendor ASC, then name ASC
                ->get()
                ->groupBy('vendor')
                ->map(function ($items, $vendor) {
                    $vendorLabel = is_string($vendor) && $vendor !== '' ? ucfirst($vendor) : 'Other';

                    return [
                        'label' => $vendorLabel,
                        'items' => $items->map(function ($item) {
                            return [
                                'value'         => $item->template_uuid,
                                'label'         => $item->name,
                                'name'          => $item->name,
                                'template_name' => $item->name,
                                'vendor'        => $item->vendor,
                            ];
                        })->values()->all(),
                    ];
                })
                ->values()
                ->all();

            $routes['template_content'] = route('provisioning-templates.content');

            $vendors = QueryBuilder::for(DeviceVendor::query())
                ->select([
                    'device_vendor_uuid',
                    'name',
                ])
                ->where('enabled', 'true')
                ->defaultSort('name')  
                ->get()
                ->map(function ($item) {
                    return [
                        'value' => $item->name,
                        'label' => $item->name,
                        'name'  => $item->name,
                    ];
                });

            // logger($vendors);
            return response()->json([
                'item' => $item,
                'default_templates' => $defaultTemplates,
                'vendors' => $vendors,
                'routes' => $routes,
                'permissions' => $this->access->permissions(),
                'read_only' => $item ? ! $this->access->canModify($item, 'update') : false,
            ]);
        } catch (\Throwable $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to fetch item details')]]
            ], 500);
        }
    }

    public function getTemplateContent(ProvisioningTemplateContentRequest $request)
    {
        $item = $this->access->visibleQuery()->findOrFail($request->validated('template_uuid'));

        try {
            return response()->json([
                'item' => $item,
            ]);
        } catch (\Throwable $e) {
            logger('getTemplateContent@ProvisioningTemplateController: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to fetch template content')]]
            ], 500);
        }
    }


    public function bulkDelete(BulkDeleteProvisioningTemplateRequest $request)
    {
        $uuids = $request->validated('items');

        try {
            DB::beginTransaction();

            $deleted = $this->access->editableQuery()->whereKey($uuids)->delete();
            abort_unless($deleted === count($uuids), 404);

            DB::commit();

            return response()->json([
                'messages' => ['success' => [__('Selected provisioning template(s) were deleted successfully.')]]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            if ($e instanceof HttpExceptionInterface) {
                throw $e;
            }

            logger('ProvisioningTemplate bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [__('An error occurred while deleting the selected provisioning template(s).')]]
            ], 500);
        }
    }
}
