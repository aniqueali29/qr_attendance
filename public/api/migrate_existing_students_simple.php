<?php
/**
 * Simple Student Migration Script
 * No authentication required - for manual migration
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

try {
    // Get all students
    $stmt = $pdo->prepare("
        SELECT s.id, s.student_id, s.name, s.email, s.admission_year, s.year_level, s.program, s.shift
        FROM students s 
        WHERE s.student_id IS NOT NULL 
        AND s.student_id != ''
        ORDER BY s.id
    ");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($students)) {
        echo json_encode([
            'success' => true,
            'message' => 'No students found to migrate',
            'total_students' => 0,
            'migrated_students' => 0,
            'errors' => 0
        ]);
        exit();
    }
    
    $migrated_students = [];
    $errors = [];
    $skipped_students = [];
    
    foreach ($students as $student) {
        try {
            $student_id = $student['student_id'];
            $roll_data = parseRollNumberForMigration($student_id, $pdo);
            
            if (!$roll_data['success']) {
                $errors[] = [
                    'student_id' => $student_id,
                    'name' => $student['name'],
                    'error' => $roll_data['error'],
                    'roll_number' => $student_id
                ];
                continue;
            }
            
            $parsed_data = $roll_data['data'];
            
            // Check if student already has correct data
            $needs_update = false;
            $update_fields = [];
            
            // Check admission year
            if ($student['admission_year'] != $parsed_data['admission_year']) {
                $update_fields['admission_year'] = $parsed_data['admission_year'];
                $needs_update = true;
            }
            
            // Check program
            if (empty($student['program']) && !empty($parsed_data['program_code'])) {
                $update_fields['program'] = $parsed_data['program_code'];
                $needs_update = true;
            }
            
            // Check shift
            if (empty($student['shift']) && !empty($parsed_data['shift'])) {
                $update_fields['shift'] = $parsed_data['shift'];
                $needs_update = true;
            }
            
            // Check year level
            if (empty($student['year_level']) && !empty($parsed_data['year_level'])) {
                $update_fields['year_level'] = $parsed_data['year_level'];
                $needs_update = true;
            }
            
            // Add roll prefix
            if (!empty($parsed_data['program_code'])) {
                $update_fields['roll_prefix'] = $parsed_data['program_code'];
                $needs_update = true;
            }
            
            // Add calculated year level (only if column exists)
            if (columnExists($pdo, 'students', 'calculated_year_level')) {
                $update_fields['calculated_year_level'] = $parsed_data['year_level'];
            }
            if (columnExists($pdo, 'students', 'last_year_update')) {
                $update_fields['last_year_update'] = date('Y-m-d H:i:s');
            }
            $needs_update = true;
            
            if ($needs_update) {
                // Build update query
                $update_fields['updated_at'] = date('Y-m-d H:i:s');
                $set_clauses = [];
                $values = [];
                
                foreach ($update_fields as $field => $value) {
                    $set_clauses[] = "$field = ?";
                    $values[] = $value;
                }
                
                $values[] = $student['id'];
                
                $stmt = $pdo->prepare("
                    UPDATE students 
                    SET " . implode(', ', $set_clauses) . "
                    WHERE id = ?
                ");
                $stmt->execute($values);
                
                $migrated_students[] = [
                    'student_id' => $student_id,
                    'name' => $student['name'],
                    'admission_year' => $parsed_data['admission_year'],
                    'program' => $parsed_data['program_code'],
                    'shift' => $parsed_data['shift'],
                    'year_level' => $parsed_data['year_level'],
                    'updated_fields' => array_keys($update_fields)
                ];
            } else {
                $skipped_students[] = [
                    'student_id' => $student_id,
                    'name' => $student['name'],
                    'reason' => 'Already up to date'
                ];
            }
            
        } catch (Exception $e) {
            $errors[] = [
                'student_id' => $student['student_id'],
                'name' => $student['name'],
                'error' => $e->getMessage(),
                'roll_number' => $student['student_id']
            ];
        }
    }
    
    // Update section student counts
    updateSectionStudentCounts($pdo);
    
    // Log migration results
    $log_data = [
        'date' => date('Y-m-d H:i:s'),
        'total_students' => count($students),
        'migrated_students' => count($migrated_students),
        'skipped_students' => count($skipped_students),
        'errors' => count($errors),
        'details' => [
            'migrated' => $migrated_students,
            'skipped' => $skipped_students,
            'errors' => $errors
        ]
    ];
    
    // Save migration log
    $log_file = "logs/student_migration_" . date('Y-m-d') . ".json";
    if (!is_dir('logs')) {
        mkdir('logs', 0755, true);
    }
    file_put_contents($log_file, json_encode($log_data, JSON_PRETTY_PRINT));
    
    echo json_encode([
        'success' => true,
        'message' => 'Student migration completed',
        'total_students' => count($students),
        'migrated_students' => count($migrated_students),
        'skipped_students' => count($skipped_students),
        'errors' => count($errors),
        'log_file' => $log_file,
        'details' => [
            'migrated' => $migrated_students,
            'skipped' => $skipped_students,
            'errors' => $errors
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Migration failed: ' . $e->getMessage()
    ]);
}

/**
 * Parse roll number for migration (same logic as roll parser service)
 */
function parseRollNumberForMigration($roll_number, $pdo) {
    try {
        // Parse roll number format: YY-[E]PROGRAM-NN
        $pattern = '/^(\d{2})-E?([A-Z]{2,10})-(\d{2})$/';
        if (!preg_match($pattern, $roll_number, $matches)) {
            return [
                'success' => false, 
                'error' => 'Invalid roll number format: ' . $roll_number
            ];
        }
        
        $year_part = $matches[1];
        $program_part = $matches[2];
        $serial_part = $matches[3];
        
        // Determine if it's evening shift (has E prefix)
        $is_evening = strpos($roll_number, '-E') !== false;
        $shift = $is_evening ? 'Evening' : 'Morning';
        
        // Convert 2-digit year to 4-digit year
        $admission_year = 2000 + intval($year_part);
        
        // Validate admission year (must be reasonable)
        $current_year = date('Y');
        if ($admission_year > $current_year || $admission_year < ($current_year - 10)) {
            return [
                'success' => false, 
                'error' => 'Invalid admission year: ' . $admission_year
            ];
        }
        
        // Get program details from database
        $stmt = $pdo->prepare("SELECT id, code, name FROM programs WHERE code = ? AND is_active = TRUE");
        $stmt->execute([$program_part]);
        $program = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$program) {
            return [
                'success' => false, 
                'error' => 'Unknown program code: ' . $program_part
            ];
        }
        
        // Calculate current year level
        $current_date = new DateTime();
        $current_academic_year = $current_date->format('Y');
        
        // Academic year starts in September
        if ($current_date->format('n') < 9) {
            $current_academic_year--;
        }
        
        $years_in_program = $current_academic_year - $admission_year + 1;
        $year_level = min(max($years_in_program, 1), 3);
        
        // Determine if student has completed the program
        $is_completed = $years_in_program > 3;
        $status = $is_completed ? 'Completed' : $year_level . 'st';
        
        return [
            'success' => true,
            'data' => [
                'roll_number' => $roll_number,
                'admission_year' => $admission_year,
                'program_id' => $program['id'],
                'program_code' => $program['code'],
                'program_name' => $program['name'],
                'shift' => $shift,
                'serial_number' => $serial_part,
                'year_level' => $status,
                'is_completed' => $is_completed,
                'years_in_program' => $years_in_program
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error parsing roll number: ' . $e->getMessage()
        ];
    }
}

/**
 * Check if a column exists in a table
 */
function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Update section student counts
 */
function updateSectionStudentCounts($pdo) {
    try {
        // Get all sections
        $stmt = $pdo->prepare("
            SELECT sec.id, sec.program_id, sec.year_level, sec.shift, sec.section_name
            FROM sections sec
            WHERE sec.is_active = TRUE
        ");
        $stmt->execute();
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sections as $section) {
            // Count students in this section
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as student_count
                FROM students s
                WHERE s.section_id = ? AND s.year_level = ?
            ");
            $stmt->execute([$section['id'], $section['year_level']]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['student_count'];
            
            // Update section count
            $stmt = $pdo->prepare("
                UPDATE sections 
                SET current_students = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$count, $section['id']]);
        }
        
    } catch (Exception $e) {
        error_log("Error updating section counts: " . $e->getMessage());
    }
}
?>
