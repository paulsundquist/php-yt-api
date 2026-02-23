<?php
// Router for PHP built-in web server

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// If file exists, serve it first
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Handle /2v/{id} short URLs
if (preg_match('#^/2v/([A-Z0-9]{8})$#', $uri, $matches)) {
    $shortId = $matches[1];
    require_once __DIR__ . '/vendor/autoload.php';

    // Load environment variables from .env file
    if (file_exists(__DIR__ . '/.env')) {
        $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }

    try {
        $db = App\Database::getInstance();
        $config = $db->get2VideosConfig($shortId);

        if ($config) {
            $redirectUrl = "/2videos_play.html?v1={$config['video1']}&v2={$config['video2']}&layout={$config['layout']}&vol1={$config['volume1']}&vol2={$config['volume2']}";
            header("Location: $redirectUrl");
            return true;
        } else {
            http_response_code(404);
            echo '404 - Configuration Not Found';
            return true;
        }
    } catch (\Exception $e) {
        http_response_code(500);
        echo '500 - Server Error: ' . $e->getMessage();
        error_log('2v route error: ' . $e->getMessage());
        return true;
    }
}

// API endpoints
if (preg_match('#^/(channels|videos|fetch|categories|groups|tours|tour-steps|votes|tvdb|chains|api|fit-videos|fit-channels)#', $uri)) {
    require __DIR__ . '/index.php';
    return true;
}

// Default to index.html
if ($uri === '/') {
    require __DIR__ . '/index.html';
    return true;
}

// 404
http_response_code(404);
echo '404 Not Found';
return true;
