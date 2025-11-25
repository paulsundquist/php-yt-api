<?php

namespace App;

class TVDBService
{
    private $apiKey;
    private $baseUrl = 'https://api4.thetvdb.com/v4';
    private $token = null;

    public function __construct($apiKey = null)
    {
        // Load API key from environment or parameter
        $this->apiKey = $apiKey ?: getenv('TVDB_API_KEY');
    }

    /**
     * Authenticate and get a bearer token
     */
    private function authenticate()
    {
        if ($this->token) {
            return $this->token;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode(['apikey' => $this->apiKey]),
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents($this->baseUrl . '/login', false, $context);

        if ($response === false) {
            throw new \Exception("Failed to connect to TVDB API");
        }

        $data = json_decode($response, true);

        if (isset($data['data']['token'])) {
            $this->token = $data['data']['token'];
            return $this->token;
        }

        throw new \Exception("Failed to authenticate with TVDB API: " . ($data['message'] ?? 'Unknown error'));
    }

    /**
     * Search for movies/series by name
     */
    public function searchByName($query, $type = 'series')
    {
        $token = $this->authenticate();

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents(
            $this->baseUrl . '/search?query=' . urlencode($query) . '&type=' . $type,
            false,
            $context
        );

        if ($response === false) {
            throw new \Exception("Failed to connect to TVDB API");
        }

        $data = json_decode($response, true);

        if (isset($data['data'])) {
            return $data['data'];
        }

        throw new \Exception("Failed to search TVDB: " . ($data['message'] ?? 'Unknown error'));
    }

    /**
     * Get movie/series details by ID
     */
    public function getDetails($id, $type = 'series')
    {
        $token = $this->authenticate();

        $endpoint = $type === 'movie' ? 'movies' : 'series';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents($this->baseUrl . "/{$endpoint}/{$id}/extended", false, $context);

        if ($response === false) {
            throw new \Exception("Failed to connect to TVDB API");
        }

        $data = json_decode($response, true);

        if (isset($data['data'])) {
            return $data['data'];
        }

        throw new \Exception("Failed to get details from TVDB: " . ($data['message'] ?? 'Unknown error'));
    }

    /**
     * Get cast/actors for a series
     */
    public function getCast($seriesId)
    {
        $token = $this->authenticate();

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents(
            $this->baseUrl . "/series/{$seriesId}/extended?meta=translations",
            false,
            $context
        );

        if ($response === false) {
            throw new \Exception("Failed to connect to TVDB API");
        }

        $data = json_decode($response, true);

        if (isset($data['data']['characters'])) {
            return $data['data']['characters'];
        }

        throw new \Exception("Failed to get cast from TVDB: " . ($data['message'] ?? 'Unknown error'));
    }

    /**
     * Search for people (actors)
     */
    public function searchPeople($query)
    {
        $token = $this->authenticate();

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents(
            $this->baseUrl . '/search?query=' . urlencode($query) . '&type=people',
            false,
            $context
        );

        if ($response === false) {
            throw new \Exception("Failed to connect to TVDB API");
        }

        $data = json_decode($response, true);

        if (isset($data['data'])) {
            return $data['data'];
        }

        throw new \Exception("Failed to search people on TVDB: " . ($data['message'] ?? 'Unknown error'));
    }

    /**
     * Get person details including filmography
     */
    public function getPersonDetails($personId)
    {
        $token = $this->authenticate();

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
                'ignore_errors' => true
            ]
        ]);

        $response = file_get_contents(
            $this->baseUrl . "/people/{$personId}/extended",
            false,
            $context
        );

        if ($response === false) {
            throw new \Exception("Failed to connect to TVDB API");
        }

        $data = json_decode($response, true);

        if (isset($data['data'])) {
            return $data['data'];
        }

        throw new \Exception("Failed to get person details from TVDB: " . ($data['message'] ?? 'Unknown error'));
    }
}
