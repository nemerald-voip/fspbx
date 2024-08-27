<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAIService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
    }

    public function textToSpeech($model='tts-1-hd', $input, $voice = 'alloy', $response_format = 'wav', $speed = '1.0')
    {
        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API key is not configured. Please set the API key in your environment file.');
        }
        
        $url = 'https://api.openai.com/v1/audio/speech';

        logger('here');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'model' => $model,
            'input' => $input,
            'voice' => $voice,
            'response_format' => $response_format,
            'speed' => $speed,
        ]);

        return $this->handleResponse($response);

    }

    private function handleResponse($response)
    {
        if ($response->successful()) {
            return $response->json();
        }

        if ($response->clientError()) {
            // Log client errors
            logger('OpenAI API Client Error: ' . $response->body());
            throw new \Exception('There was an error with your request: ' . $response->json('error.message'));
        }

        if ($response->serverError()) {
            // Log server errors
            logger('OpenAI API Server Error: ' . $response->body());
            throw new \Exception('The OpenAI API is currently unavailable. Please try again later.');
        }

        // Handle unexpected errors
        throw new \Exception('An unexpected error occurred. Please try again.');
    }
}
