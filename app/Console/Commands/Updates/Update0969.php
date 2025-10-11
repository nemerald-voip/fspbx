<?php

namespace App\Console\Commands\Updates;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;

class Update0969
{
    protected $file1 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/switch/resources/scripts/app/voicemail/resources/functions/send_email.lua';
    protected $file2 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/devices/device_edit.php';
    protected $file3 = 'https://raw.githubusercontent.com/nemerald-voip/fusionpbx/master/app/devices/device_profile_edit.php';
    protected $filePath1;
    protected $filePath2;
    protected $filePath3;

    public function __construct()
    {
        $this->filePath1 = base_path('public/app/switch/resources/scripts/app/voicemail/resources/functions/send_email.lua');
        $this->filePath2 = base_path('public/app/devices/device_edit.php');
        $this->filePath3 = base_path('public/app/devices/device_profile_edit.php');
    }

    /**
     * Apply update steps.
     *
     * @return bool
     */
    public function apply()
    {
        if (!$this->downloadAndReplaceFile($this->file1, $this->filePath1, 'send_email.lua')) {
            return false;
        }
        if (!$this->downloadAndReplaceFile($this->file2, $this->filePath2, 'device_edit.php')) {
            return false;
        }
        if (!$this->downloadAndReplaceFile($this->file3, $this->filePath3, 'device_profile_edit.php')) {
            return false;
        }

        // Update email template in DB
        $this->updateEmailTemplate();

        $result = $this->runMenuUpdate();

        return true;
    }

    /**
     * Run the artisan command to update the FS PBX menu.
     *
     * @return int Exit code of the Artisan call
     */
    protected function runMenuUpdate(): int
    {
        echo "Running menu:update (menu:create-fspbx --update)...\n";
        $exitCode = Artisan::call('menu:create-fspbx', ['--update' => true]);
        $output   = Artisan::output();
        echo $output;

        if ($exitCode !== 0) {
            echo "Error: Menu update command failed with exit code $exitCode.\n";
        } else {
            echo "Menu update completed successfully.\n";
        }

        return $exitCode;
    }

    /**
     * Download a file from a URL and replace the local file.
     *
     * @return bool
     */
    protected function downloadAndReplaceFile($url, $filePath, $fileName)
    {
        try {
            $response = Http::get($url);

            if ($response->successful()) {
                File::put($filePath, $response->body());
                echo "$fileName file downloaded and replaced successfully.\n";
                return true;
            } else {
                echo "Error downloading $fileName. Status Code: " . $response->status() . "\n";
                return false;
            }
        } catch (\Exception $e) {
            echo "Error downloading $fileName: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Update the voicemail transcription email template in the database.
     *
     * @return void
     */
    protected function updateEmailTemplate()
    {
        $appName = config('app.name');

        $newTemplateBody = <<<EOT
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="x-apple-disable-message-reformatting" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="color-scheme" content="light dark" />
    <meta name="supported-color-schemes" content="light dark" />
    <title></title>
    <style type="text/css" rel="stylesheet" media="all">
        @import url("https://fonts.googleapis.com/css?family=Nunito+Sans:400,700&display=swap");
        body { width: 100% !important; height: 100%; margin: 0; -webkit-text-size-adjust: none; }
        a { color: #3869D4; }
        a img { border: none; }
        td { word-break: break-word; }
        .preheader { display: none !important; visibility: hidden; mso-hide: all; font-size: 1px; line-height: 1px; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; }
        body, td, th { font-family: "Nunito Sans", Helvetica, Arial, sans-serif; }
        h1 { margin-top: 0; color: #333333; font-size: 22px; font-weight: bold; text-align: left; }
        h2 { margin-top: 0; color: #333333; font-size: 16px; font-weight: bold; text-align: left; }
        h3 { margin-top: 0; color: #333333; font-size: 14px; font-weight: bold; text-align: left; }
        td, th { font-size: 16px; }
        p, ul, ol, blockquote { margin: .4em 0 1.1875em; font-size: 16px; line-height: 1.625; }
        p.sub { font-size: 13px; }
        .align-right { text-align: right; }
        .align-left { text-align: left; }
        .align-center { text-align: center; }
        .button { background-color: #3869D4; border-top: 10px solid #3869D4; border-right: 18px solid #3869D4; border-bottom: 10px solid #3869D4; border-left: 18px solid #3869D4; display: inline-block; color: #FFF; text-decoration: none; border-radius: 3px; box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16); -webkit-text-size-adjust: none; box-sizing: border-box; }
        .button--green { background-color: #22BC66; border-top: 10px solid #22BC66; border-right: 18px solid #22BC66; border-bottom: 10px solid #22BC66; border-left: 18px solid #22BC66; }
        .button--red { background-color: #FF6136; border-top: 10px solid #FF6136; border-right: 18px solid #FF6136; border-bottom: 10px solid #FF6136; border-left: 18px solid #FF6136; }
        @media only screen and (max-width: 500px) {
            .button { width: 100% !important; text-align: center !important; }
        }
        .attributes { margin: 0 0 21px; }
        .attributes_content { background-color: #F4F4F7; padding: 16px; }
        .attributes_item { padding: 0; }
        .related { width: 100%; margin: 0; padding: 25px 0 0 0; }
        .related_item { padding: 10px 0; color: #CBCCCF; font-size: 15px; line-height: 18px; }
        .related_item-title { display: block; margin: .5em 0 0; }
        .related_item-thumb { display: block; padding-bottom: 10px; }
        .related_heading { border-top: 1px solid #CBCCCF; text-align: center; padding: 25px 0 10px; }
        .discount { width: 100%; margin: 0; padding: 24px; background-color: #F4F4F7; border: 2px dashed #CBCCCF; }
        .discount_heading { text-align: center; }
        .discount_body { text-align: center; font-size: 15px; }
        .social { width: auto; }
        .social td { padding: 0; width: auto; }
        .social_icon { height: 20px; margin: 0 8px 10px 8px; padding: 0; }
        .purchase { width: 100%; margin: 0; padding: 35px 0; }
        .purchase_content { width: 100%; margin: 0; padding: 25px 0 0 0; }
        .purchase_item { padding: 10px 0; color: #51545E; font-size: 15px; line-height: 18px; }
        .purchase_heading { padding-bottom: 8px; border-bottom: 1px solid #EAEAEC; }
        .purchase_heading p { margin: 0; color: #85878E; font-size: 12px; }
        .purchase_footer { padding-top: 15px; border-top: 1px solid #EAEAEC; }
        .purchase_total { margin: 0; text-align: right; font-weight: bold; color: #333333; }
        .purchase_total--label { padding: 0 15px 0 0; }
        body { background-color: #F2F4F6; color: #51545E; }
        p { color: #51545E; }
        .email-wrapper { width: 100%; margin: 0; padding: 0; background-color: #F2F4F6; }
        .email-content { width: 100%; margin: 0; padding: 0; }
        .email-masthead { padding: 25px 0; text-align: center; }
        .email-masthead_logo { width: 94px; }
        .email-masthead_name { font-size: 16px; font-weight: bold; color: #A8AAAF; text-decoration: none; text-shadow: 0 1px 0 white; }
        .email-body { width: 100%; margin: 0; padding: 0; }
        .email-body_inner { width: 570px; margin: 0 auto; padding: 0; background-color: #FFFFFF; }
        .email-footer { width: 570px; margin: 0 auto; padding: 0; text-align: center; }
        .email-footer p { color: #A8AAAF; }
        .body-action { width: 100%; margin: 30px auto; padding: 0; text-align: center; }
        .body-sub { margin-top: 25px; padding-top: 25px; border-top: 1px solid #EAEAEC; }
        .content-cell { padding: 45px; }
        @media only screen and (max-width: 600px) {
            .email-body_inner, .email-footer { width: 100% !important; }
        }
        @media (prefers-color-scheme: dark) {
            body, .email-body, .email-body_inner, .email-content, .email-wrapper, .email-masthead, .email-footer { background-color: #333333 !important; color: #FFF !important; }
            p, ul, ol, blockquote, h1, h2, h3, span, .purchase_item { color: #FFF !important; }
            .attributes_content, .discount { background-color: #222 !important; }
            .email-masthead_name { text-shadow: none !important; }
        }
        :root { color-scheme: light dark; supported-color-schemes: light dark; }
    </style>
    <!--[if mso]>
    <style type="text/css">
      .f-fallback  {
        font-family: Arial, sans-serif;
      }
    </style>
    <![endif]-->
</head>
<body>
    <table class="email-wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
            <td align="center">
                <table class="email-content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td class="email-masthead">
                            <a href="" class="f-fallback email-masthead_name">
                                {$appName}
                            </a>
                        </td>
                    </tr>
                    <!-- Email Body -->
                    <tr>
                        <td class="email-body" width="570" cellpadding="0" cellspacing="0">
                            <table class="email-body_inner" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                                <!-- Body content -->
                                <tr>
                                    <td class="content-cell">
                                        <div class="f-fallback">
                                            <p>You have a new voice message:</p>
                                            <table class="attributes" width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td class="attributes_content">
                                                        <table width="100%" cellpadding="0" cellspacing="0">
                                                            <tr>
                                                                <td class="attributes_item"><strong>From:</strong> \${caller_id_name} \${caller_id_number} </td>
                                                            </tr>
                                                            <tr>
                                                                <td class="attributes_item"><strong>To mailbox:</strong> \${dialed_user}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="attributes_item"><strong>Received:</strong> \${message_date}</td>
                                                            </tr>
                                                            <tr>
                                                                <td class="attributes_item"><strong>Length:</strong> \${message_duration}</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                            <p><strong>Voicemail Preview:</strong></p>
                                            <table class="attributes" width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td class="attributes_content">
                                                        <table width="100%" cellpadding="0" cellspacing="0">
                                                            <tr>
                                                                <td class="attributes_item">\${message_text}</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            </table>
                                            <p>Listen to this voicemail over your phone or by opening the attached sound file. You can also sign in to your account with your credentials to manage and listen to voicemails.</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table class="email-footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                                <tr>
                                    <td class="content-cell" align="center">
                                        <p class="f-fallback sub align-center">© {$appName}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
EOT;

        $oldTemplateBody = <<<EOT
<html>
<body>
Voicemail from \${caller_id_name} <a href="tel:\${caller_id_number}">\${caller_id_number}</a><br />
<br />
To \${voicemail_name_formatted}<br />
Received \${message_date}<br />
Length \${message_duration}<br />
Message \${message}<br />
<br />
Transcription<br />
\${message_text}
</body>
</html>
EOT;

        // Replace {$appName} placeholders with the actual app name
        $newTemplateBody = str_replace('{$appName}', $appName, $newTemplateBody);

        DB::table('v_email_templates')
            ->where('template_category', 'voicemail')
            ->where('template_subcategory', 'transcription')
            ->where('template_body', $oldTemplateBody)
            ->update([
                'template_body' => $newTemplateBody
            ]);

        echo "Voicemail transcription email template updated successfully.\n";

        $newTemplateBody = <<<EOT
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
        <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            <meta name="x-apple-disable-message-reformatting" />
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
            <meta name="color-scheme" content="light dark" />
            <meta name="supported-color-schemes" content="light dark" />
            <title></title>
            <style type="text/css" rel="stylesheet" media="all">
                @import url("https://fonts.googleapis.com/css?family=Nunito+Sans:400,700&display=swap");
                body { width: 100% !important; height: 100%; margin: 0; -webkit-text-size-adjust: none; }
                a { color: #3869D4; }
                a img { border: none; }
                td { word-break: break-word; }
                .preheader { display: none !important; visibility: hidden; mso-hide: all; font-size: 1px; line-height: 1px; max-height: 0; max-width: 0; opacity: 0; overflow: hidden; }
                body, td, th { font-family: "Nunito Sans", Helvetica, Arial, sans-serif; }
                h1 { margin-top: 0; color: #333333; font-size: 22px; font-weight: bold; text-align: left; }
                h2 { margin-top: 0; color: #333333; font-size: 16px; font-weight: bold; text-align: left; }
                h3 { margin-top: 0; color: #333333; font-size: 14px; font-weight: bold; text-align: left; }
                td, th { font-size: 16px; }
                p, ul, ol, blockquote { margin: .4em 0 1.1875em; font-size: 16px; line-height: 1.625; }
                p.sub { font-size: 13px; }
                .align-right { text-align: right; }
                .align-left { text-align: left; }
                .align-center { text-align: center; }
                .button { background-color: #3869D4; border-top: 10px solid #3869D4; border-right: 18px solid #3869D4; border-bottom: 10px solid #3869D4; border-left: 18px solid #3869D4; display: inline-block; color: #FFF; text-decoration: none; border-radius: 3px; box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16); -webkit-text-size-adjust: none; box-sizing: border-box; }
                .button--green { background-color: #22BC66; border-top: 10px solid #22BC66; border-right: 18px solid #22BC66; border-bottom: 10px solid #22BC66; border-left: 18px solid #22BC66; }
                .button--red { background-color: #FF6136; border-top: 10px solid #FF6136; border-right: 18px solid #FF6136; border-bottom: 10px solid #FF6136; border-left: 18px solid #FF6136; }
                @media only screen and (max-width: 500px) {
                    .button { width: 100% !important; text-align: center !important; }
                }
                .attributes { margin: 0 0 21px; }
                .attributes_content { background-color: #F4F4F7; padding: 16px; }
                .attributes_item { padding: 0; }
                .related { width: 100%; margin: 0; padding: 25px 0 0 0; }
                .related_item { padding: 10px 0; color: #CBCCCF; font-size: 15px; line-height: 18px; }
                .related_item-title { display: block; margin: .5em 0 0; }
                .related_item-thumb { display: block; padding-bottom: 10px; }
                .related_heading { border-top: 1px solid #CBCCCF; text-align: center; padding: 25px 0 10px; }
                .discount { width: 100%; margin: 0; padding: 24px; background-color: #F4F4F7; border: 2px dashed #CBCCCF; }
                .discount_heading { text-align: center; }
                .discount_body { text-align: center; font-size: 15px; }
                .social { width: auto; }
                .social td { padding: 0; width: auto; }
                .social_icon { height: 20px; margin: 0 8px 10px 8px; padding: 0; }
                .purchase { width: 100%; margin: 0; padding: 35px 0; }
                .purchase_content { width: 100%; margin: 0; padding: 25px 0 0 0; }
                .purchase_item { padding: 10px 0; color: #51545E; font-size: 15px; line-height: 18px; }
                .purchase_heading { padding-bottom: 8px; border-bottom: 1px solid #EAEAEC; }
                .purchase_heading p { margin: 0; color: #85878E; font-size: 12px; }
                .purchase_footer { padding-top: 15px; border-top: 1px solid #EAEAEC; }
                .purchase_total { margin: 0; text-align: right; font-weight: bold; color: #333333; }
                .purchase_total--label { padding: 0 15px 0 0; }
                body { background-color: #F2F4F6; color: #51545E; }
                p { color: #51545E; }
                .email-wrapper { width: 100%; margin: 0; padding: 0; background-color: #F2F4F6; }
                .email-content { width: 100%; margin: 0; padding: 0; }
                .email-masthead { padding: 25px 0; text-align: center; }
                .email-masthead_logo { width: 94px; }
                .email-masthead_name { font-size: 16px; font-weight: bold; color: #A8AAAF; text-decoration: none; text-shadow: 0 1px 0 white; }
                .email-body { width: 100%; margin: 0; padding: 0; }
                .email-body_inner { width: 570px; margin: 0 auto; padding: 0; background-color: #FFFFFF; }
                .email-footer { width: 570px; margin: 0 auto; padding: 0; text-align: center; }
                .email-footer p { color: #A8AAAF; }
                .body-action { width: 100%; margin: 30px auto; padding: 0; text-align: center; }
                .body-sub { margin-top: 25px; padding-top: 25px; border-top: 1px solid #EAEAEC; }
                .content-cell { padding: 45px; }
                @media only screen and (max-width: 600px) {
                    .email-body_inner, .email-footer { width: 100% !important; }
                }
                @media (prefers-color-scheme: dark) {
                    body, .email-body, .email-body_inner, .email-content, .email-wrapper, .email-masthead, .email-footer { background-color: #333333 !important; color: #FFF !important; }
                    p, ul, ol, blockquote, h1, h2, h3, span, .purchase_item { color: #FFF !important; }
                    .attributes_content, .discount { background-color: #222 !important; }
                    .email-masthead_name { text-shadow: none !important; }
                }
                :root { color-scheme: light dark; supported-color-schemes: light dark; }
            </style>
            <!--[if mso]>
            <style type="text/css">
              .f-fallback  {
                font-family: Arial, sans-serif;
              }
            </style>
            <![endif]-->
        </head>
        <body>
            <table class="email-wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                    <td align="center">
                        <table class="email-content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
                            <tr>
                                <td class="email-masthead">
                                    <a href="" class="f-fallback email-masthead_name">
                                        {$appName}
                                    </a>
                                </td>
                            </tr>
                            <!-- Email Body -->
                            <tr>
                                <td class="email-body" width="570" cellpadding="0" cellspacing="0">
                                    <table class="email-body_inner" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                                        <!-- Body content -->
                                        <tr>
                                            <td class="content-cell">
                                                <div class="f-fallback">
                                                    <p>You have a new voice message:</p>
                                                    <table class="attributes" width="100%" cellpadding="0" cellspacing="0">
                                                        <tr>
                                                            <td class="attributes_content">
                                                                <table width="100%" cellpadding="0" cellspacing="0">
                                                                    <tr>
                                                                        <td class="attributes_item"><strong>From:</strong> \${caller_id_name} \${caller_id_number} </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="attributes_item"><strong>To mailbox:</strong> \${dialed_user}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="attributes_item"><strong>Received:</strong> \${message_date}</td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td class="attributes_item"><strong>Length:</strong> \${message_duration}</td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    
                                                    <p>Listen to this voicemail over your phone or by opening the attached sound file. You can also sign in to your account with your credentials to manage and listen to voicemails.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <table class="email-footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
                                        <tr>
                                            <td class="content-cell" align="center">
                                                <p class="f-fallback sub align-center">© {$appName}</p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        EOT;

        $oldTemplateBody = <<<EOT
        <html>
        <body>
        Voicemail from \${caller_id_name} <a href="tel:\${caller_id_number}">\${caller_id_number}</a><br />
        <br />
        To \${voicemail_name_formatted}<br />
        Received \${message_date}<br />
        Length \${message_duration}<br />
        Message \${message}<br />
        </body>
        </html>
        EOT;

        // Replace {$appName} placeholders with the actual app name
        $newTemplateBody = str_replace('{$appName}', $appName, $newTemplateBody);

        DB::table('v_email_templates')
            ->where('template_category', 'voicemail')
            ->where('template_subcategory', 'default')
            ->where('template_body', $oldTemplateBody)
            ->update([
                'template_body' => $newTemplateBody
            ]);

        echo "Voicemail email template updated successfully.\n";
    }
}
