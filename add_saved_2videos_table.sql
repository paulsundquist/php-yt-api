-- Add saved_2videos table for storing public 2videos configurations

USE youtube_api;

CREATE TABLE IF NOT EXISTS `saved_2videos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `short_id` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video1` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `video2` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `layout` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vertical',
  `volume1` int NOT NULL DEFAULT 100,
  `volume2` int NOT NULL DEFAULT 100,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `short_id` (`short_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
