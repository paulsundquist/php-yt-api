# PHP YouTube API - Channel Video Aggregator

A PHP API that queries the YouTube API for recent videos from multiple channels and stores them in a MySQL database.

## Features

- Fetch videos from multiple YouTube channels
- Store video data in MySQL database
- RESTful API endpoints to retrieve videos
- Support for pagination and filtering by channel
- CLI script for scheduled video fetching
- Auto-update video statistics

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer
- YouTube Data API v3 key

## Installation

1. **Clone or download the project**

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment variables**
   ```bash
   cp .env.example .env
   ```

   Edit `.env` and add your credentials:
   ```
   DB_HOST=localhost
   DB_NAME=youtube_api
   DB_USER=root
   DB_PASS=your_password

   YOUTUBE_API_KEY=your_youtube_api_key_here
   ```

4. **Set up the database**
   ```bash
   mysql -u root -p < schema.sql
   ```

5. **Add channels to the database**
   ```sql
   INSERT INTO channels (channel_id, channel_name) VALUES
   ('UCXuqSBlHAE6Xw-yeJA0Tunw', 'Linus Tech Tips'),
   ('UC8butISFwT-Wl7EV0hUK0BQ', 'freeCodeCamp.org');
   ```

## Usage

### Web API

Start a PHP development server:
```bash
php -S localhost:8000 -t public
```

### API Endpoints

#### Get All Videos
```bash
GET http://localhost:8000/videos
GET http://localhost:8000/videos?limit=20&offset=0
```

#### Get Videos from Specific Channel
```bash
GET http://localhost:8000/videos?channel_id=UCXuqSBlHAE6Xw-yeJA0Tunw
```

#### Get All Active Channels
```bash
GET http://localhost:8000/channels
```

#### Fetch Latest Videos from YouTube
```bash
POST http://localhost:8000/fetch
POST http://localhost:8000/fetch (with form data: max_results=20)
```

### CLI Script

Fetch videos from all active channels:
```bash
php fetch_videos.php
php fetch_videos.php 20  # Fetch 20 videos per channel
```

You can add this to a cron job to run periodically:
```bash
# Run every hour
0 * * * * cd /path/to/php-yt-api && php fetch_videos.php >> logs/fetch.log 2>&1
```

## Database Schema

### channels table
- `id`: Primary key
- `channel_id`: YouTube channel ID (unique)
- `channel_name`: Channel display name
- `is_active`: Enable/disable channel (1/0)
- `created_at`, `updated_at`: Timestamps

### videos table
- `id`: Primary key
- `video_id`: YouTube video ID (unique)
- `channel_id`: Foreign key to channels
- `title`: Video title
- `description`: Video description
- `published_at`: Publication date
- `thumbnail_url`: Thumbnail image URL
- `view_count`, `like_count`, `comment_count`: Statistics
- `duration`: Video duration in ISO 8601 format
- `created_at`, `updated_at`: Timestamps

## Getting a YouTube API Key

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing one
3. Enable "YouTube Data API v3"
4. Create credentials (API Key)
5. Copy the API key to your `.env` file

## Example Response

```json
{
  "success": true,
  "count": 2,
  "videos": [
    {
      "id": 1,
      "video_id": "dQw4w9WgXcQ",
      "channel_id": "UCXuqSBlHAE6Xw-yeJA0Tunw",
      "channel_name": "Linus Tech Tips",
      "title": "Video Title",
      "description": "Video description...",
      "published_at": "2025-10-06 12:00:00",
      "thumbnail_url": "https://i.ytimg.com/vi/...",
      "view_count": 1000000,
      "like_count": 50000,
      "comment_count": 1000,
      "duration": "PT10M30S"
    }
  ]
}
```

## License

MIT
