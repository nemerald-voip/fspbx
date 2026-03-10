<?php

namespace App\Services;

use fpdi;
use TCPDF_FONTS;
use Carbon\Carbon;
use App\Models\Faxes;
use App\Models\FaxQueues;
use Illuminate\Support\Str;
use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use App\Jobs\SendFaxFailedNotification;
use Illuminate\Support\Facades\Storage;
use App\Jobs\SendFaxNotificationToSlack;
use libphonenumber\NumberParseException;
use App\Jobs\SendFaxInTransitNotification;
use Illuminate\Process\Exceptions\ProcessFailedException;

class FaxSendService
{
    /**
     * Send a fax using the given payload.
     *
     * @param array $payload
     *        Must include:
     *          - to: recipient email or fax number
     *          - from: sender email
     *          - subject: fax subject (optional)
     *          - body: message body (optional)
     *          - attachments: array of UploadedFile objects
     * @return array|string
     */
    public static function send(array $payload)
    {
        $instance = new self();
        $payload['fax_instance_uuid'] = (string) Str::uuid();

        $payload['slack_message'] = "*EmailToFax Notification*\n";
        $payload['slack_message'] .= "*From:* {$payload['from']}\n";
        $payload['slack_message'] .= "*To:* {$payload['fax_destination']}\n";

        $attachments = $payload['attachments'] ?? [];
        if (empty($attachments)) {
            $payload['slack_message'] .= ":warning: *Failed to process fax:* No attachments found. _Fax aborted._";
            logger($payload['slack_message']);

            $payload['email_message'] = "We regret to inform you that your recent attempt to send a fax using our Email-to-Fax service was unsuccessful due to missing attachments. Our system requires at least one attachment in order to convert and deliver your fax to the intended recipient. Unfortunately, it appears that no attachments were included in your submission.";

            SendFaxFailedNotification::dispatch(
                $payload,
            )->onQueue('emails');

            return "No attachments";
        }

        try {

            $faxServer = $instance->getFaxServerInstance($payload);

            // Create all fax directories 
            $instance->CreateFaxDirectories($faxServer);

            // Remove HTML tags
            $payload['body'] = strip_tags($payload['body']);
            // Decode HTML entities
            $payload['body'] = html_entity_decode($payload['body']);
            // Normalize line endings (replace \r\n and \r with \n)
            $payload['body'] = str_replace(["\r\n", "\r"], "\n", $payload['body']);
            // Collapse multiple blank lines to a single blank line
            $payload['body'] = preg_replace("/\n{2,}/", "\n", $payload['body']);
            // Trim leading and trailing whitespace
            $payload['body'] = trim($payload['body']);


            $settings = DefaultSettings::where('default_setting_category', 'fax')
                ->where('default_setting_enabled', 'true')
                ->get([
                    'default_setting_subcategory',
                    'default_setting_name',
                    'default_setting_value',
                ]);

            // Set defaults
            $payload['fax_page_size'] = 'letter';
            $payload['page_width'] = 8.5;
            $payload['page_height'] = 11;
            $payload['fax_resolution'] = 'normal';
            $payload['gs_r'] = '204x98';
            $payload['gs_g'] = ((int)(8.5 * 204)) . 'x' . ((int)(11 * 98));
            $payload['dialplan_variables'] = [];

            $notify_in_transit = false;

            foreach ($settings as $setting) {
                switch ($setting->default_setting_subcategory) {
                    case 'page_size':
                        $payload['fax_page_size'] = $setting->default_setting_value;
                        switch ($payload['fax_page_size']) {
                            case 'a4':
                                $payload['page_width'] = 8.3;
                                $payload['page_height'] = 11.7;
                                break;
                            case 'legal':
                                $payload['page_width'] = 8.5;
                                $payload['page_height'] = 14;
                                break;
                            case 'letter':
                            default:
                                $payload['page_width'] = 8.5;
                                $payload['page_height'] = 11;
                                $payload['fax_page_size'] = 'letter';
                                break;
                        }
                        break;

                    case 'resolution':
                        $payload['fax_resolution'] = $setting->default_setting_value;
                        switch ($payload['fax_resolution']) {
                            case 'fine':
                                $payload['gs_r'] = '204x196';
                                $payload['gs_g'] = ((int)($payload['page_width'] * 204)) . 'x' . ((int)($payload['page_height'] * 196));
                                break;
                            case 'superfine':
                                $payload['gs_r'] = '204x392';
                                $payload['gs_g'] = ((int)($payload['page_width'] * 204)) . 'x' . ((int)($payload['page_height'] * 392));
                                break;
                            case 'normal':
                            default:
                                $payload['gs_r'] = '204x98';
                                $payload['gs_g'] = ((int)($payload['page_width'] * 204)) . 'x' . ((int)($payload['page_height'] * 98));
                                $payload['fax_resolution'] = 'normal';
                                break;
                        }
                        break;

                    case 'cover_header':
                        $payload['fax_header'] = $setting->default_setting_value;
                        break;

                    case 'cover_footer':
                        $payload['fax_footer'] = $setting->default_setting_value;
                        break;

                    case 'cover_font':
                        $payload['fax_cover_font'] = $setting->default_setting_value;
                        break;

                    case 'smtp_from':
                        $payload['smtp_from'] = $setting->default_setting_value;
                        break;

                    case 'variable':
                        $payload['dialplan_variables'][] = $setting->default_setting_value;
                        break;

                    case 'notify_in_transit':
                        if ($setting->default_setting_value == 'true') {
                            $notify_in_transit = true;
                        }
                }
            }

            // If subject contains word "body" we will add a cover page to this fax
            if (preg_match("/body/i", $payload['subject'])) {
                $payload['fax_cover'] = true;
            } else {
                $payload['fax_cover'] = false;
            }

            // Convert attachments to TIFF(s)
            $conversionResult = $instance->convertAttachmentsToTif($attachments, $payload, $faxServer);

            if (!$conversionResult['success']) {
                return "Failed to convert";
            }

            $payload['fax_queue_uuid'] = Str::uuid()->toString();

            $dial_string = $instance->getDialstring(
                $payload,
                $faxServer->toArray(),
                $conversionResult['tif_file'],
                $payload['dialplan_variables'] ?? []
            );

            // Add to queue
            $result = $instance->addToFaxQueue(
                $payload,
                $faxServer->toArray(),
                $conversionResult['tif_file'],
                $dial_string
            );

            if ($notify_in_transit) {
                // Send notification to user that fax is in transit
                SendFaxInTransitNotification::dispatch($payload)->onQueue('emails');
            }

            return $result;
        } catch (\Throwable $e) {
            logger('FaxSendService@send error : ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return $e->getMessage();
        }
    }

    public function addToFaxQueue(
        array $payload,
        array $faxServer,
        string $fax_file,
        string $dial_string
    ) {
        $fax_queue = new FaxQueues();
        $fax_queue->fax_queue_uuid     = $payload['fax_queue_uuid'];
        $fax_queue->domain_uuid        = $faxServer['domain_uuid'] ?? null;
        $fax_queue->fax_uuid           = $faxServer['fax_uuid'] ?? null;
        $fax_queue->fax_date           = Carbon::now(get_local_time_zone($faxServer['domain_uuid'] ?? null))->utc()->toIso8601String();
        $fax_queue->hostname           = gethostname();
        $fax_queue->fax_caller_id_name = $faxServer['fax_caller_id_name'] ?? '';
        $fax_queue->fax_caller_id_number = $faxServer['fax_caller_id_number'] ?? '';
        $fax_queue->fax_number         = $payload['fax_destination'] ?? '';
        $fax_queue->fax_prefix         = $faxServer['fax_prefix'] ?? '';
        $fax_queue->fax_email_address  = $payload['from'] ?? '';
        $fax_queue->fax_file           = $fax_file;
        $fax_queue->fax_status         = 'waiting';
        $fax_queue->fax_retry_count    = 0;
        $fax_queue->fax_accountcode    = $faxServer['accountcode'] ?? '';
        $fax_queue->fax_command        = 'originate ' . $dial_string;

        $fax_queue->save();

        return $fax_queue->fax_queue_uuid;
    }


    public function getDialstring(array $payload, array $faxServer, string $fax_file, array $dialplan_variables = [])
    {
        $fax_queue_uuid        = $payload['fax_queue_uuid'];
        $accountcode           = $faxServer['accountcode'] ?? '';
        $sip_h_accountcode     = $faxServer['accountcode'] ?? '';
        $domain_uuid           = $faxServer['domain_uuid'] ?? '';
        $domain_name           = $faxServer['domain']['domain_name'] ?? '';
        $fax_caller_id_name    = $faxServer['fax_caller_id_name'] ?? '';
        $fax_caller_id_number  = $faxServer['fax_caller_id_number'] ?? '';
        $fax_ident             = $fax_caller_id_number;
        $fax_header            = $fax_caller_id_name;
        // $fax_extension         = $faxServer['fax_extension'] ?? '';
        $fax_prefix            = $faxServer['fax_prefix'] ?? '';
        $fax_email             = $faxServer['fax_email'] ?? '';
        $fax_from              = $payload['from'] ?? '';
        $fax_destination       = $payload['fax_destination'] ?? '';
        $fax_toll_allow        = $faxServer['fax_toll_allow'] ?? '';

        $channel_variables = [];
        if (!empty($fax_toll_allow)) {
            $channel_variables["toll_allow"] = $fax_toll_allow;
        }

        $route_array = outbound_route_to_bridge($domain_uuid, $fax_prefix . $fax_destination, $channel_variables);
        if (empty($route_array)) {
            Log::error("No outbound route found for fax to $fax_destination (domain: $domain_uuid)");
            return null;
        }
        $fax_uri = $route_array[0];

        // Helper for escaping (simple, expand as needed)
        $e = fn($val) => str_replace(["'", '{', '}'], ["\\'", '', ''], $val);

        // Build dial string as an array
        $vars = [
            "fax_queue_uuid={$e($fax_queue_uuid)}",
            "accountcode='{$e($accountcode)}'",
            "sip_h_accountcode='{$e($accountcode)}'",
            "domain_uuid={$e($domain_uuid)}",
            "domain_name={$e($domain_name)}",
            "origination_caller_id_name='{$e($fax_caller_id_name)}'",
            "origination_caller_id_number='{$e($fax_caller_id_number)}'",
            "fax_ident='{$e($fax_ident)}'",
            "fax_header='{$e($fax_header)}'",
            "fax_file='{$e($fax_file)}'",
        ];

        // Add dialplan variables
        foreach ($dialplan_variables as $variable) {
            $vars[] = $variable;
        }

        // Rest of the vars
        $vars = array_merge($vars, [
            "mailto_address='{$e($fax_email)}'",
            "mailfrom_address='{$e($fax_from)}'",
            "fax_uri={$e($fax_uri)}",
            "fax_retry_attempts=1",
            "fax_retry_limit=20",
            "fax_retry_sleep=180",
            // "fax_verbose=true",
            "fax_use_ecm=off",
            "api_hangup_hook='lua app/fax/resources/scripts/hangup_tx.lua'",
        ]);

        // Join with commas
        $dial_string = '{' . implode(',', $vars) . '}' . $fax_uri . " &txfax('{$e($fax_file)}')";

        return $dial_string;
    }


    public function getFaxServerInstance($payload)
    {
        // Test if there is a phone number in the subject line
        $subject = $payload['subject'];
        $re = '/1?\d{10}/m';
        if (preg_match($re, $subject, $matches)) {
            // if string of digits that may represent a phone number is found then check if it's a valid phone number
            $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
           try {
                $phoneNumberObject = $phoneNumberUtil->parse($matches[0], 'US');
                if ($phoneNumberUtil->isValidNumber($phoneNumberObject)) {
                    $fax_caller_id_number = $phoneNumberUtil
                        ->format($phoneNumberObject, \libphonenumber\PhoneNumberFormat::E164);
                    // Try to find fax extension by requested caller ID
                    if (isset($fax_caller_id_number)) {
                        $faxServer = Faxes::where('fax_caller_id_number', $fax_caller_id_number)
                            ->select(
                                'fax_uuid',
                                'domain_uuid',
                                'dialplan_uuid',
                                'fax_extension',
                                'fax_prefix',
                                'fax_name',
                                'fax_email',
                                'fax_caller_id_name',
                                'fax_caller_id_number',
                                'accountcode',
                                'fax_toll_allow',
                            )
                            ->with(['domain' => function ($query) {
                                $query->select('domain_uuid', 'domain_name');
                            }])
                            ->first();
                    }
                } else {
                    logger("Invalid Caller ID in subject: {$matches[0]} (continuing with fallback)");
                }
            } catch (NumberParseException $e) {
                logger("Invalid Caller ID in subject: {$matches[0]} (parse error, continuing with fallback)");
            } 
        }

        // If the subject line didn't have a valid Fax number we are going to use the first match by email
        if (!isset($faxServer)) {
            if (isset($payload['fax_uuid'])) {
                $faxServer = Faxes::where('fax_uuid', $payload['fax_uuid'])
                    ->select(
                        'fax_uuid',
                        'domain_uuid',
                        'dialplan_uuid',
                        'fax_extension',
                        'fax_prefix',
                        'fax_name',
                        'fax_email',
                        'fax_caller_id_name',
                        'fax_caller_id_number',
                        'accountcode',
                        'fax_toll_allow',
                    )
                    ->with(['domain' => function ($query) {
                        $query->select('domain_uuid', 'domain_name');
                    }])
                    ->first();
            }
        }

        // if we stil don't have a fax extension then email doesn't have any associated faxes
        if (!isset($faxServer)) {
            $payload['slack_message'] .= ":warning: *Failed to process fax:* No fax servers found associated for " . $payload['from'] . "_Fax aborted._";
            logger($payload['slack_message']);
            SendFaxNotificationToSlack::dispatch($payload['slack_message'])->onQueue('faxes');
            return "abort(404). No fax servers found";
        }

        return $faxServer;
    }


    /**
     * Converts attachments to TIF format (multi-page if needed).
     * @param array $attachments
     * @param array $payload
     * @return array ['success' => bool, 'tif_files' => array]
     */
    public function convertAttachmentsToTif(array $attachments, array $payload, $faxServer)
    {
        // Use faxServer object for domain and extension paths
        $fax_extension = $faxServer->fax_extension;
        $domain_name = $faxServer->domain->domain_name;
        $fax_cover = $payload['fax_cover'] ?? false;

        // Build working directories
        $temp_dir = Storage::disk('fax')->path("{$domain_name}/{$fax_extension}/temp");
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0777, true);
        }
        $sent_dir = Storage::disk('fax')->path("{$domain_name}/{$fax_extension}/sent");
        if (!is_dir($sent_dir)) {
            mkdir($sent_dir, 0777, true);
        }

        // Pull settings from payload
        $gs_r = $payload['gs_r'] ?? '204x98';
        $gs_g = $payload['gs_g'] ?? '1734x2156';
        $fax_page_size = $payload['fax_page_size'] ?? 'letter';
        $page_width = $payload['page_width'] ?? 8.5;
        $page_height = $payload['page_height'] ?? 11;

        $tif_files = [];
        $fax_instance_uuid = $payload['fax_instance_uuid'] ?? (string) Str::uuid();

        foreach ($attachments as $attachment) {
            $uuid_filename = Str::uuid()->toString();
            $sourcePath = Storage::disk('fax')->path($attachment['stored_path']);
            $extension = strtolower($attachment['extension']);
            $tempPath = "{$temp_dir}/{$uuid_filename}{$extension}";

            if (!rename($sourcePath, $tempPath)) {
                logger("Failed to move attachment to temp: $sourcePath");
                continue;
            }

            // 1. Office formats/doc/rtf/xls: LibreOffice to PDF
            if (!in_array($extension, ['.pdf', '.tif', '.tiff'])) {
                $process = new Process([
                    "libreoffice",
                    "--headless",
                    "--convert-to",
                    "pdf",
                    "--outdir",
                    $temp_dir,
                    $tempPath
                ], null, ['HOME' => '/tmp']);
                try {
                    $process->mustRun();
                    unlink($tempPath);
                    $pdfPath = "{$temp_dir}/{$uuid_filename}.pdf";
                } catch (ProcessFailedException $e) {
                    Log::alert("LibreOffice PDF conversion failed: " . $e->getMessage());
                    continue;
                }
            } elseif ($extension === '.pdf') {
                // 2. PDF: use directly
                $pdfPath = $tempPath;
            } elseif ($extension === '.tif' || $extension === '.tiff') {
                // 3. TIF: first convert to PDF
                $pdfPath = "{$temp_dir}/{$uuid_filename}.pdf";
                $process = new Process([
                    "tiff2pdf",
                    "-o",
                    $pdfPath,
                    $tempPath
                ]);
                try {
                    $process->mustRun();
                    unlink($tempPath); // Optionally remove the original .tif after conversion
                } catch (ProcessFailedException $e) {
                    logger("tiff2pdf TIFF to PDF failed: " . $e->getMessage());
                    continue;
                }
            } else {
                // Should never reach here, but just in case
                logger("Unsupported file type: $extension");
                continue;
            }

            if (!isset($pdfPath) || !file_exists($pdfPath)) {
                logger("PDF file missing for Ghostscript: " . ($pdfPath ?? '(not set)'));
                continue;
            }

            $tifPath = "{$temp_dir}/{$uuid_filename}.tif";
            $process = new Process([
                "gs",
                "-q",
                "-r{$gs_r}",
                "-g{$gs_g}",
                "-dBATCH",
                "-dPDFFitPage",
                "-dNOSAFER",
                "-dNOPAUSE",
                "-sOutputFile={$tifPath}",
                "-sDEVICE=tiffg4",
                "-Ilib",
                "stocht.ps",
                "-c",
                "{ .75 gt { 1 } { 0 } ifelse} settransfer",
                "--",
                $pdfPath,
                "-c",
                "quit"
            ], null, ['HOME' => '/tmp']);
            try {
                $process->mustRun();
                unlink($pdfPath);
                $tif_files[] = $tifPath;
            } catch (ProcessFailedException $e) {
                logger("Ghostscript TIFF conversion failed: " . $e->getMessage());
                continue;
            }
        }



        // Generate cover page if needed (stub: integrate your generator here)
        if ($fax_cover) {
            $coverPdf = "{$temp_dir}/{$fax_instance_uuid}_cover.pdf";
            $coverTif = "{$temp_dir}/{$fax_instance_uuid}_cover.tif";
            $this->generateFaxCoverPage($payload, $coverPdf);

            if (file_exists($coverPdf)) {
                $process = new Process([
                    "gs",
                    "-q",
                    "-r{$gs_r}",
                    "-g{$gs_g}",
                    "-dBATCH",
                    "-dPDFFitPage",
                    "-dNOSAFER",
                    "-dNOPAUSE",
                    "-sOutputFile={$coverTif}",
                    "-sDEVICE=tiffg4",
                    "-Ilib",
                    "stocht.ps",
                    "-c",
                    "{ .75 gt { 1 } { 0 } ifelse} settransfer",
                    "--",
                    $coverPdf,
                    "-c",
                    "quit"
                ], null, ['HOME' => '/tmp']);
                try {
                    $process->mustRun();
                    unlink($coverPdf);
                    array_unshift($tif_files, $coverTif); // Add cover to beginning
                } catch (ProcessFailedException $e) {
                    Log::alert("Ghostscript cover page conversion failed: " . $e->getMessage());
                }
            }
        }

        // Combine all TIFFs into one multi-page file if >1
        $final_tif_path = null;

        if (count($tif_files) > 1) {
            $multiTif = "{$sent_dir}/{$fax_instance_uuid}.tif";
            $cmd = array_merge(["tiffcp", "-c", "none"], $tif_files, [$multiTif]);
            $process = new Process($cmd, null, ['HOME' => '/tmp']);
            try {
                $process->mustRun();
                foreach ($tif_files as $file) {
                    @unlink($file);
                }
                $final_tif_path = $multiTif;
            } catch (ProcessFailedException $e) {
                logger("TIFFCP combine failed: " . $e->getMessage());
            }
        } elseif (count($tif_files) == 1) {
            // Move the single tif to sent_dir for consistency
            $finalPath = "{$sent_dir}/{$fax_instance_uuid}.tif";
            rename($tif_files[0], $finalPath);
            $final_tif_path = $finalPath;
        }

        $success = !empty($final_tif_path) && file_exists($final_tif_path);

        // Generate PDF from final TIF (to keep parity with the old script)
        if (!empty($final_tif_path) && file_exists($final_tif_path)) {
            $final_pdf_path = preg_replace('/\.tif$/', '.pdf', $final_tif_path);

            $process = new Process([
                "tiff2pdf",
                "-u",
                "i",
                "-p",
                $fax_page_size,
                "-w",
                (string) $page_width,
                "-l",
                (string) $page_height,
                "-f",
                "-o",
                $final_pdf_path,
                $final_tif_path,
            ], null, ['HOME' => '/tmp']);

            try {
                $process->mustRun();
            } catch (\Symfony\Component\Process\Exception\ProcessFailedException $e) {
                logger("tiff2pdf failed: " . $e->getMessage());
                // don't fail the fax; just log it
            }
        }

        if (!$success) {
            $message = "Couldn't process any of the attached files. Supported types: .pdf, .tif, .tiff";
            Log::alert($message);
            SendFaxFailedNotification::dispatch([
                'email_message' => $message,
                'slack_message' => $message,
            ])->onQueue('emails');
        }

        return [
            'success' => $success,
            'tif_file' => $final_tif_path,
            'fax_instance_uuid' => $fax_instance_uuid,
            'sent_dir' => $sent_dir,
        ];
    }




    public function createFaxDirectories($faxServer)
    {
        try {
            // Get values from the $faxServer object
            $domainName = $faxServer->domain->domain_name;
            $faxExtension = $faxServer->fax_extension;

            // List of directories to ensure exist (relative to the fax disk root)
            $directories = [
                "{$domainName}/{$faxExtension}/inbox",
                "{$domainName}/{$faxExtension}/sent",
                "{$domainName}/{$faxExtension}/temp",
            ];

            foreach ($directories as $dir) {
                if (!Storage::disk('fax')->exists($dir)) {
                    Storage::disk('fax')->makeDirectory($dir, 0770, true);
                }
            }
        } catch (\Throwable $e) {
            $message = "Failed to create fax directories: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}";
            logger($message);
            SendFaxNotificationToSlack::dispatch($message)->onQueue('faxes');
            // Optionally, you can throw or handle the error as needed
        }
    }

    public function generateFaxCoverPage(array $payload, string $pdfPath)
    {
        $page_width = $payload['page_width'] ?? 8.5;
        $page_height = $payload['page_height'] ?? 11;
        $fax_header = $payload['fax_header'] ?? '';
        $fax_footer = $payload['fax_footer'] ?? '';
        $fax_cover_font = $payload['fax_cover_font'] ?? '';
        $fax_message = $payload['body'] ?? '';
        $fax_destination = $payload['fax_destination'] ?? '';
        $fax_caller_id_number = $payload['from'] ?? '';

        $pdf_font = '';

        // Initialize PDF
        $pdf = new FPDI('P', 'in');
        $pdf->SetAutoPageBreak(false);
        if (method_exists($pdf, 'setPrintHeader')) $pdf->setPrintHeader(false);
        if (method_exists($pdf, 'setPrintFooter')) $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0, true);

        if (!empty($fax_cover_font)) {
            if (substr($fax_cover_font, -4) == '.ttf') {
                $pdf_font = TCPDF_FONTS::addTTFfont($fax_cover_font);
            } else {
                $pdf_font = $fax_cover_font;
            }
        }
        if (!$pdf_font) {
            $pdf_font = 'times';
        }

        // Add blank page
        $pdf->AddPage('P', array($page_width, $page_height));
        $x = 0;
        $y = 0;

        // Header
        $pdf->SetXY($x + 0.5, $y + 0.4);
        if ($fax_header !== '') {
            $pdf->SetLeftMargin(0.5);
            $pdf->SetFont($pdf_font, "", 10);
            $pdf->Write(0.3, $fax_header);
        }

        // Cover title
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont($pdf_font, "B", 55);
        $pdf->SetXY($x + 4.55, $y + 0.25);
        $pdf->Cell($x + 3.50, $y + 0.4, "Fax", 0, 0, 'R', false, null, 0, false, 'T', 'T');
        $pdf->SetFont($pdf_font, "", 12);
        if (method_exists($pdf, 'SetFontSpacing')) $pdf->SetFontSpacing(0.0425);
        $pdf->SetXY($x + 4.55, $y + 1.0);
        $pdf->Cell($x + 3.50, $y + 0.4, "Cover Page", 0, 0, 'R', false, null, 0, false, 'T', 'T');
        if (method_exists($pdf, 'SetFontSpacing')) $pdf->SetFontSpacing(0);

        // Field labels
        $pdf->SetFont($pdf_font, "B", 12);
        if ($fax_destination !== '') {
            $pdf->Text($x + 0.5, $y + 2.0, "To:");
        }
        if ($fax_caller_id_number !== '') {
            $pdf->Text($x + 0.5, $y + 2.3, "From:");
        }

        // Field values
        $pdf->SetFont($pdf_font, "", 12);
        $pdf->SetXY($x + 2.0, $y + 1.95);
        if ($fax_destination !== '') {
            $pdf->Write(0.3, $fax_destination);
        }
        $pdf->SetXY($x + 2.0, $y + 2.25);
        if ($fax_caller_id_number !== '') {
            $pdf->Write(0.3, $fax_caller_id_number);
        }

        // Message
        $pdf->SetAutoPageBreak(true, 0.6);
        $pdf->SetTopMargin(0.6);
        $pdf->SetFont($pdf_font, "", 12);
        $pdf->SetXY($x + 0.75, $y + 3.65);
        $pdf->MultiCell(7, 5.40, $fax_message . " ", 0, 'L', false);

        $pages = $pdf->getNumPages();
        if ($pages > 1) {
            $yn = $pdf->GetY();
            $pdf->setPage(1, 0);
            $pdf->Rect($x + 0.5, $y + 3.4, 7.5, $page_height - 3.9, 'D');
            for ($n = 2; $n < $pages; $n++) {
                $pdf->setPage($n, 0);
                $pdf->Rect($x + 0.5, $y + 0.5, 7.5, $page_height - 1, 'D');
            }
            $pdf->setPage($pages, 0);
            $pdf->Rect($x + 0.5, 0.5, 7.5, $yn, 'D');
            $y = $yn;
            unset($yn);
        } else {
            $pdf->Rect($x + 0.5, $y + 3.4, 7.5, 6.25, 'D');
            $y = $pdf->GetY();
        }

        // Footer
        if ($fax_footer !== '') {
            $pdf->SetAutoPageBreak(true, 0.6);
            $pdf->SetTopMargin(0.6);
            $pdf->SetFont("helvetica", "", 8);
            $pdf->SetXY($x + 0.5, $y + 0.6);
            $pdf->MultiCell(7.5, 0.75, $fax_footer, 0, 'C', false);
        }
        $pdf->SetAutoPageBreak(false);
        $pdf->SetTopMargin(0);

        // Save cover PDF
        $pdf->Output($pdfPath, "F");
    }
}
