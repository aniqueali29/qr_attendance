<?php
/**
 * Settings Sync API
 * Handles synchronization of settings between website and Python app
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

// Simple API key authentication
$api_key = 'attendance_2025_xyz789_secure'; // Match main API key

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
        case 'get_settings':
            // Get all settings from database
            $stmt = $pdo->query("
                SELECT setting_key, setting_value, setting_type, description, category
                FROM system_settings 
                ORDER BY category, setting_key
            ");
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format for JSON structure
            $json_data = [
                'success' => true,
                'settings' => [],
                'last_updated' => date('Y-m-d H:i:s'),
                'updated_by' => 'api_sync',
                'total_settings' => count($settings)
            ];
            
            foreach ($settings as $setting) {
                $key = $setting['setting_key'];
                $value = $setting['setting_value'];
                
                // Convert value based on type
                switch ($setting['setting_type']) {
                    case 'boolean':
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'integer':
                        $value = (int)$value;
                        break;
                    case 'float':
                        $value = (float)$value;
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                    default:
                        // Keep as string
                        break;
                }
                
                $json_data['settings'][$key] = $value;
            }
            
            echo json_encode($json_data, JSON_PRETTY_PRINT);
            break;
            
        case 'update_settings':
            // Update settings from Python app
            $settings_data = $_POST['settings'] ?? [];
            
            if (empty($settings_data)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No settings data provided'
                ]);
                break;
            }
            
            $updated_count = 0;
            $errors = [];
            
            foreach ($settings_data as $key => $value) {
                try {
                    // Convert value to string for storage
                    $string_value = is_array($value) ? json_encode($value) : (string)$value;
                    $type = is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : (is_float($value) ? 'float' : 'string'));
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings (setting_key, setting_value, setting_type, updated_at) 
                        VALUES (?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                        setting_value = VALUES(setting_value),
                        setting_type = VALUES(setting_type),
                        updated_at = NOW()
                    ");
                    
                    if ($stmt->execute([$key, $string_value, $type])) {
                        $updated_count++;
                    } else {
                        $errors[] = "Failed to update setting: $key";
                    }
                } catch (Exception $e) {
                    $errors[] = "Error updating $key: " . $e->getMessage();
                }
            }
            
            echo json_encode([
                'success' => $updated_count > 0,
                'message' => "Updated $updated_count settings",
                'updated_count' => $updated_count,
                'errors' => $errors
            ]);
            break;
            
        case 'sync_to_python':
            // Sync settings to Python settings.json file
            $settings_file = '../../../python/settings.json';
            
            if (!file_exists($settings_file)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Python settings.json file not found'
                ]);
                break;
            }
            
            // Get current settings from database
            $stmt = $pdo->query("
                SELECT setting_key, setting_value, setting_type
                FROM system_settings 
                ORDER BY setting_key
            ");
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Load existing Python settings
            $python_settings = json_decode(file_get_contents($settings_file), true);
            if (!$python_settings) {
                $python_settings = ['settings' => []];
            }
            
            // Update Python settings
            foreach ($settings as $setting) {
                $key = $setting['setting_key'];
                $value = $setting['setting_value'];
                
                // Convert value based on type
                switch ($setting['setting_type']) {
                    case 'boolean':
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'integer':
                        $value = (int)$value;
                        break;
                    case 'float':
                        $value = (float)$value;
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                    default:
                        // Keep as string
                        break;
                }
                
                $python_settings['settings'][$key] = $value;
            }
            
            // Update metadata
            $python_settings['last_updated'] = date('Y-m-d H:i:s');
            $python_settings['updated_by'] = 'website_sync';
            
            // Write back to file
            $result = file_put_contents($settings_file, json_encode($python_settings, JSON_PRETTY_PRINT));
            
            if ($result !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Settings synced to Python successfully',
                    'bytes_written' => $result,
                    'settings_updated' => count($settings)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to write to Python settings.json'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action. Available actions: get_settings, update_settings, sync_to_python'
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
?>
