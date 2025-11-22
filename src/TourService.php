<?php

namespace App;

use PDO;

class TourService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all tours
     *
     * @return array Array of tours
     */
    public function getAllTours()
    {
        $stmt = $this->db->prepare(
            "SELECT tour_id, tour_name, tour_description, created_by, created_at, updated_at
             FROM tours
             ORDER BY created_at DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get a tour by ID with all its steps
     *
     * @param string $tourId
     * @return array|null Tour with steps or null if not found
     */
    public function getTourById($tourId)
    {
        // Get tour
        $stmt = $this->db->prepare(
            "SELECT * FROM tours WHERE tour_id = :tour_id"
        );
        $stmt->execute([':tour_id' => $tourId]);
        $tour = $stmt->fetch();

        if (!$tour) {
            return null;
        }

        // Get steps
        $tour['steps'] = $this->getSteps($tourId);

        return $tour;
    }

    /**
     * Create a new tour
     *
     * @param string $tourName
     * @param string|null $tourDescription
     * @param string|null $createdBy
     * @return string The new tour ID
     */
    public function createTour($tourName, $tourDescription = null, $createdBy = null)
    {
        // Generate unique 8-character ID
        $tourId = Utils::generateReadableId();

        $stmt = $this->db->prepare(
            "INSERT INTO tours (tour_id, tour_name, tour_description, created_by)
             VALUES (:tour_id, :tour_name, :tour_description, :created_by)"
        );

        $stmt->execute([
            ':tour_id' => $tourId,
            ':tour_name' => $tourName,
            ':tour_description' => $tourDescription,
            ':created_by' => $createdBy
        ]);

        return $tourId;
    }

    /**
     * Update a tour
     *
     * @param string $tourId
     * @param string $tourName
     * @param string|null $tourDescription
     * @return bool Success
     */
    public function updateTour($tourId, $tourName, $tourDescription = null)
    {
        $stmt = $this->db->prepare(
            "UPDATE tours
             SET tour_name = :tour_name,
                 tour_description = :tour_description,
                 updated_at = CURRENT_TIMESTAMP
             WHERE tour_id = :tour_id"
        );

        return $stmt->execute([
            ':tour_id' => $tourId,
            ':tour_name' => $tourName,
            ':tour_description' => $tourDescription
        ]);
    }

    /**
     * Delete a tour and all its steps
     *
     * @param string $tourId
     * @return bool Success
     */
    public function deleteTour($tourId)
    {
        $stmt = $this->db->prepare("DELETE FROM tours WHERE tour_id = :tour_id");
        return $stmt->execute([':tour_id' => $tourId]);
    }

    /**
     * Get all steps for a tour
     *
     * @param string $tourId
     * @return array Array of steps
     */
    public function getSteps($tourId)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM tour_steps
             WHERE tour_id = :tour_id
             ORDER BY step_order ASC"
        );
        $stmt->execute([':tour_id' => $tourId]);
        return $stmt->fetchAll();
    }

    /**
     * Add a step to a tour
     *
     * @param string $tourId
     * @param array $stepData Array with keys: step_name, step_comment, youtube_id, start_loc, stop_loc, step_order
     * @return int The new step ID
     */
    public function addStep($tourId, $stepData)
    {
        // If no step_order provided, get the next order number
        if (!isset($stepData['step_order'])) {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(MAX(step_order), 0) + 1 as next_order
                 FROM tour_steps
                 WHERE tour_id = :tour_id"
            );
            $stmt->execute([':tour_id' => $tourId]);
            $result = $stmt->fetch();
            $stepData['step_order'] = $result['next_order'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO tour_steps
             (tour_id, step_order, step_name, step_comment, youtube_id, start_loc, stop_loc)
             VALUES
             (:tour_id, :step_order, :step_name, :step_comment, :youtube_id, :start_loc, :stop_loc)"
        );

        $stmt->execute([
            ':tour_id' => $tourId,
            ':step_order' => $stepData['step_order'],
            ':step_name' => $stepData['step_name'],
            ':step_comment' => $stepData['step_comment'] ?? null,
            ':youtube_id' => $stepData['youtube_id'],
            ':start_loc' => $stepData['start_loc'] ?? null,
            ':stop_loc' => $stepData['stop_loc'] ?? null
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Update a step
     *
     * @param int $stepId
     * @param array $stepData Array with keys to update
     * @return bool Success
     */
    public function updateStep($stepId, $stepData)
    {
        $allowedFields = ['step_name', 'step_comment', 'youtube_id', 'start_loc', 'stop_loc', 'step_order'];
        $updateFields = [];
        $params = [':step_id' => $stepId];

        foreach ($allowedFields as $field) {
            if (isset($stepData[$field])) {
                $updateFields[] = "$field = :$field";
                $params[":$field"] = $stepData[$field];
            }
        }

        if (empty($updateFields)) {
            return false;
        }

        $sql = "UPDATE tour_steps SET " . implode(', ', $updateFields) . " WHERE step_id = :step_id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Delete a step
     *
     * @param int $stepId
     * @return bool Success
     */
    public function deleteStep($stepId)
    {
        $stmt = $this->db->prepare("DELETE FROM tour_steps WHERE step_id = :step_id");
        return $stmt->execute([':step_id' => $stepId]);
    }

    /**
     * Reorder steps for a tour
     *
     * @param string $tourId
     * @param array $stepOrder Array of step IDs in desired order
     * @return bool Success
     */
    public function reorderSteps($tourId, $stepOrder)
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "UPDATE tour_steps
                 SET step_order = :order
                 WHERE step_id = :step_id AND tour_id = :tour_id"
            );

            foreach ($stepOrder as $index => $stepId) {
                $stmt->execute([
                    ':order' => $index + 1,
                    ':step_id' => $stepId,
                    ':tour_id' => $tourId
                ]);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
}
