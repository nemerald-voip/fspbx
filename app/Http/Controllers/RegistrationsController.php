<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use App\Services\DeviceActionService;
use App\Services\FreeswitchEslService;
use Illuminate\Pagination\LengthAwarePaginator;

class RegistrationsController extends Controller
{

    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Registrations';
    protected $searchable = ['lan_ip','wan_ip', 'port', 'agent', 'transport', 'sip_profile_name', 'sip_auth_user', 'sip_auth_realm'];

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(FreeswitchEslService $eslService)
    {

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () use ($eslService) {
                    return $this->getData($eslService);
                },
                'showGlobal' => function () {
                    return request('filterData.showGlobal') === 'true';
                },

                'routes' => [
                    'current_page' => route('registrations.index'),
                    'select_all' => route('registrations.select.all'),
                    // 'bulk_delete' => route('messages.bulk.delete'),
                    // 'bulk_update' => route('messages.bulk.update'),
                    'action' => route('registrations.action'),
                ]
            ]
        );
    }


    /**
     *  Get data
     */
    public function getData(FreeswitchEslService $eslService, $paginate = 50)
    {
        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        // Check if showGlobal parameter is present and not empty
        if (!empty(request('filterData.showGlobal'))) {
            $this->filters['showGlobal'] = request('filterData.showGlobal') === 'true';
        } else {
            $this->filters['showGlobal'] = null;
        }
        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'sip_auth_user'); // Default to 'sip_auth_user'
        $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to ascending

        $data = $this->builder($this->filters, $eslService);

        // Apply pagination manually
        if ($paginate) {
            $data = $this->paginateCollection($data, $paginate);
        }

        // logger($data);

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [], FreeswitchEslService $eslService)
    {

        // get a list of current registrations
        $data = $eslService->getAllSipRegistrations();

        // Apply sorting using sortBy or sortByDesc depending on the sort order
        if ($this->sortOrder === 'asc') {
            $data = $data->sortBy($this->sortField);
        } else {
            $data = $data->sortByDesc($this->sortField);
        }

        // Check if showGlobal is set to true, otherwise filter by sip_auth_realm
        if (empty($filters['showGlobal']) || $filters['showGlobal'] !== true) {
            $domainName = session('domain_name');

            $data = $data->filter(function ($item) use ($domainName) {
                return $item['sip_auth_realm'] === $domainName;
            });
        }

        // Apply additional filters, if any
        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    // Pass the collection by reference to modify it directly
                    $data = $this->$method($data, $value);
                }
            }
        }

        // logger($data);

        return $data->values(); // Ensure re-indexing of the collection
    }

    /**
     * Paginate a given collection.
     *
     * @param \Illuminate\Support\Collection $items
     * @param int $perPage
     * @param int|null $page
     * @param array $options
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginateCollection($items, $perPage = 50, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage),
            $items->count(),
            $perPage,
            $page,
            $options
        );

        // Manually set the path to the current route with proper parameters
        $paginator->setPath(url()->current());

        return $paginator;
    }

    /**
     * @param $collection
     * @param $value
     * @return void
     */
    protected function filterSearch($collection, $value)
    {
        $searchable = $this->searchable;

        // Case-insensitive partial string search in the specified fields
        $collection = $collection->filter(function ($item) use ($value, $searchable) {
            foreach ($searchable as $field) {
                if (stripos($item[$field], $value) !== false) {
                    return true;
                }
            }
            return false;
        });

        return $collection;
    }


 public function handleAction(DeviceActionService $deviceActionService, FreeswitchEslService $eslService)
{
    try {
        $action = request('action');
//        logger("handleAction triggered: action={$action}");

        foreach (request('regs') as $reg) {
            $profile = (string)($reg['sip_profile_name'] ?? '');
            $user    = (string)($reg['sip_auth_user'] ?? '');
            $realm   = (string)($reg['sip_auth_realm'] ?? '');
            $target  = ($user && $realm) ? "{$user}@{$realm}" : '';

//            logger("Processing: profile={$profile}, user={$user}, realm={$realm}, target={$target}");

            if ($action === 'unregister' && $target && $profile) {
                // Use native FreeSWITCH unregister always (no vendor check)
                $commandsToTry = [
                    "sofia profile {$profile} flush_inbound_reg {$target} reboot",
                    "sofia profile {$profile} flush_inbound_reg {$target} all reboot",
                    "sofia profile {$profile} unregister {$user} {$realm}"
                ];

                $success = false;
                foreach ($commandsToTry as $cmd) {
                    try {
//                        logger("Executing FS command: {$cmd}");
                        $result = $eslService->executeCommand($cmd, false); // use executeCommand()
//                        logger("Result: " . (is_scalar($result) ? $result : json_encode($result)));

                        // Detect success by '+OK' pattern or non-error XML
                        if (is_string($result) && preg_match('/\+?OK/i', $result)) {
//                            logger("Command succeeded: {$cmd}");
                            $success = true;
                            break;
                        }
                    } catch (\Throwable $t) {
//                        logger("Command failed: {$cmd} => " . $t->getMessage());
                    }
                }

                if (!$success) {
//                    logger("All native FS commands failed, falling back to DeviceActionService");
                    $deviceActionService->handleDeviceAction($reg, $action);
                }
            } else {
                // Everything else uses existing logic
                $deviceActionService->handleDeviceAction($reg, $action);
            }
        }

        // Disconnect once at the end (cleanup)
        $eslService->disconnect();

        return response()->json([
            'messages' => ['success' => ['Request successfully processed']]
        ], 201);

    } catch (\Exception $e) {
        logger("handleAction Exception: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
        return response()->json([
            'success' => false,
            'errors'  => ['server' => [$e->getMessage()]]
        ], 500);
    }
}


    /**
     * Get all items
     *
     * @return \Illuminate\Http\Response
     */
    public function selectAll(FreeswitchEslService $eslService)
    {
        try {

            // Check if search parameter is present and not empty
            if (!empty(request('search'))) {
                $this->filters['search'] = request('search');
            }

            // Check if showGlobal parameter is present and not empty
            if (!empty(request('showGlobal'))) {
                $this->filters['showGlobal'] = request('showGlobal');
            } else {
                $this->filters['showGlobal'] = null;
            }

            // Fetch all registrations without pagination
            $allRegistrations = $this->builder($this->filters, $eslService);

            logger($allRegistrations);
    
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $allRegistrations,  // Returning full row instead of just call_id
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
    
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

}
