<?php

namespace App\Http\Controllers;

use Exception;
use Inertia\Inertia;
use App\Mail\SmsToEmail;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Models\MessageSetting;
use Illuminate\Support\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Services\FreeswitchEslService;
use Illuminate\Support\Facades\Session;
use App\Jobs\SendSmsNotificationToSlack;
use Illuminate\Pagination\LengthAwarePaginator;

class RegistrationsController extends Controller
{

    public $eslService;
    public $filters = [];
    public $sortField;
    public $sortOrder;
    protected $viewName = 'Registrations';
    protected $searchable = ['source', 'destination', 'message'];

    public function __construct(FreeswitchEslService $eslService)
    {
        // $this->model = new Messages();
        $this->eslService = $eslService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        return Inertia::render(
            $this->viewName,
            [
                'data' => function () {
                    return $this->getData();
                },
                'showGlobal' => function () {
                    return request('filterData.showGlobal') === 'true';
                },

                'routes' => [
                    'current_page' => route('registrations.index'),
                    'select_all' => route('registrations.select.all'),
                    // 'bulk_delete' => route('messages.bulk.delete'),
                    // 'bulk_update' => route('messages.bulk.update'),
                    // 'retry' => route('messages.retry'),
                ]
            ]
        );
    }


    /**
     *  Get data
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

        $data = $this->builder($this->filters);

        // Apply pagination manually
        if ($paginate) {
            $data = $this->paginateCollection($data, $paginate);
        }

        logger($data);

        return $data;
    }

        /**
     * @param  array  $filters
     * @return Builder
     */
    public function builder(array $filters = [])
    {

        // get a list of current registrations
        $data = $this->eslService->getAllSipRegistrations();

        // Apply sorting using sortBy or sortByDesc depending on the sort order
        if ($this->sortOrder === 'asc') {
            $data = $data->sortBy($this->sortField);
        } else {
            $data = $data->sortByDesc($this->sortField);
        }

        if (is_array($filters)) {
            foreach ($filters as $field => $value) {
                if (method_exists($this, $method = "filter" . ucfirst($field))) {
                    // Pass the collection by reference to modify it directly
                    $data = $this->$method($data, $value);
                }
            }
        }

        // logger($data);

        return $data->values(); // Ensure re-indexing of the collection
    }

        /**
     * Paginate a given collection.
     *
     * @param \Illuminate\Support\Collection $items
     * @param int $perPage
     * @param int|null $page
     * @param array $options
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginateCollection($items, $perPage = 50, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage),
            $items->count(),
            $perPage,
            $page,
            $options
        );

        // Manually set the path to the current route with proper parameters
        $paginator->setPath(url()->current());

        return $paginator;
    }

    /**
     * @param $collection
     * @param $value
     * @return void
     */
    protected function filterSearch($collection, $value)
    {
        $searchable = $this->searchable;

        // Case-insensitive partial string search in the specified fields
        $collection = $collection->filter(function ($item) use ($value, $searchable) {
            foreach ($searchable as $field) {
                if (stripos($item[$field], $value) !== false) {
                    return true;
                }
            }
            return false;
        });

        return $collection;
    }


    public function retry()
    {
        try {
            //Get items info as a collection
            $items = $this->model::whereIn($this->model->getKeyName(), request('items'))
                ->get();

            foreach ($items as $item) {
                // get originating extension
                $extension = Extensions::find($item->extension_uuid);

                // check if there is an email destination
                $messageSettings = MessageSetting::where('domain_uuid', $item->domain_uuid)
                    ->where('destination', $item->destination)
                    ->first();

                if (!$extension && !$messageSettings && !$messageSettings->email) {
                    throw new Exception('No assigned destination found.');
                }


                if ($item->direction == "out") {

                    //Get message config
                    $phoneNumberSmsConfig = $this->getPhoneNumberSmsConfig($extension->extension, $item->domain_uuid);
                    $carrier =  $phoneNumberSmsConfig->carrier;
                    // logger($carrier);

                    //Determine message provider
                    $messageProvider = $this->getMessageProvider($carrier);

                    //Store message in the log database
                    $item->status = "Queued";
                    $item->save();

                    // Send message
                    $messageProvider->send($item->message_uuid);
                }

                if ($item->direction == "in") {
                    $org_id = DomainSettings::where('domain_uuid', $item->domain_uuid)
                        ->where('domain_setting_category', 'app shell')
                        ->where('domain_setting_subcategory', 'org_id')
                        ->value('domain_setting_value');

                    if (is_null($org_id)) {
                        throw new \Exception("From: " . $item->source . " To: " . $item->destination . " \n Org ID not found");
                    }

                    if ($extension) {
                        // Logic to deliver the SMS message using a third-party Ringotel API,
                        try {
                            $response = Http::ringotel_api()
                                ->withBody(json_encode([
                                    'method' => 'message',
                                    'params' => [
                                        'orgid' => $org_id,
                                        'from' => $item->source,
                                        'to' => $extension->extension,
                                        'content' => $item->message
                                    ]
                                ]), 'application/json')
                                ->post('/')
                                ->throw()
                                ->json();

                            $this->updateMessageStatus($item, $response);
                        } catch (\Throwable $e) {
                            logger("Error delivering SMS to Ringotel: {$e->getMessage()}");
                            SendSmsNotificationToSlack::dispatch("*Inbound SMS Failed*. From: " . $item->source . " To: " . $item->extension . "\nError delivering SMS to Ringotel")->onQueue('messages');
                            return false;
                        }
                    }

                    if ($messageSettings && $messageSettings->email) {
                        $attributes['orgid'] = $org_id;
                        $attributes['from'] = $item->source;
                        $attributes['email_to'] = $messageSettings->email;
                        $attributes['message'] = $item->message;
                        $attributes['email_subject'] = 'SMS Notification: New Message from ' . $item->source;
                        // $attributes['smtp_from'] = config('mail.from.address');

                        // Logic to deliver the SMS message using email
                        // This method should return a boolean indicating whether the message was sent successfully.
                        Mail::to($messageSettings->email)->send(new SmsToEmail($attributes));

                        if ($item->status = "queued") {
                            $item->status = 'emailed';
                        }
                        $item->save();
                    }
                }
            }

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
}
