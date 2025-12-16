<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Domain;
use App\Data\DomainData;
use App\Models\Extensions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Spatie\QueryBuilder\QueryBuilder;
use App\Services\SessionDomainService;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\StoreDomainRequest;
use App\Http\Requests\UpdateDomainRequest;

class DomainController extends Controller
{
    protected $viewName = 'Domains';
    protected SessionDomainService $sessionDomainService;

    public function __construct(SessionDomainService $sessionDomainService)
    {
        $this->sessionDomainService = $sessionDomainService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!userCheckPermission("domain_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [

                'routes' => [
                    // 'current_page' => route('devices.index'),
                    'data_route' => route('domains.data'),
                    // 'store' => route('devices.store'),
                    // 'select_all' => route('devices.select.all'),
                    'bulk_delete' => route('domains.bulk.delete'),
                    // 'bulk_update' => route('devices.bulk.update'),
                    'item_options' => route('domains.item.options'),
                    // 'restart' => route('devices.restart'),
                    // 'cloud_provisioning_item_options' => route('cloud-provisioning.item.options'),
                    // 'cloud_provisioning_get_token' => route('cloud-provisioning.token.get'),
                    // 'cloud_provisioning_update_api_token' => route('cloud-provisioning.token.update'),

                ],

                'permissions' => function () {
                    return $this->getUserPermissions();
                },
            ]
        );
    }

    public function getData()
    {
        $perPage = 50;

        $items = QueryBuilder::for(Domain::class)
            ->select([
                'domain_uuid',
                'domain_name',
                'domain_enabled',
                'domain_description',
            ])
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $needle = trim((string) $value);

                    // Apply your search conditions
                    $query->where(function ($q) use ($needle) {
                        $q->where('domain_name', 'ILIKE', "%{$needle}%")
                            ->orWhere('domain_description', 'ILIKE', "%{$needle}%");
                    });
                }),

            ])

            ->allowedSorts(['domain_name', 'domain_enabled', 'domain_description'])
            ->defaultSort('domain_description')
            ->paginate($perPage);

        // wrap in DTO
        $domainsDto = DomainData::collect($items);

        // logger($domainsDto);

        return $domainsDto;
    }

    public function getItemOptions()
    {
        try {

            $itemUuid = request('itemUuid');

            $routes = [];

            if ($itemUuid) {
                $item = QueryBuilder::for(Domain::class)
                    ->select([
                        'domain_uuid',
                        'domain_name',
                        'domain_enabled',
                        'domain_description',
                    ])
                    ->whereKey($itemUuid)
                    ->firstOrFail();

                $domainDto = DomainData::from($item);

                $routes = array_merge($routes, [
                    'update_route' => route('domains.update', ['domain' => $itemUuid]),
                ]);
            } else {
                // New device defaults
                $domainDto     = new DomainData();
            }

            $routes = array_merge($routes, [
                'store_route' => route('domains.store'),
            ]);

            // Construct the itemOptions object
            $itemOptions = [
                'item' => $domainDto ?? null,
                'routes' => $routes,
                'permissions' => $this->getUserPermissions(),
            ];

            return $itemOptions;
        } catch (\Exception $e) {
            logger('DeviceController@getItemOptions error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to get item details']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }



    /**
     * Switch domain from one of the Laravel pages.
     * Called when domain search is performed and user requested to switch domain
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function switchDomain(Request $request)
    {
        $domain = Domain::where('domain_uuid', $request->domain_uuid)->first();

        // If current domain is not the same as requested domain proceed with the change
        if (Session::get('domain_uuid') != $domain->domain_uuid) {

            Session::put('domain_uuid', $domain->domain_uuid);
            Session::put('domain_name', $domain->domain_name);
            Session::put('domain_description', !empty($domain->domain_description) ? $domain->domain_description : $domain->domain_name);
            $_SESSION["domain_name"] = $domain->domain_name;
            $_SESSION["domain_uuid"] = $domain->domain_uuid;
            $_SESSION["domain_description"] = !empty($domain->domain_description) ? $domain->domain_description : $domain->domain_name;

            //set the context
            Session::put('context', $_SESSION["domain_name"]);
            $_SESSION["context"] = $_SESSION["domain_name"];

            // unset destinations belonging to old domain
            unset($_SESSION["destinations"]["array"]);

            // This is a workaround to ensure the filters are reset when the domain changes.
            // Given that url()->previous() includes the filter options, it's necessary to pass a refreshed URL.
            if ($request->redirect_url) {
                $url = $request->redirect_url;
            } else {
                $url = getFusionPBXPreviousURL(url()->previous());
                $url = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH);
            }
        }

        return response()->json([
            'status' => 200,
            'redirectUrl' => $url ?? '',
            'success' => [
                'message' => 'Domain has been switched'
            ]
        ]);
    }

    /**
     * Switch domain from FusionPBX pages.
     * Called when domain search is performed and user requested to switch domain
     *
     * @return \Illuminate\Http\Response
     */
    public function switchDomainFusionPBX($domain_uuid)
    {
        logger('here');
        $domain = Domain::where('domain_uuid', $domain_uuid)->first();

        // If current domain is not the same as requested domain proceed with the change
        if (Session::get('domain_uuid') != $domain->uuid) {
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            };
            Session::put('domain_uuid', $domain->domain_uuid);
            Session::put('domain_name', $domain->domain_name);
            Session::put('domain_description', !empty($domain->domain_description) ? $domain->domain_description : $domain->domain_name);
            $_SESSION["domain_name"] = $domain->domain_name;
            $_SESSION["domain_uuid"] = $domain->domain_uuid;
            $_SESSION["domain_description"] = !empty($domain->domain_description) ? $domain->domain_description : $domain->domain_name;

            //set the context
            Session::put('context', $_SESSION["domain_name"]);
            $_SESSION["context"] = $_SESSION["domain_name"];

            // unset destinations belonging to old domain
            unset($_SESSION["destinations"]["array"]);

            $url = getFusionPBXPreviousURL(url()->previous());
            return redirect($url);
        }
    }

    /**
     * Filter domains from FusionPBX pages.
     * Called when domain search is performed and user requested to filter a list of domains
     *
     * @return \Illuminate\Http\Response
     */
    public function filterDomainsFusionPBX(Request $request)
    {
        // Retrieve domains from session
        $domains = Session::get('domains');

        // Check if domains exist in session
        if (!$domains) {
            return response()->json(['error' => 'Domains not found in session'], 404);
        }

        // Check if search parameter is provided
        if ($request->filled('search')) {
            $searchTerm = strtolower($request->search);
            // Filter domains based on search term in both domain_name and domain_description
            $domains = collect($domains)->filter(function ($domain) use ($searchTerm) {
                return strpos(strtolower($domain->domain_name), $searchTerm) !== false || strpos(strtolower($domain->domain_description), $searchTerm) !== false;
            });
        } else {
            // Convert the domains to a collection if no search term is provided
            $domains = collect($domains);
        }

        // Convert each domain object to an array
        $domains = $domains->map(function ($domain) {
            return (array) $domain;  // Cast object to array
        })->values()->all();

        echo json_encode($domains, true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreDomainRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreDomainRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $inputs = $validated;

            // Create domain
            $domain = new Domain();
            $domain->fill($inputs);
            $domain->save();

            DB::commit();

            // Keep session domains array in sync
            $this->sessionDomainService->refreshForUser(Auth::user());

            return response()->json([
                'messages' => ['success' => ['Domain created successfully.']],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            logger('DomainController@store error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create domain']],
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Domain  $domain
     * @return \Illuminate\Http\Response
     */
    public function show(Domain $domain)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Domain  $domain
     * @return \Illuminate\Http\Response
     */
    public function edit(Domain $domain)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateDeviceRequest  $request
     * @param  Devices  $device
     * @return JsonResponse
     */
    public function update(UpdateDomainRequest $request, Domain $domain)
    {
        $inputs = $request->validated();

        // logger($inputs);

        try {
            DB::beginTransaction();

            $domain->update($inputs);

            DB::commit();

            // Refresh session domains for current user
            $this->sessionDomainService->refreshForUser(Auth::user());

            return response()->json([
                'messages' => ['success' => ['Domain updated successfully.']],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            logger(
                'DomainController@update error: ' .
                    $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine()
            );
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update this domain']]
            ], 500);
        }
    }

    /**
     * Bulk delete selected domains.
     */
    public function bulkDelete(Domain $domain)
    {
        try {
            DB::beginTransaction();

            $items = request('items', []);

            if (empty($items) || !is_array($items)) {
                return response()->json([
                    'success' => false,
                    'errors'  => ['request' => ['No domains were selected for deletion.']],
                ], 422);
            }

            // Fetch all domains to delete
            $domains = Domain::whereIn('domain_uuid', $items)
                ->get([
                    'domain_uuid',
                    'domain_name'
                ]);

            foreach ($domains as $domain) {
                // If you ever need to guard special domains (like admin.localhost), do it here:
                // if ($domain->domain_name === 'admin.localhost') { continue; }

                $domain->delete();
            }

            DB::commit();

            // Remove deleted domains from session
            $this->sessionDomainService->refreshForUser(Auth::user());

            return response()->json([
                'messages' => ['server' => ['All selected domains have been deleted successfully.']],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            logger(
                'DomainController@bulkDelete error: ' .
                    $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine()
            );

            return response()->json([
                'success' => false,
                'errors'  => ['server' => ['Server returned an error while deleting the selected domains.']],
            ], 500);
        }
    }

    /**
     * get extension count for all domains.
     *
     *
     * @return \Illuminate\Http\Response
     */
    public function countExtensionsInDomains()
    {
        if (isSuperAdmin()) {


            $domains = Domain::get();


            foreach ($domains as $domain) {
                print $domain->domain_description;
                print "<br>";

                $extensions = Extensions::where('domain_uuid', $domain->domain_uuid)->get()->count();

                print $extensions;
                print "<br><br>";
            }
        } else {
            return redirect('dashboard');
        }
    }


    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['domain_create'] = userCheckPermission('domain_add');
        $permissions['domain_update'] = userCheckPermission('domain_edit');
        $permissions['domain_destroy'] = userCheckPermission('domain_delete');

        return $permissions;
    }
}
