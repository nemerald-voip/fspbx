<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\ProFeatures;
use App\Services\KeygenAPIService;
use Nwidart\Modules\Facades\Module;
use App\Services\ProFeaturesService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use App\Http\Requests\UpdateProFeatureRequest;

class ProFeaturesController extends Controller
{
    private $model;

    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'ProFeatures';
    protected $searchable = ['name'];

    public function __construct()
    {
        $this->model = new ProFeatures();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(KeygenAPIService $keygenApiService)
    {

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () use ($keygenApiService) {
                    return $this->getData($keygenApiService);
                },
                'showGlobal' => function () {
                    return request('filterData.showGlobal') === 'true';
                },

                'routes' => [
                    'current_page' => route('pro-features.index'),
                    // 'select_all' => route('active-calls.select.all'),
                    // 'bulk_delete' => route('messages.bulk.delete'),
                    // 'bulk_update' => route('messages.bulk.update'),
                    // 'action' => route('active-calls.action'),
                    'item_options' => route('pro-features.item.options')
                ]
            ]
        );
    }


    /**
     *  Get data
     */
    public function getData(KeygenAPIService $keygenApiService, $paginate = 50)
    {
        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'created_at'); // Default to 'created_at'
        $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to descending

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        // Check license validity
        $data->transform(function ($item) use ($keygenApiService) {
            if ($item->license) {
                $licenseData = $keygenApiService->validateLicenseKey($item->license);
                // Add license validity to the item
                if ($licenseData) {
                    $item->license_valid = $licenseData['meta']['code'];
                    $item->license_details = $licenseData; // Add more details as needed
                } else {
                    $item->license_valid = false;
                }
            }

            return $item;
        });


        // logger($data);

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {
        $data =  $this->model::query();

        $data->select(
            'uuid',
            'name',
            'slug',
            'license'
        );

        // Apply sorting
        $data->orderBy($this->sortField, $this->sortOrder);


        // Apply additional filters, if any
        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $this->$method($data, $value);
                }
            }
        }

        // logger($data);

        return $data;
    }


    /**
     * @param $query
     * @param $value
     * @return void
     */
    protected function filterSearch($query, $value)
    {
        $searchable = $this->searchable;
        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value, $searchable) {
            foreach ($searchable as $field) {
                $query->orWhere($field, 'ilike', '%' . $value . '%');
            }
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateProFeatureRequest  $request
     * @param  Destinations  $phone_number
     * @return JsonResponse
     */
    public function update(UpdateProFeatureRequest $request, ProFeatures $pro_feature, ProFeaturesService $service)
    {
        $result = $service->updateLicense($pro_feature, $request->validated());

        if (!empty($result['errors'])) {
            return response()->json([
                'success' => false,
                'errors' => ['license' => $result['errors']]
            ], 422);
        }

        return response()->json([
            'messages' => ['success' => ['Request has been successfully processed']]
        ], 201);
    }



    public function install(UpdateProFeatureRequest $request, ProFeatures $pro_feature, ProFeaturesService $service)
    {
        // if your UI lets them send a license in the same request, pass it:
        $inputs = $request->validated();
        $licenseOverride = $inputs['license'] ?? null;

        $result = $service->installModules($licenseOverride);

        if (!empty($result['errors'])) {
            return response()->json([
                'success' => false,
                'errors' => ['modules' => $result['errors']]
            ], 422);
        }

        return response()->json([
            'messages' => ['success' => ['Request has been successfully processed']],
            'details' => $result,
        ], 201);
    }


    public function uninstall(UpdateProFeatureRequest $request, ProFeatures $pro_feature, ProFeaturesService $service)
    {
        $result = $service->uninstallAllModules();

        if (!empty($result['errors'])) {
            return response()->json([
                'success' => false,
                'errors' => ['modules' => $result['errors']]
            ], 500);
        }

        return response()->json([
            'messages' => ['success' => ['All modules have been deleted']],
            'details' => $result,
        ], 201);
    }


    public function getItemOptions(KeygenAPIService $keygenApiService)
    {
        try {

            $item_uuid = request('item_uuid'); // Retrieve item_uuid from the request

            // Base navigation array without Greetings
            $navigation = [
                [
                    'name' => 'License',
                    'icon' => 'Cog6ToothIcon',
                    'slug' => 'license',
                ],
                [
                    'name' => 'Modules',
                    'icon' => 'CloudArrowDownIcon',
                    'slug' => 'modules',
                ],
            ];


            $item = $this->model::query()
                ->select('uuid', 'name', 'slug', 'license')
                ->where('uuid', $item_uuid)
                ->first();

            // If item doesn't exist throw an error
            if (!$item) {
                throw new \Exception("Failed to fetch item details. Item not found");
            }

            if ($item->license) {
                $licenseData = $keygenApiService->validateLicenseKey($item->license);
                // Add license validity to the item
                if ($licenseData) {
                    $item->license_valid = $licenseData['meta']['code'];
                    $item->license_details = $licenseData; // Add more details as needed
                } else {
                    $item->license_valid = false;
                }

                $allModules = collect(Module::all())->map(function ($module) {
                    return [
                        'name'    => $module->getName(),
                        'slug'    => $module->getLowerName(),
                        'enabled' => $module->isEnabled(),
                        'status'  => $module->isEnabled() ? 'enabled' : 'disabled',
                        'version' => method_exists($module, 'getVersion') ? $module->getVersion() : null,
                    ];
                })->values();
            }

            $routes = [];
            $routes = array_merge($routes, [
                'update_route' => route('pro-features.update', $item),
                'deactivate_route' => route('pro-features.destroy', $item),
                'activate_route' => route('pro-features.activate', $item),
                'install_route' => route('pro-features.install', $item),
                'uninstall_route' => route('pro-features.uninstall', $item),
            ]);

            // Construct the itemOptions object
            $itemOptions = [
                'navigation' => $navigation,
                'item' => $item,
                'modules' => $allModules ?? null,
                // 'permissions' => $permissions,
                'routes' => $routes,

            ];

            return $itemOptions;
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to fetch item details']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  ProFeatures  $pro_feature
     * @return RedirectResponse
     */
    public function destroy(ProFeatures $pro_feature, KeygenAPIService $keygenApiService)
    {
        try {
            // Step 1: Validate the license to retrieve available machines link
            $licenseKey = $pro_feature->license; // Assuming license is stored
            $licenseResponse = $keygenApiService->validateLicenseKey($licenseKey);

            if (!$licenseResponse || $licenseResponse['meta']['valid'] === false) {
                return response()->json(['errors' => ['license' => ['Invalid license or failed validation']]], 400);
            }

            // Step 2: Get machine link and fetch registered machines
            $machineLink = $licenseResponse['data']['relationships']['machines']['links']['related'];
            $machines = $keygenApiService->getMachinesByLicense($licenseKey, $machineLink);

            // Step 3: Get the machine's fingerprint and match with available machines
            $fingerprint = $keygenApiService->getMachineFingerprint();

            $machineId = null;
            foreach ($machines as $machine) {
                if ($machine['attributes']['fingerprint'] === $fingerprint) {
                    $machineId = $machine['id'];
                    break;
                }
            }

            if ($machineId) {
                // Step 4: Deactivate the matched machine
                $response = $keygenApiService->deactivateMachine($licenseKey, $machineId);
                if ($response) {
                    return response()->json(['messages' => ['success' => ['License deactivated successfully']]], 200);
                } else {
                    return response()->json(['errors' => ['machine' => ['Failed to deactivate license']]], 500);
                }
            } else {
                return response()->json(['errors' => ['machine' => ['No matching machine found for this license']]], 400);
            }
        } catch (\Exception $e) {
            // Log the error message
            logger($e);
            return redirect()->back()->with('error', ['server' => ['Server returned an error while deleting this item']]);
        }
    }

    /**
     * Get all item IDs without pagination
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function selectAll()
    {
        try {
            // Fetch all active calls without pagination
            $allCalls = $this->builder($this->filters);

            // Extract only the UUIDs from the collection
            $uuids = $allCalls->pluck('uuid');

            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $uuids,  // Returning only the UUIDs
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }
}
