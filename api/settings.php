<?php
/**
 * Settings & System API Endpoint
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../admin/auth_check.php';

$pdo = getDB();

$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true) ?? [];
$input = array_merge($_GET, $_POST, $jsonInput);

$action = $input['action'] ?? ($method === 'GET' ? 'get' : '');

try {
    switch ($action) {
        case 'get':
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $rows = $stmt->fetchAll();
            $settings = [];
            foreach ($rows as $r) {
                $settings[$r['setting_key']] = $r['setting_value'];
            }

            echo json_encode([
                'success' => true,
                'settings' => $settings
            ]);
            break;

        case 'update':
            requireAdminAuth(true);

            $allowedKeys = ['eyebrow', 'hero_title', 'hero_subtitle', 'allow_public_toggle'];
            $updated = [];

            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

            foreach ($allowedKeys as $key) {
                if (isset($input[$key])) {
                    $val = (string)$input[$key];
                    $stmt->execute([$key, $val]);
                    $updated[$key] = $val;
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Settings updated successfully.',
                'updated' => $updated
            ]);
            break;

        case 'update_credentials':
            requireAdminAuth(true);

            $currentPassword = (string)($input['current_password'] ?? '');
            $newUsername = trim((string)($input['new_username'] ?? ''));
            $newPassword = (string)($input['new_password'] ?? '');

            if (empty($currentPassword) || empty($newUsername)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Current password and new username are required.']);
                exit;
            }

            $currentLoggedInUser = getLoggedInAdminUsername();
            $adminStmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $adminStmt->execute([$currentLoggedInUser]);
            $admin = $adminStmt->fetch();

            if (!$admin || !password_verify($currentPassword, $admin['password'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
                exit;
            }

            // Check if username changed and collides
            if ($newUsername !== $currentLoggedInUser) {
                $checkName = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ? AND id != ?");
                $checkName->execute([$newUsername, $admin['id']]);
                if ($checkName->fetchColumn() > 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Username already taken by another account.']);
                    exit;
                }
            }

            if (!empty($newPassword)) {
                if (strlen($newPassword) < 5) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'New password must be at least 5 characters long.']);
                    exit;
                }
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upStmt = $pdo->prepare("UPDATE admins SET username = ?, password = ? WHERE id = ?");
                $upStmt->execute([$newUsername, $newHash, $admin['id']]);
            } else {
                $upStmt = $pdo->prepare("UPDATE admins SET username = ? WHERE id = ?");
                $upStmt->execute([$newUsername, $admin['id']]);
            }

            $_SESSION['admin_username'] = $newUsername;

            echo json_encode([
                'success' => true,
                'message' => 'Admin profile updated successfully!'
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
