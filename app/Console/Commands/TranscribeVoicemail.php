<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Services\VoicemailTranscriptionService;

class TranscribeVoicemail extends Command
{
    // Signature: php artisan voicemail:transcribe /path/to/file.wav --provider=openai
    protected $signature = 'voicemail:transcribe 
                            {file : Path to the audio file}
                            {--provider= : Transcription provider (openai, google, etc.)}
                            {--language= : Language code, if needed (optional)}
                            {--domain_uuid= : UUID of the domain (optional)}';

    protected $description = 'Transcribe a voicemail audio file using the specified provider';

    public function handle()
    {
        $filePath = $this->argument('file');
        $provider = $this->option('provider');
        $language = $this->option('language');
        $domain_uuid = $this->option('domain_uuid');

        if (!file_exists($filePath)) {
            $this->error("File does not exist: $filePath");
            return 1;
        }

        // Call your transcription service (this is a stub—implement as needed)
        // $this->info("Transcribing using provider: $provider");

        $result = app(VoicemailTranscriptionService::class)->transcribe([
            'file_path' => $filePath,
            'provider' => $provider,
            'language' => $language,
            'domain_uuid' => $domain_uuid,
        ]);

        if (!$result || empty($result['message'])) {
            $this->error("Transcription failed.");
            return 1;
        }

        // Output the transcription
        // $this->info("Transcription completed:");
        $this->line($result['message']);

        // Optionally: Write result to file/database, etc.

        return 0;
    }
}
