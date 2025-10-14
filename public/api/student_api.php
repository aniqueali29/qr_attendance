<?php
/**
 * Student API for QR Code Attendance System
 * Handles student-specific operations and data retrieval
 */

require_once 'config.php';
require_once 'auth_system.php';

// Check authentication
if (!isLoggedIn() || !hasRole('student')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$current_user = getCurrentUser();

// Handle API requests
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch ($action) {
        case 'generate_qr':
            $response = generateStudentQR($current_user['student_id']);
            break;
            
        case 'update_profile':
            $response = updateStudentProfile($current_user['id']);
            break;
            
        case 'change_password':
            $response = changeStudentPassword($current_user['id']);
            break;
    }
    
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch ($action) {
        case 'get_qr':
            $student_id = $_GET['student_id'] ?? $current_user['student_id'];
            $response = getStudentQR($student_id);
            break;
            
        case 'get_stats':
            $student_id = $_GET['student_id'] ?? $current_user['student_id'];
            $response = getStudentStats($student_id);
            break;
            
        case 'get_history':
            $student_id = $_GET['student_id'] ?? $current_user['student_id'];
            $response = getStudentHistory($student_id);
            break;
            
        case 'get_profile':
            $response = getStudentProfile($current_user['id']);
            break;
    }
    
    echo json_encode($response);
    exit;
}

// Default response
echo json_encode(['success' => false, 'message' => 'Invalid request method']);

/**
 * Generate QR code for student
 */
function generateStudentQR($student_id) {
    global $pdo, $current_user;
    
    try {
        // Check if student exists and is active
        $stmt = $pdo->prepare("
            SELECT s.student_id, s.name, s.email 
            FROM students s 
            WHERE s.student_id = ? AND s.is_active = 1
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            return ['success' => false, 'message' => 'Student not found'];
        }
        
        // Create QR data
        $qr_data = json_encode([
            'student_id' => $student['student_id'],
            'name' => $student['name'],
            'timestamp' => time(),
            'type' => 'attendance'
        ]);
        
        // Generate QR code (simplified)
        $filename = 'qr_' . $student_id . '_' . time() . '.png';
        $filepath = QR_CODE_PATH . $filename;
        
        // Create directory if it doesn't exist
        if (!is_dir(QR_CODE_PATH)) {
            mkdir(QR_CODE_PATH, 0755, true);
        }
        
        // Create a simple QR code image (in production, use a proper QR library)
        $image = imagecreate(200, 200);
        $bg_color = imagecolorallocate($image, 255, 255, 255);
        $text_color = imagecolorallocate($image, 0, 0, 0);
        
        imagefill($image, 0, 0, $bg_color);
        imagestring($image, 5, 10, 90, 'QR: ' . $student_id, $text_color);
        
        imagepng($image, $filepath);
        imagedestroy($image);
        
        // Deactivate old QR codes
        $stmt = $pdo->prepare("UPDATE qr_codes SET is_active = 0 WHERE student_id = ?");
        $stmt->execute([$student_id]);
        
        // Store QR code in database
        $stmt = $pdo->prepare("
            INSERT INTO qr_codes (student_id, qr_data, qr_image_path, created_by) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$student_id, $qr_data, $filepath, $current_user['id']]);
        
        return [
            'success' => true,
            'message' => 'QR code generated successfully',
            'data' => [
                'student_id' => $student_id,
                'qr_data' => $qr_data,
                'image_path' => $filepath,
                'image_url' => QR_CODE_URL . $filename
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'QR generation failed: ' . $e->getMessage()];
    }
}

/**
 * Get student QR code
 */
function getStudentQR($student_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT qr_data, qr_image_path, generated_at 
            FROM qr_codes 
            WHERE student_id = ? AND is_active = 1 
            ORDER BY generated_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $qr = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$qr) {
            return ['success' => false, 'message' => 'No active QR code found'];
        }
        
        return [
            'success' => true,
            'data' => [
                'student_id' => $student_id,
                'qr_data' => $qr['qr_data'],
                'image_path' => $qr['qr_image_path'],
                'image_url' => QR_CODE_URL . basename($qr['qr_image_path']),
                'generated_at' => $qr['generated_at']
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get QR code: ' . $e->getMessage()];
    }
}

/**
 * Get student attendance statistics
 */
function getStudentStats($student_id) {
    global $pdo;
    
    try {
        // Get total attendance records
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days
            FROM attendance 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate percentage
        $percentage = 0;
        if ($stats['total_days'] > 0) {
            $percentage = round(($stats['present_days'] / $stats['total_days']) * 100, 1);
        }
        
        // Get this month's stats
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as this_month_total,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as this_month_present
            FROM attendance 
            WHERE student_id = ? AND MONTH(timestamp) = MONTH(CURDATE()) AND YEAR(timestamp) = YEAR(CURDATE())
        ");
        $stmt->execute([$student_id]);
        $month_stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => [
                'total_days' => (int)$stats['total_days'],
                'present_days' => (int)$stats['present_days'],
                'absent_days' => (int)$stats['absent_days'],
                'percentage' => $percentage,
                'this_month_total' => (int)$month_stats['this_month_total'],
                'this_month_present' => (int)$month_stats['this_month_present']
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get statistics: ' . $e->getMessage()];
    }
}

/**
 * Get student attendance history
 */
function getStudentHistory($student_id, $limit = 50) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT student_id, student_name, timestamp, status 
            FROM attendance 
            WHERE student_id = ? 
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        $stmt->execute([$student_id, $limit]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'success' => true,
            'data' => $history
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get attendance history: ' . $e->getMessage()];
    }
}

/**
 * Get student profile
 */
function getStudentProfile($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT u.username, u.email, u.last_login, s.student_id, s.name, s.phone, s.created_at
            FROM users u
            JOIN students s ON u.id = s.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$profile) {
            return ['success' => false, 'message' => 'Profile not found'];
        }
        
        return [
            'success' => true,
            'data' => $profile
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to get profile: ' . $e->getMessage()];
    }
}

/**
 * Update student profile
 */
function updateStudentProfile($user_id) {
    global $pdo;
    
    try {
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        
        if (empty($name)) {
            return ['success' => false, 'message' => 'Name is required'];
        }
        
        // Update user email
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$email, $user_id]);
        
        // Update student information
        $stmt = $pdo->prepare("
            UPDATE students 
            SET name = ?, phone = ?, email = ? 
            WHERE user_id = ?
        ");
        $stmt->execute([$name, $phone, $email, $user_id]);
        
        return ['success' => true, 'message' => 'Profile updated successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to update profile: ' . $e->getMessage()];
    }
}

/**
 * Change student password
 */
function changeStudentPassword($user_id) {
    global $pdo;
    
    try {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            return ['success' => false, 'message' => 'All password fields are required'];
        }
        
        if ($new_password !== $confirm_password) {
            return ['success' => false, 'message' => 'New passwords do not match'];
        }
        
        if (strlen($new_password) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long'];
        }
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($current_password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Update password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$new_password_hash, $user_id]);
        
        return ['success' => true, 'message' => 'Password changed successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Failed to change password: ' . $e->getMessage()];
    }
}
?>
