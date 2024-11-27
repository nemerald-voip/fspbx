<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

class GitHubApiService
{
    // Set the base URI to GitHub's API endpoint
    public static $base_uri = 'https://api.github.com/';

    /**
     * Make an API request to GitHub.
     *
     * @param string $method The HTTP method (GET, POST, etc.)
     * @param string $path The API endpoint path (e.g., "repos/OWNER/REPO/releases/latest")
     * @param array $extra_data Additional request options
     * @return \Psr\Http\Message\ResponseInterface|Exception
     */
    protected function apiRequest($method, $path, $extra_data = [])
    {
        $client = new Client(['verify' => false, 'base_uri' => static::$base_uri]);

        // Set up the headers for GitHub
        $headers['headers'] = [
            'Accept'        => 'application/vnd.github.v3+json',  // GitHub API specific accept header
            'User-Agent'    => 'FS PBX',                          // GitHub API requires a User-Agent header
        ];

        $data = array_merge([
            'timeout' => 30,
            'http_errors' => false,
        ], $extra_data);

        $options = array_merge_recursive($data, $headers);

        try {
            $response = $client->request($method, $path, $options);
        } catch (ConnectException | Exception | RequestException $e) {
            $response = $e;
        }

        return $response;
    }

    /**
     * Get the HTTP response from GitHub API.
     *
     * @param string $method HTTP method
     * @param string $path API path
     * @param array $data Additional data
     * @param int $status_code Expected status code
     * @return mixed
     */
    public function getResponse($method, $path, $data = [], $status_code = 200)
    {
        $response = $this->apiRequest($method, $path, $data);

        if ($response instanceof Exception || $response->getStatusCode() != $status_code) {
            return false;
        }

        return $response;
    }

    /**
     * Get the decoded response body from GitHub API.
     *
     * @param string $method HTTP method
     * @param string $path API path
     * @param array $data Additional data
     * @param int $status_code Expected status code
     * @return array|object
     */
    public function getResponseBody($method, $path, $data = [], $status_code = 200)
    {
        $response = $this->getResponse($method, $path, $data, $status_code);
        
        if (!$response) {
            return [];
        }

        return json_decode($response->getBody());
    }

    /**
     * Fetch the latest release information from a GitHub repository.
     *
     * @param string $owner The owner of the repository
     * @param string $repo The repository name
     * @return object|array The latest release data
     */
    public function getLatestRelease($owner, $repo)
    {
        $path = "repos/{$owner}/{$repo}/releases/latest";
        return $this->getResponseBody('GET', $path);
    }
}
