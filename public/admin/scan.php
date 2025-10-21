<?php
// Admin USB Scanner Check-in Page - Enhanced Version
// Protect page with admin auth and render enhanced UI

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
// Ensure API CSRF token exists in session
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

requireAdminAuth();

$pageTitle = 'Scan Attendance';
// Load enhanced scanner module
$pageJS = [];

include 'partials/header.php';
include 'partials/sidebar.php';
include 'partials/navbar.php';
?>

<!-- Custom Scanner Styles -->
<link rel="stylesheet" href="assets/css/scanner.css">

<!-- Flash Overlay for Visual Feedback -->
<div id="flash-overlay" style="display:none"></div>

<!-- Content -->
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row">
        <!-- Left Column: Scanner Input (50%) -->
        <div class="col-12 col-lg-6">
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">
                        <i class="bx bx-scan me-2"></i>Attendance Scanner
                    </h5>
                    <div class="d-flex align-items-center gap-3">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="mode-toggle">
                            <label class="form-check-label" for="mode-toggle">
                                <span id="mode-label">Scanner</span> mode
                            </label>
                        </div>
                        <span class="badge bg-label-secondary" id="scanner-status">Ready</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary settings-icon-btn" 
                                data-bs-toggle="modal" data-bs-target="#scannerConfigModal" title="Scanner Settings">
                            <i class="bx bx-cog"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info" role="alert">
                        <i class="bx bx-info-circle me-2"></i>
                        Scan student card or toggle to Manual mode for keyboard entry.
                    </div>

                    <!-- Scanner Section -->
                    <div id="scanner-section">
                        <div class="mb-4">
                            <label for="scan-input" class="form-label">Scan Input</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">
                                    <i class="bx bx-qr-scan"></i>
                                </span>
                                <input type="text" id="scan-input" class="form-control form-control-lg" 
                                       placeholder="Focus here and scan..." 
                                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
                            </div>
                            <div class="form-text mt-2">
                                <i class="bx bx-help-circle me-1"></i>
                                Barcode/QR scanner input field. Auto-clears after each scan. Press <kbd>Alt+I</kbd> to refocus.
                            </div>
                        </div>
                    </div>

                    <!-- Manual Section -->
                    <div id="manual-section" class="d-none">
                        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="manual-student-id" class="form-label">Student ID</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bx bx-id-card"></i>
                                    </span>
                                    <input type="text" id="manual-student-id" class="form-control" placeholder="e.g., 25-SWT-01">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label for="manual-notes" class="form-label">Notes (optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bx bx-notepad"></i>
                                    </span>
                                    <input type="text" id="manual-notes" class="form-control" placeholder="Optional notes...">
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="button" id="manual-submit" class="btn btn-primary w-100 btn-lg">
                                    <i class="bx bx-save me-1"></i>Save Attendance
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="scan-feedback" class="alert d-none mt-3" role="alert"></div>

                    <!-- Stats and Actions -->
                    <div class="d-flex align-items-center justify-content-between mt-4 pt-3 border-top">
                        <div>
                            <div class="d-flex gap-4">
                                <div class="text-center">
                                    <div class="text-muted small">Total Today</div>
                                    <div class="fw-bold fs-4" id="scan-count">0</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-muted small">Last Scan</div>
                                    <div class="fw-bold" id="last-scan-time">-</div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="btn-group-vertical btn-group-sm" role="group">
                                <button type="button" id="auto-absent-btn" class="btn btn-outline-primary mb-1">
                                    <i class="bx bx-time me-1"></i>Auto Check
                                </button>
                                <button type="button" id="mark-absent-btn" class="btn btn-outline-warning">
                                    <i class="bx bx-user-x me-1"></i>Mark Absent
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tips Card -->
            <!-- <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bx bx-bulb me-1"></i>Scanner Tips</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0 ps-3">
                        <li class="mb-2">Keep scanner input focused - press <kbd>Alt+I</kbd> if needed</li>
                        <li class="mb-2">Duplicate scans are ignored for 3 seconds</li>
                        <li class="mb-2">Student preview shows before attendance is saved</li>
                        <li>Use <i class="bx bx-cog"></i> settings to customize scanner behavior</li>
                    </ul>
                </div>
            </div> -->
        </div>

        <!-- Middle Column: Student Preview (25%) - Hidden until scan -->
        <div class="col-12 col-lg-6">
            <div id="student-preview" class="card" style="display:none">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bx bx-user me-1"></i>Student Preview</h6>
                </div>
                <div class="card-body text-center">
                    <div class="preview-loading d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <img id="preview-photo" src="assets/img/default-avatar.svg" 
                         class="rounded-circle mb-3" width="120" height="120" alt="Student Photo">
                    <h5 id="preview-name" class="mb-1">-</h5>
                    <p class="text-muted mb-1"><strong id="preview-id">-</strong></p>
                    <p class="text-muted mb-2">
                        <span id="preview-program">-</span> • <span id="preview-shift">-</span>
                    </p>
                    <div id="preview-status" class="mb-3">
                        <span class="badge bg-secondary">Not checked in today</span>
                    </div>
                    <div class="btn-group w-100 mb-3" role="group">
                        <button class="btn btn-success" id="preview-confirm">
                            <i class="bx bx-check"></i> Confirm
                        </button>
                        <button class="btn btn-outline-secondary" id="preview-cancel">
                            <i class="bx bx-x"></i> Cancel
                        </button>
                    </div>
                    <div class="alert alert-light border p-2 small mb-0">
                        <i class="bx bx-time-five me-1"></i> Auto-confirms in <span id="auto-confirm-timer">2</span> seconds
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Recent Scans (25%) - Always visible -->
        <div class="col-12 col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bx bx-history me-1"></i>Recent Scans</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush" id="recent-scans" style="max-height: 500px; overflow-y: auto;">
                        <li class="list-group-item text-muted text-center py-4">
                            <i class="bx bx-history bx-lg"></i>
                            <div class="mt-2">No scans yet</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Diagnostics Panel (Collapsible) -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="accordion" id="diagnosticsAccordion">
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" 
                                data-bs-toggle="collapse" data-bs-target="#diagnosticsPanel">
                            <i class="bx bx-desktop me-2"></i>Diagnostics & System Status
                        </button>
                    </h2>
                    <div id="diagnosticsPanel" class="accordion-collapse collapse">
                        <div class="accordion-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3"><i class="bx bx-chip me-1"></i>System Status</h6>
                                    <div id="system-status">
                                        <div class="text-center py-3">
                                            <div class="spinner-border text-primary" role="status"></div>
                                            <div class="mt-2">Loading system information...</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-3"><i class="bx bx-list-ul me-1"></i>Scanner Activity Log</h6>
                                    <div id="diagnostic-logs" style="max-height:300px;overflow-y:auto" class="border rounded p-2 bg-light">
                                        <div class="text-center py-3 text-muted">
                                            <i class="bx bx-file bx-lg"></i>
                                            <div class="mt-2">Activity will appear here...</div>
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
</div>
<!-- / Content -->

<!-- Scanner Configuration Modal -->
<div class="modal fade" id="scannerConfigModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-cog me-2"></i>Scanner Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Scan Debounce (milliseconds)</label>
                    <input type="number" class="form-control" id="config-debounce" value="800" min="0" max="5000" step="100">
                    <div class="form-text">Minimum time between scans to prevent double-scanning</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Duplicate Suppression (milliseconds)</label>
                    <input type="number" class="form-control" id="config-duplicate" value="3000" min="0" max="10000" step="500">
                    <div class="form-text">Ignore same student ID within this time window</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Auto-confirm Delay (milliseconds)</label>
                    <input type="number" class="form-control" id="config-autoconfirm" value="2000" min="0" max="10000" step="500">
                    <div class="form-text">Time before auto-confirming student preview (0 = manual only)</div>
                </div>
                <hr>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="config-sound" checked>
                    <label class="form-check-label" for="config-sound">
                        <i class="bx bx-volume-full me-1"></i>Enable Sound Effects
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="config-flash" checked>
                    <label class="form-check-label" for="config-flash">
                        <i class="bx bx-brightness me-1"></i>Enable Visual Flash Feedback
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-scanner-config">
                    <i class="bx bx-save me-1"></i>Save Settings
                </button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/partials/footer.php'; ?>

<script>
// Legacy functions for compatibility with existing code
function loadRecentScans() {
    fetch('api/attendance.php?action=get_recent_scans')
        .then(response => response.json())
        .then(data => {
            const recentScansList = document.getElementById('recent-scans');
            if (data.success && data.scans && data.scans.length > 0) {
                recentScansList.innerHTML = '';
                data.scans.forEach(scan => {
                    const li = document.createElement('li');
                    li.className = 'list-group-item d-flex justify-content-between align-items-center';
                    li.innerHTML = `
                        <div>
                            <div class="fw-bold">${scan.student_name}</div>
                            <small class="text-muted">${scan.student_id} • ${scan.status}</small>
                        </div>
                        <small class="text-muted">${scan.time}</small>
                    `;
                    recentScansList.appendChild(li);
                });
            } else {
                recentScansList.innerHTML = '<li class="list-group-item text-muted text-center py-4"><i class="bx bx-history bx-lg"></i><div class="mt-2">No recent scans</div></li>';
            }
        })
        .catch(error => console.error('Error loading recent scans:', error));
}

function updateScanCount() {
    fetch('api/attendance.php?action=get_scan_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('scan-count').textContent = data.total_scans || 0;
                document.getElementById('last-scan-time').textContent = data.last_scan_time || '-';
            }
        })
        .catch(error => console.error('Error loading scan stats:', error));
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadRecentScans();
    updateScanCount();
    
    // Mode toggle functionality
    const toggle = document.getElementById('mode-toggle');
    const label = document.getElementById('mode-label');
    const scanner = document.getElementById('scanner-section');
    const manual = document.getElementById('manual-section');

    if (toggle) {
        toggle.addEventListener('change', function() {
            const isManual = toggle.checked;
            label.textContent = isManual ? 'Manual' : 'Scanner';
            scanner.classList.toggle('d-none', isManual);
            manual.classList.toggle('d-none', !isManual);
        });
    }

    // Manual submit button
    const manualSubmit = document.getElementById('manual-submit');
    if (manualSubmit) {
        manualSubmit.addEventListener('click', async function() {
            const studentId = document.getElementById('manual-student-id').value.trim();
            const notes = document.getElementById('manual-notes').value.trim();
            const csrf = document.getElementById('csrf-token').value;

            if (!studentId) {
                alert('Please enter Student ID');
                return;
            }

            try {
                const resp = await fetch('api/attendance.php?action=save_attendance', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrf
                    },
                    body: JSON.stringify({
                        student_id: studentId,
                        notes: notes
                    })
                });
                const data = await resp.json();
                if (resp.ok && data && data.success) {
                    alert('Attendance saved successfully!');
                    document.getElementById('manual-student-id').value = '';
                    document.getElementById('manual-notes').value = '';
                    updateScanCount();
                    loadRecentScans();
                } else {
                    alert(data.error || 'Failed to save attendance');
                }
            } catch (e) {
                alert('Network error');
            }
        });
    }
    
    // Mark Absent button
    const markAbsentBtn = document.getElementById('mark-absent-btn');
    if (markAbsentBtn) {
        markAbsentBtn.addEventListener('click', async function() {
            if (!confirm('Mark all students who haven\'t checked in as absent?')) return;

            markAbsentBtn.disabled = true;
            markAbsentBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i>Processing...';

            try {
                const response = await fetch('api/attendance.php?action=mark_absent_students', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.getElementById('csrf-token').value
                    },
                    body: JSON.stringify({})
                });
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    loadRecentScans();
                    updateScanCount();
                } else {
                    alert(data.error || 'Failed to mark absent students');
                }
            } catch (error) {
                alert('Network error');
            } finally {
                markAbsentBtn.disabled = false;
                markAbsentBtn.innerHTML = '<i class="bx bx-user-x me-1"></i>Mark Absent';
            }
        });
    }

    // Auto Absent button
    const autoAbsentBtn = document.getElementById('auto-absent-btn');
    if (autoAbsentBtn) {
        autoAbsentBtn.addEventListener('click', async function() {
            autoAbsentBtn.disabled = true;
            autoAbsentBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i>Checking...';

            try {
                const response = await fetch('api/attendance.php?action=auto_mark_absent', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': document.getElementById('csrf-token').value
                    },
                    body: JSON.stringify({})
                });
                const data = await response.json();
                
                if (data.success) {
                    let message = data.message;
                    if (data.total_marked > 0) {
                        message += ` (${data.total_marked} students marked absent)`;
                    }
                    alert(message);
                    loadRecentScans();
                    updateScanCount();
                } else {
                    alert(data.error || 'Auto absent check failed');
                }
            } catch (error) {
                alert('Network error');
            } finally {
                autoAbsentBtn.disabled = false;
                autoAbsentBtn.innerHTML = '<i class="bx bx-time me-1"></i>Auto Check';
            }
        });
    }
});
</script>

<!-- Load Enhanced Scanner Module -->
<script src="js/scanner-enhanced.js"></script>