-- Add vote_count field to playlists table

USE youtube_api;

ALTER TABLE `playlists`
ADD COLUMN `vote_count` int NOT NULL DEFAULT 0 AFTER `last_synced_at`,
ADD KEY `idx_vote_count` (`vote_count`);
