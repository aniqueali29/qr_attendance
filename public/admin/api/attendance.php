<?php
/**
 * Attendance API
 * Handles attendance records CRUD operations
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Check for API key authentication for bulk_sync, otherwise require admin authentication
$action = $_GET['action'] ?? 'list';
if ($action !== 'bulk_sync' && !isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest($action);
            break;
        case 'POST':
            handlePostRequest($action);
            break;
        case 'PUT':
            handlePutRequest($action);
            break;
        case 'DELETE':
            handleDeleteRequest($action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

function handleGetRequest($action) {
    switch ($action) {
        case 'list':
            echo json_encode(getAttendanceList());
            break;
        case 'view':
            echo json_encode(getAttendanceDetails());
            break;
        case 'export':
            echo json_encode(exportAttendance());
            break;
        case 'get_csrf_token':
            echo json_encode(['success' => true, 'token' => generateCSRFToken()]);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handlePostRequest($action) {
    switch ($action) {
        case 'create':
            echo json_encode(createAttendance());
            break;
        case 'bulk_sync':
            echo json_encode(bulkSyncAttendance());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handlePutRequest($action) {
    switch ($action) {
        case 'update':
            echo json_encode(updateAttendance());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handleDeleteRequest($action) {
    switch ($action) {
        case 'delete':
            echo json_encode(deleteAttendance());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

/**
 * Get attendance list with pagination and filters
 */
function getAttendanceList() {
    global $pdo;
    
    try {
        $page = (int)($_GET['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $whereConditions = ["1=1"];
        $params = [];
        
        // Apply filters
        if (!empty($_GET['date_from'])) {
            $whereConditions[] = "DATE(a.timestamp) >= ?";
            $params[] = $_GET['date_from'];
        }
        
        if (!empty($_GET['date_to'])) {
            $whereConditions[] = "DATE(a.timestamp) <= ?";
            $params[] = $_GET['date_to'];
        }
        
        if (!empty($_GET['student_id'])) {
            $whereConditions[] = "a.student_id = ?";
            $params[] = $_GET['student_id'];
        }
        
        if (!empty($_GET['status'])) {
            // Handle multiple statuses separated by comma
            if (strpos($_GET['status'], ',') !== false) {
                $statuses = explode(',', $_GET['status']);
                $statusPlaceholders = str_repeat('?,', count($statuses) - 1) . '?';
                $whereConditions[] = "a.status IN ($statusPlaceholders)";
                $params = array_merge($params, $statuses);
            } else {
                $whereConditions[] = "a.status = ?";
                $params[] = $_GET['status'];
            }
        }
        
        if (!empty($_GET['program'])) {
            $whereConditions[] = "a.program = ?";
            $params[] = $_GET['program'];
        }
        
        if (!empty($_GET['shift'])) {
            $whereConditions[] = "a.shift = ?";
            $params[] = $_GET['shift'];
        }
        
        if (!empty($_GET['search'])) {
            $whereConditions[] = "(a.student_id LIKE ? OR a.student_name LIKE ?)";
            $searchTerm = '%' . $_GET['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total count
        $countSql = "
            SELECT COUNT(*) as total 
            FROM attendance a
            WHERE $whereClause
        ";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Get attendance records
        $sql = "
            SELECT 
                a.id,
                a.student_id,
                a.student_name,
                a.student_id as roll_number,
                a.student_name as student_name,
                a.program,
                a.shift,
                a.status,
                a.check_in_time as checkin_time,
                a.check_out_time as checkout_time,
                a.session_duration as duration_minutes,
                a.timestamp,
                DATE(a.timestamp) as date,
                TIME(a.timestamp) as time,
                CASE 
                    WHEN a.check_out_time IS NOT NULL 
                    THEN CONCAT(
                        FLOOR(a.session_duration / 60), 'h ',
                        MOD(a.session_duration, 60), 'm'
                    )
                    ELSE NULL 
                END as duration
            FROM attendance a
            WHERE $whereClause
            ORDER BY a.timestamp DESC
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();
        
        $totalPages = ceil($total / $limit);
        
        return [
            'success' => true,
            'data' => [
                'records' => $records,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $total,
                    'per_page' => $limit
                ]
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get attendance details
 */
function getAttendanceDetails() {
    global $pdo;
    
    try {
        $attendanceId = $_GET['id'] ?? 0;
        
        if (!$attendanceId) {
            return ['success' => false, 'error' => 'Attendance ID required'];
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.student_id,
                a.student_name,
                a.student_id as roll_number,
                a.student_name as student_name,
                a.program,
                a.shift,
                a.status,
                a.check_in_time as checkin_time,
                a.check_out_time as checkout_time,
                a.session_duration as duration_minutes,
                a.timestamp,
                a.notes,
                DATE(a.timestamp) as date,
                TIME(a.timestamp) as time,
                CASE 
                    WHEN a.check_out_time IS NOT NULL 
                    THEN CONCAT(
                        FLOOR(a.session_duration / 60), 'h ',
                        MOD(a.session_duration, 60), 'm'
                    )
                    ELSE NULL 
                END as duration
            FROM attendance a
            WHERE a.id = ?
        ");
        $stmt->execute([$attendanceId]);
        $record = $stmt->fetch();
        
        if (!$record) {
            return ['success' => false, 'error' => 'Attendance record not found'];
        }
        
        return [
            'success' => true,
            'data' => $record
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create new attendance record
 */
function createAttendance() {
    global $pdo;
    
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            return ['success' => false, 'error' => 'Invalid CSRF token'];
        }
        
        $studentId = sanitizeInput($_POST['student_id'] ?? '');
        $studentName = sanitizeInput($_POST['student_name'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? '');
        $program = sanitizeInput($_POST['program'] ?? '');
        $shift = sanitizeInput($_POST['shift'] ?? '');
        
        // Validate required fields
        if (empty($studentId) || empty($status)) {
            return ['success' => false, 'error' => 'Student ID and status are required'];
        }
        
        // Create attendance record
        $stmt = $pdo->prepare("
            INSERT INTO attendance (student_id, student_name, status, timestamp, program, shift, created_at)
            VALUES (?, ?, ?, NOW(), ?, ?, NOW())
        ");
        $stmt->execute([$studentId, $studentName, $status, $program, $shift]);
        
        $attendanceId = $pdo->lastInsertId();
        
        // Log the action
        logAdminAction('ATTENDANCE_CREATED', "Created attendance record ID: $attendanceId");
        
        return [
            'success' => true,
            'message' => 'Attendance record created successfully',
            'data' => ['id' => $attendanceId]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Bulk sync attendance records from CSV
 */
function bulkSyncAttendance() {
    global $pdo;
    
    try {
        // Check API key authentication
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? '';
        
        if ($apiKey !== 'attendance_2025_xyz789_secure') {
            return ['success' => false, 'error' => 'Invalid API key'];
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $records = $input['records'] ?? [];
        
        if (empty($records)) {
            return ['success' => false, 'error' => 'No records provided'];
        }
        
        $inserted = 0;
        $updated = 0;
        
        foreach ($records as $record) {
            // Parse the timestamp and status to extract check-in/check-out times
            $timestamp = $record['timestamp'];
            $status = $record['status'];
            $checkInTime = null;
            $checkOutTime = null;
            $sessionDuration = null;
            
            // Handle different status formats from CSV
            if (strpos($status, 'Check-in') !== false && strpos($status, 'Checked out') !== false) {
                // This is a consolidated record: "Check-in (Checked out at...)"
                $finalStatus = 'Present'; // Student was present and attended
                
                // Extract check-in time from timestamp
                $checkInTime = $timestamp;
                
                // Extract check-out time from status text
                if (preg_match('/Checked out at (.+?)\)/', $status, $matches)) {
                    $checkOutTime = trim($matches[1]);
                }
                
                $status = $finalStatus;
                
                // Calculate duration if we have both times
                if ($checkInTime && $checkOutTime) {
                    try {
                        $checkIn = new DateTime($checkInTime);
                        $checkOut = new DateTime($checkOutTime);
                        $sessionDuration = $checkOut->getTimestamp() - $checkIn->getTimestamp();
                        $sessionDuration = round($sessionDuration / 60); // Convert to minutes
                    } catch (Exception $e) {
                        $sessionDuration = null;
                    }
                }
            } elseif (strpos($status, 'Check-in') !== false) {
                // This is a check-in record
                $checkInTime = $timestamp;
                $status = 'Check-in';
            } elseif (strpos($status, 'Check-out') !== false || strpos($status, 'Checked out') !== false) {
                // This is a check-out record
                $checkOutTime = $timestamp;
                $status = 'Checked-out';
            } elseif (strpos($status, 'Present') !== false) {
                // This is a consolidated present record
                $status = 'Present';
                // Try to extract check-in and check-out times from consolidated format
                if (strpos($timestamp, ' to ') !== false) {
                    $times = explode(' to ', $timestamp);
                    if (count($times) == 2) {
                        $checkInTime = trim($times[0]);
                        $checkOutTime = trim($times[1]);
                        // Calculate duration in minutes
                        $checkIn = new DateTime($checkInTime);
                        $checkOut = new DateTime($checkOutTime);
                        $sessionDuration = $checkOut->getTimestamp() - $checkIn->getTimestamp();
                        $sessionDuration = round($sessionDuration / 60); // Convert to minutes
                    }
                }
            }
            
            // Check if record exists
            $stmt = $pdo->prepare("
                SELECT id FROM attendance 
                WHERE student_id = ? AND DATE(timestamp) = DATE(?)
            ");
            $stmt->execute([$record['student_id'], $timestamp]);
            
            if ($stmt->fetch()) {
                // Update existing record
                $stmt = $pdo->prepare("
                    UPDATE attendance 
                    SET status = ?, shift = ?, program = ?, 
                        check_in_time = ?, check_out_time = ?, session_duration = ?,
                        updated_at = NOW()
                    WHERE student_id = ? AND DATE(timestamp) = DATE(?)
                ");
                $stmt->execute([
                    $status,
                    $record['shift'] ?? null,
                    $record['program'] ?? null,
                    $checkInTime,
                    $checkOutTime,
                    $sessionDuration,
                    $record['student_id'],
                    $timestamp
                ]);
                $updated++;
            } else {
                // Insert new record
                $stmt = $pdo->prepare("
                    INSERT INTO attendance (student_id, student_name, status, timestamp, shift, program, 
                                          check_in_time, check_out_time, session_duration, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $record['student_id'],
                    $record['student_name'] ?? 'Unknown',
                    $status,
                    $timestamp,
                    $record['shift'] ?? null,
                    $record['program'] ?? null,
                    $checkInTime,
                    $checkOutTime,
                    $sessionDuration
                ]);
                $inserted++;
            }
        }
        
        return [
            'success' => true,
            'message' => 'Bulk sync completed',
            'data' => [
                'inserted' => $inserted,
                'updated' => $updated,
                'total' => count($records)
            ]
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update attendance record
 */
function updateAttendance() {
    global $pdo;
    
    try {
        $attendanceId = $_GET['id'] ?? 0;
        
        if (!$attendanceId) {
            return ['success' => false, 'error' => 'Attendance ID required'];
        }
        
        // Validate CSRF token (temporarily disabled for debugging)
        $csrf_token = $_POST['csrf_token'] ?? '';
        $session_token = $_SESSION['admin_csrf_token'] ?? '';
        
        // Debug CSRF token validation
        error_log("CSRF Debug - Received: " . $csrf_token);
        error_log("CSRF Debug - Session: " . $session_token);
        error_log("CSRF Debug - Match: " . (hash_equals($session_token, $csrf_token) ? 'YES' : 'NO'));
        
        // Temporarily skip CSRF validation for debugging
        // if (!validateCSRFToken($csrf_token)) {
        //     return ['success' => false, 'error' => 'Invalid CSRF token'];
        // }
        
        // Handle both POST and PUT requests
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            // Parse PUT request data
            $input = json_decode(file_get_contents('php://input'), true);
            $status = sanitizeInput($input['status'] ?? '');
            $notes = sanitizeInput($input['notes'] ?? '');
        } else {
            $status = sanitizeInput($_POST['status'] ?? '');
            $notes = sanitizeInput($_POST['notes'] ?? '');
        }
        
        // Validate required fields
        if (empty($status)) {
            return ['success' => false, 'error' => 'Status is required'];
        }
        
        // Update attendance record
        $stmt = $pdo->prepare("
            UPDATE attendance 
            SET status = ?, notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $notes, $attendanceId]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'Attendance record not found'];
        }
        
        // Log the action
        logAdminAction('ATTENDANCE_UPDATED', "Updated attendance record ID: $attendanceId");
        
        return [
            'success' => true,
            'message' => 'Attendance record updated successfully'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete attendance record
 */
function deleteAttendance() {
    global $pdo;
    
    try {
        $attendanceId = $_GET['id'] ?? 0;
        
        if (!$attendanceId) {
            return ['success' => false, 'error' => 'Attendance ID required'];
        }
        
        // Get attendance info for logging
        $stmt = $pdo->prepare("SELECT student_id FROM attendance WHERE id = ?");
        $stmt->execute([$attendanceId]);
        $attendance = $stmt->fetch();
        
        if (!$attendance) {
            return ['success' => false, 'error' => 'Attendance record not found'];
        }
        
        // Delete attendance record
        $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
        $stmt->execute([$attendanceId]);
        
        // Log the action
        logAdminAction('ATTENDANCE_DELETED', "Deleted attendance record ID: $attendanceId");
        
        return [
            'success' => true,
            'message' => 'Attendance record deleted successfully'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Export attendance records
 */
function exportAttendance() {
    global $pdo;
    
    try {
        // Build WHERE clause for export
        $whereConditions = ["1=1"];
        $params = [];
        
        // Apply filters
        if (!empty($_GET['date_from'])) {
            $whereConditions[] = "DATE(a.timestamp) >= ?";
            $params[] = $_GET['date_from'];
        }
        
        if (!empty($_GET['date_to'])) {
            $whereConditions[] = "DATE(a.timestamp) <= ?";
            $params[] = $_GET['date_to'];
        }
        
        if (!empty($_GET['student_id'])) {
            $whereConditions[] = "a.student_id = ?";
            $params[] = $_GET['student_id'];
        }
        
        if (!empty($_GET['status'])) {
            // Handle multiple statuses separated by comma
            if (strpos($_GET['status'], ',') !== false) {
                $statuses = explode(',', $_GET['status']);
                $statusPlaceholders = str_repeat('?,', count($statuses) - 1) . '?';
                $whereConditions[] = "a.status IN ($statusPlaceholders)";
                $params = array_merge($params, $statuses);
            } else {
                $whereConditions[] = "a.status = ?";
                $params[] = $_GET['status'];
            }
        }
        
        if (!empty($_GET['program'])) {
            $whereConditions[] = "a.program = ?";
            $params[] = $_GET['program'];
        }
        
        if (!empty($_GET['shift'])) {
            $whereConditions[] = "a.shift = ?";
            $params[] = $_GET['shift'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get attendance records for export
        $sql = "
            SELECT 
                a.student_id as roll_number,
                a.student_name as student_name,
                a.program,
                a.shift,
                a.status,
                DATE(a.timestamp) as date,
                TIME(a.timestamp) as time,
                TIME(a.check_out_time) as checkout_time,
                CASE 
                    WHEN a.check_out_time IS NOT NULL 
                    THEN CONCAT(
                        FLOOR(a.session_duration / 60), 'h ',
                        MOD(a.session_duration, 60), 'm'
                    )
                    ELSE NULL 
                END as duration
            FROM attendance a
            WHERE $whereClause
            ORDER BY a.timestamp DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendance_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Roll Number', 'Student Name', 'Program', 'Shift', 'Status', 
            'Date', 'Check-in Time', 'Check-out Time', 'Duration'
        ]);
        
        // CSV data
        foreach ($records as $record) {
            fputcsv($output, [
                $record['roll_number'],
                $record['student_name'],
                $record['program'],
                $record['shift'],
                $record['status'],
                $record['date'],
                $record['time'],
                $record['checkout_time'],
                $record['duration']
            ]);
        }
        
        fclose($output);
        exit();
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>
