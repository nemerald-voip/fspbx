<?php

namespace App\Http\Controllers;

use Throwable;
use Inertia\Inertia;
use App\Models\Faxes;
use Inertia\Response;
use App\Models\Dialplans;
use App\Models\Domain;
use Illuminate\Support\Str;
use App\Models\Destinations;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use App\Imports\PhoneNumbersImport;
use App\Exports\PhoneNumberTemplate;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\RedirectResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\HeadingRowImport;
use App\Services\CallRoutingOptionsService;
use Maatwebsite\Excel\Excel as ExcelWriter;
use App\Http\Requests\StorePhoneNumberRequest;
use App\Http\Requests\UpdatePhoneNumberRequest;
use Illuminate\Contracts\Foundation\Application;
use App\Http\Requests\BulkUpdatePhoneNumberRequest;
use App\Http\Requests\BulkDeletePhoneNumberRequest;
use App\Http\Requests\PhoneNumberItemOptionsRequest;
use App\Http\Requests\PhoneNumberRoutingOptionsRequest;
use App\Http\Requests\ImportPhoneNumbersRequest;
use App\Http\Requests\ImportPhoneNumbersPreviewRequest;
use App\Services\DialplanBuilderService;
use App\Services\PhoneNumberAccessService;
use App\Traits\ChecksLimits;
use App\Exports\PhoneNumbersExport;

class PhoneNumbersController extends Controller
{
    use ChecksLimits;

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'PhoneNumbers';
    protected $searchable = ['destination_number', 'destination_data', 'destination_description'];

    public function export()
    {
        if (! userCheckPermission('destination_export')) {
            abort(403);
        }
        return Excel::download(new PhoneNumbersExport, 'phone_numbers.csv', ExcelWriter::CSV);
    }

    public function __construct()
    {
        $this->model = new Destinations();
        $permissions = [
            'index' => 'destination_view', 'getData' => 'destination_view', 'selectAll' => 'destination_view',
            'store' => 'destination_add', 'update' => 'destination_edit', 'bulkUpdate' => 'destination_edit',
            'bulkDelete' => 'destination_delete', 'export' => 'destination_export',
            'import' => 'destination_import', 'importPreview' => 'destination_import',
            'importCommit' => 'destination_import', 'downloadTemplate' => 'destination_import',
        ];
        $this->middleware(function ($request, $next) use ($permissions) {
            app(PhoneNumberAccessService::class)->currentDomain();
            if ($permission = $permissions[$request->route()->getActionMethod()] ?? null) {
                abort_unless(userCheckPermission($permission), 403);
            }
            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @return Redirector|Response|RedirectResponse|Application
     */
    public function index(Request $request)
    {
        if (!userCheckPermission("destination_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'pagination' => [
                    'per_page' => fspbx_pagination_per_page(),
                    'per_page_options' => fspbx_pagination_options(),
                ],

                'routes' => [
                    'current_page' => route('phone-numbers.index'),
                    'store' => route('phone-numbers.store'),
                    'data_route' => route('phone-numbers.data'),
                    'select_all' => route('phone-numbers.select.all'),
                    'bulk_update' => route('phone-numbers.bulk.update'),
                    'bulk_delete' => route('phone-numbers.bulk.delete'),
                    'item_options' => route('phone-numbers.item.options'),
                    'download_template' => route('phone-numbers.template.download'),
                    'import' => route('phone-numbers.import.preview'),
                    'export' => route('phone-numbers.export'),
                ],
                'permissions' => [
                    'view_global' => userCheckPermission('destination_all'),
                    'create' => userCheckPermission('destination_add'),
                    'update' => userCheckPermission('destination_edit'),
                    'destroy' => userCheckPermission('destination_delete'),
                    'upload' => userCheckPermission('destination_upload'),

                ],

            ]
        );
    }


    public function getItemOptions(PhoneNumberItemOptionsRequest $request)
    {
        $item_uuid = $request->validated('item_uuid');
        $mode = $request->validated('mode');
        $access = app(PhoneNumberAccessService::class);
        $number = $item_uuid ? $access->query()->findOrFail($item_uuid, [
            'domain_uuid', 'destination_uuid', 'fax_uuid', 'destination_number', 'destination_prefix',
            'destination_record', 'destination_distinctive_ring', 'destination_cid_name_prefix',
            'destination_accountcode', 'destination_actions', 'destination_enabled', 'destination_description',
            'destination_type_fax', 'destination_hold_music',
        ]) : null;
        $domainUuid = $access->targetDomain($request->validated('domain_uuid'), $number);

        try {
            // Check for limits
            if ($mode === 'create') {
                if ($resp = $this->enforceLimit('destinations', \App\Models\Destinations::class)) {
                    return $resp;
                }
            }

            $routingOptionsService = new CallRoutingOptionsService($domainUuid);
            $routingTypes = $routingOptionsService->routingTypes;

            $faxes = [];
            $faxesCollection = Faxes::query();
            $faxesCollection->where('domain_uuid', $domainUuid);
            $faxesCollection = $faxesCollection->orderBy('fax_name')->get([
                'fax_extension',
                'fax_name',
                'fax_uuid'
            ]);
            foreach ($faxesCollection as $fax) {
                $faxes[] = [
                    'label' => $fax->fax_extension . ' ' . $fax->fax_name,
                    'value' => $fax->fax_uuid
                ];
            }

            $domains = [];
            $domainsCollection = Domain::query()->when(!userCheckPermission('destination_all'),
                fn ($query) => $query->where('domain_uuid', $access->currentDomain()))
                ->get(['domain_uuid', 'domain_description', 'domain_name']);
            if ($domainsCollection) {
                foreach ($domainsCollection as $domain) {
                    $domains[] = [
                        'value' => $domain->domain_uuid,
                        'label' => $domain->domain_description ?: $domain->domain_name
                    ];
                }
            }

            $permissions = $this->getUserPermissions();

            $music_on_hold_options = getMusicOnHoldCollection($domainUuid);

            $routes = [
                'store_route' => route('phone-numbers.store'),
                'update_route' => $number ? route('phone-numbers.update', $number) : null,
                'get_routing_options' => route('phone-numbers.routing.options', ['item_uuid' => $item_uuid, 'domain_uuid' => $domainUuid]),
                'bulk_update_route' => route('phone-numbers.bulk.update'),
            ];


            // Construct the itemOptions object
            $itemOptions = [
                'item' => $number ?? $this->model,
                'permissions' => $permissions,
                'routes' => $routes,
                'routing_types' => $routingTypes,
                'faxes' => $faxes,
                'domains' => $domains,
                'music_on_hold_options' => $music_on_hold_options ?? null,
                // Define options for other fields as needed
            ];
            // logger($itemOptions);

            return $itemOptions;
        } catch (\Exception $e) {
            $this->rethrowAccessFailure($e);
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to fetch item details')]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function routingOptions(PhoneNumberRoutingOptionsRequest $request): JsonResponse
    {
        $access = app(PhoneNumberAccessService::class);
        $number = $request->filled('item_uuid') ? $access->query()->findOrFail($request->validated('item_uuid')) : null;
        $domain = $access->targetDomain($request->validated('domain_uuid'), $number);

        return response()->json(['options' => (new CallRoutingOptionsService($domain))->getOptions()]);
    }

    public function getData()
    {
        abort_unless(userCheckPermission('destination_view'), 403);

        $perPage = fspbx_pagination_per_page();
        $currentDomain = session('domain_uuid');

        $data = QueryBuilder::for(app(PhoneNumberAccessService::class)->query())
            ->select([
                'destination_uuid',
                'destination_number',
                'destination_prefix',
                'destination_actions',
                'destination_enabled',
                'destination_description',
                'destination_record',
                'fax_uuid',
                'domain_uuid',
            ])
            // allow ?filter[username]=foo or ?filter[user_email]=bar
            ->allowedFilters([
                // Only email and name_formatted
                AllowedFilter::callback('search', function ($query, $value) {
                    $s = trim((string) $value);
                    if ($s === '') {
                        return;
                    }

                    // If it contains any letters, keep original behavior (text search)
                    if (preg_match('/[A-Za-z]/', $s)) {
                        $query->where(function ($q) use ($s) {
                            $q->where('destination_number', 'ilike', "%{$s}%")
                                ->orWhere('destination_description', 'ilike', "%{$s}%")
                                ->orWhere('destination_actions', 'ilike', "%{$s}%");
                        });
                        return;
                    }

                    // Resolve the national digits using the account or explicit country code.
                    $parts = app(\App\Services\PhoneNumberService::class)->destinationParts($s, null, session('domain_uuid'));
                    $digits = preg_replace('/\D+/', '', $parts['destination_number']);

                    if ($digits === '') {
                        return;
                    }

                    // Search destination_number after stripping non-digits in SQL (Postgres)
                    $pattern = '%' . implode('%', str_split($digits)) . '%';
                    $query->where('destination_number', 'ilike', $pattern);                        
                }),
                AllowedFilter::callback('showGlobal', function ($query, $value) use ($currentDomain) {
                    if (!filter_var($value, FILTER_VALIDATE_BOOLEAN) || !userCheckPermission('destination_all')) {
                        $query->where('domain_uuid', $currentDomain);
                    }
                })->default(false)->nullable(),
            ])

            ->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description');
            }])

            ->allowedSorts([
                'destination_number',
                'destination_description',
                'destination_enabled',
                AllowedSort::callback('destination_actions', function ($query, bool $descending, string $property) {
                    $direction = $descending ? 'desc' : 'asc';
                    $query->orderByRaw("CAST({$property} AS TEXT) {$direction}");
                }),
            ])
            ->defaultSort('destination_number')
            ->paginate($perPage)
            ->through(function (Destinations $destination) {
                $extras = array_values(array_filter([
                    filled($destination->fax_uuid) ? __('Fax detection') : null,
                    $destination->destination_record === 'true' ? __('Recorded') : null,
                ]));

                // The list shows routing_summary instead of the edit form's routing_options.
                return $destination
                    ->setAppends(['destination_number_formatted'])
                    ->makeHidden(['destination_record', 'fax_uuid'])
                    ->toArray() + [
                        'routing_summary' => $destination->routingSummary(),
                        'routing_extras' => $extras,
                    ];
            });

        return $data;
    }

    /**
     * Import the specified resource
     *
     * @return \Illuminate\Http\Response
     */
    public function import()
    {
        if (! userCheckPermission('destination_import')) {
            abort(403);

            try {

                $file = request()->file('file');
                // $domain_uuid = session('domain_uuid');

                // 1. Count how many rows will be imported
                // $rows = Excel::toCollection(new PhoneNumbersImport, $file)->first(); // Get first sheet
                // $importCount = $rows->count();

                // 2. Check current count and limit
                // $currentCount = \App\Models\Extensions::where('domain_uuid', $domain_uuid)->count();
                // $maxLimit = get_limit_setting('extensions', $domain_uuid);

                // if ($maxLimit !== null && ($currentCount + $importCount) > $maxLimit) {
                //     return response()->json([
                //         'success' => false,
                //         'errors' => [
                //             'extension' => [
                //                 "Importing this file would exceed your extension limit of $maxLimit. " .
                //                     "You currently have $currentCount extensions and are trying to import $importCount."
                //             ]
                //         ]
                //     ], 422);
                // }

                $headings = (new HeadingRowImport)->toArray(request()->file('file'));

                $import = new PhoneNumbersImport;
                $import->import($file);

                if ($import->failures()->isNotEmpty()) {

                    // Transform each failure into a readable error message
                    $errors = [];
                    foreach ($import->failures() as $failure) {
                        $row = $failure->row(); // Row number
                        $attr = $failure->attribute(); // Column/field name
                        $errList = $failure->errors(); // Array of error messages

                        foreach ($errList as $errMsg) {
                            $errors[] = "Row {$row}, '{$attr}': {$errMsg}";
                        }
                    }

                    return response()->json([
                        'success' => false,
                        'errors' => ['server' => $errors]
                    ], 500);
                }

                return response()->json([
                    'success' => true,
                    'messages' => ['success' => [__('Phone numbers have been successfully imported.')]]
                ], 200);
            } catch (Throwable $e) {
                logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
                // Send response in format that Dropzone understands
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => [$e->getMessage()]]
                ], 500);
            }
        }
    }

/**
     * Parse the file and return data to frontend
     */
    public function importPreview(ImportPhoneNumbersPreviewRequest $request)
    {
        try {
            $file = $request->file('file');
            
            // Import to array instead of collection
            $import = new PhoneNumbersImport;
            $rows = $import->toArray($file);

            // Handle Parsing Errors
            if ($import->failures()->isNotEmpty()) {
                $errors = [];
                foreach ($import->failures() as $failure) {
                    $errors[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
                }
                return response()->json(['success' => false, 'errors' => ['server' => $errors]], 500);
            }

            $previewData = [];
            $sheet = $rows[0] ?? [];

            foreach ($sheet as $row) {
                // Combine Country code and number for display
                $prefix = preg_replace('/\D+/', '', $row['country_code'] ?? '');
                $number = preg_replace('/\D+/', '', $row['phone_number'] ?? '');
                $description = $this->normalizeImportDescription(
                    $row['description'] ?? $row['destination_description'] ?? null
                );
                
                $previewData[] = [
                    'id' => Str::uuid()->toString(), // Temp ID for frontend keys
                    'destination_prefix' => $prefix,
                    'destination_number' => $number,
                    'destination_number_formatted' => $prefix . $number, // Initial value
                    'destination_description' => $description,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $previewData
            ], 200);

        } catch (Throwable $e) {
            logger($e->getMessage());
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }

    /**
     * Receive the approved list and save to DB
     */
    public function importCommit(ImportPhoneNumbersRequest $request)
    {
        $items = $request->validated('items');
        $domain_uuid = session('domain_uuid');
        $domain_name = session('domain_name');

        try {
            DB::beginTransaction();

            $actions = [];
            foreach ($items as $index => $row) {
                $options = !empty($row['routing_type'])
                    ? [['type' => $row['routing_type'], 'extension' => $row['routing_extension'] ?? null]] : [];
                $actions[$index] = json_encode(app(PhoneNumberAccessService::class)->routingActions($options, $domain_uuid));
            }

            foreach ($items as $index => $row) {
                $destination_actions = $actions[$index];

                $phone_number = Destinations::create([
                    'destination_uuid' => Str::uuid(),
                    'domain_uuid' => $domain_uuid,
                    'dialplan_uuid' => Str::uuid(),
                    'destination_prefix' => $row['destination_prefix'] ?? null,
                    'destination_number' => $row['destination_number'],
                    'destination_description' => $this->sanitizeImportDescription($row['destination_description'] ?? null),
                    'destination_type' => 'inbound',
                    'destination_actions' => $destination_actions,
                    'destination_enabled' => 'true', 
                    'destination_record' => 'false', 
                    'destination_context' => 'public',
                    'destination_accountcode' => $domain_name,
                ]);

                $this->queueDialplan($phone_number);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'messages' => ['success' => [__(':count phone numbers imported successfully.', ['count' => count($items)])]]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->rethrowAccessFailure($e);
            logger($e);
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Error saving phone numbers: :error', ['error' => $e->getMessage()])]]
            ], 500);
        }
    }

    private function sanitizeImportDescription(?string $description): ?string
    {
        $description = $this->normalizeImportDescription($description);

        if ($description === null) {
            return null;
        }

        return htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
    }

    private function normalizeImportDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $description = trim(strip_tags(htmlspecialchars_decode($description, ENT_QUOTES)));

        if ($description === '') {
            return null;
        }

        return preg_replace('/[^\x20-\x7E]/', '', $description);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StorePhoneNumberRequest  $request
     * @return JsonResponse
     */
    public function store(StorePhoneNumberRequest $request): JsonResponse
    {
        $inputs = app(PhoneNumberAccessService::class)->validateWrite($request->validated());

        // Enforce limit BEFORE creating new phone number
        if ($resp = $this->enforceLimit('destinations', \App\Models\Destinations::class)) {
            return $resp;
        }

        try {
            $inputs = array_map(function ($value) {
                return $value === 'NULL' ? null : $value;
            }, $inputs);
            $inputs['destination_actions'] ??= '[]';

            $instance = $this->model;
            $instance->fill([
                'destination_uuid' => Str::uuid(),
                'domain_uuid' => $inputs['domain_uuid'],
                'dialplan_uuid' => Str::uuid(),
                'fax_uuid' => $inputs['fax_uuid'] ?? null,
                'destination_type' => 'inbound',
                'destination_prefix' => $inputs['destination_prefix'],
                'destination_number' => $inputs['destination_number'],
                'destination_actions' => $inputs['destination_actions'],
                'destination_conditions' => $inputs['destination_conditions'] ?? null,
                'destination_hold_music' => $inputs['destination_hold_music'] ?? null,
                'destination_description' => $inputs['destination_description'] ?? null,
                'destination_enabled' => $inputs['destination_enabled'] ?? true,
                'destination_record' => $inputs['destination_record'] ?? false,
                'destination_type_fax' => $inputs['destination_type_fax'] ?? false,
                'destination_cid_name_prefix' => $inputs['destination_cid_name_prefix'] ?? null,
                'destination_accountcode' => $inputs['destination_accountcode'] ?? null,
                'destination_distinctive_ring' => $inputs['destination_distinctive_ring'] ?? null,
                'destination_context' => $inputs['destination_context'] ?? 'public',
            ]);
            $instance->save();

            // $this->generateDialPlanXML($instance);
            // Generate dialplan
            $this->queueDialplan($instance);

            return response()->json([
                'messages' => ['success' => [__('New phone number succesfully created')]]
            ], 201);
        } catch (\Exception $e) {
            // Log the error message
            logger($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to create new item')], 'ss' => $e->getMessage()]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Bulk update requested items
     *
     * @param  BulkUpdatePhoneNumberRequest  $request
     * @return JsonResponse
     */
    public function bulkUpdate(BulkUpdatePhoneNumberRequest $request)
    {
        $data = $request->validated();

        $ids = $data['items'];
        unset($data['items']);

        try {
            DB::beginTransaction();

            $access = app(PhoneNumberAccessService::class);
            $numbers = $access->selection($ids);
            $updates = [];
            foreach ($numbers as $number) {
                $updates[$number->destination_uuid] = $access->validateWrite($data, $number);
            }
            // Complete authorization and reference validation before saves or queued builds.
            foreach ($numbers as $number) {
                $this->savePhoneNumber($number, $updates[$number->destination_uuid]);
            }

            DB::commit();

            return response()->json([
                'messages' => ['success' => [__('Selected phone numbers updated')]],
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->rethrowAccessFailure($e);
            logger('PhoneNumbersController@bulkUpdate error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to update selected items')]]
            ], 500);
        }
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  UpdatePhoneNumberRequest  $request
     * @param  Destinations  $phone_number
     * @return JsonResponse
     */
    public function update(UpdatePhoneNumberRequest $request, Destinations $phone_number)
    {
        try {
            DB::transaction(function () use ($request, $phone_number) {
                $access = app(PhoneNumberAccessService::class);
                $this->savePhoneNumber($phone_number, $access->validateWrite($request->validated(), $phone_number), forceRebuild: true);
            });

            return response()->json([
                'messages' => ['success' => [__('Phone number updated successfully')]],
                'phone_number' => $phone_number,
            ], 200);
        } catch (\Exception $e) {
            $this->rethrowAccessFailure($e);
            logger('PhoneNumbersController@update error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to update this item')]]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    /**
     * Remove the specified resource from storage.
     * @return JsonResponse
     */
    public function bulkDelete(BulkDeletePhoneNumberRequest $request): JsonResponse
    {
        try {
            // Begin Transaction
            DB::beginTransaction();

            // Retrieve all items at once
            $access = app(PhoneNumberAccessService::class);
            $items = $access->selection($request->validated('items'));
            $dialplans = [];
            foreach ($items as $item) {
                $dialplans[$item->destination_uuid] = $access->checkDialplan($item);
            }

            $dialplanBuilder = new DialplanBuilderService();

            foreach ($items as $item) {
                //Get dialplan details
                $dialPlan = $dialplans[$item->destination_uuid];
                // Delete dialplan
                if ($dialPlan) {
                    $dialPlan->dialplan_details()->where('domain_uuid', $item->domain_uuid)->delete();
                    $dialPlan->delete();
                }

                // Delete the item itself
                $item->delete();

                //clear cache
                $dialplanBuilder->clearCacheForPhoneNumber($item);
            }

            // Commit Transaction
            DB::commit();

            return response()->json([
                'messages' => ['server' => [__('All selected items have been deleted successfully.')]],
            ], 200);
        } catch (\Exception $e) {
            // Rollback Transaction if any error occurs
            DB::rollBack();

            $this->rethrowAccessFailure($e);

            // Log the error message
            logger('PhoneNumbersController@bulkDelete error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Server returned an error while deleting the selected items.')]]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['manage_recording_setting'] = userCheckPermission('destination_record');
        $permissions['manage_destination_prefix'] = userCheckPermission('destination_prefix');
        $permissions['manage_destination_domain'] = userCheckPermission('destination_all') && userCheckPermission('destination_domain');
        $permissions['destination_hold_music'] = userCheckPermission('destination_hold_music');
        $permissions['is_superadmin'] = isSuperAdmin();

        return $permissions;
    }

    /**
     * @return JsonResponse
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function selectAll(): JsonResponse
    {
        abort_unless(userCheckPermission('destination_view'), 403);

        try {
            $domain_uuid = session('domain_uuid');

            $data = QueryBuilder::for(app(PhoneNumberAccessService::class)->query())
                ->select([
                    'destination_uuid',
                    'domain_uuid',
                ])
                ->allowedFilters([
                    AllowedFilter::callback('showGlobal', function ($query, $value) use ($domain_uuid) {
                        if (!filter_var($value, FILTER_VALIDATE_BOOLEAN) || !userCheckPermission('destination_all')) {
                            $query->where('domain_uuid', $domain_uuid);
                        }
                    })->default(false)->nullable(),
                    AllowedFilter::callback('search', function ($query, $value) {
                        $s = trim((string) $value);
                        if ($s === '') {
                            return;
                        }

                        // If it contains any letters, keep original behavior (text search)
                        if (preg_match('/[A-Za-z]/', $s)) {
                            $query->where(function ($q) use ($s) {
                                $q->where('destination_number', 'ilike', "%{$s}%")
                                    ->orWhere('destination_data', 'ilike', "%{$s}%")
                                    ->orWhere('destination_description', 'ilike', "%{$s}%");
                            });
                            return;
                        }

                        // Resolve the national digits using the account or explicit country code.
                        $parts = app(\App\Services\PhoneNumberService::class)->destinationParts($s, null, session('domain_uuid'));
                        $digits = preg_replace('/\D+/', '', $parts['destination_number']);

                        if ($digits === '') {
                            return;
                        }

                        // Search destination_number after stripping non-digits in SQL (Postgres)
                    $pattern = '%' . implode('%', str_split($digits)) . '%';
                    $query->where('destination_number', 'ilike', $pattern);
                    }),
                ])
                ->pluck('destination_uuid');

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => [__('All items selected')]],
                'items' => $data,
            ], 200);
        } catch (\Exception $e) {
            logger($e);
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [__('Failed to select all items')]]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    private function savePhoneNumber(Destinations $number, array $data, bool $forceRebuild = false): void
    {
        $source = $number->domain_uuid;
        $number->fill($data);
        if (! $number->isDirty()) {
            if ($forceRebuild) {
                $this->queueDialplan($number);
            }
            return;
        }
        $number->save();
        if ($number->domain_uuid !== $source) {
            Dialplans::where('domain_uuid', $source)->whereKey($number->dialplan_uuid)
                ->update(['domain_uuid' => $number->domain_uuid]);
            \App\Models\DialplanDetails::where('domain_uuid', $source)->where('dialplan_uuid', $number->dialplan_uuid)
                ->update(['domain_uuid' => $number->domain_uuid]);
        }
        $this->queueDialplan($number);
    }

    private function queueDialplan(Destinations $number): void
    {
        $domainName = Domain::whereKey($number->domain_uuid)->value('domain_name');
        abort_unless($domainName, 404);
        \App\Jobs\BuildDialplanForPhoneNumber::dispatch($number->destination_uuid, $domainName)->afterCommit();
    }

    private function rethrowAccessFailure(\Throwable $e): void
    {
        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
            || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
            || $e instanceof \Illuminate\Validation\ValidationException) {
            throw $e;
        }
    }

    public function downloadTemplate()
    {
        // Download as CSV (third parameter sets the writer type)
        return Excel::download(new PhoneNumberTemplate, 'phone_number_template.csv', ExcelWriter::CSV);
    }
}
