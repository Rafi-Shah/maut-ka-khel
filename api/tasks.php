<?php
/**
 * Tasks API Endpoint
 * Handles fetching, adding, updating, toggling status, and deleting tasks.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../admin/auth_check.php';

$pdo = getDB();

// Parse input
$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
$input = array_merge($_GET, $_POST, $jsonInput);

$action = $input['action'] ?? ($method === 'GET' ? 'list' : '');

try {
    switch ($action) {
        case 'list':
            // Fetch all tasks with course information
            $sql = "SELECT t.*, c.name AS course_name, c.color AS course_color, c.dim_color AS course_dim_color 
                    FROM tasks t 
                    LEFT JOIN courses c ON t.course_code = c.code 
                    ORDER BY t.deadline_date ASC, t.id ASC";
            $stmt = $pdo->query($sql);
            $tasks = $stmt->fetchAll();

            // Group by date for frontend timeline
            $grouped = [];
            foreach ($tasks as $row) {
                $dateKey = $row['deadline_date'];
                if (!isset($grouped[$dateKey])) {
                    $grouped[$dateKey] = [
                        'date' => $dateKey,
                        'items' => []
                    ];
                }
                $grouped[$dateKey]['items'][] = [
                    'id' => (int)$row['id'],
                    'task_uid' => $row['task_uid'] ?: 'task-' . $row['id'],
                    'c' => $row['course_code'],
                    'course_name' => $row['course_name'] ?: $row['course_code'],
                    't' => $row['title'],
                    'date' => $row['deadline_date'],
                    'is_done' => (int)$row['is_done'] === 1,
                    'color' => $row['course_color'] ?: '#e3a13d',
                    'dim' => $row['course_dim_color'] ?: '#3a2c14'
                ];
            }

            echo json_encode([
                'success' => true,
                'total' => count($tasks),
                'tasks' => array_map(function($t) {
                    $t['id'] = (int)$t['id'];
                    $t['is_done'] = (int)$t['is_done'] === 1;
                    return $t;
                }, $tasks),
                'grouped' => array_values($grouped)
            ]);
            break;

        case 'create':
            requireAdminAuth(true);

            $title = trim($input['title'] ?? '');
            $courseCode = trim($input['course_code'] ?? '');
            $deadlineDate = trim($input['deadline_date'] ?? '');
            $isDone = !empty($input['is_done']) ? 1 : 0;

            if (empty($title) || empty($courseCode) || empty($deadlineDate)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Title, course, and deadline date are required.']);
                exit;
            }

            // Verify course exists
            $checkCourse = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE code = ?");
            $checkCourse->execute([$courseCode]);
            if ($checkCourse->fetchColumn() == 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid course selected.']);
                exit;
            }

            $taskUid = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $courseCode . '-' . substr($title, 0, 20))) . '-' . time();

            $insertStmt = $pdo->prepare("INSERT INTO tasks (task_uid, course_code, title, deadline_date, is_done) VALUES (?, ?, ?, ?, ?)");
            $insertStmt->execute([$taskUid, $courseCode, $title, $deadlineDate, $isDone]);
            $newId = (int)$pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'message' => 'Task added successfully.',
                'task' => [
                    'id' => $newId,
                    'task_uid' => $taskUid,
                    'course_code' => $courseCode,
                    'title' => $title,
                    'deadline_date' => $deadlineDate,
                    'is_done' => (bool)$isDone
                ]
            ]);
            break;

        case 'update':
            requireAdminAuth(true);

            $id = (int)($input['id'] ?? 0);
            $title = trim($input['title'] ?? '');
            $courseCode = trim($input['course_code'] ?? '');
            $deadlineDate = trim($input['deadline_date'] ?? '');
            $isDone = isset($input['is_done']) ? (int)!empty($input['is_done']) : null;

            if ($id <= 0 || empty($title) || empty($courseCode) || empty($deadlineDate)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Valid ID, title, course, and date are required.']);
                exit;
            }

            if ($isDone !== null) {
                $stmt = $pdo->prepare("UPDATE tasks SET title = ?, course_code = ?, deadline_date = ?, is_done = ? WHERE id = ?");
                $stmt->execute([$title, $courseCode, $deadlineDate, $isDone, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE tasks SET title = ?, course_code = ?, deadline_date = ? WHERE id = ?");
                $stmt->execute([$title, $courseCode, $deadlineDate, $id]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Task updated successfully.'
            ]);
            break;

        case 'toggle_done':
            // Check if public toggle is allowed or admin is logged in
            $settingStmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'allow_public_toggle'");
            $settingStmt->execute();
            $allowPublic = $settingStmt->fetchColumn();

            if ($allowPublic !== '1' && !isAdminLoggedIn()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Only admins can toggle completion status.']);
                exit;
            }

            $id = (int)($input['id'] ?? 0);
            $taskUid = trim($input['task_uid'] ?? '');

            if ($id <= 0 && empty($taskUid)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Task ID or UID required.']);
                exit;
            }

            // Find task
            if ($id > 0) {
                $findStmt = $pdo->prepare("SELECT id, is_done FROM tasks WHERE id = ?");
                $findStmt->execute([$id]);
            } else {
                $findStmt = $pdo->prepare("SELECT id, is_done FROM tasks WHERE task_uid = ?");
                $findStmt->execute([$taskUid]);
            }
            $task = $findStmt->fetch();

            if (!$task) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            $newDone = isset($input['is_done']) ? ((int)!empty($input['is_done'])) : ($task['is_done'] ? 0 : 1);

            $updateStmt = $pdo->prepare("UPDATE tasks SET is_done = ? WHERE id = ?");
            $updateStmt->execute([$newDone, $task['id']]);

            echo json_encode([
                'success' => true,
                'id' => (int)$task['id'],
                'is_done' => (bool)$newDone,
                'message' => 'Status updated.'
            ]);
            break;

        case 'delete':
            requireAdminAuth(true);

            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Valid task ID required.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Task deleted successfully.'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action.'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
