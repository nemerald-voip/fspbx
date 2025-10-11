<?php

namespace App\Services;

use App\Exceptions\TranscriptionConfigurationException;
use Exception;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleService
{
    protected array $config;
    protected string $apiEndpoint;

    public function __construct()
    {
        $this->config = config('services.google');
    }

    /**
     * Fetches a valid OAuth 2.0 access token using the configured service account.
     *
     * @return string The access token.
     * @throws \Exception
     */
    private function getAccessToken(): string
    {
        return Cache::remember('google_speech_access_token', 55, function () {
            // Check if the essential credentials are set
            if (empty($this->config['credentials']['client_email']) || empty($this->config['credentials']['private_key'])) {
                throw new Exception('Google service account credentials (client_email, private_key) are not configured correctly.');
            }

            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/cloud-platform', // OAuth Scope
                $this->config['credentials']
            );

            $handler = HttpHandlerFactory::build();
            $token = $credentials->fetchAuthToken($handler);

            if (!isset($token['access_token'])) {
                throw new \Exception('Failed to fetch Google an access token.');
            }

            return $token['access_token'];
        });
    }

    /**
     * Transcribes audio using Google Cloud Speech-to-Text V2 API.
     *
     * @param string $filePath
     * @param string $language
     * @return array|null
     * @throws TranscriptionConfigurationException|RequestException|\Exception
     */
    public function transcribe($filePath, $language = 'en-US'): ?array
    {
        if (!file_exists($filePath)) {
            Log::error('Google v2 transcription failed: File does not exist.', ['path' => $filePath]);
            return null;
        }

        // Fetch a valid access token
        $accessToken = $this->getAccessToken();

        $this->apiEndpoint = "https://{$this->config['region']}-speech.googleapis.com/v2/projects/{$this->config['project_id']}/locations/{$this->config['region']}/recognizers/_:recognize";
        
        $audioContent = base64_encode(file_get_contents($filePath));

        $response = Http::withToken($accessToken)
            ->withHeaders(['Content-Type' => 'application/json; charset=utf-8'])
            ->post($this->apiEndpoint, [
                'config' => [
                    'model' => $this->config['model'],
                    'languageCodes' => [$language],
                    'features' => [
                        'enableAutomaticPunctuation' => true,
                    ],
                    'autoDecodingConfig' => new \stdClass(),
                ],
                'content' => $audioContent,
            ]);

        // This will throw a RequestException on 4xx/5xx errors, which will be caught by VoicemailTranscriptionService
        $response->throw();

        $results = $response->json('results');
        $transcription = '';

        if (is_array($results)) {
            foreach ($results as $result) {
                if (!empty($result['alternatives'][0]['transcript'])) {
                    $transcription .= $result['alternatives'][0]['transcript'];
                }
            }
        }

        return ['message' => $transcription];
    }
}