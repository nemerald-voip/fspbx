<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AzureService
{
    protected $apiKey;
    protected $region;

    public function __construct()
    {
        $this->apiKey = config('services.azure.api_key');
        $this->region = config('services.azure.region');

        if (empty($this->apiKey) || empty($this->region)) {
            throw new \Exception('Azure API key or region is not configured.');
        }
    }

    public function transcribe($filePath, $language = 'en-US')
    {
        $url = "https://{$this->region}.stt.speech.microsoft.com/speech/recognition/conversation/cognitiveservices/v1?language={$language}";

        $response = Http::withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->apiKey,
            'Content-Type' => 'audio/wav; codecs=audio/pcm; samplerate=16000', // Adjust content type as needed
        ])
        ->withBody(file_get_contents($filePath), 'audio/wav')
        ->post($url);

        if ($response->successful()) {
            return [
                'message' => $response->json('DisplayText')
            ];
        } else {
            logger()->error('Azure transcription failed: ' . $response->body());
            return null;
        }
    }
}