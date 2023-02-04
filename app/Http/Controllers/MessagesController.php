<?php

namespace App\Http\Controllers;

use App\Models\Messages;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Session;
use Propaganistas\LaravelPhone\PhoneNumber;

class MessagesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $messages = Messages::latest()->paginate(50)->onEachSide(1);

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
// dd( Carbon::parse($message->created_at)->setTimezone($time_zone)->toDayDateTimeString());
// dd($message->date->toFormattedDateString());
        }

        $data=array();
        // $domain_uuid=Session::get('domain_uuid');
        $data['messages'] = $messages;

        //assign permissions
        // $permissions['add_new'] = userCheckPermission('voicemail_add');
        // $permissions['edit'] = userCheckPermission('voicemail_edit');
        // $permissions['delete'] = userCheckPermission('voicemail_delete');

        return view('layouts.messages.list')
            ->with($data);
            // ->with('permissions',$permissions);  

    }
}
