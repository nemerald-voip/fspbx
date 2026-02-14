<?php

namespace App\Http\Controllers;


use Throwable;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Data\FaxData;
use App\Models\Faxes;
use App\Models\FaxLogs;
use App\Models\FaxFiles;
use App\Data\FaxFileData;
use App\Models\Dialplans;
use App\Models\FaxQueues;
use App\Data\FaxQueueData;
use App\Data\FaxDetailData;
use App\Models\FusionCache;
use Illuminate\Support\Str;
use App\Models\Destinations;
use Illuminate\Http\Request;
use App\Models\DefaultSettings;
use Illuminate\Support\Facades\DB;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use App\Http\Requests\CreateFaxRequest;
use App\Http\Requests\UpdateFaxRequest;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use libphonenumber\NumberParseException;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\CreateNewFaxRequest;
use Exception;

class FaxesController extends Controller
{
    protected $viewName = 'Faxes';
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("fax_view")) {
            return redirect('/');
        }

        $perPage = 50;
        $currentDomain = session('domain_uuid');

        $faxes = QueryBuilder::for(Faxes::class)
            // only users in the current domain
            ->where('domain_uuid', $currentDomain)
            ->inUsersLocations()
            ->select([
                'fax_uuid',
                'domain_uuid',
                'fax_email',
                'fax_name',
                'fax_extension',
                'fax_destination_number',
                'fax_caller_id_number',
                'fax_description',
            ])
            // allow ?filter[username]=foo or ?filter[user_email]=bar
            ->allowedFilters([
                // Only email and name_formatted
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('fax_name', 'ilike', "%{$value}%")
                            ->orWhere('fax_email', 'ilike', "%{$value}%")
                            ->orWhere('fax_caller_id_number', 'ilike', "%{$value}%")
                            ->orWhere('fax_extension', 'ilike', "%{$value}%");
                    });
                }),
            ])
            // allow ?sort=-username or ?sort=add_date
            ->allowedSorts(['fax_name', 'fax_caller_id_number'])
            // let your front-end optionally eager-load relations
            ->defaultSort('fax_caller_id_number')
            ->paginate($perPage);


        // wrap in your DTO
        $faxesDto = FaxData::collect($faxes);

        // logger($faxesDto);

        $period = [
            Carbon::now()->startOfDay()->subDays(30),
            Carbon::now()->endOfDay()
        ];
        // dd(Carbon::now()->endOfDay());
        // Convert the dates to the desired format for the query
        // $period = [$startDate->toDateString(), $endDate->toDateString()];

        // Calculate total of sent faxes in the last month
        $totalReceived = FaxFiles::where('fax_mode', 'rx')
            ->where('domain_uuid', $currentDomain)
            ->inUsersLocations()
            ->whereBetween('fax_date', $period)
            ->count();
        // ->toSql();

        // Calculate total of sent faxes in the last month
        $totalSent = FaxFiles::where('fax_mode', 'tx')
            ->where('domain_uuid', $currentDomain)
            ->inUsersLocations()
            ->whereBetween('fax_date', $period)
            ->count();
        // ->toSql();

        $totalFaxes = $faxes->total();

        return Inertia::render(
            $this->viewName,
            [
                'data' => $faxesDto,

                'stats' => [
                    ['name' => 'Faxes Sent (Last 30 Days)', 'stat' => $totalSent],
                    ['name' => 'Faxes Received (Last 30 Days)', 'stat' => $totalReceived],
                    ['name' => 'Active Fax Numbers', 'stat' => $totalFaxes],
                ],


                'routes' => [
                    'current_page' => route('faxes.index'),
                    'recent_outbound_route' => route('faxes.recent-outbound'),
                    'recent_inbound_route' => route('faxes.recent-inbound'),
                    'item_options' => route('faxes.item.options'),
                    'bulk_delete' => route('faxes.bulk.delete'),
                    'new_fax_options' => route('faxes.new.fax.options'),
                    // 'select_all' => route('users.select.all'),

                ]
            ]
        );
    }

    public function getRecentOutbound()
    {
        $period = [
            Carbon::now()->startOfDay()->subDays(30),
            Carbon::now()->endOfDay()
        ];

        $currentDomain = session('domain_uuid');

        $outboundFaxes = QueryBuilder::for(FaxQueues::class)
            ->select([
                'fax_queue_uuid',
                'domain_uuid',
                'fax_caller_id_number',
                'fax_number',
                'fax_date',
                'fax_status',
            ])
            ->where('domain_uuid', $currentDomain)
            ->inUsersLocations()
            ->whereBetween('fax_date', $period)
            ->orderByDesc('fax_date')
            ->limit(5)
            ->get();

        // logger($outboundFaxes);

        $outboundFaxesDto = FaxQueueData::collect($outboundFaxes);

        // logger($outboundFaxesDto);
        return response()->json([
            'data'        => $outboundFaxes,

        ]);
    }

    public function getRecentInbound()
    {
        $period = [
            Carbon::now()->startOfDay()->subDays(30),
            Carbon::now()->endOfDay()
        ];

        $currentDomain = session('domain_uuid');


        $inboundFaxes = QueryBuilder::for(\App\Models\FaxFiles::class)
            ->select([
                'fax_file_uuid',
                'fax_uuid',
                'domain_uuid',
                'fax_caller_id_number',
                'fax_date',
            ])
            ->where('domain_uuid', $currentDomain)
            ->inUsersLocations()
            ->whereBetween('fax_date', $period)
            ->where('fax_mode', 'rx')

            ->with([
                'fax' => function ($q) {
                    $q->select([
                        'fax_uuid',
                        'fax_extension',
                        'fax_caller_id_number',
                        'domain_uuid',
                    ]);
                },
            ])

            ->orderByDesc('fax_date')
            ->limit(5)
            ->get();

        // logger($inboundFaxes);

        $inboundFaxesDto = FaxFileData::collect($inboundFaxes);

        // logger($inboundFaxesDto);
        return response()->json([
            'data'        => $inboundFaxesDto,

        ]);
    }

    public function getItemOptions(Request $request)
    {
        $itemUuid = $request->input('item_uuid');

        $routes = [];
        // 1) Base payload: either an existing user DTO or a “new user” stub
        if ($itemUuid) {
            $fax = QueryBuilder::for(Faxes::class)
                ->select([
                    'fax_uuid',
                    'domain_uuid',
                    'fax_email',
                    'fax_name',
                    'fax_extension',
                    'fax_prefix',
                    'fax_destination_number',
                    'fax_caller_id_name',
                    'fax_caller_id_number',
                    'fax_description',
                    'fax_toll_allow',
                    'fax_forward_number',
                    'fax_send_channels',
                    'fax_description'
                ])
                ->with([
                    'allowed_emails' => function ($q) {
                        $q->select([
                            'uuid',
                            'fax_uuid',
                            'email',
                        ]);
                    },
                ])
                ->with([
                    'allowed_domain_names' => function ($q) {
                        $q->select([
                            'uuid',
                            'fax_uuid',
                            'domain',
                        ]);
                    },
                ])
                ->with([
                    'locations' => function ($q) {
                        // qualify with table name and only select columns from `locations`
                        $q->select([
                            'locations.location_uuid',   // required PK for the related model
                            'locations.name',
                        ]);
                    },
                ])
                ->whereKey($itemUuid)
                ->firstOrFail();

            // wrap in your DTO
            $faxDto = FaxDetailData::from($fax);

            // logger($faxDto);

            $routes = array_merge($routes, [
                'update_route' => route('faxes.update', ['fax' => $itemUuid]),
            ]);
        } else {
            // “New fax defaults

            // Create an instance of your model
            $faxModel = new Faxes();

            // Generate the unique extension
            $fax_extension = $faxModel->generateUniqueSequenceNumber();

            $faxDto = FaxDetailData::from([
                'fax_uuid' => '',
                'domain_uuid' => session('domain_uuid'),
                'fax_email' => null,
                'fax_name' => null,
                'fax_extension' => $fax_extension,
                'fax_prefix' => '9999',
                'fax_destination_number' => $fax_extension,
                'fax_caller_id_name' => null,
                'fax_caller_id_number' => null,
                'fax_description' => null,
                'fax_toll_allow' => null,
                'fax_forward_number' => null,
                'fax_send_channels' => '10',
                'allowed_emails' => null,
                'allowed_domain_names' => null,
            ]);
        }

        // 2) Permissions array
        $permissions = $this->getUserPermissions();


        // 3) Any routes your front end needs
        $routes = array_merge($routes, [
            'store_route'  => route('faxes.store'),
            'locations' => route('locations.index'),
        ]);

        $currentDomain = session('domain_uuid');

        $phone_numbers = QueryBuilder::for(Destinations::class)
            ->allowedFilters(['destination_number', 'destination_description'])
            ->allowedSorts('destination_number')
            ->where('destination_enabled', 'true')
            ->where('domain_uuid', $currentDomain)
            ->get([
                'destination_uuid',
                'destination_number',
                'destination_description',
            ])
            ->each->append('label', 'destination_number_e164')
            ->map(function ($destination) {
                return [
                    'value' => $destination->destination_number_e164,
                    'label' => $destination->label,
                ];
            })
            ->values()
            ->toArray();

        return response()->json([
            'item'        => $faxDto,
            'permissions' => $permissions,
            'routes'      => $routes,
            'phone_numbers' => $phone_numbers,
        ]);
    }

    public function getNewFaxOptions(Request $request)
    {
        $itemUuid = $request->input('item_uuid');

        $routes = [];

        // 2) Permissions array
        $permissions = $this->getUserPermissions();


        // 3) Any routes your front end needs
        $routes = array_merge($routes, [
            'send_fax_route'  => route('faxes.new.fax.send'),
        ]);

        $currentDomain = session('domain_uuid');

        $phone_numbers = QueryBuilder::for(Faxes::class)
            ->allowedSorts('fax_caller_id_number')
            ->where('domain_uuid', $currentDomain)
            ->get([
                'fax_uuid',
                'fax_caller_id_number',
                'fax_name'
            ])
            // ->each->append('label', 'destination_number_e164')
            ->map(function ($fax) {
                return [
                    'value' => $fax->fax_caller_id_number,
                    'label' => $fax->fax_caller_id_number_formatted . ' - ' . $fax->fax_name,
                ];
            })
            ->values()
            ->toArray();

        return response()->json([
            'permissions' => $permissions,
            'routes'      => $routes,
            'phone_numbers' => $phone_numbers,
        ]);
    }


    public function downloadSentFaxFile(FaxFiles $file)
    {

        // $path = $file->domain->domain_name . '/' . $file->fax->fax_extension .  "/sent/" . substr(basename($file->fax_file_path), 0, (strlen(basename($file->fax_file_path)) -4)) . '.'.$file->fax_file_type;
        $path = $file->domain->domain_name . '/' . $file->fax->fax_extension . "/sent/" . substr(basename($file->fax_file_path), 0, (strlen(basename($file->fax_file_path)) - 4)) . '.pdf';

        if (!Storage::disk('fax')->exists($path)) {
            abort(404);
        }

        $file = Storage::disk('fax')->path($path);
        $type = Storage::disk('fax')->mimeType($path);
        $headers = array(
            'Content-Type: ' . $type,
        );

        $response = Response::download($file, basename($file), $headers);

        return $response;
    }


    public function sent(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("fax_sent_view")) {
            return redirect('/');
        }

        $statuses = ['all' => 'Show All', 'sent' => 'Sent', 'waiting' => 'Waiting', 'failed' => 'Failed', 'sending' => 'Sending'];
        $selectedStatus = $request->get('status');
        $searchString = $request->get('search');
        $searchPeriod = $request->get('period');
        $period = [
            Carbon::now()->startOfDay()->subDays(30),
            Carbon::now()->endOfDay()
        ];

        if (preg_match('/^(0[1-9]|1[1-2])\/(0[1-9]|1[0-9]|2[0-9]|3[0-1])\/([1-9+]{2})\s(0[0-9]|1[0-2]:([0-5][0-9]?\d))\s(AM|PM)\s-\s(0[1-9]|1[1-2])\/(0[1-9]|1[0-9]|2[0-9]|3[0-1])\/([1-9+]{2})\s(0[0-9]|1[0-2]:([0-5][0-9]?\d))\s(AM|PM)$/', $searchPeriod)) {
            $e = explode("-", $searchPeriod);
            $period[0] = Carbon::createFromFormat('m/d/y h:i A', trim($e[0]));
            $period[1] = Carbon::createFromFormat('m/d/y h:i A', trim($e[1]));
        }

        $domainUuid = Session::get('domain_uuid');

        $files = FaxQueues::select(
            'v_fax_queue.fax_queue_uuid',
            'v_fax_queue.fax_caller_id_name',
            'v_fax_queue.fax_caller_id_number',
            'v_fax_queue.fax_number',
            'v_fax_queue.fax_date',
            'v_fax_queue.fax_status',
            'v_fax_queue.fax_uuid',
            'v_fax_queue.fax_date',
            'v_fax_queue.fax_status',
            'v_fax_queue.fax_retry_date',
            'v_fax_queue.fax_retry_count',
            'v_fax_queue.fax_notify_date',
            'v_fax_files.fax_destination'
        )
            ->where('v_fax_queue.fax_uuid', $request->id)
            ->where('v_fax_queue.domain_uuid', $domainUuid)
            ->whereBetween('v_fax_queue.fax_date', $period);
        if (array_key_exists($selectedStatus, $statuses) && $selectedStatus != 'all') {
            $files
                ->where('v_fax_queue.fax_status', $selectedStatus);
        }
        $files->leftJoin('v_fax_files', 'fax_file_path', 'fax_file');
        if ($searchString) {
            try {
                $phoneNumberUtil = PhoneNumberUtil::getInstance();
                $phoneNumberObject = $phoneNumberUtil->parse($searchString, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $files->andWhereLike('v_fax_queue.fax_number', $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164));
                } else {
                    $files->andWhereLike('v_fax_queue.fax_number', str_replace("-", "",  $searchString));
                }
            } catch (NumberParseException $e) {
                $files->andWhereLike('v_fax_queue.fax_number', str_replace("-", "",  $searchString));
            }
        }

        $files = $files
            ->orderBy('v_fax_queue.fax_date', 'desc')
            ->paginate(10)
            ->onEachSide(1);

        $timeZone = get_local_time_zone($domainUuid);
        /** @var FaxQueues $file */
        foreach ($files as $file) {
            $file->fax_date = \Illuminate\Support\Carbon::parse($file->fax_date)->setTimezone($timeZone);
            if (!empty($file->fax_notify_date)) {
                $file->fax_notify_date = Carbon::parse($file->fax_notify_date)->setTimezone($timeZone);
            }
            if (!empty($file->fax_retry_date)) {
                $file->fax_retry_date = Carbon::parse($file->fax_retry_date)->setTimezone($timeZone);
            }
        }

        $data['files'] = $files;
        $data['statuses'] = $statuses;
        $data['selectedStatus'] = $selectedStatus;
        $data['searchString'] = $searchString;
        $data['searchPeriodStart'] = $period[0]->format('m/d/y h:i A');
        $data['searchPeriodEnd'] = $period[1]->format('m/d/y h:i A');
        $data['searchPeriod'] = implode(" - ", [$data['searchPeriodStart'], $data['searchPeriodEnd']]);
        $data['national_phone_number_format'] = PhoneNumberFormat::NATIONAL;
        $permissions['delete'] = userCheckPermission('fax_sent_delete');
        return view('layouts.fax.sent.list')
            ->with($data)
            ->with('permissions', $permissions);
    }

    public function log(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("fax_log_view")) {
            return redirect('/');
        }

        $statuses = ['all' => 'Show All', 'success' => 'Success', 'failed' => 'Failed'];
        $selectedStatus = $request->get('status');
        $searchString = $request->get('search');
        $searchPeriod = $request->get('period');
        $period = [
            Carbon::now()->startOfDay()->subDays(30),
            Carbon::now()->endOfDay()
        ];

        if (preg_match('/^(0[1-9]|1[1-2])\/(0[1-9]|1[0-9]|2[0-9]|3[0-1])\/([1-9+]{2})\s(0[0-9]|1[0-2]:([0-5][0-9]?\d))\s(AM|PM)\s-\s(0[1-9]|1[1-2])\/(0[1-9]|1[0-9]|2[0-9]|3[0-1])\/([1-9+]{2})\s(0[0-9]|1[0-2]:([0-5][0-9]?\d))\s(AM|PM)$/', $searchPeriod)) {
            $e = explode("-", $searchPeriod);
            $period[0] = Carbon::createFromFormat('m/d/y h:i A', trim($e[0]));
            $period[1] = Carbon::createFromFormat('m/d/y h:i A', trim($e[1]));
        }

        $domain_uuid = Session::get('domain_uuid');

        $timeZone = get_local_time_zone(Session::get('domain_uuid'));
        $logs = FaxLogs::where('fax_uuid', $request->id)
            ->where('domain_uuid', $domain_uuid);
        if (array_key_exists($selectedStatus, $statuses) && $selectedStatus != 'all') {
            $logs
                ->where('fax_success', ($selectedStatus == 'success'));
        }
        if ($searchString) {
            $logs->where(function ($query) use ($searchString) {
                $query
                    ->orWhereLike('fax_local_station_id', strtolower($searchString))
                    ->orWhereLike('fax_uri', strtolower($searchString));
            });
        }
        $logs->whereBetween('fax_date', $period);
        $logs = $logs->orderBy('fax_date', 'desc')->paginate(10)->onEachSide(1);

        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        $timeZone = get_local_time_zone(Session::get('domain_uuid'));

        foreach ($logs as $i => $log) {
            $logs[$i]['fax_date'] = Carbon::parse($log['fax_date']);

            // Check if the values are not empty and contain a phone number
            if (!empty($logs[$i]['fax_uri']) && preg_match("/\+\d{11}/", $logs[$i]['fax_uri'], $matches1)) {
                $logs[$i]['fax_uri'] = $matches1[0]; // Extract the phone number from the matched value
            }

            // Try to convert fax_uri number to National format
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($logs[$i]['fax_uri'], 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $logs[$i]['fax_uri'] = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                }
            } catch (NumberParseException $e) {
                // Do nothing and leave the numner as is
            }

            // Try to convert fax_local_station_id number to National format
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($logs[$i]['fax_local_station_id'], 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $logs[$i]['fax_local_station_id'] = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                }
            } catch (NumberParseException $e) {
                // Do nothing and leave the numner as is
            }
        }

        $data['logs'] = $logs;
        $data['statuses'] = $statuses;
        $data['selectedStatus'] = $selectedStatus;
        $data['searchString'] = $searchString;
        $data['searchPeriodStart'] = $period[0]->format('m/d/y h:i A');
        $data['searchPeriodEnd'] = $period[1]->format('m/d/y h:i A');
        $data['searchPeriod'] = implode(" - ", [$data['searchPeriodStart'], $data['searchPeriodEnd']]);

        unset($statuses, $logs, $log, $domainUuid, $timeZone, $selectedStatus, $searchString, $selectedScope);

        $permissions['delete'] = userCheckPermission('fax_log_delete');
        return view('layouts.fax.log.list')
            ->with($data)
            ->with('permissions', $permissions);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(CreateFaxRequest $request, Faxes $fax)
    {

        try {
            DB::beginTransaction();

            $data = $request->validated();
            // logger($data);

            // Create the fax server
            $fax = Faxes::create($data);

            // Add allowed emails
            if (!empty($data['authorized_emails']) && is_array($data['authorized_emails'])) {
                $emails = array_filter(array_map(function ($item) {
                    return $item['email'] ?? null;
                }, $data['authorized_emails']));

                $allowedEmails = [];
                foreach ($emails as $email) {
                    if (!empty($email)) {
                        $allowedEmails[] = ['email' => $email];
                    }
                }
                if (!empty($allowedEmails)) {
                    $fax->allowed_emails()->createMany($allowedEmails);
                }
            }

            // Add allowed domains
            if (!empty($data['authorized_domains']) && is_array($data['authorized_domains'])) {
                $domains = array_filter(array_map(function ($item) {
                    return $item['email'] ?? null;
                }, $data['authorized_domains']));

                $allowedDomains = [];
                foreach ($domains as $domain) {
                    if (!empty($domain)) {
                        $allowedDomains[] = ['domain' => $domain];
                    }
                }
                if (!empty($allowedDomains)) {
                    $fax->allowed_domain_names()->createMany($allowedDomains);
                }
            }

            // Generate dialplan for the new fax server
            $this->generateDialPlanXML($fax);

            // Build working directories
            $this->buildWorkingDirectories($fax);

            DB::commit();


            return response()->json([
                'messages' => ['success' => ['Fax server created successfully']],
                'fax' => $fax->fresh(['allowed_emails', 'allowed_domain_names']),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('FaxesController@update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'messages' => ['error' => ['An error occurred while updating the fax.', $e->getMessage()]],
            ], 500);
        }
    }


    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */

    function update(UpdateFaxRequest $request, $id)
    {
        try {
            DB::beginTransaction();

            $data = $request->validated();

            // Find the fax by UUID including relations
            $fax = Faxes::with(['allowed_emails', 'allowed_domain_names'])
                ->where('fax_uuid', $id)
                ->firstOrFail();

            // Update fax main fields
            $fax->update($data);

            // Update allowed emails
            if (!empty($data['authorized_emails']) && is_array($data['authorized_emails'])) {
                $emails = array_filter(array_map(function ($item) {
                    return $item['email'] ?? null;
                }, $data['authorized_emails']));

                // Delete old allowed emails
                $fax->allowed_emails()->delete();

                // Insert new allowed emails
                $allowedEmails = [];
                foreach ($emails as $email) {
                    if (!empty($email)) {
                        $allowedEmails[] = ['email' => $email];
                    }
                }
                if (!empty($allowedEmails)) {
                    $fax->allowed_emails()->createMany($allowedEmails);
                }
            } else {
                // Clear allowed emails if none provided
                $fax->allowed_emails()->delete();
            }

            // Update allowed domain names
            if (!empty($data['authorized_domains']) && is_array($data['authorized_domains'])) {
                $domains = array_filter(array_map(function ($item) {
                    return $item['email'] ?? null;
                }, $data['authorized_domains']));

                // Delete old allowed domains
                $fax->allowed_domain_names()->delete();

                // Insert new allowed domains, note column name 'domain'
                $allowedDomains = [];
                foreach ($domains as $domain) {
                    if (!empty($domain)) {
                        $allowedDomains[] = ['domain' => $domain];
                    }
                }
                if (!empty($allowedDomains)) {
                    $fax->allowed_domain_names()->createMany($allowedDomains);
                }
            } else {
                // Clear allowed domains if none provided
                $fax->allowed_domain_names()->delete();
            }

            // If key missing -> keep current; if present but [] -> unassign all
            if (array_key_exists('locations', $data)) {
                $fax->locations()->sync($data['locations'] ?? []);
            }

            $this->generateDialPlanXML($fax);

            // Build working directories
            $this->buildWorkingDirectories($fax);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Fax updated successfully']],
                'fax' => $fax->fresh(['allowed_emails', 'allowed_domain_names']),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('FaxesController@update error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json([
                'messages' => ['error' => ['An error occurred while updating the fax.', $e->getMessage()]],
            ], 500);
        }
    }

    private function buildWorkingDirectories($fax) 
    {
        $temp_dir = Storage::disk('fax')->path("{$fax->accountcode}/{$fax->fax_extension}/temp");
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0777, true);
        }
        $sent_dir = Storage::disk('fax')->path("{$fax->accountcode}/{$fax->fax_extension}/sent");
        if (!is_dir($sent_dir)) {
            mkdir($sent_dir, 0777, true);
        }
        $inbox_dir = Storage::disk('fax')->path("{$fax->accountcode}/{$fax->fax_extension}/inbox");
        if (!is_dir($inbox_dir)) {
            mkdir($inbox_dir, 0777, true);
        }
    }


    private function generateDialPlanXML($fax): void
    {

        // logger($phoneNumber);

        $settings = DefaultSettings::where('default_setting_category', 'fax')
            ->where('default_setting_subcategory', 'variable')
            ->where('default_setting_enabled', 'true')
            ->get();

        $last_fax = 'last_fax=${caller_id_number}-${strftime(%Y-%m-%d-%H-%M-%S)}';
        $rxfax_data = Storage::disk('fax')->path(
            $fax->accountcode . '/' . $fax->fax_extension . '/inbox/' . $fax->forward_prefix . '${last_fax}.tif'
        );

        // Data to pass to the Blade template
        $data = [
            'fax' => $fax,
            'settings' => $settings,
            'last_fax' => $last_fax,
            'rxfax_data' => $rxfax_data,
        ];

        // Render the Blade template and get the XML content as a string
        $xml = trim(view('layouts.xml.fax-dial-plan-template', $data)->render());

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;  // Removes extra spaces
        $dom->loadXML($xml);
        $dom->formatOutput = true;         // Formats XML properly
        $xml = $dom->saveXML($dom->documentElement);

        $dialPlan = Dialplans::where('dialplan_uuid', $fax->dialplan_uuid)->first();

        if (!$dialPlan) {
            $newDialplanUuid = Str::uuid();

            $dialPlan = new Dialplans();
            $dialPlan->dialplan_uuid = $newDialplanUuid;
            $dialPlan->app_uuid = '24108154-4ac3-1db6-1551-4731703a4440';
            $dialPlan->domain_uuid = $fax->domain_uuid;
            $dialPlan->dialplan_name = $fax->fax_name;
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_number = $fax->fax_destination_number;
            $dialPlan->dialplan_context = $fax->accountcode;
            $dialPlan->dialplan_continue = 'false';
            $dialPlan->dialplan_order = 310;
            $dialPlan->dialplan_enabled = 'true';
            $dialPlan->dialplan_description = $fax->fax_description;
            $dialPlan->insert_date = date('Y-m-d H:i:s');
            $dialPlan->insert_user = session('user_uuid');

            // Update IVR with the new dialplan_uuid
            $fax->dialplan_uuid = $newDialplanUuid;
            $fax->save();

        } else {
            // Update existing dialplan info
            $dialPlan->dialplan_name = $fax->fax_name;
            $dialPlan->dialplan_xml = $xml;
            $dialPlan->dialplan_number = $fax->fax_destination_number;
            $dialPlan->dialplan_context = $fax->accountcode;
            $dialPlan->dialplan_enabled = 'true';
            $dialPlan->dialplan_description = $fax->fax_description;
            $dialPlan->update_date = date('Y-m-d H:i:s');
            $dialPlan->update_user = session('user_uuid');
        }

        $dialPlan->save();

        // clear fusionpbx cache
        FusionCache::clear("dialplan:" . $fax->accountcode);
    }


    /**
     * Remove the specified users from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkDelete(Request $request)
    {
        if (! userCheckPermission('user_delete')) {
            return response()->json([
                'messages' => ['error' => ['Access denied.']]
            ], 403);
        }

        try {
            DB::beginTransaction();

            $uuids = $request->input('items', []);

            $faxes = Faxes::with(['allowed_emails', 'allowed_domain_names', 'dialplan'])
                ->whereIn('fax_uuid', $uuids)
                ->get();

            foreach ($faxes as $fax) {
                // Delete child records
                $fax->allowed_emails()->delete();
                $fax->allowed_domain_names()->delete();

                // Delete the associated dialplan (if it exists)
                if ($fax->dialplan) {
                    $fax->dialplan->delete();
                }

                // Delete the fax record itself
                $fax->delete();
            }

            // clear fusionpbx cache
            FusionCache::clear("dialplan:" . $fax->accountcode);

            DB::commit();

            return response()->json([
                'messages' => ['success' => ['Selected user(s) were deleted successfully.']]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('User bulkDelete error: '
                . $e->getMessage()
                . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => ['An error occurred while deleting the selected user(s).']]
            ], 500);
        }
    }


    public function deleteSentFax($id)
    {
        /** @var FaxQueues $fax */
        $fax = FaxQueues::findOrFail($id);

        if (isset($fax)) {
            if ($fax->faxAttachment) {
                $file = $fax->faxAttachment;
                $file->delete();
            }

            $deleted = $fax->delete();
            if ($deleted) {
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected fax have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected fax'
                    ]
                ]);
            }
        }
    }

    public function deleteReceivedFax($id)
    {
        $fax = FaxFiles::findOrFail($id);

        if (isset($fax)) {
            $deleted = $fax->delete();
            if ($deleted) {
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected fax have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected fax'
                    ]
                ]);
            }
        }
    }

    public function deleteFaxLog($id)
    {
        $fax = FaxLogs::findOrFail($id);

        if (isset($fax)) {
            $deleted = $fax->delete();
            if ($deleted) {
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected log has been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected log'
                    ]
                ]);
            }
        }
    }


    /**
     * Display new fax page
     *
     * 
     * @return \Illuminate\Http\Response
     */

    public function new(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("fax_send")) {
            return redirect('/');
        }

        if ($request->get('id') != "") {
            // logger($request->get('id'));
            $fax = Faxes::find($request->get('id'));
        } else {
            $fax = null;
        }

        // Get all phone numbers
        $destinations = Destinations::where('destination_enabled', 'true')
            ->where('domain_uuid', Session::get('domain_uuid'))
            ->get([
                'destination_uuid',
                'destination_number',
                'destination_enabled',
                'destination_description',
                DB::Raw("coalesce(destination_description , '') as destination_description"),
            ])
            ->sortBy('destination_number');

        $fax_numbers = Faxes::where('domain_uuid', Session::get('domain_uuid'))
            ->get(
                ['fax_caller_id_number']
            )
            ->sortBy('fax_caller_id_number');

        $data = [];
        $data['domain'] = Session::get('domain_name');
        $data['fax_numbers'] = $fax_numbers;
        $data['fax'] = $fax;
        $data['national_phone_number_format'] = PhoneNumberFormat::NATIONAL;

        //Set default allowed extensions
        $fax_allowed_extensions = DefaultSettings::where('default_setting_category', 'fax')
            ->where('default_setting_subcategory', 'allowed_extension')
            ->where('default_setting_enabled', 'true')
            ->pluck('default_setting_value')
            ->toArray();

        if (empty($fax_allowed_extensions)) {
            $fax_allowed_extensions = array('.pdf', '.tiff', '.tif');
        }

        $fax_allowed_extensions = implode(',', $fax_allowed_extensions);

        $data['fax_allowed_extensions'] = $fax_allowed_extensions;

        return view('layouts.fax.new.sendFax')->with($data);
    }

    /**
     *  This function accespt a request to send new fax
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */

    public function sendFax(CreateNewFaxRequest $request)
    {
        try {
            $data = $request->validated();

            // If files attached
            if (isset($data['files'])) {
                $files = $data['files'];
            }

            // Convert form fields to associative array
            // parse_str($data['data'], $data);

            // Validate the input
            $attributes = [
                'recipient' => 'fax recipient',
            ];

            $validator = Validator::make($data, [
                'recipient' => 'numeric|required',
                'fax_message' => 'string|nullable',
                'send_confirmation' => 'present',

            ], [], $attributes);

            if ($validator->fails()) {
                // return response()->json(['error' => $validator->errors()]);
                return response()->json([
                    'error' => $validator->errors()->first() // Sending the first error message for simplicity
                ], 400); // Bad Request status code
            }

            $data['send_confirmation'] = $request->has('send_confirmation') && $data['send_confirmation'] == 'true';
            // logger($data['send_confirmation']);

            if (!isset($data['fax_uuid'])) {
                $fax = Faxes::where('domain_uuid', session('domain_uuid'))
                    ->where('fax_caller_id_number', $data['sender_fax_number'])
                    ->first();
                if (!$fax) {
                    throw new \Exception("There was a problem scheduling your fax. Fax server not found.");
                }
                $data['fax_uuid'] = $fax->fax_uuid;
            }

            // Start creating the payload variable that will be passed to next step
            $payload = array(
                'From' => Session::get('user.user_email'),
                'FromFull' => array(
                    'Email' => ($data['send_confirmation']) ? Session::get('user.user_email') : '',
                ),
                'To' => $data['recipient'] . '@fax.domain.com',
                'Subject' => isset($data['fax_message']) ? 'body' : null,
                'TextBody' => isset($data['fax_message']) ? strip_tags($data['fax_message']) : null,
                'HtmlBody' => isset($data['fax_message']) ? strip_tags($data['fax_message']) : null,
                'fax_destination' => $data['recipient'],
                'fax_uuid' => $data['fax_uuid'],
            );

            $redirect_url = route('faxes.sent.list', $data['fax_uuid']);
            $payload['Attachments'] = array();

            // Parse files
            foreach ($files as $file) {
                // $splited = explode(',', substr($file['data'], 5), 2);
                // $mime = $splited[0];
                // $data = $splited[1];
                // $mime_split_without_base64 = explode(';', $mime, 2);
                // $mime = $mime_split_without_base64[0];
                // // $mime_split=explode('/', $mime_split_without_base64[0],2);

                $mime = $file->getClientMimeType();

                // Get original file name
                $fileName = $file->getClientOriginalName();

                // Read the file content
                $content = file_get_contents($file->getRealPath());

                // Encode the content to base64 if needed
                $base64Content = base64_encode($content);

                array_push(
                    $payload['Attachments'],
                    array(
                        'Content' => $base64Content,
                        'ContentType' => $mime,
                        'Name' => $fileName,
                    )
                );
            }

            $fax = new Faxes();
            $result = $fax->EmailToFax($payload);

            return response()->json([
                'messages' => ['success' => ['Fax is scheduled for delivery']],
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            logger('FaxController@sendFax error: '
                . $e->getMessage()
                . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'messages' => ['error' => [$e->getMessage()]]
            ], 500);
        }
    }

    public function updateStatus(FaxQueues $faxQueue, $status = null)
    {
        $faxQueue->update([
            'fax_status' => $status,
            'fax_retry_count' => 0,
            'fax_retry_date' => null
        ]);

        return redirect()->back();
    }

    public function getUserPermissions()
    {
        $permissions = [];
        // $permissions['user_group_view'] = userCheckPermission('user_group_view');
        $permissions['is_superadmin'] = isSuperAdmin();

        return $permissions;
    }
}
