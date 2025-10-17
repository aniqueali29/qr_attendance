<?php
/**
 * Student Assignments Page
 * Assignment list, submissions, and deadlines
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

// Mock assignments data (in a real system, this would come from the database)
$assignments = [
    [
        'id' => 1,
        'title' => 'Web Development Project',
        'description' => 'Create a responsive website using HTML, CSS, and JavaScript',
        'due_date' => '2024-01-15',
        'status' => 'pending',
        'submitted_date' => null,
        'grade' => null,
        'max_grade' => 100,
        'subject' => 'Web Development',
        'instructions' => 'Submit a complete website with at least 5 pages, responsive design, and interactive features.'
    ],
    [
        'id' => 2,
        'title' => 'Database Design Assignment',
        'description' => 'Design a database schema for a library management system',
        'due_date' => '2024-01-20',
        'status' => 'submitted',
        'submitted_date' => '2024-01-18',
        'grade' => 85,
        'max_grade' => 100,
        'subject' => 'Database Systems',
        'instructions' => 'Create ER diagrams and normalized database schema with proper relationships.'
    ],
    [
        'id' => 3,
        'title' => 'Programming Exercise',
        'description' => 'Implement sorting algorithms in Python',
        'due_date' => '2024-01-25',
        'status' => 'overdue',
        'submitted_date' => null,
        'grade' => null,
        'max_grade' => 50,
        'subject' => 'Programming',
        'instructions' => 'Implement bubble sort, quick sort, and merge sort with time complexity analysis.'
    ],
    [
        'id' => 4,
        'title' => 'Research Paper',
        'description' => 'Write a research paper on Artificial Intelligence trends',
        'due_date' => '2024-02-01',
        'status' => 'pending',
        'submitted_date' => null,
        'grade' => null,
        'max_grade' => 100,
        'subject' => 'Research Methods',
        'instructions' => 'Minimum 2000 words with proper citations and references.'
    ]
];

$pageTitle = "Assignments - " . $current_student['name'];
$currentPage = "assignments";
?>
<!doctype html>
<html lang="en" class="layout-menu-fixed layout-compact" data-assets-path="assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    
    <title><?php echo sanitizeOutput($pageTitle); ?> - <?php echo STUDENT_SITE_NAME; ?></title>
    <meta name="description" content="Student Assignments - QR Attendance System" />
    
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
                    <li class="menu-item active">
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
                        <h4 class="fw-bold py-3 mb-4">
                            <span class="text-muted fw-light">Student /</span> Assignments
                        </h4>

                        <!-- Assignment Statistics -->
                        <div class="row mb-4">
                            <div class="col-lg-3 col-md-6 col-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-book text-primary"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Total Assignments</span>
                                        <h3 class="card-title mb-2"><?php echo count($assignments); ?></h3>
                                        <small class="text-muted fw-semibold">
                                            <i class="bx bx-info-circle"></i> All time
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 col-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-check-circle text-success"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Submitted</span>
                                        <h3 class="card-title mb-2"><?php echo count(array_filter($assignments, function($a) { return $a['status'] === 'submitted'; })); ?></h3>
                                        <small class="text-success fw-semibold">
                                            <i class="bx bx-up-arrow-alt"></i> Completed
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 col-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-time text-warning"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Pending</span>
                                        <h3 class="card-title mb-2"><?php echo count(array_filter($assignments, function($a) { return $a['status'] === 'pending'; })); ?></h3>
                                        <small class="text-warning fw-semibold">
                                            <i class="bx bx-clock"></i> In progress
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 col-6 mb-4">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="card-title d-flex align-items-start justify-content-between">
                                            <div class="avatar flex-shrink-0">
                                                <i class="bx bx-x-circle text-danger"></i>
                                            </div>
                                        </div>
                                        <span class="fw-semibold d-block mb-1">Overdue</span>
                                        <h3 class="card-title mb-2"><?php echo count(array_filter($assignments, function($a) { return $a['status'] === 'overdue'; })); ?></h3>
                                        <small class="text-danger fw-semibold">
                                            <i class="bx bx-down-arrow-alt"></i> Late
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Assignments List -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title m-0 me-2">Assignment List</h5>
                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="assignmentFilter" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="assignmentFilter">
                                        <a class="dropdown-item" href="javascript:void(0);">All Assignments</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Pending</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Submitted</a>
                                        <a class="dropdown-item" href="javascript:void(0);">Overdue</a>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php foreach ($assignments as $assignment): ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="card h-100">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <h6 class="card-title mb-0"><?php echo sanitizeOutput($assignment['title']); ?></h6>
                                                    <span class="badge bg-label-<?php echo $assignment['status'] === 'submitted' ? 'success' : ($assignment['status'] === 'overdue' ? 'danger' : 'warning'); ?>">
                                                        <?php echo ucfirst($assignment['status']); ?>
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <p class="card-text"><?php echo sanitizeOutput($assignment['description']); ?></p>
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="bx bx-book me-1"></i>
                                                            <?php echo sanitizeOutput($assignment['subject']); ?>
                                                        </small>
                                                    </div>
                                                    <div class="mb-3">
                                                        <small class="text-muted">
                                                            <i class="bx bx-calendar me-1"></i>
                                                            Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                                                        </small>
                                                    </div>
                                                    <?php if ($assignment['submitted_date']): ?>
                                                        <div class="mb-3">
                                                            <small class="text-success">
                                                                <i class="bx bx-check me-1"></i>
                                                                Submitted: <?php echo date('M d, Y', strtotime($assignment['submitted_date'])); ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($assignment['grade'] !== null): ?>
                                                        <div class="mb-3">
                                                            <small class="text-info">
                                                                <i class="bx bx-star me-1"></i>
                                                                Grade: <?php echo $assignment['grade']; ?>/<?php echo $assignment['max_grade']; ?>
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="d-flex gap-2">
                                                        <button class="btn btn-outline-primary btn-sm flex-fill" data-bs-toggle="modal" data-bs-target="#assignmentModal<?php echo $assignment['id']; ?>">
                                                            <i class="bx bx-show me-1"></i>View Details
                                                        </button>
                                                        <?php if ($assignment['status'] !== 'submitted'): ?>
                                                            <button class="btn btn-primary btn-sm flex-fill">
                                                                <i class="bx bx-upload me-1"></i>Submit
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Assignment Details Modal -->
                                        <div class="modal fade" id="assignmentModal<?php echo $assignment['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title"><?php echo sanitizeOutput($assignment['title']); ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Subject</h6>
                                                                <p><?php echo sanitizeOutput($assignment['subject']); ?></p>
                                                                
                                                                <h6>Due Date</h6>
                                                                <p><?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></p>
                                                                
                                                                <h6>Max Grade</h6>
                                                                <p><?php echo $assignment['max_grade']; ?> points</p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Status</h6>
                                                                <p>
                                                                    <span class="badge bg-label-<?php echo $assignment['status'] === 'submitted' ? 'success' : ($assignment['status'] === 'overdue' ? 'danger' : 'warning'); ?>">
                                                                        <?php echo ucfirst($assignment['status']); ?>
                                                                    </span>
                                                                </p>
                                                                
                                                                <?php if ($assignment['submitted_date']): ?>
                                                                    <h6>Submitted Date</h6>
                                                                    <p><?php echo date('M d, Y', strtotime($assignment['submitted_date'])); ?></p>
                                                                <?php endif; ?>
                                                                
                                                                <?php if ($assignment['grade'] !== null): ?>
                                                                    <h6>Grade</h6>
                                                                    <p><?php echo $assignment['grade']; ?>/<?php echo $assignment['max_grade']; ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <h6>Description</h6>
                                                        <p><?php echo sanitizeOutput($assignment['description']); ?></p>
                                                        
                                                        <h6>Instructions</h6>
                                                        <p><?php echo sanitizeOutput($assignment['instructions']); ?></p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                                        <?php if ($assignment['status'] !== 'submitted'): ?>
                                                            <button type="button" class="btn btn-primary">Submit Assignment</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
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
</body>
</html>
