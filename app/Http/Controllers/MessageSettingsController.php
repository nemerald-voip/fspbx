<?php

namespace App\Http\Controllers;

use Exception;
use Inertia\Inertia;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\MessageSetting;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\CreateMessageSettingRequest;
use App\Http\Requests\UpdateMessageSettingRequest;
use App\Http\Requests\BulkUpdateMessageSettingRequest;

class MessageSettingsController extends Controller
{
    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'MessageSettings';
    protected $searchable = ['destination', 'carrier', 'description', 'chatplan_detail_data', 'email'];

    public function __construct()
    {
        $this->model = new MessageSetting();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("message_settings_list_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },
                'showGlobal' => function () {
                    return request('filterData.showGlobal') === 'true';
                },
                'itemData' => Inertia::lazy(
                    fn () =>
                    $this->getItemData()
                ),
                'itemOptions' => Inertia::lazy(
                    fn () =>
                    $this->getItemOptions()
                ),
                'routes' => [
                    'current_page' => route('messages.settings'),
                    'store' => route('messages.settings.store'),
                    'select_all' => route('messages.settings.select.all'),
                    'bulk_delete' => route('messages.settings.bulk.delete'),
                    'bulk_update' => route('messages.settings.bulk.update'),
                ],
            ]
        );
    }

    public function getItemData()
    {
        // Get item data
        $itemData = $this->model::findOrFail(request('itemUuid'));

        // Add update url route info
        $itemData->update_url = route('messages.settings.update', $itemData);
        return $itemData;
    }

    public function getItemOptions()
    {
        // Define the options for the 'carrier' field
        $carrierOptions = [
            ['value' => 'thinq', 'name' => 'ThinQ'],
            ['value' => 'sinch', 'name' => 'Sinch'],
        ];

        // Define the options for the 'chatplan_detail_data' field
        $extensions = Extensions::where('domain_uuid', session('domain_uuid'))
            ->get([
                'extension_uuid',
                'extension',
                'effective_caller_id_name',
            ]);

        $chatplanDetailDataOptions = [];
        // Loop through each extension and create an option
        foreach ($extensions as $extension) {
            $chatplanDetailDataOptions[] = [
                'value' => $extension->extension,
                'name' => $extension->name_formatted,
            ];
        }

        // Construct the itemOptions object
        $itemOptions = [
            'carrier' => $carrierOptions,
            'chatplan_detail_data' => $chatplanDetailDataOptions,
            // Define options for other fields as needed
        ];

        return $itemOptions;
    }

    public function getData($paginate = 50)
    {
        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        // Check if search parameter is present and not empty
        if (!empty(request('filterData.showGlobal'))) {
            $this->filters['showGlobal'] = request('filterData.showGlobal') === 'true';
        } else {
            $this->filters['showGlobal'] = null;
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'destination'); // Default to 'destination'
        $this->sortOrder = request()->get('sortOrder', 'asc'); // Default to ascending

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        if (isset($this->filters['showGlobal']) and $this->filters['showGlobal']) {
            // Access domains through the session and filter extensions by those domains
            $domainUuids = Session::get('domains')->pluck('domain_uuid');
            $extensions = Extensions::whereIn('domain_uuid', $domainUuids)
                ->get(['domain_uuid', 'extension', 'effective_caller_id_name']);
        } else {
            // get extensions for session domain
            $extensions = Extensions::where('domain_uuid',session('domain_uuid'))
                ->get(['domain_uuid', 'extension', 'effective_caller_id_name']);
        }
        // Iterate over each SMS destination to find the first matching extension
        foreach ($data as $destination) {
            // Initialize a variable to hold the first match
            $firstMatch = null;

            // Loop through extensions to find the first match
            foreach ($extensions as $extension) {
                if ($extension->domain_uuid === $destination->domain_uuid && $extension->extension === $destination->chatplan_detail_data) {
                    $firstMatch = $extension;
                    break; // Stop the loop after finding the first match
                }
            }

            // Assign the first match to the destination object
            $destination->extension = $firstMatch;
        }

        return $data;
    }


    public function builder($filters = [])
    {
        $data =  $this->model::query();

        if (isset($filters['showGlobal']) and $filters['showGlobal']) {
            $data->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description'); // Specify the fields you need
            }]);
            // Access domains through the session and filter devices by those domains
            $domainUuids = Session::get('domains')->pluck('domain_uuid');
            $data->whereHas('domain', function ($query) use ($domainUuids) {
                $query->whereIn('v_sms_destinations.domain_uuid', $domainUuids);
            });
        } else {
            // Directly filter devices by the session's domain_uuid
            $domainUuid = Session::get('domain_uuid');
            $data = $data->where('v_sms_destinations.domain_uuid', $domainUuid);
        }
        $data->select(
            'sms_destination_uuid',
            'destination',
            'carrier',
            'enabled',
            'description',
            'chatplan_detail_data',
            'email',
            'domain_uuid',
        );
        // logger($filters);

        foreach ($filters as $field => $value) {
            if (method_exists($this, $method = "filter" . ucfirst($field))) {
                $this->$method($data, $value);
            }
        }

        // Apply sorting
        $data->orderBy($this->sortField, $this->sortOrder);

        return $data;
    }

    protected function filterSearch($query, $value)
    {
        $searchable = $this->searchable;
        // Case-insensitive partial string search in the specified fields
        $query->where(function ($query) use ($value, $searchable) {
            foreach ($searchable as $field) {
                $query->orWhere($field, 'ilike', '%' . $value . '%');
            }
        });
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\UpdateMessageSettingRequest  $request
     * @param   App\Models\MessageSetting  $setting
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateMessageSettingRequest $request, MessageSetting $setting)
    {
        if (!$setting) {
            // If the model is not found, return an error response
            return response()->json([
                'success' => false,
                'errors' => ['model' => ['Model not found']]
            ], 404); // 404 Not Found if the model does not exist
        }

        try {
            $inputs = array_map(function ($value) {
                return $value === 'NULL' ? null : $value;
            }, $request->validated());

            $setting->update($inputs);

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Item updated.']]
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update this item']]
            ], 500); // 500 Internal Server Error for any other errors
        }

        return response()->json([
            'success' => false,
            'errors' => ['server' => ['Failed to update this item']]
        ], 500); // 500 Internal Server Error for any other errors
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\CreateMessageSettingRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateMessageSettingRequest $request)
    {
        try {
            // Validate the request data and create a new MessageSetting
            $newSetting = new MessageSetting($request->validated());
            $newSetting->save();  // Save the new model instance to the database

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
     * Remove the specified resource from storage.
     *
     * @param  MessageSetting $setting
     * @return Response
     */
    public function destroy(MessageSetting $setting)
    {
        try {
            // throw new \Exception;
            $setting->delete();

            return redirect()->back()->with('message', ['server' => ['Item deleted']]);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage());
            return redirect()->back()->with('error', ['server' => ['Server returned an error while deleting this item']]);
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
            if (request()->get('showGlobal')) {
                $uuids = $this->model::get($this->model->getKeyName())->pluck($this->model->getKeyName());
            } else {
                $uuids = $this->model::where('domain_uuid', session('domain_uuid'))
                    ->get($this->model->getKeyName())->pluck($this->model->getKeyName());
            }

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['All items selected']],
                'items' => $uuids,
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


    /**
     * Delete requested items
     *
     * @return \Illuminate\Http\Response
     */
    public function bulkDelete()
    {
        try {
            $deleted = MessageSetting::whereIn('sms_destination_uuid', request()->items)
                ->delete();

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Selected items deleted']],
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to delete selected items']]
            ], 500); // 500 Internal Server Error for any other errors
        }

        return response()->json([
            'success' => false,
            'errors' => ['server' => ['Failed to delete selected items']]
        ], 500); // 500 Internal Server Error for any other errors
    }

    /**
     * Bulk update requested items
     *
     * @param  \Illuminate\Http\BulkUpdateMessageSettingRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkUpdate(BulkUpdateMessageSettingRequest  $request)
    {

        try {
            // Prepare the data for updating
            $updateData = collect(request()->all())->only([
                'carrier', 'chatplan_detail_data', 'email', 'description'
            ])->filter(function ($value) {
                return $value !== null;
            })->toArray();

            $updated = MessageSetting::whereIn('sms_destination_uuid', request()->items)
                ->update($updateData);

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Selected items updated']],
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update selected items']]
            ], 500); // 500 Internal Server Error for any other errors
        }

        return response()->json([
            'success' => false,
            'errors' => ['server' => ['Failed to update selected items']]
        ], 500); // 500 Internal Server Error for any other errors
    }
}
