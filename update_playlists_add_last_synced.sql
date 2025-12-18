-- Add last_synced_at field to playlists table

USE youtube_api;

ALTER TABLE `playlists`
ADD COLUMN `last_synced_at` timestamp NULL DEFAULT NULL AFTER `url`,
ADD KEY `idx_last_synced` (`last_synced_at`);
