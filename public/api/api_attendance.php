<?php
/**
 * Attendance API for Python QR Code System
 * Place this file in your website's root directory or API folder
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration - UPDATE THESE WITH YOUR DATABASE DETAILS
$host = 'localhost';
$dbname = 'qr_attendance';
$username = 'root';
$password = '';

// API Configuration
$API_KEY = 'attendance_2025_secure_key_3e13bd5acfdf332ecece2d60aa29db78'; // Must match the key in Python app

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate API key
        if (!isset($input['api_key']) || $input['api_key'] !== $API_KEY) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            exit();
        }
        
        // Validate attendance data
        if (!isset($input['attendance_data']) || !is_array($input['attendance_data'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid attendance data']);
            exit();
        }
        
        $success_count = 0;
        $errors = [];
        
        // Process each attendance record
        foreach ($input['attendance_data'] as $record) {
            try {
                // Validate required fields (support both old and new field names)
                $student_id = $record['ID'] ?? $record['student_id'] ?? null;
                $name = $record['Name'] ?? $record['name'] ?? null;
                $timestamp = $record['Timestamp'] ?? $record['timestamp'] ?? null;
                $status = $record['Status'] ?? $record['status'] ?? null;
                
                if (!$student_id || !$name || !$timestamp || !$status) {
                    $errors[] = 'Missing required fields in record';
                    continue;
                }
                
                // Validate status is one of the allowed ENUM values
                $allowed_statuses = ['Check-in', 'Present', 'Absent'];
                if (!in_array($status, $allowed_statuses)) {
                    $errors[] = "Invalid status '$status'. Must be one of: " . implode(', ', $allowed_statuses);
                    continue;
                }
                
                // Check if record already exists (prevent duplicates)
                $stmt = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND timestamp = ?");
                $stmt->execute([$student_id, $timestamp]);
                
                if ($stmt->fetch()) {
                    // Record already exists, skip
                    continue;
                }
                
                // Extract duration fields
                $check_in_time = $record['Check_In_Time'] ?? $record['check_in_time'] ?? null;
                $check_out_time = $record['Check_Out_Time'] ?? $record['check_out_time'] ?? null;
                $session_duration = $record['Duration_Minutes'] ?? $record['session_duration'] ?? null;
                
                // Insert attendance record with metadata and duration
                $stmt = $pdo->prepare("
                    INSERT INTO attendance (student_id, student_name, timestamp, status, shift, program, current_year, admission_year, check_in_time, check_out_time, session_duration, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $student_id,
                    $name,
                    $timestamp,
                    $status,
                    $record['Shift'] ?? $record['shift'] ?? 'Morning',
                    $record['Program'] ?? $record['program'] ?? '',
                    $record['Current_Year'] ?? $record['current_year'] ?? 1,
                    $record['Admission_Year'] ?? $record['admission_year'] ?? date('Y'),
                    $check_in_time,
                    $check_out_time,
                    $session_duration
                ]);
                
                $success_count++;
                
            } catch (Exception $e) {
                $errors[] = 'Error processing record: ' . $e->getMessage();
            }
        }
        
        // Return response
        $response = [
            'success' => true,
            'processed' => $success_count,
            'total' => count($input['attendance_data']),
            'errors' => $errors
        ];
        
        echo json_encode($response);
        
    } elseif ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
        // Clean up corrupted records
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate API key
        if (!isset($input['api_key']) || $input['api_key'] !== $API_KEY) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            exit();
        }
        
        if (isset($input['action']) && $input['action'] === 'delete_corrupted') {
            // Delete records with invalid student_id format or empty timestamps
            $stmt = $pdo->prepare("DELETE FROM attendance WHERE student_id = 'Morning' OR timestamp = '0000-00-00 00:00:00' OR student_id = '' OR student_name = ''");
            $stmt->execute();
            $deleted = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'deleted' => $deleted,
                'message' => "Cleaned up $deleted corrupted records"
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
        // Provide attendance data for sync pulls with API key auth, pagination and optional since filter
        $inputKey = $_GET['api_key'] ?? '';
        if ($inputKey !== $API_KEY) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid API key']);
            exit();
        }

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        // Sanitize numeric pagination (MySQL doesn't allow bound params in LIMIT/OFFSET in some versions)
        if ($limit < 1) { $limit = 1; }
        if ($limit > 5000) { $limit = 5000; }
        if ($offset < 0) { $offset = 0; }
        $since = $_GET['since'] ?? null; // YYYY-MM-DD

        $params = [];
        $where = '';
        if ($since) {
            $where = 'WHERE timestamp >= ?';
            $params[] = $since . ' 00:00:00';
        }

        $sql = "
            SELECT student_id, student_name, timestamp, status, shift, program, current_year, admission_year, check_in_time, check_out_time, session_duration
            FROM attendance
            $where
            ORDER BY timestamp DESC
            LIMIT $limit OFFSET $offset
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $attendance
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>
