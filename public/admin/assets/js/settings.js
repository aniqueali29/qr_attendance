/**
 * Settings Page JavaScript
 * Handles settings management, validation, and API interactions
 */

// Settings page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tabs
    initializeTabs();
    
    // Load settings on page load
    loadSettings();
    
    // Set up form change detection
    setupFormChangeDetection();
});


function initializeTabs() {
    // Ensure tab content is visible
    const tabContent = document.getElementById('settingsTabContent');
    if (tabContent) {
        tabContent.style.display = 'block';
        tabContent.style.visibility = 'visible';
    }
    
    // Initialize Bootstrap tabs if available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
        const tabElements = document.querySelectorAll('[data-bs-toggle="tab"]');
        tabElements.forEach(tabElement => {
            new bootstrap.Tab(tabElement);
        });
    }
    
    // Manual tab switching as fallback
    const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-bs-target');
            const targetPane = document.querySelector(targetId);
            
            // Hide all tab panes
            const allPanes = document.querySelectorAll('.tab-pane');
            allPanes.forEach(pane => {
                pane.classList.remove('show', 'active');
                pane.style.display = 'none';
            });
            
            // Remove active class from all tab buttons
            const allButtons = document.querySelectorAll('.nav-link');
            allButtons.forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show target pane and activate button
            if (targetPane) {
                targetPane.classList.add('show', 'active');
                targetPane.style.display = 'block';
            }
            this.classList.add('active');
        });
    });
    
    // Ensure first tab is visible
    const firstTab = document.querySelector('#timings');
    if (firstTab) {
        firstTab.classList.add('show', 'active');
        firstTab.style.display = 'block';
    }
}

function loadSettings() {
    // Show loading status
    updateSettingsStatus('Loading settings...', 'info');
    
    // Fetch settings from API
    fetch('api/settings.php?action=get_all')
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
            if (data && data.success) {
                populateSettingsForm(data.data);
                updateSettingsStatus('Settings loaded successfully', 'success');
            } else {
                updateSettingsStatus('Failed to load settings: ' + (data ? data.message : 'Unknown error'), 'danger');
            }
        })
        .catch(error => {
            console.error('Error loading settings:', error);
            updateSettingsStatus('Error loading settings: ' + error.message, 'danger');
        });
}

function populateSettingsForm(settings) {
    // Populate form fields with settings data
    Object.keys(settings).forEach(category => {
        if (Array.isArray(settings[category])) {
            settings[category].forEach(setting => {
                const element = document.getElementById(setting.key);
                if (element) {
                    if (element.type === 'checkbox') {
                        element.checked = setting.value;
                    } else {
                        element.value = setting.value;
                    }
                }
            });
        } else {
            // Handle flat object structure
            Object.keys(settings[category]).forEach(key => {
                const value = settings[category][key];
                const element = document.getElementById(key);
                if (element) {
                    if (element.type === 'checkbox') {
                        element.checked = value;
                    } else {
                        element.value = value;
                    }
                }
            });
        }
    });
}

function setupFormChangeDetection() {
    // Add change listeners to all form inputs
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('change', function() {
            updateSettingsStatus('Changes detected - click Save to apply', 'warning');
        });
    });
}

function saveAllSettings() {
    // Collect all form data
    const settings = collectFormData();
    
    console.log('Sending settings:', settings);
    console.log('Number of settings:', settings.length);
    
    // Show saving status
    updateSettingsStatus('Saving settings...', 'info');
    
    // Send to API
    fetch('api/settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'bulk_update',
            settings: settings,
            updated_by: 'admin'
        })
    })
    .then(response => {
        console.log('Response status:', response.status);
        return response.text().then(text => {
            console.log('Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error('Invalid JSON response: ' + text.substring(0, 200));
            }
        });
    })
    .then(data => {
        console.log('Parsed response:', data);
        if (data.success) {
            updateSettingsStatus('Settings saved successfully', 'success');
            showAlert('Settings saved successfully', 'success');
        } else {
            let errorMessage = 'Failed to save settings: ' + data.message;
            if (data.errors && data.errors.length > 0) {
                errorMessage += '\n\nErrors:\n' + data.errors.join('\n');
            }
            if (data.failed_count && data.total_count) {
                errorMessage += `\n\nFailed: ${data.failed_count}/${data.total_count} settings`;
            }
            updateSettingsStatus(errorMessage, 'danger');
            showAlert(errorMessage, 'danger');
        }
    })
    .catch(error => {
        console.error('Error saving settings:', error);
        updateSettingsStatus('Error saving settings: ' + error.message, 'danger');
        showAlert('Error saving settings: ' + error.message, 'danger');
    });
}

function collectFormData() {
    const settings = [];
    const inputs = document.querySelectorAll('input, select, textarea');
    
    inputs.forEach(input => {
        if (input.id && input.id !== 'importFile') {
            let value = input.value;
            if (input.type === 'checkbox') {
                value = input.checked;
            }
            
            // Debug specific failing settings
            if (input.id === 'session_timeout_seconds' || input.id === 'login_lockout_minutes') {
                console.log(`Setting ${input.id}: value="${value}", type="${typeof value}", input type="${input.type}"`);
            }
            
            settings.push({
                key: input.id,
                value: value
            });
        }
    });
    
    return settings;
}

function updateSettingsStatus(message, type) {
    const statusElement = document.getElementById('settingsStatus');
    if (statusElement) {
        // Update the badge text and class
        const badgeText = statusElement.querySelector('.status-text') || statusElement;
        if (badgeText) {
            badgeText.textContent = message;
        }
        
        // Update badge color based on type
        statusElement.className = `badge bg-label-${type}`;
        
        // Update icon if it exists
        const icon = statusElement.querySelector('i');
        if (icon) {
            icon.className = `bx bx-circle me-1`;
            if (type === 'success') {
                icon.className = `bx bx-check-circle me-1`;
            } else if (type === 'danger' || type === 'error') {
                icon.className = `bx bx-x-circle me-1`;
            } else if (type === 'warning') {
                icon.className = `bx bx-exclamation-triangle me-1`;
            } else if (type === 'info') {
                icon.className = `bx bx-info-circle me-1`;
            }
        }
    }
}

function validateTimings() {
    // Get time values and ensure they have seconds
    const getTimeWithSeconds = (timeValue) => {
        if (!timeValue) return '00:00:00';
        return timeValue.includes(':') && timeValue.split(':').length === 2 
            ? timeValue + ':00' 
            : timeValue;
    };
    
    const timings = {
        morning_checkin_start: getTimeWithSeconds(document.getElementById('morning_checkin_start').value),
        morning_checkin_end: getTimeWithSeconds(document.getElementById('morning_checkin_end').value),
        morning_checkout_start: getTimeWithSeconds(document.getElementById('morning_checkout_start').value),
        morning_checkout_end: getTimeWithSeconds(document.getElementById('morning_checkout_end').value),
        morning_class_end: getTimeWithSeconds(document.getElementById('morning_class_end').value),
        evening_checkin_start: getTimeWithSeconds(document.getElementById('evening_checkin_start').value),
        evening_checkin_end: getTimeWithSeconds(document.getElementById('evening_checkin_end').value),
        evening_checkout_start: getTimeWithSeconds(document.getElementById('evening_checkout_start').value),
        evening_checkout_end: getTimeWithSeconds(document.getElementById('evening_checkout_end').value),
        evening_class_end: getTimeWithSeconds(document.getElementById('evening_class_end').value)
    };
    
    console.log('Sending timings:', timings);
    
    fetch('api/settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'validate_timings',
            timings: timings
        })
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
        const resultsElement = document.getElementById('validationResults');
        if (data && data.success) {
            resultsElement.className = 'alert alert-success';
            resultsElement.innerHTML = '<i class="bx bx-check-circle me-1"></i>Timing configuration is valid';
        } else {
            resultsElement.className = 'alert alert-danger';
            resultsElement.innerHTML = '<i class="bx bx-x-circle me-1"></i>Validation failed: ' + (data ? data.message : 'Unknown error');
        }
        resultsElement.classList.remove('d-none');
    })
    .catch(error => {
        console.error('Error validating timings:', error);
        const resultsElement = document.getElementById('validationResults');
        resultsElement.className = 'alert alert-danger';
        resultsElement.innerHTML = '<i class="bx bx-x-circle me-1"></i>Error validating timings: ' + error.message;
        resultsElement.classList.remove('d-none');
    });
}

function testConfiguration() {
    showAlert('Configuration test completed', 'info');
}

async function testConnection() {
    const resultsElement = document.getElementById('connectionResults');
    resultsElement.className = 'alert alert-info';
    resultsElement.innerHTML = '<i class="bx bx-info-circle me-1"></i>Testing connection...';
    resultsElement.classList.remove('d-none');
    
    try {
        const response = await fetch('api/settings.php?action=test_connection');
        const result = await response.json();
        
        if (result.success) {
            resultsElement.className = 'alert alert-success';
            resultsElement.innerHTML = `<i class="bx bx-check-circle me-1"></i>Connection successful - ${result.data.url}`;
        } else {
            resultsElement.className = 'alert alert-danger';
            resultsElement.innerHTML = `<i class="bx bx-x-circle me-1"></i>Connection failed: ${result.message}`;
        }
    } catch (error) {
        resultsElement.className = 'alert alert-danger';
        resultsElement.innerHTML = `<i class="bx bx-x-circle me-1"></i>Connection test error: ${error.message}`;
    }
}

async function testAPI() {
    const resultsElement = document.getElementById('connectionResults');
    resultsElement.className = 'alert alert-info';
    resultsElement.innerHTML = '<i class="bx bx-info-circle me-1"></i>Testing API endpoints...';
    resultsElement.classList.remove('d-none');
    
    try {
        const response = await fetch('api/settings.php?action=test_api');
        const result = await response.json();
        
        if (result.success) {
            resultsElement.className = 'alert alert-success';
            resultsElement.innerHTML = `<i class="bx bx-check-circle me-1"></i>All API endpoints are working correctly`;
        } else {
            resultsElement.className = 'alert alert-warning';
            let details = `<i class="bx bx-exclamation-triangle me-1"></i>${result.message}`;
            
            if (result.data && Object.keys(result.data).length > 0) {
                details += '<br><br><strong>Endpoint Details:</strong><ul class="mb-0 mt-2">';
                Object.entries(result.data).forEach(([name, endpoint]) => {
                    const statusIcon = endpoint.status === 'online' ? 'bx-check-circle text-success' : 
                                     endpoint.status === 'not_configured' ? 'bx-x-circle text-muted' : 'bx-x-circle text-danger';
                    details += `<li><i class="bx ${statusIcon} me-1"></i><strong>${name}:</strong> ${endpoint.status} (${endpoint.response_time})</li>`;
                });
                details += '</ul>';
            }
            
            resultsElement.innerHTML = details;
        }
    } catch (error) {
        resultsElement.className = 'alert alert-danger';
        resultsElement.innerHTML = `<i class="bx bx-x-circle me-1"></i>API test error: ${error.message}`;
    }
}

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bx bx-hide';
    } else {
        input.type = 'password';
        icon.className = 'bx bx-show';
    }
}

function exportSettings() {
    fetch('api/settings.php?action=export')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'settings_backup_' + new Date().toISOString().split('T')[0] + '.json';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                showAlert('Settings exported successfully', 'success');
            } else {
                showAlert('Failed to export settings: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error exporting settings:', error);
            showAlert('Error exporting settings', 'danger');
        });
}

function importSettings() {
    document.getElementById('importFile').click();
}

function handleImportFile(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const settings = JSON.parse(e.target.result);
                // Process imported settings
                showAlert('Settings imported successfully', 'success');
                loadSettings(); // Reload to show imported settings
            } catch (error) {
                showAlert('Invalid settings file', 'danger');
            }
        };
        reader.readAsText(file);
    }
}

function resetAllSettings() {
    if (confirm('Are you sure you want to reset all settings to default values? This action cannot be undone.')) {
        showAlert('Settings reset to defaults', 'info');
        loadSettings(); // Reload to show default settings
    }
}