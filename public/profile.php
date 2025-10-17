<?php
/**
 * Student Profile Page
 * Student profile management and account settings
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';

// Require student authentication
requireStudentAuth();

// Get current student data
$current_student = getCurrentStudent();
if (!$current_student) {
    logoutStudent();
    header('Location: login.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!validateCSRFToken($csrf_token)) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        if ($action === 'update_profile') {
            $name = sanitizeInput($_POST['name'] ?? '');
            $email = sanitizeInput($_POST['email'] ?? '');
            $phone = sanitizeInput($_POST['phone'] ?? '');
            
            if (empty($name) || empty($email)) {
                $error_message = 'Name and email are required.';
            } else {
                try {
                    // Update student profile
                    $stmt = $pdo->prepare("
                        UPDATE students 
                        SET name = ?, email = ?, phone = ?, updated_at = NOW() 
                        WHERE student_id = ?
                    ");
                    $stmt->execute([$name, $email, $phone, $current_student['student_id']]);
                    
                    // Update user email if different
                    if ($email !== $current_student['email']) {
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET email = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$email, $current_student['user_id']]);
                    }
                    
                    $success_message = 'Profile updated successfully!';
                    
                    // Update session data
                    $_SESSION['student_name'] = $name;
                    $_SESSION['student_email'] = $email;
                    
                    // Refresh student data
                    $current_student = getCurrentStudent();
                    
                } catch (PDOException $e) {
                    $error_message = 'Failed to update profile. Please try again.';
                    error_log("Profile update error: " . $e->getMessage());
                }
            }
        } elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error_message = 'All password fields are required.';
            } elseif ($new_password !== $confirm_password) {
                $error_message = 'New passwords do not match.';
            } elseif (strlen($new_password) < 6) {
                $error_message = 'New password must be at least 6 characters long.';
            } else {
                try {
                    // Verify current password
                    $stmt = $pdo->prepare("
                        SELECT password_hash FROM users WHERE id = ?
                    ");
                    $stmt->execute([$current_student['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$user || !password_verify($current_password, $user['password_hash'])) {
                        $error_message = 'Current password is incorrect.';
                    } else {
                        // Update password
                        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET password_hash = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$new_password_hash, $current_student['user_id']]);
                        
                        $success_message = 'Password changed successfully!';
                    }
                } catch (PDOException $e) {
                    $error_message = 'Failed to change password. Please try again.';
                    error_log("Password change error: " . $e->getMessage());
                }
            }
        }
    }
}

$pageTitle = "Profile - " . $current_student['name'];
$currentPage = "profile";
?>
<!doctype html>
<html lang="en" class="layout-menu-fixed layout-compact" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    
    <title><?php echo sanitizeOutput($pageTitle); ?> - <?php echo STUDENT_SITE_NAME; ?></title>
    <meta name="description" content="Student Profile - QR Attendance System" />
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo getStudentAssetUrl('img/favicon/favicon.ico'); ?>" />
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet" />
    
    <link rel="stylesheet" href="<?php echo getStudentAssetUrl('vendor/fonts/iconify-icons.css'); ?>" />
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="<?php echo getStudentAssetUrl('vendor/css/core.css'); ?>" />
    <link rel="stylesheet" href="<?php echo getStudentAssetUrl('css/demo.css'); ?>" />
    
    <!-- Vendors CSS -->
    <link rel="stylesheet" href="<?php echo getStudentAssetUrl('vendor/libs/perfect-scrollbar/perfect-scrollbar.css'); ?>" />
    
    <!-- Helpers -->
    <script src="<?php echo getStudentAssetUrl('vendor/js/helpers.js'); ?>"></script>
    <script src="<?php echo getStudentAssetUrl('js/config.js'); ?>"></script>
</head>

<body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="dashboard.php" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <svg width="25" viewBox="0 0 25 42" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                <defs>
                                    <path d="M13.7918663,0.358365126 L3.39788168,7.44174259 C0.566865006,9.69408886 -0.379795268,12.4788597 0.557900856,15.7960551 C0.68998853,16.2305145 1.09562888,17.7872135 3.12357076,19.2293357 C3.8146334,19.7207684 5.32369333,20.3834223 7.65075054,21.2172976 L7.59773219,21.2525164 L2.63468769,24.5493413 C0.445452254,26.3002124 0.0884951797,28.5083815 1.56381646,31.1738486 C2.83770406,32.8170431 5.20850219,33.2640127 7.09180128,32.5391577 C8.347334,32.0559211 11.4559176,30.0014999 16.4175519,26.3747182 C18.0338572,24.4997857 18.6973423,22.4544883 18.4080071,20.2388261 C17.963753,17.5346866 16.1776345,15.5799961 13.0496516,14.3747546 L10.9194936,13.4715819 L18.6192054,7.984237 L13.7918663,0.358365126 Z" id="path-1"></path>
                                    <path d="M5.47320593,6.00457225 C4.05321814,8.216144 4.36334763,10.0722806 6.40359441,11.5729822 C8.61520715,12.571656 10.0999176,13.2171421 10.8577257,13.5094407 L15.5088241,14.433041 L18.6192054,7.984237 C15.5364148,3.11535317 13.9273018,0.573395879 13.7918663,0.358365126 C13.5790555,0.511491653 10.8061687,2.3935607 5.47320593,6.00457225 Z" id="path-3"></path>
                                    <path d="M7.50063644,21.2294429 L12.2034064,18.2294047 C16.007,15.5303946 16.007,9.48238685 12.2034064,6.78337676 C8.39981282,4.08436667 1.5,4.08436667 1.5,10.1323745 C1.5,16.1803823 8.39981282,19.8793924 12.2034064,17.1803823 L16.007,14.4813722 L7.50063644,21.2294429 Z" id="path-4"></path>
                                </defs>
                                <g id="g-app-brand" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <g id="Brand-Logo" transform="translate(-27.000000, -15.000000)">
                                        <g id="Icon" transform="translate(27.000000, 15.000000)">
                                            <g id="Mask" transform="translate(0.000000, 8.000000)">
                                                <mask id="mask-2" fill="white">
                                                    <use xlink:href="#path-1"></use>
                                                </mask>
                                                <use fill="#696cff" xlink:href="#path-1"></use>
                                                <g id="Path-3" mask="url(#mask-2)">
                                                    <use fill="#696cff" xlink:href="#path-3"></use>
                                                    <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#path-3"></use>
                                                </g>
                                                <g id="Path-4" mask="url(#mask-2)">
                                                    <use fill="#696cff" xlink:href="#path-4"></use>
                                                    <use fill-opacity="0.2" fill="#FFFFFF" xlink:href="#path-4"></use>
                                                </g>
                                            </g>
                                        </g>
                                    </g>
                                </g>
                            </svg>
                        </span>
                        <span class="app-brand-text demo menu-text fw-bolder ms-2">QR Attendance</span>
                    </a>

                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <!-- Dashboard -->
                    <li class="menu-item">
                        <a href="dashboard.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Dashboard</div>
                        </a>
                    </li>

                    <!-- Attendance -->
                    <li class="menu-item">
                        <a href="attendance.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-calendar-check"></i>
                            <div data-i18n="Attendance">Attendance</div>
                        </a>
                    </li>

                    <!-- Assignments -->
                    <li class="menu-item">
                        <a href="assignments.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-book"></i>
                            <div data-i18n="Assignments">Assignments</div>
                        </a>
                    </li>

                    <!-- Quizzes -->
                    <li class="menu-item">
                        <a href="quizzes.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-edit"></i>
                            <div data-i18n="Quizzes">Quizzes</div>
                        </a>
                    </li>

                    <!-- Profile -->
                    <li class="menu-item active">
                        <a href="profile.php" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-user"></i>
                            <div data-i18n="Profile">Profile</div>
                        </a>
                    </li>
                </ul>
            </aside>
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                            <i class="bx bx-menu"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                        <!-- Search -->
                        <div class="navbar-nav align-items-center">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..." aria-label="Search..." />
                            </div>
                        </div>
                        <!-- /Search -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown user-dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="<?php echo getStudentAssetUrl('img/avatars/1.png'); ?>" alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="profile.php">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?php echo getStudentAssetUrl('img/avatars/1.png'); ?>" alt class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?php echo sanitizeOutput($current_student['name']); ?></h6>
                                                    <small class="text-muted"><?php echo sanitizeOutput($current_student['student_id']); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider my-1"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="profile.php">
                                            <i class="bx bx-user me-2"></i>
                                            <span class="align-middle">My Profile</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="logout.php">
                                            <i class="bx bx-power-off me-2"></i>
                                            <span class="align-middle">Log Out</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <!--/ User -->
                        </ul>
                    </div>
                </nav>
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <h4 class="fw-bold py-3 mb-4">
                            <span class="text-muted fw-light">Student /</span> Profile
                        </h4>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bx bx-error me-2"></i>
                                <?php echo sanitizeOutput($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bx bx-check me-2"></i>
                                <?php echo sanitizeOutput($success_message); ?>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <!-- Profile Overview -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <h5 class="card-header">Profile Overview</h5>
                                    <div class="card-body">
                                        <div class="d-flex align-items-start align-items-sm-center gap-4">
                                            <img src="<?php echo getStudentAssetUrl('img/avatars/1.png'); ?>" alt="user-avatar" class="d-block rounded" height="100" width="100" id="uploadedAvatar" />
                                            <div class="button-wrapper">
                                                <label for="upload" class="btn btn-primary me-2 mb-4" tabindex="0">
                                                    <span class="d-none d-sm-block">Upload new photo</span>
                                                    <i class="bx bx-upload d-block d-sm-none"></i>
                                                    <input type="file" id="upload" class="account-file-input" hidden accept="image/png, image/jpeg" />
                                                </label>
                                                <button type="button" class="btn btn-outline-secondary account-image-reset mb-4">
                                                    <i class="bx bx-reset d-block d-sm-none"></i>
                                                    <span class="d-none d-sm-block">Reset</span>
                                                </button>
                                                <p class="text-muted mb-0">Allowed JPG, GIF or PNG. Max size of 800K</p>
                                            </div>
                                        </div>
                                    </div>
                                    <hr class="my-0" />
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="mb-3 col-md-6">
                                                <label for="student_id" class="form-label">Student ID</label>
                                                <input class="form-control" type="text" id="student_id" name="student_id" value="<?php echo sanitizeOutput($current_student['student_id']); ?>" readonly />
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="username" class="form-label">Username</label>
                                                <input class="form-control" type="text" id="username" name="username" value="<?php echo sanitizeOutput($current_student['username']); ?>" readonly />
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="program" class="form-label">Program</label>
                                                <input class="form-control" type="text" id="program" name="program" value="<?php echo sanitizeOutput($current_student['program']); ?>" readonly />
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="shift" class="form-label">Shift</label>
                                                <input class="form-control" type="text" id="shift" name="shift" value="<?php echo sanitizeOutput($current_student['shift']); ?>" readonly />
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="year_level" class="form-label">Year Level</label>
                                                <input class="form-control" type="text" id="year_level" name="year_level" value="<?php echo sanitizeOutput($current_student['year_level']); ?>" readonly />
                                            </div>
                                            <div class="mb-3 col-md-6">
                                                <label for="section" class="form-label">Section</label>
                                                <input class="form-control" type="text" id="section" name="section" value="<?php echo sanitizeOutput($current_student['section']); ?>" readonly />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Account Settings -->
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <h5 class="card-header">Account Settings</h5>
                                    <div class="card-body">
                                        <form id="formAccountSettings" method="POST">
                                            <input type="hidden" name="action" value="update_profile">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            
                                            <div class="row">
                                                <div class="mb-3 col-md-6">
                                                    <label for="name" class="form-label">Full Name</label>
                                                    <input class="form-control" type="text" id="name" name="name" value="<?php echo sanitizeOutput($current_student['name']); ?>" required />
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                    <label for="email" class="form-label">E-mail</label>
                                                    <input class="form-control" type="text" id="email" name="email" value="<?php echo sanitizeOutput($current_student['email']); ?>" required />
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                    <label for="phone" class="form-label">Phone Number</label>
                                                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo sanitizeOutput($current_student['phone']); ?>" />
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                    <label for="admission_year" class="form-label">Admission Year</label>
                                                    <input class="form-control" type="text" id="admission_year" name="admission_year" value="<?php echo sanitizeOutput($current_student['admission_year']); ?>" readonly />
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <button type="submit" class="btn btn-primary me-2">Save changes</button>
                                                <button type="reset" class="btn btn-outline-secondary">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Change Password -->
                                <div class="card">
                                    <h5 class="card-header">Change Password</h5>
                                    <div class="card-body">
                                        <form id="formChangePassword" method="POST">
                                            <input type="hidden" name="action" value="change_password">
                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                            
                                            <div class="row">
                                                <div class="mb-3 col-md-6">
                                                    <label for="current_password" class="form-label">Current Password</label>
                                                    <input class="form-control" type="password" id="current_password" name="current_password" required />
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                    <label for="new_password" class="form-label">New Password</label>
                                                    <input class="form-control" type="password" id="new_password" name="new_password" required />
                                                </div>
                                                <div class="mb-3 col-md-6">
                                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                    <input class="form-control" type="password" id="confirm_password" name="confirm_password" required />
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <button type="submit" class="btn btn-primary me-2">Change Password</button>
                                                <button type="reset" class="btn btn-outline-secondary">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl d-flex flex-wrap justify-content-between py-2 flex-md-row flex-column">
                            <div class="mb-2 mb-md-0">
                                © <script>document.write(new Date().getFullYear())</script>, made with ❤️ by
                                <a href="https://themeselection.com" target="_blank" class="footer-link fw-bolder">ThemeSelection</a>
                            </div>
                            <div>
                                <a href="https://themeselection.com/license/" class="footer-link me-4" target="_blank">License</a>
                                <a href="https://themeselection.com/" target="_blank" class="footer-link me-4">Documentation</a>
                                <a href="https://github.com/themeselection/sneat-html-admin-template-free/issues" target="_blank" class="footer-link me-4">Support</a>
                            </div>
                        </div>
                    </footer>
                    <!-- / Footer -->

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="<?php echo getStudentAssetUrl('vendor/libs/jquery/jquery.js'); ?>"></script>
    <script src="<?php echo getStudentAssetUrl('vendor/libs/popper/popper.js'); ?>"></script>
    <script src="<?php echo getStudentAssetUrl('vendor/js/bootstrap.js'); ?>"></script>
    <script src="<?php echo getStudentAssetUrl('vendor/libs/perfect-scrollbar/perfect-scrollbar.js'); ?>"></script>

    <script src="<?php echo getStudentAssetUrl('vendor/js/menu.js'); ?>"></script>
    <!-- endbuild -->

    <!-- Main JS -->
    <script src="<?php echo getStudentAssetUrl('js/main.js'); ?>"></script>

    <!-- Page JS -->
    <script src="<?php echo getStudentAssetUrl('js/pages-account-settings-account.js'); ?>"></script>
</body>
</html>
