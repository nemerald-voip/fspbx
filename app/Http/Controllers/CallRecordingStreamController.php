<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\CDR;

class CallRecordingStreamController extends Controller
{
    /**
     * STREAM (playback) a local recording by CDR UUID.
     * Signed route; validates access; supports HTTP Range.
     */
    public function __invoke(Request $request, string $uuid): StreamedResponse
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired link.');
        }

        // Permission to PLAY (not download)
        if (!userCheckPermission('call_recording_play')) {
            abort(403, 'You do not have permission to play recordings.');
        }

        $cdr = CDR::query()
            ->select('xml_cdr_uuid', 'record_path', 'record_name', 'domain_uuid')
            ->where('xml_cdr_uuid', $uuid)
            ->firstOrFail();

        $absDir  = rtrim($cdr->record_path ?: '', '/');
        $file    = $cdr->record_name ?: '';
        $absPath = $absDir && $file ? ($absDir . '/' . $file) : null;

        if (!$absPath || !is_file($absPath)) {
            abort(404, 'Recording not found.');
        }

        // Basic MIME guess by extension (fast and sufficient here)
        $ext  = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            default => 'audio/octet-stream',
        };

        $size = filesize($absPath);

        // full stream fallback
        $streamAll = function () use ($absPath) {
            $fp = fopen($absPath, 'rb');
            fpassthru($fp);
            fclose($fp);
        };

        $headers = [
            'Content-Type'   => $mime,
            'Accept-Ranges'  => 'bytes',
            'Cache-Control'  => 'private, max-age=0, no-cache',
        ];

        // Range support
        if ($range = $request->headers->get('Range')) {
            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $m)) {
                $start  = (int) $m[1];
                $end    = $m[2] === '' ? ($size - 1) : (int) $m[2];
                $length = max(0, $end - $start + 1);

                return new StreamedResponse(function () use ($absPath, $start, $length) {
                    $fp = fopen($absPath, 'rb');
                    fseek($fp, $start);
                    $bytesLeft = $length;
                    $chunk = 1024 * 64;
                    while ($bytesLeft > 0 && !feof($fp)) {
                        $read = ($bytesLeft > $chunk) ? $chunk : $bytesLeft;
                        echo fread($fp, $read);
                        $bytesLeft -= $read;
                        @ob_flush();
                        flush();
                    }
                    fclose($fp);
                }, 206, $headers + [
                    'Content-Length' => $length,
                    'Content-Range'  => "bytes $start-$end/$size",
                ]);
            }
        }

        return new StreamedResponse($streamAll, 200, $headers + [
            'Content-Length' => $size,
        ]);
    }

    /**
     * DOWNLOAD a recording by CDR UUID.
     * - Local: serves as attachment with original filename
     * - S3: redirects to an attachment-presigned URL (no CORS needed)
     */
    public function download(Request $request, string $uuid)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired link.');
        }

        if (!userCheckPermission('call_recording_download')) {
            abort(403, 'You do not have permission to download recordings.');
        }

        $cdr = CDR::query()
            ->select('xml_cdr_uuid', 'record_path', 'record_name', 'domain_uuid')
            ->with('archive_recording:xml_cdr_uuid,object_key')
            ->where('xml_cdr_uuid', $uuid)
            ->firstOrFail();

        // If stored in S3, redirect to an attachment-presigned URL
        if ($cdr->record_path === 'S3') {
            $svc  = app(\App\Services\CallRecordingUrlService::class);
            $urls = $svc->urlsForCdr($uuid, 600);
            // urlsForCdr() should return 'download_url' with Content-Disposition: attachment
            if (!empty($urls['download_url'])) {
                return redirect()->away($urls['download_url']);
            }
            abort(404, 'Download URL not available.');
        }

        // Local file: stream as attachment with original filename
        $absDir  = rtrim($cdr->record_path ?: '', '/');
        $file    = $cdr->record_name ?: '';
        $absPath = $absDir && $file ? ($absDir . '/' . $file) : null;

        if (!$absPath || !is_file($absPath)) {
            abort(404, 'Recording not found.');
        }

        $downloadName = basename($file) ?: 'recording';
        return response()->download($absPath, $downloadName, [
            'Cache-Control' => 'private, max-age=0, no-cache',
        ]);
    }
}
