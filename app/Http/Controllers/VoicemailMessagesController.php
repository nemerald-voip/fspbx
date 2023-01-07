<?php

namespace App\Http\Controllers;

use App\Models\Voicemails;
use Illuminate\Http\Request;
use App\Models\VoicemailMessages;
use Illuminate\Support\Facades\Log;
use libphonenumber\NumberParseException;

class VoicemailMessagesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Voicemails $voicemail)
    {
        // Check permissions
        if (!userCheckPermission("voicemail_message_view")){
            return redirect('/');
        }

        //Check FusionPBX login status
        session_start();
        if(!isset($_SESSION['user'])) {
            return redirect()->route('logout');
        }

        $data=array();
        $data['voicemail'] = $voicemail;
        $messages = VoicemailMessages::where('voicemail_uuid',$voicemail->voicemail_uuid)->orderBy('created_epoch','asc')->get();

        //Get libphonenumber object
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        foreach ($messages as $message) {
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($message->caller_id_number, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)){
                    $message->caller_id_number = $phoneNumberUtil
                                ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL);
                } 
            } catch (NumberParseException $e) {
                // Do nothing and leave the numner as is
            }
        }

        $data['messages'] = $messages;

        //assign permissions
        $permissions['add_new'] = userCheckPermission('voicemail_add');
        $permissions['edit'] = userCheckPermission('voicemail_edit');
        $permissions['delete'] = userCheckPermission('voicemail_delete');

        return view('layouts.voicemails.messages.list')
            ->with($data)
            ->with('permissions',$permissions);  
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
