<?php
/**
 * Courses API Endpoint
 * Handles listing, adding, updating, and deleting course tags/categories.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../admin/auth_check.php';

$pdo = getDB();

$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
$input = array_merge($_GET, $_POST, $jsonInput);

$action = $input['action'] ?? ($method === 'GET' ? 'list' : '');

function adjustColorDim(string $hex, float $percent = 0.25): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    if (strlen($hex) !== 6) {
        return '#252525';
    }
    $r = round(hexdec(substr($hex, 0, 2)) * $percent);
    $g = round(hexdec(substr($hex, 2, 2)) * $percent);
    $b = round(hexdec(substr($hex, 4, 2)) * $percent);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("SELECT c.*, COUNT(t.id) AS task_count 
                                FROM courses c 
                                LEFT JOIN tasks t ON c.code = t.course_code 
                                GROUP BY c.id 
                                ORDER BY c.sort_order ASC, c.id ASC");
            $courses = $stmt->fetchAll();

            $map = [];
            foreach ($courses as $c) {
                $c['id'] = (int)$c['id'];
                $c['task_count'] = (int)$c['task_count'];
                $map[$c['code']] = [
                    'id' => $c['id'],
                    'code' => $c['code'],
                    'label' => $c['name'],
                    'name' => $c['name'],
                    'color' => $c['color'],
                    'dim' => $c['dim_color'] ?: adjustColorDim($c['color']),
                    'task_count' => $c['task_count']
                ];
            }

            echo json_encode([
                'success' => true,
                'courses' => $courses,
                'map' => $map
            ]);
            break;

        case 'create':
            requireAdminAuth(true);

            $code = strtolower(trim($input['code'] ?? ''));
            $name = trim($input['name'] ?? '');
            $color = trim($input['color'] ?? '#e3a13d');
            $dimColor = trim($input['dim_color'] ?? '');

            if (empty($code) || empty($name)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Course code and name are required.']);
                exit;
            }

            // Clean code to letters, digits, and underscores
            $code = preg_replace('/[^a-z0-9_-]/', '', $code);

            // Check uniqueness
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE code = ?");
            $checkStmt->execute([$code]);
            if ($checkStmt->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Course code '{$code}' already exists."]);
                exit;
            }

            if (empty($dimColor)) {
                $dimColor = adjustColorDim($color);
            }

            $maxOrder = (int)$pdo->query("SELECT MAX(sort_order) FROM courses")->fetchColumn();

            $stmt = $pdo->prepare("INSERT INTO courses (code, name, color, dim_color, sort_order) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$code, $name, $color, $dimColor, $maxOrder + 1]);

            echo json_encode([
                'success' => true,
                'message' => 'Course created successfully.',
                'course' => [
                    'id' => (int)$pdo->lastInsertId(),
                    'code' => $code,
                    'name' => $name,
                    'color' => $color,
                    'dim_color' => $dimColor
                ]
            ]);
            break;

        case 'update':
            requireAdminAuth(true);

            $id = (int)($input['id'] ?? 0);
            $newCode = strtolower(trim($input['code'] ?? ''));
            $name = trim($input['name'] ?? '');
            $color = trim($input['color'] ?? '');
            $dimColor = trim($input['dim_color'] ?? '');

            if ($id <= 0 || empty($newCode) || empty($name) || empty($color)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Valid ID, code, name, and color are required.']);
                exit;
            }

            $newCode = preg_replace('/[^a-z0-9_-]/', '', $newCode);

            // Fetch old course
            $getStmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
            $getStmt->execute([$id]);
            $oldCourse = $getStmt->fetch();
            if (!$oldCourse) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Course not found.']);
                exit;
            }

            // Check code collision if code changed
            if ($newCode !== $oldCourse['code']) {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE code = ? AND id != ?");
                $checkStmt->execute([$newCode, $id]);
                if ($checkStmt->fetchColumn() > 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Course code '{$newCode}' already in use."]);
                    exit;
                }
            }

            if (empty($dimColor)) {
                $dimColor = adjustColorDim($color);
            }

            // Update course
            $stmt = $pdo->prepare("UPDATE courses SET code = ?, name = ?, color = ?, dim_color = ? WHERE id = ?");
            $stmt->execute([$newCode, $name, $color, $dimColor, $id]);

            // If code changed, cascade update to tasks table
            if ($newCode !== $oldCourse['code']) {
                $upTasks = $pdo->prepare("UPDATE tasks SET course_code = ? WHERE course_code = ?");
                $upTasks->execute([$newCode, $oldCourse['code']]);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Course updated successfully.'
            ]);
            break;

        case 'delete':
            requireAdminAuth(true);

            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Valid course ID required.']);
                exit;
            }

            $getStmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
            $getStmt->execute([$id]);
            $course = $getStmt->fetch();
            if (!$course) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Course not found.']);
                exit;
            }

            // Delete associated tasks or prevent deletion
            $tasksCountStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE course_code = ?");
            $tasksCountStmt->execute([$course['code']]);
            $taskCount = (int)$tasksCountStmt->fetchColumn();

            $force = !empty($input['force']);
            if ($taskCount > 0 && !$force) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "This course has {$taskCount} active task(s). Confirm delete with force=true to delete associated tasks too."
                ]);
                exit;
            }

            if ($taskCount > 0 && $force) {
                $delTasks = $pdo->prepare("DELETE FROM tasks WHERE course_code = ?");
                $delTasks->execute([$course['code']]);
            }

            $delStmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $delStmt->execute([$id]);

            echo json_encode([
                'success' => true,
                'message' => 'Course deleted successfully.'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
