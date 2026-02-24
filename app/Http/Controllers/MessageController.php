<?php

namespace App\Http\Controllers;

use App\Factories\MessageProviderFactory;
use App\Jobs\SendSmsNotificationToSlack;
use App\Mail\SmsToEmail;
use App\Models\DomainSettings;
use App\Models\Extensions;
use App\Models\Messages;
use App\Models\MessageSetting;
use App\Models\SmsDestinations;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use libphonenumber\PhoneNumberFormat;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class MessageController extends Controller
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
                    'data_route'   => route('messages.data'),
                ],
            ]
        );
    }

    public function getData()
    {
        try {
            $extension_uuid = auth()->user()->extension_uuid;
            $domain_uuid = request('domain_uuid') ?? session('domain_uuid');

            // 1. Fetch All Extensions (Query #1)
            $extensions = Extensions::where('domain_uuid', $domain_uuid)
                ->orderBy('extension')
                ->get([
                    'extension_uuid',
                    'extension',
                    'effective_caller_id_name',
                    // add other fields needed for name_formatted accessor
                ]);

            // 2. Fetch All SMS Destinations for this Domain (Query #2)
            // We select 'chatplan_detail_data' because that links to the extension number
            $allDids = SmsDestinations::where('domain_uuid', $domain_uuid)
                ->whereNotNull('chatplan_detail_data')
                ->get(['destination', 'description', 'chatplan_detail_data']);

            // 3. Group DIDs by extension number in memory
            // This creates a Key-Value pair where Key = Extension Number, Value = Collection of DIDs
            $didsGrouped = $allDids->groupBy('chatplan_detail_data');

            // 4. Map extensions (Zero DB queries here)
            $extensionOptions = $extensions->map(function ($ext) use ($extension_uuid, $didsGrouped) {

                // O(1) Lookup from the grouped collection
                // If no DIDs found, return an empty collection
                $myDids = $didsGrouped->get($ext->extension, collect());

                $isMe = $ext->extension_uuid === $extension_uuid;

                return [
                    'value' => $ext->extension_uuid,
                    'name' => $isMe ? "{$ext->name_formatted} (Me)" : $ext->name_formatted,
                    'is_me' => $isMe,

                    // Map the DIDs found in memory
                    'dids' => $myDids->map(function ($did) {
                        return [
                            'number' => $did->destination,
                            'label' => $did->description ?? null
                        ];
                    })->values()->all() // Ensure clean array
                ];
            })
                ->sortByDesc('is_me')
                ->values();

            return [
                'extension_uuid' => $extension_uuid,
                'extensions' => $extensionOptions,
            ];
        } catch (\Exception $e) {
            logger('MessagesController@getData error: ' . $e->getMessage());
            return response()->json(['success' => false, 'errors' => ['server' => ['Failed to get data']]], 500);
        }
    }


    public function rooms(Request $request)
    {
        $domainUuid = $this->currentDomainUuid();
        $targetExtensionUuid = $request->input('extension_uuid');
        $limit = min((int) $request->input('limit', 50), 200);
        $search = trim((string) $request->input('q', ''));

        // 1. Build Base Query
        // Since data is E.164, we can simply switch columns based on direction
        $base = Messages::query()
            ->selectRaw("
            message_uuid,
            message,
            created_at,
            extension_uuid,
            -- LOCAL: The number BELONGING to this system
            CASE WHEN direction = 'in' THEN destination ELSE source END AS local_number,
            -- REMOTE: The customer's number
            CASE WHEN direction = 'in' THEN source ELSE destination END AS remote_number
        ")
            ->where('domain_uuid', $domainUuid);

        // Filter by Extension
        if ($targetExtensionUuid) {
            $base->where('extension_uuid', $targetExtensionUuid);
        }

        // Search Logic (Simple string match)
        if ($search !== '') {
            $base->where(function ($w) use ($search) {
                $w->where('source', 'ilike', "%{$search}%")
                    ->orWhere('destination', 'ilike', "%{$search}%")
                    ->orWhere('message', 'ilike', "%{$search}%");
            });
        }

        // 2. Group by the Unique Pair (Local + Remote)
        // Postgres DISTINCT ON works perfectly here
        $rows = DB::query()
            ->fromSub($base, 't')
            ->selectRaw("DISTINCT ON (local_number, remote_number)
            local_number,
            remote_number,
            message_uuid,
            message,
            created_at
        ")
            ->orderBy('local_number')
            ->orderBy('remote_number')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // 3. Format Response
        $rooms = $rows->map(function ($r) {
            return [
                // COMPOSITE ID: +15551234567_+16469998888
                'id' => "{$r->local_number}_{$r->remote_number}",

                // Display Name (The Customer)
                'name' => $r->remote_number,

                // Meta info (My DID) - useful for UI labels "Via +1..."
                'my_number' => $r->local_number,

                'avatar' => null,
                'unread' => 0,
                'lastMessage' => $r->message,
                'timestamp' => $r->created_at,
            ];
        })->sortByDesc('timestamp')->values();

        return response()->json(['rooms' => $rooms]);
    }

    // Optional helper if you don't have it yet
    private function formatPhoneNumber($number)
    {
        return $number; // Add formatting logic here if desired
    }


    /**
     * Fetch messages for a specific room (Conversation between Extension and External Number)
     */
    public function roomMessages(Request $request, $roomId)
    {
        // 1. Parse Composite ID: "Local_Remote"
        // e.g. "+15551234567_+16469998888"
        $parts = explode('_', $roomId);

        if (count($parts) !== 2) {
            return response()->json(['messages' => []]);
        }

        $local = $parts[0];
        $remote = $parts[1];

        $domainUuid = $this->currentDomainUuid();
        // Use the filter logic if needed (Admin vs User)
        // $targetExtension = ...

        // 2. Query Exact Matches
        $query = Messages::query()
            ->select('*')
            ->where('domain_uuid', $domainUuid)
            ->where(function ($q) use ($local, $remote) {
                // Case A: Outbound (Source=Local, Dest=Remote)
                $q->where(function ($sub) use ($local, $remote) {
                    $sub->where('source', $local)
                        ->where('destination', $remote);
                })
                    // Case B: Inbound (Source=Remote, Dest=Local)
                    ->orWhere(function ($sub) use ($local, $remote) {
                        $sub->where('source', $remote)
                            ->where('destination', $local);
                    });
            });

        // 3. Pagination & Format (Same as before)
        $rows = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('page.size', 50));

        $messages = collect($rows->items())->map(function ($r) {
            $isOutbound = in_array(strtolower($r->direction), ['out', 'outbound', 'outgoing']);
            return [
                'text' => $r->message,
                'role' => $isOutbound ? 'user' : 'ai',
                'timestamp' => $r->created_at->toIsoString(),
            ];
        });

        return response()->json([
            'messages' => $messages,
            'pagination' => [
                'total' => $rows->total(),
                'per_page' => $rows->perPage(),
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
            ]
        ]);
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

    public function send(Request $request)
    {
        // 1. Validate Input
        $data = $request->validate([
            'source' => 'required|string',      // My DID (e.g. +1555...)
            'destination' => 'required|string', // Customer (e.g. +1646...)
            'message' => 'required|string',
            'extension_uuid' => 'required|uuid',
        ]);

        $domainUuid = $this->currentDomainUuid();

        // 2. SECURITY CHECK: Verify Extension owns this Source Number
        // First, find the extension model to get the local extension number (e.g. "101")
        $extension = Extensions::where('extension_uuid', $data['extension_uuid'])
            ->where('domain_uuid', $domainUuid)
            ->firstOrFail();

        // Next, check SmsDestinations to ensure this DID is assigned to this extension
        // We assume 'destination' column holds the DID and 'chatplan_detail_data' holds the ext number
        $smsConfig = SmsDestinations::where('domain_uuid', $domainUuid)
            ->where('destination', $data['source'])
            ->where('chatplan_detail_data', $extension->extension)
            ->first();

        if (!$smsConfig) {
            return response()->json([
                'message' => 'Unauthorized: The source number is not assigned to this extension.'
            ], 403);
        }

        // 3. Save to Database
        $msg = new Messages();
        $msg->domain_uuid = $domainUuid;
        $msg->extension_uuid = $data['extension_uuid'];
        $msg->direction = 'out';
        $msg->type = 'sms'; 
        $msg->status = 'queued';

        $countryCode = get_domain_setting('country', $domainUuid) ?? 'US';

        $msg->source = formatPhoneNumber($data['source'],$countryCode,PhoneNumberFormat::E164);
        $msg->destination = formatPhoneNumber($data['destination'],$countryCode,PhoneNumberFormat::E164);

        $msg->message = $data['message'];
        $msg->created_at = now();

        // Save triggers the Observer -> Broadcasts to Reverb
        $msg->save();

        // 4. Send via Carrier API
        try {
            $carrier = $smsConfig->carrier;

            // Instantiate your provider (Commio, Twilio, etc)
            $messageProvider = MessageProviderFactory::make($carrier);

            // Execute Send
            // Assuming your Factory expects the message_uuid to find the record
            $messageProvider->send($msg->message_uuid);

            // Update Status on Success
            $msg->status = 'sent';
            $msg->save();
        } catch (\Exception $e) {
            logger("Carrier Send Failed: " . $e->getMessage());

            // Update Status on Failure
            $msg->status = 'failed';
            $msg->save();

            // We throw 500 so frontend knows it failed (DeepChat will show error)
            return response()->json(['message' => 'Carrier failed: ' . $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => $msg]);
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

        return $data;
    }
}
