<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Voicemails;
use Illuminate\Http\Request;
use App\Models\VoicemailMessages;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
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
        $messages = VoicemailMessages::where('voicemail_uuid',$voicemail->voicemail_uuid)->orderBy('created_epoch','desc')->get();

        //Get libphonenumber object
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        // Get local Time Zone
        $time_zone = get_local_time_zone($voicemail->domain_uuid);

        foreach ($messages as $message) {

            // Try to convert caller ID number to National format
            try {
                $phoneNumberObject = $phoneNumberUtil->parse($message->caller_id_number, 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)){
                    $message->caller_id_number = $phoneNumberUtil
                                ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::NATIONAL);
                } 
            } catch (NumberParseException $e) {
                // Do nothing and leave the numner as is
            }

            // Try to convert the date to human redable format
            $message->date = Carbon::createFromTimestamp($message->created_epoch, $time_zone)->toDayDateTimeString();

            // Get the path to message file
            if (Storage::disk('voicemail')->exists($voicemail->domain->domain_name . '/' . $voicemail->voicemail_id .  '/msg_' . $message->voicemail_message_uuid . '.wav')){
                $message->file = Storage::disk('voicemail')->path($voicemail->domain->domain_name . '/' . $voicemail->voicemail_id .  '/msg_' . $message->voicemail_message_uuid . '.wav');
            } elseif (Storage::disk('voicemail')->exists($voicemail->domain->domain_name . '/' . $voicemail->voicemail_id .  '/msg_' . $message->voicemail_message_uuid . '.mp3')){
                $message->file = Storage::disk('voicemail')->path($voicemail->domain->domain_name . '/' . $voicemail->voicemail_id .  '/msg_' . $message->voicemail_message_uuid . '.mp3');
            }
            // $message->file = Storage::disk('voicemail') . '/' . $voicemail->domain_uuid . '/' . $voicemail->voicemail_id . 
            //     '/msg_' . $message->voicemail_message_uuid;
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
     * Get voicemail message.
     *
     * @return \Illuminate\Http\Response
     */
    public function getVoicemailMessage(VoicemailMessages $message)
    {
        $path = Session::get('domain_name') .'/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.wav';

        if(!Storage::disk('voicemail')->exists($path)) {
            $path = Session::get('domain_name') .'/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.mp3';
            if (!Storage::disk('voicemail')->exists($path)) {
                abort (404);
            }
        }
  
        $file = Storage::disk('voicemail')->path($path);
        $type = Storage::disk('voicemail')->mimeType($path);

        $response = Response::make(file_get_contents($file), 200);
        $response->header("Content-Type", $type);
        return $response;
    }

    /**
     * Download voicemail message.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadVoicemailMessage(VoicemailMessages $message)
    {

        $path = Session::get('domain_name') .'/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.wav';

        if(!Storage::disk('voicemail')->exists($path)) {
            $path = Session::get('domain_name') .'/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.mp3';
            if (!Storage::disk('voicemail')->exists($path)) {
                abort (404);
            }
        }
  
        $file = Storage::disk('voicemail')->path($path);
        $type = Storage::disk('voicemail')->mimeType($path);
        $headers = array (
            'Content-Type: ' . $type,
        );

        $response = Response::download($file, basename($file), $headers);

        return $response;
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
        $message = VoicemailMessages::findOrFail($id);

        if(isset($message)){
            $deleted = $message->delete();

            $path = Session::get('domain_name') .'/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.wav';

            if(!Storage::disk('voicemail')->exists($path)) {
                $path = Session::get('domain_name') .'/' . $message->voicemail->voicemail_id . '/msg_' . $message->voicemail_message_uuid . '.mp3';
            }

            $file = Storage::disk('voicemail')->delete($path);

            if ($deleted){
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected vocemail messages have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected voicemail messages'
                    ]
                ]);
            }
        }
    }
}
