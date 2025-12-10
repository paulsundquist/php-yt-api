<?php
// Handler for /2v/{short_id} short URLs

require_once __DIR__ . '/vendor/autoload.php';

use App\Database;

// Load environment variables from .env file
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

// Get short_id from URL
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#^/2v/([A-Z0-9]{8})$#', $uri, $matches);
$shortId = $matches[1] ?? $_GET['id'] ?? '';

if (empty($shortId)) {
    http_response_code(400);
    echo '400 - Bad Request: Missing short ID';
    exit;
}

try {
    $db = Database::getInstance();
    $config = $db->get2VideosConfig($shortId);

    if ($config) {
        $redirectUrl = "/2videos_play.html?v1={$config['video1']}&v2={$config['video2']}&layout={$config['layout']}&vol1={$config['volume1']}&vol2={$config['volume2']}";
        header("Location: $redirectUrl");
        exit;
    } else {
        http_response_code(404);
        echo '404 - Configuration Not Found';
        exit;
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo '500 - Server Error: ' . $e->getMessage();
    exit;
}
