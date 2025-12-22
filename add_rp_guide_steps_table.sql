-- Run Pacer Guide Steps table
CREATE TABLE `rp_guide_steps` (
  `step_id` int NOT NULL AUTO_INCREMENT,
  `rp_guide_id` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `step_order` int NOT NULL DEFAULT '0',
  `step_location` float DEFAULT NULL,
  `step_comment` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`step_id`),
  KEY `idx_rp_guide_id` (`rp_guide_id`),
  KEY `idx_step_order` (`step_order`),
  CONSTRAINT `rp_guide_steps_ibfk_1` FOREIGN KEY (`rp_guide_id`) REFERENCES `rp_guide` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
