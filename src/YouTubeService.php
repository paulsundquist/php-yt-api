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
     * Search for YouTube channels by name
     *
     * @param string $query Search query
     * @param int $maxResults Maximum results to return
     * @return array
     */
    public function searchChannels($query, $maxResults = 8)
    {
        try {
            $response = $this->client->request('GET', 'search', [
                'query' => [
                    'key'        => $this->apiKey,
                    'part'       => 'snippet',
                    'type'       => 'channel',
                    'q'          => $query,
                    'maxResults' => $maxResults,
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            return array_map(function ($item) {
                return [
                    'channelId'   => $item['id']['channelId'],
                    'name'        => $item['snippet']['title'],
                    'description' => $item['snippet']['description'] ?? '',
                    'thumbnail'   => $item['snippet']['thumbnails']['default']['url'] ?? '',
                ];
            }, $data['items'] ?? []);

        } catch (GuzzleException $e) {
            throw new \Exception("Failed to search channels: " . $e->getMessage());
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
            $videoIds = [];
            $pageToken = null;
            $remaining = $maxResults;

            // Paginate through playlist items until we have enough video IDs
            do {
                $query = [
                    'key' => $this->apiKey,
                    'playlistId' => $playlistId,
                    'part' => 'contentDetails',
                    'maxResults' => min($remaining, 50)
                ];
                if ($pageToken) {
                    $query['pageToken'] = $pageToken;
                }

                $playlistResponse = $this->client->request('GET', 'playlistItems', ['query' => $query]);
                $playlistData = json_decode($playlistResponse->getBody(), true);

                if (empty($playlistData['items'])) {
                    break;
                }

                foreach ($playlistData['items'] as $item) {
                    $videoIds[] = $item['contentDetails']['videoId'];
                }

                $remaining -= count($playlistData['items']);
                $pageToken = $playlistData['nextPageToken'] ?? null;

            } while ($pageToken && $remaining > 0);

            if (empty($videoIds)) {
                return [];
            }

            // Fetch video details in batches of 50 (API limit per request)
            $allVideos = [];
            foreach (array_chunk($videoIds, 50) as $chunk) {
                $videosResponse = $this->client->request('GET', 'videos', [
                    'query' => [
                        'key' => $this->apiKey,
                        'id' => implode(',', $chunk),
                        'part' => 'snippet,contentDetails,statistics'
                    ]
                ]);
                $videosData = json_decode($videosResponse->getBody(), true);
                $allVideos = array_merge($allVideos, $videosData['items'] ?? []);
            }

            return $this->formatVideos($allVideos, $channelId);

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
     * Get all available video categories for a region
     *
     * @param string $regionCode Region code (e.g., 'US', 'GB', default: 'US')
     * @return array List of video categories
     */
    public function getVideoCategories($regionCode = 'US')
    {
        try {
            $response = $this->client->request('GET', 'videoCategories', [
                'query' => [
                    'key' => $this->apiKey,
                    'part' => 'snippet',
                    'regionCode' => $regionCode
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            if (empty($data['items'])) {
                return [];
            }

            $categories = [];
            foreach ($data['items'] as $item) {
                $categories[] = [
                    'id' => $item['id'],
                    'title' => $item['snippet']['title'],
                    'assignable' => $item['snippet']['assignable'] ?? true
                ];
            }

            return $categories;

        } catch (GuzzleException $e) {
            throw new \Exception("Failed to get video categories: " . $e->getMessage());
        }
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
        return $this->fetchAndStoreVideosForChannels($db, $channels, $maxResultsPerChannel);
    }

    /**
     * Fetch and store videos for specific channels
     *
     * @param Database $db
     * @param array $channels List of channel records
     * @param int $maxResultsPerChannel
     * @return array Statistics about the fetch operation
     */
    public function fetchAndStoreVideosForChannels(Database $db, $channels, $maxResultsPerChannel = 10)
    {
        $stats = [
            'channels_processed' => 0,
            'videos_added' => 0,
            'videos_updated' => 0,
            'errors' => []
        ];

        foreach ($channels as $channel) {
            try {
                $channelId = $channel['channel_id'];
                $playlistId = $channel['uploads_playlist_id'] ?? null;

                // Check if this is a playlist (channel_id starts with "PL_")
                $isPlaylist = str_starts_with($channelId, 'PL_');

                if ($isPlaylist) {
                    // For playlists, extract the actual playlist ID from the channel_id
                    // Format: PL_<actual_playlist_id>
                    $actualPlaylistId = substr($channelId, 3); // Remove "PL_" prefix

                    // Use the extracted playlist ID or the one stored in uploads_playlist_id
                    $playlistId = $playlistId ?: $actualPlaylistId;
                } else {
                    // For channels, get or fetch uploads playlist ID
                    if (!$playlistId) {
                        // Fetch playlist ID from YouTube (1 quota unit)
                        $playlistId = $this->getUploadsPlaylistId($channelId);

                        if ($playlistId) {
                            // Store it in DB for future use
                            $db->updateChannelPlaylistId($channelId, $playlistId);
                        } else {
                            throw new \Exception("Could not get uploads playlist ID");
                        }
                    }
                }

                $videos = $this->getChannelVideos($channelId, $playlistId, $maxResultsPerChannel);

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
