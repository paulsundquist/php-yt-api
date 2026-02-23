<?php

/**
 * CLI script to fetch YouTube fitness videos
 * Usage: php fetch_fit_videos.php [max_results_per_channel] [schedule=hourly|daily|weekly] [channel_id=CHANNEL_ID]
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
    $channelId = null;

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
        } elseif (strpos($arg, 'channel_id=') === 0) {
            $channelId = substr($arg, 11); // Extract value after "channel_id="
        } else {
            // Assume it's max_results if it's a number
            if (is_numeric($arg)) {
                $maxResults = (int)$arg;
            }
        }
    }

    echo "Fetching fitness videos from YouTube...\n";
    if ($channelId) {
        echo "Channel ID filter: {$channelId}\n";
    } else {
        echo "Schedule filter: {$schedule}\n";
    }
    echo "Max results per channel: {$maxResults}\n\n";

    $db = Database::getInstance();
    $youtubeService = new YouTubeService();

    // Get fitness channels - either specific channel or by schedule
    if ($channelId) {
        // Fetch specific fitness channel
        $stmt = $db->getConnection()->prepare(
            "SELECT id, channel_id, channel_name, channel_category, schedule, uploads_playlist_id, updated_at
             FROM fit_channels WHERE is_active = 1 AND channel_id = :channel_id"
        );
        $stmt->execute([':channel_id' => $channelId]);
        $channels = $stmt->fetchAll();

        if (empty($channels)) {
            echo "No active fitness channel found with ID: {$channelId}\n";
            exit(0);
        }
        echo "Found fitness channel: {$channels[0]['channel_name']} ({$channels[0]['channel_category']})\n\n";
    } else {
        // Get fitness channels filtered by schedule
        $stmt = $db->getConnection()->prepare(
            "SELECT id, channel_id, channel_name, channel_category, schedule, uploads_playlist_id, updated_at
             FROM fit_channels WHERE is_active = 1 AND schedule = :schedule"
        );
        $stmt->execute([':schedule' => $schedule]);
        $channels = $stmt->fetchAll();

        if (empty($channels)) {
            echo "No active fitness channels found with schedule: {$schedule}\n";
            exit(0);
        }
        echo "Found " . count($channels) . " fitness channel(s) with '{$schedule}' schedule\n\n";
    }

    // Process each fitness channel
    $channelsProcessed = 0;
    $videosAdded = 0;
    $errors = [];

    foreach ($channels as $channel) {
        try {
            echo "Processing: {$channel['channel_name']} ({$channel['channel_category']})...\n";

            $channelId = $channel['channel_id'];
            $category = $channel['channel_category'];

            // Get uploads playlist ID if not stored
            $playlistId = $channel['uploads_playlist_id'];
            if (!$playlistId) {
                echo "  Fetching uploads playlist ID...\n";
                $playlistId = $youtubeService->getUploadsPlaylistId($channelId);

                if ($playlistId) {
                    // Update fit_channels table with playlist ID
                    $updateStmt = $db->getConnection()->prepare(
                        "UPDATE fit_channels SET uploads_playlist_id = :playlist_id WHERE channel_id = :channel_id"
                    );
                    $updateStmt->execute([
                        ':playlist_id' => $playlistId,
                        ':channel_id' => $channelId
                    ]);
                    echo "  Playlist ID saved: {$playlistId}\n";
                } else {
                    throw new \Exception("Could not get uploads playlist ID");
                }
            }

            // Fetch videos from YouTube
            echo "  Fetching videos...\n";
            $videos = $youtubeService->getChannelVideos($channelId, $playlistId, $maxResults);

            // Store videos in fit_videos table
            foreach ($videos as $videoData) {
                $insertStmt = $db->getConnection()->prepare(
                    "INSERT INTO fit_videos
                    (video_id, title, description, category, thumbnail_url, view_count, like_count, duration, published_at)
                    VALUES
                    (:video_id, :title, :description, :category, :thumbnail_url, :view_count, :like_count, :duration, :published_at)
                    ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    description = VALUES(description),
                    view_count = VALUES(view_count),
                    like_count = VALUES(like_count),
                    thumbnail_url = VALUES(thumbnail_url),
                    duration = VALUES(duration)"
                );

                $insertStmt->execute([
                    ':video_id' => $videoData['video_id'],
                    ':title' => $videoData['title'],
                    ':description' => $videoData['description'],
                    ':category' => $category,
                    ':thumbnail_url' => $videoData['thumbnail_url'],
                    ':view_count' => $videoData['view_count'],
                    ':like_count' => $videoData['like_count'],
                    ':duration' => $videoData['duration'],
                    ':published_at' => $videoData['published_at']
                ]);

                $videosAdded++;
            }

            echo "  Added/updated " . count($videos) . " videos\n";
            $channelsProcessed++;

        } catch (\Exception $e) {
            $error = "Failed to fetch videos for channel {$channelId}: " . $e->getMessage();
            $errors[] = $error;
            echo "  ERROR: {$error}\n";
        }

        echo "\n";
    }

    echo "Operation completed!\n";
    echo "Fitness channels processed: {$channelsProcessed}\n";
    echo "Fitness videos added/updated: {$videosAdded}\n";

    if (!empty($errors)) {
        echo "\nErrors:\n";
        foreach ($errors as $error) {
            echo "  - {$error}\n";
        }
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
