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

// GET - Fetch programs or a specific program
if ($method === 'GET') {
    if ($action === 'list') {
        // Get all programs
        $stmt = $pdo->query("
            SELECT p.*,
                   COUNT(s.step_id) as step_count
            FROM rp_program p
            LEFT JOIN rp_program_steps s ON p.id = s.rp_program_id
            GROUP BY p.id
            ORDER BY p.created_at DESC
        ");
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($programs);
    } elseif ($action === 'get' && isset($_GET['id'])) {
        // Get specific program with steps
        $id = $_GET['id'];

        // Get program info
        $stmt = $pdo->prepare("SELECT * FROM rp_program WHERE id = ?");
        $stmt->execute([$id]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$program) {
            http_response_code(404);
            echo json_encode(['error' => 'Program not found']);
            exit;
        }

        // Get program steps
        $stmt = $pdo->prepare("
            SELECT step_id as id, step_command as command, step_seconds as seconds, step_comment as comment, step_order
            FROM rp_program_steps
            WHERE rp_program_id = ?
            ORDER BY step_order ASC
        ");
        $stmt->execute([$id]);
        $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $program['steps'] = $steps;
        echo json_encode($program);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}

// POST - Create new program
elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Program name is required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Generate unique ID
        $id = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));

        // Insert program
        $stmt = $pdo->prepare("
            INSERT INTO rp_program (id, name, description, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $id,
            $data['name'],
            $data['description'] ?? null
        ]);

        // Insert steps
        if (isset($data['steps']) && is_array($data['steps'])) {
            $stmt = $pdo->prepare("
                INSERT INTO rp_program_steps (rp_program_id, step_order, step_command, step_seconds, step_comment)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($data['steps'] as $index => $step) {
                $stmt->execute([
                    $id,
                    $index,
                    $step['command'] ?? null,
                    $step['seconds'] ?? null,
                    $step['comment'] ?? null
                ]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save program: ' . $e->getMessage()]);
    }
}

// PUT - Update existing program
elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id']) || !isset($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Program ID and name are required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Update program
        $stmt = $pdo->prepare("
            UPDATE rp_program
            SET name = ?, description = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['id']
        ]);

        // Delete existing steps
        $stmt = $pdo->prepare("DELETE FROM rp_program_steps WHERE rp_program_id = ?");
        $stmt->execute([$data['id']]);

        // Insert new steps
        if (isset($data['steps']) && is_array($data['steps'])) {
            $stmt = $pdo->prepare("
                INSERT INTO rp_program_steps (rp_program_id, step_order, step_command, step_seconds, step_comment)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($data['steps'] as $index => $step) {
                $stmt->execute([
                    $data['id'],
                    $index,
                    $step['command'] ?? null,
                    $step['seconds'] ?? null,
                    $step['comment'] ?? null
                ]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $data['id']]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update program: ' . $e->getMessage()]);
    }
}

// DELETE - Delete program
elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Program ID is required']);
        exit;
    }

    try {
        // Steps will be deleted automatically due to CASCADE
        $stmt = $pdo->prepare("DELETE FROM rp_program WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete program: ' . $e->getMessage()]);
    }
}

else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
