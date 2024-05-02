<?php

namespace App\Http\Controllers;

use Exception;
use Inertia\Inertia;
use App\Models\Messages;
use App\Models\Extensions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\SmsDestinations;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use App\Services\SynchMessageProvider;
use App\Services\CommioMessageProvider;
use Illuminate\Support\Facades\Session;
use libphonenumber\NumberParseException;
use Propaganistas\LaravelPhone\PhoneNumber;

class MessagesController extends Controller
{

    public $model;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Messages';
    protected $searchable = ['source', 'destination', 'message'];

    public function __construct()
    {
        $this->model = new Messages();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // $statuses = ['all' => 'Show All', 'success' => 'Success', 'waiting' => 'Waiting', 'emailed' => 'Emailed'];
        // $selectedStatus = $request->get('status');
        // $searchString = $request->get('search');

        // $searchPeriod = $request->get('period');

        // $period = [
        //     Carbon::now()->startOfDay()->subDays(30),
        //     Carbon::now()->endOfDay()
        // ];

        // $timeZone = get_local_time_zone(Session::get('domain_uuid'));

        // if (preg_match('/^(0[1-9]|1[1-2])\/(0[1-9]|1[0-9]|2[0-9]|3[0-1])\/([1-9+]{2})\s(0[0-9]|1[0-2]:([0-5][0-9]?\d))\s(AM|PM)\s-\s(0[1-9]|1[1-2])\/(0[1-9]|1[0-9]|2[0-9]|3[0-1])\/([1-9+]{2})\s(0[0-9]|1[0-2]:([0-5][0-9]?\d))\s(AM|PM)$/', $searchPeriod)) {
        //     $date = explode("-", $searchPeriod);
        //     $period[0] = Carbon::createFromFormat('m/d/y h:i A', trim($date[0]),$timeZone)->setTimezone('UTC');
        //     $period[1] = Carbon::createFromFormat('m/d/y h:i A', trim($date[1]),$timeZone)->setTimezone('UTC');
        // }

        // $messages = Messages::latest();
        // if (array_key_exists($selectedStatus, $statuses) && $selectedStatus != 'all') {
        //     $messages->where('status', $selectedStatus);
        // }
        // if ($searchString) {
        //     $messages->where(function ($query) use ($searchString) {
        //         $query
        //             // ->orWhereLike('source', strtolower($searchString))
        //             // ->orWhereLike('destination', strtolower($searchString))
        //             ->orWhereLike('message', strtolower($searchString));

        //             try {
        //                 $phoneNumberUtil = PhoneNumberUtil::getInstance();
        //                 $phoneNumberObject = $phoneNumberUtil->parse($searchString, 'US');
        //                 if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
        //                     $query->orWhereLike('source', str_replace('+1','',$phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164)));
        //                     $query->orWhereLike('destination', str_replace('+1','',$phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164)));
        //                 } else {
        //                     $query->orWhereLike('source', strtolower($searchString));
        //                     $query->orWhereLike('destination', strtolower($searchString));
        //                 }
        //             } catch (NumberParseException $e) {
        //                 $query->orWhereLike('source', strtolower($searchString));
        //                 $query->orWhereLike('destination', strtolower($searchString));
        //             }
        //     });
        // }
        // $messages->whereBetween('created_at', $period);

        // $messages = $messages->paginate(50)->onEachSide(1);

        // // Get local Time Zone
        // $time_zone = get_local_time_zone(Session::get('domain_uuid'));

        // //Get libphonenumber object
        // $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        // foreach ($messages as $message){
        //     //Validate source number
        //     if ($message['source']){
        //         $phoneNumberObject = $phoneNumberUtil->parse($message['source'], 'US');
        //         if ($phoneNumberUtil->isValidNumber($phoneNumberObject)){
        //             $message->source = $phoneNumberUtil
        //                 ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL);
        //         }
        //     }
        //     //Validate destination number
        //     $phoneNumberObject = $phoneNumberUtil->parse($message['destination'], 'US');
        //     if ($phoneNumberUtil->isValidNumber($phoneNumberObject)){
        //         $message->destination = $phoneNumberUtil
        //             ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL);
        //     }

        //     // Try to convert the date to human redable format
        //     // $message->date = Carbon::parse($message->created_at)->setTimezone($time_zone)->toDayDateTimeString();
        //     $message->date = Carbon::parse($message->created_at)->setTimezone($time_zone);

        // }

        // $data=array();
        // // $domain_uuid=Session::get('domain_uuid');
        // $data['messages'] = $messages;

        // //assign permissions
        // $permissions = array();
        // // $permissions['add_new'] = userCheckPermission('voicemail_add');
        // // $permissions['edit'] = userCheckPermission('voicemail_edit');
        // // $permissions['delete'] = userCheckPermission('voicemail_delete');

        // $data['permissions'] = $permissions;

        // $data['searchString'] = $searchString;

        // $data['statuses'] = $statuses;
        // $data['selectedStatus'] = $selectedStatus;

        // if ($searchPeriod) {
        //     $data['searchPeriodStart'] = $period[0]->setTimezone($time_zone)->format('m/d/y h:i A');
        //     $data['searchPeriodEnd'] = $period[1]->setTimezone($time_zone)->format('m/d/y h:i A');
        // } else {
        //     $data['searchPeriodStart'] = $period[0]->format('m/d/y h:i A');
        //     $data['searchPeriodEnd'] = $period[1]->format('m/d/y h:i A');
        // }

        // // $data['searchPeriodStart'] = Carbon::createFromFormat('m/d/y h:i A', trim($date[0]))->setTimezone('UTC')->format('m/d/y h:i A');
        // // $data['searchPeriodEnd'] = Carbon::createFromFormat('m/d/y h:i A', trim($date[1]))->setTimezone('UTC')->format('m/d/y h:i A');;
        // $data['searchPeriod'] = implode(" - ", [$data['searchPeriodStart'], $data['searchPeriodEnd']]);

        // return view('layouts.messages.list')
        //     ->with($data);
        //     // ->with('permissions',$permissions);  


        // if (!userCheckPermission("device_view")) {
        //     return redirect('/');
        // }

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },
                'showGlobal' => function () {
                    return request('filterData.showGlobal') === 'true';
                },
                // 'itemData' => Inertia::lazy(
                //     fn () =>
                //     $this->getItemData()
                // ),
                // 'itemOptions' => Inertia::lazy(
                //     fn () =>
                //     $this->getItemOptions()
                // ),
                'routes' => [
                    'current_page' => route('messages.index'),
                    'store' => route('messages.store'),
                    'select_all' => route('messages.select.all'),
                    'bulk_delete' => route('messages.bulk.delete'),
                    'bulk_update' => route('messages.bulk.update'),
                    'retry' => route('messages.retry'),
                ]
            ]
        );
    }


    /**
     *  Get device data
     */
    public function getData($paginate = 50)
    {

        // Check if search parameter is present and not empty
        if (!empty(request('filterData.search'))) {
            $this->filters['search'] = request('filterData.search');
        }

        // Check if showGlobal parameter is present and not empty
        if (!empty(request('filterData.showGlobal'))) {
            $this->filters['showGlobal'] = request('filterData.showGlobal') === 'true';
        } else {
            $this->filters['showGlobal'] = null;
        }

        // Add sorting criteria
        $this->sortField = request()->get('sortField', 'created_at'); // Default to 'created_at'
        $this->sortOrder = request()->get('sortOrder', 'desc'); // Default to descending

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
            // $extensions = Extensions::whereIn('domain_uuid', $domainUuids)
            //     ->get(['domain_uuid', 'extension', 'effective_caller_id_name']);
        } else {
            // get extensions for session domain
            // $extensions = Extensions::where('domain_uuid', session('domain_uuid'))
            //     ->get(['domain_uuid', 'extension', 'effective_caller_id_name']);
        }

        // foreach ($data as $device) {
        //     // Check each line in the device if it exists
        //     $device->lines->each(function ($line) use ($extensions, $device) {
        //         // Find the first matching extension
        //         $firstMatch = $extensions->first(function ($extension) use ($line, $device) {
        //             return $extension->domain_uuid === $device->domain_uuid && $extension->extension === $line->label;
        //         });

        //         // Assign the first matching extension to the line
        //         $line->extension = $firstMatch;
        //     });
        //     // logger($device->lines);
        // }

        // Get local Time Zone
        $time_zone = get_local_time_zone(Session::get('domain_uuid'));

        logger($time_zone);

        return $data;
    }

    /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {
        $data =  $this->model::query();

        if (isset($filters['showGlobal']) and $filters['showGlobal']) {
            $data->with(['domain' => function ($query) {
                $query->select('domain_uuid', 'domain_name', 'domain_description'); // Specify the fields you need
            }]);
            // Access domains through the session and filter devices by those domains
            $domainUuids = Session::get('domains')->pluck('domain_uuid');
            $data->whereHas('domain', function ($query) use ($domainUuids) {
                $query->whereIn($this->model->getTable() . '.domain_uuid', $domainUuids);
            });
        } else {
            // Directly filter devices by the session's domain_uuid
            $domainUuid = Session::get('domain_uuid');
            $data = $data->where($this->model->getTable() . '.domain_uuid', $domainUuid);
        }

        // $data->with(['profile' => function ($query) {
        //     $query->select('device_profile_uuid', 'device_profile_name', 'device_profile_description');
        // }]);

        // $data->with(['lines' => function ($query) {
        //     $query->select('domain_uuid', 'device_line_uuid', 'device_uuid', 'line_number', 'label');
        // }]);

        $data->select(
            'message_uuid',
            'extension_uuid',
            'domain_uuid',
            'source',
            'destination',
            'message',
            'direction',
            'type',
            'status',
            'created_at'

        );

        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    $this->$method($data, $value);
                }
            }
        }

        // Apply sorting
        $data->orderBy($this->sortField, $this->sortOrder);

        return $data;
    }

    /**
     * @param $query
     * @param $value
     * @return void
     */
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


    public function retry()
    {
        try {

            logger('retry');

            // // Get a collection of SIP registrations 
            // $regs = sipRegistrations();

            //Get items info as a collection
            $items = $this->model::whereIn($this->model->getKeyName(), request('items'))
                ->get();


            // logger($items);

            foreach ($items as $item) {
                // get originating extension
                $extension = Extensions::find($item->extension_uuid);

                if (!$extension) {
                    throw new Exception('Extension not found');
                }

                //Get message config
                $phoneNumberSmsConfig = $this->getPhoneNumberSmsConfig($extension->extension, $item->domain_uuid);
                $carrier =  $phoneNumberSmsConfig->carrier;
                logger($carrier);

                //Determine message provider
                $messageProvider = $this->getMessageProvider($carrier);

                //Store message in the log database
                $item->status = "Queued";
                $item->save();

                // Send message
                $messageProvider->send($item->message_uuid);
            }

            // logger($devices);



            // Return a JSON response indicating success
            return response()->json([
                'messages' => ['success' => ['Selected message(s) scheduled for sending']]
            ], 201);
        } catch (\Exception $e) {
            logger($e->getMessage() . PHP_EOL);
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }


    private function getPhoneNumberSmsConfig($from, $domainUuid)
    {
        $phoneNumberSmsConfig = SmsDestinations::where('domain_uuid', $domainUuid)
            ->where('chatplan_detail_data', $from)
            ->first();

        if (!$phoneNumberSmsConfig) {
            throw new \Exception("SMS configuration not found for extension " . $from);
        }

        return $phoneNumberSmsConfig;
    }

    private function getMessageProvider($carrier)
    {
        switch ($carrier) {
            case 'thinq':
                return new CommioMessageProvider();
            case 'synch':
                return new SynchMessageProvider();
                // Add cases for other carriers
            default:
                throw new \Exception("Unsupported carrier");
        }
    }
}
