<?php

namespace App\Http\Controllers;

use App\Exports\SpeedDialExport;
use App\Exports\SpeedDialTemplate;
use App\Http\Requests\StoreSpeedDialRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Http\Requests\UpdateSpeedDialRequest;
use App\Imports\SpeedDialImport;
use App\Models\SpeedDial;
use App\Models\SpeedDialPhone;
use App\Models\SpeedDialUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Throwable;


class SpeedDialController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'SpeedDial';
    protected $searchable = ['contact_organization', 'primaryPhone.phone_number', 'primaryPhone.phone_speed_dial',];

    public function __construct()
    {
        $this->model = new SpeedDial();
    }

    public function index()
    {
        if (!userCheckPermission("contact_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },
                'routes' => [
                    'current_page' => route('speed-dial.index'),
                    'store' => route('speed-dial.store'),
                    'select_all' => route('speed-dial.select.all'),
                    'bulk_delete' => route('speed-dial.bulk.delete'),
                    'item_options' => route('speed-dial.item.options'),
                    'import' => route('speed-dial.import'),
                    'download_template' => route('speed-dial.download.template'),
                    'export' => route('speed-dial.export'),
                ]
            ]
        );
    }

    public function getData($paginate = 50)
    {
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        $this->sortField = request()->get('sortField', 'contact_organization');
        $this->sortOrder = request()->get('sortOrder', 'asc');

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        // logger($data);

        return $data;
    }

    public function builder(array $filters = [])
    {
        $data = $this->model::query();
        $domainUuid = session('domain_uuid');
        $data = $data->where('domain_uuid', $domainUuid);
        $data->with(['primaryPhone' => function ($query) {
            $query->select('contact_phone_uuid', 'contact_uuid', 'phone_number', 'phone_speed_dial');
        }]);
        $data->with(['speedDialUser' => function ($query) {
            $query->select('contact_user_uuid', 'contact_uuid', 'user_uuid')->with(['user' => function ($query) {
                $query->select('user_uuid', 'username');
            }]);
        }]);

        $data->select(
            'contact_uuid',
            'contact_organization',

        );

        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $this->$method($data, $value);
                }
            }
        }

        return $data->orderBy($this->sortField, $this->sortOrder);
    }

    protected function filterSearch($query, $value)
    {
        $searchable = $this->searchable;

        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value, $searchable) {
            foreach ($searchable as $field) {
                if (strpos($field, '.') !== false) {
                    // Nested field (e.g., 'extension.name_formatted')
                    [$relation, $nestedField] = explode('.', $field, 2);

                    $query->orWhereHas($relation, function ($query) use ($nestedField, $value) {
                        $query->where($nestedField, 'ilike', '%' . $value . '%');
                    });
                } else {
                    // Direct field
                    $query->orWhere($field, 'ilike', '%' . $value . '%');
                }
            }
        });
    }

    public function store(StoreSpeedDialRequest $request)
    {
        try {
            DB::beginTransaction();

            // Extract validated data
            $validated = $request->validated();

            // Create new contact
            $contact = SpeedDial::create([
                'contact_uuid' => Str::uuid(),
                'contact_organization' => $validated['contact_organization'],
                'domain_uuid' => session('domain_uuid')
            ]);

            // Create phone details
            SpeedDialPhone::create([
                'contact_phone_uuid' => Str::uuid(),
                'contact_uuid' => $contact->contact_uuid,
                'phone_number' => $validated['destination_number'],
                'phone_speed_dial' => $validated['phone_speed_dial'],
                'domain_uuid' => session('domain_uuid'),
            ]);

            // Create contact users
            $userIds = collect($validated['speed_dial_users'] ?? [])->pluck('value');
            foreach ($userIds as $userId) {
                SpeedDialUser::create([
                    'contact_user_uuid' => Str::uuid(),
                    'contact_uuid' => $contact->contact_uuid,
                    'user_uuid' => $userId
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'messages' => ['success' => ['Item created successfully']],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create this item']]
            ], 500);
        }
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  UpdateContactRequest  $request
     */
    public function update(UpdateSpeedDialRequest $request, SpeedDial $speed_dial)
    {
        if (!$speed_dial) {
            return response()->json([
                'success' => false,
                'errors' => ['model' => ['Speed Dial not found']]
            ], 404);
        }

        try {
            DB::beginTransaction();

            // Extract validated data
            $validated = $request->validated();

            // Update contact details
            $speed_dial->update([
                'contact_organization' => $validated['contact_organization']
            ]);

            // Create or update phone details
            $contactPhone = SpeedDialPhone::updateOrCreate(
                [
                    'contact_uuid' => $speed_dial->contact_uuid,
                ],
                [
                    'phone_number' => $validated['destination_number'],
                    'phone_speed_dial' => $validated['phone_speed_dial'],
                    'domain_uuid' => session('domain_uuid'),
                ]
            );

            // Update contact users
            $userIds = collect($validated['speed_dial_users'])->pluck('value');
            $speed_dial->speedDialUser()->delete(); // Remove existing users

            foreach ($userIds as $userId) {
                SpeedDialUser::create([
                    'contact_user_uuid' => Str::uuid(),
                    'contact_uuid' => $speed_dial->contact_uuid,
                    'user_uuid' => $userId
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'messages' => ['success' => ['Item updated successfully']],
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update this item']]
            ], 500);
        }
    }

    public function destroy(SpeedDial $speed_dial)
    {
        try {
            DB::beginTransaction();
            $speed_dial->delete();
            DB::commit();
            return response()->json(['messages' => ['success' => ['Contact deleted']]], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e);
            return response()->json(['errors' => ['server' => ['Failed to delete contact']]], 500);
        }
    }


    public function getItemOptions()
    {
        try {

            $domain_uuid = request('domain_uuid') ?? session('domain_uuid');
            $item_uuid = request('item_uuid'); // Retrieve item_uuid from the request

            // Base navigation array without Greetings
            $navigation = [
                [
                    'name' => 'General',
                    'icon' => 'Cog6ToothIcon',
                    'slug' => 'general',
                ],

                [
                    'name' => 'Advanced',
                    'icon' => 'AdjustmentsHorizontalIcon',
                    'slug' => 'advanced',
                ],

            ];


            $users = User::where('domain_uuid', $domain_uuid)
                ->select('user_uuid')
                ->orderBy('username', 'asc')
                ->get();

            // Transform the collection into the desired array format
            $userOptions = $users->map(function ($user) {
                return [
                    'value' => $user->user_uuid,
                    'name' => $user->name_formatted,
                ];
            })->toArray();

            // Check if item_uuid exists to find an existing model
            if ($item_uuid) {
                // Find existing item by item_uuid
                $contact = $this->model::where($this->model->getKeyName(), $item_uuid)
                    ->with(['primaryPhone' => function ($query) {
                        $query->select('contact_phone_uuid', 'contact_uuid', 'phone_number', 'phone_speed_dial');
                    }])
                    ->select(
                        'contact_uuid',
                        'contact_organization',

                    )
                    ->with(['speedDialUser' => function ($query) {
                        $query->select('contact_user_uuid', 'contact_uuid', 'user_uuid')->with(['user' => function ($query) {
                            $query->select('user_uuid', 'username');
                        }]);
                    }])
                    ->first();

                // If a model exists, use it; otherwise, create a new one
                if (!$contact) {
                    throw new \Exception("Failed to fetch item details. Item not found");
                }

                // Define the update route
                $updateRoute = route('speed-dial.update', ['speed_dial' => $item_uuid]);
            } else {
                // Create a new model if item_uuid is not provided
                $contact = $this->model;
            }

            $permissions = $this->getUserPermissions();

            $routes = [
                'update_route' => $updateRoute ?? null,
                // 'get_routing_options' => route('routing.options'),

            ];

            $speed_dial_users = [];
            if ($contact->speedDialUser) {
                $speed_dial_users = $contact->speedDialUser->map(function ($user) {
                    return [
                        'value' => $user->user_uuid,
                        'name' => ''
                    ];
                })->toArray();
            }


            // Construct the itemOptions object
            $itemOptions = [
                'navigation' => $navigation,
                'contact' => $contact,
                'users' => $userOptions,
                'permissions' => $permissions,
                'routes' => $routes,
                'speed_dial_users' => $speed_dial_users,
                // Define options for other fields as needed
            ];
            // logger($itemOptions);

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


    public function bulkDelete(Request $request)
    {
        $items = $request->input('items', []);

        if (empty($items)) {
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['No items selected for deletion.']]
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Fetch contacts to be deleted
            $contacts = SpeedDial::whereIn('contact_uuid', $items)->get();

            foreach ($contacts as $contact) {
                // Delete related contact phones
                SpeedDialPhone::where('contact_uuid', $contact->contact_uuid)->delete();

                // Delete related contact users
                SpeedDialUser::where('contact_uuid', $contact->contact_uuid)->delete();

                // Delete contact
                $contact->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'messages' => ['success' => ['Selected contacts have been deleted successfully.']]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while deleting the selected items.']]
            ], 500);
        }
    }

    /**
     * Import the specified resource
     *
     * @return \Illuminate\Http\Response
     */
    public function import()
    {
        try {

            $headings = (new HeadingRowImport)->toArray(request()->file('file'));

            $import = new SpeedDialImport;
            $import->import(request()->file('file'));

            if ($import->failures()->isNotEmpty()) {

                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['Server returned an error while uploading this file.']]
                ], 500);
            }

            return response()->json([
                'success' => true,
                'messages' => ['success' => ['Speed dials have been successfully uploaded.']]
            ], 200);
        } catch (Throwable $e) {
            logger('SpeedDialController@import error: ' .$e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // Send response in format that Dropzone understands
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }


    public function getUserPermissions()
    {
        $permissions = [];
        return $permissions;
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function selectAll()
    {
        try {
            $uuids = $this->model::where('domain_uuid', session('domain_uuid'))
                ->get($this->model->getKeyName())->pluck($this->model->getKeyName());


            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $uuids,
            ], 200);
        } catch (\Exception $e) {
            logger($e);
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to select all items']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    public function downloadTemplate()
    {
        // Download as CSV (third parameter sets the writer type)
        return Excel::download(new SpeedDialTemplate, 'template.csv', ExcelWriter::CSV);
    }

    public function export()
    {
        return Excel::download(new SpeedDialExport, 'contacts.csv', ExcelWriter::CSV);
    }
}
