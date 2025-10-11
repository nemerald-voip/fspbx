<?php

namespace App\Services;

use App\Exceptions\TranscriptionConfigurationException;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class WatsonService
{
    protected $apiKey;
    protected $url;

    public function __construct()
    {
        $this->apiKey = config('services.watson.api_key');
        $this->url = config('services.watson.url');
    }
    
    /**
     * @throws TranscriptionConfigurationException|RequestException
     */
    public function transcribe($filePath, $language = 'en-US')
    {
        if (empty($this->apiKey) || empty($this->url)) {
            throw new Exception('Watson API key or URL is not configured.');
        }

        // CORRECTED: Switched from _BroadbandModel to _Telephony for voicemail audio
        $url = "{$this->url}/v1/recognize?model={$language}_Telephony";

        $response = Http::withHeaders([
            // 'audio/wav' is a generic container. If you know the specific codec 
            // (like mu-law), you could use 'audio/mulaw;rate=8000' for even more precision.
            'Content-Type' => 'audio/wav',
        ])
        ->withBasicAuth('apikey', $this->apiKey)
        ->withBody(file_get_contents($filePath), 'audio/wav') // Sending as a raw body is often more reliable than attaching
        ->post($url);

        $response->throw();

        $results = $response->json('results');
        $transcription = '';
        if (!empty($results) && !empty($results[0]['alternatives'])) {
            $transcription = $results[0]['alternatives'][0]['transcript'];
        }
        return ['message' => $transcription];
    }
}