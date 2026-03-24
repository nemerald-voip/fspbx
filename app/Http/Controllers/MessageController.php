<?php

namespace App\Http\Controllers;

use App\Factories\MessageProviderFactory;
use App\Jobs\SendSmsNotificationToSlack;
use App\Mail\SmsToEmail;
use App\Models\Contact;
use App\Models\ContactPhone;
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
    protected $viewName = 'Messages';

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
        if (!userCheckPermission("messages_view")) {
            return redirect('/');
        }

        return Inertia::render(
            $this->viewName,
            [
                'routes' => [
                    'roomsIndex'   => route('messages.rooms'),
                    'roomMessages' => route('messages.room.messages', ['roomId' => ':roomId']),
                    'sendMessage'  => route('messages.send'),
                    'markRead'  => route('messages.mark-read'),
                    'data_route'   => route('messages.data'),
                    'contactStore' => route('contacts.store'),
                    'contactShow'  => route('contacts.show', ['phoneNumber' => ':phoneNumber']),
                    'contactDestroy' => route('contacts.destroy', ['contact' => ':contact']),
                    'organizationsIndex' => route('organizations.index'),
                    'organizationsStore' => route('organizations.store'),
                ],
                'permissions' => function () {
                    return $this->getUserPermissions();
                },
            ]
        );
    }

    public function getData()
    {
        try {
            $extension_uuid = auth()->user()->extension_uuid;
            $domain_uuid = request('domain_uuid') ?? session('domain_uuid');

            // 1. Build Base Extension Query
            $extQuery = Extensions::where('domain_uuid', $domain_uuid)
                ->orderBy('extension');

            // 2. SECURITY: If they don't have 'view_as' permission, ONLY fetch their own extension
            if (!userCheckPermission('messages_view_as')) {
                $extQuery->where('extension_uuid', $extension_uuid);
            }

            // 3. Execute Query
            $extensions = $extQuery->get([
                'extension_uuid',
                'extension',
                'effective_caller_id_name',
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
            media,
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
            media,
            created_at
        ")
            ->orderBy('local_number')
            ->orderBy('remote_number')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        // Extract all remote numbers
        $remoteNumbers = $rows->pluck('remote_number')->unique();

        // Query the ContactPhone table (Polymorphic)
        $phones = ContactPhone::whereIn('phone_number', $remoteNumbers)
            ->whereHasMorph('phoneable', [\App\Models\Contact::class, \App\Models\Organization::class], function ($query) use ($domainUuid) {
                $query->where('domain_uuid', $domainUuid);
            })
            ->with('phoneable')
            ->get();

        // Build Map: Number => Name
        $directory = [];
        foreach ($phones as $phone) {
            $owner = $phone->phoneable;
            if ($owner) {
                // Handle name depending on whether it's a Contact or Organization
                $name = ($owner instanceof \App\Models\Contact) ? $owner->full_name : $owner->name;

                // If full_name is empty (no first/last name), fallback to the phone number
                if (trim($name) === '') {
                    $name = $phone->phone_number;
                }

                $directory[$phone->phone_number] = $name;
            }
        }

        $pairs = $rows->map(function ($r) {
            return [
                'to' => $r->local_number,   // Me
                'from' => $r->remote_number // Customer
            ];
        });

        // 2. Fetch Unread Counts in Bulk
        // We want: count(*) where read_at is NULL AND direction is 'in'

        // Efficiently build a query for these specific pairs
        $unreadCounts = Messages::query()
            ->selectRaw('source, destination, count(*) as count')
            ->whereNull('read_at')
            ->where('direction', 'in')
            ->where('domain_uuid', $this->currentDomainUuid())
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as $pair) {
                    $q->orWhere(function ($sub) use ($pair) {
                        $sub->where('destination', $pair['to'])
                            ->where('source', $pair['from']);
                    });
                }
            })
            ->groupBy('source', 'destination')
            ->get();

        // 3. Map Counts Keyed by "MyDID_CustomerDID"
        $countMap = [];
        foreach ($unreadCounts as $u) {
            $key = "{$u->destination}_{$u->source}";
            $countMap[$key] = $u->count;
        }

        // 4. Merge into Response
        $rooms = $rows->map(function ($r) use ($countMap, $directory) {
            $id = "{$r->local_number}_{$r->remote_number}";
            $displayName = $directory[$r->remote_number] ?? $this->formatPhoneNumber($r->remote_number);

            // Tell Carbon this raw string is UTC, then format it to an ISO string
            $timestamp = Carbon::parse($r->created_at, 'UTC')->toIsoString();

            $lastMessageText = (string) $r->message;
            if (trim($lastMessageText) === '' && !empty($r->media) && $r->media !== '[]' && $r->media !== 'null') {
                $lastMessageText = '📷 Image';
            }

            return [
                'id' => $id,
                'name' => $displayName,
                'my_number' => $r->local_number,
                'avatar' => null,
                'unread' => $countMap[$id] ?? 0,
                'lastMessage' => $lastMessageText, 
                'timestamp' => $timestamp,       
            ];
        })->sortByDesc('timestamp')->values();

        return response()->json(['rooms' => $rooms]);
    }

    // Optional helper if you don't have it yet
    private function formatPhoneNumber($number)
    {
        return $number; // Add formatting logic here if desired
    }

    // --- FETCH CONTACT FOR SIDE PANEL ---
    public function getContact(Request $request, $phoneNumber)
    {
        // Find the phone record
        $phone = ContactPhone::where('phone_number', $phoneNumber)->first();

        if (!$phone || !$phone->phoneable) {
            return response()->json(['contact' => null]);
        }

        $contact = $phone->phoneable;

        // Load related data for the form
        $contact->load(['emails', 'addresses', 'organization', 'phones']);

        // Flatten data for the VueForm (optional, but helps with mapping)
        $data = $contact->toArray();

        // Extract specific fields for the form if needed
        $data['phone_number'] = $phoneNumber; // The specific number we clicked on

        // Grab values from related tables to populate form fields
        $data['email'] = $contact->emails->where('label', 'work')->first()->email_address ?? null;
        $data['website'] = $contact->organization->website ?? null; // Example
        $data['address'] = $contact->addresses->first()->street ?? null; // Simplified

        // Map organization name string
        $data['organization'] = $contact->organization->name ?? null;

        return response()->json(['contact' => $data]);
    }

    // --- NEW: STORE CONTACT FROM SIDE PANEL ---
    public function storeContact(Request $request)
    {
        $data = $request->validate([
            'phone_number' => 'required|string',
            'first_name'   => 'nullable|string',
            'last_name'    => 'nullable|string',
            'email'        => 'nullable|email',
            'website'      => 'nullable|string',
            'organization' => 'nullable|string', // String input from form
            'department'   => 'nullable|string',
            'address'      => 'nullable|string',
            'notes'        => 'nullable|string',
            'mobile_number' => 'nullable|string',
            'fax_number'   => 'nullable|string',
        ]);

        $domainUuid = $this->currentDomainUuid();

        DB::beginTransaction();
        try {
            // 1. Handle Organization
            $orgId = null;
            if (!empty($data['organization'])) {
                $org = Organization::firstOrCreate(
                    ['domain_uuid' => $domainUuid, 'name' => $data['organization']],
                    ['website' => $data['website']]
                );
                $orgId = $org->organization_uuid;
            }

            // 2. Find or Create Contact via Phone Number linkage
            // Logic: Does this phone number already exist?
            $existingPhone = ContactPhone::where('phone_number', $data['phone_number'])->first();

            if ($existingPhone && $existingPhone->phoneable_type === Contact::class) {
                $contact = $existingPhone->phoneable;
                // Update existing
                $contact->update([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'organization_uuid' => $orgId,
                    'department' => $data['department'],
                    'notes' => $data['notes'],
                ]);
            } else {
                // Create New Contact
                $contact = Contact::create([
                    'domain_uuid' => $domainUuid,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'organization_uuid' => $orgId,
                    'department' => $data['department'],
                    'notes' => $data['notes'],
                ]);

                // Create the Phone Link
                $contact->phones()->create([
                    'phone_number' => $data['phone_number'],
                    'label' => 'work' // Default for the chat ID
                ]);
            }

            // 3. Handle Email (Update/Create 'work' email)
            if (!empty($data['email'])) {
                $contact->emails()->updateOrCreate(
                    ['label' => 'work'],
                    ['email_address' => $data['email']]
                );
            }

            // 4. Handle Address (Update/Create 'main' address)
            if (!empty($data['address'])) {
                $contact->addresses()->updateOrCreate(
                    ['label' => 'main'],
                    ['street' => $data['address'], 'domain_uuid' => $domainUuid]
                );
            }

            // 5. Handle Extra Phones (Mobile/Fax)
            if (!empty($data['mobile_number'])) {
                $contact->phones()->updateOrCreate(
                    ['label' => 'mobile'],
                    ['phone_number' => $data['mobile_number']]
                );
            }
            if (!empty($data['fax_number'])) {
                $contact->phones()->updateOrCreate(
                    ['label' => 'fax'],
                    ['phone_number' => $data['fax_number']]
                );
            }

            DB::commit();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            logger($e);
            return response()->json(['message' => 'Failed to save contact'], 500);
        }
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
                'media' => $r->media,
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

    public function markRead(Request $request)
    {
        $request->validate(['roomId' => 'required|string']);

        // Parse Composite ID
        $parts = explode('_', $request->roomId);
        if (count($parts) !== 2) return response()->json([], 400);

        $myDid = $parts[0];
        $customerDid = $parts[1];

        // Update DB
        Messages::where('domain_uuid', $this->currentDomainUuid())
            ->where('direction', 'in') // Only mark incoming messages
            ->where('destination', $myDid)
            ->where('source', $customerDid)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }



    private function currentDomainUuid(): string
    {
        $domain = session('domain_uuid');
        if (!$domain) {
            throw new \Exception('domain_uuid not found in session');
        }
        return (string) $domain;
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
        $countryCode = get_domain_setting('country', $domainUuid) ?? 'US';

        $normalizedSource = formatPhoneNumber($data['source'], $countryCode, PhoneNumberFormat::E164);
        $normalizedDestination = formatPhoneNumber($data['destination'], $countryCode, PhoneNumberFormat::E164);

        $smsConfig = SmsDestinations::where('domain_uuid', $domainUuid)
            ->where('destination', $normalizedSource)
            ->where('chatplan_detail_data', $extension->extension)
            ->first();

        if (!$smsConfig) {
            return response()->json([
                'message' => 'Unauthorized: The source number is not assigned to this extension.'
            ], 403);
        }

        $msg = new Messages();
        $msg->domain_uuid = $domainUuid;
        $msg->extension_uuid = $data['extension_uuid'];
        $msg->direction = 'out';
        $msg->type = 'sms';
        $msg->status = 'queued';
        $msg->source = $normalizedSource;
        $msg->destination = $normalizedDestination;

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

                if (!$extension && (!$messageSettings || !$messageSettings->email)) {
                    throw new Exception('No assigned destination found.');
                }


                if ($item->direction == "out") {

                    //Get message config
                    $phoneNumberSmsConfig = $this->getPhoneNumberSmsConfig(
                        $item->source,
                        $extension->extension,
                        $item->domain_uuid
                    );
                    $carrier =  $phoneNumberSmsConfig->carrier;

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

                        if ($item->status == "queued") {
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


    private function getPhoneNumberSmsConfig($sourceNumber, $extensionNumber, $domainUuid)
    {
        $phoneNumberSmsConfig = SmsDestinations::where('domain_uuid', $domainUuid)
            ->where('destination', $sourceNumber)
            ->where('chatplan_detail_data', $extensionNumber)
            ->first();

        if (!$phoneNumberSmsConfig) {
            throw new \Exception(
                "SMS configuration not found for source {$sourceNumber} on extension {$extensionNumber}"
            );
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
                SendSmsNotificationToSlack::dispatch("*Commio Inbound SMS Failed*.From: " . $message->source . " To: " . $message->extension . "\nRingotel API Error: No message ID received. Details: " . $errorDetail)->onQueue('messages');
            }
        } else {
            $message->status = 'failed';
            $errorDetail = isset($response['error']) ? json_encode($response['error']) : 'Unknown error';
            SendSmsNotificationToSlack::dispatch("*Commio Inbound SMS Failed*.From: " . $message->source . " To: " . $message->extension . "\nRingotel API Failure: " . $errorDetail)->onQueue('messages');
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

    public function getUserPermissions()
    {
        $permissions = [];
        $permissions['messages_view_as'] = userCheckPermission('messages_view_as');

        return $permissions;
    }
}
