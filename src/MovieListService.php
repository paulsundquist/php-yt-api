<?php

namespace App;

use PDO;

class MovieListService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Create a new movie list
     *
     * @param string $name List name
     * @param string|null $description List description
     * @return string The new list ID
     */
    public function createList($name, $description = null)
    {
        $listId = Utils::generateReadableId();

        $stmt = $this->db->prepare(
            "INSERT INTO movie_lists (list_id, list_name, description)
             VALUES (:list_id, :list_name, :description)"
        );

        $stmt->execute([
            ':list_id' => $listId,
            ':list_name' => $name,
            ':description' => $description
        ]);

        return $listId;
    }

    /**
     * Get a list by ID with all its items
     *
     * @param string $listId
     * @return array|null List with items or null if not found
     */
    public function getList($listId)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM movie_lists WHERE list_id = :list_id"
        );
        $stmt->execute([':list_id' => $listId]);
        $list = $stmt->fetch();

        if (!$list) {
            return null;
        }

        $list['items'] = $this->getItems($listId);

        return $list;
    }

    /**
     * Get all items for a list
     *
     * @param string $listId
     * @return array Array of items
     */
    public function getItems($listId)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM movie_list_items
             WHERE list_id = :list_id
             ORDER BY position ASC, added_at ASC"
        );
        $stmt->execute([':list_id' => $listId]);
        return $stmt->fetchAll();
    }

    /**
     * Get all curated lists
     *
     * @return array Array of curated lists with item counts
     */
    public function getCuratedLists()
    {
        $stmt = $this->db->prepare(
            "SELECT ml.*, COUNT(mli.id) as item_count
             FROM movie_lists ml
             LEFT JOIN movie_list_items mli ON ml.list_id = mli.list_id
             WHERE ml.is_curated = 1
             GROUP BY ml.id
             ORDER BY ml.list_name ASC"
        );
        $stmt->execute();
        $lists = $stmt->fetchAll();

        // Get first 4 poster paths for each list
        foreach ($lists as &$list) {
            $list['preview_posters'] = $this->getPreviewPosters($list['list_id'], 4);
        }

        return $lists;
    }

    /**
     * Get recent public lists
     *
     * @param int $limit
     * @return array Array of recent lists
     */
    public function getRecentLists($limit = 10)
    {
        $stmt = $this->db->prepare(
            "SELECT ml.*, COUNT(mli.id) as item_count
             FROM movie_lists ml
             LEFT JOIN movie_list_items mli ON ml.list_id = mli.list_id
             WHERE ml.is_curated = 0
             GROUP BY ml.id
             ORDER BY ml.created_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $lists = $stmt->fetchAll();

        // Get first 4 poster paths for each list
        foreach ($lists as &$list) {
            $list['preview_posters'] = $this->getPreviewPosters($list['list_id'], 4);
        }

        return $lists;
    }

    /**
     * Get preview poster paths for a list
     *
     * @param string $listId
     * @param int $limit
     * @return array Array of poster paths
     */
    private function getPreviewPosters($listId, $limit = 4)
    {
        $stmt = $this->db->prepare(
            "SELECT poster_path FROM movie_list_items
             WHERE list_id = :list_id AND poster_path IS NOT NULL
             ORDER BY position ASC, added_at ASC
             LIMIT :limit"
        );
        $stmt->bindValue(':list_id', $listId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_column($stmt->fetchAll(), 'poster_path');
    }

    /**
     * Update list name and/or description
     *
     * @param string $listId
     * @param string $name
     * @param string|null $description
     * @return bool Success
     */
    public function updateList($listId, $name, $description = null)
    {
        $stmt = $this->db->prepare(
            "UPDATE movie_lists
             SET list_name = :list_name,
                 description = :description,
                 updated_at = CURRENT_TIMESTAMP
             WHERE list_id = :list_id"
        );

        return $stmt->execute([
            ':list_id' => $listId,
            ':list_name' => $name,
            ':description' => $description
        ]);
    }

    /**
     * Delete a list and all its items
     *
     * @param string $listId
     * @return bool Success
     */
    public function deleteList($listId)
    {
        $stmt = $this->db->prepare("DELETE FROM movie_lists WHERE list_id = :list_id");
        return $stmt->execute([':list_id' => $listId]);
    }

    /**
     * Add a movie to a list
     *
     * @param string $listId
     * @param array $movieData Array with keys: tmdb_id, title, poster_path, release_year, rating, notes
     * @return int The new item ID
     */
    public function addMovie($listId, $movieData)
    {
        // Get the next position
        $stmt = $this->db->prepare(
            "SELECT COALESCE(MAX(position), 0) + 1 as next_position
             FROM movie_list_items
             WHERE list_id = :list_id"
        );
        $stmt->execute([':list_id' => $listId]);
        $result = $stmt->fetch();
        $position = $result['next_position'];

        $stmt = $this->db->prepare(
            "INSERT INTO movie_list_items
             (list_id, tmdb_id, title, poster_path, release_year, rating, position, notes)
             VALUES
             (:list_id, :tmdb_id, :title, :poster_path, :release_year, :rating, :position, :notes)"
        );

        $stmt->execute([
            ':list_id' => $listId,
            ':tmdb_id' => $movieData['tmdb_id'],
            ':title' => $movieData['title'],
            ':poster_path' => $movieData['poster_path'] ?? null,
            ':release_year' => $movieData['release_year'] ?? null,
            ':rating' => $movieData['rating'] ?? null,
            ':position' => $position,
            ':notes' => $movieData['notes'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Remove a movie from a list
     *
     * @param string $listId
     * @param int $itemId
     * @return bool Success
     */
    public function removeMovie($listId, $itemId)
    {
        $stmt = $this->db->prepare(
            "DELETE FROM movie_list_items WHERE id = :item_id AND list_id = :list_id"
        );
        return $stmt->execute([
            ':item_id' => $itemId,
            ':list_id' => $listId
        ]);
    }

    /**
     * Update a movie's note
     *
     * @param string $listId
     * @param int $itemId
     * @param string $notes
     * @return bool Success
     */
    public function updateMovieNote($listId, $itemId, $notes)
    {
        $stmt = $this->db->prepare(
            "UPDATE movie_list_items SET notes = :notes WHERE id = :item_id AND list_id = :list_id"
        );
        return $stmt->execute([
            ':notes' => $notes,
            ':item_id' => $itemId,
            ':list_id' => $listId
        ]);
    }

    /**
     * Reorder movies in a list
     *
     * @param string $listId
     * @param array $positions Array of item IDs in desired order
     * @return bool Success
     */
    public function reorderMovies($listId, $positions)
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "UPDATE movie_list_items
                 SET position = :position
                 WHERE id = :item_id AND list_id = :list_id"
            );

            foreach ($positions as $index => $itemId) {
                $stmt->execute([
                    ':position' => $index + 1,
                    ':item_id' => $itemId,
                    ':list_id' => $listId
                ]);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Increment view count for a list
     *
     * @param string $listId
     * @return bool Success
     */
    public function incrementViewCount($listId)
    {
        $stmt = $this->db->prepare(
            "UPDATE movie_lists SET view_count = view_count + 1 WHERE list_id = :list_id"
        );
        return $stmt->execute([':list_id' => $listId]);
    }
}
