<?php
/**
 * Environment Setup Script
 * Helps configure the QR Attendance System environment
 */

// Prevent direct access
if (php_sapi_name() !== 'cli' && !isset($_GET['setup'])) {
    die('This script can only be run from command line or with ?setup parameter');
}

echo "QR Attendance System - Environment Setup\n";
echo "========================================\n\n";

// Check if config.env exists
$configFile = __DIR__ . '/config.env';
if (!file_exists($configFile)) {
    echo "❌ config.env file not found!\n";
    echo "Please create config.env file first.\n";
    exit(1);
}

echo "✅ config.env file found\n";

// Load current configuration
require_once __DIR__ . '/includes/secure_config.php';
$config = SecureConfig::load();

echo "\n📋 Current Configuration:\n";
echo "------------------------\n";
echo "Database Host: " . $config['DB_HOST'] . "\n";
echo "Database Name: " . $config['DB_NAME'] . "\n";
echo "Database User: " . $config['DB_USER'] . "\n";
echo "Database Pass: " . (empty($config['DB_PASS']) ? '[EMPTY]' : '[SET]') . "\n";
echo "Debug Mode: " . ($config['DEBUG_MODE'] ? 'ON' : 'OFF') . "\n";
echo "API Key: " . (empty($config['API_KEY']) ? '[EMPTY]' : '[SET]') . "\n";
echo "SMTP Host: " . $config['SMTP_HOST'] . "\n";
echo "SMTP User: " . (empty($config['SMTP_USERNAME']) ? '[EMPTY]' : '[SET]') . "\n";

// Check for required configurations
echo "\n🔍 Configuration Check:\n";
echo "----------------------\n";

$issues = [];

if (empty($config['DB_PASS'])) {
    $issues[] = "Database password is empty";
}

if (empty($config['SMTP_USERNAME']) || $config['SMTP_USERNAME'] === 'your_email@gmail.com') {
    $issues[] = "SMTP username not configured";
}

if (empty($config['SMTP_PASSWORD']) || $config['SMTP_PASSWORD'] === 'your_app_password_here') {
    $issues[] = "SMTP password not configured";
}

if ($config['API_KEY'] === 'attendance_2025_secure_key_abc123def456') {
    $issues[] = "API key is using default value (security risk)";
}

if ($config['JWT_SECRET'] === 'jwt_secret_key_1234567890abcdef') {
    $issues[] = "JWT secret is using default value (security risk)";
}

if (empty($issues)) {
    echo "✅ All configurations look good!\n";
} else {
    echo "⚠️  Configuration Issues Found:\n";
    foreach ($issues as $issue) {
        echo "   - $issue\n";
    }
}

// Test database connection
echo "\n🔌 Database Connection Test:\n";
echo "----------------------------\n";

try {
    $pdo = new PDO(
        "mysql:host=" . $config['DB_HOST'] . ";dbname=" . $config['DB_NAME'] . ";charset=utf8",
        $config['DB_USER'],
        $config['DB_PASS'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    echo "✅ Database connection successful\n";
    
    // Test if required tables exist
    $tables = ['students', 'users', 'attendance', 'sessions'];
    $missingTables = [];
    
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            $missingTables[] = $table;
        }
    }
    
    if (empty($missingTables)) {
        echo "✅ All required tables exist\n";
    } else {
        echo "⚠️  Missing tables: " . implode(', ', $missingTables) . "\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}

// Check file permissions
echo "\n📁 File Permissions Check:\n";
echo "-------------------------\n";

$directories = [
    'uploads' => 0755,
    'uploads/profile_pictures' => 0755,
    'qr_codes' => 0755,
    'logs' => 0755,
    'sessions' => 0700,
    'backups' => 0755
];

foreach ($directories as $dir => $expectedPerms) {
    $fullPath = __DIR__ . '/' . $dir;
    if (!is_dir($fullPath)) {
        if (mkdir($fullPath, $expectedPerms, true)) {
            echo "✅ Created directory: $dir\n";
        } else {
            echo "❌ Failed to create directory: $dir\n";
        }
    } else {
        $perms = fileperms($fullPath) & 0777;
        if ($perms === $expectedPerms) {
            echo "✅ $dir permissions correct ($perms)\n";
        } else {
            echo "⚠️  $dir permissions incorrect (current: $perms, expected: $expectedPerms)\n";
        }
    }
}

// Security recommendations
echo "\n🔒 Security Recommendations:\n";
echo "----------------------------\n";

if ($config['DEBUG_MODE']) {
    echo "⚠️  Debug mode is ON - disable for production\n";
} else {
    echo "✅ Debug mode is OFF\n";
}

if (strpos($config['API_KEY'], 'attendance_2025_secure_key_abc123def456') !== false) {
    echo "⚠️  Generate a new API key for production\n";
} else {
    echo "✅ API key appears to be customized\n";
}

if (strpos($config['JWT_SECRET'], 'jwt_secret_key_1234567890abcdef') !== false) {
    echo "⚠️  Generate a new JWT secret for production\n";
} else {
    echo "✅ JWT secret appears to be customized\n";
}

echo "\n📝 Next Steps:\n";
echo "-------------\n";
echo "1. Update config.env with your actual database credentials\n";
echo "2. Configure SMTP settings for email functionality\n";
echo "3. Generate secure API keys and secrets\n";
echo "4. Set DEBUG_MODE=false for production\n";
echo "5. Test all functionality\n";
echo "6. Deploy to production\n";

echo "\n🎉 Setup check complete!\n";
?>
