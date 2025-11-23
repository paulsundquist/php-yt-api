-- Add feature votes table
CREATE TABLE IF NOT EXISTS `feature_votes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `feature_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vote_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `feature_id` (`feature_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialize feature votes
INSERT INTO feature_votes (feature_id, vote_count) VALUES
('tours', 0),
('shorts', 0),
('trailers', 0),
('popup-comments', 0),
('7-degrees', 0)
ON DUPLICATE KEY UPDATE feature_id=VALUES(feature_id);
