/**
 * Filter Presets
 * Save and load commonly used filter combinations
 */

const FilterPresets = {
    storageKey: 'qr_attendance_filter_presets',
    currentPage: '',

    /**
     * Initialize filter presets
     */
    init: function(pageName) {
        this.currentPage = pageName;
        this.addPresetUI();
        this.loadPresetButtons();
    },

    /**
     * Add preset UI to filter panel
     */
    addPresetUI: function() {
        const filterPanel = document.getElementById('filter-panel');
        if (!filterPanel) return;

        const presetRow = document.createElement('div');
        presetRow.className = 'row mt-3 pt-3 border-top';
        presetRow.innerHTML = `
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">
                        <i class="bx bx-save me-1"></i>Filter Presets
                    </h6>
                    <button class="btn btn-sm btn-primary" onclick="FilterPresets.savePreset()">
                        <i class="bx bx-plus me-1"></i>Save Current Filters
                    </button>
                </div>
                <div id="filterPresetsContainer" class="d-flex flex-wrap gap-2">
                    <!-- Preset buttons will be loaded here -->
                </div>
            </div>
        `;

        filterPanel.appendChild(presetRow);
    },

    /**
     * Get current filter values
     */
    getCurrentFilters: function() {
        const filters = {};
        const filterInputs = document.querySelectorAll('#filter-panel select, #filter-panel input');
        
        filterInputs.forEach(input => {
            if (input.value && input.id) {
                filters[input.id] = input.value;
            }
        });

        return filters;
    },

    /**
     * Save current filters as preset
     */
    savePreset: function() {
        const filters = this.getCurrentFilters();
        
        if (Object.keys(filters).length === 0) {
            UIHelpers.showWarning('No filters to save');
            return;
        }

        UIHelpers.showConfirmDialog({
            title: 'Save Filter Preset',
            message: `
                <div class="mb-3">
                    <label class="form-label">Preset Name</label>
                    <input type="text" id="presetNameInput" class="form-control" 
                           placeholder="e.g., Active Morning Students">
                </div>
            `,
            confirmText: 'Save',
            confirmClass: 'btn-primary',
            onConfirm: () => {
                const name = document.getElementById('presetNameInput').value.trim();
                
                if (!name) {
                    UIHelpers.showError('Please enter a preset name');
                    return;
                }

                const presets = this.getPresets();
                presets.push({
                    id: Date.now(),
                    name: name,
                    page: this.currentPage,
                    filters: filters,
                    created: new Date().toISOString()
                });

                this.savePresets(presets);
                this.loadPresetButtons();
                UIHelpers.showSuccess('Filter preset saved!');
            }
        });

        // Focus input after modal is shown
        setTimeout(() => {
            const input = document.getElementById('presetNameInput');
            if (input) input.focus();
        }, 500);
    },

    /**
     * Apply preset
     */
    applyPreset: function(presetId) {
        const presets = this.getPresets();
        const preset = presets.find(p => p.id === presetId);

        if (!preset) return;

        // Apply filters
        Object.keys(preset.filters).forEach(filterId => {
            const input = document.getElementById(filterId);
            if (input) {
                input.value = preset.filters[filterId];
            }
        });

        // Trigger filter application
        if (typeof applyFilters === 'function') {
            applyFilters();
        }

        UIHelpers.showSuccess(`Applied preset: ${preset.name}`);
    },

    /**
     * Delete preset
     */
    deletePreset: function(presetId) {
        UIHelpers.showConfirmDialog({
            title: 'Delete Preset',
            message: 'Are you sure you want to delete this filter preset?',
            confirmText: 'Delete',
            confirmClass: 'btn-danger',
            onConfirm: () => {
                const presets = this.getPresets();
                const filtered = presets.filter(p => p.id !== presetId);
                this.savePresets(filtered);
                this.loadPresetButtons();
                UIHelpers.showSuccess('Preset deleted');
            }
        });
    },

    /**
     * Load preset buttons
     */
    loadPresetButtons: function() {
        const container = document.getElementById('filterPresetsContainer');
        if (!container) return;

        const presets = this.getPresets().filter(p => p.page === this.currentPage);

        if (presets.length === 0) {
            container.innerHTML = '<p class="text-muted small mb-0">No saved presets. Save your current filters to create one.</p>';
            return;
        }

        container.innerHTML = presets.map(preset => `
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary" 
                        onclick="FilterPresets.applyPreset(${preset.id})">
                    <i class="bx bx-filter-alt me-1"></i>${preset.name}
                </button>
                <button type="button" class="btn btn-outline-danger" 
                        onclick="FilterPresets.deletePreset(${preset.id})">
                    <i class="bx bx-x"></i>
                </button>
            </div>
        `).join('');
    },

    /**
     * Get presets from localStorage
     */
    getPresets: function() {
        try {
            const data = localStorage.getItem(this.storageKey);
            return data ? JSON.parse(data) : [];
        } catch (e) {
            return [];
        }
    },

    /**
     * Save presets to localStorage
     */
    savePresets: function(presets) {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(presets));
        } catch (e) {
            console.error('Failed to save presets:', e);
        }
    }
};
