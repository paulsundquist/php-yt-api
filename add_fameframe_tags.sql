-- Add a comma-separated tags column to the fameframe table
USE youtube_api;

ALTER TABLE fameframe
  ADD COLUMN tags VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER image_url;
