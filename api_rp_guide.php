<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Load environment variables from .env file
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

// Database connection
$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// GET - Fetch guides or a specific guide
if ($method === 'GET') {
    if ($action === 'list') {
        // Get all guides
        $stmt = $pdo->query("
            SELECT g.*,
                   COUNT(s.step_id) as step_count
            FROM rp_guide g
            LEFT JOIN rp_guide_steps s ON g.id = s.rp_guide_id
            GROUP BY g.id
            ORDER BY g.created_at DESC
        ");
        $guides = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($guides);
    } elseif ($action === 'get' && isset($_GET['id'])) {
        // Get specific guide with steps
        $id = $_GET['id'];

        // Get guide info
        $stmt = $pdo->prepare("SELECT * FROM rp_guide WHERE id = ?");
        $stmt->execute([$id]);
        $guide = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$guide) {
            http_response_code(404);
            echo json_encode(['error' => 'Guide not found']);
            exit;
        }

        // Get guide steps
        $stmt = $pdo->prepare("
            SELECT step_id as id, step_location as location, step_comment as comment, step_order
            FROM rp_guide_steps
            WHERE rp_guide_id = ?
            ORDER BY step_order ASC, step_location ASC
        ");
        $stmt->execute([$id]);
        $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $guide['steps'] = $steps;
        echo json_encode($guide);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}

// POST - Create new guide
elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Guide name is required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Generate unique ID
        $id = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));

        // Insert guide
        $stmt = $pdo->prepare("
            INSERT INTO rp_guide (id, name, description, map_link, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $id,
            $data['name'],
            $data['description'] ?? null,
            $data['mapLink'] ?? null
        ]);

        // Insert steps
        if (isset($data['steps']) && is_array($data['steps'])) {
            $stmt = $pdo->prepare("
                INSERT INTO rp_guide_steps (rp_guide_id, step_order, step_location, step_comment)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($data['steps'] as $index => $step) {
                $stmt->execute([
                    $id,
                    $index,
                    $step['location'],
                    $step['comment']
                ]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save guide: ' . $e->getMessage()]);
    }
}

// PUT - Update existing guide
elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id']) || !isset($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Guide ID and name are required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update guide
        $stmt = $pdo->prepare("
            UPDATE rp_guide
            SET name = ?, description = ?, map_link = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['mapLink'] ?? null,
            $data['id']
        ]);

        // Delete existing steps
        $stmt = $pdo->prepare("DELETE FROM rp_guide_steps WHERE rp_guide_id = ?");
        $stmt->execute([$data['id']]);

        // Insert new steps
        if (isset($data['steps']) && is_array($data['steps'])) {
            $stmt = $pdo->prepare("
                INSERT INTO rp_guide_steps (rp_guide_id, step_order, step_location, step_comment)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($data['steps'] as $index => $step) {
                $stmt->execute([
                    $data['id'],
                    $index,
                    $step['location'],
                    $step['comment']
                ]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $data['id']]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update guide: ' . $e->getMessage()]);
    }
}

// DELETE - Delete guide
elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Guide ID is required']);
        exit;
    }

    try {
        // Steps will be deleted automatically due to CASCADE
        $stmt = $pdo->prepare("DELETE FROM rp_guide WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete guide: ' . $e->getMessage()]);
    }
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
