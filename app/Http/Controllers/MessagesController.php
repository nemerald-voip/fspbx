<?php

namespace App\Http\Controllers;

use App\Models\Messages;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Propaganistas\LaravelPhone\PhoneNumber;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;

class MessagesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $statuses = ['all' => 'Show All', 'success' => 'Success', 'waiting' => 'Waiting', 'emailed' => 'Emailed'];
        $selectedStatus = $request->get('status');
        $searchString = $request->get('search');

        $searchPeriod = $request->get('period');
        
        $period = [
            Carbon::now()->startOfDay()->subDays(30),
            Carbon::now()->endOfDay()
        ];

        $timeZone = get_local_time_zone(Session::get('domain_uuid'));

        if (preg_match('/^(0[1-9]|1[1-2])\/(0[1-9]|1[0-9]|2[0-9]|3[0-1])\/([1-9+]{2})\s(0[0-9]|1[0-2]:([0-5][0-9]?\d))\s(AM|PM)\s-\s(0[1-9]|1[1-2])\/(0[1-9]|1[0-9]|2[0-9]|3[0-1])\/([1-9+]{2})\s(0[0-9]|1[0-2]:([0-5][0-9]?\d))\s(AM|PM)$/', $searchPeriod)) {
            $date = explode("-", $searchPeriod);
            $period[0] = Carbon::createFromFormat('m/d/y h:i A', trim($date[0]),$timeZone)->setTimezone('UTC');
            $period[1] = Carbon::createFromFormat('m/d/y h:i A', trim($date[1]),$timeZone)->setTimezone('UTC');
        }

        logger($selectedStatus);

        $messages = Messages::latest();
        if (array_key_exists($selectedStatus, $statuses) && $selectedStatus != 'all') {
            $messages->where('status', $selectedStatus);
        }
        if ($searchString) {
            $messages->where(function ($query) use ($searchString) {
                $query
                    // ->orWhereLike('source', strtolower($searchString))
                    // ->orWhereLike('destination', strtolower($searchString))
                    ->orWhereLike('message', strtolower($searchString));

                    try {
                        $phoneNumberUtil = PhoneNumberUtil::getInstance();
                        $phoneNumberObject = $phoneNumberUtil->parse($searchString, 'US');
                        if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                            $query->orWhereLike('source', str_replace('+1','',$phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164)));
                            $query->orWhereLike('destination', str_replace('+1','',$phoneNumberUtil->format($phoneNumberObject, PhoneNumberFormat::E164)));
                        } else {
                            $query->orWhereLike('source', strtolower($searchString));
                            $query->orWhereLike('destination', strtolower($searchString));
                        }
                    } catch (NumberParseException $e) {
                        $query->orWhereLike('source', strtolower($searchString));
                        $query->orWhereLike('destination', strtolower($searchString));
                    }
            });
        }
        $messages->whereBetween('created_at', $period);
        logger($period);
        
        $messages = $messages->paginate(50)->onEachSide(1);

        // Get local Time Zone
        $time_zone = get_local_time_zone(Session::get('domain_uuid'));

        //Get libphonenumber object
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        foreach ($messages as $message){
            //Validate source number
            if ($message['source']){
                $phoneNumberObject = $phoneNumberUtil->parse($message['source'], 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)){
                    $message->source = $phoneNumberUtil
                        ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL);
                }
            }
            //Validate destination number
            $phoneNumberObject = $phoneNumberUtil->parse($message['destination'], 'US');
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)){
                $message->destination = $phoneNumberUtil
                    ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL);
            }

            // Try to convert the date to human redable format
            // $message->date = Carbon::parse($message->created_at)->setTimezone($time_zone)->toDayDateTimeString();
            $message->date = Carbon::parse($message->created_at)->setTimezone($time_zone);

        }

        $data=array();
        // $domain_uuid=Session::get('domain_uuid');
        $data['messages'] = $messages;

        //assign permissions
        $permissions = array();
        // $permissions['add_new'] = userCheckPermission('voicemail_add');
        // $permissions['edit'] = userCheckPermission('voicemail_edit');
        // $permissions['delete'] = userCheckPermission('voicemail_delete');

        $data['permissions'] = $permissions;

        $data['searchString'] = $searchString;

        $data['statuses'] = $statuses;
        $data['selectedStatus'] = $selectedStatus;

        if ($searchPeriod) {
            $data['searchPeriodStart'] = $period[0]->setTimezone($time_zone)->format('m/d/y h:i A');
            $data['searchPeriodEnd'] = $period[1]->setTimezone($time_zone)->format('m/d/y h:i A');
        } else {
            $data['searchPeriodStart'] = $period[0]->format('m/d/y h:i A');
            $data['searchPeriodEnd'] = $period[1]->format('m/d/y h:i A');
        }

        // $data['searchPeriodStart'] = Carbon::createFromFormat('m/d/y h:i A', trim($date[0]))->setTimezone('UTC')->format('m/d/y h:i A');
        // $data['searchPeriodEnd'] = Carbon::createFromFormat('m/d/y h:i A', trim($date[1]))->setTimezone('UTC')->format('m/d/y h:i A');;
        $data['searchPeriod'] = implode(" - ", [$data['searchPeriodStart'], $data['searchPeriodEnd']]);

        return view('layouts.messages.list')
            ->with($data);
            // ->with('permissions',$permissions);  

    }
}
