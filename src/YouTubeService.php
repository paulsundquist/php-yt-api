<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class YouTubeService
{
    private $apiKey;
    private $client;
    private $baseUrl = 'https://www.googleapis.com/youtube/v3/';

    public function __construct($apiKey = null)
    {
        $this->apiKey = $apiKey ?: getenv('YOUTUBE_API_KEY');
        if (!$this->apiKey) {
            throw new \Exception("YouTube API key is required");
        }
        $this->client = new Client(['base_uri' => $this->baseUrl]);
    }

    /**
     * Get channel info by handle (@ username)
     *
     * @param string $handle YouTube handle (with or without @)
     * @return array|null Channel info with id and name
     */
    public function getChannelByHandle($handle)
    {
        try {
            // Remove @ if present
            $handle = ltrim($handle, '@');

            $response = $this->client->request('GET', 'channels', [
                'query' => [
                    'key' => $this->apiKey,
                    'forHandle' => $handle,
                    'part' => 'snippet,contentDetails'
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            if (empty($data['items'])) {
                return null;
            }

            $channel = $data['items'][0];
            return [
                'channel_id' => $channel['id'],
                'channel_name' => $channel['snippet']['title'],
                'uploads_playlist_id' => $channel['contentDetails']['relatedPlaylists']['uploads'] ?? null
            ];

        } catch (GuzzleException $e) {
            throw new \Exception("Failed to get channel by handle: " . $e->getMessage());
        }
    }

    /**
     * Get uploads playlist ID for a channel
     *
     * @param string $channelId YouTube channel ID
     * @return string|null Uploads playlist ID
     */
    public function getUploadsPlaylistId($channelId)
    {
        try {
            $response = $this->client->request('GET', 'channels', [
                'query' => [
                    'key' => $this->apiKey,
                    'id' => $channelId,
                    'part' => 'contentDetails'
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? null;

        } catch (GuzzleException $e) {
            throw new \Exception("Failed to get uploads playlist: " . $e->getMessage());
        }
    }

    /**
     * Get recent videos from a channel using uploads playlist
     *
     * @param string $channelId YouTube channel ID
     * @param string $playlistId Uploads playlist ID
     * @param int $maxResults Maximum number of results (default: 10, max: 50)
     * @return array
     */
    public function getChannelVideos($channelId, $playlistId, $maxResults = 10)
    {
        try {
            // Get video IDs from uploads playlist (1 quota unit vs 100 for search)
            $playlistResponse = $this->client->request('GET', 'playlistItems', [
                'query' => [
                    'key' => $this->apiKey,
                    'playlistId' => $playlistId,
                    'part' => 'contentDetails',
                    'maxResults' => min($maxResults, 50)
                ]
            ]);

            $playlistData = json_decode($playlistResponse->getBody(), true);

            if (empty($playlistData['items'])) {
                return [];
            }

            // Extract video IDs
            $videoIds = array_map(function ($item) {
                return $item['contentDetails']['videoId'];
            }, $playlistData['items']);

            // Get detailed video statistics
            $videosResponse = $this->client->request('GET', 'videos', [
                'query' => [
                    'key' => $this->apiKey,
                    'id' => implode(',', $videoIds),
                    'part' => 'snippet,contentDetails,statistics'
                ]
            ]);

            $videosData = json_decode($videosResponse->getBody(), true);

            return $this->formatVideos($videosData['items'] ?? [], $channelId);

        } catch (GuzzleException $e) {
            throw new \Exception("YouTube API request failed: " . $e->getMessage());
        }
    }

    /**
     * Format video data for database storage
     *
     * @param array $videos
     * @param string $channelId
     * @return array
     */
    private function formatVideos($videos, $channelId)
    {
        $formatted = [];

        foreach ($videos as $video) {
            $formatted[] = [
                'video_id' => $video['id'],
                'channel_id' => $channelId,
                'title' => $video['snippet']['title'] ?? '',
                'description' => $video['snippet']['description'] ?? '',
                'published_at' => date('Y-m-d H:i:s', strtotime($video['snippet']['publishedAt'])),
                'thumbnail_url' => $video['snippet']['thumbnails']['high']['url'] ?? '',
                'view_count' => $video['statistics']['viewCount'] ?? 0,
                'like_count' => $video['statistics']['likeCount'] ?? 0,
                'comment_count' => $video['statistics']['commentCount'] ?? 0,
                'duration' => $video['contentDetails']['duration'] ?? ''
            ];
        }

        return $formatted;
    }

    /**
     * Fetch and store videos for all active channels
     *
     * @param Database $db
     * @param int $maxResultsPerChannel
     * @return array Statistics about the fetch operation
     */
    public function fetchAndStoreVideos(Database $db, $maxResultsPerChannel = 10)
    {
        $channels = $db->getActiveChannels();
        $stats = [
            'channels_processed' => 0,
            'videos_added' => 0,
            'videos_updated' => 0,
            'errors' => []
        ];

        foreach ($channels as $channel) {
            try {
                // Get or fetch uploads playlist ID
                $playlistId = $channel['uploads_playlist_id'] ?? null;

                if (!$playlistId) {
                    // Fetch playlist ID from YouTube (1 quota unit)
                    $playlistId = $this->getUploadsPlaylistId($channel['channel_id']);

                    if ($playlistId) {
                        // Store it in DB for future use
                        $db->updateChannelPlaylistId($channel['channel_id'], $playlistId);
                    } else {
                        throw new \Exception("Could not get uploads playlist ID");
                    }
                }

                $videos = $this->getChannelVideos($channel['channel_id'], $playlistId, $maxResultsPerChannel);

                foreach ($videos as $videoData) {
                    try {
                        $db->insertOrUpdateVideo($videoData);
                        $stats['videos_added']++;
                    } catch (\Exception $e) {
                        $stats['errors'][] = "Failed to save video {$videoData['video_id']}: " . $e->getMessage();
                    }
                }

                $stats['channels_processed']++;

            } catch (\Exception $e) {
                $stats['errors'][] = "Failed to fetch videos for channel {$channel['channel_id']}: " . $e->getMessage();
            }
        }

        return $stats;
    }
}
