<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Voicemails;
use Illuminate\Http\Request;
use App\Models\VoicemailGreetings;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

class VoicemailController extends Controller
{
    /**
     * Upload a voicemail greeting.
     *
     * @return \Illuminate\Http\Response
     */
    public function uploadVoicemailGreeting(Request $request,Voicemails $voicemail)
    {

        $domain = Domain::where('domain_uuid',$voicemail->domain_uuid)->first();

        if ($request->greeting_type == "unavailable") {
            $filename = "greeting_1.wav";
            $path = $request->voicemail_unavailable_upload_file->storeAs(
                $domain->domain_name .'/' . $voicemail->extension->extension,
                $filename,
                'voicemail'
            );
        } elseif ($request->greeting_type == "name") {
            $filename = "recorded_name.wav";
            $path = $request->voicemail_name_upload_file->storeAs(
                $domain->domain_name .'/' . $voicemail->extension->extension,
                $filename,
                'voicemail'
            );
        }

        if (!Storage::disk('voicemail')->exists($path)) {
            return response()->json([
                'error' => 401,
                'message' => 'Failed to upload file']);   
        }

        // Remove old greeting
        foreach ($voicemail->greetings as $greeting){
            if ($greeting->filename = $filename){
                $greeting->delete();
                break;
            }
        }

        if ($request->greeting_type == "unavailable") {
            // Save new greeting in the database
            $greeting = new VoicemailGreetings();
            $greeting->domain_uuid = Session::get('domain_uuid');
            $greeting->voicemail_id = $voicemail->voicemail_id;
            $greeting->greeting_id = 1;
            $greeting->greeting_name = "Greeting 1";
            $greeting->greeting_filename = $filename;
            $voicemail->greetings()->save($greeting);

            // Save default gretting ID
            $voicemail->greeting_id = 1;
            $voicemail->save();
        }

        return response()->json([
            'status' => "success",
            'voicemail' => $voicemail->voicemail_id,
            'filename' => $filename,
            'message' => 'Greeting uploaded successfully'
        ]);
    }


    /**
     * Get voicemail greeting.
     *
     * @return \Illuminate\Http\Response
     */
    public function getVoicemailGreeting(Voicemails $voicemail, string $filename)
    {

        $path = Session::get('domain_name') .'/' . $voicemail->voicemail_id . '/' . $filename;

        if(!Storage::disk('voicemail')->exists($path)) abort(404);
  
        $file = Storage::disk('voicemail')->path($path);
        $type = Storage::disk('voicemail')->mimeType($path);

        $response = Response::make(file_get_contents($file), 200);
        $response->header("Content-Type", $type);
        return $response;
    }

    /**
     * Get voicemail greeting.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadVoicemailGreeting(Voicemails $voicemail, string $filename)
    {

        $path = Session::get('domain_name') .'/' . $voicemail->voicemail_id . '/' . $filename;

        if(!Storage::disk('voicemail')->exists($path)) abort(404);
  
        $file = Storage::disk('voicemail')->path($path);
        $type = Storage::disk('voicemail')->mimeType($path);
        $headers = array (
            'Content-Type: ' . $type,
        );

        $response = Response::download($file, $filename, $headers);

        return $response;
    }

    /**
     * Get voicemail greeting.
     *
     * @return \Illuminate\Http\Response
     */
    public function deleteVoicemailGreeting(Voicemails $voicemail, string $filename)
    {

        $path = Session::get('domain_name') .'/' . $voicemail->voicemail_id . '/' . $filename;

        $file = Storage::disk('voicemail')->delete($path);

        if(Storage::disk('voicemail')->exists($path)) {
            return response()->json([
                'error' => 401,
                'message' => 'Failed to delete file'
            ]);
        }

        // Remove greeting from database
        foreach ($voicemail->greetings as $greeting){
            if ($greeting->filename = "greeting_1.wav"){
                $greeting->delete();
                break;
            }
        }

        // Update default gretting ID
        $voicemail->greeting_id = null;
        $voicemail->save();

        return response()->json([
            'status' => "success",
            'voicemail' => $voicemail->voicemail_id,
            'filename' => 'greeting_1.wav',
            'message' => 'Greeting deleted successfully'
        ]);

    }

}
