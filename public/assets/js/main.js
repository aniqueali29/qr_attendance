/**
 * Unified JavaScript for QR Code Attendance System
 * Common functionality across all pages
 */

// Global variables
window.QRSystem = {
    config: {
        apiBaseUrl: '/api',
        refreshInterval: 30000, // 30 seconds
        animationDuration: 300,
        maxRetries: 3
    },
    state: {
        isOnline: navigator.onLine,
        lastUpdate: null,
        refreshInterval: null
    },
    utils: {},
    ui: {},
    api: {}
};

// Utility Functions
window.QRSystem.utils = {
    /**
     * Format date to readable string
     */
    formatDate: function(date, options = {}) {
        const defaultOptions = {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        
        return new Date(date).toLocaleString('en-US', { ...defaultOptions, ...options });
    },

    /**
     * Format file size
     */
    formatFileSize: function(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;
        
        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }
        
        return `${size.toFixed(1)} ${units[unitIndex]}`;
    },

    /**
     * Debounce function
     */
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle function
     */
    throttle: function(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    /**
     * Generate unique ID
     */
    generateId: function() {
        return Math.random().toString(36).substr(2, 9);
    },

    /**
     * Validate email
     */
    isValidEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    /**
     * Validate student ID
     */
    isValidStudentId: function(studentId) {
        return /^[A-Z0-9-]+$/.test(studentId) && studentId.length >= 5;
    },

    /**
     * Sanitize HTML
     */
    sanitizeHtml: function(str) {
        const temp = document.createElement('div');
        temp.textContent = str;
        return temp.innerHTML;
    },

    /**
     * Copy text to clipboard
     */
    copyToClipboard: async function(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (err) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            const successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            return successful;
        }
    },

    /**
     * Download file
     */
    downloadFile: function(url, filename) {
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
};

// UI Components
window.QRSystem.ui = {
    /**
     * Show alert message
     */
    showAlert: function(message, type = 'info', duration = 5000) {
        const alertContainer = document.getElementById('alert-container') || this.createAlertContainer();
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} fade-in`;
        alert.innerHTML = `
            <i class="fas fa-${this.getAlertIcon(type)}"></i>
            ${message}
        `;
        
        alertContainer.appendChild(alert);
        
        if (duration > 0) {
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, duration);
        }
        
        return alert;
    },

    /**
     * Create alert container if it doesn't exist
     */
    createAlertContainer: function() {
        const container = document.createElement('div');
        container.id = 'alert-container';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 1000; max-width: 400px;';
        document.body.appendChild(container);
        return container;
    },

    /**
     * Get alert icon based on type
     */
    getAlertIcon: function(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-triangle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    },

    /**
     * Show loading state
     */
    showLoading: function(element, message = 'Loading...') {
        if (typeof element === 'string') {
            element = document.getElementById(element);
        }
        
        if (element) {
            element.innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    ${message}
                </div>
            `;
        }
    },

    /**
     * Hide loading state
     */
    hideLoading: function(element) {
        if (typeof element === 'string') {
            element = document.getElementById(element);
        }
        
        if (element) {
            const loading = element.querySelector('.loading');
            if (loading) {
                loading.remove();
            }
        }
    },

    /**
     * Show modal
     */
    showModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    },

    /**
     * Hide modal
     */
    hideModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    },

    /**
     * Toggle modal
     */
    toggleModal: function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            if (modal.style.display === 'block') {
                this.hideModal(modalId);
            } else {
                this.showModal(modalId);
            }
        }
    },

    /**
     * Update progress bar
     */
    updateProgress: function(progressId, percentage) {
        const progress = document.getElementById(progressId);
        if (progress) {
            const bar = progress.querySelector('.progress-bar');
            if (bar) {
                bar.style.width = `${Math.min(100, Math.max(0, percentage))}%`;
            }
        }
    },

    /**
     * Animate number counter
     */
    animateCounter: function(elementId, targetValue, duration = 1000) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        const startValue = parseInt(element.textContent) || 0;
        const increment = (targetValue - startValue) / (duration / 16);
        let currentValue = startValue;
        
        const timer = setInterval(() => {
            currentValue += increment;
            if ((increment > 0 && currentValue >= targetValue) || 
                (increment < 0 && currentValue <= targetValue)) {
                currentValue = targetValue;
                clearInterval(timer);
            }
            element.textContent = Math.round(currentValue);
        }, 16);
    }
};

// API Functions
window.QRSystem.api = {
    /**
     * Make API request
     */
    request: async function(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            }
        };
        
        const config = { ...defaultOptions, ...options };
        
        try {
            const response = await fetch(url, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API request failed:', error);
            throw error;
        }
    },

    /**
     * GET request
     */
    get: function(url, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const fullUrl = queryString ? `${url}?${queryString}` : url;
        return this.request(fullUrl);
    },

    /**
     * POST request
     */
    post: function(url, data = {}) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    /**
     * PUT request
     */
    put: function(url, data = {}) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    /**
     * DELETE request
     */
    delete: function(url) {
        return this.request(url, {
            method: 'DELETE'
        });
    },

    /**
     * Upload file
     */
    upload: function(url, formData) {
        return this.request(url, {
            method: 'POST',
            body: formData,
            headers: {} // Let browser set Content-Type for FormData
        });
    }
};

// Auto-refresh functionality
window.QRSystem.autoRefresh = {
    /**
     * Start auto refresh
     */
    start: function(callback, interval = null) {
        const refreshInterval = interval || window.QRSystem.config.refreshInterval;
        
        if (window.QRSystem.state.refreshInterval) {
            this.stop();
        }
        
        window.QRSystem.state.refreshInterval = setInterval(() => {
            if (window.QRSystem.state.isOnline && callback) {
                callback();
            }
        }, refreshInterval);
    },

    /**
     * Stop auto refresh
     */
    stop: function() {
        if (window.QRSystem.state.refreshInterval) {
            clearInterval(window.QRSystem.state.refreshInterval);
            window.QRSystem.state.refreshInterval = null;
        }
    },

    /**
     * Restart auto refresh
     */
    restart: function(callback, interval = null) {
        this.stop();
        this.start(callback, interval);
    }
};

// Online/Offline detection
window.QRSystem.network = {
    /**
     * Initialize network monitoring
     */
    init: function() {
        window.addEventListener('online', this.handleOnline.bind(this));
        window.addEventListener('offline', this.handleOffline.bind(this));
    },

    /**
     * Handle online event
     */
    handleOnline: function() {
        window.QRSystem.state.isOnline = true;
        window.QRSystem.ui.showAlert('Connection restored', 'success');
        this.onlineCallback && this.onlineCallback();
    },

    /**
     * Handle offline event
     */
    handleOffline: function() {
        window.QRSystem.state.isOnline = false;
        window.QRSystem.ui.showAlert('Connection lost', 'warning');
        this.offlineCallback && this.offlineCallback();
    },

    /**
     * Set online callback
     */
    setOnlineCallback: function(callback) {
        this.onlineCallback = callback;
    },

    /**
     * Set offline callback
     */
    setOfflineCallback: function(callback) {
        this.offlineCallback = callback;
    }
};

// Form validation
window.QRSystem.validation = {
    /**
     * Validate form
     */
    validateForm: function(formId) {
        const form = document.getElementById(formId);
        if (!form) return false;
        
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    },

    /**
     * Validate individual field
     */
    validateField: function(field) {
        const value = field.value.trim();
        const type = field.type;
        const required = field.hasAttribute('required');
        
        // Clear previous validation
        this.clearFieldError(field);
        
        // Check required fields
        if (required && !value) {
            this.showFieldError(field, 'This field is required');
            return false;
        }
        
        // Type-specific validation
        if (value) {
            switch (type) {
                case 'email':
                    if (!window.QRSystem.utils.isValidEmail(value)) {
                        this.showFieldError(field, 'Please enter a valid email address');
                        return false;
                    }
                    break;
                    
                case 'tel':
                    if (!/^[\+]?[0-9\s\-\(\)]+$/.test(value)) {
                        this.showFieldError(field, 'Please enter a valid phone number');
                        return false;
                    }
                    break;
                    
                case 'password':
                    if (value.length < 8) {
                        this.showFieldError(field, 'Password must be at least 8 characters long');
                        return false;
                    }
                    break;
            }
        }
        
        return true;
    },

    /**
     * Show field error
     */
    showFieldError: function(field, message) {
        field.classList.add('error');
        
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        errorElement.textContent = message;
        errorElement.style.cssText = 'color: var(--danger-color); font-size: 0.8rem; margin-top: 4px;';
        
        field.parentNode.appendChild(errorElement);
    },

    /**
     * Clear field error
     */
    clearFieldError: function(field) {
        field.classList.remove('error');
        const errorElement = field.parentNode.querySelector('.field-error');
        if (errorElement) {
            errorElement.remove();
        }
    }
};

// Chart utilities
window.QRSystem.charts = {
    /**
     * Create doughnut chart
     */
    createDoughnut: function(canvasId, data, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        };
        
        return new Chart(ctx, {
            type: 'doughnut',
            data: data,
            options: { ...defaultOptions, ...options }
        });
    },

    /**
     * Create line chart
     */
    createLine: function(canvasId, data, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        };
        
        return new Chart(ctx, {
            type: 'line',
            data: data,
            options: { ...defaultOptions, ...options }
        });
    },

    /**
     * Create bar chart
     */
    createBar: function(canvasId, data, options = {}) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return null;
        
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        };
        
        return new Chart(ctx, {
            type: 'bar',
            data: data,
            options: { ...defaultOptions, ...options }
        });
    }
};

// Initialize system
document.addEventListener('DOMContentLoaded', function() {
    // Initialize network monitoring
    window.QRSystem.network.init();
    
    // Add global event listeners
    addGlobalEventListeners();
    
    // Initialize common UI elements
    initializeCommonUI();
});

/**
 * Add global event listeners
 */
function addGlobalEventListeners() {
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
    
    // Handle form submissions
    document.addEventListener('submit', function(event) {
        const form = event.target;
        if (form.hasAttribute('data-validate')) {
            event.preventDefault();
            if (window.QRSystem.validation.validateForm(form.id)) {
                // Form is valid, proceed with submission
                handleFormSubmission(form);
            }
        }
    });
    
    // Handle real-time validation
    document.addEventListener('blur', function(event) {
        if (event.target.hasAttribute('data-validate')) {
            window.QRSystem.validation.validateField(event.target);
        }
    }, true);
}

/**
 * Initialize common UI elements
 */
function initializeCommonUI() {
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize dropdowns
    initializeDropdowns();
    
    // Initialize tabs
    initializeTabs();
}

/**
 * Initialize tooltips
 */
function initializeTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

/**
 * Show tooltip
 */
function showTooltip(event) {
    const element = event.target;
    const text = element.getAttribute('data-tooltip');
    
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    tooltip.style.cssText = `
        position: absolute;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 0.8rem;
        z-index: 1000;
        pointer-events: none;
    `;
    
    document.body.appendChild(tooltip);
    
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';
    
    element._tooltip = tooltip;
}

/**
 * Hide tooltip
 */
function hideTooltip(event) {
    const element = event.target;
    if (element._tooltip) {
        element._tooltip.remove();
        delete element._tooltip;
    }
}

/**
 * Initialize dropdowns
 */
function initializeDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                menu.classList.toggle('show');
            });
        }
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
    });
}

/**
 * Initialize tabs
 */
function initializeTabs() {
    const tabContainers = document.querySelectorAll('.tab-container');
    tabContainers.forEach(container => {
        const tabs = container.querySelectorAll('.tab');
        const contents = container.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                
                // Remove active class from all tabs and contents
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                this.classList.add('active');
                if (targetId) {
                    const targetContent = document.getElementById(targetId);
                    if (targetContent) {
                        targetContent.classList.add('active');
                    }
                }
            });
        });
    });
}

/**
 * Handle form submission
 */
function handleFormSubmission(form) {
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    // Add loading state
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
        const originalText = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.innerHTML = '<div class="spinner"></div> Processing...';
        
        // Simulate form submission (replace with actual API call)
        setTimeout(() => {
            submitButton.disabled = false;
            submitButton.textContent = originalText;
            window.QRSystem.ui.showAlert('Form submitted successfully!', 'success');
        }, 1000);
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = window.QRSystem;
}
