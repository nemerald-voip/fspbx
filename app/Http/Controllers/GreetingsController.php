<?php

namespace App\Http\Controllers;

use App\Models\Recordings;
use Illuminate\Support\Str;
use App\Services\OpenAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
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
            $datePart = str_replace("temp", "", $file_name); // Remove temp
            $datePart = str_replace(".wav", "", $file_name); // Remove .wav
            $datePart = ltrim($datePart, "_"); // Remove leading underscore

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
                'recording_name' => "AI Greeting" . $datePart,
            ]);


            return response()->json([
                'success' => true,
                'greeting_id' => $newFileName,
                'greeting_name' => "AI Greeting" . $datePart,
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

            // If the greeting is found, proceed to delete from the database
            if ($greeting) {
                if (!$greeting->delete()) {
                    throw new \Exception('Failed to delete greeting from the database.');
                }
            }

            $filePath = session('domain_name') . '/' . $file_name;

            // Check if the file exists in storage and delete it if present
            if (Storage::disk('recordings')->exists($filePath)) {
                if (!Storage::disk('recordings')->delete($filePath)) {
                    throw new \Exception('Failed to delete greeting file from storage.');
                }
            } else {
                logger('Greeting file does not exist in storage: ' . $filePath);
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

    public function updateGreetingFile()
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

            $greeting->recording_name = request('new_name');
            $greeting->save();

            // Return a successful JSON response
            return response()->json([
                'success' => true,
                'message' => ['success' => 'Greeting has been updated.']
            ], 200);
        } catch (\Exception $e) {
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json(['success' => false, 'errors' => ['server' => [$e->getMessage()]]], 500);
        }
    }

    public function uploadGreeting(Request $request)
    {
        // Validate the file input
        $request->validate([
            'file' => 'required|mimes:wav,mp3|max:51200', // Allow only WAV and MP3 files, max size 50MB
        ]);

        $file = $request->file('file');
        $domainName = session('domain_name');

        try {
            $datePart = now()->format('Ymd_His');

            // Step 2: Generate a unique filename based on the ID and current time
            $originalFileName = 'uploaded_greeting_' . $datePart . '.' . $file->getClientOriginalExtension();
            $convertedFileName = 'uploaded_greeting_' . $datePart . '.wav'; // Convert to WAV format for consistency

            // Step 3: Save the original file to the recordings disk
            Storage::disk('recordings')->putFileAs($domainName, $file, $originalFileName);

            // Step 4: Define file paths for conversion
            $originalFilePath = Storage::disk('recordings')->path($domainName . '/' . $originalFileName);
            $convertedFilePath = Storage::disk('recordings')->path($domainName . '/temp_' . $convertedFileName);

            // Step 5: Convert the file to the required format using ffmpeg
            $process = Process::run([
                'ffmpeg',
                '-i',
                $originalFilePath,
                '-ac',
                '1', // Mono audio
                '-ar',
                '16000', // Audio rate 16 kHz
                '-ab',
                '256k', // Audio bitrate 256 kbps
                $convertedFilePath
            ]);

            if ($process->successful()) {
                // Step 6: Replace the original file with the converted one
                Storage::disk('recordings')->delete($domainName . '/' . $originalFileName);
                Storage::disk('recordings')->move($domainName . '/' . 'temp_' . $convertedFileName, $domainName . '/' . $convertedFileName);

                // Step 7: Save the greeting information to the database
                Recordings::create([
                    'domain_uuid' => session('domain_uuid'),
                    'recording_filename' => $convertedFileName,
                    'recording_name' => "Uploaded File " . $datePart,
                ]);

                // Return a success response
                return response()->json([
                    'success' => true,
                    'greeting_id' => $convertedFileName,
                    'greeting_name' => "Uploaded File " . $datePart,
                    'message' => ['success' => 'Your greeting has been uploaded and activated.']
                ], 200);
            } else {
                // If conversion fails, retain the original file and notify the user
                logger('File conversion failed: ' . $process->errorOutput());

                return response()->json([
                    'success' => false,
                    'message' => ['warning' => 'File uploaded, but conversion failed. Original file retained.']
                ], 200); // Indicate partial success
            }
        } catch (\Exception $e) {
            // Log and handle the exception
            logger($e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                'success' => false,
                'errors' => ['server' => [$e->getMessage()]]
            ], 500);
        }
    }
}
