<?php

namespace App\Http\Controllers;

use cache;
use App\Models\Domain;
use App\Models\Extensions;
use App\Models\Voicemails;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\VoicemailGreetings;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class VoicemailController extends Controller
{

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request){
        // Check permissions
        if (!userCheckPermission("voicemail_view")){
            return redirect('/');
        }
        $data=array();
        $domain_uuid=Session::get('domain_uuid');
        $data['voicemails']=Voicemails::where('domain_uuid',$domain_uuid)->orderBy('voicemail_id','asc')->get();

        //assign permissions
        $permissions['add_new'] = userCheckPermission('voicemail_add');
        $permissions['edit'] = userCheckPermission('voicemail_edit');
        $permissions['delete'] = userCheckPermission('voicemail_delete');

        return view('layouts.voicemails.list')
            ->with($data)
            ->with('permissions',$permissions);     
    } 
    public function store(Request $request, Voicemails $voicemail)
    {

        if (!userCheckPermission('voicemail_add') || !userCheckPermission('voicemail_edit')) {
            return redirect('/');
        }

        $attributes = [
            'voicemail_id' => 'voicemail extension number',
            'voicemail_password' => 'voicemail PIN',
            'greeting_id' =>'extension number',
            'voicemail_mail_to' => 'email address',
            'voicemail_enabled' => 'enabled',
            'voicemail_description' => 'description',
        ];

        $validator = Validator::make($request->all(), [
            'voicemail_id' =>[
                'required',
                'numeric',
                Rule::unique('App\Models\Extensions','extension')
                    ->ignore($request->extension,'extension_uuid')
                    ->where('domain_uuid', Session::get('domain_uuid')),
                Rule::unique('App\Models\Voicemails','voicemail_id')
                    ->where('domain_uuid', Session::get('domain_uuid')),
            ],
            'voicemail_password' => 'numeric|digits_between:3,10',
            'voicemail_mail_to' => 'nullable|email:rfc,dns',
            'voicemail_enabled' => 'present',
            'voicemail_enabled' => 'present',
            'voicemail_description' => 'nullable|string|max:100',
            'voicemail_transcription_enabled' => 'present',
            'voicemail_attach_file' => 'present',
            'voicemail_file' => 'present',
            'voicemail_local_after_email' => 'present',
            'extension' => "uuid",

        ], [], $attributes);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();
        $attributes['domain_uuid'] = Session::get('domain_uuid');
        // find extension with provided UUID
        // $extension = Extensions::findOrFail($request->extension);

        // // Delete cache and update extension
        // if (session_status() == PHP_SESSION_NONE  || session_id() == '') {
        //     session_start();
        // }
        // $cache = new cache;
        // $cache->delete("directory:".$extension->extension."@".$extension->user_context);

        $voicemail->fill($attributes);    
        $voicemail->save();
      

        //clear the destinations session array
        if (isset($_SESSION['destinations']['array'])) {
            unset($_SESSION['destinations']['array']);
        }

        return response()->json([
            'voicemail' => $voicemail->voicemail_id,
            //'request' => $attributes,
            'status' => 'success',
            'message' => 'Voicemail has been created'
        ]);

    }

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


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $voicemail = Voicemails::findOrFail($id);

        if(isset($voicemail)){
            $deleted = $voicemail->delete();

            if ($deleted){
                return response()->json([
                    'status' => 200,
                    'success' => [
                        'message' => 'Selected vocemail extensions have been deleted'
                    ]
                ]);
            } else {
                return response()->json([
                    'status' => 401,
                    'error' => [
                        'message' => 'There was an error deleting selected voicemail extensions'
                    ]
                ]);
            }
        }
    }

}
