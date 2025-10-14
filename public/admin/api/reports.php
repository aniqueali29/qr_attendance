<?php
/**
 * Reports API
 * Handles attendance reports generation and statistics
 */

require_once '../includes/config.php';
require_once '../includes/auth.php';

// Require admin authentication
requireAdminAuth();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'quick_stats':
            $stats = getQuickStats($pdo);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'program_stats':
            $stats = getProgramStats($pdo);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'shift_stats':
            $stats = getShiftStats($pdo);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'trends':
            $trends = getAttendanceTrends($pdo);
            echo json_encode(['success' => true, 'data' => $trends]);
            break;
            
        case 'generate_report':
            generateReport($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Get quick statistics
 */
function getQuickStats($pdo) {
    // Total students
    $stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE is_active = 1");
    $totalStudents = $stmt->fetchColumn();
    
    // Present today
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT student_id) 
        FROM attendance 
        WHERE DATE(timestamp) = CURDATE() 
        AND status IN ('Present', 'Check-in')
    ");
    $presentToday = $stmt->fetchColumn();
    
    // Average attendance (last 30 days)
    $stmt = $pdo->query("
        SELECT ROUND(
            (COUNT(CASE WHEN status IN ('Present', 'Check-in') THEN 1 END) * 100.0 / 
            NULLIF(COUNT(*), 0)), 2
        ) as avg_attendance
        FROM attendance
        WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $avgAttendance = $stmt->fetchColumn() ?? 0;
    
    // This month attendance
    $stmt = $pdo->query("
        SELECT ROUND(
            (COUNT(CASE WHEN status IN ('Present', 'Check-in') THEN 1 END) * 100.0 / 
            NULLIF(COUNT(*), 0)), 2
        ) as month_attendance
        FROM attendance
        WHERE YEAR(timestamp) = YEAR(CURDATE())
        AND MONTH(timestamp) = MONTH(CURDATE())
    ");
    $monthAttendance = $stmt->fetchColumn() ?? 0;
    
    return [
        'total_students' => (int)$totalStudents,
        'present_today' => (int)$presentToday,
        'avg_attendance' => (float)$avgAttendance,
        'month_attendance' => (float)$monthAttendance
    ];
}

/**
 * Get program-wise statistics
 */
function getProgramStats($pdo) {
    $stmt = $pdo->query("
        SELECT 
            s.program,
            COUNT(DISTINCT s.id) as total_students,
            ROUND(
                (COUNT(CASE WHEN a.status IN ('Present', 'Check-in') THEN 1 END) * 100.0 / 
                NULLIF(COUNT(a.id), 0)), 2
            ) as attendance_percentage
        FROM students s
        LEFT JOIN attendance a ON s.student_id = a.student_id
        WHERE s.is_active = 1
        AND (a.timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) OR a.id IS NULL)
        GROUP BY s.program
        ORDER BY s.program
    ");
    
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format percentages
    foreach ($stats as &$stat) {
        $stat['attendance_percentage'] = $stat['attendance_percentage'] ?? 0;
    }
    
    return $stats;
}

/**
 * Get shift-wise statistics
 */
function getShiftStats($pdo) {
    $stmt = $pdo->query("
        SELECT 
            s.shift,
            COUNT(DISTINCT s.id) as total_students,
            ROUND(
                (COUNT(CASE WHEN a.status IN ('Present', 'Check-in') THEN 1 END) * 100.0 / 
                NULLIF(COUNT(a.id), 0)), 2
            ) as attendance_percentage
        FROM students s
        LEFT JOIN attendance a ON s.student_id = a.student_id
        WHERE s.is_active = 1
        AND (a.timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) OR a.id IS NULL)
        GROUP BY s.shift
        ORDER BY s.shift
    ");
    
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format percentages
    foreach ($stats as &$stat) {
        $stat['attendance_percentage'] = $stat['attendance_percentage'] ?? 0;
    }
    
    return $stats;
}

/**
 * Get attendance trends (last 7 days)
 */
function getAttendanceTrends($pdo) {
    $stmt = $pdo->query("
        SELECT 
            DATE(timestamp) as date,
            COUNT(CASE WHEN status IN ('Present', 'Check-in') THEN 1 END) as present,
            COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
            COUNT(*) as total,
            ROUND(
                (COUNT(CASE WHEN status IN ('Present', 'Check-in') THEN 1 END) * 100.0 / 
                NULLIF(COUNT(*), 0)), 2
            ) as percentage
        FROM attendance
        WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(timestamp)
        ORDER BY DATE(timestamp) DESC
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generate attendance report
 */
function generateReport($pdo) {
    $type = $_GET['type'] ?? '';
    $fromDate = $_GET['from'] ?? '';
    $toDate = $_GET['to'] ?? '';
    $format = $_GET['format'] ?? 'pdf';
    $studentId = $_GET['student'] ?? '';
    $program = $_GET['program'] ?? '';
    
    // Build query based on filters
    $whereConditions = [];
    $params = [];
    
    if ($fromDate) {
        $whereConditions[] = "DATE(a.timestamp) >= ?";
        $params[] = $fromDate;
    }
    
    if ($toDate) {
        $whereConditions[] = "DATE(a.timestamp) <= ?";
        $params[] = $toDate;
    }
    
    if ($studentId) {
        $whereConditions[] = "s.id = ?";
        $params[] = $studentId;
    }
    
    if ($program) {
        $whereConditions[] = "s.program = ?";
        $params[] = $program;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get report data
    $query = "
        SELECT 
            s.student_id as 'Roll Number',
            s.name as 'Student Name',
            s.program as 'Program',
            s.shift as 'Shift',
            COUNT(a.id) as 'Total Records',
            COUNT(CASE WHEN a.status IN ('Present', 'Check-in') THEN 1 END) as 'Present',
            COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as 'Absent',
            ROUND(
                (COUNT(CASE WHEN a.status IN ('Present', 'Check-in') THEN 1 END) * 100.0 / 
                NULLIF(COUNT(a.id), 0)), 2
            ) as 'Attendance %'
        FROM students s
        LEFT JOIN attendance a ON s.student_id = a.student_id
        $whereClause
        GROUP BY s.id, s.student_id, s.name, s.program, s.shift
        ORDER BY s.name
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Export based on format
    if ($format === 'csv') {
        exportCSV($data, 'attendance_report_' . date('Y-m-d') . '.csv');
    } elseif ($format === 'excel') {
        exportExcel($data, 'attendance_report_' . date('Y-m-d') . '.xlsx');
    } else {
        exportPDF($data, 'attendance_report_' . date('Y-m-d') . '.pdf', $type, $fromDate, $toDate);
    }
}

function exportCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function exportExcel($data, $filename) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function exportPDF($data, $filename, $reportType, $fromDate, $toDate) {
    header('Content-Type: text/html; charset=UTF-8');
    
    $reportTitle = ucfirst($reportType) . ' Attendance Report';
    $dateRange = '';
    if ($fromDate && $toDate) {
        $dateRange = "From: $fromDate To: $toDate";
    } elseif ($fromDate) {
        $dateRange = "From: $fromDate";
    } elseif ($toDate) {
        $dateRange = "To: $toDate";
    }
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . $reportTitle . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; text-align: center; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .header { text-align: center; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .print-button { position: fixed; top: 10px; right: 10px; background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        .print-button:hover { background: #0056b3; }
        @media print { .print-button { display: none; } }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">🖨️ Print to PDF</button>
    
    <div class="header">
        <h1>' . $reportTitle . '</h1>
        <p><strong>Generated on:</strong> ' . date('Y-m-d H:i:s') . '</p>';
    
    if ($dateRange) {
        $html .= '<p><strong>' . $dateRange . '</strong></p>';
    }
    
    $html .= '<p><strong>Total Records:</strong> ' . count($data) . '</p>
    </div>';
    
    if (!empty($data)) {
        $html .= '<table><thead><tr>';
        foreach (array_keys($data[0]) as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
    }
    
    $html .= '</body></html>';
    
    echo $html;
    exit;
}
?>
