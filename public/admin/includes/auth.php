<?php
/**
 * Admin Authentication System
 * Handles admin login, session management, and authorization
 */

require_once 'config.php';

class AdminAuth {
    private $pdo;
    private $session_timeout;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->session_timeout = ADMIN_SESSION_TIMEOUT;
    }
    
    /**
     * Authenticate admin user
     */
    public function login($username, $password, $remember = false) {
        try {
            // Find admin user
            $stmt = $this->pdo->prepare("
                SELECT id, username, email, password_hash, role, is_active, last_login 
                FROM users 
                WHERE (username = ? OR email = ?) AND role = 'admin' AND is_active = 1
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Create session
            $session_id = $this->createSession($user['id']);
            
            // Set session variables
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['admin_role'] = $user['role'];
            $_SESSION['admin_session_id'] = $session_id;
            $_SESSION['admin_last_activity'] = time();
            
            // Set remember me cookie if requested
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + (30 * 24 * 60 * 60); // 30 days
                setcookie('admin_remember_token', $token, $expires, '/', '', false, true);
            }
            
            // Update last login
            $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            return [
                'success' => true, 
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }
    
    /**
     * Create admin session
     */
    private function createSession($user_id) {
        $session_id = bin2hex(random_bytes(32));
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Use existing sessions table
        $stmt = $this->pdo->prepare("
            INSERT INTO sessions (id, user_id, ip_address, user_agent, last_activity) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$session_id, $user_id, $ip_address, $user_agent]);
        
        return $session_id;
    }
    
    /**
     * Check if admin is logged in
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['admin_last_activity'])) {
            if (time() - $_SESSION['admin_last_activity'] > $this->session_timeout) {
                $this->logout();
                return false;
            }
            $_SESSION['admin_last_activity'] = time();
        }
        
        // Verify session in database
        if (isset($_SESSION['admin_session_id'])) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT s.id, s.last_activity, u.is_active 
                    FROM sessions s 
                    JOIN users u ON s.user_id = u.id 
                    WHERE s.id = ? AND s.user_id = ? AND u.is_active = 1
                ");
                $stmt->execute([$_SESSION['admin_session_id'], $_SESSION['admin_user_id']]);
                $session = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$session) {
                    $this->logout();
                    return false;
                }
                
                // Update last activity
                $stmt = $this->pdo->prepare("UPDATE sessions SET last_activity = NOW() WHERE id = ?");
                $stmt->execute([$_SESSION['admin_session_id']]);
                
            } catch (Exception $e) {
                $this->logout();
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get current admin user
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['admin_user_id'],
            'username' => $_SESSION['admin_username'],
            'email' => $_SESSION['admin_email'],
            'role' => $_SESSION['admin_role']
        ];
    }
    
    /**
     * Logout admin
     */
    public function logout() {
        if (isset($_SESSION['admin_session_id'])) {
            try {
                // Remove session from database
                $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
                $stmt->execute([$_SESSION['admin_session_id']]);
            } catch (Exception $e) {
                // Log error but continue with logout
            }
        }
        
        // Clear remember me cookie
        if (isset($_COOKIE['admin_remember_token'])) {
            setcookie('admin_remember_token', '', time() - 3600, '/', '', false, true);
        }
        
        // Destroy session
        session_destroy();
        
        return true;
    }
    
    /**
     * Check remember me token
     */
    public function checkRememberToken() {
        if (isset($_COOKIE['admin_remember_token'])) {
            try {
                $token = $_COOKIE['admin_remember_token'];
                // For now, just clear the cookie since we don't have remember tokens table
                setcookie('admin_remember_token', '', time() - 3600, '/', '', false, true);
            } catch (Exception $e) {
                // Clear invalid token
                setcookie('admin_remember_token', '', time() - 3600, '/', '', false, true);
            }
        }
        
        return false;
    }
}

// Initialize auth system
$adminAuth = new AdminAuth($pdo);

// Check remember me token if not logged in
if (!$adminAuth->isLoggedIn()) {
    $adminAuth->checkRememberToken();
}

// Helper functions
function requireAdminAuth() {
    global $adminAuth;
    
    if (!$adminAuth->isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function isAdminLoggedIn() {
    global $adminAuth;
    return $adminAuth->isLoggedIn();
}

function getAdminUser() {
    global $adminAuth;
    return $adminAuth->getCurrentUser();
}

function adminLogout() {
    global $adminAuth;
    return $adminAuth->logout();
}

// Handle API requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'login':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']);
            
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
    exit();
}
?>
