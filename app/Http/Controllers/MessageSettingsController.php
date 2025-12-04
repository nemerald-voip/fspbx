<?php

namespace App\Http\Controllers;

use Exception;
use Inertia\Inertia;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\MessageSetting;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
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
                'routes' => [
                    'current_page' => route('messages.settings'),
                    'data_route' => route('messages.settings.data'),
                    'item_options' => route('messages.settings.item.options'),
                    'store' => route('messages.settings.store'),
                    'select_all' => route('messages.settings.select.all'),
                    'bulk_delete' => route('messages.settings.bulk.delete'),
                    'bulk_update' => route('messages.settings.bulk.update'),
                ],
            ]
        );
    }

    public function getItemOptions()
    {
        try {
            $itemUuid = request('itemUuid');

            $routes = [];

            if ($itemUuid) {

                $item = QueryBuilder::for(MessageSetting::class)
                    ->select([
                        'sms_destination_uuid',
                        'domain_uuid',
                        'destination',
                        'carrier',
                        'description',
                        'chatplan_detail_data',
                        'email',
                    ])
                    ->whereKey($itemUuid)
                    ->firstOrFail();


                $routes = array_merge($routes, [
                    'update_route' => route('messages.settings.update', ['setting' => $itemUuid]),
                ]);
            }

            // Define the options for the 'carrier' field
            $carrierOptions = [
                ['value' => 'bandwidth', 'label' => 'Bandwidth'],
                ['value' => 'clicksend', 'label' => 'ClickSend'],
                ['value' => 'thinq', 'label' => 'Commio (ThinQ)'],
                ['value' => 'sinch', 'label' => 'Sinch'],
                ['value' => 'telnyx', 'label' => 'Telnyx'],
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
                    'label' => $extension->name_formatted,
                ];
            }

            $routes = array_merge($routes, [
                'store_route' => route('messages.settings.store'),
                'bulk_delete' => route('messages.settings.bulk.delete'),
            ]);

            // Construct the itemOptions object
            $itemOptions = [
                'item' => $item ?? null,
                'carrier' => $carrierOptions,
                'chatplan_detail_data' => $chatplanDetailDataOptions,
                'routes' => $routes,
                // Define options for other fields as needed
            ];

            return $itemOptions;
        } catch (\Exception $e) {
            logger('MessageSettingsController@getItemOptions error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to get item details']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }

    public function getData()
    {

        $perPage = 50;
        $currentDomain = session('domain_uuid');

        // If the filter is not present, assign default value before QueryBuilder
        if (!request()->has('filter.showGlobal')) {
            request()->merge([
                'filter' => array_merge(
                    request()->input('filter', []),
                    ['showGlobal' => false]
                ),
            ]);
        }

        $data = QueryBuilder::for(MessageSetting::class)
            ->select([
                'sms_destination_uuid',
                'destination',
                'carrier',
                'enabled',
                'description',
                'chatplan_detail_data',
                'email',
                'domain_uuid',

            ])
            ->with('domain:domain_uuid,domain_name,domain_description')
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    if ($value === null || $value === '') return;

                    $term = '%' . $value . '%';

                    // Use ILIKE for Postgres; if MySQL, change to 'like'
                    $query->where(function ($q) use ($term) {
                        $q->where('destination', 'ILIKE', $term)
                            ->orWhere('carrier', 'ILIKE', $term)
                            ->orWhere('email', 'ILIKE', $term)
                            ->orWhere('chatplan_detail_data', 'ILIKE', $term);
                    });
                }),

                AllowedFilter::callback('showGlobal', function ($query, $value) use ($currentDomain) {
                    if (!$value || $value === '0' || $value === 0 || $value === false) {
                        $query->where('domain_uuid', $currentDomain);
                    }
                }),
            ])

            ->allowedSorts(['destination'])
            ->defaultSort('destination')
            ->paginate($perPage)
            ->withQueryString();

        $rows = $data->getCollection();

        if (request('filter.showGlobal')) {
            $domainUuids = Session::get('domains')->pluck('domain_uuid');
            $extensions = Extensions::whereIn('domain_uuid', $domainUuids)
                ->get(['domain_uuid', 'extension', 'effective_caller_id_name']);
        } else {
            $extensions = Extensions::where('domain_uuid', session('domain_uuid'))
                ->get(['domain_uuid', 'extension', 'effective_caller_id_name']);
        }

        foreach ($rows as $destination) {
            $match = $extensions->first(function ($ext) use ($destination) {
                return $ext->domain_uuid === $destination->domain_uuid
                    && $ext->extension === $destination->chatplan_detail_data;
            });

            $destination->extension = $match;
        }

        // Set modified collection back into paginator
        $data->setCollection($rows);

        // logger($data);

        return $data;
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
        $inputs = $request->validated();

        if (!$setting) {
            return response()->json([
                'success' => false,
                'errors' => ['model' => ['Item not found']]
            ], 404);
        }

        try {
            DB::beginTransaction();

            $setting->update($inputs);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Settings updated succesfully.']]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            logger('MesssageSettingsController@update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to update this device']]
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\CreateMessageSettingRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateMessageSettingRequest $request)
    {
        $data = $request->validated();

        try {
            DB::beginTransaction();

            $newSetting = new MessageSetting($data);
            $newSetting->save();

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Request processed successfully.']]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            logger('DeviceController@store error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to create device']]
            ], 500);
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
    public function BulkDelete()
    {
        try {
            // Begin Transaction
            DB::beginTransaction();

            // Retrieve all items
            $items = $this->model::whereIn('sms_destination_uuid', request('items'))->get();

            foreach ($items as $item) {

                // Delete the item itself
                $item->delete();
            }

            // Commit Transaction
            DB::commit();

            return response()->json([
                'messages' => ['server' => ['All selected items have been deleted successfully.']],
            ], 200);
        } catch (\Exception $e) {
            // Rollback Transaction if any error occurs
            DB::rollBack();

            logger('MessageSettingsControler@bulkDelete error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Server returned an error while deleting the selected items.']]
            ], 500); // 500 Internal Server Error for any other errors
        }
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
                'carrier',
                'chatplan_detail_data',
                'email',
                'description'
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
