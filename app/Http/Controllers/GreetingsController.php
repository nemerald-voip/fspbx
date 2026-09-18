<?php

namespace App\Http\Controllers;

use App\Http\Requests\TextToSpeechRequest;
use App\Models\Recordings;
use App\Models\SwitchVariable;
use App\Services\OpenAIService;
use App\Services\Tts\TtsProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GreetingsController extends Controller
{
    public function greetings()
    {
        try {
            $domain_uuid = request('domain_uuid') ?? session('domain_uuid');

            $greetingsArray = Recordings::where('domain_uuid', $domain_uuid)
                ->orderBy('recording_name')
                ->get()
                ->map(function ($greeting) {
                    return [
                        'value' => (string) $greeting->recording_filename,
                        'label' => $greeting->recording_name,
                        'description' => html_entity_decode(
                            $greeting->recording_description ?? '',
                            ENT_QUOTES | ENT_HTML5,
                            'UTF-8'
                        ),
                    ];
                })->values()->toArray();

            array_unshift(
                $greetingsArray,
                ['value' => '0', 'label' => 'None']
            );

            return response()->json($greetingsArray);
        } catch (\Exception $e) {
            logger('Error: ' . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());

            return response()->json([
                ['value' => '0', 'label' => 'None']
            ]);
        }
    }

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
            return response()->download(
                Storage::disk('recordings')->path($filePath),
                basename($filePath)
            );
        }

        // Serve the file inline
        return response()->file(Storage::disk('recordings')->path($filePath));
    }


    public function textToSpeech(TtsProviderRegistry $registry, TextToSpeechRequest $request)
    {
        $input = $request->input('input');
        $providerKey = $request->input('provider');
        $responseFormat = $request->input('response_format');

        try {
            $provider = $registry->make($providerKey);
            $response = $provider->textToSpeech($input, [
                'model'           => $request->input('model'),
                'voice'           => $request->input('voice'),
                'response_format' => $responseFormat,
                'speed'           => $request->input('speed'),
            ]);

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
            $applyUrl = route('greeting.file.apply');

            return response()->json([
                'success' => true,
                'file_url' => $fileUrl,
                'apply_url' => $applyUrl,
                'file_name' => $fileName,
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

    /**
     * Return available voices, speeds, and formats for a given TTS provider.
     */
    public function getTtsVoices(TtsProviderRegistry $registry, Request $request)
    {
        $providerKey = $request->input('provider', 'openai');

        try {
            $provider = $registry->make($providerKey);

            return response()->json([
                'voices'         => $provider->getVoices(),
                'speeds'         => $provider->getSpeeds(),
                'formats'        => $provider->getOutputFormats(),
                'default_voice'  => $provider->getDefaultVoice(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'voices' => [],
                'speeds' => [],
                'formats' => [],
                'default_voice' => null,
                'error' => $e->getMessage(),
            ], 422);
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

    public function applyAIGreetingFile()
    {
        try {
            $domain_name = session('domain_name');

            // Retrieve the file name and custom greeting message from the request payload
            $file_name = request('file_name');
            $customMessage = request('input');

            // Step 1: Make sure the file exists
            $filePath = $domain_name . "/" . $file_name;

            if (!Storage::disk('recordings')->exists($filePath)) {
                throw new \Exception("File not found"); // File not found
            }

            // Step 2: Generate new greeting_id and filename
            $newFileName = str_replace("temp", "ai_generated", $file_name);
            $datePart = str_replace("temp", "", $file_name); // Remove temp
            $datePart = str_replace(".wav", "", $datePart); // Remove .wav
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

            $sanitizedDescription =  preg_replace('/\s+/', ' ', htmlspecialchars(strip_tags(trim($customMessage)), ENT_QUOTES, 'UTF-8'));

            // Step 5: Save greeting info to the database
            Recordings::create([
                'recording_filename' => $newFileName,
                'recording_name' => "AI Greeting " . $datePart,
                'recording_description' => $sanitizedDescription,
            ]);

            return response()->json([
                'success' => true,
                'greeting_id' => $newFileName,
                'greeting_name' => "AI Greeting " . $datePart,
                'description' => $sanitizedDescription,
                'messages' => ['success' => ['Your AI-generated greeting has been saved.']]
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

    /**
     * Serve the greeting file as a URL.
     *
     * @param string $greetingId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getIvrMessageUrl()
    {
        try {
            // Step 1: Get the greeting_id from the request
            $file_name = request('file_name');

            // Check if the greeting exists
            if (!$file_name) {
                throw new \Exception('File not found');
            }

            // Generate the file URL using the defined route
            $fileUrl = route('ivr.message.file.serve', [
                'file_name' => urlencode($file_name),
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


    public function serveIvrMessageFile($file_name)
    {
        // Primary path in the 'recordings' disk
        $primaryPath = session('domain_name') . '/' . $file_name;

        // Check if the file exists in the primary path
        if (!Storage::disk('recordings')->exists($primaryPath)) {
            // Check the alternative path

            // Retrieve default variables for the alternative path
            $variables = SwitchVariable::whereIn('var_name', ['default_language', 'default_dialect', 'default_voice'])
                ->pluck('var_value', 'var_name');

            $defaultLanguage = $variables['default_language'] ?? 'en'; // Fallback to 'en' if not found
            $defaultDialect = $variables['default_dialect'] ?? 'us';  // Fallback to 'us' if not found
            $defaultVoice = $variables['default_voice'] ?? 'callie';  // Fallback to 'callie' if not found

            // Alternative path in the 'sounds' disk
            $alternativePath = $defaultLanguage . "/" . $defaultDialect . "/" . $defaultVoice  . "/" . str_replace('/', "/16000/", $file_name);

            if (!Storage::disk('sounds')->exists($alternativePath)) {
                // File not found in either location
                return response()->json([
                    'success' => false,
                    'errors' => ['server' => 'File not found']
                ], 404);
            }

            // File found in the alternative path
            $filePath = Storage::disk('sounds')->path($alternativePath);
        } else {
            // File found in the primary path
            $filePath = Storage::disk('recordings')->path($primaryPath);
        }

        // Check if the 'download' parameter is present and set to true
        $download = request()->query('download', false);

        if ($download) {
            // Serve the file as a download
            return response()->download($filePath);
        }

        // Serve the file inline
        return response()->file($filePath);
    }


    public function deleteGreetingFile()
    {
        try {
            $file_name = request('file_name');

            if (blank($file_name)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['file_name' => ['Greeting file name is required.']]
                ], 422);
            }

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
                'messages' => ['success' => ['Greeting has been removed.']]
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
                'messages' => ['success' => ['Greeting has been updated.']]
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
            'file' => 'required|mimes:wav,mp3,m4a|max:51200', // Allow only WAV and MP3 files, max size 50MB
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
                    'messages' => ['success' => ['Your greeting has been uploaded and activated.']]
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
