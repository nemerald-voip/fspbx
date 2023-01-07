<?php

namespace App\Models;

use text;
use database;
use Exception;
use Throwable;
use permisssions;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Jobs\SendFaxNotificationToSlack;
use libphonenumber\NumberParseException;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Symfony\Component\Process\Exception\ProcessFailedException;


class Faxes extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_fax";

    public $timestamps = false;

    protected $primaryKey = 'fax_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    // private $domain



    public function EmailToFax ($payload){
        $this->message = "*EmailToFax* From: " . $payload['FromFull']['Email'] . ", To:" . $payload['fax_destination'] ."\n";
        // Get email subject and make sure it's valid
        // $subject = $this->webhookCall->payload['Subject'];

        $this->domain = Domain::find($payload['domain_uuid']);

        $settings= DefaultSettings::where('default_setting_category','switch')
        ->get([
            'default_setting_subcategory',
            'default_setting_name',
            'default_setting_value',
        ]);

        foreach ($settings as $setting) {
            if ($setting->default_setting_subcategory == 'storage') {
                $this->fax_dir = $setting->default_setting_value . '/fax/' . $this->domain->domain_name;
                $this->stor_dir = $setting->default_setting_value;
            }            
        }

        // Pick the first fax that belongs to this domain
        // This needs to be fixed in the future
        try {
            $this->fax_extension = $this->domain->faxes->firstOrFail();
        } catch (Throwable $e) {
            $this->message .= "No faxes found for domain - " . $this->domain->domain_description ;
            SendFaxNotificationToSlack::dispatch($this->message)->onQueue('faxes');
            return "abort(404). No fax is set up for this domain";
        }

        // Create all fax directories 
        $this->CreateFaxDirectories();

        // $fax_sender = '';
        $this->fax_caller_id_number = $this->fax_extension->fax_caller_id_number;
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($this->fax_caller_id_number, 'US');
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)){
                $this->fax_caller_id_number = $phoneNumberUtil
                            ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::E164);
            } else {
                $this->message .= "Invalid Caller ID is set up for fax server " . $this->fax_extension->fax_extension . ": " . $this->fax_caller_id_number ;
                SendFaxNotificationToSlack::dispatch($this->message)->onQueue('faxes');
                return "abort(404). Invalid caller ID";
            }
        } catch (NumberParseException $e) {
            // Process invalid Fax Caller ID
            $this->message .= "Invalid Caller ID is set up for fax server " . $this->fax_extension->fax_extension . ": " . $this->fax_caller_id_number ;
            SendFaxNotificationToSlack::dispatch($this->message)->onQueue('faxes');
            return "abort(404). Invalid caller ID";
        }


        $this->fax_toll_allow = $this->fax_extension->fax_toll_allow;
        $this->fax_accountcode = $this->fax_extension->fax_accountcode;
        $this->fax_send_greeting = $this->fax_extension->fax_send_greeting;
        $this->fax_uuid = $this->fax_extension->fax_uuid;
        $this->fax_caller_id_name = $this->fax_extension->fax_caller_id_name;

        //get email body (if any) for cover page. 
		$fax_message = $payload['TextBody'];
        $fax_message = strip_tags($fax_message);
        $fax_message = html_entity_decode($fax_message);
        $fax_message = str_replace("\r\n\r\n", "\r\n", $fax_message);

        //Set default allowed extensions 
        $this->fax_allowed_extensions = DefaultSettings::where('default_setting_category','fax')
            ->where('default_setting_subcategory','allowed_extension')
            ->where('default_setting_enabled','true')
            ->pluck('default_setting_value')
            ->toArray();

        if (empty($this->fax_allowed_extensions)) {
            $this->fax_allowed_extensions = array('.pdf', '.tiff', '.tif');
        }

        $settings= DefaultSettings::where('default_setting_category','fax')
        ->get([
            'default_setting_subcategory',
            'default_setting_name',
            'default_setting_value',
        ]);

        $this->dialplan_variables = array();
        foreach ($settings as $setting) {
            if ($setting->default_setting_subcategory == 'page_size') {
                $this->fax_page_size = $setting->default_setting_value;
                //determine page size
                switch ($this->fax_page_size) {
                    case 'a4' :
                        $this->page_width = 8.3; //in
                        $this->page_height = 11.7; //in
                        break;
                    case 'legal' :
                        $this->page_width = 8.5; //in
                        $this->page_height = 14; //in
                        break;
                    case 'letter' :
                        $this->page_width = 8.5; //in
                        $this->page_height = 11; //in
                        break;
                    default	:
                        $this->page_width = 8.5; //in
                        $this->page_height = 11; //in
                        $this->fax_page_size = 'letter';
                }
            }            
            if ($setting->default_setting_subcategory == 'resolution') {
                $this->fax_resolution = $setting->default_setting_value;
                switch ($this->fax_resolution) {
                    case 'fine':
                        $this->gs_r = '204x196';
                        $this->gs_g = ((int) ($this->page_width * 204)).'x'.((int) ($this->page_height * 196));
                        break;
                    case 'superfine':
                        $this->gs_r = '204x392';
                        $this->gs_g = ((int) ($this->page_width * 204)).'x'.((int) ($this->page_height * 392));
                        break;
                    case 'normal':
                    default:
                        $this->gs_r = '204x98';
                        $this->gs_g = ((int) ($this->page_width * 204)).'x'.((int) ($this->page_height * 98));
                        break;
                }
            }  
            if ($setting->default_setting_subcategory == 'cover_header') {
                $this->fax_header = $setting->default_setting_value;
            }  
            if ($setting->default_setting_subcategory == 'cover_footer') {
                $this->fax_footer = $setting->default_setting_value;
            }  
            if ($setting->default_setting_subcategory == 'cover_font') {
                $this->fax_cover_font = $setting->default_setting_value;
            }
            if ($setting->default_setting_subcategory == 'cover_logo') {
            }
            if ($setting->default_setting_subcategory == 'smtp_from') {
                $this->smtp_from = $setting->default_setting_value;
            }
            if ($setting->default_setting_subcategory == 'variable') {
                $this->dialplan_variables = array_merge($this->dialplan_variables, [$setting->default_setting_value]);
            }
        
        }

        // If email has attachents convert them to TIF files for faxing
        if (sizeof($payload['Attachments']) > 0) {
            $this->attachments = $payload['Attachments'];
            $this->convertAttachmentsToTif();
        } else {
            // Abort
            $this->message .= "Email has no attachments. Aborting";
            Log::alert($this->message);
            SendFaxNotificationToSlack::dispatch($this->message)->onQueue('faxes');
            return "No attachements";
        }




        // Set fax subject
        $this->fax_subject = $payload['Subject'];

        //Set fax destination
        $this->fax_destination = $payload['fax_destination'];

        // Set fax from 
        $this->fax_from = $payload['FromFull']['Email'];

        $this->dial_string = $this->getDialstring();

        //add fax to the fax queue or send it directly
        if ($this->fax_queue_enabled) {
            $fax_queue = new FaxQueues();
            $fax_queue->fax_queue_uuid = $this->fax_queue_uuid;
            $fax_queue->domain_uuid = $this->domain->domain_uuid;
            $fax_queue->fax_uuid = $this->fax_extension ->fax_uuid;
            $fax_queue->fax_date = now();
            $fax_queue->hostname = gethostname();
            $fax_queue->fax_caller_id_name = $this->fax_caller_id_name;
            $fax_queue->fax_caller_id_number = $this->fax_caller_id_number;
            $fax_queue->fax_number = $this->fax_destination;
            $fax_queue->fax_prefix = $this->fax_extension->fax_prefix;
            $fax_queue->fax_email_address = $this->fax_from;
            $fax_queue->fax_email_address = $this->fax_from;
            $fax_queue->fax_file = $this->dir_fax_sent."/".$this->fax_instance_uuid.".tif";
            $fax_queue->fax_status = 'waiting';
            $fax_queue->fax_retry_count = 0;
            $fax_queue->fax_accountcode = $this->fax_accountcode;
            $fax_queue->fax_command = 'originate '.$this->dial_string;
            $fax_queue->save();
        }
        



        Log::alert("----------Webhook Job ends-----------");

        return "ok";

    }

    public function getDialstring() {
        $dial_string = "";
        $this->fax_queue_uuid = Str::uuid()->toString();
        //send the fax
        $fax_file = $this->dir_fax_sent."/".$this->fax_instance_uuid.".tif";
        $dial_string .= "fax_queue_uuid="                . $this->fax_queue_uuid          . ",";
        $dial_string .= "accountcode='"                  . $this->fax_accountcode         . "',";
        $dial_string .= "sip_h_accountcode='"            . $this->fax_accountcode         . "',";
        $dial_string .= "domain_uuid="                   . $this->domain->domain_uuid . ",";
        $dial_string .= "domain_name="                   . $this->domain->domain_name  . ",";
        $dial_string .= "origination_caller_id_name='"   . $this->fax_caller_id_name      . "',";
        $dial_string .= "origination_caller_id_number='" . $this->fax_caller_id_number    . "',";
        $dial_string .= "fax_ident='"                    . $this->fax_caller_id_number    . "',";
        $dial_string .= "fax_header='"                   . $this->fax_caller_id_name      . "',";
        $dial_string .= "fax_file='"                     . $fax_file               . "',";

        //prepare the fax command
        $channel_variables = array();
        if (strlen($this->fax_toll_allow) > 0) {
            $channel_variables["toll_allow"] = $this->fax_toll_allow;
        }

        $route_array = outbound_route_to_bridge($this->domain->domain_uuid, $this->fax_extension->fax_prefix . $this->fax_destination, $channel_variables);

        if (count($route_array) == 0) {
            //send the internal call to the registered extension
            $fax_uri = "user/".$this->fax_destination."@".$this->domain->domain_uuid;
            $fax_variables = "";
        }
        else {
            //send the external call
            $fax_uri = $route_array[0];
            $fax_variables = "";
            foreach($this->dialplan_variables as $variable) {
                $fax_variables .= $variable.",";
            }
        }

        // Check if Fax Queue is enabled
        $fax_queue_enabled = DefaultSettings::where('default_setting_category','fax_queue')
            ->where('default_setting_subcategory','enabled')
            ->where('default_setting_enabled','true')
            ->pluck('default_setting_value')
            ->toArray();
        if (sizeof($fax_queue_enabled) > 0 && $fax_queue_enabled[0] == 'true') {
            $this->fax_queue_enabled = true;
        } else {
            $this->fax_queue_enabled = false;
        }

        //build the fax dial string
        $dial_string .= $fax_variables;
        $dial_string .= "mailto_address='"     . $this->fax_extension->fax_email   . "',";
        $dial_string .= "mailfrom_address='"   . $this->fax_from . "',";
        $dial_string .= "fax_uri="             . $fax_uri           . ",";
        $dial_string .= "fax_retry_attempts=1" . ",";
        $dial_string .= "fax_retry_limit=20"   . ",";
        $dial_string .= "fax_retry_sleep=180"  . ",";
        //$dial_string .= "fax_verbose=true"     . ",";
        $dial_string .= "fax_use_ecm=off"      . ",";
        if ($this->fax_queue_enabled) {
            $dial_string .= "api_hangup_hook='lua app/fax/resources/scripts/hangup_tx.lua'";
        }
        else {
            $dial_string .= "api_hangup_hook='lua fax_retry.lua'";
        }
        $dial_string  = "{" . $dial_string . "}" . $fax_uri." &txfax('".$fax_file."')";

        return $dial_string;
    }

    public function CreateFaxDirectories() {
        try {
            // Set variables for all directories
            $this->dir_fax_inbox = $this->fax_dir.'/'.$this->fax_extension->fax_extension.'/inbox';
            $this->dir_fax_sent = $this->fax_dir.'/'.$this->fax_extension->fax_extension.'/sent';
            $this->dir_fax_temp = $this->fax_dir.'/'.$this->fax_extension->fax_extension.'/temp';
    
            //make sure the directories exist
            if (!is_dir($this->fax_dir)) {
                mkdir($this->fax_dir, 0770);
            }
            if (!is_dir($this->fax_dir.'/fax')) {
                mkdir($this->fax_dir.'/fax', 0770);
            }
            if (!is_dir($this->fax_dir.'/fax/'.$this->domain->domain_name)) {
                mkdir($this->fax_dir.'/fax/'.$this->domain->domain_name, 0770);
            }
            if (!is_dir($this->fax_dir.'/'.$this->fax_extension->fax_extension)) {
                mkdir($this->fax_dir.'/'.$this->fax_extension->fax_extension, 0770);
            }
            if (!is_dir($this->dir_fax_inbox)) {
                mkdir($this->dir_fax_inbox, 0770);
            }
            if (!is_dir($this->dir_fax_sent)) {
                mkdir($this->dir_fax_sent, 0770);
            }
            if (!is_dir($this->dir_fax_temp)) {
                mkdir($this->dir_fax_temp, 0770);
            }
        } catch (Throwable $e) {
            $this->message .= $e->getMessage() . " at ". $e->getFile() . ":". $e->getLine().'\n';
            Log::alert($this->message);
            SendFaxNotificationToSlack::dispatch($this->message)->onQueue('faxes');
            //Process errors
        }
       
    }

    public function convertAttachmentsToTif(){
        $tif_files = array();
        foreach ($this->attachments as $attachment){
            $fax_file_extension = strtolower(pathinfo($attachment['Name'], PATHINFO_EXTENSION));

            //block unknown files
            if ($fax_file_extension == '') {continue; }

            //block unauthorized files
            if (!in_array('.' . $fax_file_extension,$this->fax_allowed_extensions)) { continue; }

            $uuid_filename = Str::uuid()->toString();

            // Save attachment to the storage
            try {
                $path = Storage::disk('fax')->put($this->domain->domain_name . '/'. $this->fax_extension->fax_extension . '/temp/' . $uuid_filename.'.'.$fax_file_extension, base64_decode($attachment['Content']));

            } catch (Throwable $e) {
                    $slack_message = $e->getMessage() . " at ". $e->getFile() . ":". $e->getLine();
                    SendFaxNotificationToSlack::dispatch($this->message . ' ' . $slack_message)->onQueue('faxes');
                    continue;
            }                

            //convert files to pdf, if necessary
            if ($fax_file_extension != "pdf") {
                $process = new Process([
                    "libreoffice",
                    "--headless",
                    "--convert-to",
                    "pdf",
                    "--outdir",
                    "{$this->dir_fax_temp}",
                    "{$this->dir_fax_temp}/{$uuid_filename}.{$fax_file_extension}"

                ], 
                null, [
                    'HOME' => '/tmp'
                ]);

                try {
                    $process->setWorkingDirectory($this->dir_fax_temp);
                    $process->mustRun();

                    //log::alert($process->getOutput());

                    //remove the original file
                    $deleted = Storage::disk('fax')->delete($this->domain->domain_name . '/'. $this->fax_extension->fax_extension . '/temp/' . $uuid_filename.'.'.$fax_file_extension);

                } catch (ProcessFailedException $e) {
                    $this->message .= $e->getMessage();
                    Log::alert($e->getMessage());
                    SendFaxNotificationToSlack::dispatch($this->message)->onQueue('faxes');
                }
            }


            // Convert files to tif
            if (file_exists($this->dir_fax_temp.'/'.$uuid_filename.'.pdf')) {
                $process = new Process([
                    "gs",
                    "-q",
                    "-r{$this->gs_r}",
                    "-g{$this->gs_g}",
                    "-dBATCH",
                    "-dPDFFitPage",
                    "-dNOSAFER",
                    "-dNOPAUSE",
                    "-sOutputFile={$uuid_filename}.tif",
                    "-sDEVICE=tiffg4",
                    "-Ilib",
                    "stocht.ps",
                    "-c",
                    "{ .75 gt { 1 } { 0 } ifelse} settransfer",
                    "--",
                    "{$uuid_filename}.pdf",
                    "-c",
                    "quit"
                ], 
                null, [
                    'HOME' => '/tmp'
                ]);

                try {
                    $process->setWorkingDirectory($this->dir_fax_temp);
                    $process->mustRun();

                    // log::alert($process->getOutput());

                    //remove the original file
                    $deleted = Storage::disk('fax')->delete($this->domain->domain_name . '/'. $this->fax_extension->fax_extension . '/temp/' . $uuid_filename.'.pdf');

                } catch (ProcessFailedException $e) {
                    $this->message .= $e->getMessage();
                    Log::alert($e->getMessage());
                    SendFaxNotificationToSlack::dispatch($this->message)->onQueue('faxes');
                }
            }

            // //Count pages
            // $process = new Process([
            //     "tiffinfo",
            //     "{$uuid_filename}.tif",
            // ], 
            // null, [
            //     'HOME' => '/tmp'
            // ]);

            // try {
            //     $process->setWorkingDirectory($this->dir_fax_temp);
            //     $process->mustRun();

            //     log::alert($process->getOutput());


            // } catch (ProcessFailedException $e) {
            //     log::alert($e->getMessage());
            //     $slack_message = $e->getMessage();
            //     SendFaxNotificationToSlack::dispatch($this->message . ' ' . $slack_message)->onQueue('faxes');
            // }

            //add file to array
            $tif_files[] = $uuid_filename.'.tif';

        }

        // Check if email had allowed attachments
        if (sizeof($tif_files) == 0) {
            $this->message .= "Couldn't proccess any of the attached files. Please refer to the list of allowed extensions";
            Log::alert($this->message);
            SendFaxNotificationToSlack::dispatch($this->message)->onQueue('faxes');
            return "No allowed attachments";
        }

        //Generate cover page
        if ($this->fax_cover){
        // Create cover here
        
        }

        //combine tif files into single multi-page tif
        if (is_array($tif_files) && sizeof($tif_files) > 0) {

            $this->fax_instance_uuid = Str::uuid()->toString();

            $file_names = '';
            $parameters = array("tiffcp", "-c","none");

            foreach ($tif_files as $tif_file) {
                $parameters[] = $this->dir_fax_temp . "/" .$tif_file;
            }
            $parameters[] = $this->dir_fax_sent . "/" . $this->fax_instance_uuid . ".tif";
            
            $process = new Process($parameters, 
            null, [
                'HOME' => '/tmp'
            ]);

            try {
                $process->setWorkingDirectory($this->dir_fax_temp);
                $process->mustRun();

                // log::alert($process->getOutput());

            } catch (ProcessFailedException $e) {
                log::alert($e->getMessage());
                $slack_message = $e->getMessage();
                SendFaxNotificationToSlack::dispatch($this->message . ' ' . $slack_message)->onQueue('faxes');
            }

        }


        //generate pdf from tif
        if (file_exists($this->dir_fax_sent.'/'.$this->fax_instance_uuid.'.tif')) {

            $process = new Process([
                "tiff2pdf",
                "-u",
                "i",
                "-p",
                "{$this->fax_page_size}",
                "-w",
                "{$this->page_width}",
                "-l",
                "{$this->page_height}",
                "-f",
                "-o",
                "{$this->dir_fax_sent}/{$this->fax_instance_uuid}.pdf",
                "{$this->dir_fax_sent}/{$this->fax_instance_uuid}.tif",

            ], 
            null, [
                'HOME' => '/tmp'
            ]);

            try {
                $process->setWorkingDirectory($this->dir_fax_temp);
                $process->mustRun();

                // log::alert($process->getOutput());

            } catch (ProcessFailedException $e) {
                $this->message .= $e->getMessage();
                Log::alert($e->getMessage());
                SendFaxNotificationToSlack::dispatch($this->message)->onQueue('faxes');
            }
        }

        //remove the extra files
        foreach ($tif_files as $tif_file) {
            $deleted = Storage::disk('fax')->delete($this->domain->domain_name . '/'. $this->fax_extension->fax_extension . '/temp/' . $tif_file);
        }


    }
}
