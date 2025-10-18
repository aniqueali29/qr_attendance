<?php
/**
 * Students Sync API
 * Handles synchronization between web app and local Python app
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

// Include configuration
require_once 'config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple API key authentication (you can change this)
$api_key = 'attendance_2025_secure_key_3e13bd5acfdf332ecece2d60aa29db78'; // Match main API key

function authenticateAPI() {
    global $api_key;
    
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? '';
    
    // Check for API key in Authorization header
    if ($auth_header === 'Bearer ' . $api_key) {
        return true;
    }
    
    // Check for API key in request parameter
    if (isset($_GET['api_key']) && $_GET['api_key'] === $api_key) {
        return true;
    }
    
    return false;
}

// Authenticate API request
if (!authenticateAPI()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'Invalid API key'
    ]);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_students':
            // Get all students from database
            $stmt = $pdo->query("
                SELECT 
                    student_id,
                    roll_number,
                    name,
                    email,
                    phone,
                    program,
                    shift,
                    year_level,
                    section,
                    admission_year,
                    roll_prefix,
                    is_active,
                    created_at,
                    updated_at,
                    username,
                    password,
                    current_year,
                    is_graduated,
                    last_year_update
                FROM students 
                WHERE is_active = 1
                ORDER BY student_id
            ");
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format for JSON structure
            $json_data = [
                'success' => true,
                'students' => [],
                'last_updated' => date('Y-m-d H:i:s'),
                'updated_by' => 'api_sync',
                'total_students' => count($students)
            ];
            
            foreach ($students as $student) {
                // Use the actual password from database (should be roll number)
                $password = $student['password'] ?? $student['roll_number'];
                
                $json_data['students'][$student['student_id']] = [
                    'name' => $student['name'],
                    'email' => $student['email'],
                    'phone' => $student['phone'],
                    'password' => $password,
                    'is_active' => (bool)$student['is_active'],
                    'created_at' => $student['created_at'],
                    'updated_at' => $student['updated_at'],
                    'admission_year' => (int)$student['admission_year'],
                    'current_year' => (int)($student['current_year'] ?? $student['year_level']),
                    'shift' => $student['shift'],
                    'program' => $student['program'],
                    'section' => $student['section'],
                    'roll_number' => $student['roll_number'],
                    'roll_prefix' => $student['roll_prefix'],
                    'is_graduated' => (bool)($student['is_graduated'] ?? false),
                    'last_year_update' => $student['last_year_update'],
                    'student_id' => $student['student_id']
                ];
            }
            
            echo json_encode($json_data, JSON_PRETTY_PRINT);
            break;
            
        case 'update_student':
            // Update student data
            $student_id = $_POST['student_id'] ?? '';
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            
            if (empty($student_id) || empty($name) || empty($email)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields'
                ]);
                break;
            }
            
            $stmt = $pdo->prepare("
                UPDATE students 
                SET name = ?, email = ?, phone = ?, updated_at = NOW()
                WHERE student_id = ?
            ");
            $result = $stmt->execute([$name, $email, $phone, $student_id]);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Student updated successfully' : 'Failed to update student'
            ]);
            break;
            
        case 'add_student':
            // Add new student
            $student_id = $_POST['student_id'] ?? '';
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $program = $_POST['program'] ?? '';
            $shift = $_POST['shift'] ?? '';
            $year_level = $_POST['year_level'] ?? '';
            $section = $_POST['section'] ?? '';
            $admission_year = $_POST['admission_year'] ?? '';
            
            if (empty($student_id) || empty($name) || empty($email)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields'
                ]);
                break;
            }
            
            // Check if student already exists
            $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
            $stmt->execute([$student_id]);
            if ($stmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Student already exists'
                ]);
                break;
            }
            
            // Insert new student
            $stmt = $pdo->prepare("
                INSERT INTO students (student_id, roll_number, name, email, phone, program, shift, year_level, section, admission_year, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $result = $stmt->execute([$student_id, $student_id, $name, $email, $phone, $program, $shift, $year_level, $section, $admission_year]);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Student added successfully' : 'Failed to add student'
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action. Available actions: get_students, update_student, add_student'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}

// Helper function to generate passwords
function generatePassword($length = 12) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $charactersLength = strlen($characters);
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $password;
}
?>
