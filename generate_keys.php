<?php
/**
 * Secure Key Generator
 * Generates secure keys for the QR Attendance System
 */

// Prevent direct access
if (php_sapi_name() !== 'cli' && !isset($_GET['generate'])) {
    die('This script can only be run from command line or with ?generate parameter');
}

echo "QR Attendance System - Secure Key Generator\n";
echo "==========================================\n\n";

// Generate secure keys
function generateSecureKey($length = 32) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($length));
    } else {
        // Fallback for older PHP versions
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $key = '';
        for ($i = 0; $i < $length * 2; $i++) {
            $key .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $key;
    }
}

// Generate keys
$apiKey = 'attendance_2025_secure_key_' . generateSecureKey(16);
$apiSecret = 'attendance_secret_' . generateSecureKey(32);
$jwtSecret = 'jwt_secret_' . generateSecureKey(32);
$encryptionKey = 'encryption_key_' . generateSecureKey(32);

echo "🔑 Generated Secure Keys:\n";
echo "========================\n\n";

echo "API_KEY=$apiKey\n";
echo "API_SECRET=$apiSecret\n";
echo "JWT_SECRET=$jwtSecret\n";
echo "ENCRYPTION_KEY=$encryptionKey\n";

echo "\n📋 Instructions:\n";
echo "================\n";
echo "1. Copy the keys above\n";
echo "2. Open your config.env file\n";
echo "3. Replace the default values with these new keys\n";
echo "4. Save the file\n";
echo "5. Run setup_environment.php to verify configuration\n";

echo "\n⚠️  Security Notes:\n";
echo "==================\n";
echo "- Keep these keys secure and never share them\n";
echo "- Never commit these keys to version control\n";
echo "- Use different keys for different environments\n";
echo "- Rotate keys regularly for enhanced security\n";

echo "\n🎉 Key generation complete!\n";
?>
