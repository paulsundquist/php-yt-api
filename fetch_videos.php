<?php

/**
 * CLI script to fetch YouTube videos
 * Usage: php fetch_videos.php [max_results_per_channel] [schedule=hourly|daily|weekly]
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
    $maxResults = 10;
    $schedule = 'weekly'; // Default to weekly

    // Parse command line arguments
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];

        // Check if it's a schedule parameter
        if (strpos($arg, 'schedule=') === 0) {
            $schedule = substr($arg, 9); // Extract value after "schedule="

            // Validate schedule value
            if (!in_array($schedule, ['hourly', 'daily', 'weekly'])) {
                echo "Error: Invalid schedule value. Must be 'hourly', 'daily', or 'weekly'\n";
                exit(1);
            }
        } else {
            // Assume it's max_results if it's a number
            if (is_numeric($arg)) {
                $maxResults = (int)$arg;
            }
        }
    }

    echo "Fetching videos from YouTube...\n";
    echo "Schedule filter: {$schedule}\n";
    echo "Max results per channel: {$maxResults}\n\n";

    $db = Database::getInstance();
    $youtubeService = new YouTubeService();

    // Get channels filtered by schedule
    $channels = $db->getActiveChannelsBySchedule($schedule);

    if (empty($channels)) {
        echo "No active channels found with schedule: {$schedule}\n";
        exit(0);
    }

    echo "Found " . count($channels) . " channel(s) with '{$schedule}' schedule\n\n";

    $stats = $youtubeService->fetchAndStoreVideosForChannels($db, $channels, $maxResults);

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
