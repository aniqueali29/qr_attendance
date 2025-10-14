<?php
/**
 * Students API
 * Handles student CRUD operations
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

// Require admin authentication
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit();
}

$action = $_GET['action'] ?? 'list';

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
            echo json_encode(getStudentsList());
            break;
        case 'view':
            echo json_encode(getStudentDetails());
            break;
        case 'export':
            echo json_encode(exportStudents());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handlePostRequest($action) {
    switch ($action) {
        case 'create':
            echo json_encode(createStudent());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handlePutRequest($action) {
    switch ($action) {
        case 'update':
            echo json_encode(updateStudent());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handleDeleteRequest($action) {
    switch ($action) {
        case 'delete':
            echo json_encode(deleteStudent());
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

/**
 * Get students list with pagination and filters
 */
function getStudentsList() {
    global $pdo;
    
    try {
        $page = (int)($_GET['page'] ?? 1);
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $whereConditions = ["u.role = 'student'", "u.is_active = 1"];
        $params = [];
        
        // Apply filters
        if (!empty($_GET['program'])) {
            $whereConditions[] = "u.program = ?";
            $params[] = $_GET['program'];
        }
        
        if (!empty($_GET['shift'])) {
            $whereConditions[] = "u.shift = ?";
            $params[] = $_GET['shift'];
        }
        
        if (!empty($_GET['year'])) {
            $whereConditions[] = "u.year_level = ?";
            $params[] = $_GET['year'];
        }
        
        if (!empty($_GET['section'])) {
            $whereConditions[] = "u.section = ?";
            $params[] = $_GET['section'];
        }
        
        if (!empty($_GET['search'])) {
            $whereConditions[] = "(u.username LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
            $searchTerm = '%' . $_GET['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM users u WHERE $whereClause";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Get students with attendance percentage
        $sql = "
            SELECT 
                u.id,
                u.username as roll_number,
                u.name,
                u.email,
                u.phone,
                u.program,
                u.shift,
                u.year_level,
                u.section,
                COALESCE(att.attendance_percentage, 0) as attendance_percentage
            FROM users u
            LEFT JOIN (
                SELECT 
                    user_id,
                    ROUND(
                        (COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / 
                         NULLIF(COUNT(*), 0)), 2
                    ) as attendance_percentage
                FROM attendance 
                GROUP BY user_id
            ) att ON u.id = att.user_id
            WHERE $whereClause
            ORDER BY u.username
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $students = $stmt->fetchAll();
        
        $totalPages = ceil($total / $limit);
        
        return [
            'success' => true,
            'data' => [
                'students' => $students,
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
 * Get student details
 */
function getStudentDetails() {
    global $pdo;
    
    try {
        $studentId = $_GET['id'] ?? 0;
        
        if (!$studentId) {
            return ['success' => false, 'error' => 'Student ID required'];
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.username as roll_number,
                u.name,
                u.email,
                u.phone,
                u.program,
                u.shift,
                u.year_level,
                u.section,
                u.created_at,
                u.last_login
            FROM users u
            WHERE u.id = ? AND u.role = 'student' AND u.is_active = 1
        ");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        
        if (!$student) {
            return ['success' => false, 'error' => 'Student not found'];
        }
        
        return [
            'success' => true,
            'data' => $student
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create new student
 */
function createStudent() {
    global $pdo;
    
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            return ['success' => false, 'error' => 'Invalid CSRF token'];
        }
        
        $rollNumber = sanitizeInput($_POST['roll_number'] ?? '');
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $program = sanitizeInput($_POST['program'] ?? '');
        $shift = sanitizeInput($_POST['shift'] ?? '');
        $yearLevel = sanitizeInput($_POST['year_level'] ?? '');
        $section = sanitizeInput($_POST['section'] ?? '');
        
        // Validate required fields
        if (empty($rollNumber) || empty($name) || empty($email) || empty($program) || empty($shift) || empty($yearLevel) || empty($section)) {
            return ['success' => false, 'error' => 'All required fields must be filled'];
        }
        
        // Check if roll number already exists in students table
        $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ? OR roll_number = ?");
        $stmt->execute([$rollNumber, $rollNumber]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Roll number already exists'];
        }
        
        // Check if email already exists in students table
        $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Email already exists'];
        }
        
        // Create student in students table only
        $admissionYear = '20' . substr($rollNumber, 0, 2);
        $rollPrefix = explode('-', $rollNumber)[1];
        
        $stmt = $pdo->prepare("
            INSERT INTO students (student_id, roll_number, name, email, phone, program, shift, year_level, section, admission_year, roll_prefix, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $rollNumber, $rollNumber, $name, $email, $phone, $program, $shift, $yearLevel, $section, $admissionYear, $rollPrefix
        ]);
        
        $studentId = $pdo->lastInsertId();
        
        // Log the action
        logAdminAction('STUDENT_CREATED', "Created student: $rollNumber ($name)");
        
        return [
            'success' => true,
            'message' => 'Student created successfully',
            'data' => [
                'id' => $studentId,
                'roll_number' => $rollNumber,
                'password' => $password
            ]
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Update student
 */
function updateStudent() {
    global $pdo;
    
    try {
        $studentId = $_GET['id'] ?? 0;
        
        if (!$studentId) {
            return ['success' => false, 'error' => 'Student ID required'];
        }
        
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            return ['success' => false, 'error' => 'Invalid CSRF token'];
        }
        
        $name = sanitizeInput($_POST['name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $program = sanitizeInput($_POST['program'] ?? '');
        $shift = sanitizeInput($_POST['shift'] ?? '');
        $yearLevel = sanitizeInput($_POST['year_level'] ?? '');
        $section = sanitizeInput($_POST['section'] ?? '');
        
        // Validate required fields
        if (empty($name) || empty($email) || empty($program) || empty($shift) || empty($yearLevel) || empty($section)) {
            return ['success' => false, 'error' => 'All required fields must be filled'];
        }
        
        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $studentId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Email already exists'];
        }
        
        // Update student
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, email = ?, phone = ?, program = ?, shift = ?, year_level = ?, section = ?, updated_at = NOW()
            WHERE id = ? AND role = 'student'
        ");
        $stmt->execute([$name, $email, $phone, $program, $shift, $yearLevel, $section, $studentId]);
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'Student not found'];
        }
        
        // Log the action
        logAdminAction('STUDENT_UPDATED', "Updated student ID: $studentId");
        
        return [
            'success' => true,
            'message' => 'Student updated successfully'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete student
 */
function deleteStudent() {
    global $pdo;
    
    try {
        $studentId = $_GET['id'] ?? 0;
        
        if (!$studentId) {
            return ['success' => false, 'error' => 'Student ID required'];
        }
        
        // Get student info for logging
        $stmt = $pdo->prepare("SELECT username, name FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        
        if (!$student) {
            return ['success' => false, 'error' => 'Student not found'];
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Delete attendance records
            $stmt = $pdo->prepare("DELETE FROM attendance WHERE user_id = ?");
            $stmt->execute([$studentId]);
            
            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
            $stmt->execute([$studentId]);
            
            $pdo->commit();
            
            // Log the action
            logAdminAction('STUDENT_DELETED', "Deleted student: {$student['username']} ({$student['name']})");
            
            return [
                'success' => true,
                'message' => 'Student deleted successfully'
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Export students
 */
function exportStudents() {
    global $pdo;
    
    try {
        // Get all students (no pagination for export)
        $sql = "
            SELECT 
                u.username as roll_number,
                u.name,
                u.email,
                u.phone,
                u.program,
                u.shift,
                u.year_level,
                u.section,
                u.created_at,
                COALESCE(att.attendance_percentage, 0) as attendance_percentage
            FROM users u
            LEFT JOIN (
                SELECT 
                    user_id,
                    ROUND(
                        (COUNT(CASE WHEN status = 'present' THEN 1 END) * 100.0 / 
                         NULLIF(COUNT(*), 0)), 2
                    ) as attendance_percentage
                FROM attendance 
                GROUP BY user_id
            ) att ON u.id = att.user_id
            WHERE u.role = 'student' AND u.is_active = 1
            ORDER BY u.username
        ";
        
        $stmt = $pdo->query($sql);
        $students = $stmt->fetchAll();
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="students_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Roll Number', 'Name', 'Email', 'Phone', 'Program', 'Shift', 
            'Year Level', 'Section', 'Attendance %', 'Created At'
        ]);
        
        // CSV data
        foreach ($students as $student) {
            fputcsv($output, [
                $student['roll_number'],
                $student['name'],
                $student['email'],
                $student['phone'],
                $student['program'],
                $student['shift'],
                $student['year_level'],
                $student['section'],
                $student['attendance_percentage'],
                $student['created_at']
            ]);
        }
        
        fclose($output);
        exit();
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>
