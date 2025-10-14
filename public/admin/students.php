<?php
/**
 * Students Management Page
 * Manage students with CRUD operations, filters, and search
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin authentication
requireAdminAuth();

$pageTitle = "Student Management";
$currentPage = "students";
$pageCSS = ['css/responsive-buttons.css'];
$pageJS = ['js/admin.js'];

include 'partials/header.php';
include 'partials/sidebar.php';
include 'partials/navbar.php';
?>


<!-- Content wrapper -->
<div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
        <!-- Breadcrumb -->
        <div class="row">
            <div class="col-12">
                <?php echo generateBreadcrumb([
                    ['title' => 'Dashboard', 'url' => 'index.php'],
                    ['title' => 'Student Management', 'url' => '']
                ]); ?>
            </div>
        </div>
        
        <!-- Student Management Card -->
        <div class="card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-12 col-md-6 col-lg-8">
                        <h5 class="card-title mb-0">
                            <i class="bx bx-user me-2"></i>Student Management
                        </h5>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4 mt-2 mt-md-0">
                        <div class="btn-container">
                            <button class="btn btn-primary btn-responsive" onclick="openStudentModal()">
                                <i class="bx bx-plus me-1"></i>
                                <span class="btn-text">Add</span>
                            </button>
                            <button class="btn btn-success btn-responsive" onclick="exportStudents()">
                                <i class="bx bx-download me-1"></i>
                                <span class="btn-text">Export</span>
                            </button>
                            <button class="btn btn-warning btn-responsive" onclick="openBulkImportModal()">
                                <i class="bx bx-upload me-1"></i>
                                <span class="btn-text">Import</span>
                            </button>
                            <button class="btn btn-info btn-responsive" onclick="toggleFilterPanel()">
                                <i class="bx bx-filter me-1"></i>
                                <span class="btn-text">Filters</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Advanced Filter Panel -->
            <div id="filter-panel" class="card-body border-top" style="display: none;">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="program-filter" class="form-label">Program</label>
                        <select id="program-filter" class="form-select">
                            <option value="">All Programs</option>
                            <option value="SWT">Software Technology</option>
                            <option value="CIT">Computer Information Technology</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="shift-filter" class="form-label">Shift</label>
                        <select id="shift-filter" class="form-select">
                            <option value="">All Shifts</option>
                            <option value="Morning">Morning</option>
                            <option value="Evening">Evening</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="year-filter" class="form-label">Year Level</label>
                        <select id="year-filter" class="form-select">
                            <option value="">All Years</option>
                            <option value="1st">1st Year</option>
                            <option value="2nd">2nd Year</option>
                            <option value="3rd">3rd Year</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="section-filter" class="form-label">Section</label>
                        <select id="section-filter" class="form-select">
                            <option value="">All Sections</option>
                            <option value="A">Section A</option>
                            <option value="B">Section B</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="search-input" class="form-label">Search</label>
                        <input type="text" id="search-input" class="form-control" placeholder="Search by name, ID, or email...">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" onclick="applyFilters()">
                            <i class="bx bx-search me-1"></i>Apply Filters
                        </button>
                        <button class="btn btn-secondary" onclick="clearFilters()">
                            <i class="bx bx-x me-1"></i>Clear Filters
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Students Table -->
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="students-table">
                        <thead>
                            <tr>
                                <th>Roll Number</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Shift</th>
                                <th>Year</th>
                                <th>Section</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <div class="mt-2">Loading students...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div id="pagination" class="d-flex justify-content-center mt-4">
                    <!-- Pagination will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    <!-- / Content -->
</div>
<!-- Content wrapper -->

<!-- Student Modal -->
<div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="studentModalTitle">Add Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="studentForm">
                <div class="modal-body">
                    <input type="hidden" id="student-id" name="id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="student-roll" class="form-label">Roll Number *</label>
                            <input type="text" id="student-roll" name="roll_number" class="form-control" placeholder="e.g., 24-SWT-01" required>
                            <div class="form-text">Format: YY-PROGRAM-NN (Morning) or YY-EPROGRAM-NN (Evening)</div>
                            <div id="roll-number-status" class="mt-2" style="display: none;"></div>
                            <div id="duplicate-actions" class="mt-2" style="display: none;">
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="clearDuplicateWarning()">
                                    <i class="bx bx-x me-1"></i>Clear Warning
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="student-name" class="form-label">Full Name *</label>
                            <input type="text" id="student-name" name="name" class="form-control" placeholder="e.g., John Doe" required>
                        </div>
                        <div class="col-md-6">
                            <label for="student-email" class="form-label">Email Address *</label>
                            <input type="email" id="student-email" name="email" class="form-control" placeholder="e.g., john.doe@college.edu" required>
                        </div>
                        <div class="col-md-6">
                            <label for="student-phone" class="form-label">Phone Number</label>
                            <input type="tel" id="student-phone" name="phone" class="form-control" placeholder="e.g., +92 300 1234567">
                        </div>
                        <div class="col-md-6">
                            <label for="student-admission-year" class="form-label">Admission Year</label>
                            <input type="number" id="student-admission-year" name="admission_year" class="form-control" placeholder="e.g., 2024" readonly>
                            <div class="form-text">Auto-filled from roll number</div>
                        </div>
                        <div class="col-md-6">
                            <label for="student-program" class="form-label">Program *</label>
                            <select id="student-program" name="program" class="form-select" required>
                                <option value="">Select Program</option>
                                <option value="SWT">Software Technology</option>
                                <option value="CIT">Computer Information Technology</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="student-shift" class="form-label">Shift *</label>
                            <select id="student-shift" name="shift" class="form-select" required>
                                <option value="">Select Shift</option>
                                <option value="Morning">Morning</option>
                                <option value="Evening">Evening</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="student-year" class="form-label">Year Level *</label>
                            <select id="student-year" name="year_level" class="form-select" required>
                                <option value="">Select Year</option>
                                <option value="1st">1st Year</option>
                                <option value="2nd">2nd Year</option>
                                <option value="3rd">3rd Year</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="student-section" class="form-label">Section *</label>
                            <select id="student-section" name="section" class="form-select" required>
                                <option value="">Select Section</option>
                                <option value="A">Section A</option>
                                <option value="B">Section B</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="bx bx-info-circle me-2"></i>
                                <strong>Password:</strong> A secure password will be automatically generated and included in the students.json file.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-user-plus me-1"></i>Create Student Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this student? This action cannot be undone.</p>
                <div class="alert alert-warning">
                    <i class="bx bx-warning me-2"></i>
                    This will also delete all attendance records for this student.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">
                    <i class="bx bx-trash me-1"></i>Delete Student
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Student Info Modal -->
<div class="modal fade" id="studentInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-user me-2"></i>Student Information
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <!-- Personal Information -->
                    <div class="col-12">
                        <h6 class="mb-3">
                            <i class="bx bx-info-circle me-2"></i>Personal Information
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Roll Number</label>
                        <div class="fw-semibold" id="info-roll-number">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Full Name</label>
                        <div class="fw-semibold" id="info-name">-</div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="col-12 mt-3">
                        <h6 class="mb-3">
                            <i class="bx bx-envelope me-2"></i>Contact Information
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Email Address</label>
                        <div class="fw-semibold" id="info-email">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Phone Number</label>
                        <div class="fw-semibold" id="info-phone">-</div>
                    </div>
                    
                    <!-- Academic Information -->
                    <div class="col-12 mt-3">
                        <h6 class="mb-3">
                            <i class="bx bx-book me-2"></i>Academic Information
                        </h6>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Program</label>
                        <div id="info-program-badge">-</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Shift</label>
                        <div id="info-shift-badge">-</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Year Level</label>
                        <div class="fw-semibold" id="info-year">-</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Section</label>
                        <div class="fw-semibold" id="info-section">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Admission Year</label>
                        <div class="fw-semibold" id="info-admission-year">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Current Year</label>
                        <div class="fw-semibold" id="info-current-year">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Roll Prefix</label>
                        <div class="fw-semibold" id="info-roll-prefix">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Student ID</label>
                        <div class="fw-semibold" id="info-student-id">-</div>
                    </div>
                    
                    <!-- Status Information -->
                    <div class="col-12 mt-3">
                        <h6 class="mb-3">
                            <i class="bx bx-check-circle me-2"></i>Status Information
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Is Active</label>
                        <div id="info-active-status">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Is Graduated</label>
                        <div id="info-graduated-status">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Last Year Update</label>
                        <div class="fw-semibold" id="info-last-year-update">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Created At</label>
                        <div class="fw-semibold" id="info-created-at">-</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Updated At</label>
                        <div class="fw-semibold" id="info-updated-at">-</div>
                    </div>
                    
                    <!-- Account Information -->
                    <div class="col-12 mt-3">
                        <h6 class="mb-3">
                            <i class="bx bx-lock me-2"></i>Account Information
                        </h6>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Username</label>
                        <div class="fw-semibold" id="info-username">-</div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label text-muted small">Password Management</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="resetStudentPassword()">
                                <i class="bx bx-refresh me-1"></i>Reset to Roll Number
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="setCustomPassword()">
                                <i class="bx bx-edit me-1"></i>Set Custom Password
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="generateRandomPassword()">
                                <i class="bx bx-shuffle me-1"></i>Generate Random
                            </button>
                        </div>
                        <div class="form-text">Reset to roll number, set custom password, or generate a random secure password</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="editStudentFromInfo()">
                    <i class="bx bx-edit me-1"></i>Edit Student
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Password Management Modal -->
<div class="modal fade" id="passwordResetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordModalTitle">
                    <i class="bx bx-key me-2"></i>Password Management
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" id="passwordAlert">
                    <i class="bx bx-info-circle me-2"></i>
                    <span id="passwordAlertText">Select a password management option below.</span>
                </div>
                
                <!-- Password Type Selection -->
                <div class="mb-4" id="passwordTypeSelection">
                    <label class="form-label">Password Management Options</label>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-secondary w-100" onclick="selectPasswordType('roll')">
                                <i class="bx bx-refresh me-1"></i><br>
                                <small>Reset to Roll Number</small>
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-primary w-100" onclick="selectPasswordType('custom')">
                                <i class="bx bx-edit me-1"></i><br>
                                <small>Set Custom Password</small>
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-info w-100" onclick="selectPasswordType('random')">
                                <i class="bx bx-shuffle me-1"></i><br>
                                <small>Generate Random</small>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Password Input Section -->
                <div id="passwordInputSection" style="display: none;">
                    <form id="passwordResetForm">
                        <div class="mb-3">
                            <label class="form-label" id="passwordLabel">New Password</label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="new-password" placeholder="Enter or generate password">
                                <button class="btn btn-outline-secondary" type="button" onclick="copyPassword()">
                                    <i class="bx bx-copy"></i>
                                </button>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()">
                                    <i class="bx bx-show" id="toggleIcon"></i>
                                </button>
                            </div>
                            <div class="form-text" id="passwordHelpText">Copy this password to share with the student</div>
                        </div>
                        
                        <!-- Custom Password Input -->
                        <div id="customPasswordSection" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Enter Custom Password</label>
                                <input type="text" class="form-control" id="custom-password" placeholder="Enter custom password">
                                <div class="form-text">Enter a custom password for this student</div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirmPasswordBtn" onclick="confirmPasswordReset()" style="display: none;">
                    <i class="bx bx-check me-1"></i>Confirm Password Change
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Import Modal -->
<div class="modal fade" id="bulkImportModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-upload me-2"></i>Bulk Import Students
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="bulkImportCloseBtn"></button>
            </div>
            <div class="modal-body">
                <!-- Import Steps -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-center align-items-center">
                            <div class="steps-container">
                                <div class="step-indicator active" id="step1-indicator">
                                    <div class="step-number">1</div>
                                    <div class="step-label">Upload File</div>
                                </div>
                                <div class="step-indicator" id="step2-indicator">
                                    <div class="step-number">2</div>
                                    <div class="step-label">Validate</div>
                                </div>
                                <div class="step-indicator" id="step3-indicator">
                                    <div class="step-number">3</div>
                                    <div class="step-label">Import</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 1: File Upload -->
                <div id="import-step-1" class="import-step">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card border-2 border-dashed border-primary">
                                <div class="card-body text-center py-5">
                                    <div class="mb-4">
                                        <i class="bx bx-cloud-upload display-1 text-primary"></i>
                                    </div>
                                    <h5 class="mb-3">Upload Student Data File</h5>
                                    <p class="text-muted mb-4">
                                        Upload a CSV or Excel file containing student information. 
                                        Download the template to see the required format.
                                    </p>
                                    
                                    <div class="mb-4">
                                        <input type="file" id="importFile" class="form-control" accept=".csv,.xlsx" style="display: none;">
                                        <button type="button" class="btn btn-primary btn-lg" onclick="document.getElementById('importFile').click()">
                                            <i class="bx bx-folder-open me-2"></i>Choose File
                                        </button>
                                    </div>
                                    
                                    <div id="file-info" class="mt-3" style="display: none;">
                                        <div class="alert alert-info">
                                            <i class="bx bx-file me-2"></i>
                                            <span id="file-name"></span>
                                            <span class="badge bg-primary ms-2" id="file-size"></span>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4">
                                        <button type="button" class="btn btn-outline-primary" onclick="downloadTemplate()">
                                            <i class="bx bx-download me-2"></i>Download Template
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="bx bx-info-circle me-2"></i>Import Guidelines
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2">
                                            <i class="bx bx-check text-success me-2"></i>
                                            <strong>File Format:</strong> CSV or Excel (.xlsx)
                                        </li>
                                        <li class="mb-2">
                                            <i class="bx bx-check text-success me-2"></i>
                                            <strong>Max Size:</strong> 10MB
                                        </li>
                                        <li class="mb-2">
                                            <i class="bx bx-check text-success me-2"></i>
                                            <strong>Required Fields:</strong> Student ID, Name, Email, Program, Shift, Year, Section
                                        </li>
                                        <li class="mb-2">
                                            <i class="bx bx-check text-success me-2"></i>
                                            <strong>Student ID Format:</strong> YY-PROGRAM-NN (e.g., 25-SWT-01)
                                        </li>
                                        <li class="mb-0">
                                            <i class="bx bx-check text-success me-2"></i>
                                            <strong>Duplicate Check:</strong> Automatic validation
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Validation -->
                <div id="import-step-2" class="import-step" style="display: none;">
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="bx bx-check-circle me-2"></i>File Validation
                                    </h6>
                                    <div id="validation-status" class="badge bg-warning">Validating...</div>
                                </div>
                                <div class="card-body">
                                    <div id="validation-progress" class="mb-4">
                                        <div class="progress">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <div class="text-center mt-2">
                                            <span id="validation-text">Preparing validation...</span>
                                        </div>
                                    </div>
                                    
                                    <div id="validation-results" style="display: none;">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="card border-success">
                                                    <div class="card-body text-center">
                                                        <i class="bx bx-check-circle display-4 text-success mb-3"></i>
                                                        <h5 class="text-success" id="valid-count">0</h5>
                                                        <p class="text-muted mb-0">Valid Records</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card border-danger">
                                                    <div class="card-body text-center">
                                                        <i class="bx bx-x-circle display-4 text-danger mb-3"></i>
                                                        <h5 class="text-danger" id="invalid-count">0</h5>
                                                        <p class="text-muted mb-0">Invalid Records</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div id="validation-errors" class="mt-4" style="display: none;">
                                            <h6 class="text-danger mb-3">
                                                <i class="bx bx-error me-2"></i>Validation Errors
                                            </h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead class="table-danger">
                                                        <tr>
                                                            <th>Row</th>
                                                            <th>Student ID</th>
                                                            <th>Errors</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="error-details">
                                                        <!-- Error details will be populated here -->
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <div id="data-preview" class="mt-4" style="display: none;">
                                            <h6 class="text-success mb-3">
                                                <i class="bx bx-table me-2"></i>Data Preview
                                            </h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered">
                                                    <thead class="table-success">
                                                        <tr>
                                                            <th>Student ID</th>
                                                            <th>Name</th>
                                                            <th>Email</th>
                                                            <th>Program</th>
                                                            <th>Shift</th>
                                                            <th>Year</th>
                                                            <th>Section</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="preview-data">
                                                        <!-- Preview data will be populated here -->
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Import Process -->
                <div id="import-step-3" class="import-step" style="display: none;">
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        <i class="bx bx-data me-2"></i>Import Process
                                    </h6>
                                    <div id="import-status" class="badge bg-warning">Preparing...</div>
                                </div>
                                <div class="card-body">
                                    <div id="import-progress" class="mb-4">
                                        <div class="progress">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <div class="text-center mt-2">
                                            <span id="import-text">Preparing import...</span>
                                        </div>
                                    </div>
                                    
                                    <div id="import-results" style="display: none;">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="card border-success">
                                                    <div class="card-body text-center">
                                                        <i class="bx bx-check-circle display-4 text-success mb-3"></i>
                                                        <h5 class="text-success" id="import-success-count">0</h5>
                                                        <p class="text-muted mb-0">Successfully Imported</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card border-danger">
                                                    <div class="card-body text-center">
                                                        <i class="bx bx-x-circle display-4 text-danger mb-3"></i>
                                                        <h5 class="text-danger" id="import-error-count">0</h5>
                                                        <p class="text-muted mb-0">Failed</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="card border-info">
                                                    <div class="card-body text-center">
                                                        <i class="bx bx-time display-4 text-info mb-3"></i>
                                                        <h5 class="text-info" id="import-total-count">0</h5>
                                                        <p class="text-muted mb-0">Total Records</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div id="import-summary" class="mt-4">
                                            <div class="alert alert-success" style="display: none;">
                                                <i class="bx bx-check-circle me-2"></i>
                                                <strong>Import Completed Successfully!</strong>
                                                <span id="success-message"></span>
                                            </div>
                                            
                                            <div id="import-errors" class="mt-4" style="display: none;">
                                                <h6 class="text-danger mb-3">
                                                    <i class="bx bx-error me-2"></i>Import Errors
                                                </h6>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered">
                                                        <thead class="table-danger">
                                                            <tr>
                                                                <th>Row</th>
                                                                <th>Student ID</th>
                                                                <th>Error</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="import-error-details">
                                                            <!-- Import error details will be populated here -->
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="bulkImportCancelBtn">
                    <i class="bx bx-x me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="bulkImportNextBtn" onclick="nextImportStep()" style="display: none;">
                    <i class="bx bx-right-arrow me-1"></i>Next
                </button>
                <button type="button" class="btn btn-success" id="bulkImportStartBtn" onclick="startImport()" style="display: none;">
                    <i class="bx bx-play me-1"></i>Start Import
                </button>
                <button type="button" class="btn btn-primary" id="bulkImportFinishBtn" onclick="finishImport()" style="display: none;">
                    <i class="bx bx-check me-1"></i>Finish
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Alert Container -->
<div id="alert-container"></div>

<!-- Core JS -->
<script src="<?php echo getAdminAssetUrl('vendor/libs/jquery/jquery.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/libs/popper/popper.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/js/bootstrap.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/libs/perfect-scrollbar/perfect-scrollbar.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/js/menu.js'); ?>"></script>

<!-- Main JS -->
<script src="<?php echo getAdminAssetUrl('js/main.js'); ?>"></script>

<!-- Page JS -->
<script src="<?php echo getAdminAssetUrl('js/admin.js?v=' . time()); ?>"></script>

<!-- Bulk Import Styles -->
<style>
/* Simple Professional Step Indicator */
.steps-container {
    display: flex;
    align-items: center;
    position: relative;
    padding: 20px 0;
    overflow: hidden;
}

.step-indicator {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    padding: 0 20px;
    z-index: 2;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 8px;
    transition: all 0.3s ease;
}

.step-indicator.active .step-number {
    background-color: #0d6efd;
    color: white;
}

.step-indicator.completed .step-number {
    background-color: #198754;
    color: white;
}

.step-indicator.completed .step-number {
    font-size: 0;
}

.step-indicator.completed .step-number::before {
    content: "✓";
    font-size: 18px;
    font-weight: bold;
    display: block;
}

.step-label {
    font-size: 14px;
    font-weight: 500;
    color: #6c757d;
    text-align: center;
    transition: all 0.3s ease;
}

.step-indicator.active .step-label {
    color: #0d6efd;
    font-weight: 600;
}

.step-indicator.completed .step-label {
    color: #198754;
    font-weight: 600;
}

/* Professional Toast Alert Styles */
.toast-container {
    max-width: 400px;
}

.toast {
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 12px;
    animation: slideInRight 0.3s ease-out;
}

.toast-body {
    padding: 16px 20px;
    font-size: 14px;
    line-height: 1.4;
}

.toast-message {
    font-size: 13px;
    opacity: 0.95;
    margin-top: 2px;
    white-space: pre-line;
}

.toast .fw-semibold {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 2px;
}

.toast .btn-close {
    padding: 8px;
    margin: -8px -8px -8px 8px;
    opacity: 0.8;
    transition: opacity 0.2s ease;
}

.toast .btn-close:hover {
    opacity: 1;
}

/* Toast Animation */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Toast Type Specific Styles */
.toast.text-bg-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
}

.toast.text-bg-danger {
    background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%) !important;
}

.toast.text-bg-warning {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%) !important;
    color: #000 !important;
}

.toast.text-bg-info {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%) !important;
}

/* Responsive Toast */
@media (max-width: 576px) {
    .toast-container {
        max-width: calc(100vw - 24px);
        left: 12px !important;
        right: 12px !important;
    }
}


.import-step {
    min-height: 400px;
}

.border-dashed {
    border-style: dashed !important;
}

#file-info .alert {
    border-left: 4px solid #0d6efd;
}

.progress-bar-animated {
    animation: progress-bar-stripes 1s linear infinite;
}

@keyframes progress-bar-stripes {
    0% { background-position: 0 0; }
    100% { background-position: 40px 0; }
}

.table-responsive {
    max-height: 300px;
    overflow-y: auto;
}

.display-1 {
    font-size: 4rem;
}

.display-4 {
    font-size: 2.5rem;
}

.badge {
    font-size: 0.75em;
}

.card.border-success {
    border-color: #198754 !important;
}

.card.border-danger {
    border-color: #dc3545 !important;
}

.card.border-info {
    border-color: #0dcaf0 !important;
}

.table-success {
    background-color: rgba(25, 135, 84, 0.1);
}

.table-danger {
    background-color: rgba(220, 53, 69, 0.1);
}

.alert-sm {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
}
</style>

<script>
// Students page specific JavaScript

// Professional Toast Alert System
if (typeof window.adminUtils === 'undefined') {
    window.adminUtils = {
        showAlert: function(message, type = 'info', duration = 5000) {
            // Create toast container if it doesn't exist
            let toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toast-container';
                toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
                toastContainer.style.zIndex = '9999';
                document.body.appendChild(toastContainer);
            }
            
            // Generate unique ID for toast
            const toastId = 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            
            // Get icon and color based on type
            const typeConfig = {
                'success': { icon: 'bx-check-circle', color: 'success', title: 'Success' },
                'error': { icon: 'bx-x-circle', color: 'danger', title: 'Error' },
                'warning': { icon: 'bx-error-circle', color: 'warning', title: 'Warning' },
                'info': { icon: 'bx-info-circle', color: 'info', title: 'Information' },
                'danger': { icon: 'bx-x-circle', color: 'danger', title: 'Error' }
            };
            
            const config = typeConfig[type] || typeConfig['info'];
            
            // Create toast HTML
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-bg-${config.color} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body d-flex align-items-center">
                            <i class="bx ${config.icon} me-2 fs-5"></i>
                            <div>
                                <div class="fw-semibold">${config.title}</div>
                                <div class="toast-message">${message}</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            // Add toast to container
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            // Initialize and show toast
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: duration
            });
            
            toast.show();
            
            // Remove toast element after it's hidden
            toastElement.addEventListener('hidden.bs.toast', function() {
                toastElement.remove();
            });
        },
        
        showSuccess: function(message, duration = 4000) {
            this.showAlert(message, 'success', duration);
        },
        
        showError: function(message, duration = 6000) {
            this.showAlert(message, 'error', duration);
        },
        
        showWarning: function(message, duration = 5000) {
            this.showAlert(message, 'warning', duration);
        },
        
        showInfo: function(message, duration = 4000) {
            this.showAlert(message, 'info', duration);
        }
    };
}

let currentStudentsPage = 1;
let currentFilters = {};
let deleteStudentId = null;
let editingStudentId = null; // Track if we're editing a student

document.addEventListener('DOMContentLoaded', function() {
    loadStudents();
    
    // Setup form handlers
    setupFormHandlers();
    setupFilterHandlers();
});

function setupFormHandlers() {
    // Student form submission
    document.getElementById('studentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveStudent();
    });
    
    // Roll number validation and auto-fill
    document.getElementById('student-roll').addEventListener('input', function() {
        validateRollNumber(this.value);
        // Check for duplicates with debouncing - only for new students
        clearTimeout(window.duplicateCheckTimeout);
        window.duplicateCheckTimeout = setTimeout(() => {
            if (this.value.trim().length >= 5 && !isEditingStudent()) {
                checkRollNumberDuplicate(this.value.trim());
            }
        }, 500); // 500ms delay to avoid too many API calls
    });
    
    // Roll number auto-fill on blur
    document.getElementById('student-roll').addEventListener('blur', function() {
        const rollNumber = this.value.trim();
        if (rollNumber) {
            parseRollNumber(rollNumber);
            // Only check duplicates for new students, not when editing
            if (!isEditingStudent()) {
                checkRollNumberDuplicate(rollNumber);
            }
        }
    });
    
    // Roll number auto-fill on Enter key
    document.getElementById('student-roll').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const rollNumber = this.value.trim();
            if (rollNumber) {
                parseRollNumber(rollNumber);
                // Only check duplicates for new students, not when editing
                if (!isEditingStudent()) {
                    checkRollNumberDuplicate(rollNumber);
                }
            }
        }
    });
    
    // Delete confirmation
    document.getElementById('confirmDelete').addEventListener('click', function() {
        if (deleteStudentId) {
            deleteStudent(deleteStudentId);
        }
    });
    
    // Clear editing flag when modal is closed
    document.getElementById('studentModal').addEventListener('hidden.bs.modal', function() {
        editingStudentId = null;
        clearRollNumberStatus();
    });
}

function isEditingStudent() {
    return editingStudentId !== null;
}

function clearRollNumberStatus() {
    const statusDiv = document.getElementById('roll-number-status');
    if (statusDiv) {
        statusDiv.style.display = 'none';
        statusDiv.innerHTML = '';
    }
    
    // Reset roll number field styling
    const rollInput = document.getElementById('student-roll');
    if (rollInput) {
        rollInput.style.backgroundColor = '';
        rollInput.style.borderColor = '';
    }
    
    // Re-enable form submission
    const submitBtn = document.querySelector('#studentModal .btn-primary');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bx bx-save me-1"></i>Save Student';
    }
    
    // Hide duplicate actions
    const duplicateActions = document.getElementById('duplicate-actions');
    if (duplicateActions) {
        duplicateActions.style.display = 'none';
    }
}

function setupFilterHandlers() {
    // Filter handlers can be added here if needed
}

function loadStudents(page = 1) {
    currentStudentsPage = page;
    
    const params = new URLSearchParams({
        page: page,
        ...currentFilters
    });
    
    console.log('Loading students with params:', params.toString());
    fetch(`api/admin_api.php?action=get_filtered_students&${params}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            console.log('Students API response:', data);
            if (data.success) {
                updateStudentsTable(data.data);
                updatePagination({
                    current_page: data.page,
                    total_pages: Math.ceil(data.total / data.limit)
                });
            } else {
                adminUtils.showAlert('Error loading students: ' + (data.message || data.error), 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading students:', error);
            adminUtils.showAlert('Error loading students: ' + error.message, 'danger');
        });
}

function updateStudentsTable(students) {
    const tbody = document.querySelector('#students-table tbody');
    
    if (students.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4">No students found</td></tr>';
        return;
    }
    
    tbody.innerHTML = students.map(student => `
        <tr>
            <td><strong>${student.student_id || student.roll_number || '-'}</strong></td>
            <td>${student.name || '-'}</td>
            <td><span class="badge bg-primary">${student.program || '-'}</span></td>
            <td><span class="badge bg-${student.shift === 'Morning' ? 'success' : 'info'}">${student.shift || '-'}</span></td>
            <td>${student.year_level || '-'}</td>
            <td>Section ${student.section || '-'}</td>
            <td>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        Actions
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="editStudent(${student.id})">
                            <i class="bx bx-edit me-2"></i>Edit
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="viewStudent(${student.id})">
                            <i class="bx bx-show me-2"></i>View
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="viewAttendance(${student.id})">
                            <i class="bx bx-clipboard me-2"></i>Attendance
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete(${student.id})">
                            <i class="bx bx-trash me-2"></i>Delete
                        </a></li>
                    </ul>
                </div>
            </td>
        </tr>
    `).join('');
}

function updatePagination(pagination) {
    const container = document.getElementById('pagination');
    
    if (pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous button
    if (pagination.current_page > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadStudents(${pagination.current_page - 1})">Previous</a></li>`;
    } else {
        html += '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === pagination.current_page) {
            html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
        } else {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="loadStudents(${i})">${i}</a></li>`;
        }
    }
    
    // Next button
    if (pagination.current_page < pagination.total_pages) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadStudents(${pagination.current_page + 1})">Next</a></li>`;
    } else {
        html += '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    html += '</ul></nav>';
    container.innerHTML = html;
}

function openStudentModal(studentId = null) {
    const modal = new bootstrap.Modal(document.getElementById('studentModal'));
    const form = document.getElementById('studentForm');
    const title = document.getElementById('studentModalTitle');
    
    if (studentId) {
        title.textContent = 'Edit Student';
        editingStudentId = studentId; // Set editing flag
        loadStudentData(studentId);
    } else {
        title.textContent = 'Add Student';
        editingStudentId = null; // Clear editing flag
        form.reset();
        document.getElementById('student-id').value = '';
    }
    
    modal.show();
}

function loadStudentData(studentId) {
    fetch(`api/admin_api.php?action=view_student&id=${studentId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            if (data.success) {
                const student = data.data;
                document.getElementById('student-id').value = student.id;
                document.getElementById('student-roll').value = student.student_id || '';
                document.getElementById('student-name').value = student.name || '';
                document.getElementById('student-email').value = student.email || '';
                document.getElementById('student-phone').value = student.phone || '';
                document.getElementById('student-program').value = student.program || '';
                document.getElementById('student-shift').value = student.shift || '';
                document.getElementById('student-year').value = student.year_level || '';
                document.getElementById('student-section').value = student.section || '';
                
                // Clear any duplicate warnings when editing
                clearRollNumberStatus();
            } else {
                adminUtils.showAlert('Error loading student data: ' + (data.message || data.error), 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading student:', error);
            adminUtils.showAlert('Error loading student data: ' + error.message, 'danger');
        });
}

function saveStudent() {
    const form = document.getElementById('studentForm');
    const formData = new FormData(form);
    
    const studentId = document.getElementById('student-id').value;
    const url = studentId ? `api/admin_api.php?action=update_student&id=${studentId}` : 'api/admin_api.php?action=create_student';
    const method = 'POST'; // Both create and update use POST method
    
    fetch(url, {
        method: method,
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(data => {
        if (data.success) {
            adminUtils.showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('studentModal')).hide();
            loadStudents(currentStudentsPage);
        } else {
            adminUtils.showAlert(data.message || data.error, 'danger');
        }
    })
    .catch(error => {
        console.error('Error saving student:', error);
        adminUtils.showAlert('Error saving student: ' + error.message, 'danger');
    });
}

function editStudent(studentId) {
    openStudentModal(studentId);
}

function viewStudent(studentId) {
    fetch(`api/admin_api.php?action=view_student&id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const student = data.data;
                
                // Populate modal fields
                document.getElementById('info-roll-number').textContent = student.student_id || '-';
                document.getElementById('info-name').textContent = student.name || '-';
                document.getElementById('info-email').textContent = student.email || '-';
                document.getElementById('info-phone').textContent = student.phone || 'Not provided';
                document.getElementById('info-year').textContent = student.year_level || '-';
                document.getElementById('info-section').textContent = 'Section ' + (student.section || '-');
                document.getElementById('info-admission-year').textContent = student.admission_year || '-';
                document.getElementById('info-current-year').textContent = student.current_year || '-';
                document.getElementById('info-roll-prefix').textContent = student.roll_prefix || '-';
                document.getElementById('info-student-id').textContent = student.student_id || '-';
                
                // Program
                document.getElementById('info-program-badge').textContent = student.program || '-';
                
                // Shift
                document.getElementById('info-shift-badge').textContent = student.shift || '-';
                
                // Status fields
                const isActive = student.is_active ? 'Yes' : 'No';
                const activeStatus = student.is_active ? 
                    '<span class="badge bg-success">Active</span>' : 
                    '<span class="badge bg-danger">Inactive</span>';
                document.getElementById('info-active-status').innerHTML = activeStatus;
                
                const isGraduated = student.is_graduated ? 'Yes' : 'No';
                const graduatedStatus = student.is_graduated ? 
                    '<span class="badge bg-warning">Graduated</span>' : 
                    '<span class="badge bg-info">Not Graduated</span>';
                document.getElementById('info-graduated-status').innerHTML = graduatedStatus;
                
                document.getElementById('info-last-year-update').textContent = student.last_year_update || 'Never';
                document.getElementById('info-created-at').textContent = student.created_at || '-';
                document.getElementById('info-updated-at').textContent = student.updated_at || '-';
                
                // Username (use actual username from database)
                document.getElementById('info-username').textContent = student.username || student.student_id || '-';
                
                // Store student ID for other actions
                currentViewStudentId = studentId;
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('studentInfoModal'));
                modal.show();
            } else {
                adminUtils.showError('Error loading student information: ' + (data.message || data.error));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            adminUtils.showError('Error loading student information: ' + error.message);
        });
}

function viewAttendance(studentId) {
    // Show loading message
    adminUtils.showInfo('Loading attendance records for this student...');
    
    // Get the actual student_id string from the table row
    const row = event.target.closest('tr');
    const studentIdCell = row.querySelector('td:first-child strong');
    const actualStudentId = studentIdCell ? studentIdCell.textContent.trim() : studentId;
    
    console.log('Database ID:', studentId, 'Actual Student ID:', actualStudentId);
    
    // Redirect to attendance page with the actual student_id string
    window.location.href = `attendances.php?student_id=${encodeURIComponent(actualStudentId)}`;
}

function confirmDelete(studentId) {
    deleteStudentId = studentId;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function deleteStudent(studentId) {
    fetch(`api/admin_api.php?action=delete_student&id=${studentId}`, {
        method: 'DELETE'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(data => {
        if (data.success) {
            adminUtils.showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
            loadStudents(currentStudentsPage);
        } else {
            adminUtils.showAlert(data.message || data.error, 'danger');
        }
    })
    .catch(error => {
        console.error('Error deleting student:', error);
        adminUtils.showAlert('Error deleting student: ' + error.message, 'danger');
    });
}

function toggleFilterPanel() {
    const panel = document.getElementById('filter-panel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function applyFilters() {
    currentFilters = {
        program: document.getElementById('program-filter').value,
        shift: document.getElementById('shift-filter').value,
        year: document.getElementById('year-filter').value,
        section: document.getElementById('section-filter').value,
        search: document.getElementById('search-input').value
    };
    
    loadStudents(1);
}

function clearFilters() {
    document.getElementById('program-filter').value = '';
    document.getElementById('shift-filter').value = '';
    document.getElementById('year-filter').value = '';
    document.getElementById('section-filter').value = '';
    document.getElementById('search-input').value = '';
    
    currentFilters = {};
    loadStudents(1);
}

function validateRollNumber(rollNumber) {
    if (rollNumber.length < 5) return;
    
    // Basic roll number validation
    const pattern = /^\d{2}-[A-Z]{2,3}-\d{2}$/;
    const statusDiv = document.getElementById('roll-number-status');
    
    if (pattern.test(rollNumber)) {
        statusDiv.innerHTML = '<span class="text-success"><i class="bx bx-check-circle me-1"></i>Valid format</span>';
        statusDiv.style.display = 'block';
    } else {
        statusDiv.innerHTML = '<span class="text-warning"><i class="bx bx-info-circle me-1"></i>Invalid format</span>';
        statusDiv.style.display = 'block';
    }
}

function exportStudents() {
    // Show export options modal
    showExportModal();
}

function showExportModal() {
    // Create export modal if it doesn't exist
    let modal = document.getElementById('exportModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'exportModal';
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Export Students</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Export Format</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="exportFormat" id="formatCsv" value="csv" checked>
                                <label class="form-check-label" for="formatCsv">
                                    <i class="bx bx-file me-2"></i>CSV (Comma Separated Values)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="exportFormat" id="formatExcel" value="excel">
                                <label class="form-check-label" for="formatExcel">
                                    <i class="bx bx-table me-2"></i>Excel (.xlsx)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="exportFormat" id="formatPdf" value="pdf">
                                <label class="form-check-label" for="formatPdf">
                                    <i class="bx bx-file-pdf me-2"></i>PDF Document
                                </label>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="bx bx-info-circle me-2"></i>
                            <strong>Note:</strong> The export will include all students matching your current filters.
                        </div>
                        <div class="alert alert-info">
                            <i class="bx bx-info-circle me-2"></i>
                            <strong>Export Formats:</strong> CSV for data analysis, Excel for spreadsheets, PDF for reports.
                        </div>
                        <div class="alert alert-warning">
                            <i class="bx bx-printer me-2"></i>
                            <strong>PDF Export:</strong> Opens as HTML report. Click "Print to PDF" button to save as PDF file.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" onclick="performExport()">
                            <i class="bx bx-download me-1"></i>Export
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Show the modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

function performExport() {
    const format = document.querySelector('input[name="exportFormat"]:checked').value;
    const params = new URLSearchParams({
        ...currentFilters,
        format: format
    });
    
    // Close the modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
    modal.hide();
    
    // Show loading message
    adminUtils.showAlert('Preparing export...', 'info');
    
    // Open export URL
    window.open(`api/admin_api.php?action=export_students&${params}`, '_blank');
}

/**
 * Parse roll number and auto-fill form fields
 */
async function parseRollNumber(rollNumber) {
    if (!rollNumber || rollNumber.length < 5) {
        return;
    }
    
    try {
        console.log('Parsing roll number:', rollNumber);
        
        const response = await fetch(`api/roll_parser_service.php?action=parse_roll&roll_number=${encodeURIComponent(rollNumber)}`);
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            console.log('Roll number parsed successfully:', data);
            
            // Auto-fill form fields with correct element IDs
            const programSelect = document.getElementById('student-program');
            const shiftSelect = document.getElementById('student-shift');
            const yearLevelSelect = document.getElementById('student-year');
            const admissionYearInput = document.getElementById('student-admission-year');
            const sectionSelect = document.getElementById('student-section');
            
            // Fill admission year
            if (admissionYearInput) {
                admissionYearInput.value = data.admission_year;
                admissionYearInput.style.backgroundColor = '#e8f5e8';
                admissionYearInput.style.borderColor = '#4caf50';
                console.log('Admission year filled:', data.admission_year);
            }
            
            // Fill program
            if (programSelect) {
                console.log('Looking for program:', data.program_code);
                for (let option of programSelect.options) {
                    if (option.value === data.program_code) {
                        option.selected = true;
                        programSelect.style.backgroundColor = '#e8f5e8';
                        programSelect.style.borderColor = '#4caf50';
                        console.log('Program selected:', option.text);
                        break;
                    }
                }
            }
            
            // Fill shift
            if (shiftSelect) {
                console.log('Looking for shift:', data.shift);
                for (let option of shiftSelect.options) {
                    if (option.value === data.shift) {
                        option.selected = true;
                        shiftSelect.style.backgroundColor = '#e8f5e8';
                        shiftSelect.style.borderColor = '#4caf50';
                        console.log('Shift selected:', option.text);
                        break;
                    }
                }
            }
            
            // Fill year level
            if (yearLevelSelect) {
                console.log('Looking for year level:', data.year_level);
                console.log('Available year level options:', Array.from(yearLevelSelect.options).map(o => ({value: o.value, text: o.text})));
                for (let option of yearLevelSelect.options) {
                    if (option.value === data.year_level) {
                        option.selected = true;
                        yearLevelSelect.style.backgroundColor = '#e8f5e8';
                        yearLevelSelect.style.borderColor = '#4caf50';
                        console.log('Year level selected:', option.text);
                        break;
                    }
                }
            } else {
                console.log('Year level select not found');
            }
            
            // Fill section (auto-select first available section)
            if (sectionSelect && data.available_sections && data.available_sections.length > 0) {
                console.log('Available sections:', data.available_sections);
                const firstSection = data.available_sections[0];
                console.log('Auto-selecting first available section:', firstSection.section_name);
                
                for (let option of sectionSelect.options) {
                    if (option.value === firstSection.section_name) {
                        option.selected = true;
                        sectionSelect.style.backgroundColor = '#e8f5e8';
                        sectionSelect.style.borderColor = '#4caf50';
                        console.log('Section selected:', option.text);
                        break;
                    }
                }
            } else {
                console.log('Section select not found or no available sections');
            }
            
            // Show success message
            const sectionInfo = data.available_sections && data.available_sections.length > 0 ? 
                ` - Section ${data.available_sections[0].section_name}` : '';
            showRollNumberStatus('success', `Auto-filled: ${data.program_name} - ${data.shift} - ${data.year_level}${sectionInfo}`);
            
            // Store parsed data for form submission
            window.parsedRollData = data;
            
        } else {
            console.error('Roll number parsing failed:', result.error);
            showRollNumberStatus('error', result.error);
            
            // Reset form fields
            resetAutoFilledFields();
        }
        
    } catch (error) {
        console.error('Error parsing roll number:', error);
        showRollNumberStatus('error', 'Error parsing roll number: ' + error.message);
        resetAutoFilledFields();
    }
}

/**
 * Show roll number status message
 */
function showRollNumberStatus(type, message) {
    const statusDiv = document.getElementById('roll-number-status');
    if (statusDiv) {
        statusDiv.style.display = 'block';
        
        let alertClass, iconClass;
        switch (type) {
            case 'success':
                alertClass = 'alert-success';
                iconClass = 'check-circle';
                break;
            case 'warning':
                alertClass = 'alert-warning';
                iconClass = 'error-circle';
                break;
            case 'error':
                alertClass = 'alert-danger';
                iconClass = 'error-circle';
                break;
            default:
                alertClass = 'alert-info';
                iconClass = 'info-circle';
        }
        
        statusDiv.className = `alert ${alertClass} alert-sm`;
        statusDiv.innerHTML = `<i class="bx bx-${iconClass} me-1"></i>${message}`;
        
        // Auto-hide after 5 seconds (except for warnings which stay longer)
        const hideDelay = type === 'warning' ? 8000 : 5000;
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, hideDelay);
    }
}

/**
 * Check for duplicate roll number
 */
async function checkRollNumberDuplicate(rollNumber) {
    if (!rollNumber || rollNumber.length < 5) {
        return;
    }
    
    try {
        console.log('Checking for duplicate roll number:', rollNumber);
        
        const response = await fetch(`api/check_duplicate.php?action=check_roll_number&roll_number=${encodeURIComponent(rollNumber)}`);
        const result = await response.json();
        
        if (result.success) {
            if (result.is_duplicate) {
                // Show duplicate warning
                showRollNumberStatus('warning', `⚠️ Roll number already exists for: ${result.data.existing_student.name} (${result.data.existing_student.program} - ${result.data.existing_student.shift})`);
                
                // Highlight the roll number field
                const rollInput = document.getElementById('student-roll');
                rollInput.style.backgroundColor = '#fff3cd';
                rollInput.style.borderColor = '#ffc107';
                
                // Disable form submission
                const submitBtn = document.querySelector('#studentModal .btn-primary');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="bx bx-error-circle me-1"></i>Duplicate Roll Number';
                }
                
                // Show duplicate actions
                const duplicateActions = document.getElementById('duplicate-actions');
                if (duplicateActions) {
                    duplicateActions.style.display = 'block';
                }
                
            } else {
                // Clear duplicate warning
                showRollNumberStatus('success', `✅ Roll number '${rollNumber}' is available`);
                
                // Reset roll number field styling
                const rollInput = document.getElementById('student-roll');
                rollInput.style.backgroundColor = '';
                rollInput.style.borderColor = '';
                
                // Enable form submission
                const submitBtn = document.querySelector('#studentModal .btn-primary');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bx bx-user-plus me-1"></i>Create Student Account';
                }
                
                // Hide duplicate actions
                const duplicateActions = document.getElementById('duplicate-actions');
                if (duplicateActions) {
                    duplicateActions.style.display = 'none';
                }
            }
        } else {
            console.error('Duplicate check failed:', result.error);
            showRollNumberStatus('error', 'Error checking roll number: ' + result.error);
        }
        
    } catch (error) {
        console.error('Error checking duplicate:', error);
        showRollNumberStatus('error', 'Error checking roll number: ' + error.message);
    }
}

/**
 * Reset auto-filled fields
 */
function resetAutoFilledFields() {
    const fields = ['student-program', 'student-shift', 'student-year', 'student-admission-year', 'student-section'];
    
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.style.backgroundColor = '';
            field.style.borderColor = '';
            if (field.tagName === 'SELECT') {
                field.selectedIndex = 0;
            } else if (field.tagName === 'INPUT') {
                field.value = '';
            }
        }
    });
    
    // Clear parsed data
    window.parsedRollData = null;
}

/**
 * Clear duplicate warning
 */
function clearDuplicateWarning() {
    // Clear the roll number field
    document.getElementById('student-roll').value = '';
    
    // Reset styling
    const rollInput = document.getElementById('student-roll');
    rollInput.style.backgroundColor = '';
    rollInput.style.borderColor = '';
    
    // Hide status and actions
    document.getElementById('roll-number-status').style.display = 'none';
    document.getElementById('duplicate-actions').style.display = 'none';
    
    // Enable form submission
    const submitBtn = document.querySelector('#studentModal .btn-primary');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bx bx-user-plus me-1"></i>Create Student Account';
    }
}

// Bulk Import Variables
let currentImportStep = 1;
let importFile = null;
let validationResults = null;
let importResults = null;

// Bulk Import Functions
function openBulkImportModal() {
    const modal = new bootstrap.Modal(document.getElementById('bulkImportModal'));
    resetBulkImportModal();
    modal.show();
}

function resetBulkImportModal() {
    currentImportStep = 1;
    importFile = null;
    validationResults = null;
    importResults = null;
    
    // Reset steps
    document.getElementById('step1-indicator').className = 'step-indicator active';
    document.getElementById('step2-indicator').className = 'step-indicator';
    document.getElementById('step3-indicator').className = 'step-indicator';
    
    // Show step 1, hide others
    document.getElementById('import-step-1').style.display = 'block';
    document.getElementById('import-step-2').style.display = 'none';
    document.getElementById('import-step-3').style.display = 'none';
    
    // Reset buttons
    document.getElementById('bulkImportNextBtn').style.display = 'none';
    document.getElementById('bulkImportStartBtn').style.display = 'none';
    document.getElementById('bulkImportFinishBtn').style.display = 'none';
    
    // Reset file input
    document.getElementById('importFile').value = '';
    document.getElementById('file-info').style.display = 'none';
    
    // Reset validation results
    document.getElementById('validation-results').style.display = 'none';
    document.getElementById('validation-errors').style.display = 'none';
    document.getElementById('data-preview').style.display = 'none';
    
    // Reset import results
    document.getElementById('import-results').style.display = 'none';
    document.getElementById('import-summary').style.display = 'none';
    document.getElementById('import-errors').style.display = 'none';
}

// File upload handler
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('importFile');
    if (fileInput) {
        fileInput.addEventListener('change', handleFileSelect);
    }
});

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Validate file type
    const allowedTypes = ['text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    const fileExtension = file.name.split('.').pop().toLowerCase();
    
    if (!['csv', 'xlsx'].includes(fileExtension)) {
        adminUtils.showAlert('Please select a CSV or Excel file (.csv, .xlsx)', 'danger');
        return;
    }
    
    // Validate file size (10MB max)
    const maxSize = 10 * 1024 * 1024; // 10MB
    if (file.size > maxSize) {
        adminUtils.showAlert('File size must be less than 10MB', 'danger');
        return;
    }
    
    importFile = file;
    
    // Show file info
    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-size').textContent = formatFileSize(file.size);
    document.getElementById('file-info').style.display = 'block';
    
    // Show next button
    document.getElementById('bulkImportNextBtn').style.display = 'inline-block';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function nextImportStep() {
    if (currentImportStep === 1) {
        // Move to validation step
        currentImportStep = 2;
        showImportStep(2);
        validateFile();
    } else if (currentImportStep === 2) {
        // Move to import step
        currentImportStep = 3;
        showImportStep(3);
    }
}

function showImportStep(step) {
    // Hide all steps
    document.getElementById('import-step-1').style.display = 'none';
    document.getElementById('import-step-2').style.display = 'none';
    document.getElementById('import-step-3').style.display = 'none';
    
    // Show current step
    document.getElementById(`import-step-${step}`).style.display = 'block';
    
    // Update step indicators
    for (let i = 1; i <= 3; i++) {
        const indicator = document.getElementById(`step${i}-indicator`);
        if (i < step) {
            indicator.className = 'step-indicator completed';
        } else if (i === step) {
            indicator.className = 'step-indicator active';
        } else {
            indicator.className = 'step-indicator';
        }
    }
    
    // Update buttons
    document.getElementById('bulkImportNextBtn').style.display = 'none';
    document.getElementById('bulkImportStartBtn').style.display = 'none';
    document.getElementById('bulkImportFinishBtn').style.display = 'none';
    
    if (step === 2) {
        // Validation step - no buttons until validation completes
    } else if (step === 3) {
        // Import step - show start button
        document.getElementById('bulkImportStartBtn').style.display = 'inline-block';
    }
}

async function validateFile() {
    if (!importFile) {
        adminUtils.showAlert('No file selected', 'danger');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', importFile);
    
    try {
        // Show progress
        updateValidationProgress(0, 'Uploading file...');
        
        console.log('Uploading file:', importFile.name, importFile.size, importFile.type);
        
        // Read file content for debugging
        const reader = new FileReader();
        reader.onload = function(e) {
            console.log('File content preview:', e.target.result.substring(0, 200));
        };
        reader.readAsText(importFile);
        
        const response = await fetch('api/admin_api.php?action=validate_bulk_import', {
            method: 'POST',
            body: formData
        });
        
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        const result = await response.json();
        
        console.log('Validation API response:', result);
        console.log('Result success:', result.success);
        console.log('Result message:', result.message);
        console.log('Result error:', result.error);
        console.log('Result details:', result.details);
        console.log('Result details length:', result.details ? result.details.length : 0);
        if (result.details && result.details.length > 0) {
            console.log('First error detail:', result.details[0]);
            result.details.forEach((detail, index) => {
                console.log(`Error ${index + 1}:`, detail);
                if (detail.errors && Array.isArray(detail.errors)) {
                    console.log(`  - Errors for row ${detail.row}:`, detail.errors);
                }
            });
        }
        
        if (result.success) {
            updateValidationProgress(100, 'Validation completed');
            showValidationResults(result.data);
            validationResults = result.data;
            
            // Show next button if validation passed
            if (result.data.valid_rows > 0) {
                document.getElementById('bulkImportNextBtn').style.display = 'inline-block';
            }
        } else {
            updateValidationProgress(100, 'Validation failed');
            console.error('Validation failed:', result);
            
            // Show detailed validation errors in a user-friendly format
            showDetailedValidationErrors(result.details || []);
        }
        
    } catch (error) {
        console.error('Validation error:', error);
        updateValidationProgress(100, 'Validation failed');
        adminUtils.showError('Error validating file: ' + error.message);
    }
}

function updateValidationProgress(percentage, text) {
    const progressBar = document.querySelector('#validation-progress .progress-bar');
    const progressText = document.getElementById('validation-text');
    const statusBadge = document.getElementById('validation-status');
    
    progressBar.style.width = percentage + '%';
    progressText.textContent = text;
    
    if (percentage === 100) {
        progressBar.classList.remove('progress-bar-animated');
        if (text.includes('completed')) {
            statusBadge.className = 'badge bg-success';
            statusBadge.textContent = 'Completed';
        } else if (text.includes('failed')) {
            statusBadge.className = 'badge bg-danger';
            statusBadge.textContent = 'Failed';
        }
    }
}

function showValidationResults(data) {
    document.getElementById('validation-results').style.display = 'block';
    document.getElementById('valid-count').textContent = data.valid_rows;
    document.getElementById('invalid-count').textContent = data.invalid_rows;
    
    // Show errors if any
    if (data.invalid_rows > 0) {
        document.getElementById('validation-errors').style.display = 'block';
        showValidationErrors(data.errors || []);
    }
    
    // Show preview if valid data exists
    if (data.valid_rows > 0 && data.preview) {
        document.getElementById('data-preview').style.display = 'block';
        showDataPreview(data.preview);
    }
}

function showDetailedValidationErrors(errors) {
    if (!errors || errors.length === 0) {
        adminUtils.showError('Validation failed: No specific error details available');
        return;
    }
    
    // Create a detailed error message
    let errorMessage = '❌ Validation Failed - Please fix the following errors:\n\n';
    
    errors.forEach((error, index) => {
        const rowNumber = error.row || (index + 2);
        const studentId = error.student_id || 'N/A';
        const errorList = Array.isArray(error.errors) ? error.errors : [error.error || 'Unknown error'];
        
        errorMessage += `📋 Row ${rowNumber} (${studentId}):\n`;
        errorList.forEach(err => {
            errorMessage += `   • ${err}\n`;
        });
        errorMessage += '\n';
    });
    
    errorMessage += '💡 Tips:\n';
    errorMessage += '• Use unique student IDs and email addresses\n';
    errorMessage += '• Make sure all required fields are filled\n';
    errorMessage += '• Check that programs and sections exist in the system\n';
    errorMessage += '• Download a fresh template and replace with your data';
    
    // Show the detailed error message using the new toast system
    adminUtils.showError(errorMessage);
    
    // Also show in the validation errors table
    showValidationErrors(errors);
}

function showValidationErrors(errors) {
    const errorDetails = document.getElementById('error-details');
    if (!errorDetails) return;
    
    errorDetails.innerHTML = '';
    
    errors.forEach(error => {
        const row = document.createElement('tr');
        const errorList = Array.isArray(error.errors) ? error.errors : [error.error || 'Unknown error'];
        
        row.innerHTML = `
            <td>${error.row || 'N/A'}</td>
            <td>${error.student_id || 'N/A'}</td>
            <td>
                <ul class="mb-0">
                    ${errorList.map(err => `<li>${err}</li>`).join('')}
                </ul>
            </td>
        `;
        errorDetails.appendChild(row);
    });
}

function showDataPreview(previewData) {
    const previewTable = document.getElementById('preview-data');
    previewTable.innerHTML = '';
    
    previewData.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${row.student_id || ''}</td>
            <td>${row.name || ''}</td>
            <td>${row.email || ''}</td>
            <td>${row.program || ''}</td>
            <td>${row.shift || ''}</td>
            <td>${row.year_level || ''}</td>
            <td>${row.section || ''}</td>
        `;
        previewTable.appendChild(tr);
    });
}

async function startImport() {
    if (!validationResults || !validationResults.valid_data) {
        adminUtils.showAlert('No valid data to import', 'danger');
        return;
    }
    
    console.log('Starting import with data:', validationResults.valid_data);
    
    try {
        // Show progress
        updateImportProgress(0, 'Starting import...');
        
        // Create AbortController for timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
        
        const response = await fetch('api/admin_api.php?action=bulk_import_students', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                data: validationResults.valid_data
            }),
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        
        const result = await response.json();
        
        console.log('Import API response:', result);
        
        if (result.success) {
            updateImportProgress(100, 'Import completed');
            showImportResults(result.data);
            importResults = result.data;
            
            // Show finish button
            document.getElementById('bulkImportFinishBtn').style.display = 'inline-block';
            document.getElementById('bulkImportStartBtn').style.display = 'none';
        } else {
            updateImportProgress(100, 'Import failed');
            console.error('Import failed:', result);
            adminUtils.showAlert('Import failed: ' + (result.message || result.error || 'Unknown error'), 'danger');
        }
        
    } catch (error) {
        console.error('Import error:', error);
        updateImportProgress(100, 'Import failed');
        
        if (error.name === 'AbortError') {
            adminUtils.showAlert('Import timed out. Please try again with fewer records.', 'danger');
        } else {
            adminUtils.showAlert('Error importing data: ' + error.message, 'danger');
        }
    }
}

function updateImportProgress(percentage, text) {
    const progressBar = document.querySelector('#import-progress .progress-bar');
    const progressText = document.getElementById('import-text');
    const statusBadge = document.getElementById('import-status');
    
    progressBar.style.width = percentage + '%';
    progressText.textContent = text;
    
    if (percentage === 100) {
        progressBar.classList.remove('progress-bar-animated');
        if (text.includes('completed')) {
            statusBadge.className = 'badge bg-success';
            statusBadge.textContent = 'Completed';
        } else if (text.includes('failed')) {
            statusBadge.className = 'badge bg-danger';
            statusBadge.textContent = 'Failed';
        }
    }
}

function showImportResults(data) {
    document.getElementById('import-results').style.display = 'block';
    document.getElementById('import-success-count').textContent = data.success ? data.success.length : 0;
    document.getElementById('import-error-count').textContent = data.errors ? data.errors.length : 0;
    document.getElementById('import-total-count').textContent = data.total || 0;
    
    // Show success message
    if (data.success && data.success.length > 0) {
        document.querySelector('#import-summary .alert-success').style.display = 'block';
        document.getElementById('success-message').textContent = ` ${data.success.length} students imported successfully.`;
    }
    
    // Show errors if any
    if (data.errors && data.errors.length > 0) {
        document.getElementById('import-errors').style.display = 'block';
        showImportErrors(data.errors);
    }
}

function showImportErrors(errors) {
    const errorDetails = document.getElementById('import-error-details');
    errorDetails.innerHTML = '';
    
    errors.forEach(error => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${error.row || 'N/A'}</td>
            <td>${error.student_id || 'N/A'}</td>
            <td>${error.error || 'Unknown error'}</td>
        `;
        errorDetails.appendChild(row);
    });
}

function finishImport() {
    // Close modal and refresh students list
    const modal = bootstrap.Modal.getInstance(document.getElementById('bulkImportModal'));
    modal.hide();
    
    // Refresh students table
    loadStudents(currentStudentsPage);
    
    // Show success message
    if (importResults && importResults.success && importResults.success.length > 0) {
        adminUtils.showAlert(`Successfully imported ${importResults.success.length} students!`, 'success');
    }
}

function downloadTemplate() {
    // Create a completely fresh template with unique data
    const timestamp = Date.now();
    const templateData = [
        ['student_id', 'name', 'email', 'phone', 'program', 'shift', 'year_level', 'section'],
        [`25-SWT-${String(timestamp).slice(-3)}`, 'Sample Student 1', `student1_${timestamp}@college.edu`, '923001234567', 'SWT', 'Morning', '1st', 'A'],
        [`25-SWT-${String(timestamp + 1).slice(-3)}`, 'Sample Student 2', `student2_${timestamp}@college.edu`, '923007654321', 'SWT', 'Evening', '1st', 'B'],
        [`25-CIT-${String(timestamp + 2).slice(-3)}`, 'Sample Student 3', `student3_${timestamp}@college.edu`, '923009876543', 'CIT', 'Morning', '1st', 'A'],
        [`25-CIT-${String(timestamp + 3).slice(-3)}`, 'Sample Student 4', `student4_${timestamp}@college.edu`, '923001112233', 'CIT', 'Evening', '1st', 'B']
    ];
    
    // Convert to CSV
    const csvContent = templateData.map(row => row.join(',')).join('\n');
    
    // Create and download file
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'student_import_template_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    adminUtils.showSuccess('Fresh template downloaded with unique data! Replace the sample data with your real student information.');
}

// Test function to debug API connection
async function testBulkImportAPI() {
    try {
        console.log('Testing bulk import API...');
        const response = await fetch('api/admin_api.php?action=test_bulk_import');
        const result = await response.json();
        console.log('API test result:', result);
        
        if (result.success) {
            adminUtils.showAlert('API connection successful!', 'success');
        } else {
            adminUtils.showAlert('API test failed: ' + result.message, 'danger');
        }
    } catch (error) {
        console.error('API test error:', error);
        adminUtils.showAlert('API test failed: ' + error.message, 'danger');
    }
}

// Debug function to test file validation
async function debugValidation() {
    if (!importFile) {
        adminUtils.showWarning('No file selected for debugging');
        return;
    }
    
    console.log('=== DEBUGGING VALIDATION ===');
    console.log('File name:', importFile.name);
    console.log('File size:', importFile.size);
    console.log('File type:', importFile.type);
    console.log('File last modified:', importFile.lastModified);
    
    // Test API connection first
    await testBulkImportAPI();
}

// Store current student ID for info modal actions
let currentViewStudentId = null;

function editStudentFromInfo() {
    // Close info modal
    const infoModal = bootstrap.Modal.getInstance(document.getElementById('studentInfoModal'));
    infoModal.hide();
    
    // Open edit modal
    setTimeout(() => {
        editStudent(currentViewStudentId);
    }, 300);
}

// Password management variables
let currentPasswordType = null;

function resetStudentPassword() {
    currentPasswordType = 'roll';
    showPasswordModal('roll');
}

function setCustomPassword() {
    currentPasswordType = 'custom';
    showPasswordModal('custom');
}

function generateRandomPassword() {
    currentPasswordType = 'random';
    showPasswordModal('random');
}

function showPasswordModal(type) {
    // Reset modal state
    document.getElementById('passwordTypeSelection').style.display = 'none';
    document.getElementById('passwordInputSection').style.display = 'block';
    document.getElementById('confirmPasswordBtn').style.display = 'inline-block';
    
    // Update modal title and alert
    const title = document.getElementById('passwordModalTitle');
    const alert = document.getElementById('passwordAlert');
    const alertText = document.getElementById('passwordAlertText');
    const passwordLabel = document.getElementById('passwordLabel');
    const passwordHelp = document.getElementById('passwordHelpText');
    const customSection = document.getElementById('customPasswordSection');
    
    if (type === 'roll') {
        title.innerHTML = '<i class="bx bx-refresh me-2"></i>Reset to Roll Number';
        alert.className = 'alert alert-warning';
        alertText.textContent = 'This will reset the password to the student\'s roll number.';
        passwordLabel.textContent = 'New Password (Roll Number)';
        passwordHelp.textContent = 'Password will be set to the student\'s roll number';
        customSection.style.display = 'none';
        
        // Get student roll number
        const rollNumber = document.getElementById('info-roll-number').textContent;
        document.getElementById('new-password').value = rollNumber;
        
    } else if (type === 'custom') {
        title.innerHTML = '<i class="bx bx-edit me-2"></i>Set Custom Password';
        alert.className = 'alert alert-info';
        alertText.textContent = 'Enter a custom password for this student.';
        passwordLabel.textContent = 'Custom Password';
        passwordHelp.textContent = 'Enter a custom password for this student';
        customSection.style.display = 'block';
        document.getElementById('new-password').value = '';
        
    } else if (type === 'random') {
        title.innerHTML = '<i class="bx bx-shuffle me-2"></i>Generate Random Password';
        alert.className = 'alert alert-success';
        alertText.textContent = 'A secure random password will be generated.';
        passwordLabel.textContent = 'Generated Password';
        passwordHelp.textContent = 'Copy this secure password to share with the student';
        customSection.style.display = 'none';
        
        // Generate random password
        const randomPassword = generateSecurePassword();
        document.getElementById('new-password').value = randomPassword;
    }
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('passwordResetModal'));
    modal.show();
}

function generateSecurePassword() {
    const length = 12;
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
    let password = "";
    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    return password;
}

function selectPasswordType(type) {
    showPasswordModal(type);
}

function togglePasswordVisibility() {
    const passwordInput = document.getElementById('new-password');
    const toggleIcon = document.getElementById('toggleIcon');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.className = 'bx bx-hide';
    } else {
        passwordInput.type = 'password';
        toggleIcon.className = 'bx bx-show';
    }
}

function copyPassword() {
    const passwordInput = document.getElementById('new-password');
    passwordInput.select();
    document.execCommand('copy');
    adminUtils.showSuccess('Password copied to clipboard!');
}

// Handle custom password input
document.addEventListener('DOMContentLoaded', function() {
    const customPasswordInput = document.getElementById('custom-password');
    if (customPasswordInput) {
        customPasswordInput.addEventListener('input', function() {
            document.getElementById('new-password').value = this.value;
        });
    }
});

function confirmPasswordReset() {
    const newPassword = document.getElementById('new-password').value;
    
    if (!currentViewStudentId) {
        adminUtils.showError('Student ID not found');
        return;
    }
    
    if (!newPassword || newPassword.trim() === '') {
        adminUtils.showError('Please enter a password');
        return;
    }
    
    // Show loading state
    const confirmBtn = document.getElementById('confirmPasswordBtn');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i>Updating...';
    
    fetch('api/admin_api.php?action=reset_student_password', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            student_id: currentViewStudentId,
            new_password: newPassword,
            password_type: currentPasswordType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let successMessage = 'Password updated successfully!';
            if (currentPasswordType === 'roll') {
                successMessage = 'Password reset to roll number successfully!';
            } else if (currentPasswordType === 'custom') {
                successMessage = 'Custom password set successfully!';
            } else if (currentPasswordType === 'random') {
                successMessage = 'Random password generated and set successfully!';
            }
            
            adminUtils.showSuccess(successMessage);
            
            // Close both modals
            const resetModal = bootstrap.Modal.getInstance(document.getElementById('passwordResetModal'));
            const infoModal = bootstrap.Modal.getInstance(document.getElementById('studentInfoModal'));
            resetModal.hide();
            setTimeout(() => infoModal.hide(), 300);
        } else {
            adminUtils.showError('Error updating password: ' + (data.message || data.error));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        adminUtils.showError('Error updating password: ' + error.message);
    })
    .finally(() => {
        // Reset button state
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalText;
    });
}
</script>

</body>
</html>
