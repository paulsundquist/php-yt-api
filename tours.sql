-- Database schema for YouTube API aggregator

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
