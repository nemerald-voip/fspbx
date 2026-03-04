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
use Illuminate\Support\Carbon;

class FirewallController extends Controller
{
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Firewall';
    protected $searchable = ['hostname', 'ip', 'filter', 'extension', 'user_agent'];

    public function __construct()
    {
    }

    /**
     * Display a listing of the resource.
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
                ]
            ]
        );
    }

    /**
     * Get data.
     */
    public function getData($paginate = 50)
    {
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        $this->sortField = request()->get('sortField', 'ip');
        $this->sortOrder = request()->get('sortOrder', 'asc');

        $data = $this->builder($this->filters);

        if ($paginate) {
            $data = $this->paginateCollection($data, $paginate);
        }

        return $data;
    }

    /**
     * Build firewall data collection.
     */
    public function builder(array $filters = [])
    {
        // Current blocked IPs from iptables
        $data = $this->getBlockedIps();

        // Only query Event Guard rows for IPs that are actually blocked right now
        $blockedIps = $data->pluck('ip')->filter()->unique()->values()->all();

        // Get only the latest log row per blocked IP
        $eventGuardLogs = $this->getEventGuardLogs($blockedIps);

        // Merge db data into iptables data
        $data = $this->combineEventGuardLogs($data, $eventGuardLogs);

        // Apply filters after enrichment
        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $data = $this->$method($data, $value);
                }
            }
        }

        // Sort after merge/filter so UI sorts by final values
        if ($this->sortOrder === 'asc') {
            $data = $data->sortBy($this->sortField);
        } else {
            $data = $data->sortByDesc($this->sortField);
        }

        return $data->values();
    }

    /**
     * Case-insensitive search across selected fields.
     */
    protected function filterSearch($collection, $value)
    {
        $searchable = $this->searchable;

        return $collection->filter(function ($item) use ($value, $searchable) {
            foreach ($searchable as $field) {
                $fieldValue = data_get($item, $field);

                if ($fieldValue !== null && stripos((string) $fieldValue, $value) !== false) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Parse iptables output and return blocked IP/subnet list.
     */
    public function getBlockedIps()
    {
        $result = $this->getIptablesRules();

        $blockedIps = [];
        $currentChain = '';
        $lines = explode("\n", $result);
        $hostname = gethostname();

        foreach ($lines as $line) {
            if (preg_match('/^Chain\s+(\S+)/', $line, $matches)) {
                $currentChain = $matches[1];
                continue;
            }

            if (strpos($line, 'DROP') !== false || strpos($line, 'REJECT') !== false) {
                $parts = preg_split('/\s+/', trim($line));

                // With --line-numbers:
                // 0:num, 1:target, 2:prot, 3:opt, 4:source
                if (!isset($parts[4])) {
                    continue;
                }

                $source = $parts[4];

                // Ignore generic catch-all rules
                if ($source === '0.0.0.0/0') {
                    continue;
                }

                $isIp = filter_var($source, FILTER_VALIDATE_IP);
                $isCidr = false;

                if (!$isIp && strpos($source, '/') !== false) {
                    [$cidrIp] = explode('/', $source, 2);
                    if (filter_var($cidrIp, FILTER_VALIDATE_IP)) {
                        $isCidr = true;
                    }
                }

                if ($isIp || $isCidr) {
                    $blockedIps[] = [
                        'uuid' => (string) Str::uuid(),
                        'hostname' => $hostname,
                        'ip' => $source,
                        'extension' => null,
                        'user_agent' => null,
                        'date' => null,
                        'filter' => $currentChain,
                        'status' => 'blocked',
                    ];
                }
            }
        }

        return collect($blockedIps)->unique('ip');
    }

    /**
     * Run iptables command.
     */
    public function getIptablesRules()
    {
        $process = new Process([
            'sudo',
            '-n',
            'iptables',
            '-L',
            '-n',
            '--line-numbers',
        ]);

        $process->setTimeout(10);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    /**
     * Get only the latest Event Guard log per IP for the given blocked IPs.
     *
     * PostgreSQL DISTINCT ON keeps the first row per ip_address based on ORDER BY.
     */
    public function getEventGuardLogs(array $ips = [])
    {
        if (empty($ips)) {
            return collect();
        }

        return EventGuardLogs::query()
            ->selectRaw("
                DISTINCT ON (ip_address)
                event_guard_log_uuid,
                hostname,
                log_date,
                filter,
                ip_address,
                extension,
                user_agent,
                log_status
            ")
            ->whereIn('ip_address', $ips)
            ->orderBy('ip_address')
            ->orderByDesc('log_date')
            ->get()
            ->keyBy('ip_address');
    }

    /**
     * Paginate a given collection.
     */
    public function paginateCollection($items, $perPage = 50, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);

        $pageItems = $items->forPage($page, $perPage)->values();

        $pageItems = $pageItems->map(function ($item) {
            $item['date'] = !empty($item['date'])
                ? Carbon::parse($item['date'])->format('M j, Y g:i A')
                : null;

            return $item;
        });

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $items->count(),
            $perPage,
            $page,
            $options
        );

        $paginator->setPath(url()->current());

        return $paginator;
    }

    /**
     * Merge Event Guard data into blocked IP collection.
     */
    protected function combineEventGuardLogs($data, $eventGuardLogs)
    {
        return $data->map(function ($item) use ($eventGuardLogs) {
            $ip = $item['ip'];

            if (isset($eventGuardLogs[$ip])) {
                $log = $eventGuardLogs[$ip];

                $item['uuid'] = $log->event_guard_log_uuid;
                $item['hostname'] = $log->hostname ?: $item['hostname'];
                $item['extension'] = $log->extension;
                $item['user_agent'] = $log->user_agent;
                $item['date'] = $log->log_date;
                $item['filter'] = $log->filter ?: $item['filter'];
                $item['status'] = $log->log_status ?: $item['status'];
            }

            return $item;
        });
    }

    public function destroy()
    {
        try {
            foreach (request('items', []) as $ip) {
                $fail2banProcess = new Process(['sudo', '-n', 'fail2ban-client', 'unban', 'ip', $ip]);
                $fail2banProcess->setTimeout(10);
                $fail2banProcess->run();

                if (!$fail2banProcess->isSuccessful()) {
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
                if (preg_match('/^Chain\s+(\S+)/', $line, $matches)) {
                    $currentChain = $matches[1];
                    continue;
                }

                foreach (request('items', []) as $ip) {
                    if (
                        strpos($line, $ip) !== false &&
                        (strpos($line, 'DROP') !== false || strpos($line, 'REJECT') !== false)
                    ) {
                        $parts = preg_split('/\s+/', trim($line));

                        if (isset($parts[0]) && is_numeric($parts[0]) && $currentChain) {
                            $rulesToDelete[] = [
                                'chain' => $currentChain,
                                'line' => $parts[0],
                                'ip' => $ip,
                            ];
                        }
                    }
                }
            }

            // Delete from highest line number first per chain
            $rulesToDelete = collect($rulesToDelete)
                ->groupBy('chain')
                ->flatMap(function ($rules) {
                    return $rules->sortByDesc('line')->values();
                })
                ->values()
                ->all();

            foreach ($rulesToDelete as $rule) {
                $deleteProcess = new Process([
                    'sudo',
                    '-n',
                    'iptables',
                    '-D',
                    $rule['chain'],
                    $rule['line']
                ]);

                $deleteProcess->setTimeout(10);
                $deleteProcess->run();

                if (!$deleteProcess->isSuccessful()) {
                    throw new ProcessFailedException($deleteProcess);
                } else {
                    logger("IP {$rule['ip']} is succesfully unbanned in iptables");
                }
            }

            EventGuardLogs::whereIn('ip_address', request('items', []))->delete();

            return response()->json([
                'messages' => ['success' => ['Request to unblock IP addresses was successful']]
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . PHP_EOL);

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreIpBlockRequest $request): JsonResponse
    {
        try {
            $inputs = $request->validated();
            $ipOrSubnet = $inputs['ip_address'];

            $this->ensureChainExists('fs_pbx_deny_access');

            $blockProcess = new Process([
                'sudo',
                '-n',
                'iptables',
                '-A',
                'fs_pbx_deny_access',
                '-s',
                $ipOrSubnet,
                '-j',
                'DROP'
            ]);

            $blockProcess->setTimeout(10);
            $blockProcess->run();

            if (!$blockProcess->isSuccessful()) {
                throw new ProcessFailedException($blockProcess);
            }

            $saveProcess = new Process(['sudo', '-n', 'iptables-save']);
            $saveProcess->setTimeout(10);
            $saveProcess->run();

            if (!$saveProcess->isSuccessful()) {
                throw new ProcessFailedException($saveProcess);
            }

            return response()->json([
                'messages' => ['success' => ['New item created']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create new item']]
            ], 500);
        }
    }

    /**
     * Ensure a chain exists.
     */
    protected function ensureChainExists($chain)
    {
        $checkChainProcess = new Process(['sudo', '-n', 'iptables', '-L', $chain]);
        $checkChainProcess->setTimeout(10);
        $checkChainProcess->run();

        if (!$checkChainProcess->isSuccessful()) {
            $createChainProcess = new Process(['sudo', '-n', 'iptables', '-N', $chain]);
            $createChainProcess->setTimeout(10);
            $createChainProcess->run();

            if (!$createChainProcess->isSuccessful()) {
                throw new ProcessFailedException($createChainProcess);
            }

            $insertChainProcess = new Process(['sudo', '-n', 'iptables', '-I', 'INPUT', '-j', $chain]);
            $insertChainProcess->setTimeout(10);
            $insertChainProcess->run();

            if (!$insertChainProcess->isSuccessful()) {
                throw new ProcessFailedException($insertChainProcess);
            }
        }
    }

    /**
     * Select all blocked IPs.
     */
    public function selectAll()
    {
        try {
            $ips = [];
            $process = new Process(['sudo', '-n', 'iptables', '-L', '-n']);
            $process->setTimeout(10);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();
            $lines = explode("\n", $output);

            foreach ($lines as $line) {
                if (strpos($line, 'DROP') !== false || strpos($line, 'REJECT') !== false) {
                    $parts = preg_split('/\s+/', trim($line));

                    // Standard `iptables -L -n` output:
                    // 0:target, 1:prot, 2:opt, 3:source
                    if (!isset($parts[3])) {
                        continue;
                    }

                    $source = $parts[3];

                    if ($source === '0.0.0.0/0') {
                        continue;
                    }

                    $isIp = filter_var($source, FILTER_VALIDATE_IP);
                    $isCidr = (
                        !$isIp &&
                        strpos($source, '/') !== false &&
                        filter_var(explode('/', $source)[0], FILTER_VALIDATE_IP)
                    );

                    if ($isIp || $isCidr) {
                        $ips[] = $source;
                    }
                }
            }

            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => array_values(array_unique($ips)),
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