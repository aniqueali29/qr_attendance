<?php
/**
 * Admin Settings Page
 * System configuration and settings management
 */

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

// Require admin authentication
requireAdminAuth();

$pageTitle = "System Settings";
$currentPage = "settings";
$pageCSS = ['css/settings.css'];
$pageJS = ['js/settings.js'];

include 'partials/header.php';
include 'partials/sidebar.php';
include 'partials/navbar.php';
?>

<!-- Content wrapper -->
<div class="content-wrapper">
    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">
        <!-- Header -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-6">
                                <h4 class="card-title mb-0">
                                    <i class="bx bx-cog me-2"></i>System Settings
                                </h4>
                                <p class="card-subtitle mb-0">Configure system parameters and timing settings</p>
                            </div>
                            <div class="col-12 col-md-6 mt-2 mt-md-0">
                                <div class="d-flex flex-wrap align-items-center gap-2 justify-content-md-end">
                                    <div class="settings-status">
                                        <span class="badge bg-label-info" id="settingsStatus">
                                            <i class="bx bx-circle me-1"></i> <span class="status-text">Loading...</span>
                                        </span>
                                    </div>
                                    <button class="btn btn-primary" onclick="saveAllSettings()">
                                        <i class="bx bx-save me-1"></i>
                                        <span class="btn-text">Save All</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="timings-tab" data-bs-toggle="tab" data-bs-target="#timings" type="button" role="tab">
                                    <i class="bx bx-time me-1"></i>Shift Timings
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                                    <i class="bx bx-slider me-1"></i>System Config
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="integration-tab" data-bs-toggle="tab" data-bs-target="#integration" type="button" role="tab">
                                    <i class="bx bx-plug me-1"></i>Integration
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="advanced-tab" data-bs-toggle="tab" data-bs-target="#advanced" type="button" role="tab">
                                    <i class="bx bx-cog me-1"></i>Advanced
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="settingsTabContent">
            <!-- Shift Timings Tab -->
            <div class="tab-pane fade show active" id="timings" role="tabpanel">
                <div class="row">
                    <!-- Morning Shift Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-sun me-2"></i>Morning Shift Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="morning_checkin_start" class="form-label">Check-in Start Time</label>
                                        <input type="time" class="form-control" id="morning_checkin_start" value="09:00">
                                        <div class="form-text">When students can start checking in</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="morning_checkin_end" class="form-label">Check-in End Time</label>
                                        <input type="time" class="form-control" id="morning_checkin_end" value="11:00">
                                        <div class="form-text">Last time students can check in</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="morning_checkout_start" class="form-label">Check-out Start Time</label>
                                        <input type="time" class="form-control" id="morning_checkout_start" value="12:00">
                                        <div class="form-text">When students can start checking out</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="morning_checkout_end" class="form-label">Check-out End Time</label>
                                        <input type="time" class="form-control" id="morning_checkout_end" value="13:40">
                                        <div class="form-text">Last time students can check out</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="morning_class_end" class="form-label">Class End Time</label>
                                        <input type="time" class="form-control" id="morning_class_end" value="13:40">
                                        <div class="form-text">When the class session ends</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Evening Shift Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-moon me-2"></i>Evening Shift Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="evening_checkin_start" class="form-label">Check-in Start Time</label>
                                        <input type="time" class="form-control" id="evening_checkin_start" value="15:00">
                                        <div class="form-text">When students can start checking in</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="evening_checkin_end" class="form-label">Check-in End Time</label>
                                        <input type="time" class="form-control" id="evening_checkin_end" value="18:00">
                                        <div class="form-text">Last time students can check in</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="evening_checkout_start" class="form-label">Check-out Start Time</label>
                                        <input type="time" class="form-control" id="evening_checkout_start" value="15:00">
                                        <div class="form-text">When students can start checking out</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="evening_checkout_end" class="form-label">Check-out End Time</label>
                                        <input type="time" class="form-control" id="evening_checkout_end" value="18:00">
                                        <div class="form-text">Last time students can check out</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="evening_class_end" class="form-label">Class End Time</label>
                                        <input type="time" class="form-control" id="evening_class_end" value="18:00">
                                        <div class="form-text">When the class session ends</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timing Validation -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-check-circle me-2"></i>Timing Validation
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <button class="btn btn-outline-primary" onclick="validateTimings()">
                                        <i class="bx bx-check me-1"></i>
                                        <span class="btn-text">Validate</span>
                                    </button>
                                    <button class="btn btn-outline-info" onclick="testConfiguration()">
                                        <i class="bx bx-play me-1"></i>
                                        <span class="btn-text">Test</span>
                                    </button>
                                </div>
                                <div id="validationResults" class="alert alert-info d-none">
                                    <i class="bx bx-info-circle me-1"></i>
                                    <span class="validation-message">Click "Validate Configuration" to check your settings</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Configuration Tab -->
            <div class="tab-pane fade" id="system" role="tabpanel">
                <div class="row">
                    <!-- General Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-cog me-2"></i>General Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="minimum_duration_minutes" class="form-label">Minimum Duration (minutes)</label>
                                        <input type="number" class="form-control" id="minimum_duration_minutes" value="120" min="30" max="480">
                                        <div class="form-text">Minimum time required for attendance</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="sync_interval_seconds" class="form-label">Sync Interval (seconds)</label>
                                        <input type="number" class="form-control" id="sync_interval_seconds" value="60" min="10" max="300">
                                        <div class="form-text">How often to sync with web server</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="timezone" class="form-label">Timezone</label>
                                        <select class="form-select" id="timezone">
                                            <option value="Asia/Karachi">Asia/Karachi</option>
                                            <option value="UTC">UTC</option>
                                            <option value="America/New_York">America/New_York</option>
                                            <option value="Europe/London">Europe/London</option>
                                        </select>
                                        <div class="form-text">System timezone</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="academic_year_start_month" class="form-label">Academic Year Start Month</label>
                                        <select class="form-select" id="academic_year_start_month">
                                            <option value="1">January</option>
                                            <option value="2">February</option>
                                            <option value="3">March</option>
                                            <option value="4">April</option>
                                            <option value="5">May</option>
                                            <option value="6">June</option>
                                            <option value="7">July</option>
                                            <option value="8">August</option>
                                            <option value="9" selected>September</option>
                                            <option value="10">October</option>
                                            <option value="11">November</option>
                                            <option value="12">December</option>
                                        </select>
                                        <div class="form-text">When the academic year starts</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Auto-Absent Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-user-x me-2"></i>Auto-Absent Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="auto_absent_morning_hour" class="form-label">Morning Auto-Absent Hour</label>
                                        <input type="number" class="form-control" id="auto_absent_morning_hour" value="11" min="8" max="16">
                                        <div class="form-text">Hour to mark morning shift absent (24h format)</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="auto_absent_evening_hour" class="form-label">Evening Auto-Absent Hour</label>
                                        <input type="number" class="form-control" id="auto_absent_evening_hour" value="17" min="14" max="20">
                                        <div class="form-text">Hour to mark evening shift absent (24h format)</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Integration Tab -->
            <div class="tab-pane fade" id="integration" role="tabpanel">
                <div class="row">
                    <!-- Website Integration -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-link me-2"></i>Website Integration
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="website_url" class="form-label">Website URL</label>
                                        <input type="url" class="form-control" id="website_url" value="http://localhost/qr_attendance/public">
                                        <div class="form-text">Base URL of the web application</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="api_endpoint_attendance" class="form-label">Attendance API Endpoint</label>
                                        <input type="text" class="form-control" id="api_endpoint_attendance" value="/api/api_attendance.php">
                                        <div class="form-text">API endpoint for attendance data</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="api_endpoint_checkin" class="form-label">Check-in API Endpoint</label>
                                        <input type="text" class="form-control" id="api_endpoint_checkin" value="/api/checkin_api.php">
                                        <div class="form-text">API endpoint for check-in operations</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="api_endpoint_dashboard" class="form-label">Dashboard API Endpoint</label>
                                        <input type="text" class="form-control" id="api_endpoint_dashboard" value="/api/dashboard_api.php">
                                        <div class="form-text">API endpoint for dashboard data</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="api_timeout_seconds" class="form-label">API Timeout (seconds)</label>
                                        <input type="number" class="form-control" id="api_timeout_seconds" value="30" min="5" max="120">
                                        <div class="form-text">Timeout for API requests</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-key me-2"></i>Security Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="api_key" class="form-label">API Key</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="api_key" value="attendance_2025_xyz789_secure">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('api_key')">
                                                <i class="bx bx-show"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">API authentication key</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="max_sync_records" class="form-label">Max Sync Records</label>
                                        <input type="number" class="form-control" id="max_sync_records" value="1000" min="100" max="10000">
                                        <div class="form-text">Maximum records per sync operation</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="api_rate_limit" class="form-label">API Rate Limit (per hour)</label>
                                        <input type="number" class="form-control" id="api_rate_limit" value="100" min="10" max="1000">
                                        <div class="form-text">Rate limit for API requests</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Connection Test -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-wifi me-2"></i>Connection Test
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <button class="btn btn-outline-info" onclick="testConnection()">
                                        <i class="bx bx-wifi me-1"></i>
                                        <span class="btn-text">Test Website</span>
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="testAPI()">
                                        <i class="bx bx-plug me-1"></i>
                                        <span class="btn-text">Test API</span>
                                    </button>
                                </div>
                                <div id="connectionResults" class="alert alert-info d-none">
                                    <i class="bx bx-info-circle me-1"></i>
                                    <span class="connection-message">Click a test button to check connectivity</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Tab -->
            <div class="tab-pane fade" id="advanced" role="tabpanel">
                <div class="row">
                    <!-- Debug Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-bug me-2"></i>Debug Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="debug_mode" checked>
                                            <label class="form-check-label" for="debug_mode">
                                                Enable Debug Mode
                                            </label>
                                        </div>
                                        <div class="form-text">Show detailed debug information</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="log_errors" checked>
                                            <label class="form-check-label" for="log_errors">
                                                Enable Error Logging
                                            </label>
                                        </div>
                                        <div class="form-text">Log errors to system log</div>
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="show_errors" checked>
                                            <label class="form-check-label" for="show_errors">
                                                Show Errors in Development
                                            </label>
                                        </div>
                                        <div class="form-text">Display errors in development mode</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-shield me-2"></i>Security Settings
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="session_timeout_seconds" class="form-label">Session Timeout (seconds)</label>
                                        <input type="number" class="form-control" id="session_timeout_seconds" value="3600" min="300" max="86400">
                                        <div class="form-text">How long sessions remain active</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="max_login_attempts" class="form-label">Max Login Attempts</label>
                                        <input type="number" class="form-control" id="max_login_attempts" value="5" min="3" max="10">
                                        <div class="form-text">Maximum failed login attempts before lockout</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="login_lockout_minutes" class="form-label">Login Lockout (minutes)</label>
                                        <input type="number" class="form-control" id="login_lockout_minutes" value="15" min="5" max="60">
                                        <div class="form-text">How long to lockout after failed attempts</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                        <input type="number" class="form-control" id="password_min_length" value="8" min="6" max="32">
                                        <div class="form-text">Minimum required password length</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup & Restore -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bx bx-download me-2"></i>Backup & Restore
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <button class="btn btn-success" onclick="exportSettings()">
                                        <i class="bx bx-download me-1"></i>
                                        <span class="btn-text">Export</span>
                                    </button>
                                    <button class="btn btn-warning" onclick="importSettings()">
                                        <i class="bx bx-upload me-1"></i>
                                        <span class="btn-text">Import</span>
                                    </button>
                                    <button class="btn btn-danger" onclick="resetAllSettings()">
                                        <i class="bx bx-undo me-1"></i>
                                        <span class="btn-text">Reset</span>
                                    </button>
                                </div>
                                <input type="file" id="importFile" accept=".json" style="display: none;" onchange="handleImportFile(this)">
                                <div class="alert alert-info">
                                    <i class="bx bx-info-circle me-1"></i>
                                    <strong>Backup & Restore:</strong> Export your settings to a JSON file for backup, or import previously exported settings. Use "Reset to Defaults" to restore all settings to their original values.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- / Content -->
    
    <!-- Footer -->
    <footer class="content-footer footer bg-footer-theme">
        <div class="container-xxl">
            <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                <div class="mb-2 mb-md-0">
                    &#169;
                    <script>
                        document.write(new Date().getFullYear());
                    </script>
                    , made with ❤️ by
                    <a href="#" target="_blank" class="footer-link">QR Attendance System</a>
                </div>
                <div class="d-none d-lg-inline-block">
                    <a href="#" class="footer-link me-4">Documentation</a>
                    <a href="#" class="footer-link me-4">Support</a>
                </div>
            </div>
        </div>
    </footer>
    <!-- / Footer -->
    
    <div class="content-backdrop fade"></div>
</div>
<!-- Content wrapper -->

<!-- Alert Container -->
<div id="alert-container" class="toast-container"></div>
</div>
<!-- / Layout page -->
</div>
<!-- / Layout container -->
</div>
<!-- / Layout wrapper -->

<!-- Core JS -->
<script src="<?php echo getAdminAssetUrl('vendor/libs/jquery/jquery.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/libs/popper/popper.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/js/bootstrap.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/libs/perfect-scrollbar/perfect-scrollbar.js'); ?>"></script>
<script src="<?php echo getAdminAssetUrl('vendor/js/menu.js'); ?>"></script>

<!-- Main JS -->
<script src="<?php echo getAdminAssetUrl('js/main.js'); ?>"></script>

<!-- Page JS -->
<script src="<?php echo getAdminAssetUrl('js/settings.js'); ?>"></script>

<style>
/* Responsive button text */
@media (max-width: 768px) {
    .btn-text {
        display: none;
    }
    
    .d-flex.flex-wrap.gap-2 .btn {
        padding: 0.5rem 0.75rem;
    }
    
    .settings-status {
        width: 100%;
        text-align: center;
        margin-bottom: 0.5rem;
    }
}

@media (min-width: 769px) {
    .btn i.me-1 {
        margin-right: 0.5rem !important;
    }
}
</style>

</body>
</html>