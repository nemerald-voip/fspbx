<?php

namespace App\Models;

use text;
use database;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Faxes extends Model
{
    use HasFactory, \App\Models\Traits\TraitUuid;

    protected $table = "v_fax";

    public $timestamps = false;

    protected $primaryKey = 'fax_uuid';
    public $incrementing = false;
    protected $keyType = 'string';


    public function EmailToFaxInit ($payload){
        // Get email subject and make sure it's valid
        // $subject = $this->webhookCall->payload['Subject'];

        $domain = Domain::find($payload['domain_uuid']);

        // Pick the first fax that belongs to this domain
        // This needs to be fixed in the future
        $fax = $domain->faxes->first();

        $fax_extension = $fax->fax_extension;
        $fax_sender = '';
        $fax_caller_id_number = $fax->fax_caller_id_number;
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        try {
            $phoneNumberObject = $phoneNumberUtil->parse($fax_caller_id_number, 'US');
            if ($phoneNumberUtil->isValidNumber($phoneNumberObject)){
                $fax_caller_id_number = $phoneNumberUtil
                            ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::E164);
            }
        } catch (Throwable $e) {
            // Process invalid Fax Caller ID
        }
        $fax_toll_allow = $fax->fax_toll_allow;
        $fax_accountcode = $fax->fax_accountcode;
        $fax_send_greeting = $fax->fax_send_greeting;
        $fax_uuid = $fax->fax_uuid;
        $fax_caller_id_name = $fax->fax_caller_id_name;
        $fax_caller_id_number = $fax->fax_caller_id_number;

        //get email body (if any) for cover page. 
		$fax_message = $payload['TextBody'];
        $fax_message = strip_tags($fax_message);
        $fax_message = html_entity_decode($fax_message);
        $fax_message = str_replace("\r\n\r\n", "\r\n", $fax_message);

        //Set default allowed extensions 
        $fax_allowed_extensions = DefaultSettings::where('default_setting_category','fax')
            ->where('default_setting_subcategory','allowed_extension')
            ->get(['default_setting_value','default_setting_enabled']);
        
        $fax_allowed_extension_default = array();
        foreach ($fax_allowed_extensions as $ext){
            $fax_allowed_extension_default[$ext['default_setting_value']] = $ext['default_setting_enabled'];
        }

        if($fax_allowed_extension_default == false){
            $tmp = array('.pdf', '.tiff', '.tif');
            $fax_allowed_extension_default = arr_to_map($tmp);
        }

        $attachments = $payload['Attachments'];

        $emailed_files = Array();
        foreach ($attachments as $attachment){
            $fax_file_extension = pathinfo($attachment['Name'], PATHINFO_EXTENSION);

            //block unknown files
            if ($fax_file_extension == '') {continue; }

            //block unauthorized files
            if (!$fax_allowed_extension_default['.' . $fax_file_extension]) { continue; }
            $uuid_filename = Str::uuid()->toString();

            // Save attachment to the storage
            $path = Storage::disk('fax')->put($domain->domain_name . '/'. $fax_extension . '/temp/' . $uuid_filename . '-' . $attachment['Name'], base64_decode($attachment['Content']));
            
            //load files array with attachments
            $emailed_files['error'][] = 0;
            $emailed_files['size'][] = $attachment['ContentLength'];
            $emailed_files['tmp_name'][] = $uuid_filename."-".$attachment['Name'];
            $emailed_files['name'][] = $uuid_filename."-".$attachment['Name'];

        }

        //Set up remaining variable before handing the script off to FusionPBX
        $cwd = getcwd();
        set_include_path($cwd . '/public');
        $included = true;
        $settings= DefaultSettings::where('default_setting_category','switch')
            ->get([
                'default_setting_subcategory',
                'default_setting_name',
                'default_setting_value',
            ]);

        session_start();
        session_unset();

        $_SESSION['domain_name'] = $domain->domain_name;

        foreach ($settings as $setting) {
            if ($setting->default_setting_subcategory == 'storage') {
                $fax_dir = $setting->default_setting_value . '/fax/' . $domain->domain_name;
                $_SESSION['switch']['storage']['dir'] = $setting->default_setting_value;
            }            
        }

        $settings= DefaultSettings::where('default_setting_category','fax')
            ->get([
                'default_setting_subcategory',
                'default_setting_name',
                'default_setting_value',
            ]);

        foreach ($settings as $setting) {
            if ($setting->default_setting_subcategory == 'page_size') {
                $fax_page_size = $setting->default_setting_value;
            }            
            if ($setting->default_setting_subcategory == 'resolution') {
                $fax_resolution = $setting->default_setting_value;
            }  
            if ($setting->default_setting_subcategory == 'cover_header') {
                $fax_header = $setting->default_setting_value;
            }  
            if ($setting->default_setting_subcategory == 'cover_footer') {
                $fax_footer = $setting->default_setting_value;
            }  
            if ($setting->default_setting_subcategory == 'cover_font') {
                $fax_cover_font = $setting->default_setting_value;
            }
            if ($setting->default_setting_subcategory == 'cover_logo') {
                $_SESSION['fax']['cover_logo'] = array();
                $_SESSION['fax']['cover_logo']['text'] = null;
            }
            if ($setting->default_setting_subcategory == 'smtp_from') {
                $_SESSION['fax']['smtp_from']['text'] = $setting->default_setting_value;
            }
           
        }

        $fax_subject = $payload['Subject'];
        define('PROJECT_PATH', '');

        $_SERVER['PROJECT_ROOT']=$cwd . "/public";

        // Log::alert($_SERVER);

        //add multi-lingual support
        $language = new text;
        chdir($cwd . "/public/app/fax");
        $text = $language->get();
        chdir($cwd);

        // Log::alert($text);

        //Set fax destination
        $fax_numbers[] = $payload['fax_destination'];

        // This variable is used in Fusion when the fax is uploaded through the web page
        // It's not defined otherwise and throws errors. To avoid it we are setting it empty srting
        $fax_recipient = '';

        // This variable is used in Fusion to download fax preview from the web page
        // Set it to empty srting since we don't need it for this scrip
        $_REQUEST['submit'] = '';

        $sender_email = $payload['FromFull']['Email'];
        $common_variables = '';

        $_SESSION["domain_uuid"] = $domain->domain_uuid;
        $_SESSION["domain_name"] = $domain->domain_name;

        include("resources/functions.php");
        require("app/fax/fax_send.php");
    }
}
