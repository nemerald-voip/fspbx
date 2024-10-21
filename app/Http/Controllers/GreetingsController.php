<?php

namespace App\Http\Controllers;

use App\Models\Recordings;
use Illuminate\Support\Str;
use App\Events\GreetingDeleted;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\TextToSpeechRequest;

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
            ], 404);  // 404 Not Found status for file not found
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

    public function textToSpeech(OpenAIService $openAIService, TextToSpeechRequest $request)
    {
        $input = $request->input('input');
        $model = $request->input('model');
        $voice = $request->input('voice');
        $responseFormat = $request->input('response_format');
        $speed = $request->input('speed');

        try {
            $response = $openAIService->textToSpeech($model, $input, $voice, $responseFormat, $speed);

            $domainName = session('domain_name');

            // Delete all temp files
            $this->deleteTempFiles($domainName);

            $fileName = 'temp_' . now()->format('Ymd_His') . '.' . $responseFormat; // Generates filename like temp_20240826_153045.wav
            $filePath = $domainName . '/' . $fileName;

            // Save file to the voicemail disk with domain folder
            Storage::disk('recordings')->put($filePath, $response);

            // Generate the file URL using the defined route
            $fileUrl = route('greeting.file.serve', [
                'file_name' => $fileName,
            ]);

            // Generate the file URL using the defined route
            $applyUrl = route('greeting.file.apply', [
                'file_name' => $fileName,
            ]);

            return response()->json([
                'success' => true,
                'file_url' => $fileUrl,
                'apply_url' => $applyUrl,
            ]);
        } catch (\Exception $e) {
            // Log the error message
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            // report($e);

            // Handle any other exception that may occur
            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);  // 500 Internal Server Error for any other errors
        }
    }

    public function deleteTempFiles($folderPath)
    {
        $files = Storage::disk('recordings')->files($folderPath);
        foreach ($files as $file) {
            if (Str::startsWith(basename($file), 'temp')) {
                Storage::disk('recordings')->delete($file);
            }
        }
    }

    public function applyGreetingFile($file_name)
    {
        try {

            $domain_name = session('domain_name');

            // Step 1: Make sure the file exists
            $filePath = $domain_name . "/" . $file_name;

            if (!Storage::disk('recordings')->exists($filePath)) {
                throw new \Exception("File not found"); // File not found
            }

            // Step 2: Generate new greeting_id and filename
            $newFileName = str_replace("temp", "ai_generated", $file_name);

            // Step 3: Construct the new file path
            $newFilePath = $domain_name . "/" . $newFileName;

            // Step 4: Store the file with the new name 
            if (!Storage::disk('recordings')->move($filePath, $newFilePath)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => ['Failed to save the file']]
                ], 500);
            }

            // Step 5: Save greeting info to the database
            Recordings::create([
                'recording_filename' => $newFileName,
                'recording_name' => "AI Greeting " . date('Ymd_His'),
            ]);


            return response()->json([
                'success' => true,
                'greeting_id' => $newFileName,
                'greeting_name' => "AI Greeting " . date('Ymd_His'),
                'message' => ['success' => 'Your AI-generated greeting has been saved.']
            ], 200);
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


    public function deleteGreetingFile()
    {
        try {
            $file_name = request('file_name');

            // Fetch the greeting to delete
            $greeting = Recordings::where('domain_uuid', session('domain_uuid'))
                ->where('recording_filename', $file_name)
                ->first();

            if (!$greeting) {
                throw new \Exception('Greeting not found');
            }

            $filePath = session('domain_name') . '/' . $file_name;

            // Delete the greeting file from storage
            if (Storage::disk('recordings')->exists($filePath)) {
                $fileDeleted = Storage::disk('recordings')->delete($filePath);

                if (!$fileDeleted) {
                    throw new \Exception('Failed to delete greeting file from storage.');
                }
            } else {
                throw new \Exception('Greeting file does not exist in storage.');
            }

            // Delete the greeting record from the database
            if ($greeting->delete()) {
                // Fire the event to notify other models only after successful deletion
                // event(new GreetingDeleted($greeting));
            } else {
                throw new \Exception('Failed to delete greeting from the database.');
            }

            // Return a successful JSON response
            return response()->json([
                'success' => true,
                'message' => ['success' => 'Greeting has been removed.']
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json(['success' => false, 'errors' => ['server' => [$e->getMessage()]]], 500);
        }
    }
}
