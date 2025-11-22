-- Database schema for YouTube API aggregator

CREATE DATABASE IF NOT EXISTS youtube_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE youtube_api;

-- Channels table
CREATE TABLE `channels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `channel_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel_category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploads_playlist_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `schedule` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel_id` (`channel_id`),
  KEY `idx_channel_id` (`channel_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Genres table
CREATE TABLE `genres` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Channel_genres junction table (many-to-many relationship)
CREATE TABLE `channel_genres` (
  `channel_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `genre_id` int NOT NULL,
  PRIMARY KEY (`channel_id`, `genre_id`),
  KEY `idx_genre_id` (`genre_id`),
  CONSTRAINT `channel_genres_ibfk_1` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`channel_id`) ON DELETE CASCADE,
  CONSTRAINT `channel_genres_ibfk_2` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Groups table
CREATE TABLE `groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
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

-- Tours table
CREATE TABLE `tours` (
  `tour_id` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tour_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tour_description` text COLLATE utf8mb4_unicode_ci,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tour_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tour steps table
CREATE TABLE `tour_steps` (
  `step_id` int NOT NULL AUTO_INCREMENT,
  `tour_id` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `step_order` int NOT NULL DEFAULT '0',
  `step_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `step_comment` text COLLATE utf8mb4_unicode_ci,
  `youtube_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_loc` decimal(10,2) DEFAULT NULL,
  `stop_loc` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`step_id`),
  KEY `idx_tour_id` (`tour_id`),
  KEY `idx_step_order` (`step_order`),
  CONSTRAINT `tour_steps_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`tour_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data for testing
INSERT INTO channels (channel_id, channel_name) VALUES
('UCXuqSBlHAE6Xw-yeJA0Tunw', 'Linus Tech Tips'),
('UC8butISFwT-Wl7EV0hUK0BQ', 'freeCodeCamp.org'),
('UCX6OQ3DkcsbYNE6H8uQQuVA', 'MrBeast')
ON DUPLICATE KEY UPDATE channel_name=VALUES(channel_name);
