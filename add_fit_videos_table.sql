-- Create fit_videos table for fitness content
USE youtube_api;

CREATE TABLE IF NOT EXISTS `fit_videos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `video_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumbnail_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `view_count` bigint DEFAULT '0',
  `like_count` bigint DEFAULT '0',
  `duration` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `published_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `video_id` (`video_id`),
  KEY `idx_category` (`category`),
  KEY `idx_published_at` (`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample fitness videos
-- Lift category
INSERT INTO fit_videos (video_id, title, description, category, thumbnail_url, view_count, like_count, duration, published_at) VALUES
('test_lift_1', 'Best Exercise for Bigger Arms', 'Learn the most effective arm exercises', 'Lift', 'https://img.youtube.com/vi/test_lift_1/mqdefault.jpg', 250000, 12500, 'PT10M30S', '2025-12-20 10:00:00'),
('test_lift_2', 'Perfect Push-Up Form', 'Master the push-up technique', 'Lift', 'https://img.youtube.com/vi/test_lift_2/mqdefault.jpg', 180000, 9200, 'PT8M15S', '2025-12-19 14:30:00'),
('test_lift_3', '30 Minute Full Body Workout', 'Complete home workout routine', 'Lift', 'https://img.youtube.com/vi/test_lift_3/mqdefault.jpg', 420000, 18900, 'PT30M45S', '2025-12-18 09:00:00'),
('test_lift_4', 'Deadlift Tutorial', 'Perfect your deadlift form', 'Lift', 'https://img.youtube.com/vi/test_lift_4/mqdefault.jpg', 320000, 15400, 'PT12M20S', '2025-12-17 11:00:00');

-- Bike category
INSERT INTO fit_videos (video_id, title, description, category, thumbnail_url, view_count, like_count, duration, published_at) VALUES
('test_bike_1', 'Indoor Cycling Workout 45 Minutes', 'High intensity cycling session', 'Bike', 'https://img.youtube.com/vi/test_bike_1/mqdefault.jpg', 95000, 3200, 'PT45M00S', '2025-12-21 08:00:00'),
('test_bike_2', 'Bike Maintenance Tips', 'Keep your bike in top condition', 'Bike', 'https://img.youtube.com/vi/test_bike_2/mqdefault.jpg', 68000, 2100, 'PT12M20S', '2025-12-17 12:00:00'),
('test_bike_3', 'Cycling for Beginners', 'Start your cycling journey', 'Bike', 'https://img.youtube.com/vi/test_bike_3/mqdefault.jpg', 125000, 4800, 'PT15M40S', '2025-12-16 15:30:00'),
('test_bike_4', 'Hill Climbing Techniques', 'Master uphill cycling', 'Bike', 'https://img.youtube.com/vi/test_bike_4/mqdefault.jpg', 78000, 2950, 'PT9M55S', '2025-12-15 10:30:00');

-- Treadmill category
INSERT INTO fit_videos (video_id, title, description, category, thumbnail_url, view_count, like_count, duration, published_at) VALUES
('test_run_1', '5K Treadmill Run', 'Complete 5K running workout', 'Treadmill', 'https://img.youtube.com/vi/test_run_1/mqdefault.jpg', 82000, 2900, 'PT30M00S', '2025-12-22 07:00:00'),
('test_run_2', 'Interval Training on Treadmill', 'HIIT treadmill workout', 'Treadmill', 'https://img.youtube.com/vi/test_run_2/mqdefault.jpg', 105000, 4100, 'PT25M30S', '2025-12-19 06:30:00'),
('test_run_3', 'Marathon Training Tips', 'Prepare for your first marathon', 'Treadmill', 'https://img.youtube.com/vi/test_run_3/mqdefault.jpg', 156000, 6800, 'PT18M15S', '2025-12-15 10:00:00'),
('test_run_4', 'Running Form Basics', 'Improve your running technique', 'Treadmill', 'https://img.youtube.com/vi/test_run_4/mqdefault.jpg', 92000, 3850, 'PT11M40S', '2025-12-14 08:00:00');
