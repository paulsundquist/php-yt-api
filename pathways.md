# PHP YouTube API - Channel Video Aggregator Tour.html

a Tour.html page that guides users through multiple youtube pages

## Features

- a Tour_editor.html page that allows users to create a new tour
- store 
- RESTful API endpoints to retrieve videos
- Support for pagination and filtering by channel
- CLI script for scheduled video fetching
- Auto-update video statistics

# Database Structure

### Tour table
- tour_id: Primary key - unique random 8 character alphanumeric
- tour_name : tour name
- tour_description
- created_at,updated_at: Timestamp
- created_by: username

### Tour table
- step_id: Primary key
- tour_id: related to Tour table
- step_name: display name
- step_comment: display comment
- youtube_id: youtube video id
- start_loc - float can be null
- stop_loc - float can be null

## Tour.html page UI
-allow the user to see a listing of all tours, showing tour_name, tour_description, created_at 
-an Add button allows the user to create a new tour with Tour_editor.html, with a new unique tour_id

## Tour_editor.html
- allow user to specify tour_name, tour_description
- there is a youtube frame at the top
- the user will have a table of steps for the tour, each step:
  - step_name, comment, youtube_id, start_loc in seconds, stop_loc in seconds
  - each step can be edited in place
  - a trash icon to delete the step
  - a play icon to play all steps starting with the current step
  - a plus icon to add a row below the current step


-  
