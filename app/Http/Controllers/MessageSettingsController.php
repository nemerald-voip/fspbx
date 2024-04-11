<?php

namespace App\Http\Controllers;

use App\Models\Extensions;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

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
                'url' => route('messages.settings'),
            ]
        );
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

}
