<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\EventGuardLogs;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Symfony\Component\Process\Process;
use App\Http\Requests\StoreIpBlockRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\Process\Exception\ProcessFailedException;

class FirewallController extends Controller
{

    // public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Firewall';
    protected $searchable = ['hostname', 'ip', 'filter', 'extension', 'user_agent'];

    public function __construct()
    {
        // $this->model = new Messages();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (!userCheckPermission("firewall_list_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },

                'routes' => [
                    'current_page' => route('firewall.index'),
                    'unblock' => route('firewall.unblock'),
                    'block' => route('firewall.block'),
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
        $this->sortField = request()->get('sortField', 'ip'); // Default to 'created_at'
        $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to descending

        $data = $this->builder($this->filters);

        // Apply pagination manually
        if ($paginate) {
            $data = $this->paginateCollection($data, $paginate);
        }

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {

        // get a list of blocked IPs from iptables
        $data =  $this->getBlockedIps();

        // Apply sorting using sortBy or sortByDesc depending on the sort order
        if ($this->sortOrder === 'asc') {
            $data = $data->sortBy($this->sortField);
        } else {
            $data = $data->sortByDesc($this->sortField);
        }

        // Get a list of IPs blocked by Event Guard
        $eventGuardLogs = $this->getEventGuardLogs();

        $data = $this->combineEventGuardLogs($data, $eventGuardLogs);

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


    public function getBlockedIps()
    {
        $result = $this->getIptablesRules();

        $blockedIps = [];
        $currentChain = '';
        $lines = explode("\n", $result);

        $hostname = gethostname();

        foreach ($lines as $line) {
            // Detect the start of a new chain
            if (preg_match('/^Chain\s+(\S+)/', $line, $matches)) {
                $currentChain = $matches[1];
                continue;
            }

            // Check if the line contains a DROP or REJECT action
            if (strpos($line, 'DROP') !== false || strpos($line, 'REJECT') !== false) {
                // Split by whitespace
                $parts = preg_split('/\s+/', $line);

                // We expect index 4 because getIptablesRules uses --line-numbers
                // 0:num, 1:target, 2:prot, 3:opt, 4:source
                if (isset($parts[4])) {
                    $source = $parts[4];

                    // --- FIX START: Ignore generic 0.0.0.0/0 (Anywhere) rules ---
                    if ($source === '0.0.0.0/0') {
                        continue;
                    }
                    // --- FIX END ---

                    // Check if it is a valid IP OR a valid CIDR subnet
                    $isIp = filter_var($source, FILTER_VALIDATE_IP);
                    $isCidr = false;

                    if (!$isIp && strpos($source, '/') !== false) {
                        $cidrParts = explode('/', $source);
                        if (filter_var($cidrParts[0], FILTER_VALIDATE_IP)) {
                            $isCidr = true;
                        }
                    }

                    if ($isIp || $isCidr) {
                        $blockedIps[] = [
                            'uuid' => Str::uuid()->toString(),
                            'hostname' => $hostname,
                            'ip' => $source,
                            'extension' => null,
                            'user_agent' => null,
                            'filter' => $currentChain,
                            'status' => 'blocked',
                        ];
                    }
                }
            }
        }

        return collect($blockedIps)->unique();
    }

    public function getIptablesRules()
    {
        // Get the full iptables output including all chains
        $process = new Process(['sudo', 'iptables', '-L', '-n', '--line-numbers']);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();
        return $output;
    }

    public function getEventGuardLogs()
    {
        $logs = EventGuardLogs::select(
            'event_guard_log_uuid',
            'hostname',
            'log_date',
            'filter',
            'ip_address',
            'extension',
            'user_agent',
            'log_status'
        )
            ->get();

        return $logs;
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
     * Combine event guard logs with blocked IPs data
     *
     * @param  Collection  $data
     * @param  Collection  $eventGuardLogs
     * @return Collection
     */
    protected function combineEventGuardLogs($data, $eventGuardLogs)
    {
        // Group event guard logs by IP address for easy lookup
        $groupedLogs = $eventGuardLogs->groupBy('ip_address');

        // Add additional fields from event guard logs to the data array
        return $data->map(function ($item) use ($groupedLogs) {
            $ip = $item['ip'];
            if (isset($groupedLogs[$ip])) {
                $log = $groupedLogs[$ip]->first();
                $item['uuid'] = $log->event_guard_log_uuid;
                $item['extension'] = $log->extension;
                $item['user_agent'] = $log->user_agent;
                $item['date'] = $log->log_date_formatted;
                // Add any other fields you need here
            }
            return $item;
        });
    }


    public function destroy()
    {
        try {
            // Unblock the IPs in fail2ban
            foreach (request('items') as $ip) {
                $fail2banProcess = new Process(['sudo', 'fail2ban-client', 'unban', 'ip', $ip]);
                $fail2banProcess->run();

                if (!$fail2banProcess->isSuccessful()) {
                    // logger()->error("Failed to unban IP $ip in fail2ban: " . $fail2banProcess->getErrorOutput());
                    throw new ProcessFailedException($fail2banProcess);
                } else {
                    logger("IP $ip is succesfully unbanned in fail2ban");
                }
            }

            $result = $this->getIptablesRules();

            $lines = explode("\n", $result);
            $rulesToDelete = [];

            $currentChain = null;

            foreach ($lines as $line) {
                // Detect the start of a new chain
                if (preg_match('/^Chain\s+(\S+)/', $line, $matches)) {
                    $currentChain = $matches[1];
                    continue;
                }

                // Check each IP in the provided list
                foreach (request('items') as $ip) {
                    // Check if the line contains the IP address and a DROP/REJECT action
                    if (strpos($line, $ip) !== false && (strpos($line, 'DROP') !== false || strpos($line, 'REJECT') !== false)) {
                        // Extract the line number (first column)
                        $parts = preg_split('/\s+/', $line);
                        if (isset($parts[0]) && is_numeric($parts[0]) && $currentChain) {
                            $rulesToDelete[] = ['chain' => $currentChain, 'line' => $parts[0]];
                        }
                    }
                }
            }

            if (!empty($rulesToDelete)) {
                foreach ($rulesToDelete as $rule) {
                    // Delete the rule from the specified chain
                    $deleteProcess = new Process(['sudo', 'iptables', '-D', $rule['chain'], $rule['line']]);
                    $deleteProcess->run();

                    if (!$deleteProcess->isSuccessful()) {
                        throw new ProcessFailedException($deleteProcess);
                    } else {
                        logger("IP $ip is succesfully unbanned in iptables");
                    }
                }
            }

            // Delete corresponding EventGuardLogs entries
            EventGuardLogs::whereIn('ip_address', request('items'))->delete();

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Request to unblock IP addresses was successful']]
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . PHP_EOL);
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreIpBlockRequest  $request
     * @return JsonResponse
     */
    public function store(StoreIpBlockRequest $request): JsonResponse
    {
        try {
            $inputs = $request->validated();

            // Can be "192.168.1.50" OR "192.168.1.0/24"
            $ipOrSubnet = $inputs['ip_address'];

            // Ensure the chain exists
            $this->ensureChainExists('fs_pbx_deny_access');

            // Add the IP to the fs_pbx_deny_access chain
            $blockProcess = new Process(['sudo', 'iptables', '-A', 'fs_pbx_deny_access', '-s', $ipOrSubnet, '-j', 'DROP']);
            $blockProcess->run();

            if (!$blockProcess->isSuccessful()) {
                throw new ProcessFailedException($blockProcess);
            }

            // Save the current iptables rules
            $saveProcess = new Process(['sudo', 'iptables-save']);
            $saveProcess->run();

            if (!$saveProcess->isSuccessful()) {
                throw new ProcessFailedException($saveProcess);
            }

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

            // Note: This command usually does NOT include --line-numbers based on your previous snippet
            // So source is usually index 3 (Target, Prot, Opt, Source)
            $process = new Process(['sudo', 'iptables', '-L', '-n']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();
            $lines = explode("\n", $output);

            foreach ($lines as $line) {
                if (strpos($line, 'DROP') !== false || strpos($line, 'REJECT') !== false) {
                    $parts = preg_split('/\s+/', $line);

                    // Index 3 is Source in standard `iptables -L -n` output
                    if (isset($parts[3])) {
                        $source = $parts[3];

                        // --- FIX: Ignore 0.0.0.0/0 ---
                        if ($source === '0.0.0.0/0') {
                            continue;
                        }

                        // Validate IP or CIDR
                        $isIp = filter_var($source, FILTER_VALIDATE_IP);
                        $isCidr = (!$isIp && strpos($source, '/') !== false && filter_var(explode('/', $source)[0], FILTER_VALIDATE_IP));

                        if ($isIp || $isCidr) {
                            $ips[] = $source;
                        }
                    }
                }
            }

            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => array_unique($ips),
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500);
        }
    }
}
