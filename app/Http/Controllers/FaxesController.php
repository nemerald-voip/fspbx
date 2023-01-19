<?php

namespace App\Http\Controllers;


use cache;
use Carbon\Carbon;
use App\Models\Faxes;
use App\Models\Domain;
use App\Models\FaxLogs;
use App\Models\FaxFiles;
use App\Models\Extensions;
use App\Models\Voicemails;
use App\Models\Destinations;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\FaxAllowedEmails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\FaxAllowedDomainNames;
use libphonenumber\PhoneNumberFormat;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Propaganistas\LaravelPhone\PhoneNumber;

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
        if (!userCheckPermission("fax_view")){
            return redirect('/');
        }
        // $list = Session::get('permissions', false);
        // pr($list);exit;
        $domain_uuid=Session::get('domain_uuid');
        $data['faxes']=Faxes::where('domain_uuid',$domain_uuid)->get();
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
        
        return view('layouts.fax.list')
            ->with($data)
            ->with('permissions',$permissions);  
    }

    public function inbox(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("fax_inbox_view")){
            return redirect('/');
        }
        $domain_uuid=Session::get('domain_uuid');
        
        $files=FaxFiles::where('fax_uuid',$request->id)->where('fax_mode','rx')->where('domain_uuid',$domain_uuid)->orderBy('fax_date','desc')->get();
        $data['files']=$files;
        $time_zone = get_local_time_zone($domain_uuid);
        foreach($files as $file){
            if (Storage::disk('fax')->exists($file->domain->domain_name . '/' . $file->fax->fax_extension .  "/inbox/" . substr(basename($file->fax_file_path), 0, (strlen(basename($file->fax_file_path)) -4)) . '.'.$file->fax_file_type)){
                $file->fax_file_path = Storage::disk('fax')->path($file->domain->domain_name . '/' . $file->fax->fax_extension .  "/inbox/" . substr(basename($file->fax_file_path), 0, (strlen(basename($file->fax_file_path)) -4)) . '.'.$file->fax_file_type);
            }
            $file->fax_date = Carbon::createFromTimestamp($file->fax_epoch, $time_zone)->toDayDateTimeString();
        }
        $permissions['delete'] = userCheckPermission('fax_inbox_delete');
        return view('layouts.fax.inbox.list')
            ->with($data)
            ->with('permissions',$permissions);  

    }

    public function downloadInboxFaxFile(FaxFiles $file)
    {

        $path = $file->domain->domain_name . '/' . $file->fax->fax_extension .  "/inbox/" . substr(basename($file->fax_file_path), 0, (strlen(basename($file->fax_file_path)) -4)) . '.'.$file->fax_file_type;

        if(!Storage::disk('fax')->exists($path)) {
                abort (404);
        }
  
        $file = Storage::disk('fax')->path($path);
        $type = Storage::disk('fax')->mimeType($path);
        $headers = array (
            'Content-Type: ' . $type,
        );

        $response = Response::download($file, basename($file), $headers);

        return $response;
    }

    public function downloadSentFaxFile(FaxFiles $file)
    {

        $path = $file->domain->domain_name . '/' . $file->fax->fax_extension .  "/sent/" . substr(basename($file->fax_file_path), 0, (strlen(basename($file->fax_file_path)) -4)) . '.'.$file->fax_file_type;

        if(!Storage::disk('fax')->exists($path)) {
                abort (404);
        }
  
        $file = Storage::disk('fax')->path($path);
        $type = Storage::disk('fax')->mimeType($path);
        $headers = array (
            'Content-Type: ' . $type,
        );

        $response = Response::download($file, basename($file), $headers);

        return $response;
    }
  

    
    public function sent(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("fax_sent_view")){
            return redirect('/');
        }
        $domain_uuid=Session::get('domain_uuid');
        $files=FaxFiles::where('fax_uuid',$request->id)->where('fax_mode','tx')->where('domain_uuid',$domain_uuid)->orderBy('fax_date','desc')->get();
        $time_zone = get_local_time_zone($domain_uuid);
        foreach($files as $file){
            $file->fax_date = Carbon::createFromTimestamp($file->fax_epoch, $time_zone)->toDayDateTimeString();
        }
        $data['files']=$files;
        $permissions['delete'] = userCheckPermission('fax_sent_delete');
        return view('layouts.fax.sent.list')
            ->with($data)
            ->with('permissions',$permissions);  

    }

    
    public function log(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("fax_log_view")){
            return redirect('/');
        }
        $domain_uuid=Session::get('domain_uuid');
        $logs=FaxLogs::where('fax_uuid',$request->id)->where('domain_uuid',$domain_uuid)->orderBy('fax_date','desc')->get();
        $time_zone = get_local_time_zone($domain_uuid);
        foreach($logs as $log){
            $log->fax_date = Carbon::createFromTimestamp($log->fax_epoch, $time_zone)->toDayDateTimeString();
        }


        $data['logs']=$logs;
        $permissions['delete'] = userCheckPermission('fax_log_delete');
        return view('layouts.fax.log.list')
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
        // Check permissions
        if (!userCheckPermission("fax_add")){
            return redirect('/');
        }

        
        // Get all phone numbers
        $destinations = Destinations::where('destination_enabled', 'true')
        ->where ('domain_uuid', Session::get('domain_uuid'))
        ->get([
            'destination_uuid',
            'destination_number',
            'destination_enabled',
            'destination_description',
            DB::Raw("coalesce(destination_description , '') as destination_description"),
        ])
        ->sortBy('destination_number');


        $data=[];
        $fax=new Faxes;
        $data['fax']= $fax;
        $data['domain']=Session::get('domain_name');
        $data['destinations']=$destinations;
        $data['national_phone_number_format']=PhoneNumberFormat::NATIONAL;
        $data['allowed_emails'] = $fax->allowed_emails;
        $data['allowed_domain_names'] = $fax->allowed_domain_names;
        
        return view('layouts.fax.createOrUpdate')->with($data);;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request,Faxes $fax)
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
            'fax_email' => 'nullable|email:rfc,dns',
            'fax_caller_id_name' => 'nullable',
            'fax_caller_id_number' => 'nullable',
            'fax_forward_number' => 'nullable',
            'fax_toll_allow' => 'nullable',
            'fax_send_channels' => 'nullable',
            'fax_description' => 'nullable|string|max:100',
            'email_list' => 'nullable|array',

        ], [], $attributes);

        if ($validator->fails()) {
            return response()->json(['error'=>$validator->errors()]);
        }

        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();
        $attributes['domain_uuid'] = Session::get('domain_uuid');
        $attributes['accountcode']=Session::get('domain_name');
        $attributes['fax_prefix']=9999;
        $attributes['fax_destination_number']=$attributes['fax_extension'];
        $fax->fill($attributes);    
        $fax->save();

        // If allowed email list is submitted save it to database
        if (isset($attributes['email_list'])) {
            foreach($attributes['email_list'] as $email){
                $allowed_email = new FaxAllowedEmails();
                $allowed_email->fax_uuid = $fax->fax_uuid;
                $allowed_email->email = $email;
                $allowed_email->save();
            }
        } 

        // If allowed domain list is submitted save it to database
        if (isset($attributes['domain_list'])) {
            foreach($attributes['domain_list'] as $domain){
                $allowed_domain = new FaxAllowedDomainNames();
                $allowed_domain->fax_uuid = $fax->fax_uuid;
                $allowed_domain->domain = $domain;
                $allowed_domain->save();
            }
        } 

        //clear the destinations session array
        if (isset($_SESSION['destinations']['array'])) {
            unset($_SESSION['destinations']['array']);
        }

        return response()->json([
            'fax' => $fax->fax_uuid,
            'redirect_url' =>route('faxes.edit',['fax'=>$fax->fax_uuid]),
            'status' => 'success',
            'message' => 'Fax has been created'
        ]);

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
    public function edit(Faxes $fax)
    {
           //check permissions
	    if (!userCheckPermission('fax_edit')) {
            return redirect('/');
	    }

        //Check FusionPBX login status
        session_start();
        if(session_status() === PHP_SESSION_NONE) {
            return redirect()->route('logout');
        }
        
        // Get all phone numbers
        $destinations = Destinations::where('destination_enabled', 'true')
        ->where ('domain_uuid', Session::get('domain_uuid'))
        ->get([
            'destination_uuid',
            'destination_number',
            'destination_enabled',
            'destination_description',
            DB::Raw("coalesce(destination_description , '') as destination_description"),
        ])
        ->sortBy('destination_number');


        $data=array();
        $data['fax']=$fax;
        $data['domain']=Session::get('domain_name');
        $data['destinations']=$destinations;
        $data['national_phone_number_format'] = PhoneNumberFormat::NATIONAL;
        $data['allowed_emails'] = $fax->allowed_emails;
        $data['allowed_domain_names'] = $fax->allowed_domain_names;

        return view('layouts.fax.createOrUpdate')->with($data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
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
            'fax_email' => 'nullable|email:rfc,dns',
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
             return response()->json(['error'=>$validator->errors()]);
        }
 
        // Retrieve the validated input assign all attributes
        $attributes = $validator->validated();
        $attributes['fax_destination_number']=$attributes['fax_extension'];
        $fax->fill($attributes);  
        $fax->update($attributes);  
 

        // Remove current allowed emails from the database
        if (isset($fax->allowed_emails)) {
            foreach($fax->allowed_emails as $email) {
                $email->delete();
            }
        }

        // Remove current allowed domains from the database
        if (isset($fax->allowed_domain_names)) {
            foreach($fax->allowed_domain_names as $domain_name) {
                $domain_name->delete();
            }
        }

        // If allowed email list is submitted save it to database
        if (isset($attributes['email_list'])) {
            foreach($attributes['email_list'] as $email){
                $allowed_email = new FaxAllowedEmails();
                $allowed_email->fax_uuid = $fax->fax_uuid;
                $allowed_email->email = $email;
                $allowed_email->save();
            }
        } 

        // If allowed domain list is submitted save it to database
        if (isset($attributes['domain_list'])) {
            foreach($attributes['domain_list'] as $domain){
                $allowed_domain = new FaxAllowedDomainNames();
                $allowed_domain->fax_uuid = $fax->fax_uuid;
                $allowed_domain->domain = $domain;
                $allowed_domain->save();
            }
        } 
         
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $fax = Faxes::findOrFail($id);

        if(isset($fax)){
            $deleted = $fax->delete();
            if ($deleted){
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

        if(isset($fax)){
            $deleted = $fax->delete();
            if ($deleted){
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

        if(isset($fax)){
            $deleted = $fax->delete();
            if ($deleted){
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

    public function new(Request $request)
    {
        // Check permissions
        if (!userCheckPermission("fax_send")){
            return redirect('/');
        }
        $data=[];
        $data['id']= $request->id;
        $data['domain']=Session::get('domain_name');
        $data['destinations']=[];
        $data['fax']=new Faxes;
        $data['national_phone_number_format']=PhoneNumberFormat::NATIONAL;
        
        return view('layouts.fax.new.createOrUpdate')->with($data);;
    }

    public function sendFax(Request $request){

    }
}
