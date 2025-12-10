<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: 'youtube_api';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') ?: '';

        try {
            $this->connection = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function getActiveChannels()
    {
        $stmt = $this->connection->prepare(
            "SELECT id, channel_id, channel_name, channel_category, schedule, uploads_playlist_id, updated_at FROM channels WHERE is_active = 1"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getActiveChannelsBySchedule($schedule)
    {
        $stmt = $this->connection->prepare(
            "SELECT id, channel_id, channel_name, channel_category, schedule, uploads_playlist_id, updated_at FROM channels WHERE is_active = 1 AND schedule = :schedule"
        );
        $stmt->execute([':schedule' => $schedule]);
        return $stmt->fetchAll();
    }

    public function addChannel($channelId, $channelName, $channelCategory = null, $schedule = null)
    {
        $stmt = $this->connection->prepare(
            "INSERT INTO channels (channel_id, channel_name, channel_category, schedule, is_active)
             VALUES (:channel_id, :channel_name, :channel_category, :schedule, 1)
             ON DUPLICATE KEY UPDATE
             channel_name = VALUES(channel_name),
             channel_category = VALUES(channel_category),
             schedule = VALUES(schedule),
             is_active = 1"
        );
        return $stmt->execute([
            ':channel_id' => $channelId,
            ':channel_name' => $channelName,
            ':channel_category' => $channelCategory,
            ':schedule' => $schedule
        ]);
    }

    public function updateChannelPlaylistId($channelId, $playlistId)
    {
        $stmt = $this->connection->prepare(
            "UPDATE channels SET uploads_playlist_id = :playlist_id WHERE channel_id = :channel_id"
        );
        return $stmt->execute([
            ':playlist_id' => $playlistId,
            ':channel_id' => $channelId
        ]);
    }

    public function insertOrUpdateVideo($videoData)
    {
        $sql = "INSERT INTO videos
                (video_id, channel_id, title, description, published_at, thumbnail_url,
                 view_count, like_count, comment_count, duration)
                VALUES
                (:video_id, :channel_id, :title, :description, :published_at, :thumbnail_url,
                 :view_count, :like_count, :comment_count, :duration)
                ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                description = VALUES(description),
                view_count = VALUES(view_count),
                like_count = VALUES(like_count),
                comment_count = VALUES(comment_count),
                thumbnail_url = VALUES(thumbnail_url),
                duration = VALUES(duration)";

        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($videoData);
    }

    public function getVideos($limit = 50, $offset = 0, $channelId = null)
    {
        $sql = "SELECT v.*, c.channel_name
                FROM videos v
                JOIN channels c ON v.channel_id = c.channel_id";

        if ($channelId) {
            $sql .= " WHERE v.channel_id = :channel_id";
        }

        $sql .= " ORDER BY v.published_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->connection->prepare($sql);

        if ($channelId) {
            $stmt->bindValue(':channel_id', $channelId, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getFeatureVotes()
    {
        $stmt = $this->connection->prepare(
            "SELECT feature_id, vote_count FROM feature_votes ORDER BY vote_count DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function addFeatureVote($featureId)
    {
        $stmt = $this->connection->prepare(
            "INSERT INTO feature_votes (feature_id, vote_count) VALUES (:feature_id, 1)
             ON DUPLICATE KEY UPDATE vote_count = vote_count + 1"
        );
        return $stmt->execute([':feature_id' => $featureId]);
    }

    public function removeFeatureVote($featureId)
    {
        $stmt = $this->connection->prepare(
            "UPDATE feature_votes SET vote_count = GREATEST(vote_count - 1, 0) WHERE feature_id = :feature_id"
        );
        return $stmt->execute([':feature_id' => $featureId]);
    }

    public function saveChain($chainId, $chainData, $chainName = null)
    {
        $stmt = $this->connection->prepare(
            "INSERT INTO chains (chain_id, chain_name, chain_data) VALUES (:chain_id, :chain_name, :chain_data)
             ON DUPLICATE KEY UPDATE chain_name = VALUES(chain_name), chain_data = VALUES(chain_data)"
        );
        return $stmt->execute([
            ':chain_id' => $chainId,
            ':chain_name' => $chainName,
            ':chain_data' => json_encode($chainData)
        ]);
    }

    public function getChain($chainId)
    {
        $stmt = $this->connection->prepare(
            "SELECT chain_id, chain_name, chain_data, created_at FROM chains WHERE chain_id = :chain_id"
        );
        $stmt->execute([':chain_id' => $chainId]);
        $result = $stmt->fetch();

        if ($result) {
            $result['chain_data'] = json_decode($result['chain_data'], true);
        }

        return $result;
    }

    public function getAllChains()
    {
        $stmt = $this->connection->prepare(
            "SELECT chain_id, chain_name, chain_data, created_at FROM chains ORDER BY created_at DESC"
        );
        $stmt->execute();
        $results = $stmt->fetchAll();

        foreach ($results as &$result) {
            $result['chain_data'] = json_decode($result['chain_data'], true);
        }

        return $results;
    }

    public function save2VideosConfig($shortId, $name, $video1, $video2, $layout, $volume1, $volume2)
    {
        $stmt = $this->connection->prepare(
            "INSERT INTO saved_2videos (short_id, name, video1, video2, layout, volume1, volume2)
             VALUES (:short_id, :name, :video1, :video2, :layout, :volume1, :volume2)"
        );
        return $stmt->execute([
            ':short_id' => $shortId,
            ':name' => $name,
            ':video1' => $video1,
            ':video2' => $video2,
            ':layout' => $layout,
            ':volume1' => $volume1,
            ':volume2' => $volume2
        ]);
    }

    public function get2VideosConfig($shortId)
    {
        $stmt = $this->connection->prepare(
            "SELECT short_id, name, video1, video2, layout, volume1, volume2, created_at
             FROM saved_2videos WHERE short_id = :short_id"
        );
        $stmt->execute([':short_id' => $shortId]);
        return $stmt->fetch();
    }
}
