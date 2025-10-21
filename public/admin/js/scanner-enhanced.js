/**
 * Enhanced Scanner Module for QR Attendance System
 * Includes: Audio feedback, visual effects, student preview, diagnostics
 */

(function() {
    'use strict';

    // ============================================================
    // Configuration Manager
    // ============================================================
    const ScannerConfig = {
        settings: {
            debounceMs: 800,
            duplicateSuppressionMs: 3000,
            autoConfirmDelayMs: 2000,
            validPattern: /^[A-Za-z0-9-_]{4,32}$/,
            soundEnabled: true,
            visualFlashEnabled: true,
            previewEnabled: true
        },
        
        load() {
            try {
                const saved = localStorage.getItem('scannerConfig');
                if (saved) {
                    Object.assign(this.settings, JSON.parse(saved));
                }
            } catch (e) {
                console.error('Failed to load scanner config:', e);
            }
        },
        
        save() {
            try {
                localStorage.setItem('scannerConfig', JSON.stringify(this.settings));
            } catch (e) {
                console.error('Failed to save scanner config:', e);
            }
        },
        
        updateFromUI() {
            this.settings.debounceMs = parseInt(document.getElementById('config-debounce')?.value || 800);
            this.settings.duplicateSuppressionMs = parseInt(document.getElementById('config-duplicate')?.value || 3000);
            this.settings.autoConfirmDelayMs = parseInt(document.getElementById('config-autoconfirm')?.value || 2000);
            this.settings.soundEnabled = document.getElementById('config-sound')?.checked ?? true;
            this.settings.visualFlashEnabled = document.getElementById('config-flash')?.checked ?? true;
            this.save();
        },
        
        loadToUI() {
            if (document.getElementById('config-debounce')) document.getElementById('config-debounce').value = this.settings.debounceMs;
            if (document.getElementById('config-duplicate')) document.getElementById('config-duplicate').value = this.settings.duplicateSuppressionMs;
            if (document.getElementById('config-autoconfirm')) document.getElementById('config-autoconfirm').value = this.settings.autoConfirmDelayMs;
            if (document.getElementById('config-sound')) document.getElementById('config-sound').checked = this.settings.soundEnabled;
            if (document.getElementById('config-flash')) document.getElementById('config-flash').checked = this.settings.visualFlashEnabled;
        }
    };

    // ============================================================
    // Audio Manager
    // ============================================================
    const AudioManager = {
        sounds: {},
        initialized: false,
        
        init() {
            if (this.initialized) return;
            
            try {
                this.sounds.success = new Audio('assets/sounds/success.mp3');
                this.sounds.checkout = new Audio('assets/sounds/checkout.mp3');
                this.sounds.error = new Audio('assets/sounds/error.mp3');
                this.sounds.warning = new Audio('assets/sounds/warning.mp3');
                
                // Preload all sounds
                Object.values(this.sounds).forEach(sound => {
                    sound.load();
                });
                
                this.initialized = true;
                DiagnosticMonitor.log('Audio system initialized', 'success');
            } catch (e) {
                DiagnosticMonitor.log('Audio initialization failed: ' + e.message, 'warning');
            }
        },
        
        play(type) {
            if (!ScannerConfig.settings.soundEnabled) return;
            
            const sound = this.sounds[type];
            if (sound) {
                try {
                    sound.currentTime = 0;
                    sound.play().catch(err => {
                        DiagnosticMonitor.log('Audio play failed: ' + err.message, 'warning');
                    });
                } catch (e) {
                    console.error('Sound play error:', e);
                }
            }
        }
    };

    // ============================================================
    // Visual Feedback Manager
    // ============================================================
    const VisualFeedback = {
        showFlash(type) {
            if (!ScannerConfig.settings.visualFlashEnabled) return;
            
            const overlay = document.getElementById('flash-overlay');
            if (!overlay) return;
            
            overlay.className = '';
            overlay.style.display = 'block';
            
            // Force reflow
            void overlay.offsetWidth;
            
            overlay.classList.add('flash-' + type);
            
            setTimeout(() => {
                overlay.style.display = 'none';
                overlay.className = '';
            }, 250);
        },
        
        pulseStatus() {
            const badge = document.getElementById('scanner-status');
            if (badge) {
                badge.classList.add('status-pulse');
                setTimeout(() => badge.classList.remove('status-pulse'), 1000);
            }
        },
        
        setStatus(text, type) {
            const badge = document.getElementById('scanner-status');
            if (!badge) return;
            
            badge.textContent = text;
            badge.className = 'badge ';
            
            switch(type) {
                case 'error':
                    badge.classList.add('bg-label-danger');
                    break;
                case 'success':
                    badge.classList.add('bg-label-success');
                    break;
                case 'warning':
                    badge.classList.add('bg-label-warning');
                    break;
                default:
                    badge.classList.add('bg-label-info');
            }
            
            this.pulseStatus();
        },
        
        showFeedback(message, type) {
            const feedback = document.getElementById('scan-feedback');
            if (!feedback) return;
            
            feedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
            
            const cssClass = {
                'success': 'alert-success',
                'error': 'alert-danger',
                'warning': 'alert-warning',
                'info': 'alert-info'
            }[type] || 'alert-info';
            
            feedback.classList.add(cssClass);
            feedback.textContent = message;
        }
    };

    // ============================================================
    // Student Preview Manager
    // ============================================================
    const StudentPreview = {
        currentStudent: null,
        confirmCallback: null,
        autoConfirmTimer: null,
        
        async show(studentId, onConfirm) {
            if (!ScannerConfig.settings.previewEnabled) {
                // Skip preview, confirm immediately
                if (onConfirm) onConfirm(studentId);
                return;
            }
            
            this.confirmCallback = onConfirm;
            DiagnosticMonitor.log('Loading student preview: ' + studentId, 'info');
            
            try {
                const response = await fetch(`api/attendance.php?action=get_student_preview&student_id=${encodeURIComponent(studentId)}`);
                const data = await response.json();
                
                if (data.success && data.student) {
                    this.currentStudent = data.student;
                    this.renderPreview(data.student);
                    DiagnosticMonitor.log('Student preview loaded: ' + data.student.name, 'success');
                    
                    // Auto-confirm timer
                    if (ScannerConfig.settings.autoConfirmDelayMs > 0) {
                        this.autoConfirmTimer = setTimeout(() => {
                            this.confirm();
                        }, ScannerConfig.settings.autoConfirmDelayMs);
                    }
                } else {
                    throw new Error(data.error || 'Student not found');
                }
            } catch (error) {
                DiagnosticMonitor.log('Preview load failed: ' + error.message, 'error');
                VisualFeedback.showFeedback('Student not found: ' + studentId, 'error');
                VisualFeedback.showFlash('error');
                AudioManager.play('error');
            }
        },
        
        renderPreview(student) {
            const preview = document.getElementById('student-preview');
            if (!preview) return;
            
            // Set photo
            const photo = document.getElementById('preview-photo');
            if (photo) {
                photo.src = student.photo_url || 'assets/img/default-avatar.svg';
                photo.onerror = function() {
                    this.src = 'assets/img/default-avatar.svg';
                };
            }
            
            // Set student info
            if (document.getElementById('preview-name')) document.getElementById('preview-name').textContent = student.name;
            if (document.getElementById('preview-id')) document.getElementById('preview-id').textContent = student.id;
            if (document.getElementById('preview-program')) document.getElementById('preview-program').textContent = student.program || '-';
            if (document.getElementById('preview-shift')) document.getElementById('preview-shift').textContent = student.shift || '-';
            
            // Set status
            let statusHtml = '';
            if (student.check_out_time) {
                const time = new Date(student.check_out_time).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                statusHtml = `<span class="badge bg-success">Checked out at ${time}</span>`;
            } else if (student.check_in_time) {
                const time = new Date(student.check_in_time).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'});
                statusHtml = `<span class="badge bg-info">Checked in at ${time}</span>`;
            } else {
                statusHtml = `<span class="badge bg-secondary">Not checked in today</span>`;
            }
            
            const statusEl = document.getElementById('preview-status');
            if (statusEl) statusEl.innerHTML = statusHtml;
            
            // Show preview with animation
            preview.style.display = 'block';
            preview.classList.add('slide-in');
        },
        
        confirm() {
            this.clearAutoConfirm();
            
            if (this.confirmCallback && this.currentStudent) {
                this.confirmCallback(this.currentStudent.id);
            }
            
            this.hide();
        },
        
        cancel() {
            this.clearAutoConfirm();
            DiagnosticMonitor.log('Student preview cancelled', 'info');
            this.hide();
        },
        
        hide() {
            const preview = document.getElementById('student-preview');
            if (preview) {
                preview.style.display = 'none';
                preview.classList.remove('slide-in');
            }
            
            this.currentStudent = null;
            this.confirmCallback = null;
        },
        
        clearAutoConfirm() {
            if (this.autoConfirmTimer) {
                clearTimeout(this.autoConfirmTimer);
                this.autoConfirmTimer = null;
            }
        }
    };

    // ============================================================
    // Diagnostic Monitor
    // ============================================================
    const DiagnosticMonitor = {
        logs: [],
        maxLogs: 50,
        
        log(message, type = 'info') {
            const entry = {
                timestamp: new Date().toISOString(),
                message: message,
                type: type
            };
            
            this.logs.unshift(entry);
            if (this.logs.length > this.maxLogs) {
                this.logs.pop();
            }
            
            this.updateDisplay();
            
            // Also log to console in development
            const consoleMethod = type === 'error' ? 'error' : type === 'warning' ? 'warn' : 'log';
            console[consoleMethod]('[Scanner]', message);
        },
        
        updateDisplay() {
            const container = document.getElementById('diagnostic-logs');
            if (!container) return;
            
            container.innerHTML = this.logs.map(log => {
                const time = new Date(log.timestamp).toLocaleTimeString();
                return `<div class="log-entry log-${log.type}">
                    <span class="log-time">${time}</span>
                    <span class="log-message">${this.escapeHtml(log.message)}</span>
                </div>`;
            }).join('');
        },
        
        async loadSystemStatus() {
            try {
                const response = await fetch('api/attendance.php?action=get_diagnostics');
                const data = await response.json();
                
                if (data.success && data.diagnostics) {
                    this.displaySystemStatus(data.diagnostics);
                }
            } catch (error) {
                this.log('Failed to load system status: ' + error.message, 'error');
            }
        },
        
        displaySystemStatus(diagnostics) {
            const container = document.getElementById('system-status');
            if (!container) return;
            
            const items = [
                {label: 'Database', value: diagnostics.database ? 'Connected' : 'Disconnected', ok: diagnostics.database},
                {label: 'Session', value: diagnostics.session_active ? 'Active' : 'Inactive', ok: diagnostics.session_active},
                {label: 'CSRF Token', value: diagnostics.csrf_token_set ? 'Set' : 'Missing', ok: diagnostics.csrf_token_set},
                {label: 'Timestamp', value: diagnostics.timestamp, ok: true},
                {label: 'Timezone', value: diagnostics.timezone, ok: true},
                {label: 'PHP Version', value: diagnostics.php_version, ok: true}
            ];
            
            container.innerHTML = items.map(item => `
                <div class="status-item">
                    <span class="status-label">${item.label}:</span>
                    <span class="status-value ${item.ok ? 'status-ok' : 'status-error'}">${this.escapeHtml(item.value)}</span>
                </div>
            `).join('');
        },
        
        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    // ============================================================
    // Main Scanner Controller
    // ============================================================
    const ScannerController = {
        input: null,
        lastScanValue: '',
        lastScanAt: 0,
        
        init() {
            DiagnosticMonitor.log('Initializing scanner controller', 'info');
            
            // Load configuration
            ScannerConfig.load();
            
            // Initialize audio
            AudioManager.init();
            
            // Get DOM elements
            this.input = document.getElementById('scan-input');
            
            // Set up event listeners
            this.setupEventListeners();
            
            // Focus input
            if (this.input) this.input.focus();
            
            // Set initial status
            VisualFeedback.setStatus('Ready', 'info');
            
            // Load diagnostics on page load
            if (document.getElementById('system-status')) {
                DiagnosticMonitor.loadSystemStatus();
            }
            
            DiagnosticMonitor.log('Scanner initialized successfully', 'success');
        },
        
        setupEventListeners() {
            // Scanner input enter key
            if (this.input) {
                this.input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const value = this.input.value.trim();
                        this.input.value = '';
                        if (value) {
                            this.handleScan(value);
                        }
                    }
                });
                
                // Auto-refocus on blur
                this.input.addEventListener('blur', () => {
                    setTimeout(() => {
                        if (this.input) this.input.focus();
                    }, 100);
                });
            }
            
            // Global keyboard shortcut (Alt+I to refocus)
            document.addEventListener('keydown', (e) => {
                if ((e.altKey || e.metaKey) && (e.key === 'i' || e.key === 'I')) {
                    e.preventDefault();
                    if (this.input) {
                        this.input.focus();
                        VisualFeedback.setStatus('Ready', 'info');
                    }
                }
            });
            
            // Preview confirm/cancel buttons
            const confirmBtn = document.getElementById('preview-confirm');
            const cancelBtn = document.getElementById('preview-cancel');
            
            if (confirmBtn) {
                confirmBtn.addEventListener('click', () => StudentPreview.confirm());
            }
            
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => StudentPreview.cancel());
            }
            
            // Scanner config modal save button
            const saveConfigBtn = document.getElementById('save-scanner-config');
            if (saveConfigBtn) {
                saveConfigBtn.addEventListener('click', () => {
                    ScannerConfig.updateFromUI();
                    DiagnosticMonitor.log('Scanner configuration saved', 'success');
                    
                    // Close modal if using Bootstrap
                    const modal = bootstrap?.Modal?.getInstance(document.getElementById('scannerConfigModal'));
                    if (modal) modal.hide();
                });
            }
            
            // Load config to UI when modal opens
            const configModal = document.getElementById('scannerConfigModal');
            if (configModal) {
                configModal.addEventListener('show.bs.modal', () => {
                    ScannerConfig.loadToUI();
                });
            }
        },
        
        handleScan(studentId) {
            const now = Date.now();
            
            // Debounce check
            if (now - this.lastScanAt < ScannerConfig.settings.debounceMs) {
                DiagnosticMonitor.log('Scan debounced: too fast', 'warning');
                return;
            }
            
            // Duplicate check
            if (studentId === this.lastScanValue && 
                (now - this.lastScanAt) < ScannerConfig.settings.duplicateSuppressionMs) {
                DiagnosticMonitor.log('Duplicate scan ignored: ' + studentId, 'warning');
                VisualFeedback.showFeedback('Duplicate scan ignored: ' + studentId, 'warning');
                VisualFeedback.setStatus('Duplicate', 'warning');
                VisualFeedback.showFlash('warning');
                AudioManager.play('warning');
                return;
            }
            
            // Validation check
            if (!ScannerConfig.settings.validPattern.test(studentId)) {
                DiagnosticMonitor.log('Invalid format: ' + studentId, 'error');
                VisualFeedback.showFeedback('Invalid student ID format', 'error');
                VisualFeedback.setStatus('Invalid', 'error');
                VisualFeedback.showFlash('error');
                AudioManager.play('error');
                return;
            }
            
            this.lastScanValue = studentId;
            this.lastScanAt = now;
            
            DiagnosticMonitor.log('Processing scan: ' + studentId, 'info');
            VisualFeedback.setStatus('Processing', 'info');
            
            // Show student preview
            StudentPreview.show(studentId, (confirmedId) => {
                this.submitAttendance(confirmedId);
            });
        },
        
        async submitAttendance(studentId) {
            DiagnosticMonitor.log('Submitting attendance for: ' + studentId, 'info');
            VisualFeedback.setStatus('Submitting', 'info');
            
            try {
                const csrfToken = document.getElementById('csrf-token')?.value || '';
                
                const response = await fetch('api/attendance.php?action=save_attendance', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        student_id: studentId,
                        source: 'scanner'
                    })
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    const isCheckout = data.mode === 'checkout';
                    const message = isCheckout ? 'Check-out successful' : 'Check-in successful';
                    
                    DiagnosticMonitor.log(message + ': ' + studentId, 'success');
                    VisualFeedback.showFeedback(message, 'success');
                    VisualFeedback.setStatus('Success', 'success');
                    VisualFeedback.showFlash('success');
                    AudioManager.play(isCheckout ? 'checkout' : 'success');
                    
                    // Update UI counters
                    this.updateCounters();
                } else {
                    throw new Error(data.error || data.message || 'Attendance submission failed');
                }
            } catch (error) {
                DiagnosticMonitor.log('Submission error: ' + error.message, 'error');
                VisualFeedback.showFeedback('Error: ' + error.message, 'error');
                VisualFeedback.setStatus('Error', 'error');
                VisualFeedback.showFlash('error');
                AudioManager.play('error');
            } finally {
                // Refocus input
                if (this.input) this.input.focus();
            }
        },
        
        async updateCounters() {
            // Reload recent scans
            if (typeof loadRecentScans === 'function') {
                loadRecentScans();
            }
            
            // Reload scan stats
            if (typeof updateScanCount === 'function') {
                updateScanCount();
            }
        }
    };

    // ============================================================
    // Initialize on DOM Ready
    // ============================================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ScannerController.init());
    } else {
        ScannerController.init();
    }

})();

