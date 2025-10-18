<?php
/**
 * Student Authentication System
 * Handles student login, session management, and authorization
 */

require_once 'config.php';

// Authentication Functions
function isStudentLoggedIn() {
    return isset($_SESSION['student_id']) && isset($_SESSION['student_username']) && !empty($_SESSION['student_id']);
}

function requireStudentAuth() {
    if (!isStudentLoggedIn()) {
        // Store the current page to redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit();
    }
}

function getCurrentStudent() {
    global $pdo;
    
    if (!isStudentLoggedIn()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, u.username, u.email as user_email, u.last_login
            FROM students s 
            LEFT JOIN users u ON s.user_id = u.id 
            WHERE s.student_id = ? AND s.is_active = 1
        ");
        $stmt->execute([$_SESSION['student_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching current student: " . $e->getMessage());
        return null;
    }
}

function authenticateStudent($username, $password) {
    global $pdo;
    
    try {
        // Check if student exists and is active
        $stmt = $pdo->prepare("
            SELECT s.*, u.username, u.email, u.password_hash, u.last_login
            FROM students s 
            LEFT JOIN users u ON s.user_id = u.id 
            WHERE (s.username = ? OR s.student_id = ? OR u.username = ?) 
            AND s.is_active = 1
        ");
        $stmt->execute([$username, $username, $username]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        
        if (!$student) {
            return ['success' => false, 'message' => 'Student not found or inactive'];
        }
        
        // Check password - handle both hashed and plain text passwords
        $password_hash = $student['password_hash'] ?? null;
        $student_password = $student['password'] ?? null;
        
        $password_valid = false;
        
        // First try hashed password from users table (newer records)
        if ($password_hash) {
            $password_valid = password_verify($password, $password_hash);
        }
        
        // If no user record or hashed password fails, try student password field
        if (!$password_valid && $student_password) {
            // Check if student password is hashed (60+ characters) or plain text
            if (strlen($student_password) > 20) {
                // It's a hash, verify it
                $password_valid = password_verify($password, $student_password);
            } else {
                // It's plain text, compare directly
                $password_valid = ($password === $student_password);
            }
        }
        
        if (!$password_valid) {
            return ['success' => false, 'message' => 'Invalid password'];
        }
        
        // Update last login (only if user_id exists)
        if ($student['user_id']) {
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$student['user_id']]);
        }
        
        // Set session variables
        $_SESSION['student_id'] = $student['student_id'];
        $_SESSION['student_username'] = $student['username'] ?: $student['student_id']; // Use student_id if username is empty
        $_SESSION['student_name'] = $student['name'];
        $_SESSION['student_email'] = $student['email'];
        $_SESSION['student_program'] = $student['program'];
        $_SESSION['student_shift'] = $student['shift'];
        $_SESSION['student_year_level'] = $student['year_level'];
        $_SESSION['student_section'] = $student['section'];
        $_SESSION['login_time'] = time();
        
        // Log successful login
        logStudentAction('LOGIN_SUCCESS', "Student logged in: {$student['student_id']}");
        
        return ['success' => true, 'student' => $student];
        
    } catch (PDOException $e) {
        error_log("Authentication error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Authentication failed. Please try again.'];
    }
}

function logoutStudent() {
    if (isStudentLoggedIn()) {
        logStudentAction('LOGOUT', "Student logged out: {$_SESSION['student_id']}");
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
}

function checkSessionTimeout() {
    if (isStudentLoggedIn() && isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > STUDENT_SESSION_TIMEOUT) {
            logoutStudent();
            return false;
        }
        return true;
    }
    return false;
}

function hasRole($role) {
    // For now, all students have the same role
    // This can be extended for different student types
    return isStudentLoggedIn();
}

function getStudentAttendanceStats($student_id) {
    global $pdo;
    
    try {
        // Get current month attendance
        $current_month = date('Y-m');
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'Check-in' THEN 1 ELSE 0 END) as checkin_days
            FROM attendance 
            WHERE student_id = ? 
            AND DATE_FORMAT(timestamp, '%Y-%m') = ?
        ");
        $stmt->execute([$student_id, $current_month]);
        $monthly_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get overall attendance percentage
        $stmt = $pdo->prepare("
            SELECT attendance_percentage 
            FROM students 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $overall_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure proper array structure with default values
        return [
            'monthly' => [
                'total_days' => (int)($monthly_stats['total_days'] ?? 0),
                'present_days' => (int)($monthly_stats['present_days'] ?? 0),
                'absent_days' => (int)($monthly_stats['absent_days'] ?? 0),
                'checkin_days' => (int)($monthly_stats['checkin_days'] ?? 0),
                'percentage' => 0
            ],
            'overall' => [
                'total_days' => 0,
                'present_days' => 0,
                'absent_days' => 0,
                'checkin_days' => 0,
                'percentage' => 0
            ],
            'overall_percentage' => (float)($overall_stats['attendance_percentage'] ?? 0)
        ];
        
    } catch (PDOException $e) {
        error_log("Error fetching attendance stats: " . $e->getMessage());
        // Return proper array structure even on error
        return [
            'monthly' => [
                'total_days' => 0,
                'present_days' => 0,
                'absent_days' => 0,
                'checkin_days' => 0,
                'percentage' => 0
            ],
            'overall' => [
                'total_days' => 0,
                'present_days' => 0,
                'absent_days' => 0,
                'checkin_days' => 0,
                'percentage' => 0
            ],
            'overall_percentage' => 0
        ];
    }
}

function getStudentRecentAttendance($student_id, $limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(timestamp) as date,
                TIME(timestamp) as time,
                status, 
                notes
            FROM attendance 
            WHERE student_id = ? 
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        $stmt->execute([$student_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching recent attendance: " . $e->getMessage());
        return [];
    }
}

function getStudentUpcomingEvents($student_id) {
    global $pdo;
    
    try {
        // Get upcoming assignments (if assignments table exists)
        $events = [];
        
        // For now, return empty array - can be extended when assignments/quizzes are implemented
        return $events;
        
    } catch (PDOException $e) {
        error_log("Error fetching upcoming events: " . $e->getMessage());
        return [];
    }
}

// Auto-check session timeout on every request
checkSessionTimeout();
?>
