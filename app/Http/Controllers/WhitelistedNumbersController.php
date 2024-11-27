<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\JsonResponse;
use App\Models\WhitelistedNumbers;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use App\Http\Requests\StoreWhitelistNumberRequest;
use Symfony\Component\Process\Exception\ProcessFailedException;

class WhitelistedNumbersController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'WhitelistedNumbers';
    protected $searchable = ['number'];

    public function __construct()
    {
        $this->model = new WhitelistedNumbers();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },

                'routes' => [
                    'current_page' => route('whitelisted-numbers.index'),
                    'store' => route('whitelisted-numbers.store'),
                    'select_all' => route('firewall.select.all'),
                    // 'bulk_delete' => route('messages.bulk.delete'),
                    // 'bulk_update' => route('messages.bulk.update'),
                    // 'retry' => route('messages.retry'),
                ]
            ]
        );
    }


    /**
     *  Get data
     */
    public function getData($paginate = 50)
    {

        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'number'); // Default to 'number'
        $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to ascending

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {
        $data =  $this->model::query();
        $domainUuid = session('domain_uuid');
        $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);

        $data->select(
            'uuid',
            'domain_uuid',
            'number',
            'created_at'

        );

        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $this->$method($data, $value);
                }
            }
        }

        // Apply sorting
        $data->orderBy($this->sortField, $this->sortOrder);

        return $data;
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

    public function destroy(WhitelistedNumbers $whitelisted_number)
    {

        try {

            $whitelisted_number->delete();

            return redirect()->back()->with('message', ['server' => ['Item deleted']]);
        } catch (\Exception $e) {

            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return redirect()->back()->with('error', ['server' => ['Server returned an error while deleting this item']]);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWhitelistNumberRequest  $request
     * @return JsonResponse
     */
    public function store(StoreWhitelistNumberRequest $request)
    {
        try {
            $inputs = $request->validated();
            $this->model->fill($inputs);

            // Save the model instance to the database
            $this->model->save();

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['New item created']]
            ], 201);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage());

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create new item']]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Ensure that the specified chain exists, and create it if it doesn't.
     *
     * @param string $chain
     * @return void
     */
    protected function ensureChainExists($chain)
    {
        // Check if the chain already exists
        $checkChainProcess = new Process(['sudo', 'iptables', '-L', $chain]);
        $checkChainProcess->run();

        // If the chain does not exist, create it
        if (!$checkChainProcess->isSuccessful()) {
            $createChainProcess = new Process(['sudo', 'iptables', '-N', $chain]);
            $createChainProcess->run();

            if (!$createChainProcess->isSuccessful()) {
                throw new ProcessFailedException($createChainProcess);
            }

            // Insert the chain into the INPUT chain to ensure it's processed
            $insertChainProcess = new Process(['sudo', 'iptables', '-I', 'INPUT', '-j', $chain]);
            $insertChainProcess->run();

            if (!$insertChainProcess->isSuccessful()) {
                throw new ProcessFailedException($insertChainProcess);
            }
        }
    }

    /**
     * Get all items
     *
     * @return \Illuminate\Http\Response
     */
    public function selectAll()
    {
        try {
            $ips = [];

            // Get the full iptables output including all chains
            $process = new Process(['sudo', 'iptables', '-L', '-n']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();
            $lines = explode("\n", $output);

            foreach ($lines as $line) {
                // Check if the line contains a DROP or REJECT action
                if (strpos($line, 'DROP') !== false || strpos($line, 'REJECT') !== false) {
                    // Extract the source IP address (typically the 4th or 5th column)
                    $parts = preg_split('/\s+/', $line);
                    if (isset($parts[3]) && filter_var($parts[3], FILTER_VALIDATE_IP)) {
                        $ips[] = $parts[3];
                    }
                }
            }

            // Ensure uniqueness in case an IP is blocked in multiple chains
            $ips = array_unique($ips);

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $ips,
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500); // 500 Internal Server Error for any other errors
        }

        return response()->json([
            'success' => false,
            'errors' => ['server' => ['Failed to select all items']]
        ], 500); // 500 Internal Server Error for any other errors
    }
}
