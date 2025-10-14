<?php
/**
 * Attendance Records Page
 * View and manage attendance records
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin authentication
requireAdminAuth();

$pageTitle = "Today's Active Students";
$currentPage = "attendances";
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
                    ['title' => 'Attendance Records', 'url' => '']
                ]); ?>
            </div>
        </div>
        
        <!-- Attendance Records Card -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bx bx-clipboard me-2"></i>Attendance Records
                    <span id="student-filter-indicator" class="badge bg-info ms-2" style="display: none;">
                        <i class="bx bx-user me-1"></i>Filtered by Student
                    </span>
                </h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" onclick="exportAttendance()">
                        <i class="bx bx-download me-1"></i>Export
                    </button>
                    <button class="btn btn-primary" onclick="loadAttendance()">
                        <i class="bx bx-refresh me-1"></i>Refresh
                    </button>
                    <button class="btn btn-info" onclick="toggleFilterPanel()">
                        <i class="bx bx-filter me-1"></i>Filters
                    </button>
                </div>
            </div>
            
            <!-- Filter Panel -->
            <div id="filter-panel" class="card-body border-top" style="display: none;">
                <div class="row g-3 mt-3">
                    <div class="col-md-3">
                        <label for="date-from" class="form-label">From Date</label>
                        <input type="date" id="date-from" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="date-to" class="form-label">To Date</label>
                        <input type="date" id="date-to" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="student-filter" class="form-label">Student</label>
                        <select id="student-filter" class="form-select">
                            <option value="">All Students</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status-filter" class="form-label">Status</label>
                        <select id="status-filter" class="form-select">
                            <option value="">All Status</option>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                            <option value="Check-in">Check-in</option>
                            <option value="Checked-out">Checked-out</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="program-filter" class="form-label">Program</label>
                        <select id="program-filter" class="form-select">
                            <option value="">All Programs</option>
                            <option value="SWT">Software Technology</option>
                            <option value="CIT">Computer Information Technology</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="shift-filter" class="form-label">Shift</label>
                        <select id="shift-filter" class="form-select">
                            <option value="">All Shifts</option>
                            <option value="Morning">Morning</option>
                            <option value="Evening">Evening</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="search-input" class="form-label">Search</label>
                        <input type="text" id="search-input" class="form-control" placeholder="Search by student name or ID...">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" onclick="applyFilters()">
                            <i class="bx bx-search me-1"></i>Apply Filters
                        </button>
                        <button class="btn btn-info" onclick="clearFilters()">
                            <i class="bx bx-home me-1"></i>Reset to Default
                        </button>
                        <button class="btn btn-secondary" onclick="clearAllFilters()">
                            <i class="bx bx-x me-1"></i>Clear All
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Table -->
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="attendance-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Shift</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <div class="mt-2">Loading attendance records...</div>
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

<!-- Attendance Details Modal -->
<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attendanceModalTitle">Attendance Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="attendanceModalBody">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editAttendanceBtn" style="display: none;">
                    <i class="bx bx-edit me-1"></i>Edit
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Attendance Modal -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Attendance Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editAttendanceForm">
                <div class="modal-body">
                    <input type="hidden" id="edit-attendance-id" name="id">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="edit-status" class="form-label">Status *</label>
                        <select id="edit-status" name="status" class="form-select" required>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                            <option value="Check-in">Check-in</option>
                            <option value="Checked-out">Checked-out</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit-notes" class="form-label">Notes</label>
                        <textarea id="edit-notes" name="notes" class="form-control" rows="3" placeholder="Additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i>Save Changes
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
                <p>Are you sure you want to delete this attendance record? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">
                    <i class="bx bx-trash me-1"></i>Delete Record
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

<script>
// Attendance page specific JavaScript
let attendanceCurrentPage = 1;
let currentFilters = {};
let deleteAttendanceId = null;

document.addEventListener('DOMContentLoaded', function() {
    // Check for URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const studentIdParam = urlParams.get('student_id');
    
    // Set default filter values in the UI
    const today = new Date();
    const todayString = today.toISOString().split('T')[0];
    
    // Set filter values in the UI
    document.getElementById('date-from').value = todayString;
    document.getElementById('date-to').value = todayString;
    document.getElementById('status-filter').value = '';
    
    // Setup filter handlers
    setupFilterHandlers();
    
    // Load student options first, then apply student filter if needed
    loadStudentOptions().then(() => {
        // If student_id parameter exists, set it in the filter after options are loaded
        if (studentIdParam) {
            const studentSelect = document.getElementById('student-filter');
            
            // Try to set the value in the dropdown
            studentSelect.value = studentIdParam;
            
            // If the option wasn't found, try to find it manually
            if (studentSelect.value !== studentIdParam) {
                for (let i = 0; i < studentSelect.options.length; i++) {
                    if (studentSelect.options[i].value === studentIdParam) {
                        studentSelect.selectedIndex = i;
                        break;
                    }
                }
            }
            
            // Apply the student filter immediately
            currentFilters.student_id = studentIdParam;
            
            // Show student filter indicator
            const indicator = document.getElementById('student-filter-indicator');
            if (indicator) {
                indicator.style.display = 'inline-block';
            }
        }
        
        // Then load attendance with filters
    loadAttendance();
    });
    
    // Setup form handlers
    setupFormHandlers();
});

function setupFormHandlers() {
    // Edit attendance form submission
    const editForm = document.getElementById('editAttendanceForm');
    if (editForm) {
        console.log('Setting up edit form handler');
        editForm.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
            e.preventDefault();
            saveAttendanceEdit();
        });
    } else {
        console.error('Edit form not found!');
    }
    
    // Delete confirmation
    document.getElementById('confirmDelete').addEventListener('click', function() {
        if (deleteAttendanceId) {
            deleteAttendance(deleteAttendanceId);
        }
    });
    
    // Edit button
    document.getElementById('editAttendanceBtn').addEventListener('click', function() {
        const attendanceId = this.getAttribute('data-attendance-id');
        openEditModal(attendanceId);
    });
    
    // Save button click handler (backup)
    const saveButton = document.querySelector('#editAttendanceModal button[type="submit"]');
    if (saveButton) {
        saveButton.addEventListener('click', function(e) {
            console.log('Save button clicked directly');
            e.preventDefault();
            saveAttendanceEdit();
        });
    }
}

function setupFilterHandlers() {
    // Set default filters: Today's Check-in and Present students
    const today = new Date();
    const todayString = today.toISOString().split('T')[0];
    
    // Use setTimeout to ensure DOM elements are ready
    setTimeout(() => {
        const dateFromEl = document.getElementById('date-from');
        const dateToEl = document.getElementById('date-to');
        const statusEl = document.getElementById('status-filter');
        
        if (dateFromEl) dateFromEl.value = todayString;
        if (dateToEl) dateToEl.value = todayString;
        if (statusEl) statusEl.value = ''; // Leave status filter empty to show all
        
        // Apply the default filters immediately
        currentFilters = {
            date_from: todayString,
            date_to: todayString,
            status: 'Check-in,Present'
        };
    }, 100);
}

function loadAttendance(page = 1) {
    attendanceCurrentPage = page;
    
    // If no filters are set, apply default filters (today's check-in and present students)
    if (Object.keys(currentFilters).length === 0) {
        const today = new Date();
        const todayString = today.toISOString().split('T')[0];
        currentFilters = {
            date_from: todayString,
            date_to: todayString,
            status: 'Check-in,Present'  // Multiple statuses separated by comma
        };
    }
    
    // Always check if student filter is set in the UI and apply it
    const studentFilter = document.getElementById('student-filter').value;
    if (studentFilter) {
        currentFilters.student_id = studentFilter;
    } else if (currentFilters.student_id) {
        // Keep existing student filter if UI doesn't have one set
    }
    
    const params = new URLSearchParams({
        page: page,
        ...currentFilters
    });
    
    console.log('API URL:', `api/attendance.php?${params}`);
    
    fetch(`api/attendance.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateAttendanceTable(data.data.records);
                updatePagination(data.data.pagination);
            } else {
                showAlert('Error loading attendance: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading attendance:', error);
            showAlert('Error loading attendance', 'danger');
        });
}

function loadStudentOptions() {
    return fetch('api/students.php?action=list&limit=1000')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('student-filter');
                select.innerHTML = '<option value="">All Students</option>';
                
                data.data.students.forEach(student => {
                    const option = document.createElement('option');
                    option.value = student.student_id || student.roll_number; // Use actual student ID string
                    option.textContent = `${student.roll_number} - ${student.name}`;
                    select.appendChild(option);
                });
            }
            return data;
        })
        .catch(error => {
            console.error('Error loading students:', error);
            throw error;
        });
}

function updateAttendanceTable(records) {
    const tbody = document.querySelector('#attendance-table tbody');
    
    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4">No attendance records found</td></tr>';
        return;
    }
    
    tbody.innerHTML = records.map(record => `
        <tr>
            <td><strong>${record.roll_number}</strong></td>
            <td>${record.student_name}</td>
            <td><span class="badge bg-primary">${record.program}</span></td>
            <td><span class="badge bg-${record.shift === 'Morning' ? 'success' : 'info'}">${record.shift}</span></td>
            <td>
                <div>${record.date}</div>
                <small class="text-muted">${record.time}</small>
            </td>
            <td>
                <span class="badge bg-${getStatusColor(record.status)}">${record.status.toUpperCase()}</span>
            </td>
            <td>${record.duration || '-'}</td>
            <td>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        Actions
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="viewAttendance(${record.id})">
                            <i class="bx bx-show me-2"></i>View Details
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="editAttendanceRecord(${record.id})">
                            <i class="bx bx-edit me-2"></i>Edit
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="confirmDelete(${record.id})">
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
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadAttendance(${pagination.current_page - 1})">Previous</a></li>`;
    } else {
        html += '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === pagination.current_page) {
            html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
        } else {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="loadAttendance(${i})">${i}</a></li>`;
        }
    }
    
    // Next button
    if (pagination.current_page < pagination.total_pages) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadAttendance(${pagination.current_page + 1})">Next</a></li>`;
    } else {
        html += '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    html += '</ul></nav>';
    container.innerHTML = html;
}

function getStatusColor(status) {
    switch (status) {
        case 'Present': return 'success';
        case 'Absent': return 'danger';
        case 'Check-in': return 'info';
        case 'Checked-out': return 'warning';
        default: return 'secondary';
    }
}

function viewAttendance(attendanceId) {
    fetch(`api/attendance.php?action=view&id=${attendanceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const record = data.data;
                const modalBody = document.getElementById('attendanceModalBody');
                const editBtn = document.getElementById('editAttendanceBtn');
                
                modalBody.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Student Information</h6>
                            <p><strong>Name:</strong> ${record.student_name}</p>
                            <p><strong>Roll Number:</strong> ${record.roll_number}</p>
                            <p><strong>Program:</strong> ${record.program}</p>
                            <p><strong>Shift:</strong> ${record.shift}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Attendance Details</h6>
                            <p><strong>Date:</strong> ${record.date}</p>
                            <p><strong>Time:</strong> ${record.time}</p>
                            <p><strong>Status:</strong> <span class="badge bg-${getStatusColor(record.status)}">${record.status.toUpperCase()}</span></p>
                            <p><strong>Duration:</strong> ${record.duration || 'N/A'}</p>
                        </div>
                    </div>
                    ${record.notes ? `<div class="mt-3"><h6>Notes</h6><p>${record.notes}</p></div>` : ''}
                `;
                
                editBtn.setAttribute('data-attendance-id', attendanceId);
                editBtn.style.display = 'inline-block';
                
                const modal = new bootstrap.Modal(document.getElementById('attendanceModal'));
                modal.show();
            } else {
                showAlert('Error loading attendance details', 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading attendance:', error);
            showAlert('Error loading attendance details', 'danger');
        });
}

function editAttendanceRecord(attendanceId) {
    openEditModal(attendanceId);
}

function openEditModal(attendanceId) {
    fetch(`api/attendance.php?action=view&id=${attendanceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const record = data.data;
                
                document.getElementById('edit-attendance-id').value = record.id;
                document.getElementById('edit-status').value = record.status;
                document.getElementById('edit-notes').value = record.notes || '';
                
                const modal = new bootstrap.Modal(document.getElementById('editAttendanceModal'));
                modal.show();
            } else {
                showAlert('Error loading attendance data: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading attendance:', error);
            showAlert('Error loading attendance data', 'danger');
        });
}

function saveAttendanceEdit() {
    console.log('saveAttendanceEdit called');
    
    const form = document.getElementById('editAttendanceForm');
    const formData = new FormData(form);
    const attendanceId = document.getElementById('edit-attendance-id').value;
    
    // Convert FormData to JSON
    const jsonData = {
        status: formData.get('status'),
        notes: formData.get('notes'),
        csrf_token: formData.get('csrf_token')
    };
    
    console.log('JSON data:', jsonData);
    console.log('Attendance ID:', attendanceId);
    
    fetch(`api/attendance.php?action=update&id=${attendanceId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(jsonData)
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data);
        if (data.success) {
            alert('Success: ' + data.message);
            bootstrap.Modal.getInstance(document.getElementById('editAttendanceModal')).hide();
            loadAttendance(attendanceCurrentPage);
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error saving attendance:', error);
        alert('Error saving attendance: ' + error.message);
    });
}

function confirmDelete(attendanceId) {
    deleteAttendanceId = attendanceId;
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function deleteAttendance(attendanceId) {
    fetch(`api/attendance.php?action=delete&id=${attendanceId}`, {
        method: 'DELETE'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
            loadAttendance(attendanceCurrentPage);
        } else {
            showAlert(data.error, 'danger');
        }
    })
    .catch(error => {
        console.error('Error deleting attendance:', error);
        showAlert('Error deleting attendance', 'danger');
    });
}

function toggleFilterPanel() {
    const panel = document.getElementById('filter-panel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function applyFilters() {
    currentFilters = {
        date_from: document.getElementById('date-from').value,
        date_to: document.getElementById('date-to').value,
        student_id: document.getElementById('student-filter').value,
        status: document.getElementById('status-filter').value,
        program: document.getElementById('program-filter').value,
        shift: document.getElementById('shift-filter').value,
        search: document.getElementById('search-input').value
    };
    
    loadAttendance(1);
}

function clearFilters() {
    // Reset to default view: Today's Check-in and Present students
    const today = new Date();
    const todayString = today.toISOString().split('T')[0];
    
    document.getElementById('date-from').value = todayString;
    document.getElementById('date-to').value = todayString;
    document.getElementById('student-filter').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('program-filter').value = '';
    document.getElementById('shift-filter').value = '';
    document.getElementById('search-input').value = '';
    
    // Hide student filter indicator
    const indicator = document.getElementById('student-filter-indicator');
    if (indicator) {
        indicator.style.display = 'none';
    }
    
    currentFilters = {
        date_from: todayString,
        date_to: todayString,
        status: 'Check-in,Present'
    };
    loadAttendance(1);
}

function clearAllFilters() {
    // Clear all filters to show all records
    document.getElementById('date-from').value = '';
    document.getElementById('date-to').value = '';
    document.getElementById('student-filter').value = '';
    document.getElementById('status-filter').value = '';
    document.getElementById('program-filter').value = '';
    document.getElementById('shift-filter').value = '';
    document.getElementById('search-input').value = '';
    
    // Hide student filter indicator
    const indicator = document.getElementById('student-filter-indicator');
    if (indicator) {
        indicator.style.display = 'none';
    }
    
    currentFilters = {};
    loadAttendance(1);
}

function exportAttendance() {
    const params = new URLSearchParams(currentFilters);
    window.open(`api/attendance.php?action=export&${params}`, '_blank');
}

function showAlert(message, type = 'info') {
    // Create alert container if it doesn't exist
    let alertContainer = document.getElementById('alert-container');
    if (!alertContainer) {
        alertContainer = document.createElement('div');
        alertContainer.id = 'alert-container';
        alertContainer.style.position = 'fixed';
        alertContainer.style.top = '20px';
        alertContainer.style.right = '20px';
        alertContainer.style.zIndex = '9999';
        document.body.appendChild(alertContainer);
    }
    
    // Create alert element
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alertDiv);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 5000);
}
</script>

</body>
</html>
