<?php

namespace App\Http\Controllers;

use App\Services\FreeswitchEslService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

class SystemController extends Controller
{
    public function index()
    {
        if (!$this->canViewPage()) {
            return redirect('/');
        }

        return Inertia::render('System', [
            'routes' => [
                'current_page' => route('system.index'),
                'data_route' => route('system.data'),
            ],
            'permissions' => $this->permissions(),
        ]);
    }

    public function data(FreeswitchEslService $eslService): JsonResponse
    {
        if (!$this->canViewPage()) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']],
            ], 403);
        }

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'info' => userCheckPermission('system_view_info') ? $this->info($eslService) : null,
            'memory' => userCheckPermission('system_view_ram') ? $this->memory() : null,
            'cpu' => userCheckPermission('system_view_cpu') ? $this->cpu() : null,
            'disk' => userCheckPermission('system_view_hdd') ? $this->disk() : null,
            'database' => userCheckPermission('system_view_database') ? $this->database() : null,
            'memcache' => userCheckPermission('system_view_memcache') ? $this->memcache($eslService) : null,
        ]);
    }

    private function canViewPage(): bool
    {
        return collect($this->permissions())->contains(true);
    }

    private function permissions(): array
    {
        return [
            'system_view_info' => userCheckPermission('system_view_info'),
            'system_view_cpu' => userCheckPermission('system_view_cpu'),
            'system_view_hdd' => userCheckPermission('system_view_hdd'),
            'system_view_ram' => userCheckPermission('system_view_ram'),
            'system_view_database' => userCheckPermission('system_view_database'),
            'system_view_memcache' => userCheckPermission('system_view_memcache'),
            'system_view_backup' => userCheckPermission('system_view_backup'),
        ];
    }

    private function info(FreeswitchEslService $eslService): array
    {
        $rows = [
            ['label' => 'Application Version', 'value' => env('VERSION', config('app.version'))],
            ['label' => 'Project Path', 'value' => base_path()],
            ['label' => 'PHP Version', 'value' => PHP_VERSION],
            ['label' => 'Date', 'value' => now()->toRfc2822String()],
        ];

        if ($eslService->isConnected()) {
            $switchVersion = (string) $eslService->executeCommand('version', false);

            if ($switchVersion !== '') {
                $rows[] = [
                    'label' => 'FreeSWITCH Version',
                    'value' => $this->formatSwitchVersion($switchVersion),
                ];
            }
        } else {
            $rows[] = [
                'label' => 'FreeSWITCH Version',
                'value' => 'Unable to connect to the event socket.',
            ];
        }

        $osRows = [
            ['label' => 'Operating System', 'value' => $this->osName()],
            ['label' => 'Kernel', 'value' => trim(php_uname())],
            ['label' => 'Uptime', 'value' => $this->command('uptime')],
        ];

        return [
            'rows' => $rows,
            'os' => array_values(array_filter($osRows, fn ($row) => filled($row['value']))),
        ];
    }

    private function memory(): ?array
    {
        if (stristr(PHP_OS, 'Linux')) {
            return [
                'title' => 'Memory',
                'output' => $this->command('free -hw'),
            ];
        }

        if (stristr(PHP_OS, 'FreeBSD')) {
            return [
                'title' => 'Memory',
                'output' => $this->command('sysctl vm.vmtotal'),
            ];
        }

        return null;
    }

    private function cpu(): ?array
    {
        if (stristr(PHP_OS, 'Linux')) {
            return [
                'title' => 'CPU',
                'output' => $this->command("ps -e -o pcpu,cpu,nice,state,cputime,args --sort pcpu | sed '/^ 0.0 /d'"),
            ];
        }

        if (stristr(PHP_OS, 'FreeBSD')) {
            return [
                'title' => 'CPU',
                'output' => $this->command('top'),
            ];
        }

        return null;
    }

    private function disk(): ?array
    {
        if (stristr(PHP_OS, 'Linux') || stristr(PHP_OS, 'FreeBSD')) {
            return [
                'title' => 'Drive Space',
                'output' => $this->command('df -hP --total'),
            ];
        }

        return null;
    }

    private function database(): ?array
    {
        if (config('database.default') !== 'pgsql') {
            return [
                'rows' => [
                    ['label' => 'Driver', 'value' => config('database.default')],
                ],
                'databases' => [],
            ];
        }

        try {
            return [
                'rows' => [
                    ['label' => 'Version', 'value' => DB::selectOne('select version() as version')?->version],
                    ['label' => 'Connections', 'value' => DB::selectOne('select count(*) as count from pg_stat_activity')?->count],
                ],
                'databases' => collect(DB::select(
                    'select datname, pg_size_pretty(pg_database_size(datname)) as size from pg_database order by datname'
                ))->map(fn ($row) => [
                    'name' => $row->datname,
                    'size' => $row->size,
                ])->values(),
            ];
        } catch (Throwable $e) {
            logger('SystemController@database error: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());

            return [
                'rows' => [
                    ['label' => 'Status', 'value' => 'Unable to read database status.'],
                ],
                'databases' => [],
            ];
        }
    }

    private function memcache(FreeswitchEslService $eslService): array
    {
        if (!$eslService->isConnected()) {
            return [
                'available' => false,
                'rows' => [
                    ['label' => 'Status', 'value' => 'Unable to connect to the event socket.'],
                ],
            ];
        }

        $response = (string) $eslService->executeCommand('memcache status verbose', false);
        $rows = collect(preg_split('/\r?\n/', $response))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => $line !== '' && str_contains($line, ': '))
            ->map(function ($line) {
                [$label, $value] = explode(': ', $line, 2);

                return [
                    'label' => $label,
                    'value' => $value,
                ];
            })
            ->values();

        if ($rows->isEmpty()) {
            return [
                'available' => false,
                'rows' => [
                    ['label' => 'Status', 'value' => 'Unavailable'],
                ],
            ];
        }

        return [
            'available' => true,
            'rows' => $rows,
        ];
    }

    private function osName(): ?string
    {
        if (stristr(PHP_OS, 'Linux')) {
            $name = $this->command('lsb_release -ds');

            if (filled($name)) {
                return trim($name, "\"' \n\r\t");
            }

            $release = @file_get_contents('/etc/os-release');
            if ($release && preg_match('/^PRETTY_NAME=["\']?(.+?)["\']?$/m', $release, $matches)) {
                return $matches[1];
            }
        }

        return PHP_OS;
    }

    private function formatSwitchVersion(string $version): string
    {
        if (preg_match('/FreeSWITCH Version (\d+\.\d+\.\d+(?:\.\d+)?).*\(.*?(\d+\w+)\s*\)/', $version, $matches)) {
            return "{$matches[1]} ({$matches[2]})";
        }

        return trim($version);
    }

    private function command(string $command): ?string
    {
        $output = @shell_exec($command);

        return filled($output) ? trim($output) : null;
    }
}
