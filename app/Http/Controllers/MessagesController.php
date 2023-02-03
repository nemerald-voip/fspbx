<?php

namespace App\Http\Controllers;

use App\Models\Messages;
use Illuminate\Http\Request;
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

        }

        return view('layouts.messages.list')
            ->with('messages',$messages);
    }
}
