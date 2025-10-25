<?php
// Router for PHP built-in web server

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API endpoints
if (preg_match('#^/(channels|videos|fetch|categories|groups)#', $uri)) {
    require __DIR__ . '/index.php';
    return true;
}

// If file exists, serve it
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
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
