<?php
/**
 * Simple Account Setup Script
 * Quick setup for adding initial admin and student accounts
 */

// Include configuration
require_once 'api/config.php';

// Simple HTML interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Account Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background: #007cba;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }
        button:hover {
            background: #005a87;
        }
        .message {
            padding: 10px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .quick-setup {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .quick-setup h3 {
            margin-top: 0;
            color: #0066cc;
        }
        .quick-links {
            text-align: center;
            margin-top: 30px;
        }
        .quick-links a {
            display: inline-block;
            margin: 10px;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .quick-links a:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Quick Account Setup</h1>
        
        <div class="quick-setup">
            <h3>🚀 Quick Start</h3>
            <p>This tool helps you quickly add admin and student accounts to your QR Attendance System.</p>
            <p><strong>Default Admin:</strong> username: <code>admin</code>, password: <code>password</code></p>
        </div>

        <?php
        // Handle form submission
        if ($_POST) {
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = trim($_POST['password']);
            $role = $_POST['role'];
            $student_id = trim($_POST['student_id'] ?? '');
            $student_name = trim($_POST['student_name'] ?? '');
            
            $errors = [];
            
            // Validation
            if (empty($username)) $errors[] = 'Username is required';
            if (empty($email)) $errors[] = 'Email is required';
            if (empty($password)) $errors[] = 'Password is required';
            if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
            if ($role === 'student' && empty($student_id)) $errors[] = 'Student ID is required for student accounts';
            if ($role === 'student' && empty($student_name)) $errors[] = 'Student name is required for student accounts';
            
            if (empty($errors)) {
                try {
                    // Check if username or email exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username, $email]);
                    if ($stmt->fetch()) {
                        throw new Exception('Username or email already exists');
                    }
                    
                    // Create account
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password_hash, role, student_id, is_active) 
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    
                    $student_id_value = ($role === 'student') ? $student_id : null;
                    $stmt->execute([$username, $email, $password_hash, $role, $student_id_value]);
                    $user_id = $pdo->lastInsertId();
                    
                    // Create student record if needed
                    if ($role === 'student') {
                        $stmt = $pdo->prepare("
                            INSERT INTO students (student_id, name, email, user_id, is_active) 
                            VALUES (?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$student_id, $student_name, $email, $user_id]);
                    }
                    
                    echo '<div class="message success">✅ Account created successfully!</div>';
                    
                } catch (Exception $e) {
                    echo '<div class="message error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
            } else {
                echo '<div class="message error">❌ ' . implode('<br>', $errors) . '</div>';
            }
        }
        ?>

        <form method="POST">
            <div class="form-group">
                <label for="role">Account Type</label>
                <select name="role" id="role" required onchange="toggleStudentFields()">
                    <option value="">Select Account Type</option>
                    <option value="admin">Admin</option>
                    <option value="student">Student</option>
                </select>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" required>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required minlength="8">
            </div>

            <div class="form-group" id="studentIdGroup" style="display: none;">
                <label for="student_id">Student ID</label>
                <input type="text" name="student_id" id="student_id" placeholder="e.g., 24-SWT-01">
            </div>

            <div class="form-group" id="studentNameGroup" style="display: none;">
                <label for="student_name">Student Name</label>
                <input type="text" name="student_name" id="student_name" placeholder="Full name">
            </div>

            <button type="submit">Create Account</button>
        </form>

        <div class="quick-links">
            <a href="account_manager.html">📋 Advanced Account Manager</a>
            <a href="index.html">🏠 Back to Home</a>
        </div>
    </div>

    <script>
        function toggleStudentFields() {
            const role = document.getElementById('role').value;
            const studentIdGroup = document.getElementById('studentIdGroup');
            const studentNameGroup = document.getElementById('studentNameGroup');
            
            if (role === 'student') {
                studentIdGroup.style.display = 'block';
                studentNameGroup.style.display = 'block';
                document.getElementById('student_id').required = true;
                document.getElementById('student_name').required = true;
            } else {
                studentIdGroup.style.display = 'none';
                studentNameGroup.style.display = 'none';
                document.getElementById('student_id').required = false;
                document.getElementById('student_name').required = false;
            }
        }
    </script>
</body>
</html>
