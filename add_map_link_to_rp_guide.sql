-- Add map_link column to rp_guide table
ALTER TABLE `rp_guide`
ADD COLUMN `map_link` TEXT COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `description`;
