<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Database;
use App\YouTubeService;

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
                    'GET /channels' => 'Get all active channels'
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
                $result = $db->addChannel($channelId, $channelName);

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

        case '/fetch':
            if ($method !== 'POST' && $method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $maxResults = $method === 'POST'
                ? (isset($_POST['max_results']) ? (int)$_POST['max_results'] : 10)
                : (isset($_GET['max_results']) ? (int)$_GET['max_results'] : 10);

            $youtubeService = new YouTubeService();
            $stats = $youtubeService->fetchAndStoreVideos($db, $maxResults);

            echo json_encode([
                'success' => true,
                'message' => 'Videos fetched and stored successfully',
                'stats' => $stats
            ]);
            break;

        default:
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
