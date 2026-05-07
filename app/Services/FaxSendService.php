<?php

namespace App\Services;

use fpdi;
use TCPDF_FONTS;
use App\Jobs\SendFaxJob;
use App\Models\Faxes;
use App\Models\OutboundFax;
use Illuminate\Support\Str;
use App\Models\DefaultSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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

            // Normalize the destination to E.164 using the tenant's country
            // setting. Webhook profiles already do this before calling send(),
            // but the dashboard / future API callers may pass a raw number.
            // formatPhoneNumber is idempotent on already-E.164 input.
            if (is_object($faxServer)) {
                $payload['fax_destination'] = formatPhoneNumber(
                    $payload['fax_destination'] ?? '',
                    get_domain_setting('country', $faxServer->domain_uuid) ?? 'US',
                    \libphonenumber\PhoneNumberFormat::E164
                );
            }

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

            // Persist the outbound fax record. SendFaxJob will pick it up and
            // perform the actual originate; the row's status drives retries
            // and reaping.
            $outboundFax = $instance->createOutboundFax(
                $payload,
                $faxServer,
                $conversionResult['tif_file'],
                $conversionResult['total_pages'] ?? null
            );

            fax_webhook_debug('FaxSendService row created', [
                'outbound_fax_uuid' => $outboundFax->outbound_fax_uuid,
                'prefix'            => $outboundFax->prefix,
                'destination'       => $outboundFax->destination,
                'total_pages'       => $outboundFax->total_pages,
            ]);

            // Dispatch the worker. Wrap in try/catch so a Redis outage doesn't
            // fail the request — CheckStuckFaxesJob will pick the row up later.
            try {
                SendFaxJob::dispatch($outboundFax->outbound_fax_uuid);
            } catch (\Throwable $e) {
                logger('FaxSendService@send: SendFaxJob dispatch failed (row remains in waiting): ' . $e->getMessage());
            }

            if ($notify_in_transit) {
                // Send notification to user that fax is in transit
                SendFaxInTransitNotification::dispatch($payload)->onQueue('emails');
            }

            return $outboundFax->outbound_fax_uuid;
        } catch (\Throwable $e) {
            logger('FaxSendService@send error : ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return $e->getMessage();
        }
    }

    /**
     * Create the outbound_faxes row that drives the rest of the lifecycle.
     */
    public function createOutboundFax(
        array $payload,
        Faxes $faxServer,
        string $fax_file,
        ?int $total_pages
    ): OutboundFax {
        return OutboundFax::create([
            'domain_uuid'      => $faxServer->domain_uuid,
            'fax_uuid'         => $faxServer->fax_uuid,
            'status'           => 'waiting',
            'source'           => $faxServer->fax_caller_id_number,
            'source_name'      => $faxServer->fax_caller_id_name,
            'destination'      => $payload['fax_destination'] ?? '',
            'email'            => $payload['from'] ?? '',
            'subject'          => $payload['subject'] ?? '',
            'body'             => $payload['body'] ?? '',
            'file_path'        => $fax_file,
            'total_pages'      => $total_pages,
            'prefix'           => $faxServer->fax_prefix,
            'accountcode'      => $faxServer->accountcode,
            'retry_count'      => 0,
            'retry_limit'      => 5,
            'retry_at'         => null,
        ]);
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
            'success'           => $success,
            'tif_file'          => $final_tif_path,
            'fax_instance_uuid' => $fax_instance_uuid,
            'sent_dir'          => $sent_dir,
            'total_pages'       => $success ? $this->countTiffPages($final_tif_path) : null,
        ];
    }

    /**
     * Count pages in a multi-page TIFF using tiffinfo (already a dependency
     * of tiffcp/tiff2pdf). Returns null on any failure — the reaper has a
     * generous fallback when total_pages is unknown.
     */
    public function countTiffPages(?string $tif_path): ?int
    {
        if (!$tif_path || !file_exists($tif_path)) {
            return null;
        }

        try {
            $process = new Process(['tiffinfo', $tif_path], null, ['HOME' => '/tmp']);
            $process->mustRun();
            $output = $process->getOutput();
            $count = preg_match_all('/^TIFF Directory at offset/m', $output);
            return $count > 0 ? $count : null;
        } catch (\Throwable $e) {
            logger('FaxSendService@countTiffPages failed: ' . $e->getMessage());
            return null;
        }
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

    /**
     * Allowed file extensions for fax attachments (cached for one day).
     */
    public static function getAllowedExtensions(): array
    {
        return Cache::remember('fax_allowed_extensions', now()->addDay(), function () {
            $extensions = DefaultSettings::where('default_setting_category', 'fax')
                ->where('default_setting_subcategory', 'allowed_extension')
                ->where('default_setting_enabled', 'true')
                ->pluck('default_setting_value')
                ->toArray();

            return !empty($extensions) ? $extensions : ['.pdf', '.tiff', '.tif'];
        });
    }

    /**
     * Store an UploadedFile (multipart upload) on the fax disk under /temp.
     * Returns attachment metadata in the shape FaxSendService::send() expects,
     * or null if the extension is not allowed or the save fails.
     *
     * Files land in the disk root /temp; getFaxServerInstance() can later pick
     * a different fax extension based on a phone number in the subject, and
     * convertAttachmentsToTif() renames the files into the resolved tenant's
     * {domain}/{ext}/temp directory.
     */
    public static function storeUploadedAttachment(UploadedFile $file): ?array
    {
        $originalName = $file->getClientOriginalName();
        $extension = '.' . strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::getAllowedExtensions())) {
            return null;
        }

        try {
            $stored = Storage::disk('fax')->putFileAs(
                '/temp',
                $file,
                Str::uuid()->toString() . $extension
            );

            return [
                'original_name' => $originalName,
                'stored_path'   => $stored,
                'mime_type'     => $file->getMimeType(),
                'extension'     => $extension,
            ];
        } catch (\Throwable $e) {
            Log::alert('Failed to save fax attachment: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Store a base64-encoded attachment (e.g. Postmark JSON payload) on the
     * fax disk under /temp. Same routing rationale as storeUploadedAttachment().
     */
    public static function storeBase64Attachment(string $name, string $base64Content, ?string $mimeType = null): ?array
    {
        $extension = '.' . strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($extension, self::getAllowedExtensions())) {
            return null;
        }

        $storedPath = 'temp/' . Str::uuid()->toString() . $extension;

        try {
            Storage::disk('fax')->put($storedPath, base64_decode($base64Content));

            return [
                'original_name' => $name,
                'stored_path'   => $storedPath,
                'mime_type'     => $mimeType ?? 'application/octet-stream',
                'extension'     => $extension,
            ];
        } catch (\Throwable $e) {
            Log::alert('Failed to save fax attachment: ' . $e->getMessage());
            return null;
        }
    }
}
