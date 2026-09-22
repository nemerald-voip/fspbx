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
use App\Services\Messaging\MessageParticipantService;
use App\Services\Messaging\MessageSettingsAccess;

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
                'permissions' => ['manage' => MessageSettingsAccess::canManage()],
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
        abort_unless(userCheckPermission('message_settings_list_view'), 403);
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
                    ->whereIn('domain_uuid', MessageSettingsAccess::domains())
                    ->firstOrFail();

                $item->allowed_extension_uuids = app(MessageParticipantService::class)->assigned($item)->pluck('extension_uuid')->all();


                $routes = array_merge($routes, [
                    'update_route' => route('messages.settings.update', ['setting' => $itemUuid]),
                ]);
            }

            // Define the options for the 'carrier' field
            $carrierOptions = [
                ['value' => 'apidaze', 'label' => 'Apidaze'],
                ['value' => 'bandwidth', 'label' => 'Bandwidth'],
                ['value' => 'bulkvs', 'label' => 'BulkVS'],
                ['value' => 'clicksend', 'label' => 'ClickSend'],
                ['value' => 'thinq', 'label' => 'Commio (ThinQ)'],
                ['value' => 'fibernetics', 'label' => 'Fibernetics'],
                ['value' => 'sinch', 'label' => 'Sinch'],
                ['value' => 'telnyx', 'label' => 'Telnyx'],
                ['value' => 'twilio', 'label' => 'Twilio'],
                ['value' => 'voipms', 'label' => 'VoIP.MS'],
                ['value' => 'voxutel', 'label' => 'Voxutel'],
            ];

            // Define the options for the 'chatplan_detail_data' field
            $extensions = Extensions::where('domain_uuid', $item->domain_uuid ?? session('domain_uuid'))
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
                'extensions' => $extensions->map(fn ($ext) => ['value' => $ext->extension_uuid, 'label' => $ext->name_formatted])->values(),
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
        abort_unless(userCheckPermission('message_settings_list_view'), 403);

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
            ->whereIn('domain_uuid', MessageSettingsAccess::domains())
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
            $domainUuids = MessageSettingsAccess::domains();
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
            $destination->allowed_extensions = app(MessageParticipantService::class)->assigned($destination)
                ->map(fn ($ext) => ['value' => $ext->extension_uuid, 'label' => $ext->name_formatted])->values();
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

            $setting = MessageSetting::whereKey($setting->getKey())->lockForUpdate()->firstOrFail();
            $this->saveSetting($setting, $inputs);

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

            $newSetting = new MessageSetting();
            $this->saveSetting($newSetting, $data);

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
        abort_unless(userCheckPermission('message_settings_list_view'), 403);
        try {
            if (request()->get('showGlobal')) {
                $uuids = $this->model::whereIn('domain_uuid', MessageSettingsAccess::domains())->pluck($this->model->getKeyName());
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
        abort_unless(MessageSettingsAccess::canManage(), 403);
        try {
            // Begin Transaction
            DB::beginTransaction();

            // Retrieve all items
            $items = $this->model::whereIn('domain_uuid', MessageSettingsAccess::domains())
                ->whereIn('sms_destination_uuid', request('items', []))->lockForUpdate()->get();

            foreach ($items as $item) {

                // Delete the item itself
                DB::table('sms_destination_members')->where('domain_uuid', $item->domain_uuid)
                    ->where('sms_destination_uuid', $item->sms_destination_uuid)->delete();
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
            $updateData = collect($request->validated())->only([
                'carrier',
                'chatplan_detail_data',
                'allowed_extension_uuids',
                'email',
                'description'
            ])->filter(function ($value) {
                return $value !== null;
            })->toArray();

            DB::transaction(function () use ($request, $updateData) {
                $items = MessageSetting::whereIn('domain_uuid', MessageSettingsAccess::domains())
                    ->whereIn('sms_destination_uuid', $request->validated('items'))->lockForUpdate()->get();
                foreach ($items as $item) {
                    $this->saveSetting($item, $updateData);
                }
            });

            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Selected items updated']],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
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
    private function saveSetting(MessageSetting $setting, array $data): void
    {
        $hasMembers = array_key_exists('allowed_extension_uuids', $data);
        $members = $data['allowed_extension_uuids'] ?? [];
        unset($data['allowed_extension_uuids']);
        $legacyAssignment = array_key_exists('chatplan_detail_data', $data);
        $setting->fill($data);
        $setting->save();
        if (!$hasMembers && $legacyAssignment) {
            $members = Extensions::where('domain_uuid', $setting->domain_uuid)
                ->where('extension', $setting->chatplan_detail_data)->pluck('extension_uuid')->all();
        }
        if ($hasMembers || $legacyAssignment) {
            app(MessageParticipantService::class)->assign($setting, $members);
        }
    }
}
