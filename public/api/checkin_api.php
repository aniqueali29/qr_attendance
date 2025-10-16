<?php
/**
 * Check-in/Check-out API for QR Code Attendance System
 * Handles check-in and check-out operations with time validation
 * Enhanced with roll number parsing and shift-based time windows
 */

require_once 'config.php';
require_once 'roll_parser.php';
require_once 'time_validator.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        // Get action from JSON input or POST data
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';
        
        switch ($action) {
            case 'check_in':
                handleCheckIn($pdo);
                break;
                
            case 'check_out':
                handleCheckOut($pdo);
                break;
                
            case 'get_status':
                getStudentStatus($pdo);
                break;
                
            case 'bulk_checkin':
                handleBulkCheckIn($pdo);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

/**
 * Handle check-in operation with enhanced roll number parsing and time validation
 */
function handleCheckIn($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['student_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    $student_id = $input['student_id'];
    $current_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
    $current_time_str = $current_time->format('Y-m-d H:i:s');
    
    try {
        // Parse roll number to get student metadata
        $roll_data = RollParser::parseRollNumber($student_id);
        if (!$roll_data['valid']) {
            echo json_encode(['success' => false, 'message' => 'Invalid roll number format: ' . $roll_data['error']]);
            return;
        }
        
        // Get student information from database
        $stmt = $pdo->prepare("
            SELECT name, admission_year, current_year, shift, program, is_active, is_graduated 
            FROM students 
            WHERE student_id = ? AND is_active = 1
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found or inactive']);
            return;
        }
        
        // Check if student is graduated
        if ($student['is_graduated']) {
            echo json_encode(['success' => false, 'message' => 'Student has graduated and cannot check in']);
            return;
        }
        
        // Update student metadata if needed
        $needs_update = false;
        if ($student['admission_year'] != $roll_data['admission_year'] || 
            $student['shift'] != $roll_data['shift'] || 
            $student['program'] != $roll_data['program']) {
            $needs_update = true;
        }
        
        if ($needs_update) {
            $stmt = $pdo->prepare("
                UPDATE students 
                SET admission_year = ?, current_year = ?, shift = ?, program = ?, last_year_update = CURDATE()
                WHERE student_id = ?
            ");
            $stmt->execute([
                $roll_data['admission_year'],
                $roll_data['current_year'],
                $roll_data['shift'],
                $roll_data['program'],
                $student_id
            ]);
        }
        
        // Validate check-in time based on shift
        $time_validator = new TimeValidator();
        $time_validation = $time_validator->validateCheckinTime($student_id, $current_time, $roll_data['shift']);
        
        if (!$time_validation['valid']) {
            echo json_encode([
                'success' => false, 
                'message' => $time_validation['error'],
                'timing_info' => [
                    'shift' => $roll_data['shift'],
                    'checkin_window' => $time_validation['checkin_start'] . ' - ' . $time_validation['checkin_end'],
                    'current_time' => $time_validation['current_time']
                ]
            ]);
            return;
        }
        
        // Check if student already has an active session
        $stmt = $pdo->prepare("SELECT id FROM check_in_sessions WHERE student_id = ? AND is_active = 1");
        $stmt->execute([$student_id]);
        $active_session = $stmt->fetch();
        
        if ($active_session) {
            echo json_encode(['success' => false, 'message' => 'Student already checked in. Please check out first.']);
            return;
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Create new check-in session (only use columns that exist in the table)
            $stmt = $pdo->prepare("
                INSERT INTO check_in_sessions (student_id, student_name, check_in_time, is_active) 
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([
                $student_id, 
                $student['name'], 
                $current_time_str
            ]);
            
            // Record attendance with Check-in status and enhanced metadata
            $stmt = $pdo->prepare("
                INSERT INTO attendance (student_id, student_name, timestamp, status, check_in_time, shift, program, current_year, admission_year) 
                VALUES (?, ?, ?, 'Check-in', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $student_id, 
                $student['name'], 
                $current_time_str, 
                $current_time_str,
                $roll_data['shift'],
                $roll_data['program'],
                $roll_data['current_year'],
                $roll_data['admission_year']
            ]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Check-in successful',
                'data' => [
                    'student_id' => $student_id,
                    'student_name' => $student['name'],
                    'check_in_time' => $current_time_str,
                    'status' => 'Check-in',
                    'shift' => $roll_data['shift'],
                    'program' => $roll_data['program'],
                    'current_year' => $roll_data['current_year'],
                    'admission_year' => $roll_data['admission_year'],
                    'timing_info' => [
                        'checkin_window' => $time_validation['checkin_start'] . ' - ' . $time_validation['checkin_end'],
                        'class_ends' => $time_validation['class_end'],
                        'time_until_close' => $time_validation['time_until_close']
                    ]
                ]
            ]);
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Check-in failed: ' . $e->getMessage()]);
    }
}

/**
 * Handle check-out operation
 */
function handleCheckOut($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['student_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    $student_id = $input['student_id'];
    $current_time = date('Y-m-d H:i:s');
    
    try {
        // Check if student has an active session
        $stmt = $pdo->prepare("
            SELECT id, check_in_time, student_name 
            FROM check_in_sessions 
            WHERE student_id = ? AND is_active = 1
        ");
        $stmt->execute([$student_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'No active check-in session found']);
            return;
        }
        
        // Parse roll number to get shift information for checkout validation
        $roll_data = RollParser::parseRollNumber($student_id);
        if (!$roll_data['valid']) {
            echo json_encode(['success' => false, 'message' => 'Invalid roll number format: ' . $roll_data['error']]);
            return;
        }
        
        // Validate checkout time based on shift
        $time_validator = new TimeValidator();
        $checkout_validation = $time_validator->validateCheckoutTime($student_id, new DateTime($current_time), $roll_data['shift']);
        
        if (!$checkout_validation['valid']) {
            echo json_encode([
                'success' => false, 
                'message' => $checkout_validation['error'],
                'timing_info' => [
                    'shift' => $roll_data['shift'],
                    'checkout_window' => $checkout_validation['checkout_start'] . ' - ' . $checkout_validation['checkout_end'],
                    'current_time' => $checkout_validation['current_time']
                ]
            ]);
            return;
        }
        
        // Calculate session duration for all shifts
        $check_in_time = new DateTime($session['check_in_time']);
        $current_time_obj = new DateTime($current_time);
        $time_diff = $current_time_obj->diff($check_in_time);
        $total_minutes = ($time_diff->h * 60) + $time_diff->i;
        
        // For morning shift, check minimum duration (2 hours)
        // For evening shift, allow immediate checkout (free access)
        if ($roll_data['shift'] === 'Morning') {
            if ($total_minutes < 120) { // 2 hours = 120 minutes
                $remaining_minutes = 120 - $total_minutes;
                echo json_encode([
                    'success' => false, 
                    'message' => "Cannot check out yet. Please wait {$remaining_minutes} more minutes.",
                    'remaining_minutes' => $remaining_minutes
                ]);
                return;
            }
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Update attendance record to Present (student completed check-in and check-out)
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET status = 'Present', check_out_time = ?, session_duration = ?
                WHERE student_id = ? AND DATE(timestamp) = DATE(?) AND status = 'Check-in'
            ");
            $stmt->execute([$current_time, $total_minutes, $student_id, $current_time]);
            
            // Deactivate the check-in session
            $stmt = $pdo->prepare("
                UPDATE check_in_sessions 
                SET is_active = 0 
                WHERE id = ?
            ");
            $stmt->execute([$session['id']]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Check-out successful',
                'data' => [
                    'student_id' => $student_id,
                    'student_name' => $session['student_name'],
                    'check_in_time' => $session['check_in_time'],
                    'check_out_time' => $current_time,
                    'session_duration' => $total_minutes,
                    'status' => 'Present'
                ]
            ]);
            
        } catch (Exception $e) {
            $pdo->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Check-out failed: ' . $e->getMessage()]);
    }
}

/**
 * Get student's current status with enhanced shift and timing information
 */
function getStudentStatus($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    $student_id = $input['student_id'] ?? $_GET['student_id'] ?? '';
    
    if (empty($student_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Student ID is required']);
        return;
    }
    
    try {
        // Parse roll number to get student metadata
        $roll_data = RollParser::parseRollNumber($student_id);
        $current_time = new DateTime('now', new DateTimeZone('Asia/Karachi'));
        
        // Get student information
        $stmt = $pdo->prepare("
            SELECT name, admission_year, current_year, shift, program, is_active, is_graduated
            FROM students 
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            echo json_encode(['success' => false, 'message' => 'Student not found']);
            return;
        }
        
        // Check if student has an active session (shift, program, current_year not in check_in_sessions table)
        $stmt = $pdo->prepare("
            SELECT id, check_in_time, student_name, last_activity
            FROM check_in_sessions
            WHERE student_id = ? AND is_active = 1
        ");
        $stmt->execute([$student_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            // Calculate time elapsed
            $check_in_time = new DateTime($session['check_in_time']);
            $time_diff = $current_time->diff($check_in_time);
            $total_minutes = ($time_diff->h * 60) + $time_diff->i;
            
            // Get shift information from students table (not in check_in_sessions)
            $session_shift = $student['shift'];

            // Validate checkout time based on shift
            $time_validator = new TimeValidator();
            $checkout_validation = $time_validator->validateCheckoutTime($student_id, $current_time, $session_shift);

            // For morning shift, still check minimum duration (2 hours)
            // For evening shift, allow immediate checkout (free access)
            $can_checkout = $checkout_validation['valid'];
            if ($session_shift === 'Morning') {
                $can_checkout = $can_checkout && $total_minutes >= 120;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'student_id' => $student_id,
                    'student_name' => $session['student_name'],
                    'status' => 'Checked-in',
                    'check_in_time' => $session['check_in_time'],
                    'time_elapsed' => $total_minutes,
                    'can_checkout' => $can_checkout,
                    'remaining_minutes' => $can_checkout ? 0 : (120 - $total_minutes),
                    'shift' => $session_shift,
                    'program' => $student['program'],
                    'current_year' => $student['current_year'],
                    'admission_year' => $student['admission_year'],
                    'is_graduated' => $student['is_graduated'],
                    'checkout_validation' => [
                        'valid' => $checkout_validation['valid'],
                        'checkout_window' => $checkout_validation['checkout_start'] . ' - ' . $checkout_validation['checkout_end'],
                        'error' => $checkout_validation['error']
                    ]
                ]
            ]);
        } else {
            // Get timing information for check-in window
            $time_validator = new TimeValidator();
            $time_validation = $time_validator->validateCheckinTime($student_id, $current_time, $student['shift']);
            $next_window = $time_validator->getNextCheckinWindow($student['shift'], $current_time);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'student_id' => $student_id,
                    'student_name' => $student['name'],
                    'status' => 'Not checked in',
                    'can_checkin' => $time_validation['valid'],
                    'shift' => $student['shift'],
                    'program' => $student['program'],
                    'current_year' => $student['current_year'],
                    'admission_year' => $student['admission_year'],
                    'is_graduated' => $student['is_graduated'],
                    'timing_info' => [
                        'checkin_window' => $time_validation['checkin_start'] . ' - ' . $time_validation['checkin_end'],
                        'class_ends' => $time_validation['class_end'],
                        'current_time' => $time_validation['current_time'],
                        'is_within_window' => $time_validation['is_within_window'],
                        'time_until_close' => $time_validation['time_until_close']
                    ],
                    'next_window' => $next_window
                ]
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to get status: ' . $e->getMessage()]);
    }
}

/**
 * Handle bulk check-in from Python app
 */
function handleBulkCheckIn($pdo) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate API key
        $api_key = $input['api_key'] ?? '';
        if ($api_key !== 'attendance_2025_xyz789_secure') {
            echo json_encode(['success' => false, 'message' => 'Invalid API key']);
            return;
        }
        
        $attendance_data = $input['attendance_data'] ?? [];
        
        if (empty($attendance_data)) {
            echo json_encode(['success' => false, 'message' => 'No attendance data provided']);
            return;
        }
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($attendance_data as $record) {
            try {
                $student_id = $record['ID'] ?? $record['student_id'] ?? '';
                $student_name = $record['Name'] ?? $record['student_name'] ?? 'Unknown';
                $timestamp = $record['Timestamp'] ?? $record['timestamp'] ?? date('Y-m-d H:i:s');
                $status = $record['Status'] ?? $record['status'] ?? 'present';
                $shift = $record['Shift'] ?? 'Morning';
                $program = $record['Program'] ?? '';
                $current_year = $record['Current_Year'] ?? $record['current_year'] ?? 1;
                $admission_year = $record['Admission_Year'] ?? $record['admission_year'] ?? date('Y');
                
                if (empty($student_id)) {
                    $error_count++;
                    $errors[] = "Missing student ID in record";
                    continue;
                }
                
                // Insert attendance record with all required fields
                $stmt = $pdo->prepare("
                    INSERT INTO attendance (student_id, student_name, timestamp, status, shift, program, current_year, admission_year, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([$student_id, $student_name, $timestamp, $status, $shift, $program, $current_year, $admission_year]);
                $success_count++;
                
            } catch (Exception $e) {
                $error_count++;
                $errors[] = "Error processing record for {$student_id}: " . $e->getMessage();
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Bulk check-in completed: {$success_count} success, {$error_count} errors",
            'data' => [
                'success_count' => $success_count,
                'error_count' => $error_count,
                'errors' => $errors
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Bulk check-in failed: ' . $e->getMessage()]);
    }
}
?>
