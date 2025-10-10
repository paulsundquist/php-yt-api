-- Database schema for YouTube API aggregator

CREATE DATABASE IF NOT EXISTS youtube_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE youtube_api;

-- Channels table
CREATE TABLE `channels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `channel_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploads_playlist_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel_id` (`channel_id`),
  KEY `idx_channel_id` (`channel_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Videos table
CREATE TABLE `videos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `video_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `published_at` datetime NOT NULL,
  `thumbnail_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `view_count` bigint DEFAULT '0',
  `like_count` bigint DEFAULT '0',
  `comment_count` bigint DEFAULT '0',
  `duration` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `video_id` (`video_id`),
  KEY `idx_video_id` (`video_id`),
  KEY `idx_channel_id` (`channel_id`),
  KEY `idx_published_at` (`published_at`),
  CONSTRAINT `videos_ibfk_1` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`channel_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data for testing
INSERT INTO channels (channel_id, channel_name) VALUES
('UCXuqSBlHAE6Xw-yeJA0Tunw', 'Linus Tech Tips'),
('UC8butISFwT-Wl7EV0hUK0BQ', 'freeCodeCamp.org'),
('UCX6OQ3DkcsbYNE6H8uQQuVA', 'MrBeast')
ON DUPLICATE KEY UPDATE channel_name=VALUES(channel_name);
