-- Movie Lists Tables
-- Run this script to deploy the movie lists feature
-- Usage: mysql -u username -p database_name < add_movie_lists_table.sql

-- Create movie_lists table
CREATE TABLE IF NOT EXISTS `movie_lists` (
  `id` int NOT NULL AUTO_INCREMENT,
  `list_id` varchar(8) NOT NULL,
  `list_name` varchar(255) NOT NULL,
  `description` text,
  `is_curated` tinyint(1) DEFAULT 0,
  `view_count` int DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `list_id` (`list_id`),
  KEY `idx_is_curated` (`is_curated`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create movie_list_items table
CREATE TABLE IF NOT EXISTS `movie_list_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `list_id` varchar(8) NOT NULL,
  `tmdb_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `poster_path` varchar(255),
  `release_year` int,
  `rating` decimal(3,1),
  `position` int NOT NULL DEFAULT 0,
  `notes` text,
  `added_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_list_id` (`list_id`),
  KEY `idx_position` (`position`),
  FOREIGN KEY (`list_id`) REFERENCES `movie_lists` (`list_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed data: Curated lists
INSERT IGNORE INTO `movie_lists` (`list_id`, `list_name`, `description`, `is_curated`) VALUES
('OSCAR001', 'Oscar Best Picture Winners', 'Academy Award winners for Best Picture throughout the years', 1),
('80SMOV01', 'Top 80s Movies', 'The most iconic and beloved films from the 1980s', 1),
('COMEDY01', 'Classic Comedies', 'Timeless comedy films that never fail to make you laugh', 1),
('SCIFI001', 'Sci-Fi Essentials', 'Must-watch science fiction films for any fan of the genre', 1),
('2026OSCR', '2026 Oscar Nominations', 'All Best Picture nominees for the 98th Academy Awards (2026)', 1);

-- Seed data: 2026 Oscar Best Picture Nominees
INSERT IGNORE INTO `movie_list_items` (`list_id`, `tmdb_id`, `title`, `poster_path`, `release_year`, `rating`, `position`) VALUES
('2026OSCR', 1233413, 'Sinners', '/705nQHqe4JGdEisrQmVYmXyjs1U.jpg', 2025, NULL, 1),
('2026OSCR', 1054867, 'One Battle After Another', '/m1jFoahEbeQXtx4zArT2FKdbNIj.jpg', 2025, NULL, 2),
('2026OSCR', 701387, 'Bugonia', '/oxgsAQDAAxA92mFGYCZllgWkH9J.jpg', 2025, NULL, 3),
('2026OSCR', 911430, 'F1', '/vqBmyAj0Xm9LnS1xe1MSlMAJyHq.jpg', 2025, NULL, 4),
('2026OSCR', 1062722, 'Frankenstein', '/g4JtvGlQO7DByTI6frUobqvSL3R.jpg', 2025, NULL, 5),
('2026OSCR', 858024, 'Hamnet', '/61xMzN4h8iLk0hq6oUzr9Ts6GE9.jpg', 2025, NULL, 6),
('2026OSCR', 1317288, 'Marty Supreme', '/firAhZA0uQvRL2slp7v3AnOj0ZX.jpg', 2025, NULL, 7),
('2026OSCR', 1220564, 'The Secret Agent', '/ovn2eHUXwEaYwB9owrg8gY4awpj.jpg', 2025, NULL, 8),
('2026OSCR', 1124566, 'Sentimental Value', '/pz9NCWxxOk3o0W3v1Zkhawrwb4i.jpg', 2025, NULL, 9),
('2026OSCR', 1241983, 'Train Dreams', '/wfzYOVdafdbD1d3SxNqiBtV2Yhx.jpg', 2025, NULL, 10);
