<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DefaultSettings;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\ArchiveRecording;
use App\Models\CDR;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
 
class UploadArchiveFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UploadArchiveFiles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->uploadRecordings();
        return 0;
    }
    public function uploadRecordings(){
        $start_date = date("Y-m-d", strtotime("-1 days"));
        $recordings=$this->getCallRecrordings($start_date);


      
        $failed=[];
        $success=[];
        foreach($recordings as $key=>$call_recording){
                    $setting=getS3Setting($call_recording->domain_uuid);
                    // $domain=$this->getDomainName($call_recording->domain_uuid);
            

                    $s3 = new \Aws\S3\S3Client([
                    'region'  => $setting['region'],
                    'version' => 'latest',
                    'credentials' => [
                        'key'    => $setting['key'],
                        'secret' => $setting['secret']
                    ]
                    ]);
                try{
                    if(!empty($call_recording->record_name)){
                    //S3 file location
                    $location=$call_recording->domain_name.'/'.date('Y',strtotime($call_recording->answer_stamp)).'/'.date('m',strtotime($call_recording->answer_stamp)).'/'.date('d',strtotime($call_recording->answer_stamp)).'/';
                    //S3 Object name
                    // $object_key=$location . $call_recording->call_recording_name;
                    $ext=explode('.',$call_recording->record_name);
                    $file_ext='wav';
                    if(isset($ext[1])){
                        $file_ext=$ext[1];
                    }
                    $object_key=$location . $call_recording->direction.'-' . $call_recording->caller_destination.'-'.$call_recording->caller_id_number.'-'.date('mdy',strtotime($call_recording->start_stamp)).'-'.date('hmi',strtotime($call_recording->start_stamp)).'.'.$file_ext;
                    $path=$s3->putObject(array(
                        'Bucket'     => $setting['bucket'],
                        'SourceFile' => $call_recording->record_path.'/'.$call_recording->record_name,
                        'Key'        => $object_key
                    ));

                   
                    // $call_recording->call_recording_name
                    
                    $archive=new ArchiveRecording();
                    $archive->domain_uuid=$call_recording->domain_uuid;
                    $archive->s3_path=$path['ObjectURL'];
                    $archive->object_key=$object_key;
                    $call_recording->archive_recording()->save($archive);
                
                    unlink($call_recording->record_path.'/'.$call_recording->record_name);
                    if(!empty($call_recording->record_name)){
                        array_push($success,$call_recording->record_name);
                    }
                }
                   
                } catch(\Exception $ex){
                    if(!empty($call_recording->record_name)){
                        array_push($failed,$call_recording->record_name);
                    }
                }

                     
        }
        //    if(!empty($failed)){
                $this->sendFailEmail($failed,$success);
            // }
    
    }
    public function sendFailEmail($failed,$success){
        $view=view('emails.failed_upload')->with(['failed'=>$failed,'success'=>$success]);
        // sergei@nemerald.com
        $mail = Send_Email(array('email_layout'=>'emails/content','content'=>$view,'subject'=>'Completed - failed uploads.','user'=>(object) array('name'=>'','email'=>'sergei@nemerald.com')));
    }

  

    public function getDomainName($domain_id){
        return Domain::where('domain_uuid',$domain_id)->first();
    }
    
    public function getCallRecrordings($date){
        // return CallRecordings::whereRaw("DATE(call_recording_date) = '2022-06-28'")->get();
        // return CallRecordings::get();
        // $rec = CallRecordings::whereHas('ArchiveRecording', function($q) {
        // })->pluck('id')->toArray();
        // pr($rec);
        // exit;
 
            // return CDR::get();
        $calls=CDR::select('v_xml_cdr.*')
        ->leftJoin('archive_recording', function($join) {
            $join->on('v_xml_cdr.xml_cdr_uuid','archive_recording.xml_cdr_uuid');
        })
        ->whereRaw("DATE(start_stamp) < '$date'")
        ->whereRaw('archive_recording.id IS NULL')
        ->get();

        return $calls; 

    }

  
}
