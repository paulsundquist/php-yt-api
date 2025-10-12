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

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
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
            if ($method !== 'GET') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $channels = $db->getActiveChannels();
            echo json_encode([
                'success' => true,
                'count' => count($channels),
                'channels' => $channels
            ]);
            break;

        case '/fetch':
            if ($method !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                break;
            }

            $maxResults = isset($_POST['max_results']) ? (int)$_POST['max_results'] : 10;

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
