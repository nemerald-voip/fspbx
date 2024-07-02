<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Domain;
use App\Jobs\ExportReport;

class ReportsController extends Controller
{


    protected $viewName = 'Reports';

    // public function __construct()
    // {
    //     //
    // }

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
                'viewName' => function () {
                    return $this->viewName;
                },
                // 'showGlobal' => function () {
                //     return request('filterData.showGlobal') === 'true';
                // },

                'routes' => [
                    'current_page' => route('reports.index'),
                    'generate' => route('reports.generate'),
                ]
            ]
        );
    }


    /**
     *  Get data
     */
    public function getData()
    {

        $data = collect([
            ['reportName' => 'Active and suspended extensions per domain'],

        ]);

        return $data;
    }



    public function store()
    {

        try {

            if (request('reportName') == "Active and suspended extensions per domain") {
                $this->handleActiveAndSuspendedExtensionsReport();
            }


            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Report is being generated in the background. We\'ll email you a link when it\'s ready to download.']],
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to export items']]
            ], 500); // 500 Internal Server Error for any other errors
        }

        return response()->json([
            'success' => false,
            'errors' => ['server' => ['Failed to export']]
        ], 500); // 500 Internal Server Error for any other errors
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
}
