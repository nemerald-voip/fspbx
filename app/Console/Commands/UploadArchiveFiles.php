<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DefaultSettings;
use App\Models\Domain;
use App\Models\DomainSettings;
use Illuminate\Support\Facades\Config;
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
        // $domains=$this->getDomains();
        // foreach($domains as $domain){
        //     $s3_setting=$this->getS3Setting($domain->domain_uuid);
        //     $start_date = date("Y-m-d", strtotime("-1 months"));
        //     $recordings=$this->getCallRecrordings($domain->domain_uuid,$start_date);
        //     foreach($recordings as $call_recording){

        //     }
        // }

        $this->uploadRecordings();
        return 0;
    }
    public function uploadRecordings(){
        // , strtotime("-1 months")
        $start_date = date("Y-m-d");
        $recordings=$this->getCallRecrordings($start_date);
        pr($recordings);
        exit;
        foreach($recordings as $call_recording){
            $setting=$this->getS3Setting($call_recording->domain_uuid);
        }
    
    }

    public function getDomains(){
        return Domain::all();
    }
    public function getS3Setting($domain_id){
        $config=[];
        $settings=DomainSettings::where('domain_uuid',$domain_id)->where('domain_setting_category','aws')->get();
        if(!blank($settings)){
            foreach($settings as $conf){
                $config[getCredentialKey($conf->domain_setting_subcategory)]=$conf->domain_setting_value;
            }
        } else {
            $config=$this->getDefaultS3Configuration();
        }
        return $config;
    }

    public function getCallRecrordings($date){
        return \DB::table('v_call_recordings')->whereRaw("DATE(call_recording_date) < '".$date."'")->get();
    }

    public function getDefaultS3Configuration(){
        $default_credentials=DefaultSettings::where('default_setting_category','aws')->get();
        $config=[];
        foreach($default_credentials as $d_conf){
            $config[getCredentialKey($d_conf->default_setting_subcategory)]=$d_conf->default_setting_value;
        }
        return $config;
    }
}
