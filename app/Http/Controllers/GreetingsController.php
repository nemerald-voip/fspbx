<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class GreetingsController extends Controller
{
    /**
     * Serve the greeting file as a URL.
     *
     * @param string $greetingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getGreetingUrl()
    {

        try {
            // Step 1: Get the greeting_id from the request
            $file_name = request('file_name');

            // Check if the greeting exists
            if (!$file_name) {
                throw new \Exception('File not found');
            }

            // Generate the file URL using the defined route
            $fileUrl = route('greeting.file.serve', [
                'file_name' => $file_name,
            ]);

            logger($fileUrl);

            return response()->json([
                'success' => true,
                'file_url' => $fileUrl,
            ]);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }


    public function serveGreetingFile($file_name)
    {
        $filePath = session('domain_name') . '/' . $file_name;

        if (!Storage::disk('recordings')->exists($filePath)) {
            // File not found
            return response()->json([
                'success' => false,
                'errors' => ['server' => 'File not found']
            ], 500);  // 500 Internal Server Error for any other errors
        }

        // Check if the 'download' parameter is present and set to true
        $download = request()->query('download', false);

        if ($download) {
            // Serve the file as a download
            return response()->download(Storage::disk('recordings')->path($filePath));
        }

        // Serve the file inline
        return response()->file(Storage::disk('recordings')->path($filePath));
    }

}
