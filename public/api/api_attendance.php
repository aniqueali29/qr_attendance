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
$API_KEY = 'attendance_2025_xyz789_secure'; // Must match the key in Python app

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
                // Validate required fields
                if (!isset($record['ID']) || !isset($record['Name']) || 
                    !isset($record['Timestamp']) || !isset($record['Status'])) {
                    $errors[] = 'Missing required fields in record';
                    continue;
                }
                
                // Check if record already exists (prevent duplicates)
                $stmt = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND timestamp = ?");
                $stmt->execute([$record['ID'], $record['Timestamp']]);
                
                if ($stmt->fetch()) {
                    // Record already exists, skip
                    continue;
                }
                
                // Insert attendance record
                $stmt = $pdo->prepare("
                    INSERT INTO attendance (student_id, student_name, timestamp, status, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $record['ID'],
                    $record['Name'],
                    $record['Timestamp'],
                    $record['Status']
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
        
    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET') {
        // Get attendance data (for website display)
        $stmt = $pdo->prepare("
            SELECT student_id, student_name, timestamp, status 
            FROM attendance 
            ORDER BY timestamp DESC 
            LIMIT 100
        ");
        $stmt->execute();
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
