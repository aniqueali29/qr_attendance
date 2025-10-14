<?php
/**
 * Admin Settings API
 * Enhanced settings management for admin panel
 */

// Enable error reporting for development (disable suppression)
// Errors will be logged to PHP error log, not displayed to client
error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep this 0 to prevent HTML output in JSON
ini_set('log_errors', 1);     // Enable error logging

// Set JSON content type early
header('Content-Type: application/json');

require_once '../includes/config.php';

class AdminSettingsAPI {
    private $pdo;
    
    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        
        if (!$this->pdo) {
            error_log("Database connection failed in AdminSettingsAPI");
        }
    }
    
    /**
     * Check if system_settings table exists
     * DO NOT create tables at runtime - they should be created during deployment
     */
    private function ensureTableExists() {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'system_settings'");
            if ($stmt->rowCount() == 0) {
                error_log("CRITICAL: system_settings table missing! Run database migrations.");
                throw new Exception("Database schema not initialized. Please contact administrator.");
            }
            return true;
        } catch (PDOException $e) {
            error_log("Database check error: " . $e->getMessage());
            throw new Exception("Database connection error");
        }
    }
    
    /**
     * Get all settings with enhanced categorization
     */
    public function getAllSettings() {
        try {
            if (!$this->pdo) {
                return [
                    'success' => false,
                    'message' => 'Database connection not available'
                ];
            }
            
            // Check if table exists (don't create it)
            $this->ensureTableExists();
            
            // First, ensure all default settings exist
            $this->ensureDefaultSettings();
            
            $stmt = $this->pdo->query("
                SELECT setting_key, setting_value, setting_type, category, description, 
                       validation_rules, last_updated, updated_by
                FROM system_settings 
                ORDER BY category, setting_key
            ");
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $category = $row['category'];
                if (!isset($settings[$category])) {
                    $settings[$category] = [];
                }
                
                // Convert value based on type
                $value = $this->convertValue($row['setting_value'], $row['setting_type']);
                
                $settings[$category][] = [
                    'key' => $row['setting_key'],
                    'value' => $value,
                    'type' => $row['setting_type'],
                    'description' => $row['description'],
                    'validation_rules' => json_decode($row['validation_rules'], true),
                    'last_updated' => $row['last_updated'],
                    'updated_by' => $row['updated_by']
                ];
            }
            
            return [
                'success' => true,
                'data' => $settings,
                'total_categories' => count($settings)
            ];
            
        } catch (PDOException $e) {
            error_log("Settings API Database Error: " . $e->getMessage());
            // Return default settings as fallback
            return [
                'success' => true,
                'data' => $this->getDefaultSettings(),
                'message' => 'Using default settings (database unavailable)'
            ];
        } catch (Exception $e) {
            error_log("Settings API General Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error loading settings: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get default settings as fallback
     */
    private function getDefaultSettings() {
        return [
            'timings' => [
                ['key' => 'morning_checkin_start', 'value' => '09:00', 'type' => 'string'],
                ['key' => 'morning_checkin_end', 'value' => '11:00', 'type' => 'string'],
                ['key' => 'morning_checkout_start', 'value' => '12:00', 'type' => 'string'],
                ['key' => 'morning_checkout_end', 'value' => '13:40', 'type' => 'string'],
                ['key' => 'morning_class_end', 'value' => '13:40', 'type' => 'string'],
                ['key' => 'evening_checkin_start', 'value' => '15:00', 'type' => 'string'],
                ['key' => 'evening_checkin_end', 'value' => '18:00', 'type' => 'string'],
                ['key' => 'evening_checkout_start', 'value' => '15:00', 'type' => 'string'],
                ['key' => 'evening_checkout_end', 'value' => '18:00', 'type' => 'string'],
                ['key' => 'evening_class_end', 'value' => '18:00', 'type' => 'string']
            ],
            'system' => [
                ['key' => 'minimum_duration_minutes', 'value' => '120', 'type' => 'integer'],
                ['key' => 'sync_interval_seconds', 'value' => '30', 'type' => 'integer'],
                ['key' => 'timezone', 'value' => 'Asia/Karachi', 'type' => 'string'],
                ['key' => 'academic_year_start_month', 'value' => '9', 'type' => 'integer'],
                ['key' => 'auto_absent_morning_hour', 'value' => '11', 'type' => 'integer'],
                ['key' => 'auto_absent_evening_hour', 'value' => '17', 'type' => 'integer']
            ],
            'integration' => [
                ['key' => 'website_url', 'value' => 'http://localhost/qr_attendance/public', 'type' => 'string'],
                ['key' => 'api_endpoint_attendance', 'value' => '/api/api_attendance.php', 'type' => 'string'],
                ['key' => 'api_endpoint_checkin', 'value' => '/api/checkin_api.php', 'type' => 'string'],
                ['key' => 'api_endpoint_dashboard', 'value' => '/api/dashboard_api.php', 'type' => 'string'],
                ['key' => 'api_timeout_seconds', 'value' => '30', 'type' => 'integer'],
                ['key' => 'api_key', 'value' => 'attendance_2025_xyz789_secure', 'type' => 'string'],
                ['key' => 'max_sync_records', 'value' => '1000', 'type' => 'integer'],
                ['key' => 'api_rate_limit', 'value' => '100', 'type' => 'integer'],
                ['key' => 'qr_code_size', 'value' => '200', 'type' => 'integer'],
                ['key' => 'qr_code_margin', 'value' => '10', 'type' => 'integer'],
                ['key' => 'qr_code_path', 'value' => 'assets/img/qr_codes/', 'type' => 'string'],
                ['key' => 'max_file_size_mb', 'value' => '5', 'type' => 'integer'],
                ['key' => 'allowed_extensions', 'value' => 'csv,json,xlsx', 'type' => 'string']
            ],
            'advanced' => [
                ['key' => 'debug_mode', 'value' => true, 'type' => 'boolean'],
                ['key' => 'log_errors', 'value' => true, 'type' => 'boolean'],
                ['key' => 'show_errors', 'value' => true, 'type' => 'boolean'],
                ['key' => 'session_timeout_seconds', 'value' => '3600', 'type' => 'integer'],
                ['key' => 'max_login_attempts', 'value' => '5', 'type' => 'integer'],
                ['key' => 'login_lockout_minutes', 'value' => '15', 'type' => 'integer'],
                ['key' => 'password_min_length', 'value' => '8', 'type' => 'integer']
            ]
        ];
    }
    
    /**
     * Ensure all default settings exist in database
     */
    private function ensureDefaultSettings() {
        $defaults = $this->getDefaultSettings();
        
        foreach ($defaults as $category => $settings) {
            foreach ($settings as $setting) {
                $key = $setting['key'];
                $value = $setting['value'];
                $type = $setting['type'];
                
                // Check if setting exists
                $stmt = $this->pdo->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
                $stmt->execute([$key]);
                
                if ($stmt->fetch()) {
                    // Setting exists, update validation rules
                    $this->updateValidationRules($key);
                } else {
                    // Setting doesn't exist, create it
                    $stmt = $this->pdo->prepare("
                        INSERT INTO system_settings 
                        (setting_key, setting_value, setting_type, category, description, validation_rules)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    $description = $this->getDescriptionForKey($key);
                    $validation = $this->getValidationRulesForKey($key);
                    
                    $stmt->execute([
                        $key,
                        (string) $value,
                        $type,
                        $category,
                        $description,
                        json_encode($validation)
                    ]);
                }
            }
        }
    }
    
    private function updateValidationRules($key) {
        try {
            $validation = $this->getValidationRulesForKey($key);
            $stmt = $this->pdo->prepare("UPDATE system_settings SET validation_rules = ? WHERE setting_key = ?");
            $stmt->execute([json_encode($validation), $key]);
            error_log("Updated validation rules for {$key}: " . json_encode($validation));
        } catch (Exception $e) {
            error_log("Error updating validation rules for {$key}: " . $e->getMessage());
        }
    }
    
    /**
     * Get category for a setting key
     */
    private function getCategoryForKey($key) {
        $categories = [
            'shift_timings' => ['morning_', 'evening_'],
            'system_config' => ['minimum_duration', 'sync_interval', 'timezone', 'academic_year', 'auto_absent'],
            'integration' => ['website_url', 'api_endpoint', 'api_key', 'api_timeout'],
            'advanced' => ['debug_mode', 'log_errors', 'show_errors', 'session_timeout', 'max_login', 'login_lockout', 'password_min', 'max_sync', 'api_rate'],
            'qr_code' => ['qr_code_'],
            'file_upload' => ['max_file_size', 'allowed_extensions'],
            'email' => ['smtp_']
        ];
        
        foreach ($categories as $category => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (strpos($key, $prefix) === 0) {
                    return $category;
                }
            }
        }
        
        return 'general';
    }
    
    /**
     * Get type for a value
     */
    private function getTypeForValue($value) {
        if (is_bool($value)) {
            return 'boolean';
        } elseif (is_int($value)) {
            return 'integer';
        } elseif (is_float($value)) {
            return 'float';
        } elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            return 'url';
        } elseif (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return 'time';
        } else {
            return 'string';
        }
    }
    
    /**
     * Get description for a setting key
     */
    private function getDescriptionForKey($key) {
        $descriptions = [
            'morning_checkin_start' => 'When morning shift students can start checking in',
            'morning_checkin_end' => 'Last time morning shift students can check in',
            'morning_checkout_start' => 'When morning shift students can start checking out',
            'morning_checkout_end' => 'Last time morning shift students can check out',
            'morning_class_end' => 'When morning shift class ends',
            'evening_checkin_start' => 'When evening shift students can start checking in',
            'evening_checkin_end' => 'Last time evening shift students can check in',
            'evening_checkout_start' => 'When evening shift students can start checking out',
            'evening_checkout_end' => 'Last time evening shift students can check out',
            'evening_class_end' => 'When evening shift class ends',
            'minimum_duration_minutes' => 'Minimum time required for attendance',
            'sync_interval_seconds' => 'How often to sync with web server',
            'timezone' => 'System timezone',
            'academic_year_start_month' => 'When the academic year starts',
            'auto_absent_morning_hour' => 'Hour to mark morning shift absent',
            'auto_absent_evening_hour' => 'Hour to mark evening shift absent',
            'website_url' => 'Base URL of the web application',
            'api_key' => 'API authentication key',
            'debug_mode' => 'Show detailed debug information',
            'log_errors' => 'Log errors to system log',
            'show_errors' => 'Display errors in development mode',
            'session_timeout_seconds' => 'How long sessions remain active',
            'max_login_attempts' => 'Maximum failed login attempts before lockout',
            'login_lockout_minutes' => 'How long to lockout after failed attempts',
            'password_min_length' => 'Minimum required password length',
            'max_sync_records' => 'Maximum records per sync operation',
            'api_rate_limit' => 'Rate limit for API requests',
            'qr_code_size' => 'Size of generated QR codes',
            'qr_code_margin' => 'Margin around QR codes',
            'qr_code_path' => 'Directory to store QR code images',
            'max_file_size_mb' => 'Maximum file size for uploads',
            'allowed_extensions' => 'Comma-separated list of allowed file extensions',
            'smtp_host' => 'SMTP server hostname',
            'smtp_port' => 'SMTP server port',
            'smtp_username' => 'SMTP authentication username',
            'smtp_password' => 'SMTP authentication password',
            'smtp_from_email' => 'Default sender email address',
            'smtp_from_name' => 'Default sender name'
        ];
        
        return $descriptions[$key] ?? 'System setting';
    }
    
    /**
     * Get validation rules for a setting key
     */
    private function getValidationRulesForKey($key) {
        $rules = [
            'morning_checkin_start' => ['required' => true, 'type' => 'time'],
            'morning_checkin_end' => ['required' => true, 'type' => 'time'],
            'morning_checkout_start' => ['required' => true, 'type' => 'time'],
            'morning_checkout_end' => ['required' => true, 'type' => 'time'],
            'morning_class_end' => ['required' => true, 'type' => 'time'],
            'evening_checkin_start' => ['required' => true, 'type' => 'time'],
            'evening_checkin_end' => ['required' => true, 'type' => 'time'],
            'evening_checkout_start' => ['required' => true, 'type' => 'time'],
            'evening_checkout_end' => ['required' => true, 'type' => 'time'],
            'evening_class_end' => ['required' => true, 'type' => 'time'],
            'minimum_duration_minutes' => ['required' => true, 'min' => 30, 'max' => 480],
            'sync_interval_seconds' => ['required' => true, 'min' => 10, 'max' => 300],
            'timezone' => ['required' => true, 'options' => ['Asia/Karachi', 'UTC', 'America/New_York', 'Europe/London']],
            'academic_year_start_month' => ['required' => true, 'min' => 1, 'max' => 12],
            'auto_absent_morning_hour' => ['required' => true, 'min' => 8, 'max' => 16],
            'auto_absent_evening_hour' => ['required' => true, 'min' => 14, 'max' => 20],
            'website_url' => ['required' => true, 'type' => 'url'],
            'api_key' => ['required' => true, 'min_length' => 10],
            'api_timeout_seconds' => ['required' => true, 'min' => 5, 'max' => 120],
            'session_timeout_seconds' => ['required' => true, 'min' => 300, 'max' => 86400],
            'max_login_attempts' => ['required' => true, 'min' => 3, 'max' => 10],
            'login_lockout_minutes' => ['required' => true, 'min' => 5, 'max' => 60],
            'password_min_length' => ['required' => true, 'min' => 6, 'max' => 32],
            'max_sync_records' => ['required' => true, 'min' => 100, 'max' => 10000],
            'api_rate_limit' => ['required' => true, 'min' => 10, 'max' => 1000],
            'qr_code_size' => ['required' => true, 'min' => 100, 'max' => 500],
            'qr_code_margin' => ['required' => true, 'min' => 0, 'max' => 50],
            'max_file_size_mb' => ['required' => true, 'min' => 1, 'max' => 100],
            'smtp_port' => ['required' => true, 'min' => 1, 'max' => 65535],
            'smtp_from_email' => ['required' => true, 'type' => 'email']
        ];
        
        return $rules[$key] ?? [];
    }
    
    /**
     * Convert value based on type
     */
    private function convertValue($value, $type) {
        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'boolean':
                return in_array(strtolower($value), ['true', '1', 'yes']);
            case 'time':
            case 'url':
            case 'email':
            case 'string':
            default:
                return $value;
        }
    }
    
    /**
     * Test website connection
     */
    public function testConnection() {
        try {
            $website_url = $this->getSetting('website_url');
            if (!$website_url['success']) {
                return [
                    'success' => false,
                    'message' => 'Website URL not configured'
                ];
            }
            
            $url = $website_url['data']['value'];
            
            // Test connection with timeout
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'HEAD'
                ]
            ]);
            
            $result = @file_get_contents($url, false, $context);
            
            if ($result !== false) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'data' => [
                        'url' => $url,
                        'response_time' => microtime(true),
                        'status' => 'online'
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Connection failed',
                    'data' => [
                        'url' => $url,
                        'status' => 'offline'
                    ]
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Test API endpoints
     */
    public function testAPI() {
        try {
            $results = [];
            
            // Default endpoints to test if settings are not configured
            $default_endpoints = [
                'attendance' => '/api/api_attendance.php',
                'checkin' => '/api/checkin_api.php',
                'dashboard' => '/api/dashboard_api.php'
            ];
            
            $endpoints = [
                'attendance' => $this->getSetting('api_endpoint_attendance'),
                'checkin' => $this->getSetting('api_endpoint_checkin'),
                'dashboard' => $this->getSetting('api_endpoint_dashboard')
            ];
            
            $base_url = $this->getSetting('website_url');
            $base_url_value = $base_url['success'] ? $base_url['data']['value'] : 'http://localhost/qr_attendance/public';
            
            foreach ($endpoints as $name => $endpoint) {
                // Use configured endpoint or fall back to default
                if ($endpoint['success']) {
                    $endpoint_path = $endpoint['data']['value'];
                } else {
                    $endpoint_path = $default_endpoints[$name];
                }
                
                $url = rtrim($base_url_value, '/') . '/' . ltrim($endpoint_path, '/');
                
                $start_time = microtime(true);
                
                // Test if the endpoint file exists
                $file_path = $_SERVER['DOCUMENT_ROOT'] . '/qr_attendance/public' . $endpoint_path;
                $file_exists = file_exists($file_path);
                
                // Test HTTP response
                $http_works = false;
                if ($file_exists) {
                    $context = stream_context_create([
                        'http' => [
                            'timeout' => 3,
                            'method' => 'GET',
                            'header' => 'Content-Type: application/json',
                            'ignore_errors' => true
                        ]
                    ]);
                    
                    $result = @file_get_contents($url, false, $context);
                    $http_works = $result !== false;
                }
                
                $response_time = round((microtime(true) - $start_time) * 1000, 2);
                
                $results[$name] = [
                    'url' => $url,
                    'file_exists' => $file_exists,
                    'http_works' => $http_works,
                    'status' => $file_exists && $http_works ? 'online' : 'offline',
                    'response_time' => $response_time . 'ms'
                ];
            }
            
            $online_count = 0;
            $total_count = count($results);
            foreach ($results as $result) {
                if ($result['status'] === 'online') {
                    $online_count++;
                }
            }
            
            $all_online = $online_count === $total_count;
            
            return [
                'success' => $all_online,
                'message' => $all_online ? 'All API endpoints are working' : "Only {$online_count}/{$total_count} API endpoints are responding",
                'data' => $results,
                'summary' => [
                    'total' => $total_count,
                    'online' => $online_count,
                    'offline' => $total_count - $online_count
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'API test failed: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    /**
     * Get a specific setting
     */
    public function getSetting($key) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT setting_key, setting_value, setting_type, category, description, 
                       validation_rules, last_updated, updated_by
                FROM system_settings 
                WHERE setting_key = ?
            ");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$row) {
                return [
                    'success' => false,
                    'message' => 'Setting not found'
                ];
            }
            
            $value = $this->convertValue($row['setting_value'], $row['setting_type']);
            
            return [
                'success' => true,
                'data' => [
                    'key' => $row['setting_key'],
                    'value' => $value,
                    'type' => $row['setting_type'],
                    'category' => $row['category'],
                    'description' => $row['description'],
                    'validation_rules' => json_decode($row['validation_rules'], true),
                    'last_updated' => $row['last_updated'],
                    'updated_by' => $row['updated_by']
                ]
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update a single setting
     */
    public function updateSetting($key, $value, $updated_by = 'admin') {
        try {
            // Get current setting info
            $current = $this->getSetting($key);
            
            if (!$current['success']) {
                // Setting doesn't exist, create it
                return $this->createSetting($key, $value, $updated_by);
            }
            
            $setting_info = $current['data'];
            
            // Validate the value
            $validation = $this->validateSetting($key, $value, $setting_info['validation_rules']);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ];
            }
            
            // Convert value to string for storage
            $string_value = $this->convertToString($value, $setting_info['type']);
            
            // Update the setting
            $stmt = $this->pdo->prepare("
                UPDATE system_settings 
                SET setting_value = ?, last_updated = CURRENT_TIMESTAMP, updated_by = ?
                WHERE setting_key = ?
            ");
            $result = $stmt->execute([$string_value, $updated_by, $key]);
            
            if ($result) {
                // Write to settings.json file for Python app
                $this->writeSettingsToJson();
                
                return [
                    'success' => true,
                    'message' => 'Setting updated successfully',
                    'data' => [
                        'key' => $key,
                        'value' => $value,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update setting'
                ];
            }
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create a new setting
     */
    private function createSetting($key, $value, $updated_by = 'admin') {
        try {
            $category = $this->getCategoryForKey($key);
            $type = $this->getTypeForValue($value);
            $description = $this->getDescriptionForKey($key);
            $validation = $this->getValidationRulesForKey($key);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO system_settings 
                (setting_key, setting_value, setting_type, category, description, validation_rules, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $key,
                (string) $value,
                $type,
                $category,
                $description,
                json_encode($validation),
                $updated_by
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Setting created successfully',
                    'data' => [
                        'key' => $key,
                        'value' => $value,
                        'created_at' => date('Y-m-d H:i:s')
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create setting'
                ];
            }
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update multiple settings
     */
    public function bulkUpdateSettings($settings, $updated_by = 'admin') {
        try {
            error_log("Bulk update started with " . count($settings) . " settings");
            error_log("Settings data: " . print_r($settings, true));
            
            $this->pdo->beginTransaction();
            
            $results = [];
            $errors = [];
            
            foreach ($settings as $setting) {
                $key = $setting['key'];
                $value = $setting['value'];
                
                error_log("Processing setting: {$key} = " . print_r($value, true));
                
                $result = $this->updateSetting($key, $value, $updated_by);
                $results[] = $result;
                
                if (!$result['success']) {
                    $errors[] = "Setting '{$key}': " . $result['message'];
                    error_log("Failed to update setting '{$key}': " . $result['message']);
                } else {
                    error_log("Successfully updated setting '{$key}'");
                }
            }
            
            if (empty($errors)) {
                $this->pdo->commit();
                
                // Write to settings.json file for Python app
                $this->writeSettingsToJson();
                
                return [
                    'success' => true,
                    'message' => 'All settings updated successfully',
                    'updated_count' => count($settings)
                ];
            } else {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Some settings failed to update',
                    'errors' => $errors,
                    'failed_count' => count($errors),
                    'total_count' => count($settings)
                ];
            }
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Bulk update database error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate timing settings
     */
    public function validateTimings($timings) {
        $errors = [];
        $warnings = [];
        
        // Debug: Log the received timings
        error_log("Received timings: " . print_r($timings, true));
        
        try {
            // Helper function to parse time with flexible format and proper validation
            $parseTime = function($time, $fieldName, $default = '09:00:00') {
                if (empty($time)) {
                    $dt = DateTime::createFromFormat('H:i:s', $default);
                    if ($dt === false) {
                        throw new Exception("Invalid default time for {$fieldName}: {$default}");
                    }
                    return $dt;
                }
                
                // Try different time formats
                $formats = ['H:i:s', 'H:i', 'g:i A', 'g:i a'];
                foreach ($formats as $format) {
                    $dt = DateTime::createFromFormat($format, $time);
                    if ($dt !== false) {
                        // Validate that the parsing was successful without errors
                        $errors = DateTime::getLastErrors();
                        if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                            continue; // Try next format
                        }
                        return $dt;
                    }
                }
                
                // If no format worked, throw exception
                throw new Exception("Invalid time format for {$fieldName}: {$time}. Expected formats: HH:MM, HH:MM:SS, or h:mm AM/PM");
            };
            
            // Parse morning timings with validation
            $morning_start = $parseTime($timings['morning_checkin_start'] ?? '', 'morning_checkin_start', '09:00:00');
            $morning_end = $parseTime($timings['morning_checkin_end'] ?? '', 'morning_checkin_end', '11:00:00');
            $morning_checkout_start = $parseTime($timings['morning_checkout_start'] ?? '', 'morning_checkout_start', '12:00:00');
            $morning_checkout_end = $parseTime($timings['morning_checkout_end'] ?? '', 'morning_checkout_end', '13:40:00');
            $morning_class_end = $parseTime($timings['morning_class_end'] ?? '', 'morning_class_end', '13:40:00');
            
            // Parse evening timings with validation
            $evening_start = $parseTime($timings['evening_checkin_start'] ?? '', 'evening_checkin_start', '15:00:00');
            $evening_end = $parseTime($timings['evening_checkin_end'] ?? '', 'evening_checkin_end', '18:00:00');
            $evening_checkout_start = $parseTime($timings['evening_checkout_start'] ?? '', 'evening_checkout_start', '15:00:00');
            $evening_checkout_end = $parseTime($timings['evening_checkout_end'] ?? '', 'evening_checkout_end', '18:00:00');
            $evening_class_end = $parseTime($timings['evening_class_end'] ?? '', 'evening_class_end', '18:00:00');
            
            // Validate morning shift logic
            if ($morning_end <= $morning_start) {
                $errors[] = "Morning check-in end must be after check-in start";
            }
            
            if ($morning_checkout_start < $morning_start) {
                $errors[] = "Morning check-out start must be during or after check-in start";
            }
            
            if ($morning_checkout_end <= $morning_checkout_start) {
                $errors[] = "Morning check-out end must be after check-out start";
            }
            
            if ($morning_class_end <= $morning_start) {
                $errors[] = "Morning class end must be after check-in start";
            }
            
            // Validate evening shift
            if ($evening_end <= $evening_start) {
                $errors[] = "Evening check-in end must be after check-in start";
            }
            
            if ($evening_checkout_start < $evening_start) {
                $errors[] = "Evening check-out start must be during or after check-in start";
            }
            
            if ($evening_checkout_end <= $evening_checkout_start) {
                $errors[] = "Evening check-out end must be after check-out start";
            }
            
            if ($evening_class_end <= $evening_start) {
                $errors[] = "Evening class end must be after check-in start";
            }
            
            // Check for overlapping shifts
            if ($morning_class_end > $evening_start) {
                $warnings[] = "Morning class end overlaps with evening shift start";
            }
            
            // Check for reasonable time windows
            try {
                $morning_duration = $morning_end->diff($morning_start)->h;
                if ($morning_duration < 0.5) {
                    $warnings[] = "Morning check-in window is very short";
                }
            } catch (Exception $e) {
                $warnings[] = "Could not calculate morning duration";
            }
            
            try {
                $evening_duration = $evening_end->diff($evening_start)->h;
                if ($evening_duration < 0.5) {
                    $warnings[] = "Evening check-in window is very short";
                }
            } catch (Exception $e) {
                $warnings[] = "Could not calculate evening duration";
            }
            
            return [
                'success' => empty($errors),
                'valid' => empty($errors),
                'errors' => $errors,
                'warnings' => $warnings,
                'message' => empty($errors) ? 'Timing configuration is valid' : 'Validation failed'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'valid' => false,
                'errors' => ["Invalid time format: " . $e->getMessage()],
                'warnings' => [],
                'message' => 'Validation error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Export all settings as JSON
     */
    public function exportSettings() {
        try {
            $settings = $this->getAllSettings();
            if ($settings['success']) {
                return [
                    'success' => true,
                    'message' => 'Settings exported successfully',
                    'data' => $settings['data'],
                    'exported_at' => date('Y-m-d H:i:s'),
                    'total_settings' => array_sum(array_map('count', $settings['data']))
                ];
            } else {
                return $settings;
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Convert value to string for storage
     */
    private function convertToString($value, $type) {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'integer':
                return (string) $value;
            default:
                return (string) $value;
        }
    }
    
    /**
     * Validate a single setting
     */
    private function validateSetting($key, $value, $validation_rules) {
        $errors = [];
        
        error_log("Validating setting '{$key}' with value: " . print_r($value, true) . " and rules: " . print_r($validation_rules, true));
        
        if (empty($validation_rules)) {
            return ['valid' => true, 'errors' => []];
        }
        
        // Type validation first
        if (isset($validation_rules['type'])) {
            switch ($validation_rules['type']) {
                case 'integer':
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[] = "Value must be numeric (got: " . gettype($value) . ")";
                        // If not numeric, skip other validations
                        return ['valid' => false, 'errors' => $errors];
                    }
                    break;
                case 'boolean':
                    if (!in_array($value, [true, false, 'true', 'false', '1', '0', 1, 0], true)) {
                        $errors[] = "Value must be boolean (true/false)";
                        return ['valid' => false, 'errors' => $errors];
                    }
                    break;
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Invalid email format";
                        return ['valid' => false, 'errors' => $errors];
                    }
                    break;
                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        $errors[] = "Invalid URL format";
                        return ['valid' => false, 'errors' => $errors];
                    }
                    break;
                case 'time':
                    // Validate time format (HH:MM or HH:MM:SS)
                    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $value)) {
                        $errors[] = "Invalid time format. Expected HH:MM or HH:MM:SS";
                        return ['valid' => false, 'errors' => $errors];
                    }
                    break;
            }
        }
        
        // Check required
        if (isset($validation_rules['required']) && $validation_rules['required']) {
            if ($value === '' || $value === null) {
                $errors[] = "This setting is required";
            }
        }
        
        // Check minimum length (for strings)
        if (isset($validation_rules['min_length']) && is_string($value)) {
            if (strlen($value) < $validation_rules['min_length']) {
                $errors[] = "Minimum length is " . $validation_rules['min_length'] . " characters";
            }
        }
        
        // Check maximum length (for strings)
        if (isset($validation_rules['max_length']) && is_string($value)) {
            if (strlen($value) > $validation_rules['max_length']) {
                $errors[] = "Maximum length is " . $validation_rules['max_length'] . " characters";
            }
        }
        
        // Check numeric ranges
        $is_numeric = is_numeric($value) || (is_string($value) && is_numeric(trim($value)));
        error_log("Value '{$value}' is numeric: " . ($is_numeric ? 'yes' : 'no'));
        
        if ($is_numeric) {
            $numeric_value = (float) $value;
            error_log("Numeric value: {$numeric_value}");
            
            if (isset($validation_rules['min'])) {
                error_log("Checking min: {$numeric_value} < {$validation_rules['min']} = " . ($numeric_value < $validation_rules['min'] ? 'true' : 'false'));
                if ($numeric_value < $validation_rules['min']) {
                    $errors[] = "Minimum value is " . $validation_rules['min'] . " (got " . $numeric_value . ")";
                }
            }
            if (isset($validation_rules['max'])) {
                error_log("Checking max: {$numeric_value} > {$validation_rules['max']} = " . ($numeric_value > $validation_rules['max'] ? 'true' : 'false'));
                if ($numeric_value > $validation_rules['max']) {
                    $errors[] = "Maximum value is " . $validation_rules['max'] . " (got " . $numeric_value . ")";
                }
            }
        } else {
            // If it's not numeric but has min/max rules, it's an error
            if (isset($validation_rules['min']) || isset($validation_rules['max'])) {
                $errors[] = "Value must be numeric for min/max validation (got: " . var_export($value, true) . ")";
            }
        }
        
        // Check options (whitelist)
        if (isset($validation_rules['options']) && is_array($validation_rules['options'])) {
            if (!in_array($value, $validation_rules['options'], true)) {
                $errors[] = "Value must be one of: " . implode(', ', $validation_rules['options']);
            }
        }
        
        // Check regex pattern
        if (isset($validation_rules['pattern']) && is_string($value)) {
            if (!preg_match($validation_rules['pattern'], $value)) {
                $errors[] = "Value does not match required pattern";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Write all settings to settings.json file for Python app
     */
    private function writeSettingsToJson() {
        // Suppress warnings for this function
        $old_error_reporting = error_reporting(0);
        
        try {
            // Get all settings from database
            $stmt = $this->pdo->query("
                SELECT setting_key, setting_value, setting_type 
                FROM system_settings 
                ORDER BY setting_key
            ");
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = $row['setting_key'];
                $value = $row['setting_value'];
                $type = $row['setting_type'];
                
                // Convert value based on type
                if ($type === 'integer') {
                    $settings[$key] = (int)$value;
                } elseif ($type === 'boolean') {
                    $settings[$key] = $value === '1' || $value === 'true';
                } elseif ($type === 'json') {
                    $settings[$key] = json_decode($value, true);
                } else {
                    $settings[$key] = $value;
                }
            }
            
            // Create settings.json structure
            $jsonData = [
                'settings' => $settings,
                'last_updated' => date('Y-m-d H:i:s'),
                'updated_by' => 'admin_panel'
            ];
            
            // For hosting setup, we don't write to local files
            // Instead, we'll provide an API endpoint for Python app to fetch settings
            error_log("Settings updated in database - Python app should fetch via API");
            return true;
            
        } catch (Exception $e) {
            error_log("Error writing settings.json: " . $e->getMessage());
            return false;
        } finally {
            // Restore error reporting
            error_reporting($old_error_reporting);
        }
    }
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $api = new AdminSettingsAPI();
    
    // Debug logging
    error_log("Settings API called with action: " . $action);
    
    switch ($action) {
        case 'get_all':
            $result = $api->getAllSettings();
            error_log("Settings API result: " . json_encode($result));
            break;
            
        case 'test_db':
            // Test database connection
            try {
                if (!$api->pdo) {
                    $result = [
                        'success' => false,
                        'message' => 'Database connection not available'
                    ];
                } else {
                    $stmt = $api->pdo->query("SHOW TABLES LIKE 'system_settings'");
                    $tableExists = $stmt->fetch();
                    $result = [
                        'success' => true,
                        'message' => $tableExists ? 'Table exists' : 'Table does not exist',
                        'data' => ['table_exists' => (bool)$tableExists]
                    ];
                }
            } catch (Exception $e) {
                $result = [
                    'success' => false,
                    'message' => 'Database test failed: ' . $e->getMessage()
                ];
            }
            break;
            
        case 'test_connection':
            $result = $api->testConnection();
            break;
            
        case 'test_api':
            $result = $api->testAPI();
            break;
            
        case 'export':
            $result = $api->exportSettings();
            break;
            
        default:
            $result = ['success' => false, 'message' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $api = new AdminSettingsAPI();
    
    switch ($action) {
        case 'bulk_update':
            $result = $api->bulkUpdateSettings($input['settings'] ?? [], $input['updated_by'] ?? 'admin');
            break;
            
        case 'update':
            $result = $api->updateSetting($input['key'] ?? '', $input['value'] ?? '', $input['updated_by'] ?? 'admin');
            break;
            
        case 'validate_timings':
            $result = $api->validateTimings($input['timings'] ?? []);
            break;
            
        default:
            $result = ['success' => false, 'message' => 'Invalid action'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
