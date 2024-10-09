<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\ProFeatures;
use App\Services\KeygenAPIService;
use Nwidart\Modules\Facades\Module;
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
     * @param  UpdatePhoneNumberRequest  $request
     * @param  Destinations  $phone_number
     * @return JsonResponse
     */
    public function update(UpdateProFeatureRequest $request, ProFeatures $pro_feature, KeygenApiService $keygenApiService)
    {

        if (!$pro_feature) {
            // If the model is not found, return an error response
            return response()->json([
                'success' => false,
                'errors' => ['model' => ['Model not found']]
            ], 404); // 404 Not Found if the model does not exist
        }

        try {
            $inputs = array_map(function ($value) {
                return $value === 'NULL' ? null : $value;
            }, $request->validated());

            // License validation
            $licenseKey = $inputs['license'] ?? $pro_feature->license;
            $licenseResponse = $keygenApiService->validateLicenseKey($licenseKey);

            if ($licenseResponse && $licenseResponse['meta']['valid'] === false && $licenseResponse['meta']['code'] === 'NO_MACHINE') {
                $machineCount = $licenseResponse['data']['attributes']['machines']['meta']['count'] ?? 0;
                $maxMachines = $licenseResponse['data']['attributes']['maxMachines'] ?? 1;

                if ($machineCount < $maxMachines) {
                    // Activate the machine if the machine count is less than max allowed machines
                    $licenseId = $licenseResponse['data']['id'];
                    $keygenApiService->activateMachine($licenseKey, $licenseId);
                } else {
                    return response()->json([
                        'success' => false,
                        'errors' => ['license' => ['Max machine limit reached']]
                    ], 422);
                }
            }


            $pro_feature->update($inputs);

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Request has been succesfully processed']]
            ], 201);
        } catch (\Exception $e) {
            logger($e);
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update this item']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }



    public function install(UpdateProFeatureRequest $request, ProFeatures $pro_feature, KeygenApiService $keygenApiService)
    {

        if (!$pro_feature) {
            // If the model is not found, return an error response
            return response()->json([
                'success' => false,
                'errors' => ['model' => ['Model not found']]
            ], 404); // 404 Not Found if the model does not exist
        }

        try {
            $inputs = array_map(function ($value) {
                return $value === 'NULL' ? null : $value;
            }, $request->validated());



            // License validation
            $licenseKey = $inputs['license'] ?? $pro_feature->license;
            $licenseResponse = $keygenApiService->validateLicenseKey($licenseKey);

            if ($licenseResponse && $licenseResponse['meta']['valid'] === true) {

                $releases = $keygenApiService->getReleases($licenseKey);

                if (!empty($releases)) {
                    $firstRelease = $releases[0];
                    $releaseVersion = $firstRelease['attributes']['version'];

                    // Format the artifact name
                    $artifactName = "fspbx-contact-module-{$releaseVersion}.tar.gz";

                    // Download the artifact using the version
                    $artifactContent = $keygenApiService->downloadArtifact($licenseKey, $releaseVersion, $artifactName);

                    if ($artifactContent) {
                        $this->saveAndExtract($artifactName, $artifactContent);

                        // Run the Artisan command to enable the ContactCenter module
                        Artisan::call('module:enable', ['module' => 'ContactCenter']);

                        // Run the module:seed command after extraction
                        Artisan::call('module:seed', ['module' => 'ContactCenter']);
                        
                        // Run the 'app:update' command using Symfony Process
                        // $process = new Process(['php', 'artisan', 'app:update']);
                        // $process->setWorkingDirectory(base_path());  // Set the Laravel root directory
                        // $process->run();

                        // // Log the output of the command
                        // if ($process->isSuccessful()) {
                        //     logger('App update completed: ' . $process->getOutput());
                        // } else {
                        //     logger('App update failed. Error: ' . $process->getErrorOutput());
                        //     logger('App update failed. Full output: ' . $process->getOutput());
                        // }
                    } else {
                        throw new \Exception("Unable to download the file");
                    }
                }
            }


            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Request has been succesfully processed']]
            ], 201);
        } catch (\Exception $e) {
            logger($e);
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to install this module']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    public function uninstall(UpdateProFeatureRequest $request, ProFeatures $pro_feature, KeygenApiService $keygenApiService)
    {

        if (!$pro_feature) {
            // If the model is not found, return an error response
            return response()->json([
                'success' => false,
                'errors' => ['model' => ['Model not found']]
            ], 404); // 404 Not Found if the model does not exist
        }

        try {
            $inputs = array_map(function ($value) {
                return $value === 'NULL' ? null : $value;
            }, $request->validated());

            logger($inputs);
            Module::disable('ContactCenter');
            Module::delete('ContactCenter');

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Request has been succesfully processed']]
            ], 201);
        } catch (\Exception $e) {
            logger($e);
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to uninstall this module']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }


    public function getItemOptions(KeygenApiService $keygenApiService)
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
                    'name' => 'Downloads',
                    'icon' => 'CloudArrowDownIcon',
                    'slug' => 'downloads',
                ],
            ];


            $item =  $this->model::find($item_uuid)
                ->select(
                    'uuid',
                    'name',
                    'slug',
                    'license',

                )
                ->get()->first();

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

                // Check if the module is installed and enabled 
                if (Module::has('ContactCenter') && Module::collections()->has('ContactCenter')) {
                    $item->is_installed = true;
                } else {
                    $item->is_installed = false;
                }
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
                // 'releases' => $releases, 
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
     * @param  Destinations  $phone_number
     * @return RedirectResponse
     */
    public function destroy(ProFeatures $pro_feature, KeygenApiService $keygenApiService)
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

    public function saveAndExtract($artifactName, $artifactContent)
    {
        // Save the artifact to Modules directory
        $filePath = base_path("Modules/{$artifactName}");
        $extractPath = base_path("Modules/ContactCenter");

        // If the extract path exists, remove it and recreate it
        if (file_exists($extractPath)) {
            $this->deleteDirectory($extractPath);  // Delete existing directory
        }
        mkdir($extractPath, 0755, true);  // Recreate directory

        // Save the downloaded file
        file_put_contents($filePath, $artifactContent);

        // Remove existing .tar file if it exists
        $tarFile = str_replace('.gz', '', $filePath);
        if (file_exists($tarFile)) {
            unlink($tarFile);
        }
        // Extract the .tar.gz file
        $phar = new \PharData($filePath);
        $phar->decompress();  // Decompress the .gz file (removes .gz and creates .tar)

        // Now extract the .tar contents
        $phar = new \PharData($tarFile);
        $phar->extractTo($extractPath, null, true);  // Extract all files

        // Clean up by deleting the .tar and original .gz files
        unlink($filePath); // delete .tar.gz
        unlink($tarFile);  // delete .tar

        // Find the extracted directory dynamically
        $subDirs = glob($extractPath . '/*', GLOB_ONLYDIR);

        if (count($subDirs) > 0) {
            $extractedDir = $subDirs[0];  // The first (and likely only) subdirectory

            // Move each file from the extracted folder to the main ContactCenter directory
            $files = scandir($extractedDir);

            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    rename("{$extractedDir}/{$file}", "{$extractPath}/{$file}");
                }
            }

            // Delete the extracted directory
            rmdir($extractedDir);
        }
    }

    // Helper function to delete a directory and its contents
    private function deleteDirectory($dirPath)
    {
        if (!is_dir($dirPath)) {
            return;
        }

        $files = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($files as $file) {
            $filePath = "{$dirPath}/{$file}";
            is_dir($filePath) ? $this->deleteDirectory($filePath) : unlink($filePath);
        }
        rmdir($dirPath);
    }
}
