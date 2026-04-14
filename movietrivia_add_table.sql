CREATE TABLE IF NOT EXISTS movietrivia_quiz (
  id VARCHAR(8) PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  is_daily TINYINT(1) DEFAULT 0,
  daily_date DATE NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS movietrivia_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id VARCHAR(8) NOT NULL,
  movie_title VARCHAR(255) NOT NULL,
  trailer_video_id VARCHAR(50) NOT NULL,
  tmdb_id INT NULL,
  wrong_answer_1 VARCHAR(255) NULL,
  wrong_answer_2 VARCHAR(255) NULL,
  wrong_answer_3 VARCHAR(255) NULL,
  position INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (quiz_id) REFERENCES movietrivia_quiz(id) ON DELETE CASCADE
);
