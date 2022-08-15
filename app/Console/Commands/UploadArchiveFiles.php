<?php

namespace App\Console\Commands;

use App\Jobs\SendS3UploadReport;
use Illuminate\Console\Command;
use App\Models\DefaultSettings;
use App\Models\Domain;
use App\Models\DomainSettings;
use App\Models\ArchiveRecording;
use App\Models\CDR;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
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
        //$start_date = date("Y-m-d", strtotime("-1 days"));
        //$recordings=$this->getCallRecordings($start_date);
        $recordings=$this->getCallRecordings();

        $failed=[];
        $success=[];
        foreach($recordings as $key=>$call_recording){
                    $setting=getS3Setting($call_recording->domain_uuid);
                    
                    $s3 = new \Aws\S3\S3Client([
                    'region'  => $setting['region'],
                    'version' => 'latest',
                    'credentials' => [
                        'key'    => $setting['key'],
                        'secret' => $setting['secret']
                    ]
                    ]);
                try{

                        if(file_exists($call_recording->record_path.'/'.$call_recording->record_name)){

                            Log::info("Uploading File : " .$call_recording->record_path.'/'.$call_recording->record_name);
                            //exit();

                            // Set default time zone for this script
                            date_default_timezone_set('America/Los_Angeles');

                            //S3 file location
                            if ($setting['type'] == "default") {
                                $location=$call_recording->domain_name.'/'.date('Y',strtotime($call_recording->start_stamp)).'/'.date('m',strtotime($call_recording->start_stamp)).'/'.date('d',strtotime($call_recording->start_stamp)).'/';
                            } elseif ($setting['type'] == "custom") {
                                $location='recordings'.'/'.date('Y',strtotime($call_recording->start_stamp)).'/'.date('m',strtotime($call_recording->start_stamp)).'/'.date('d',strtotime($call_recording->start_stamp)).'/';
                            }

                            //S3 Object name
                            $ext=explode('.',$call_recording->record_name);
                            $file_ext='wav';
                            if(isset($ext[1])){
                                $file_ext=$ext[1];
                            }

                            $object_key=$location . $call_recording->direction.'-' . $call_recording->caller_id_number.'-'.$call_recording->caller_destination.'-'.date('m.d.y',strtotime($call_recording->start_stamp)).'-'.date('h:i:sA',strtotime($call_recording->start_stamp)).'.'.$file_ext;
                            $path=$s3->putObject(array(
                                'Bucket'     => $setting['bucket'],
                                'SourceFile' => $call_recording->record_path.'/'.$call_recording->record_name,
                                'Key'        => $object_key
                            ));

                            //Add uploaded recording to the database    
                            $archive=new ArchiveRecording();
                            $archive->domain_uuid=$call_recording->domain_uuid;
                            $archive->s3_path=$path['ObjectURL'];
                            $archive->object_key=$object_key;
                            $call_recording->archive_recording()->save($archive);
                        
                            unlink($call_recording->record_path.'/'.$call_recording->record_name);

                            array_push($success,$call_recording->record_name);

                        } else {
                            // If file doesn't exist empty the database record file name
                            Log::info("File doesn't exist: " .$call_recording->record_name);
                            $cdr = CDR::find($call_recording->xml_cdr_uuid);
                            $call_recording->record_name = '';
                            $cdr->update([
                                'record_name' => $call_recording->record_name
                            ]);

                        }
                    
                   
                } catch(\Exception $ex){
                    if(!empty($call_recording->record_name)){
                        
                        array_push($failed,['msg'=>$ex->getMessage(),'name'=>$call_recording->record_name]);
                    }
                }
        
        }
        //    if(!empty($failed)){
            // Send email report with upload status
            $attributes['email'] = $setting['upload_notification_email'];
            $attributes['failed'] = $failed;
            $attributes['success'] = $success;
            SendS3UploadReport::dispatch($attributes)->onQueue('emails');

                // $this->sendFailEmail($failed,$success);
            // }
    
    }
    public function sendFailEmail($failed,$success){
        $view=view('emails.failed_upload')->with(['failed'=>$failed,'success'=>$success]);
        // sergei@nemerald.com
        // sergei@nemerald.com
        $emails=['sergei@nemerald.com'];
        foreach($emails as $email){
            $mail = sendEmail(array('email_layout'=>'emails/content','content'=>$view,'subject'=>'Completed - failed uploads.','user'=>(object) array('name'=>'','email'=>$email)));
        }
    }

  

    public function getDomainName($domain_id){
        return Domain::where('domain_uuid',$domain_id)->first();
    }
    
    public function getCallRecordings(){
 
        // Get all calls that have call recordings
        $calls=CDR::select([
            'xml_cdr_uuid',
            'domain_uuid',
            'domain_name',
            'direction',
            'caller_id_number',
            'caller_destination',
            'start_stamp',
            'record_path',
            'record_name'
        ])

        ->where ('record_name','<>','')
        ->whereDate('start_stamp', '<', Carbon::yesterday()->toDateTimeString())
        ->take (2000)
        // ->toSql();
        ->get();
        // Log::info($calls);
        // exit();
        return $calls; 

    }

  
}
