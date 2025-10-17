<?php
/**
 * Student Attendance Page
 * Attendance tracking and history view
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

// Get attendance data
$attendance_stats = getStudentAttendanceStats($current_student['student_id']);
$recent_attendance = getStudentRecentAttendance($current_student['student_id'], 50);

$pageTitle = "Attendance - " . $current_student['name'];
$currentPage = "attendance";
?>
<!doctype html>
<html lang="en" class="layout-menu-fixed layout-compact" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    
    <title><?php echo sanitizeOutput($pageTitle); ?> - <?php echo STUDENT_SITE_NAME; ?></title>
    <meta name="description" content="Student Attendance - QR Attendance System" />
    
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
    
    <!-- Responsive Styles -->
    <style>
        /* Mobile-first responsive improvements */
        @media (max-width: 576px) {
            .card-body {
                padding: 1rem;
            }
            
            .card-title {
                font-size: 1.1rem;
            }
            
            .avatar {
                width: 2rem;
                height: 2rem;
            }
            
            .avatar i {
                font-size: 1.2rem;
            }
            
            .btn-sm {
                padding: 0.375rem 0.75rem;
                font-size: 0.875rem;
            }
            
            .table-responsive {
                border-radius: 0.375rem;
            }
        }
        
        @media (max-width: 768px) {
            .container-xxl {
                padding-left: 1rem;
                padding-right: 1rem;
            }
            
            .card {
                margin-bottom: 1rem;
            }
            
            .navbar-nav .dropdown-menu {
                min-width: 200px;
            }
        }
        
        @media (max-width: 992px) {
            .layout-navbar {
                padding: 0.5rem 1rem;
            }
            
            .card-header {
                padding: 1rem;
            }
        }
        
        /* Ensure proper text wrapping and truncation */
        .text-truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        /* Improve card heights on mobile */
        @media (max-width: 576px) {
            .card.h-100 {
                min-height: auto;
            }
        }
        
        /* Better spacing for mobile cards */
        .d-lg-none .card {
            border: 1px solid rgba(0,0,0,0.125);
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        
        /* Responsive badge sizing */
        @media (max-width: 576px) {
            .badge {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
        }
        
        /* Filter dropdown styles */
        .filter-option.active {
            background-color: var(--bs-primary);
            color: white;
        }
        
        .filter-option.active i {
            color: white;
        }
        
        .filter-option:hover {
            background-color: var(--bs-light);
        }
        
        .filter-option.active:hover {
            background-color: var(--bs-primary);
            color: white;
        }
        
        /* Loading state for filter button */
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* Spinner animation */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .bx-spin {
            animation: spin 1s linear infinite;
        }
    </style>
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
                    <li class="menu-item active">
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

                    <div class="navbar-nav-right d-flex align-items-center w-100" id="navbar-collapse">
                        <!-- Search - Hidden on mobile -->
                        <div class="navbar-nav align-items-center d-none d-md-flex">
                            <div class="nav-item d-flex align-items-center">
                                <i class="bx bx-search fs-4 lh-0"></i>
                                <input type="text" class="form-control border-0 shadow-none" placeholder="Search..." aria-label="Search..." />
                            </div>
                        </div>
                        <!-- /Search -->

                        <ul class="navbar-nav flex-row align-items-center ms-auto">
                            <!-- User -->
                            <li class="nav-item navbar-dropdown dropdown user-dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow d-flex align-items-center" href="javascript:void(0);" data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="<?php echo getStudentAssetUrl('img/avatars/1.png'); ?>" alt class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                    <!-- Show name on larger screens -->
                                    <span class="ms-2 d-none d-lg-block text-truncate" style="max-width: 150px;">
                                        <?php echo sanitizeOutput($current_student['name']); ?>
                                    </span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="profile.php">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="<?php echo getStudentAssetUrl('img/avatars/1.png'); ?>" alt class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0 text-truncate"><?php echo sanitizeOutput($current_student['name']); ?></h6>
                                                    <small class="text-muted"><?php echo sanitizeOutput($current_student['student_id']); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider my-1"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center" href="profile.php">
                                            <i class="bx bx-user me-2"></i>
                                            <span class="align-middle">My Profile</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center" href="logout.php">
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
                        <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between mb-4">
                            <div class="flex-grow-1">
                                <h4 class="fw-bold py-3 mb-2 mb-sm-0">
                                    <span class="text-muted fw-light d-block d-sm-inline">Student /</span> 
                                    <span class="d-block d-sm-inline">Attendance</span>
                                </h4>
                            </div>
                            <!-- Quick Actions for Mobile -->
                            <div class="d-flex gap-2 d-sm-none w-100">
                                <button class="btn btn-outline-primary btn-sm flex-fill" type="button">
                                    <i class="bx bx-refresh me-1"></i>
                                    Refresh
                                </button>
                                <button class="btn btn-primary btn-sm flex-fill" type="button">
                                    <i class="bx bx-download me-1"></i>
                                    Export
                                </button>
                            </div>
                        </div>

                        <!-- Statistics Cards -->
                        <div class="row g-3 mb-4">
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                                <div class="card h-100">
                                    <div class="card-body d-flex flex-column">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-calendar-check text-primary fs-4"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <span class="fw-semibold d-block mb-1 text-truncate">Present Days</span>
                                            <h3 class="card-title mb-2 text-nowrap"><?php echo $attendance_stats['monthly']['present_days'] ?? 0; ?></h3>
                                            <small class="text-success fw-semibold d-flex align-items-center">
                                                <i class="bx bx-up-arrow-alt me-1"></i> 
                                                <span class="text-truncate">This month</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                                <div class="card h-100">
                                    <div class="card-body d-flex flex-column">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-x-circle text-danger fs-4"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <span class="fw-semibold d-block mb-1 text-truncate">Absent Days</span>
                                            <h3 class="card-title mb-2 text-nowrap"><?php echo $attendance_stats['monthly']['absent_days'] ?? 0; ?></h3>
                                            <small class="text-danger fw-semibold d-flex align-items-center">
                                                <i class="bx bx-down-arrow-alt me-1"></i> 
                                                <span class="text-truncate">This month</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                                <div class="card h-100">
                                    <div class="card-body d-flex flex-column">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-trending-up text-info fs-4"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <span class="fw-semibold d-block mb-1 text-truncate">Attendance %</span>
                                            <h3 class="card-title mb-2 text-nowrap"><?php echo number_format($attendance_stats['overall_percentage'] ?? 0, 1); ?>%</h3>
                                            <small class="text-success fw-semibold d-flex align-items-center">
                                                <i class="bx bx-up-arrow-alt me-1"></i> 
                                                <span class="text-truncate">Overall</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
                                <div class="card h-100">
                                    <div class="card-body d-flex flex-column">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-calendar text-warning fs-4"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <span class="fw-semibold d-block mb-1 text-truncate">Total Days</span>
                                            <h3 class="card-title mb-2 text-nowrap"><?php echo $attendance_stats['monthly']['total_days'] ?? 0; ?></h3>
                                            <small class="text-muted fw-semibold d-flex align-items-center">
                                                <i class="bx bx-calendar me-1"></i> 
                                                <span class="text-truncate">This month</span>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance History -->
                        <div class="card">
                            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                                <h5 class="card-title m-0">Attendance History</h5>
                                <div class="dropdown position-relative">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" id="transactionID">
                                        <i class="bx bx-filter-alt me-1"></i>
                                        <span class="d-none d-sm-inline" id="filter-text">Filter</span>
                                        <i class="bx bx-chevron-down ms-1"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end position-absolute" id="filter-dropdown" style="display: none; top: 100%; right: 0; z-index: 1000; min-width: 200px;">
                                        <a class="dropdown-item filter-option active" href="javascript:void(0);" data-filter="all" data-days="all">
                                            <i class="bx bx-calendar-check me-2"></i>All Records
                                        </a>
                                        <a class="dropdown-item filter-option" href="javascript:void(0);" data-filter="28days" data-days="28">
                                            <i class="bx bx-calendar me-2"></i>Last 28 Days
                                        </a>
                                        <a class="dropdown-item filter-option" href="javascript:void(0);" data-filter="month" data-days="30">
                                            <i class="bx bx-calendar me-2"></i>Last Month
                                        </a>
                                        <a class="dropdown-item filter-option" href="javascript:void(0);" data-filter="year" data-days="365">
                                            <i class="bx bx-calendar me-2"></i>Last Year
                                        </a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="javascript:void(0);" onclick="refreshAttendanceData()">
                                            <i class="bx bx-refresh me-2"></i>Refresh Data
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <!-- Desktop Table -->
                                <div class="table-responsive d-none d-lg-block">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="px-3 py-3">Date</th>
                                                <th class="px-3 py-3">Time</th>
                                                <th class="px-3 py-3">Status</th>
                                                <th class="px-3 py-3">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-border-bottom-0">
                                            <?php if (empty($recent_attendance)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-5">
                                                        <div class="d-flex flex-column align-items-center">
                                                            <i class="bx bx-calendar-x text-muted" style="font-size: 3rem;"></i>
                                                            <p class="text-muted mt-2 mb-0">No attendance records found</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($recent_attendance as $record): ?>
                                                    <tr>
                                                        <td class="px-3 py-3">
                                                            <div class="d-flex flex-column">
                                                                <span class="fw-semibold"><?php echo date('M d, Y', strtotime($record['date'])); ?></span>
                                                                <small class="text-muted"><?php echo date('l', strtotime($record['date'])); ?></small>
                                                            </div>
                                                        </td>
                                                        <td class="px-3 py-3">
                                                            <span class="badge bg-label-info"><?php echo $record['time']; ?></span>
                                                        </td>
                                                        <td class="px-3 py-3">
                                                            <span class="badge bg-label-<?php echo $record['status'] === 'Present' ? 'success' : ($record['status'] === 'Absent' ? 'danger' : 'info'); ?>">
                                                                <?php echo $record['status']; ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-3 py-3">
                                                            <span class="text-muted small"><?php echo $record['notes'] ? sanitizeOutput($record['notes']) : '-'; ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Cards -->
                                <div class="d-lg-none">
                                    <?php if (empty($recent_attendance)): ?>
                                        <div class="text-center py-5">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="bx bx-calendar-x text-muted" style="font-size: 3rem;"></i>
                                                <p class="text-muted mt-2 mb-0">No attendance records found</p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="p-3">
                                            <?php foreach ($recent_attendance as $index => $record): ?>
                                                <div class="card mb-3 <?php echo $index === 0 ? '' : ''; ?>">
                                                    <div class="card-body p-3">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div class="flex-grow-1">
                                                                <h6 class="card-title mb-1">
                                                                    <?php echo date('M d, Y', strtotime($record['date'])); ?>
                                                                </h6>
                                                                <small class="text-muted">
                                                                    <?php echo date('l', strtotime($record['date'])); ?>
                                                                </small>
                                                            </div>
                                                            <span class="badge bg-label-<?php echo $record['status'] === 'Present' ? 'success' : ($record['status'] === 'Absent' ? 'danger' : 'info'); ?>">
                                                                <?php echo $record['status']; ?>
                                                            </span>
                                                        </div>
                                                        <div class="row g-2">
                                                            <div class="col-6">
                                                                <small class="text-muted d-block">Time</small>
                                                                <span class="badge bg-label-info"><?php echo $record['time']; ?></span>
                                                            </div>
                                                            <div class="col-6">
                                                                <small class="text-muted d-block">Notes</small>
                                                                <span class="small"><?php echo $record['notes'] ? sanitizeOutput($record['notes']) : '-'; ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
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
    
    <!-- Attendance Filter JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing attendance filters...');
            // Initialize filter functionality
            initializeAttendanceFilters();
        });

        function initializeAttendanceFilters() {
            console.log('Initializing attendance filters...');
            
            // Check if the filter button exists
            const filterButton = document.getElementById('transactionID');
            const filterDropdown = document.getElementById('filter-dropdown');
            console.log('Filter button found:', !!filterButton);
            console.log('Filter dropdown found:', !!filterDropdown);
            
            if (!filterButton || !filterDropdown) {
                console.error('Filter elements not found!');
                return;
            }
            
            // Check if filter options exist
            const filterOptions = document.querySelectorAll('.filter-option');
            console.log('Found filter options:', filterOptions.length);
            
            if (filterOptions.length === 0) {
                console.error('No filter options found!');
                return;
            }
            
            // Setup manual dropdown toggle
            filterButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Filter button clicked!');
                
                // Toggle dropdown
                const isVisible = filterDropdown.style.display === 'block';
                filterDropdown.style.display = isVisible ? 'none' : 'block';
                
                console.log('Dropdown visibility:', !isVisible ? 'shown' : 'hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!filterButton.contains(e.target) && !filterDropdown.contains(e.target)) {
                    filterDropdown.style.display = 'none';
                }
            });
            
            // Add click handlers to filter options
            filterOptions.forEach((option, index) => {
                console.log(`Adding click handler to option ${index}:`, option.textContent.trim());
                
                option.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    console.log('Filter option clicked:', this.textContent.trim());
                    
                    // Remove active class from all options
                    filterOptions.forEach(opt => opt.classList.remove('active'));
                    
                    // Add active class to clicked option
                    this.classList.add('active');
                    
                    // Get filter data
                    const filterType = this.getAttribute('data-filter');
                    const days = this.getAttribute('data-days');
                    
                    console.log('Filter type:', filterType, 'Days:', days);
                    
                    // Apply filter
                    applyAttendanceFilter(filterType, days);
                    
                    // Update button text
                    updateFilterButtonText(this.textContent.trim());
                    
                    // Close dropdown
                    filterDropdown.style.display = 'none';
                });
            });
            
            console.log('Filter initialization complete');
        }

        function setupManualDropdown() {
            console.log('Setting up manual dropdown fallback...');
            
            const filterButton = document.getElementById('transactionID');
            const dropdownMenu = filterButton.nextElementSibling;
            
            if (!dropdownMenu || !dropdownMenu.classList.contains('dropdown-menu')) {
                console.error('Dropdown menu not found for manual setup');
                return;
            }
            
            // Hide dropdown initially
            dropdownMenu.style.display = 'none';
            
            // Add click handler to button
            filterButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Toggle dropdown
                const isVisible = dropdownMenu.style.display === 'block';
                dropdownMenu.style.display = isVisible ? 'none' : 'block';
                
                if (!isVisible) {
                    // Position dropdown
                    dropdownMenu.style.position = 'absolute';
                    dropdownMenu.style.top = '100%';
                    dropdownMenu.style.right = '0';
                    dropdownMenu.style.zIndex = '1000';
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!filterButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.style.display = 'none';
                }
            });
            
            console.log('Manual dropdown setup complete');
        }

        function applyAttendanceFilter(filterType, days) {
            // Show loading state
            showLoadingState();
            
            // Calculate date range if needed
            let dateFrom = null;
            let dateTo = null;
            
            if (days !== 'all') {
                const today = new Date();
                dateTo = today.toISOString().split('T')[0];
                
                const fromDate = new Date();
                fromDate.setDate(fromDate.getDate() - parseInt(days));
                dateFrom = fromDate.toISOString().split('T')[0];
            }
            
            // Make AJAX request to get filtered data
            const params = new URLSearchParams({
                action: 'get_attendance_history',
                student_id: '<?php echo $current_student['student_id']; ?>',
                filter_type: filterType,
                days: days
            });
            
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            fetch(`api/student_api.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateAttendanceDisplay(data.attendance);
                        updateStatisticsDisplay(data.stats);
                    } else {
                        console.error('Filter error:', data.message);
                        showErrorMessage('Failed to load filtered data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showErrorMessage('Network error occurred');
                })
                .finally(() => {
                    hideLoadingState();
                });
        }

        function updateAttendanceDisplay(attendanceData) {
            // Update desktop table
            const desktopTable = document.querySelector('.table-responsive.d-none.d-lg-block tbody');
            const mobileCards = document.querySelector('.d-lg-none .p-3');
            
            if (attendanceData.length === 0) {
                // Show no records message
                desktopTable.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center py-5">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bx bx-calendar-x text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-2 mb-0">No attendance records found</p>
                            </div>
                        </td>
                    </tr>
                `;
                
                mobileCards.innerHTML = `
                    <div class="text-center py-5">
                        <div class="d-flex flex-column align-items-center">
                            <i class="bx bx-calendar-x text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-0">No attendance records found</p>
                        </div>
                    </div>
                `;
                return;
            }
            
            // Update desktop table
            desktopTable.innerHTML = attendanceData.map(record => `
                <tr>
                    <td class="px-3 py-3">
                        <div class="d-flex flex-column">
                            <span class="fw-semibold">${formatDate(record.date)}</span>
                            <small class="text-muted">${formatDayOfWeek(record.date)}</small>
                        </div>
                    </td>
                    <td class="px-3 py-3">
                        <span class="badge bg-label-info">${record.time}</span>
                    </td>
                    <td class="px-3 py-3">
                        <span class="badge bg-label-${getStatusColor(record.status)}">
                            ${record.status}
                        </span>
                    </td>
                    <td class="px-3 py-3">
                        <span class="text-muted small">${record.notes || '-'}</span>
                    </td>
                </tr>
            `).join('');
            
            // Update mobile cards
            mobileCards.innerHTML = attendanceData.map((record, index) => `
                <div class="card mb-3">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="flex-grow-1">
                                <h6 class="card-title mb-1">${formatDate(record.date)}</h6>
                                <small class="text-muted">${formatDayOfWeek(record.date)}</small>
                            </div>
                            <span class="badge bg-label-${getStatusColor(record.status)}">
                                ${record.status}
                            </span>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <small class="text-muted d-block">Time</small>
                                <span class="badge bg-label-info">${record.time}</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Notes</small>
                                <span class="small">${record.notes || '-'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function updateStatisticsDisplay(stats) {
            // Update statistics cards if stats are provided
            if (stats) {
                const presentDaysElement = document.querySelector('.col-xl-3:first-child .card-title');
                const absentDaysElement = document.querySelector('.col-xl-3:nth-child(2) .card-title');
                const attendancePercentElement = document.querySelector('.col-xl-3:nth-child(3) .card-title');
                const totalDaysElement = document.querySelector('.col-xl-3:last-child .card-title');
                
                if (presentDaysElement) presentDaysElement.textContent = stats.present_days || 0;
                if (absentDaysElement) absentDaysElement.textContent = stats.absent_days || 0;
                if (attendancePercentElement) attendancePercentElement.textContent = (stats.percentage || 0).toFixed(1) + '%';
                if (totalDaysElement) totalDaysElement.textContent = stats.total_days || 0;
            }
        }

        function updateFilterButtonText(text) {
            const filterText = document.getElementById('filter-text');
            if (filterText) {
                filterText.textContent = text.split(' ')[0]; // Show first word only
            }
        }

        function refreshAttendanceData() {
            // Get currently active filter
            const activeFilter = document.querySelector('.filter-option.active');
            if (activeFilter) {
                const filterType = activeFilter.getAttribute('data-filter');
                const days = activeFilter.getAttribute('data-days');
                applyAttendanceFilter(filterType, days);
            }
        }

        function showLoadingState() {
            // Add loading spinner or disable interactions
            const button = document.getElementById('transactionID');
            const filterText = document.getElementById('filter-text');
            
            if (button) {
                button.disabled = true;
                if (filterText) {
                    filterText.textContent = 'Loading...';
                }
                button.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i><span class="d-none d-sm-inline" id="filter-text">Loading...</span><i class="bx bx-chevron-down ms-1"></i>';
            }
        }

        function hideLoadingState() {
            const button = document.getElementById('transactionID');
            const filterText = document.getElementById('filter-text');
            
            if (button) {
                button.disabled = false;
                button.innerHTML = '<i class="bx bx-filter-alt me-1"></i><span class="d-none d-sm-inline" id="filter-text">Filter</span><i class="bx bx-chevron-down ms-1"></i>';
            }
        }

        function showErrorMessage(message) {
            // You can implement a toast notification here
            console.error(message);
            alert(message); // Simple alert for now
        }

        // Utility functions
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            });
        }

        function formatDayOfWeek(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { weekday: 'long' });
        }

        function getStatusColor(status) {
            switch(status.toLowerCase()) {
                case 'present': return 'success';
                case 'absent': return 'danger';
                default: return 'info';
            }
        }
    </script>
</body>
</html>
