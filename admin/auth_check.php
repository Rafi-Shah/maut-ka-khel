<?php
/**
 * Authentication and Session Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isAdminLoggedIn(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function getLoggedInAdminUsername(): string {
    return $_SESSION['admin_username'] ?? 'Admin';
}

function requireAdminAuth(bool $isApi = false): void {
    if (!isAdminLoggedIn()) {
        if ($isApi) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized. Please log in first.'
            ]);
            exit;
        } else {
            header('Location: login.php');
            exit;
        }
    }
}
