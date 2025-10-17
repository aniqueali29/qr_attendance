<?php
/**
 * Admin Authentication API
 * Handles admin login, logout, and session management
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Set JSON header
header('Content-Type: application/json');

// Handle CORS if needed
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

// Only allow POST requests for authentication
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get the action from POST data
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            // Validate CSRF token
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Invalid security token. Please try again.']);
                exit();
            }
            
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);
            
            if (empty($username) || empty($password)) {
                echo json_encode(['success' => false, 'message' => 'Username and password are required']);
                exit();
            }
            
            $result = $adminAuth->login($username, $password, $remember);
            echo json_encode($result);
            break;
            
        case 'logout':
            $result = $adminAuth->logout();
            echo json_encode(['success' => $result, 'message' => 'Logged out successfully']);
            break;
            
        case 'check':
            echo json_encode(['authenticated' => $adminAuth->isLoggedIn()]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
?>
