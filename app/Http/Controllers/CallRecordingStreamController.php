<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\CDR;

class CallRecordingStreamController extends Controller
{
    /**
     * Stream a local recording by CDR UUID.
     * Route is signed; still validate user can access the domain/recording.
     */
    public function __invoke(Request $request, string $uuid): StreamedResponse
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired link.');
        }

        // Permission check — stop immediately if not allowed
        if (!userCheckPermission('call_recording_play')) {
            abort(403, 'You do not have permission to download recordings.');
        }

        $cdr = CDR::query()
            ->select('xml_cdr_uuid', 'record_path', 'record_name', 'domain_uuid')
            ->where('xml_cdr_uuid', $uuid)
            ->firstOrFail();

        // Expecting record_path like /var/lib/freeswitch/recordings/.../YYYY/Mon/DD
        $absDir  = rtrim($cdr->record_path ?: '', '/');
        $file    = $cdr->record_name ?: '';
        $absPath = $absDir && $file ? ($absDir . '/' . $file) : null;

        if (!$absPath || !is_file($absPath)) {
            abort(404, 'Recording not found.');
        }

        $mime = 'audio/wav'; // or detect via finfo if mixed
        $size = filesize($absPath);

        $stream = function () use ($absPath) {
            $fp = fopen($absPath, 'rb');
            fpassthru($fp);
            fclose($fp);
        };

        // Range support
        $response = new StreamedResponse($stream, 200, [
            'Content-Type'  => $mime,
            'Content-Length' => $size,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=0, no-cache',
        ]);

        // Handle Range header for seeking
        if ($range = $request->headers->get('Range')) {
            // Example: Range: bytes=START-END
            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $m)) {
                $start = (int)$m[1];
                $end   = $m[2] === '' ? ($size - 1) : (int)$m[2];
                $length = $end - $start + 1;

                $response = new StreamedResponse(function () use ($absPath, $start, $length) {
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
                }, 206, [
                    'Content-Type'  => $mime,
                    'Content-Length' => $length,
                    'Content-Range' => "bytes $start-$end/$size",
                    'Accept-Ranges' => 'bytes',
                    'Cache-Control' => 'private, max-age=0, no-cache',
                ]);
            }
        }

        return $response;
    }
}
