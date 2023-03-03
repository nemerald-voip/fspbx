<?php

namespace App\Http\Controllers;


use App\Models\DefaultSettings;
use App\Models\Destinations;
use App\Models\Dialplans;
use App\Models\FaxAllowedDomainNames;
use App\Models\FaxAllowedEmails;
use App\Models\Faxes;
use App\Models\FaxFiles;
use App\Models\FaxLogs;
use App\Models\FaxQueues;
use App\Models\FreeswitchSettings;
use cache;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;

class FaxesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Check permissions
        if (!userCheckPermission("fax_view")) {
            return redirect('/');
        }
        // $list = Session::get('permissions', false);
        // pr($list);exit;
        $domain_uuid = Session::get('domain_uuid');
        $data['faxes'] = Faxes::where('domain_uuid', $domain_uuid)->get();
        $permissions['add_new'] = userCheckPermission('fax_add');
        $permissions['edit'] = userCheckPermission('fax_edit');
        $permissions['delete'] = userCheckPermission('fax_delete');
        $permissions['view'] = userCheckPermission('fax_view');
        $permissions['send'] = userCheckPermission('fax_send');
        $permissions['fax_inbox_view'] = userCheckPermission('fax_inbox_view');
        $permissions['fax_sent_view'] = userCheckPermission('fax_sent_view');
        $permissions['fax_active_view'] = userCheckPermission('fax_active_view');
        $permissions['fax_log_view'] = userCheckPermission('fax_log_view');
        $permissions['fax_send'] = userCheckPermission('fax_send');

        foreach ($data['faxes'] as $fax) {
            if (!empty($fax->fax_email)) {
                $fax->fax_email = explode(',', $fax->fax_email);
            } else {
                $fax->fax_email = [];
            }
        }

        return view('layouts.fax.list')
            ->with($data)
            ->with('permissions', $permissions);
    }

    public function inbox(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("fax_inbox_view")) {
            return redirect('/');
        }
        $domain_uuid = Session::get('domain_uuid');

        //Get libphonenumber object
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        $files = FaxFiles::where('fax_uuid', $request->id)->where('fax_mode', 'rx')->where('domain_uuid', $domain_uuid)->orderBy('fax_date', 'desc')->get();
        $data['files'] = $files;
        $time_zone = get_local_time_zone($domain_uuid);
        foreach ($files as $file) {
            if (Storage::disk('fax')->exists($file->domain->domain_name . '/' . $file->fax->fax_extension . "/inbox/" . substr(basename($file->fax_file_path), 0, (strlen(basename($file->fax_file_path)) - 4)) . '.' . $file->fax_file_type)) {
                $file->fax_file_path = Storage::disk('fax')->path($file->domain->domain_name . '/' . $file->fax->fax_extension . "/inbox/" . substr(basename($file->fax_file_path), 0, (strlen(basename($file->fax_file_path)) - 4)) . '.' . $file->fax_file_type);
            }

            // Try to convert caller ID number to National format
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($file->fax_caller_id_number, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $file->fax_caller_id_number = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                }
            } catch (NumberParseException $e) {
                // Do nothing and leave the numner as is
            }

            // Try to convert destination number to National format
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($file->fax->fax_caller_id_number, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $file->fax_destination = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                }
            } catch (NumberParseException $e) {
                // Do nothing and leave the numner as is
            }

            // Try to convert the date to human redable format
            $file->fax_date = Carbon::createFromTimestamp($file->fax_epoch, $time_zone)->toDayDateTimeString();
        }
        $permissions['delete'] = userCheckPermission('fax_inbox_delete');
        return view('layouts.fax.inbox.list')
            ->with($data)
            ->with('permissions', $permissions);

    }

    public function downloadInboxFaxFile(FaxFiles $file)
    {

        $path = $file->domain->domain_name . '/' . $file->fax->fax_extension . "/inbox/" . substr(basename($file->fax_file_path), 0, (strlen(basename($file->fax_file_path)) - 4)) . '.pdf';
        // $path = $file->domain->domain_name . '/' . $file->fax->fax_extension .  "/inbox/" . substr(basename($file->fax_file_path), 0, (strlen(basename($file->fax_file_path)) -4)) . '.'.$file->fax_file_type;

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

        $statuses = ['all' => 'Show All', 'sent' => 'Sent', 'waiting' => 'Waiting', 'failed' => 'Failed'];
        $selectedStatus = $request->get('status');
        $searchString = $request->get('search');
        $searchPeriod = $request->get('period');
        $period = [
            Carbon::now()->startOfMonth()->subMonthsNoOverflow(),
            Carbon::now()->endOfDay()
        ];

        if(preg_match('/^(0[1-9]|1[1-2])\/(0[1-9]|1[0-9]|2[0-9]|3[0-1])\/([1-9+]{2})\s(0[0-9]|1[0-2]:([0-5][0-9]?\d))\s(AM|PM)\s-\s(0[1-9]|1[1-2])\/(0[1-9]|1[0-9]|2[0-9]|3[0-1])\/([1-9+]{2})\s(0[0-9]|1[0-2]:([0-5][0-9]?\d))\s(AM|PM)$/', $searchPeriod)) {
            $e = explode("-", $searchPeriod);
            $period[0] = Carbon::createFromFormat('m/d/y h:i A', trim($e[0]));
            $period[1] = Carbon::createFromFormat('m/d/y h:i A', trim($e[1]));
        }

        //Get libphonenumber object
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        $domainUuid = Session::get('domain_uuid');

        $files = FaxQueues::where('fax_uuid', $request->id)
            ->where('domain_uuid', $domainUuid)
            ->whereBetween('fax_date', $period);
        if (array_key_exists($selectedStatus, $statuses) && $selectedStatus != 'all') {
            $files
                ->where('fax_status', $selectedStatus);
        }
        if ($searchString) {
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($searchString, 'US');
                $searchString = $phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $files->where('fax_caller_id_number', $searchString);
                }
            } catch (NumberParseException $e) {
                // Do nothing and leave the number as is
            }
        }

        $files = $files
            ->orderBy('fax_date', 'desc')
            ->paginate(10)
            ->onEachSide(1);

        $time_zone = get_local_time_zone($domainUuid);
        /** @var FaxFiles $file */
        foreach ($files as $file) {

            // Try to convert caller ID number to National format
            //try {
                $phoneNumberObject = $phoneNumberUtil->parse($file->getFaxFile()->fax_caller_id_number, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $file->fax_caller_id_number = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                }
            /*} catch (NumberParseException $e) {
                // Do nothing and leave the number as is
            }*/

            // Try to convert destination number to National format
            //try {
                $phoneNumberObject = $phoneNumberUtil->parse($file->getFaxFile()->fax_destination, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $file->fax_destination = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                }
            /*} catch (NumberParseException $e) {
                // Do nothing and leave the number as is
            }*/

            $file->fax_date = Carbon::createFromTimestamp($file->getFaxFile()->fax_epoch, $time_zone);
            $file->fax_notify_date = Carbon::parse($file->getFaxFile()->fax_notify_date)->setTimezone($time_zone);
            $file->fax_retry_date = Carbon::parse($file->getFaxFile()->fax_retry_date)->setTimezone($time_zone);
        }

        $data['files'] = $files;
        $data['statuses'] = $statuses;
        $data['selectedStatus'] = $selectedStatus;
        $data['searchString'] = $searchString;
        $data['searchPeriodStart'] = $period[0]->format('m/d/y h:i A');
        $data['searchPeriodEnd'] = $period[1]->format('m/d/y h:i A');
        $data['searchPeriod'] = implode(" - ", [$data['searchPeriodStart'], $data['searchPeriodEnd']]);
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

        //Get libphonenumber object
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();


        $domain_uuid = Session::get('domain_uuid');
        $logs = FaxLogs::where('fax_uuid', $request->id)->where('domain_uuid', $domain_uuid)->orderBy('fax_date', 'desc')->get();
        $time_zone = get_local_time_zone($domain_uuid);
        foreach ($logs as $log) {

            // Try to convert caller ID number to National format
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($log->fax_local_station_id, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $log->fax_local_station_id = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                }
            } catch (NumberParseException $e) {
                // Do nothing and leave the numner as is
            }

            // Try to convert destination number to National format
            try {
                $phoneNumberObject = $phoneNumberUtil->parse(basename($log->fax_uri), 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $log->fax_uri = $phoneNumberUtil
                        ->format($phoneNumberObject, PhoneNumberFormat::NATIONAL);
                }
            } catch (NumberParseException $e) {
                // Do nothing and leave the numner as is
            }

            // Try to convert the date to human redable format
            $log->fax_date = Carbon::createFromTimestamp($log->fax_epoch, $time_zone)->toDayDateTimeString();
        }


        $data['logs'] = $logs;
        $permissions['delete'] = userCheckPermission('fax_log_delete');
        return view('layouts.fax.log.list')
            ->with($data)
            ->with('permissions', $permissions);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // Check permissions
        if (!userCheckPermission("fax_add")) {
            return redirect('/');
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


        $data = [];
        $fax = new Faxes;
        $data['fax'] = $fax;
        $data['domain'] = Session::get('domain_name');
        $data['destinations'] = $destinations;
        $data['national_phone_number_format'] = PhoneNumberFormat::NATIONAL;
        $data['allowed_emails'] = $fax->allowed_emails;
        $data['allowed_domain_names'] = $fax->allowed_domain_names;


        return view('layouts.fax.createOrUpdate')->with($data);;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Faxes $fax)
    {

        if (!userCheckPermission('fax_add') || !userCheckPermission('fax_edit')) {
            return redirect('/');
        }

        //Setting variables to use
        $domain_id = Session::get('domain_uuid');
        $domain_name = Session::get('domain_name');


        //Validation check
        $attributes = [
            'fax_name' => 'Fax Name',
            'fax_extension' => 'Fax Extension',
            // 'accountcode' =>'Account Code',
            // 'fax_destination_number' => 'Destination Number',
            // 'fax_prefix' => 'Prefix',
            'fax_email' => 'Email',
            'fax_caller_id_name' => 'Caller ID name',
            'fax_caller_id_number' => 'Caller ID number',
            'fax_forward_number' => 'Fax Forward Number',
            'fax_toll_allow' => 'Fax Toll Allow',
            'fax_send_channels' => 'Fax Send Channels',
            'fax_description' => 'Description',
        ];

        $validator = Validator::make($request->all(), [

            'fax_name' => 'required',
            'fax_extension' => 'required',
            // 'accountcode' => 'nullable',
            // 'fax_destination_number' => 'nullable',
            // 'fax_prefix' => 'nullable',
            'fax_email' => 'nullable|array',
            'fax_caller_id_name' => 'nullable',
            'fax_caller_id_number' => 'nullable',
            'fax_forward_number' => 'nullable',
            'fax_toll_allow' => 'nullable',
            'fax_send_channels' => 'nullable',
            'fax_description' => 'nullable|string|max:100',
            'email_list' => 'nullable|array',

        ], [], $attributes);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();
        $attributes['domain_uuid'] = $domain_id;
        $attributes['accountcode'] = $domain_name;
        $attributes['fax_prefix'] = 9999;
        $attributes['fax_destination_number'] = $attributes['fax_extension'];
        $fax_email = '';
        if (isset($attributes['fax_email'])) {
            $fax_email = implode(',', $attributes['fax_email']);
        }
        $attributes['fax_email'] = $fax_email;
        $fax->fill($attributes);
        $fax->save();

        $dialplan = new Dialplans;
        $dialplan->domain_uuid = $domain_id;
        $dialplan->app_uuid = "24108154-4ac3-1db6-1551-4731703a4440";
        $dialplan->dialplan_name = $attributes['fax_name'];
        $dialplan->dialplan_number = $attributes['fax_extension'];
        $dialplan->dialplan_context = $domain_name;
        $dialplan->dialplan_continue = 'false';
        $dialplan->dialplan_order = '310';
        $dialplan->dialplan_enabled = 'true';
        $dialplan->dialplan_description = $attributes['fax_description'];
        $dialplan->save();
        $dialplan->dialplan_xml = get_fax_dial_plan($fax, $dialplan);
        $dialplan->save();
        $fax->dialplan_uuid = $dialplan->dialplan_uuid;
        $fax->save();


        // If allowed email list is submitted save it to database
        if (isset($attributes['email_list'])) {
            foreach ($attributes['email_list'] as $email) {
                $allowed_email = new FaxAllowedEmails();
                $allowed_email->fax_uuid = $fax->fax_uuid;
                $allowed_email->email = $email;
                $allowed_email->save();
            }
        }

        // If allowed domain list is submitted save it to database
        if (isset($attributes['domain_list'])) {
            foreach ($attributes['domain_list'] as $domain) {
                $allowed_domain = new FaxAllowedDomainNames();
                $allowed_domain->fax_uuid = $fax->fax_uuid;
                $allowed_domain->domain = $domain;
                $allowed_domain->save();
            }
        }
        if (session_status() == PHP_SESSION_NONE || session_id() == '') {
            $method_setting = DefaultSettings::where('default_setting_enabled', 'true')
                ->where('default_setting_category', 'cache')
                ->where('default_setting_subcategory', 'method')
                ->get()
                ->first();

            $location_setting = DefaultSettings::where('default_setting_enabled', 'true')
                ->where('default_setting_category', 'cache')
                ->where('default_setting_subcategory', 'location')
                ->get()
                ->first();

            $freeswitch_settings = FreeswitchSettings::first();

            session_start();
            $_SESSION['cache']['method']['text'] = $method_setting->default_setting_value;
            $_SESSION['cache']['location']['text'] = $location_setting->default_setting_value;
            $_SESSION['event_socket_ip_address'] = $freeswitch_settings['event_socket_ip_address'];
            $_SESSION['event_socket_port'] = $freeswitch_settings['event_socket_port'];
            $_SESSION['event_socket_password'] = $freeswitch_settings['event_socket_password'];
        }
        $cache = new cache;
        $cache->delete("dialplan:" . $domain_name);
        //clear the destinations session array
        if (isset($_SESSION['destinations']['array'])) {
            unset($_SESSION['destinations']['array']);
        }

        return response()->json([
            'fax' => $fax->fax_uuid,
            'redirect_url' => route('faxes.edit', ['fax' => $fax->fax_uuid]),
            'status' => 'success',
            'message' => 'Fax has been created'
        ]);

    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Faxes $fax)
    {
        //check permissions
        if (!userCheckPermission('fax_edit')) {
            return redirect('/');
        }

        //Check FusionPBX login status
        session_start();
        if (session_status() === PHP_SESSION_NONE) {
            return redirect()->route('logout');
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
        if (isset($fax->fax_email)) {
            if (!empty($fax->fax_email)) {
                $fax->fax_email = explode(',', $fax->fax_email);
            }
        }


        $data = array();
        $data['fax'] = $fax;
        $data['domain'] = Session::get('domain_name');
        $data['destinations'] = $destinations;
        $data['national_phone_number_format'] = PhoneNumberFormat::NATIONAL;
        $data['allowed_emails'] = $fax->allowed_emails;
        $data['allowed_domain_names'] = $fax->allowed_domain_names;

        return view('layouts.fax.createOrUpdate')->with($data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */

    function update(Request $request, Faxes $fax)
    {
        if (!userCheckPermission('fax_add') || !userCheckPermission('fax_edit')) {
            return redirect('/');
        }

        $attributes = [
            'fax_name' => 'Fax Name',
            'fax_extension' => 'Fax Extension',
            // 'accountcode' =>'Account Code',
            // 'fax_destination_number' => 'Destination Number',
            // 'fax_prefix' => 'Prefix',
            'fax_email' => 'Email',
            'fax_caller_id_name' => 'Caller ID name',
            'fax_caller_id_number' => 'Caller ID number',
            'fax_forward_number' => 'Fax Forward Number',
            'fax_toll_allow' => 'Fax Toll Allow',
            'fax_send_channels' => 'Fax Send Channels',
            'fax_description' => 'Description',
        ];

        $validator = Validator::make($request->all(), [

            'fax_name' => 'required',
            'fax_extension' => 'required',
            // 'accountcode' => 'nullable',
            // 'fax_destination_number' => 'nullable',
            // 'fax_prefix' => 'nullable',
            'fax_email' => 'nullable|array',
            'fax_caller_id_name' => 'nullable',
            'fax_caller_id_number' => 'nullable',
            'fax_forward_number' => 'nullable',
            'fax_toll_allow' => 'nullable',
            'fax_send_channels' => 'nullable',
            'fax_description' => 'nullable|string|max:100',
            'email_list' => 'nullable|array',
            'domain_list' => 'nullable|array',

        ], [], $attributes);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();
        $attributes['fax_destination_number'] = $attributes['fax_extension'];
        $fax_email = '';
        if (isset($attributes['fax_email'])) {
            $fax_email = implode(',', $attributes['fax_email']);
        }
        $attributes['fax_email'] = $fax_email;
        $fax->fill($attributes);
        $fax->update($attributes);


        //Setting variables to use
        $domain_id = Session::get('domain_uuid');
        $domain_name = Session::get('domain_name');

        $old_dialplan = Dialplans::where('dialplan_uuid', $fax->dialplan_uuid)->first();
        if (!empty($old_dialplan)) {
            $old_dialplan->delete();
        }

        $dialplan = new Dialplans;
        $dialplan->domain_uuid = $domain_id;
        $dialplan->app_uuid = "24108154-4ac3-1db6-1551-4731703a4440";
        $dialplan->dialplan_name = $attributes['fax_name'];
        $dialplan->dialplan_number = $attributes['fax_extension'];
        $dialplan->dialplan_context = $domain_name;
        $dialplan->dialplan_continue = 'false';
        $dialplan->dialplan_order = '310';
        $dialplan->dialplan_enabled = 'true';
        $dialplan->dialplan_description = $attributes['fax_description'];
        $dialplan->save();
        $dialplan->dialplan_xml = get_fax_dial_plan($fax, $dialplan);
        $dialplan->save();
        $fax->dialplan_uuid = $dialplan->dialplan_uuid;
        $fax->save();


        // Remove current allowed emails from the database
        if (isset($fax->allowed_emails)) {
            foreach ($fax->allowed_emails as $email) {
                $email->delete();
            }
        }

        // Remove current allowed domains from the database
        if (isset($fax->allowed_domain_names)) {
            foreach ($fax->allowed_domain_names as $domain_name) {
                $domain_name->delete();
            }
        }

        // If allowed email list is submitted save it to database
        if (isset($attributes['email_list'])) {
            foreach ($attributes['email_list'] as $email) {
                $allowed_email = new FaxAllowedEmails();
                $allowed_email->fax_uuid = $fax->fax_uuid;
                $allowed_email->email = $email;
                $allowed_email->save();
            }
        }

        // If allowed domain list is submitted save it to database
        if (isset($attributes['domain_list'])) {
            foreach ($attributes['domain_list'] as $domain) {
                $allowed_domain = new FaxAllowedDomainNames();
                $allowed_domain->fax_uuid = $fax->fax_uuid;
                $allowed_domain->domain = $domain;
                $allowed_domain->save();
            }
        }

        if (session_status() == PHP_SESSION_NONE || session_id() == '') {
            $method_setting = DefaultSettings::where('default_setting_enabled', 'true')
                ->where('default_setting_category', 'cache')
                ->where('default_setting_subcategory', 'method')
                ->get()
                ->first();

            $location_setting = DefaultSettings::where('default_setting_enabled', 'true')
                ->where('default_setting_category', 'cache')
                ->where('default_setting_subcategory', 'location')
                ->get()
                ->first();

            $freeswitch_settings = FreeswitchSettings::first();

            session_start();
            $_SESSION['cache']['method']['text'] = $method_setting->default_setting_value;
            $_SESSION['cache']['location']['text'] = $location_setting->default_setting_value;
            $_SESSION['event_socket_ip_address'] = $freeswitch_settings['event_socket_ip_address'];
            $_SESSION['event_socket_port'] = $freeswitch_settings['event_socket_port'];
            $_SESSION['event_socket_password'] = $freeswitch_settings['event_socket_password'];
        }
        $cache = new cache;
        $cache->delete("dialplan:" . $domain_name);
        //clear the destinations session array
        if (isset($_SESSION['destinations']['array'])) {
            unset($_SESSION['destinations']['array']);
        }
        return response()->json([
            'fax' => $fax->fax_uuid,
            //'request' => $attributes,
            'status' => 'success',
            'message' => 'Fax has been updated'
        ]);

    }


    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $fax = Faxes::findOrFail($id);

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


    public function deleteFaxFile($id)
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
     * @param int $id
     * @return \Illuminate\Http\Response
     */

    public function new(Faxes $fax)
    {
        // Check permissions
        if (!userCheckPermission("fax_send")) {
            return redirect('/');
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


        $data = [];
        $data['domain'] = Session::get('domain_name');
        $data['destinations'] = $destinations;
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

    public function sendFax(Request $request)
    {
        // Log::alert($request->all());
        $data = $request->all();

        // If files attached
        if (isset($data['files'])) {
            $files = $data['files'];
        }

        // Convert form fields to associative array
        parse_str($data['data'], $data);


        // Validate the input
        $attributes = [
            'recipient' => 'fax recipient',
        ];

        $validator = Validator::make($data, [
            'recipient' => 'numeric|required|phone:US',

        ], [], $attributes);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()]);
        }

        if (!isset($files) || sizeof($files) == 0) {
            return response()->json(['error' => ['files' => ['At least one file must be uploaded']]]);
        }
        // Start creating the payload variable that will be passed to next step
        $payload = array(
            'From' => Session::get('user.user_email'),
            'FromFull' => array(
                'Email' => Session::get('user.user_email'),
            ),
            'To' => $data['recipient'] . '@fax.nemerald.com',
            'Subject' => $data['fax_subject'],
            'TextBody' => $data['fax_message'],
            'HtmlBody' => $data['fax_message'],
            'fax_destination' => $data['recipient'],
            'fax_uuid' => $data['fax_uuid'],
        );

        $payload['Attachments'] = array();

        // Parse files
        foreach ($files as $file) {
            $splited = explode(',', substr($file['data'], 5), 2);
            $mime = $splited[0];
            $data = $splited[1];
            $mime_split_without_base64 = explode(';', $mime, 2);
            $mime = $mime_split_without_base64[0];
            // $mime_split=explode('/', $mime_split_without_base64[0],2);

            array_push($payload['Attachments'],
                array(
                    'Content' => $data,
                    'ContentType' => $mime,
                    'Name' => $file['name'],
                )
            );

        }

        $fax = new Faxes();
        // $result = $fax->EmailToFax($payload);


        return response()->json([
            'request' => $request->all(),
            'status' => 200,
            'success' => [
                'message' => 'Fax is scheduled for delivery'
            ]
        ]);
    }

    public function updateStatus(FaxQueues $faxQueue, $status = null)
    {
        $faxQueue->update([
            'fax_status' => $status,
            'fax_retry_count' => 0
        ]);

        return redirect()->back();
    }
}
