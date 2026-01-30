<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Database;
use App\YouTubeService;
use App\TourService;
use App\TVDBService;
use App\MovieListService;

// Load environment variables from .env file
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

// Set headers for JSON API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/index.php', '', $path);

try {
    $db = Database::getInstance();

    // Router
    switch ($path) {
        case '/':
        case '':
            echo json_encode([
                'message' => 'YouTube Channel Aggregator API',
                'endpoints' => [
                    'GET /videos' => 'Get all videos',
                    'GET /videos?channel_id={id}' => 'Get videos from specific channel',
                    'GET /videos?limit={n}&offset={n}' => 'Paginate videos',
                    'POST /fetch' => 'Fetch latest videos from YouTube',
                    'GET /channels' => 'Get all active channels',
                    'POST /channels' => 'Add a new channel',
                    'GET /channels/export' => 'Export all channels as JSON',
                    'POST /channels/import' => 'Import channels from JSON',
                    'GET /groups/default' => 'Get default channel groups',
                    'GET /categories' => 'Get YouTube video categories'
                ]
            ]);
            break;

        case '/videos':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $channelId = $_GET['channel_id'] ?? null;

            $videos = $db->getVideos($limit, $offset, $channelId);
            echo json_encode([
                'success' => true,
                'count' => count($videos),
                'videos' => $videos
            ]);
            break;

        case '/channels':
            if ($method === 'GET') {
                $channels = $db->getActiveChannels();
                echo json_encode([
                    'success' => true,
                    'count' => count($channels),
                    'channels' => $channels
                ]);
            } elseif ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);

                $channelId = $input['channel_id'] ?? '';
                $channelName = $input['channel_name'] ?? '';
                $channelCategory = $input['channel_category'] ?? null;
                $schedule = $input['schedule'] ?? null;
                $fetchVideos = $input['fetch_videos'] ?? true;
                $playlistId = null;

                // If channel_id starts with @, resolve it using YouTube API
                if (str_starts_with($channelId, '@')) {
                    try {
                        $youtubeService = new YouTubeService();
                        $channelInfo = $youtubeService->getChannelByHandle($channelId);

                        if (!$channelInfo) {
                            http_response_code(404);
                            echo json_encode(['error' => 'Channel handle not found on YouTube']);
                            break;
                        }

                        $channelId = $channelInfo['channel_id'];
                        // Use the name from YouTube if not provided
                        if (empty($channelName)) {
                            $channelName = $channelInfo['channel_name'];
                        }

                        $playlistId = $channelInfo['uploads_playlist_id'] ?? null;
                    } catch (\Exception $e) {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to resolve channel handle: ' . $e->getMessage()]);
                        break;
                    }
                }

                if (empty($channelId) || empty($channelName)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'channel_id and channel_name are required']);
                    break;
                }

                // Add the channel to database
                $result = $db->addChannel($channelId, $channelName, $channelCategory, $schedule);

                if (!$result) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to add channel']);
                    break;
                }

                // Update playlist ID if we have it
                if ($playlistId) {
                    $db->updateChannelPlaylistId($channelId, $playlistId);
                }

                // Optionally fetch initial videos
                $videoStats = null;
                if ($fetchVideos) {
                    try {
                        $youtubeService = $youtubeService ?? new YouTubeService();

                        // Get playlist ID if we don't have it
                        if (!$playlistId) {
                            $playlistId = $youtubeService->getUploadsPlaylistId($channelId);
                            if ($playlistId) {
                                $db->updateChannelPlaylistId($channelId, $playlistId);
                            }
                        }

                        // Fetch videos
                        if ($playlistId) {
                            $videos = $youtubeService->getChannelVideos($channelId, $playlistId, 10);
                            $videoCount = 0;
                            foreach ($videos as $videoData) {
                                $db->insertOrUpdateVideo($videoData);
                                $videoCount++;
                            }
                            $videoStats = ['videos_fetched' => $videoCount];
                        }
                    } catch (\Exception $e) {
                        // Don't fail the whole request if video fetch fails
                        $videoStats = ['error' => 'Failed to fetch videos: ' . $e->getMessage()];
                    }
                }

                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Channel added successfully',
                    'channel_id' => $channelId,
                    'channel_name' => $channelName,
                    'video_stats' => $videoStats
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/fit-channels':
            // Handle CORS preflight
            if ($method === 'OPTIONS') {
                header('Access-Control-Allow-Headers: Content-Type');
                http_response_code(200);
                break;
            }

            if ($method === 'GET') {
                $stmt = $db->getConnection()->prepare(
                    "SELECT id, channel_id, channel_name, channel_category, schedule, uploads_playlist_id, updated_at FROM fit_channels WHERE is_active = 1"
                );
                $stmt->execute();
                $channels = $stmt->fetchAll();

                echo json_encode([
                    'success' => true,
                    'count' => count($channels),
                    'channels' => $channels
                ]);
            } elseif ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);

                $channelId = $input['channel_id'] ?? '';
                $channelName = $input['channel_name'] ?? '';
                $channelCategory = $input['channel_category'] ?? null;
                $schedule = $input['schedule'] ?? null;
                $playlistId = null;

                if (empty($channelId)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'channel_id is required']);
                    break;
                }

                // If channel_id starts with @, resolve it using YouTube API
                if (str_starts_with($channelId, '@')) {
                    try {
                        $youtubeService = new YouTubeService();
                        $channelInfo = $youtubeService->getChannelByHandle($channelId);

                        if (!$channelInfo) {
                            http_response_code(404);
                            echo json_encode(['error' => 'Channel handle not found on YouTube']);
                            break;
                        }

                        $channelId = $channelInfo['channel_id'];
                        // Use the name from YouTube if not provided
                        if (empty($channelName)) {
                            $channelName = $channelInfo['channel_name'];
                        }

                        $playlistId = $channelInfo['uploads_playlist_id'] ?? null;
                    } catch (\Exception $e) {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to resolve channel handle: ' . $e->getMessage()]);
                        break;
                    }
                }

                // After handle resolution, verify we have a channel name
                if (empty($channelName)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'channel_name is required (or use @ handle to auto-fetch)']);
                    break;
                }

                // Add the channel to fit_channels table
                $stmt = $db->getConnection()->prepare(
                    "INSERT INTO fit_channels (channel_id, channel_name, channel_category, schedule, is_active)
                     VALUES (:channel_id, :channel_name, :channel_category, :schedule, 1)
                     ON DUPLICATE KEY UPDATE
                     channel_name = VALUES(channel_name),
                     channel_category = VALUES(channel_category),
                     schedule = VALUES(schedule),
                     is_active = 1"
                );
                $result = $stmt->execute([
                    ':channel_id' => $channelId,
                    ':channel_name' => $channelName,
                    ':channel_category' => $channelCategory,
                    ':schedule' => $schedule
                ]);

                if (!$result) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to add fitness channel']);
                    break;
                }

                // Update playlist ID if we have it from handle resolution
                if ($playlistId) {
                    $updateStmt = $db->getConnection()->prepare(
                        "UPDATE fit_channels SET uploads_playlist_id = :playlist_id WHERE channel_id = :channel_id"
                    );
                    $updateStmt->execute([
                        ':playlist_id' => $playlistId,
                        ':channel_id' => $channelId
                    ]);
                }

                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Fitness channel added successfully',
                    'channel_id' => $channelId,
                    'channel_name' => $channelName
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/channels/export':
            if ($method === 'GET') {
                $channels = $db->getActiveChannels();

                // Format for export
                $exportData = [
                    'version' => '1.0',
                    'exported_at' => date('Y-m-d H:i:s'),
                    'count' => count($channels),
                    'channels' => array_map(function($channel) {
                        return [
                            'channel_id' => $channel['channel_id'],
                            'channel_name' => $channel['channel_name'],
                            'channel_category' => $channel['channel_category'] ?? null,
                            'uploads_playlist_id' => $channel['uploads_playlist_id'] ?? null
                        ];
                    }, $channels)
                ];

                echo json_encode($exportData, JSON_PRETTY_PRINT);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/channels/import':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);

                if (!isset($input['channels']) || !is_array($input['channels'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid import format. Expected "channels" array']);
                    break;
                }

                $results = [
                    'success' => true,
                    'total' => count($input['channels']),
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => []
                ];

                foreach ($input['channels'] as $index => $channel) {
                    $channelId = $channel['channel_id'] ?? '';
                    $channelName = $channel['channel_name'] ?? '';
                    $channelCategory = $channel['channel_category'] ?? null;
                    $playlistId = $channel['uploads_playlist_id'] ?? null;

                    if (empty($channelId) || empty($channelName)) {
                        $results['skipped']++;
                        $results['errors'][] = [
                            'index' => $index,
                            'error' => 'Missing channel_id or channel_name'
                        ];
                        continue;
                    }

                    try {
                        $result = $db->addChannel($channelId, $channelName, $channelCategory);

                        if ($result) {
                            // Update playlist ID if provided
                            if ($playlistId) {
                                $db->updateChannelPlaylistId($channelId, $playlistId);
                            }
                            $results['imported']++;
                        } else {
                            $results['skipped']++;
                            $results['errors'][] = [
                                'index' => $index,
                                'channel_id' => $channelId,
                                'error' => 'Failed to add channel (may already exist)'
                            ];
                        }
                    } catch (\Exception $e) {
                        $results['skipped']++;
                        $results['errors'][] = [
                            'index' => $index,
                            'channel_id' => $channelId,
                            'error' => $e->getMessage()
                        ];
                    }
                }

                http_response_code(200);
                echo json_encode($results);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/fetch':
            if ($method !== 'POST' && $method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $maxResults = $method === 'POST'
                ? (isset($_POST['max_results']) ? (int)$_POST['max_results'] : 10)
                : (isset($_GET['max_results']) ? (int)$_GET['max_results'] : 10);

            $schedule = $method === 'POST'
                ? ($_POST['schedule'] ?? null)
                : ($_GET['schedule'] ?? null);

            $channelId = $method === 'POST'
                ? ($_POST['channel_id'] ?? null)
                : ($_GET['channel_id'] ?? null);

            // Validate schedule if provided
            if ($schedule !== null && !in_array($schedule, ['hourly', 'daily', 'weekly'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid schedule value. Must be hourly, daily, or weekly'
                ]);
                break;
            }

            $youtubeService = new YouTubeService();

            // Get channels based on filters
            if ($channelId) {
                // Fetch specific channel
                $allChannels = $db->getActiveChannels();
                $channels = array_filter($allChannels, function($channel) use ($channelId) {
                    return $channel['channel_id'] === $channelId;
                });
                $channels = array_values($channels); // Re-index array

                if (empty($channels)) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => "No active channel found with ID: {$channelId}"
                    ]);
                    break;
                }
                $stats = $youtubeService->fetchAndStoreVideosForChannels($db, $channels, $maxResults);
            } elseif ($schedule) {
                $channels = $db->getActiveChannelsBySchedule($schedule);
                $stats = $youtubeService->fetchAndStoreVideosForChannels($db, $channels, $maxResults);
            } else {
                $stats = $youtubeService->fetchAndStoreVideos($db, $maxResults);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Videos fetched and stored successfully',
                'stats' => $stats
            ]);
            break;

        case '/groups/default':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $configFile = __DIR__ . '/config/default_groups.json';
            if (!file_exists($configFile)) {
                echo json_encode([
                    'success' => true,
                    'groups' => []
                ]);
                break;
            }

            $defaultGroupsConfig = json_decode(file_get_contents($configFile), true);
            $allChannels = $db->getActiveChannels();

            // Build a map of channel id (auto-increment) to channel data
            $channelMap = [];
            foreach ($allChannels as $channel) {
                $channelMap[$channel['id']] = $channel;
            }

            // Process default groups
            $defaultGroups = [];
            foreach ($defaultGroupsConfig as $groupKey => $groupConfig) {
                $channels = [];
                foreach ($groupConfig['channel_ids'] as $channelId) {
                    if (isset($channelMap[$channelId])) {
                        $channels[] = [
                            'channel_id' => $channelMap[$channelId]['channel_id'],
                            'channel_name' => $channelMap[$channelId]['channel_name']
                        ];
                    }
                }

                if (!empty($channels)) {
                    $defaultGroups[$groupKey] = [
                        'name' => $groupConfig['name'],
                        'description' => $groupConfig['description'] ?? '',
                        'channels' => $channels,
                        'is_default' => true
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'groups' => $defaultGroups
            ]);
            break;

        case '/categories':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $regionCode = $_GET['region'] ?? 'US';
            $youtubeService = new YouTubeService();
            $categories = $youtubeService->getVideoCategories($regionCode);

            echo json_encode([
                'success' => true,
                'region' => $regionCode,
                'count' => count($categories),
                'categories' => $categories
            ]);
            break;

        case '/votes':
            if ($method === 'GET') {
                $votes = $db->getFeatureVotes();

                // Convert to a more convenient format
                $voteData = [];
                foreach ($votes as $vote) {
                    $voteData[$vote['feature_id']] = (int)$vote['vote_count'];
                }

                echo json_encode([
                    'success' => true,
                    'votes' => $voteData
                ]);
            } elseif ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $featureId = $input['feature_id'] ?? '';
                $action = $input['action'] ?? ''; // 'add' or 'remove'

                if (empty($featureId) || empty($action)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'feature_id and action are required']);
                    break;
                }

                if ($action === 'add') {
                    $db->addFeatureVote($featureId);
                } elseif ($action === 'remove') {
                    $db->removeFeatureVote($featureId);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action. Must be "add" or "remove"']);
                    break;
                }

                // Return updated votes
                $votes = $db->getFeatureVotes();
                $voteData = [];
                foreach ($votes as $vote) {
                    $voteData[$vote['feature_id']] = (int)$vote['vote_count'];
                }

                echo json_encode([
                    'success' => true,
                    'votes' => $voteData
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/api/tours':
            $tourService = new TourService();

            if ($method === 'GET') {
                $tours = $tourService->getAllTours();
                echo json_encode([
                    'success' => true,
                    'count' => count($tours),
                    'tours' => $tours
                ]);
            } elseif ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);

                if (empty($input['tour_name'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'tour_name is required']);
                    break;
                }

                $tourId = $tourService->createTour(
                    $input['tour_name'],
                    $input['tour_description'] ?? null,
                    $input['created_by'] ?? null
                );

                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Tour created successfully',
                    'tour_id' => $tourId
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/tvdb/search':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $query = $_GET['q'] ?? $_GET['query'] ?? '';
            $type = $_GET['type'] ?? 'series'; // series, movie, or people

            if (empty($query)) {
                http_response_code(400);
                echo json_encode(['error' => 'Query parameter "q" or "query" is required']);
                break;
            }

            try {
                $tvdbService = new TVDBService();

                if ($type === 'people') {
                    $results = $tvdbService->searchPeople($query);
                } else {
                    $results = $tvdbService->searchByName($query, $type);
                }

                echo json_encode([
                    'success' => true,
                    'query' => $query,
                    'type' => $type,
                    'count' => count($results),
                    'results' => $results
                ]);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;

        case '/tvdb/details':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $id = $_GET['id'] ?? '';
            $type = $_GET['type'] ?? 'series';

            if (empty($id)) {
                http_response_code(400);
                echo json_encode(['error' => 'ID parameter is required']);
                break;
            }

            try {
                $tvdbService = new TVDBService();
                $details = $tvdbService->getDetails($id, $type);

                echo json_encode([
                    'success' => true,
                    'data' => $details
                ]);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;

        case '/tvdb/cast':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $seriesId = $_GET['series_id'] ?? $_GET['id'] ?? '';

            if (empty($seriesId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Series ID parameter is required']);
                break;
            }

            try {
                $tvdbService = new TVDBService();
                $cast = $tvdbService->getCast($seriesId);

                echo json_encode([
                    'success' => true,
                    'series_id' => $seriesId,
                    'cast' => $cast
                ]);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;

        case '/tvdb/person':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $personId = $_GET['person_id'] ?? $_GET['id'] ?? '';

            if (empty($personId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Person ID parameter is required']);
                break;
            }

            try {
                $tvdbService = new TVDBService();
                $person = $tvdbService->getPersonDetails($personId);

                echo json_encode([
                    'success' => true,
                    'person_id' => $personId,
                    'person' => $person
                ]);
            } catch (\Exception $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            break;

        case '/chains':
            if ($method === 'POST') {
                // Save a chain
                $input = json_decode(file_get_contents('php://input'), true);
                $chainId = $input['chain_id'] ?? '';
                $chainData = $input['chain_data'] ?? null;
                $chainName = $input['chain_name'] ?? null;

                if (empty($chainId) || empty($chainData)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Chain ID and chain data are required']);
                    break;
                }

                try {
                    $db->saveChain($chainId, $chainData, $chainName);
                    echo json_encode([
                        'success' => true,
                        'chain_id' => $chainId,
                        'chain_name' => $chainName,
                        'message' => 'Chain saved successfully'
                    ]);
                } catch (\Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            } elseif ($method === 'GET') {
                // Get all chains or a specific chain
                $chainId = $_GET['chain_id'] ?? '';

                try {
                    if ($chainId) {
                        $chain = $db->getChain($chainId);
                        if ($chain) {
                            echo json_encode([
                                'success' => true,
                                'chain' => $chain
                            ]);
                        } else {
                            http_response_code(404);
                            echo json_encode([
                                'success' => false,
                                'error' => 'Chain not found'
                            ]);
                        }
                    } else {
                        $chains = $db->getAllChains();
                        echo json_encode([
                            'success' => true,
                            'count' => count($chains),
                            'chains' => $chains
                        ]);
                    }
                } catch (\Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/api/playlists':
            if ($method === 'GET') {
                $category = $_GET['category'] ?? null;
                $sortBy = $_GET['sort'] ?? 'created_at';
                $playlists = $db->getAllPlaylists($category, $sortBy);
                echo json_encode([
                    'success' => true,
                    'count' => count($playlists),
                    'playlists' => $playlists
                ]);
            } elseif ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $playlistId = $input['playlist_id'] ?? '';
                $playlistName = $input['playlist_name'] ?? '';
                $category = $input['category'] ?? '';
                $url = $input['url'] ?? '';

                if (empty($playlistId) || empty($playlistName) || empty($category)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'playlist_id, playlist_name, and category are required']);
                    break;
                }

                try {
                    $db->addPlaylist($playlistId, $playlistName, $category, $url);
                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Playlist added successfully'
                    ]);
                } catch (\Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/api/playlists/vote':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $playlistId = $input['playlist_id'] ?? '';
                $action = $input['action'] ?? 'add';

                if (empty($playlistId)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'playlist_id is required']);
                    break;
                }

                if (!in_array($action, ['add', 'remove'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action. Must be "add" or "remove"']);
                    break;
                }

                try {
                    $db->votePlaylist($playlistId, $action);
                    $playlist = $db->getPlaylist($playlistId);

                    echo json_encode([
                        'success' => true,
                        'vote_count' => $playlist ? (int)$playlist['vote_count'] : 0
                    ]);
                } catch (\Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/api/save-2videos':
            if ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $name = $input['name'] ?? null;
                $video1 = $input['video1'] ?? '';
                $video2 = $input['video2'] ?? '';
                $layout = $input['layout'] ?? 'vertical';
                $volume1 = $input['volume1'] ?? 100;
                $volume2 = $input['volume2'] ?? 100;

                if (empty($video1) || empty($video2)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'video1 and video2 are required']);
                    break;
                }

                // Generate a unique 8-character ID
                $shortId = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

                try {
                    $db->save2VideosConfig($shortId, $name, $video1, $video2, $layout, $volume1, $volume2);
                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'short_id' => $shortId,
                        'message' => '2videos configuration saved successfully'
                    ]);
                } catch (\Exception $e) {
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        case '/fit-videos':
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $category = $_GET['category'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

            $sql = "SELECT * FROM fit_videos";
            if ($category && $category !== 'all') {
                $sql .= " WHERE category = :category";
            }
            $sql .= " ORDER BY published_at DESC LIMIT :limit";

            $stmt = $db->getConnection()->prepare($sql);
            if ($category && $category !== 'all') {
                $stmt->bindValue(':category', $category, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $videos = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'count' => count($videos),
                'category' => $category ?? 'all',
                'videos' => $videos
            ]);
            break;

        case '/api/movie-lists':
            // Handle CORS preflight
            if ($method === 'OPTIONS') {
                header('Access-Control-Allow-Headers: Content-Type');
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
                http_response_code(200);
                break;
            }

            $movieListService = new MovieListService();

            if ($method === 'GET') {
                $curated = $movieListService->getCuratedLists();
                $recent = $movieListService->getRecentLists(10);

                echo json_encode([
                    'success' => true,
                    'curated' => $curated,
                    'recent' => $recent
                ]);
            } elseif ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);

                if (empty($input['name'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'name is required']);
                    break;
                }

                $listId = $movieListService->createList(
                    $input['name'],
                    $input['description'] ?? null
                );

                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'List created successfully',
                    'list_id' => $listId
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        default:
            // Check for dynamic tour routes
            if (preg_match('#^/api/tours/([A-Z0-9]{8})$#', $path, $matches)) {
                $tourId = $matches[1];
                $tourService = new TourService();

                if ($method === 'GET') {
                    $tour = $tourService->getTourById($tourId);
                    if (!$tour) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Tour not found']);
                        break;
                    }
                    echo json_encode([
                        'success' => true,
                        'tour' => $tour
                    ]);
                } elseif ($method === 'PUT') {
                    $input = json_decode(file_get_contents('php://input'), true);

                    if (empty($input['tour_name'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'tour_name is required']);
                        break;
                    }

                    $result = $tourService->updateTour(
                        $tourId,
                        $input['tour_name'],
                        $input['tour_description'] ?? null
                    );

                    if (!$result) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Tour not found']);
                        break;
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Tour updated successfully'
                    ]);
                } elseif ($method === 'DELETE') {
                    $result = $tourService->deleteTour($tourId);

                    if (!$result) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Tour not found']);
                        break;
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Tour deleted successfully'
                    ]);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            }

            // Add step to tour
            if (preg_match('#^/api/tours/([A-Z0-9]{8})/steps$#', $path, $matches)) {
                $tourId = $matches[1];
                $tourService = new TourService();

                if ($method === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);

                    if (empty($input['step_name']) || empty($input['youtube_id'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'step_name and youtube_id are required']);
                        break;
                    }

                    $stepId = $tourService->addStep($tourId, $input);

                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Step added successfully',
                        'step_id' => $stepId
                    ]);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            }

            // Update or delete step
            if (preg_match('#^/api/tour-steps/(\d+)$#', $path, $matches)) {
                $stepId = (int)$matches[1];
                $tourService = new TourService();

                if ($method === 'PUT') {
                    $input = json_decode(file_get_contents('php://input'), true);

                    $result = $tourService->updateStep($stepId, $input);

                    if (!$result) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Step not found or nothing to update']);
                        break;
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Step updated successfully'
                    ]);
                } elseif ($method === 'DELETE') {
                    $result = $tourService->deleteStep($stepId);

                    if (!$result) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Step not found']);
                        break;
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Step deleted successfully'
                    ]);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            }

            // Movie list routes
            if (preg_match('#^/api/movie-lists/([A-Z0-9]{8})$#', $path, $matches)) {
                $listId = $matches[1];
                $movieListService = new MovieListService();

                if ($method === 'GET') {
                    $list = $movieListService->getList($listId);
                    if (!$list) {
                        http_response_code(404);
                        echo json_encode(['error' => 'List not found']);
                        break;
                    }
                    $movieListService->incrementViewCount($listId);
                    echo json_encode([
                        'success' => true,
                        'list' => $list
                    ]);
                } elseif ($method === 'PUT') {
                    $input = json_decode(file_get_contents('php://input'), true);

                    if (empty($input['name'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'name is required']);
                        break;
                    }

                    $result = $movieListService->updateList(
                        $listId,
                        $input['name'],
                        $input['description'] ?? null
                    );

                    if (!$result) {
                        http_response_code(404);
                        echo json_encode(['error' => 'List not found']);
                        break;
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'List updated successfully'
                    ]);
                } elseif ($method === 'DELETE') {
                    $result = $movieListService->deleteList($listId);

                    if (!$result) {
                        http_response_code(404);
                        echo json_encode(['error' => 'List not found']);
                        break;
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'List deleted successfully'
                    ]);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            }

            // Add movie to list
            if (preg_match('#^/api/movie-lists/([A-Z0-9]{8})/items$#', $path, $matches)) {
                $listId = $matches[1];
                $movieListService = new MovieListService();

                if ($method === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);

                    if (empty($input['tmdb_id']) || empty($input['title'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'tmdb_id and title are required']);
                        break;
                    }

                    $itemId = $movieListService->addMovie($listId, $input);

                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Movie added successfully',
                        'item_id' => $itemId
                    ]);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            }

            // Remove movie from list or update note
            if (preg_match('#^/api/movie-lists/([A-Z0-9]{8})/items/(\d+)$#', $path, $matches)) {
                $listId = $matches[1];
                $itemId = (int)$matches[2];
                $movieListService = new MovieListService();

                if ($method === 'DELETE') {
                    $result = $movieListService->removeMovie($listId, $itemId);

                    if (!$result) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Item not found']);
                        break;
                    }

                    echo json_encode([
                        'success' => true,
                        'message' => 'Movie removed successfully'
                    ]);
                } elseif ($method === 'PUT') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $result = $movieListService->updateMovieNote($listId, $itemId, $input['notes'] ?? '');

                    echo json_encode([
                        'success' => $result,
                        'message' => $result ? 'Note updated successfully' : 'Failed to update note'
                    ]);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            }

            // Update movie note (alternate endpoint)
            if (preg_match('#^/api/movie-lists/([A-Z0-9]{8})/items/(\d+)/note$#', $path, $matches)) {
                $listId = $matches[1];
                $itemId = (int)$matches[2];
                $movieListService = new MovieListService();

                if ($method === 'PUT') {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $result = $movieListService->updateMovieNote($listId, $itemId, $input['notes'] ?? '');

                    echo json_encode([
                        'success' => $result,
                        'message' => $result ? 'Note updated successfully' : 'Failed to update note'
                    ]);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            }

            // Reorder movies in list
            if (preg_match('#^/api/movie-lists/([A-Z0-9]{8})/reorder$#', $path, $matches)) {
                $listId = $matches[1];
                $movieListService = new MovieListService();

                if ($method === 'POST') {
                    $input = json_decode(file_get_contents('php://input'), true);

                    if (empty($input['positions']) || !is_array($input['positions'])) {
                        http_response_code(400);
                        echo json_encode(['error' => 'positions array is required']);
                        break;
                    }

                    $result = $movieListService->reorderMovies($listId, $input['positions']);

                    echo json_encode([
                        'success' => $result,
                        'message' => $result ? 'Movies reordered successfully' : 'Failed to reorder movies'
                    ]);
                } else {
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
                }
                break;
            }

        case '/api/config':
            if ($method === 'GET') {
                $config = [
                    'tmdb_api_key' => getenv('TMDB_API_KEY') ?: null
                ];
                echo json_encode([
                    'success' => true,
                    'config' => $config
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
