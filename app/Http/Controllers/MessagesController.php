<?php

namespace App\Http\Controllers;

use Exception;
use Inertia\Inertia;
use App\Mail\SmsToEmail;
use App\Models\Messages;
use App\Models\Extensions;
use Illuminate\Http\Request;
use App\Models\DomainSettings;
use App\Models\MessageSetting;
use Illuminate\Support\Carbon;
use App\Models\SmsDestinations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Jobs\SendSmsNotificationToSlack;
use App\Factories\MessageProviderFactory;

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

        return Inertia::render(
            $this->viewName,
            [
                'routes' => [
                    'roomsIndex'   => route('messages.rooms'),
                    'roomMessages' => route('messages.room.messages', ['roomId' => ':roomId']),
                    'sendMessage'  => route('messages.send'),
                ],
                'auth' => [
                    // Adjust these to however FS PBX exposes current extension
                    'currentExtensionUuid' => auth()->user()->extension_uuid ?? '',
                    'currentExtensionName' => session('extension_name') ?? 'You',
                ],
            ]
        );
    }


    public function rooms(Request $request)
    {
        $domainUuid = $this->currentDomainUuid();
        $extensionUuid = $this->currentExtensionUuid();


        $limit = min((int) $request->input('limit', 200), 500);
        $q = trim((string) $request->input('q', ''));

        $base = Messages::query()
            // ->where('domain_uuid', $domainUuid)
            // ->where('extension_uuid', $extensionUuid)
            ->selectRaw("
            message_uuid,
            message,
            direction,
            created_at,
            CASE WHEN direction = 'in' THEN source ELSE destination END AS external_number
        ");

        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('source', 'like', "%{$q}%")
                    ->orWhere('destination', 'like', "%{$q}%")
                    ->orWhere('message', 'like', "%{$q}%");
            });
        }

        $rows = DB::query()
            ->fromSub($base, 't')
            ->selectRaw("DISTINCT ON (external_number)
            external_number,
            message_uuid,
            message,
            direction,
            created_at
        ")
            ->orderBy('external_number')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $rooms = $rows->map(function ($r) use ($extensionUuid) {
            $external = (string) $r->external_number;

            return [
                'roomId' => $this->buildRoomId($extensionUuid, $external),
                'roomName' => $external,
                'unreadCount' => 0,
                'lastMessage' => [
                    '_id' => (string) $r->message_uuid,
                    'content' => (string) ($r->message ?? ''),
                    'timestamp' => optional($r->created_at)->toISOString() ?? null,
                ],
                'users' => [],
            ];
        })->values();

        return response()->json(['rooms' => $rooms]);
    }



    /**
     * Fetch messages for a specific room (Conversation between Extension and External Number)
     */
    public function roomMessages(Request $request, $roomId)
    {
        try {
            // 1. Context & Security
            $domainUuid = $this->currentDomainUuid();

            // 2. Parse the Room ID using your existing helper
            // Format is expected to be: {extension_uuid}:{external_number}
            $parts = $this->parseRoomId($roomId);
            $extensionUuid = $parts['extension_uuid'];
            $externalNumber = $parts['external'];

            // 3. Pagination Settings
            // Frontend sends: params: { 'page[size]': 50 }
            $pageSize = $request->input('page.size', 50);

            // 4. Query the Messages
            $query = Messages::query()
                ->where('domain_uuid', $domainUuid)
                // Ensure we only look at messages belonging to this specific extension
                // ->where('extension_uuid', $extensionUuid)
                // Filter for conversation with the external number (either inbound or outbound)
                ->where(function ($q) use ($externalNumber) {
                    $q->where('source', $externalNumber)
                        ->orWhere('destination', $externalNumber);
                })
                // Order by Newest First (Frontend reverses this to show history correctly)
                ->orderBy('created_at', 'desc');

            // 5. Execute Query
            $messages = $query->paginate($pageSize);

            // 6. Return JSON
            return response()->json([
                'messages' => $messages->items(),
                'meta' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'total' => $messages->total(),
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            // Handle invalid room ID format
            return response()->json(['error' => 'Invalid Room ID', 'messages' => []], 400);
        } catch (\Exception $e) {
            // Handle general errors
            logger($e->getMessage());
            return response()->json(['error' => 'Server Error', 'messages' => []], 500);
        }
    }



    private function currentDomainUuid(): string
    {
        $domain = session('domain_uuid');
        if (!$domain) {
            throw new \Exception('domain_uuid not found in session');
        }
        return (string) $domain;
    }

    private function currentExtensionUuid(): string
    {
        // Prefer auth user
        $user = auth()->user();
        if ($user && !empty($user->extension_uuid)) {
            return (string) $user->extension_uuid;
        }

        throw new \Exception('extension_uuid not found (auth or session)');
    }

    private function buildRoomId(string $extensionUuid, string $externalE164): string
    {
        return $extensionUuid . ':' . $externalE164;
    }

    private function parseRoomId(string $roomId): array
    {
        $parts = explode(':', $roomId, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException("Invalid roomId: {$roomId}");
        }
        return [
            'extension_uuid' => $parts[0],
            'external' => $parts[1],
        ];
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
                    $messageProvider = MessageProviderFactory::make($carrier);

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

    private function updateMessageStatus($message, $response)
    {
        if (isset($response['result']) && !empty($response['result'])) {
            if (isset($response['result']['messageid'])) {
                $message->status = 'success';
                $message->reference_id = $response['result']['messageid'];
            } else {
                $message->status = 'failed';
                $errorDetail = json_encode($response['result']);
                SendSmsNotificationToSlack::dispatch("*Commio Inbound SMS Failed*.From: " . $this->source . " To: " . $this->extension . "\nRingotel API Error: No message ID received. Details: " . $errorDetail)->onQueue('messages');
            }
        } else {
            $message->status = 'failed';
            $errorDetail = isset($response['error']) ? json_encode($response['error']) : 'Unknown error';
            SendSmsNotificationToSlack::dispatch("*Commio Inbound SMS Failed*.From: " . $this->source . " To: " . $this->extension . "\nRingotel API Failure: " . $errorDetail)->onQueue('messages');
        }
        $message->save();
    }

    public function logs()
    {
        $params = request()->all();
        $params['paginate'] = 50;
        $domain_uuid = session('domain_uuid');
        $params['domain_uuid'] = $domain_uuid;

        if (!empty(request('filter.dateRange'))) {
            $startPeriod = Carbon::parse(request('filter.dateRange')[0])->setTimeZone('UTC');
            $endPeriod = Carbon::parse(request('filter.dateRange')[1])->setTimeZone('UTC');
        }

        $params['filter']['startPeriod'] = $startPeriod;
        $params['filter']['endPeriod'] = $endPeriod;

        unset(
            $params['filter']['dateRange'],
        );

        $data = QueryBuilder::for(Messages::class, request()->merge($params))
            ->select([
                'message_uuid',
                'extension_uuid',
                'domain_uuid',
                'source',
                'destination',
                'message',
                'direction',
                'type',
                'status',
                'created_at',
            ])

            ->allowedFilters([
                AllowedFilter::callback('startPeriod', function ($query, $value) {
                    $query->where('created_at', '>=', $value);
                }),
                AllowedFilter::callback('endPeriod', function ($query, $value) {
                    $query->where('created_at', '<=', $value);
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('source', 'ilike', "%{$value}%")
                            ->orWhere('destination', 'ilike', "%{$value}%")
                            ->orWhere('message', 'ilike', "%{$value}%");
                    });
                }),
            ])
            // Sorting
            ->allowedSorts(['created_at']) // add more if needed
            ->defaultSort('-created_at');

        if ($params['paginate']) {
            $data = $data->paginate($params['paginate']);
        } else {
            $data = $data->cursor();
        }

        logger($data);

        return $data;
    }
}
