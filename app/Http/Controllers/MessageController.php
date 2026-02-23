<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
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
use Illuminate\Support\Str;
use Inertia\Inertia;
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

        $extension = Extensions::where('extension_uuid', auth()->user()->extension_uuid)->select('extension_uuid', 'domain_uuid', 'extension')->first();

        $smsConfigs = SmsDestinations::where('domain_uuid', $extension->domain_uuid ?? null)
            ->where('chatplan_detail_data', $extension->extension ?? null)
            ->get();

        logger($smsConfigs);

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

            // Define the options for the 'extensions' field
            $extensions = Extensions::where('domain_uuid', $domain_uuid)
                ->orderBy('extension')  // Sorts by the 'extension' field in ascending order
                ->get([
                    'extension_uuid',
                    'extension',
                    'effective_caller_id_name',
                ]);

            $extensionOptions = $extensions->map(function ($ext) use ($extension_uuid) {
                $isMe = $ext->extension_uuid === $extension_uuid;
                return [
                    'value' => $ext->extension_uuid,
                    'name' => $isMe ? "{$ext->name_formatted} (Me)" : $ext->name_formatted,
                    'is_me' => $isMe, // Add a flag for sorting
                ];
            })
                ->sortByDesc('is_me') // True (1) comes before False (0)
                ->values();


            // Construct the data object
            $data = [
                'extension_uuid' => $extension_uuid ?? null,
                'extensions' => $extensionOptions,
                // 'permissions' => $this->getUserPermissions(),
                // Define options for other fields as needed
            ];

            return $data;
        } catch (\Exception $e) {
            logger('MessagesController@getData error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => ['Failed to get item details']]
            ], 500); // 500 Internal Server Error for any other errors
        }
    }


    public function rooms(Request $request)
    {
        // logger($request->all()); 

        $domainUuid = $this->currentDomainUuid();
        $limit = min((int) $request->input('limit', 50), 200);

        // 1. CAPTURE THE EXTENSION UUID
        $targetExtensionUuid = $request->input('extension_uuid');
        $search = trim((string) $request->input('q', ''));

        // 2. Define SQL Logic (Keep exactly as you had it)
        $rawDigitsSql = "REGEXP_REPLACE(
        CASE WHEN direction = 'in' THEN source ELSE destination END, 
        '\D', '', 'g'
    )";

        $normalizedSql = "
        CASE 
            WHEN LENGTH($rawDigitsSql) = 11 AND LEFT($rawDigitsSql, 1) = '1' THEN $rawDigitsSql
            WHEN LENGTH($rawDigitsSql) = 10 THEN '1' || $rawDigitsSql
            ELSE $rawDigitsSql 
        END
    ";

        // 3. Build Base Query
        $base = Messages::query()
            ->selectRaw("
            message_uuid,
            message,
            created_at,
            extension_uuid, 
            CASE WHEN direction = 'in' THEN source ELSE destination END AS original_number,
            $normalizedSql as normalized_id
        ")
            ->where('domain_uuid', $domainUuid);

        // 4. APPLY THE EXTENSION FILTER
        // This is the key update. If the frontend sends an extension_uuid, we filter by it.
        if ($targetExtensionUuid) {
            $base->where('extension_uuid', $targetExtensionUuid);
        }

        // 5. APPLY SEARCH (If needed)
        if ($search !== '') {
            $base->where(function ($w) use ($search) {
                $w->where('source', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // 6. Execute Grouping
        $rows = DB::query()
            ->fromSub($base, 't')
            ->selectRaw("DISTINCT ON (normalized_id)
            normalized_id,
            original_number,
            message_uuid,
            message,
            created_at
        ")
            ->orderBy('normalized_id')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // 7. Format Response
        $rooms = $rows->map(function ($r) {
            return [
                'id' => $r->normalized_id,
                'name' => $this->formatPhoneNumber($r->original_number), // Optional formatter
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
        // $roomId comes in as "16474775001" (from your frontend URL)

        $domainUuid = $this->currentDomainUuid();
        $isAdmin = true; // Or your actual admin check logic

        // 1. Define the Same Normalization SQL used in 'rooms()'
        // We need this to match the '16474775001' ID back to rows like '+1 (647)...'
        $rawDigitsSql = "REGEXP_REPLACE(CASE WHEN direction = 'in' THEN source ELSE destination END, '\D', '', 'g')";

        // This SQL mimics the logic we used to generate the ID
        $normalizedIdSql = "
        CASE 
            WHEN LENGTH($rawDigitsSql) = 11 AND LEFT($rawDigitsSql, 1) = '1' THEN $rawDigitsSql
            WHEN LENGTH($rawDigitsSql) = 10 THEN '1' || $rawDigitsSql
            ELSE $rawDigitsSql 
        END
    ";

        // 2. Build the Query
        $query = Messages::query()
            ->select('*')
            ->where('domain_uuid', $domainUuid);

        // 3. Filter by Permission
        if (!$isAdmin) {
            $query->where('extension_uuid', $this->currentExtensionUuid());
        }

        // 4. CRITICAL: Filter by the "Virtual" Room ID
        // We compare the calculated SQL ID against the $roomId passed in the URL
        $query->whereRaw("($normalizedIdSql) = ?", [$roomId]);

        // 5. Pagination
        // DeepChat loads history Oldest -> Newest, but API usually paginates Newest -> Oldest.
        // We fetch Newest first (desc), then the frontend reverses it.
        $rows = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('page.size', 50));

        // 6. Format for DeepChat
        $messages = collect($rows->items())->map(function ($r) {
            // Determine if this message is "Mine" (user) or "Theirs" (ai/contact)
            // Adjust this logic based on your system. 
            // Usually: Outbound = Me, Inbound = Contact.
            $isOutbound = in_array(strtolower($r->direction), ['out', 'outbound', 'outgoing']);

            return [
                // DeepChat Keys
                'text' => $r->message,
                'role' => $isOutbound ? 'user' : 'ai',

                // Optional Metadata
                'timestamp' => $r->created_at->toIsoString(),
            ];
        });

        return response()->json([
            'messages' => $messages, // The array DeepChat needs
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
        // 1. Validate
        $data = $request->validate([
            'roomId' => 'required|string', // This is the Destination Number (e.g. 1646...)
            'message' => 'required|string',
            'extension_uuid' => 'required|uuid', // The "From" identity
        ]);

        try {
            $domainUuid = $this->currentDomainUuid();

            // 2. Find the Extension to get its Number
            $extension = Extensions::where('extension_uuid', $data['extension_uuid'])
                ->where('domain_uuid', $domainUuid)
                ->firstOrFail();

            // 3. Find the Source Phone Number (DID) for this extension
            // We reuse your existing logic helper
            $smsConfig = $this->getPhoneNumberSmsConfig($extension->extension, $domainUuid);
            $sourceNumber = $smsConfig->destination; // 'destination' in SmsDestinations is the DID

            // 4. Save to Database
            $msg = new Messages();
            $msg->domain_uuid = $domainUuid;
            $msg->extension_uuid = $extension->extension_uuid;
            $msg->direction = 'out';
            $msg->type = 'sms';
            $msg->status = 'queued';
            $msg->source = $sourceNumber; // The DID
            $msg->destination = $data['roomId']; // The Customer Number
            $msg->message = $data['message'];
            $msg->created_at = now();
            $msg->save();

            // 5. Trigger the Actual SMS Provider (Commio/etc)
            // Using your existing Factory logic
            try {
                $carrier = $smsConfig->carrier;
                $messageProvider = MessageProviderFactory::make($carrier);
                $messageProvider->send($msg->message_uuid);

                $msg->status = 'sent';
                $msg->save();
            } catch (\Exception $e) {
                logger("Provider Send Failed: " . $e->getMessage());
                // We don't fail the request here, just log it, 
                // because we successfully saved it to DB for the UI.
                $msg->status = 'failed';
                $msg->save();
            }

            return response()->json(['success' => true, 'message' => $msg]);
        } catch (\Exception $e) {
            logger($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
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
