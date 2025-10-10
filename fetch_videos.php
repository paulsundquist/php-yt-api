<?php

/**
 * CLI script to fetch YouTube videos
 * Usage: php fetch_videos.php [max_results_per_channel]
 */

require_once dirname(__FILE__) . '/vendor/autoload.php';

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

try {
    $maxResults = isset($argv[1]) ? (int)$argv[1] : 10;

    echo "Fetching videos from YouTube...\n";
    echo "Max results per channel: {$maxResults}\n\n";

    $db = Database::getInstance();
    $youtubeService = new YouTubeService();

    $stats = $youtubeService->fetchAndStoreVideos($db, $maxResults);

    echo "Operation completed!\n";
    echo "Channels processed: {$stats['channels_processed']}\n";
    echo "Videos added/updated: {$stats['videos_added']}\n";

    if (!empty($stats['errors'])) {
        echo "\nErrors:\n";
        foreach ($stats['errors'] as $error) {
            echo "  - {$error}\n";
        }
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
