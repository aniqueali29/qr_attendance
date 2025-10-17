<?php
/**
 * Student Dashboard
 * Main dashboard with overview widgets and analytics
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require student authentication
if (DEBUG_MODE) {
    error_log("Dashboard accessed - Session ID: " . session_id());
    error_log("Dashboard - isStudentLoggedIn(): " . (isStudentLoggedIn() ? 'TRUE' : 'FALSE'));
    error_log("Dashboard - Session variables: " . json_encode($_SESSION));
}
requireStudentAuth();

// Get current student data
$current_student = getCurrentStudent();
if (!$current_student) {
    logoutStudent();
    header('Location: login.php');
    exit();
}

// Get student statistics
$attendance_stats = getStudentAttendanceStats($current_student['student_id']);
$recent_attendance = getStudentRecentAttendance($current_student['student_id'], 5);
$upcoming_events = getStudentUpcomingEvents($current_student['student_id']);

$pageTitle = "Dashboard - " . $current_student['name'] . " (" . $current_student['student_id'] . ")";
$currentPage = "dashboard";
?>
<!doctype html>
<html lang="en" class="layout-menu-fixed layout-compact" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    
    <title><?php echo sanitizeOutput($pageTitle); ?> - <?php echo STUDENT_SITE_NAME; ?></title>
    <meta name="description" content="Student Dashboard - QR Attendance System" />
    
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
    <link rel="stylesheet" href="<?php echo getStudentAssetUrl('vendor/libs/apex-charts/apex-charts.css'); ?>" />
    
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
                        <span class="app-brand-text demo menu-text fw-bolder ms-2">Dashboard</span>
                    </a>

                    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
                        <i class="bx bx-chevron-left bx-sm align-middle"></i>
                    </a>
                </div>

                <div class="menu-inner-shadow"></div>

                <ul class="menu-inner py-1">
                    <!-- Dashboard -->
                    <li class="menu-item active">
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
                    <li class="menu-item">
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
                        <div class="row">
                            <div class="col-lg-8 mb-4 order-0">
                                <div class="card">
                                    <div class="d-flex align-items-end row">
                                        <div class="col-sm-7">
                                            <div class="card-body">
                                                <h5 class="card-title text-primary">Welcome back, <?php echo sanitizeOutput($current_student['name']); ?>! 🎉</h5>
                                                <p class="mb-4">
                                                    You have <span class="fw-bold"><?php echo $attendance_stats['monthly']['present_days'] ?? 0; ?></span> present days this month. 
                                                    Your overall attendance is <span class="fw-bold"><?php echo number_format($attendance_stats['overall_percentage'] ?? 0, 1); ?>%</span>.
                                                </p>
                                                <a href="attendance.php" class="btn btn-sm btn-outline-primary">View Attendance</a>
                                            </div>
                                        </div>
                                        <div class="col-sm-5 text-center text-sm-left">
                                            <div class="card-body pb-0 px-0 px-md-4">
                                                <img src="<?php echo getStudentAssetUrl('img/illustrations/man-with-laptop.png'); ?>" height="140" alt="View Badge User" data-app-dark-img="illustrations/man-with-laptop-dark.png" data-app-light-img="illustrations/man-with-laptop-light.png" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-4 order-1">
                                <div class="row">
                                    <div class="col-lg-6 col-md-12 col-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="card-title d-flex align-items-start justify-content-between">
                                                    <div class="avatar flex-shrink-0">
                                                        <i class="bx bx-calendar-check text-primary"></i>
                                                    </div>
                                                </div>
                                                <span class="fw-semibold d-block mb-1">Present Days</span>
                                                <h3 class="card-title mb-2"><?php echo $attendance_stats['monthly']['present_days'] ?? 0; ?></h3>
                                                <small class="text-success fw-semibold">
                                                    <i class="bx bx-up-arrow-alt"></i> This month
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6 col-md-12 col-6 mb-4">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="card-title d-flex align-items-start justify-content-between">
                                                    <div class="avatar flex-shrink-0">
                                                        <i class="bx bx-trending-up text-info"></i>
                                                    </div>
                                                </div>
                                                <span class="fw-semibold d-block mb-1">Attendance %</span>
                                                <h3 class="card-title mb-2"><?php echo number_format($attendance_stats['overall_percentage'] ?? 0, 1); ?>%</h3>
                                                <small class="text-success fw-semibold">
                                                    <i class="bx bx-up-arrow-alt"></i> Overall
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Recent Attendance -->
                            <div class="col-md-6 col-lg-4 col-xl-4 order-0 mb-4">
                                <div class="card h-100">
                                    <div class="card-header d-flex align-items-center justify-content-between pb-0">
                                        <div class="card-title mb-0">
                                            <h5 class="m-0 me-2">Recent Attendance</h5>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn p-0" type="button" id="attendanceDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="attendanceDropdown">
                                                <a class="dropdown-item" href="attendance.php">View All</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="d-flex flex-column align-items-center gap-1">
                                                <h2 class="mb-2"><?php echo count($recent_attendance); ?></h2>
                                                <span>Recent Records</span>
                                            </div>
                                            <?php if (count($recent_attendance) > 0 && ($attendance_stats['monthly']['total_days'] > 0)): ?>
                                                <div id="orderStatisticsChart"></div>
                                            <?php else: ?>
                                                <div class="d-flex flex-column align-items-center gap-1">
                                                    <div class="avatar">
                                                        <span class="avatar-initial rounded bg-label-secondary">
                                                            <i class="bx bx-calendar"></i>
                                                        </span>
                                                    </div>
                                                    <small class="text-muted">No data</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <ul class="p-0 m-0">
                                            <?php if (empty($recent_attendance)): ?>
                                                <li class="d-flex mb-4 pb-1">
                                                    <div class="empty-state" style="min-height: 120px; padding: 1rem;">
                                                        <div class="avatar" style="width: 3rem; height: 3rem;">
                                                            <span class="avatar-initial rounded bg-label-secondary">
                                                                <i class="bx bx-time"></i>
                                                            </span>
                                                        </div>
                                                        <h6 class="text-muted">No attendance records found</h6>
                                                        <small class="text-muted">Your attendance will appear here</small>
                                                    </div>
                                                </li>
                                            <?php else: ?>
                                                <?php foreach ($recent_attendance as $record): ?>
                                                    <li class="d-flex mb-4 pb-1">
                                                        <div class="avatar flex-shrink-0 me-3">
                                                            <span class="avatar-initial rounded bg-label-<?php echo getStatusBadgeClass($record['status']); ?>">
                                                                <i class="bx bx-<?php echo getStatusIcon($record['status']); ?>"></i>
                                                            </span>
                                                        </div>
                                                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                            <div class="me-2">
                                                                <h6 class="mb-0"><?php echo formatDate($record['date']); ?></h6>
                                                                <small class="text-muted"><?php echo formatTime($record['time']); ?></small>
                                                            </div>
                                                            <div class="user-progress d-flex align-items-center gap-1">
                                                                <span class="badge bg-label-<?php echo getStatusBadgeClass($record['status']); ?>">
                                                                    <?php echo $record['status']; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <!--/ Recent Attendance -->

                            <!-- Student Info -->
                            <div class="col-md-6 col-lg-4 order-1 mb-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5 class="card-title m-0 me-2">Student Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <ul class="p-0 m-0">
                                            <li class="d-flex mb-4 pb-1">
                                                <div class="avatar flex-shrink-0 me-3">
                                                    <span class="avatar-initial rounded bg-label-primary">
                                                        <i class="bx bx-user"></i>
                                                    </span>
                                                </div>
                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0">Student ID</h6>
                                                        <small class="text-muted"><?php echo sanitizeOutput($current_student['student_id']); ?></small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="d-flex mb-4 pb-1">
                                                <div class="avatar flex-shrink-0 me-3">
                                                    <span class="avatar-initial rounded bg-label-success">
                                                        <i class="bx bx-book"></i>
                                                    </span>
                                                </div>
                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0">Program</h6>
                                                        <small class="text-muted"><?php echo sanitizeOutput($current_student['program']); ?></small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="d-flex mb-4 pb-1">
                                                <div class="avatar flex-shrink-0 me-3">
                                                    <span class="avatar-initial rounded bg-label-info">
                                                        <i class="bx bx-time"></i>
                                                    </span>
                                                </div>
                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0">Shift</h6>
                                                        <small class="text-muted"><?php echo sanitizeOutput($current_student['shift']); ?></small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="d-flex mb-4 pb-1">
                                                <div class="avatar flex-shrink-0 me-3">
                                                    <span class="avatar-initial rounded bg-label-warning">
                                                        <i class="bx bx-layer"></i>
                                                    </span>
                                                </div>
                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0">Year Level</h6>
                                                        <small class="text-muted"><?php echo sanitizeOutput($current_student['year_level']); ?></small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="d-flex mb-4 pb-1">
                                                <div class="avatar flex-shrink-0 me-3">
                                                    <span class="avatar-initial rounded bg-label-secondary">
                                                        <i class="bx bx-group"></i>
                                                    </span>
                                                </div>
                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0">Section</h6>
                                                        <small class="text-muted"><?php echo sanitizeOutput($current_student['section']); ?></small>
                                                    </div>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <!--/ Student Info -->

                            <!-- Upcoming Events -->
                            <div class="col-md-6 col-lg-4 order-2 mb-4">
                                <div class="card h-100">
                                    <div class="card-header d-flex align-items-center justify-content-between pb-0">
                                        <div class="card-title mb-0">
                                            <h5 class="m-0 me-2">Upcoming Events</h5>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn p-0" type="button" id="eventsDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="eventsDropdown">
                                                <a class="dropdown-item" href="assignments.php">View Assignments</a>
                                                <a class="dropdown-item" href="quizzes.php">View Quizzes</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($upcoming_events)): ?>
                                            <div class="empty-state">
                                                <div class="avatar">
                                                    <span class="avatar-initial rounded bg-label-secondary">
                                                        <i class="bx bx-calendar-event"></i>
                                                    </span>
                                                </div>
                                                <h6 class="text-muted">No upcoming events</h6>
                                                <small class="text-muted">Your assignments and quizzes will appear here</small>
                                            </div>
                                        <?php else: ?>
                                            <ul class="p-0 m-0">
                                                <?php foreach ($upcoming_events as $event): ?>
                                                    <li class="d-flex mb-4 pb-1">
                                                        <div class="avatar flex-shrink-0 me-3">
                                                            <span class="avatar-initial rounded bg-label-<?php echo $event['type'] === 'assignment' ? 'primary' : 'warning'; ?>">
                                                                <i class="bx bx-<?php echo $event['type'] === 'assignment' ? 'book' : 'edit'; ?>"></i>
                                                            </span>
                                                        </div>
                                                        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                            <div class="me-2">
                                                                <h6 class="mb-0"><?php echo sanitizeOutput($event['name']); ?></h6>
                                                                <small class="text-muted"><?php echo ucfirst($event['type']); ?></small>
                                                            </div>
                                                            <div class="user-progress d-flex align-items-center gap-1">
                                                                <span class="badge bg-label-<?php echo getUrgencyClass($event['date']); ?>">
                                                                    <?php 
                                                                    $days = getDaysUntilDue($event['date']);
                                                                    if ($days < 0) {
                                                                        echo 'Overdue';
                                                                    } elseif ($days == 0) {
                                                                        echo 'Due Today';
                                                                    } elseif ($days == 1) {
                                                                        echo 'Due Tomorrow';
                                                                    } else {
                                                                        echo $days . ' days';
                                                                    }
                                                                    ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <!--/ Upcoming Events -->
                        </div>

                        <!-- Attendance Chart Section -->
                        <div class="row">
                            <div class="col-12 mb-4">
                                <div class="card">
                                    <div class="card-header d-flex align-items-center justify-content-between">
                                        <h5 class="card-title m-0 me-2">Attendance Trend (Last 6 Months)</h5>
                                        <div class="dropdown">
                                            <button class="btn p-0" type="button" id="chartDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <i class="bx bx-dots-vertical-rounded"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="chartDropdown">
                                                <a class="dropdown-item" href="attendance.php">View Detailed Report</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div id="attendanceChart" style="height: 300px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--/ Attendance Chart Section -->

                        <!-- Performance Summary -->
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title m-0 me-2">Academic Performance</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php 
                                        $performance = getStudentPerformanceSummary($current_student['student_id']);
                                        ?>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar flex-shrink-0 me-3">
                                                        <span class="avatar-initial rounded bg-label-primary">
                                                            <i class="bx bx-book"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0">Assignments</h6>
                                                        <small class="text-muted"><?php echo $performance['assignments']['total']; ?> completed</small>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="fw-semibold">Average Grade: </span>
                                                    <span class="text-primary"><?php echo $performance['assignments']['average_grade']; ?>%</span>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar flex-shrink-0 me-3">
                                                        <span class="avatar-initial rounded bg-label-warning">
                                                            <i class="bx bx-edit"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0">Quizzes</h6>
                                                        <small class="text-muted"><?php echo $performance['quizzes']['total']; ?> taken</small>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="fw-semibold">Best Score: </span>
                                                    <span class="text-warning"><?php echo $performance['quizzes']['best_score']; ?>%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title m-0 me-2">Monthly Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar flex-shrink-0 me-3">
                                                        <span class="avatar-initial rounded bg-label-success">
                                                            <i class="bx bx-check-circle"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0">Present Days</h6>
                                                        <small class="text-muted">This month</small>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="fw-semibold"><?php echo $attendance_stats['monthly']['present_days'] ?? 0; ?></span>
                                                    <span class="text-muted">/ <?php echo $attendance_stats['monthly']['total_days'] ?? 0; ?> days</span>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar flex-shrink-0 me-3">
                                                        <span class="avatar-initial rounded bg-label-danger">
                                                            <i class="bx bx-x-circle"></i>
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0">Absent Days</h6>
                                                        <small class="text-muted">This month</small>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <span class="fw-semibold"><?php echo $attendance_stats['monthly']['absent_days'] ?? 0; ?></span>
                                                    <span class="text-muted">days</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--/ Performance Summary -->
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

    <!-- Vendors JS -->
    <script src="<?php echo getStudentAssetUrl('vendor/libs/apex-charts/apexcharts.js'); ?>"></script>

    <!-- Main JS -->
    <script src="<?php echo getStudentAssetUrl('js/main.js'); ?>"></script>

    <!-- Page JS -->
    <script src="<?php echo getStudentAssetUrl('js/dashboards-analytics.js'); ?>"></script>
    
    <!-- Custom Styles for Empty States -->
    <style>
        .empty-state {
            min-height: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem 1rem;
        }
        
        .empty-state .avatar {
            width: 4rem;
            height: 4rem;
            margin-bottom: 1rem;
        }
        
        .empty-state .avatar-initial {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .empty-state h6 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .empty-state small {
            opacity: 0.8;
            line-height: 1.4;
        }
    </style>

    <!-- Attendance Chart -->
    <script>
        // Get attendance chart data from PHP
        <?php 
        $chart_data = getStudentAttendanceChartData($current_student['student_id']);
        ?>
        const attendanceChartData = {
            months: <?php echo json_encode($chart_data['months']); ?>,
            percentages: <?php echo json_encode($chart_data['percentages']); ?>
        };

        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof ApexCharts !== 'undefined') {
                // Order Statistics Chart (Recent Attendance widget)
                const orderStatisticsChartEl = document.querySelector('#orderStatisticsChart');
                if (orderStatisticsChartEl && <?php echo count($recent_attendance); ?> > 0 && <?php echo $attendance_stats['monthly']['total_days'] ?? 0; ?> > 0) {
                    // Use overall attendance percentage instead of just recent records
                    const overallPercentage = <?php echo $attendance_stats['overall_percentage'] ?? 0; ?>;
                    const monthlyTotal = <?php echo $attendance_stats['monthly']['total_days'] ?? 0; ?>;
                    const monthlyPresent = <?php echo $attendance_stats['monthly']['present_days'] ?? 0; ?>;
                    const monthlyAbsent = <?php echo $attendance_stats['monthly']['absent_days'] ?? 0; ?>;
                    const monthlyCheckin = <?php echo $attendance_stats['monthly']['checkin_days'] ?? 0; ?>;
                    
                    // For chart display, use overall percentage if available, otherwise calculate from monthly data
                    const displayPercentage = overallPercentage > 0 ? overallPercentage : 
                        (monthlyTotal > 0 ? Math.round((monthlyPresent / monthlyTotal) * 100) : 0);
                    
                    // Chart data calculated from real attendance statistics
                    
                    // Clear any existing chart
                    orderStatisticsChartEl.innerHTML = '';
                    
                    const orderStatisticsChart = new ApexCharts(orderStatisticsChartEl, {
                        chart: {
                            type: 'donut',
                            height: 85,
                            width: 85,
                            sparkline: {
                                enabled: true
                            }
                        },
                        labels: ['Present', 'Absent', 'Check-in'],
                        series: [monthlyPresent, monthlyAbsent, monthlyCheckin],
                        colors: ['#71dd37', '#ff3e1d', '#03c3ec'],
                        stroke: {
                            width: 0
                        },
                        dataLabels: {
                            enabled: false
                        },
                        legend: {
                            show: false
                        },
                        plotOptions: {
                            pie: {
                                donut: {
                                    size: '70%',
                                    labels: {
                                        show: true,
                                        name: {
                                            show: false
                                        },
                                        value: {
                                            show: true,
                                            fontSize: '12px',
                                            fontWeight: 600,
                                            color: '#8592a3',
                                            formatter: function(val) {
                                                return displayPercentage + '%';
                                            }
                                        },
                                        total: {
                                            show: false
                                        }
                                    }
                                }
                            }
                        },
                        tooltip: {
                            enabled: false
                        }
                    });
                    orderStatisticsChart.render();
                }

                // Main Attendance Trend Chart
                const attendanceChartEl = document.querySelector("#attendanceChart");
                if (attendanceChartEl) {
                    const attendanceChartOptions = {
                        series: [{
                            name: 'Attendance %',
                            data: attendanceChartData.percentages
                        }],
                        chart: {
                            type: 'line',
                            height: 300,
                            toolbar: {
                                show: false
                            }
                        },
                        colors: ['#696cff'],
                        stroke: {
                            curve: 'smooth',
                            width: 3
                        },
                        xaxis: {
                            categories: attendanceChartData.months,
                            labels: {
                                style: {
                                    colors: '#8592a3'
                                }
                            }
                        },
                        yaxis: {
                            min: 0,
                            max: 100,
                            labels: {
                                style: {
                                    colors: '#8592a3'
                                },
                                formatter: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        grid: {
                            borderColor: '#e7eef7',
                            strokeDashArray: 5
                        },
                        tooltip: {
                            y: {
                                formatter: function(value) {
                                    return value + '%';
                                }
                            }
                        },
                        markers: {
                            size: 5,
                            colors: ['#696cff'],
                            strokeColors: '#fff',
                            strokeWidth: 2,
                            hover: {
                                size: 7
                            }
                        }
                    };

                    const attendanceChart = new ApexCharts(attendanceChartEl, attendanceChartOptions);
                    attendanceChart.render();
                }
            }
        });
    </script>
</body>
</html>
