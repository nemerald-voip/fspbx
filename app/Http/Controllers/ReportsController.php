<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Domain;
use App\Jobs\ExportReport;
use App\Jobs\AuditStaleRingotelUsers;

class ReportsController extends Controller
{


    protected $viewName = 'Reports';

    public function index()
    {
        return Inertia::render($this->viewName, [
            'routes' => [
                'data_route' => route('reports.data'),
                'generate' => route('reports.generate'),
            ],
        ]);
    }

    private function reports(): array
    {
        return [
            ['id' => 'active-extensions', 'reportName' => __('Active and suspended extensions per domain')],
            ['id' => 'stale-ringotel-users', 'reportName' => __('Stale Ringotel users')],
        ];
    }

    public function getData(Request $request)
    {
        $search = trim((string) $request->input('filter.search', ''));
        return response()->json(collect($this->reports())->filter(
            fn ($report) => $search === '' || mb_stripos($report['reportName'], $search) !== false
        )->values());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'reportId' => ['required', Rule::in(array_column($this->reports(), 'id'))],
        ]);

        try {
            if ($validated['reportId'] === 'active-extensions') {
                $this->handleActiveAndSuspendedExtensionsReport();
            } else {
                $this->handleStaleRingotelUsersReport();
            }

            return response()->json([
                'messages' => ['success' => [__("Report is being generated in the background. We'll email you a link when it's ready to download.")]],
            ]);
        } catch (\Exception $e) {
            logger($e->getMessage());
            return response()->json([
                'errors' => ['server' => [__('Failed to export items')]],
            ], 500);
        }
    }

    private function handleActiveAndSuspendedExtensionsReport()
    {
        $domains = Domain::select(
            'domain_uuid',
            'domain_name',
            'domain_description',
        )
            ->with(['extensions' => function ($query) {
                $query->select('extension_uuid', 'domain_uuid');
            }])
            ->with(['extensions.mobile_app' => function ($query) {
                $query->select('mobile_app_user_uuid', 'extension_uuid', 'status');
            }])
            ->get();

        // Iterate through the collection to count extensions and suspended extensions
        $domainData = $domains->map(function ($domain) {
            $totalExtensions = $domain->extensions->count();
            $suspendedExtensions = $domain->extensions->where('suspended', true)->count();
            $activeExtensions = $totalExtensions - $suspendedExtensions;

            $activeMobileApps = 0;
            foreach ($domain->extensions as $extension) {
                if ($extension->mobile_app) {
                    if ($extension->mobile_app->status == 1) {
                        $activeMobileApps++;
                    }
                }
            }

            return [
                'domain_uuid' => $domain->domain_uuid,
                'domain_name' => $domain->domain_name,
                'domain_description' => $domain->domain_description,
                'total_extensions' => $totalExtensions,
                'suspended_extensions' => $suspendedExtensions,
                'active_extensions' => $activeExtensions,
                'active_mobile_apps' => $activeMobileApps,
            ];
        });

        $params['user_email'] = auth()->user()->user_email;

        ExportReport::dispatch($params, $domainData);

    }

    private function handleStaleRingotelUsersReport()
    {
        AuditStaleRingotelUsers::dispatch();
    }

}
