<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Extensions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Http\Requests\UpdateMessageSettingRequest;
use App\Models\MessageSetting;

class MessageSettingsController extends Controller
{
    public $model = 'App\Models\MessageSetting';
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'MessageSettings';
    protected $searchable = ['destination', 'carrier', 'description', 'chatplan_detail_data', 'email'];


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        logger($request->all());
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
                'itemData' => Inertia::lazy(
                    fn () =>
                    $this->getItemData()
                ),
                'itemOptions' => Inertia::lazy(
                    fn () =>
                    $this->getItemOptions()
                ),
                'extensionData' => Inertia::lazy(
                    fn () =>
                    $this->getExtensionData()
                ),
                'url' => route('messages.settings'),
            ]
        );
    }

    public function getExtensionData()
    {

        // Define the options for the 'chatplan_detail_data' field
        $extensions = Extensions::where('domain_uuid', session('domain_uuid'))
            ->get([
                'extension',
                'effective_caller_id_name',
            ]);

        return $extensions;
    }

    public function getItemData()
    {
        $itemData = $this->model::findOrFail(request('itemUuid'));
        return $itemData;
    }

    public function getItemOptions()
    {
        // Define the options for the 'carrier' field
        $carrierOptions = [
            ['value' => 'thinq', 'label' => 'ThinQ'],
            ['value' => 'synch', 'label' => 'Synch'],
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
            $this->filters['showGlobal'] = request('filterData.showGlobal');
        }
        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'destination'); // Default to 'destination'
        $this->sortOrder = request()->get('sortOrder', 'desc'); // Default to ascending

        $data = $this->builder($this->filters);

        // Apply pagination if requested
        if ($paginate) {
            $data = $data->paginate($paginate);
        } else {
            $data = $data->get(); // This will return a collection
        }

        // $data->transform(function ($item) {
        //     $item->start_date = $item->start_date;
        //     $item->start_time = $item->start_time;

        //     return $item;
        // });
        // logger($data);
        return $data;
    }


    public function builder($filters = [])
    {
        $data =  $this->model::query();

        if (isset($filters['showGlobal']) and $filters['showGlobal']) {
            // Access domains through the session and filter devices by those domains
            $domainUuids = Session::get('domains')->pluck('domain_uuid');
            $data->whereHas('domain', function ($query) use ($domainUuids) {
                $query->whereIn('domain_uuid', $domainUuids);
            });
        } else {
            // Directly filter devices by the session's domain_uuid
            $domainUuid = Session::get('domain_uuid');
            $data->where('domain_uuid', $domainUuid);
        }
        // logger($data->toSql());

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

        logger($setting);
        //
        // return response()->json([
        //     'status' => 'success',
        //     'message' => 'Device has been updated.'
        // ]);
        // return back();

        // try {
        //     // Attempt to find and update the organization
        //     $organization = Organization::findOrFail($id);
        //     $organization->update($validatedData);
    
        //     // Return a JSON response indicating success
        //     return response()->json([
        //         'success' => true,
        //         'data' => new OrganizationResource($organization), // Optionally use a Resource for data transformation
        //         'message' => 'Organization updated successfully'
        //     ], 200); // 200 OK for successful resource updates
    
        // } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        //     // If the organization is not found, return an error response
        //     return response()->json([
        //         'success' => false,
        //                     'errors' => ['organization' => ['Organization not found']]
        //     ], 404); // 404 Not Found if the organization does not exist
        // } catch (\Exception $e) {
        //     // Handle any other exception that may occur
            // return response()->json([
            //     'success' => false,
            //     'errors' => ['server' => ['Failed to update organization: ' ]]
            // ], 500); // 500 Internal Server Error for any other errors
        // }

        return response()->json([
            'success' => true,
        ], 200); 
        
    }
}
