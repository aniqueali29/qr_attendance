<?php
/**
 * Programs API
 * Handles program and section management
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

// Require admin authentication
requireAdminAuth();

$response = ['success' => false, 'message' => 'Invalid request.'];

try {
    $action = $_REQUEST['action'] ?? '';

    switch ($action) {
        case 'list':
            // Get all programs with section counts
            $stmt = $pdo->query("
                SELECT 
                    p.id,
                    p.code,
                    p.name,
                    p.description,
                    p.is_active,
                    (
                        SELECT COUNT(DISTINCT s.id) 
                        FROM sections s 
                        WHERE s.is_active = 1 
                        AND (
                            -- Morning sections: count under their assigned program
                            (s.shift = 'Morning' AND s.program_id = p.id)
                            OR 
                            -- Evening sections: count under evening program codes
                            (s.shift = 'Evening' AND (
                                (p.code = 'ESWT' AND s.program_id = (SELECT id FROM programs WHERE code = 'SWT'))
                                OR
                                (p.code = 'ECIT' AND s.program_id = (SELECT id FROM programs WHERE code = 'CIT'))
                            ))
                        )
                    ) as section_count,
                    (
                        SELECT COUNT(DISTINCT st.id) 
                        FROM students st 
                        WHERE st.is_active = 1 
                        AND (
                            -- Morning students: count under their assigned program
                            (st.shift = 'Morning' AND st.program = p.code)
                            OR 
                            -- Evening students: count under evening program codes
                            (st.shift = 'Evening' AND (
                                (p.code = 'ESWT' AND st.program = 'SWT') OR
                                (p.code = 'ECIT' AND st.program = 'CIT')
                            ))
                            OR
                            -- Students assigned to sections of this program
                            st.section_id IN (SELECT id FROM sections WHERE program_id = p.id)
                        )
                    ) as total_students
                FROM programs p
                GROUP BY p.id, p.code, p.name, p.description, p.is_active
                ORDER BY p.name ASC
            ");
            $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'data' => $programs
            ];
            break;

        case 'programs':
            // Get just the programs list
            $stmt = $pdo->query("
                SELECT id, code, name, is_active
                FROM programs
                ORDER BY name ASC
            ");
            $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'data' => $programs
            ];
            break;

        case 'sections':
            $programId = $_GET['program_id'] ?? '';
            
            if ($programId) {
                $stmt = $pdo->prepare("
                    SELECT 
                        s.id,
                        s.section_name,
                        s.year_level,
                        s.shift,
                        s.capacity,
                        s.current_students,
                        s.is_active,
                        p.name as program_name,
                        COUNT(st.id) as student_count,
                        ROUND((COUNT(st.id) / s.capacity) * 100, 2) as capacity_utilization
                    FROM sections s
                    LEFT JOIN programs p ON s.program_id = p.id
                    LEFT JOIN students st ON (s.id = st.section_id OR (st.section_id IS NULL AND st.program = p.code)) AND st.is_active = 1
                    WHERE s.program_id = ?
                    GROUP BY s.id, s.section_name, s.year_level, s.shift, s.capacity, s.current_students, s.is_active, p.name
                    ORDER BY s.section_name ASC
                ");
                $stmt->execute([$programId]);
                $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->query("
                    SELECT 
                        s.id,
                        s.section_name,
                        s.year_level,
                        s.shift,
                        s.capacity,
                        s.current_students,
                        s.is_active,
                        p.name as program_name,
                        COUNT(st.id) as student_count,
                        ROUND((COUNT(st.id) / s.capacity) * 100, 2) as capacity_utilization
                    FROM sections s
                    LEFT JOIN programs p ON s.program_id = p.id
                    LEFT JOIN students st ON (s.id = st.section_id OR (st.section_id IS NULL AND st.program = p.code)) AND st.is_active = 1
                    GROUP BY s.id, s.section_name, s.year_level, s.shift, s.capacity, s.current_students, s.is_active, p.name
                    ORDER BY s.section_name ASC
                ");
                $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            $response = [
                'success' => true,
                'data' => $sections
            ];
            break;

        case 'add':
            $code = $_POST['code'] ?? '';
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            
            if (empty($code) || empty($name)) {
                $response = ['success' => false, 'message' => 'Code and name are required.'];
                break;
            }
            
            // Check if program code already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetchColumn() > 0) {
                $response = ['success' => false, 'message' => 'Program code already exists.'];
                break;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO programs (code, name, description, is_active, created_at)
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$code, $name, $description]);
            
            $response = ['success' => true, 'message' => 'Program added successfully.'];
            break;

        case 'add-section':
            $programId = $_POST['program_id'] ?? '';
            $sectionName = $_POST['section_name'] ?? '';
            $yearLevel = $_POST['year_level'] ?? '';
            $shift = $_POST['shift'] ?? '';
            $capacity = $_POST['capacity'] ?? 40;
            
            if (empty($programId) || empty($sectionName) || empty($yearLevel) || empty($shift)) {
                $response = ['success' => false, 'message' => 'Program ID, section name, year level, and shift are required.'];
                break;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO sections (program_id, section_name, year_level, shift, capacity, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$programId, $sectionName, $yearLevel, $shift, $capacity]);
            
            $response = ['success' => true, 'message' => 'Section added successfully.'];
            break;

        case 'view':
            $id = $_GET['id'] ?? '';
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.*, 
                    (
                        SELECT COUNT(DISTINCT s.id) 
                        FROM sections s 
                        WHERE s.is_active = 1 
                        AND (
                            -- Morning sections: count under their assigned program
                            (s.shift = 'Morning' AND s.program_id = p.id)
                            OR 
                            -- Evening sections: count under evening program codes
                            (s.shift = 'Evening' AND (
                                (p.code = 'ESWT' AND s.program_id = (SELECT id FROM programs WHERE code = 'SWT'))
                                OR
                                (p.code = 'ECIT' AND s.program_id = (SELECT id FROM programs WHERE code = 'CIT'))
                            ))
                        )
                    ) as section_count,
                    (
                        SELECT COUNT(DISTINCT st.id) 
                        FROM students st 
                        WHERE st.is_active = 1 
                        AND (
                            -- Morning students: count under their assigned program
                            (st.shift = 'Morning' AND st.program = p.code)
                            OR 
                            -- Evening students: count under evening program codes
                            (st.shift = 'Evening' AND (
                                (p.code = 'ESWT' AND st.program = 'SWT') OR
                                (p.code = 'ECIT' AND st.program = 'CIT')
                            ))
                            OR
                            -- Students assigned to sections of this program
                            st.section_id IN (SELECT id FROM sections WHERE program_id = p.id)
                        )
                    ) as total_students
                FROM programs p
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $program = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($program) {
                $response = ['success' => true, 'data' => $program];
            } else {
                $response = ['success' => false, 'message' => 'Program not found.'];
            }
            break;

        case 'view-section':
            $id = $_GET['id'] ?? '';
            
            $stmt = $pdo->prepare("
                SELECT 
                    s.*, 
                    p.name as program_name, 
                    COUNT(st.id) as student_count,
                    ROUND((COUNT(st.id) / s.capacity) * 100, 2) as capacity_utilization
                FROM sections s
                LEFT JOIN programs p ON s.program_id = p.id
                LEFT JOIN students st ON (s.id = st.section_id OR (st.section_id IS NULL AND st.program = p.code)) AND st.is_active = 1
                WHERE s.id = ?
                GROUP BY s.id, s.section_name, s.year_level, s.shift, s.capacity, s.current_students, s.is_active, p.name
            ");
            $stmt->execute([$id]);
            $section = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Rename section_name to name for consistency
            if ($section) {
                $section['name'] = $section['section_name'];
            }
            
            if ($section) {
                $response = ['success' => true, 'data' => $section];
            } else {
                $response = ['success' => false, 'message' => 'Section not found.'];
            }
            break;

        case 'delete':
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                $response = ['success' => false, 'message' => 'Program ID is required.'];
                break;
            }
            
            // Check if program has students
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE program = (SELECT code FROM programs WHERE id = ?)");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                $response = ['success' => false, 'message' => 'Cannot delete program with existing students.'];
                break;
            }
            
            $stmt = $pdo->prepare("DELETE FROM programs WHERE id = ?");
            $stmt->execute([$id]);
            
            $response = ['success' => true, 'message' => 'Program deleted successfully.'];
            break;

        case 'delete-section':
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                $response = ['success' => false, 'message' => 'Section ID is required.'];
                break;
            }
            
            // Check if section has students
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE section_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                $response = ['success' => false, 'message' => 'Cannot delete section with existing students.'];
                break;
            }
            
            $stmt = $pdo->prepare("DELETE FROM sections WHERE id = ?");
            $stmt->execute([$id]);
            
            $response = ['success' => true, 'message' => 'Section deleted successfully.'];
            break;

        case 'update':
            $id = $_POST['id'] ?? '';
            $code = $_POST['code'] ?? '';
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $isActive = $_POST['is_active'] ?? 1;

            if (empty($id) || empty($code) || empty($name)) {
                $response = ['success' => false, 'message' => 'Program ID, code, and name are required.'];
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE programs SET code = ?, name = ?, description = ?, is_active = ? WHERE id = ?
            ");
            $stmt->execute([$code, $name, $description, $isActive, $id]);
            
            $response = ['success' => true, 'message' => 'Program updated successfully.'];
            break;

        case 'update-section':
            $id = $_POST['id'] ?? '';
            $programId = $_POST['program_id'] ?? '';
            $sectionName = $_POST['section_name'] ?? '';
            $yearLevel = $_POST['year_level'] ?? '';
            $shift = $_POST['shift'] ?? '';
            $capacity = $_POST['capacity'] ?? 40;
            $isActive = $_POST['is_active'] ?? 1;

            if (empty($id) || empty($programId) || empty($sectionName) || empty($yearLevel) || empty($shift)) {
                $response = ['success' => false, 'message' => 'Section ID, program ID, section name, year level, and shift are required.'];
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE sections SET program_id = ?, section_name = ?, year_level = ?, shift = ?, capacity = ?, is_active = ? WHERE id = ?
            ");
            $stmt->execute([$programId, $sectionName, $yearLevel, $shift, $capacity, $isActive, $id]);
            
            $response = ['success' => true, 'message' => 'Section updated successfully.'];
            break;

        default:
            http_response_code(400);
            $response = ['success' => false, 'message' => 'Invalid action.'];
            break;
    }
} catch (PDOException $e) {
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
} catch (Exception $e) {
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
}

echo json_encode($response);
?>
