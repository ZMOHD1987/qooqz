/**
 * /admin/assets/js/pages/AdminUiThemeLoader.js
 * Theme Management System - Production Ready
 * Version: 2.0.0
 */

class ThemeManager {
    constructor() {
        // Log initialization
        console.log('[ThemeManager] Initializing...');
        console.log('[ThemeManager] APP_CONFIG:', window.APP_CONFIG);

        // Configuration
        this.config = window.APP_CONFIG || {};
        this.apiBase = this.config.API_BASE || '/api';
        this.csrfToken = this.config.CSRF_TOKEN || '';
        this.permissions = this.config.PERMISSIONS || {};

        console.log('[ThemeManager] API Base:', this.apiBase);
        console.log('[ThemeManager] CSRF Token:', this.csrfToken ? 'Present' : 'Missing');
        console.log('[ThemeManager] Permissions:', this.permissions);

        // State Management
        this.state = {
            currentPage: 1,
            itemsPerPage: 10,
            totalItems: 0,
            totalPages: 0,
            selectedThemes: new Set(),
            isSelectAll: false,
            filters: {
                search: '',
                status: '',
                type: '',
                sort: 'created_at:desc',
                page: 1,
                limit: 10
            },
            currentThemeId: null,
            previewThemeId: null,
            themes: [],
            isLoading: false,
            formMode: 'add' // 'add' or 'edit'
        };

        // API Endpoints - Use from APP_CONFIG if available
        const endpoints = this.config.ENDPOINTS || {};
        this.endpoints = {
            themes: endpoints.themes || `${this.apiBase}/themes`,
            theme: (id) => `${endpoints.themes || this.apiBase + '/themes'}/${id}`,
            activate: (id) => `${endpoints.themes || this.apiBase + '/themes'}/${id}/activate`,
            deactivate: (id) => `${endpoints.themes || this.apiBase + '/themes'}/${id}/deactivate`,
            bulkActions: `${endpoints.themes || this.apiBase + '/themes'}/bulk`,
            export: `${endpoints.themes || this.apiBase + '/themes'}/export`,
            preview: (id) => `${endpoints.themes || this.apiBase + '/themes'}/${id}/preview`,
            validateSlug: `${endpoints.themes || this.apiBase + '/themes'}/validate-slug`,
            // Additional endpoints for theme components
            design_settings: endpoints.design_settings || `${this.apiBase}/design_settings`,
            color_settings: endpoints.color_settings || `${this.apiBase}/color_settings`,
            font_settings: endpoints.font_settings || `${this.apiBase}/font_settings`,
            button_styles: endpoints.button_styles || `${this.apiBase}/button_styles`,
            card_styles: endpoints.card_styles || `${this.apiBase}/card_styles`,
            system_settings: endpoints.system_settings || `${this.apiBase}/system_settings`
        };

        // Initialize
        this.init();
    }

    // ==================== INITIALIZATION ====================

    init() {
        this.cacheElements();
        this.bindEvents();
        this.setupColorPickers();
        this.loadThemes();
        this.setupKeyboardShortcuts();
        console.log('ThemeManager initialized successfully.');
    }

    cacheElements() {
        // Main containers
        this.elements = {
            app: document.getElementById('themeManagerApp'),
            loadingState: document.getElementById('loadingState'),
            emptyState: document.getElementById('emptyState'),
            errorState: document.getElementById('errorState'),
            tableContainer: document.getElementById('tableContainer'),
            tableBody: document.getElementById('tableBody'),
            tableFooter: document.getElementById('tableFooter'),
            pagination: document.getElementById('pagination'),
            paginationInfo: document.getElementById('paginationInfo'),
            errorMessage: document.getElementById('errorMessage'),

            // Bulk selection
            bulkSelectionInfo: document.getElementById('bulkSelectionInfo'),
            selectedCount: document.getElementById('selectedCount'),
            selectAllThemes: document.getElementById('selectAllThemes'),
            btnClearSelection: document.getElementById('btnClearSelection'),

            // Filters
            searchInput: document.getElementById('searchInput'),
            statusFilter: document.getElementById('statusFilter'),
            typeFilter: document.getElementById('typeFilter'),
            sortFilter: document.getElementById('sortFilter'),
            btnApplyFilters: document.getElementById('btnApplyFilters'),
            btnResetFilters: document.getElementById('btnResetFilters'),
            btnClearSearch: document.getElementById('btnClearSearch'),

            // Bulk actions
            btnBulkActivate: document.getElementById('btnBulkActivate'),
            btnBulkDeactivate: document.getElementById('btnBulkDeactivate'),
            btnBulkDelete: document.getElementById('btnBulkDelete'),

            // Main buttons
            btnAddTheme: document.getElementById('btnAddTheme'),
            btnAddFirstTheme: document.getElementById('btnAddFirstTheme'),
            btnExportAll: document.getElementById('btnExportAll'),
            btnRetry: document.getElementById('btnRetry'),
            btnReportError: document.getElementById('btnReportError'),

            // Modals - only initialize if elements exist
            themeModal: document.getElementById('themeModal') ?
                new bootstrap.Modal(document.getElementById('themeModal')) : null,
            deleteModal: document.getElementById('deleteModal') ?
                new bootstrap.Modal(document.getElementById('deleteModal')) : null,
            previewModal: document.getElementById('previewModal') ?
                new bootstrap.Modal(document.getElementById('previewModal')) : null,
            loadingOverlay: document.getElementById('loadingOverlay') ?
                new bootstrap.Modal(document.getElementById('loadingOverlay')) : null,

            // Form elements
            themeForm: document.getElementById('themeForm'),
            modalTitle: document.getElementById('modalTitle'),
            themeId: document.getElementById('themeId'),
            themeName: document.getElementById('themeName'),
            themeSlug: document.getElementById('themeSlug'),
            themeDescription: document.getElementById('themeDescription'),
            themeVersion: document.getElementById('themeVersion'),
            themeAuthor: document.getElementById('themeAuthor'),
            themeStatus: document.getElementById('themeStatus'),
            themeIsDefault: document.getElementById('themeIsDefault'),
            themeIsPublic: document.getElementById('themeIsPublic'),
            themeCustomCSS: document.getElementById('themeCustomCSS'),
            btnSaveTheme: document.getElementById('btnSaveTheme'),
            saveButtonText: document.getElementById('saveButtonText'),
            btnGenerateSlug: document.getElementById('btnGenerateSlug'),

            // Color pickers
            themePrimaryColor: document.getElementById('themePrimaryColor'),
            themeSecondaryColor: document.getElementById('themeSecondaryColor'),
            themeSuccessColor: document.getElementById('themeSuccessColor'),
            themeDangerColor: document.getElementById('themeDangerColor'),
            colorPreview: document.getElementById('colorPreview'),

            // Form tabs
            themeFormTabs: new bootstrap.Tab(document.querySelector('#themeFormTabs button')),

            // Delete modal
            deleteThemeName: document.getElementById('deleteThemeName'),
            btnConfirmDelete: document.getElementById('btnConfirmDelete'),

            // Preview modal
            previewFrame: document.getElementById('previewFrame'),
            btnActivatePreview: document.getElementById('btnActivatePreview'),

            // Loading overlay
            loadingMessage: document.getElementById('loadingMessage')
        };
    }

    bindEvents() {
        // Filter events
        this.elements.btnApplyFilters.addEventListener('click', () => this.applyFilters());
        this.elements.btnResetFilters.addEventListener('click', () => this.resetFilters());
        this.elements.btnClearSearch.addEventListener('click', () => {
            this.elements.searchInput.value = '';
            this.applyFilters();
        });
        this.elements.searchInput.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') this.applyFilters();
        });

        // Bulk selection
        this.elements.selectAllThemes.addEventListener('change', (e) => this.toggleSelectAll(e.target.checked));
        this.elements.btnClearSelection.addEventListener('click', () => this.clearSelection());

        // Bulk actions
        this.elements.btnBulkActivate?.addEventListener('click', () => this.bulkActivate());
        this.elements.btnBulkDeactivate?.addEventListener('click', () => this.bulkDeactivate());
        this.elements.btnBulkDelete?.addEventListener('click', () => this.bulkDelete());

        // Main buttons
        this.elements.btnAddTheme?.addEventListener('click', () => this.showAddForm());
        this.elements.btnAddFirstTheme?.addEventListener('click', () => this.showAddForm());
        this.elements.btnExportAll?.addEventListener('click', () => this.exportAll());
        this.elements.btnRetry.addEventListener('click', () => this.loadThemes());
        this.elements.btnReportError?.addEventListener('click', () => this.reportError());

        // Form events
        this.elements.themeForm.addEventListener('submit', (e) => this.saveTheme(e));
        this.elements.themeName.addEventListener('blur', () => this.generateSlug());
        this.elements.btnGenerateSlug.addEventListener('click', () => this.generateSlug());
        this.elements.themeSlug.addEventListener('blur', () => this.validateSlug());

        // Color preview updates
        ['themePrimaryColor', 'themeSecondaryColor', 'themeSuccessColor', 'themeDangerColor'].forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('change', () => this.updateColorPreview());
            }
        });

        // Delete confirmation
        this.elements.btnConfirmDelete.addEventListener('click', () => this.confirmDelete());

        // Preview activation
        this.elements.btnActivatePreview.addEventListener('click', () => this.activatePreviewTheme());

        // Modal events
        document.getElementById('themeModal').addEventListener('hidden.bs.modal', () => this.resetForm());
        document.getElementById('previewModal').addEventListener('hidden.bs.modal', () => {
            this.state.previewThemeId = null;
            this.elements.previewFrame.src = 'about:blank';
        });

        // Toast notifications
        window.addEventListener('theme-saved', (e) => {
            this.showSuccess(e.detail?.message || 'Theme saved successfully');
        });

        window.addEventListener('theme-deleted', (e) => {
            this.showSuccess(e.detail?.message || 'Theme deleted successfully');
        });

        window.addEventListener('theme-activated', (e) => {
            this.showSuccess(e.detail?.message || 'Theme activated successfully');
        });
    }

    setupColorPickers() {
        // Initialize color pickers
        $('.colorpicker').colorpicker({
            format: 'hex',
            color: '#3b82f6',
            useAlpha: false
        }).on('colorpickerChange', (e) => {
            this.updateColorPreview();
        });

        // Initial color preview
        this.updateColorPreview();
    }

    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + N: Add new theme
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                if (this.permissions.canCreate) {
                    this.showAddForm();
                }
            }

            // Escape: Close modal if open
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    const modalInstance = bootstrap.Modal.getInstance(openModal);
                    if (modalInstance) modalInstance.hide();
                }
            }

            // Ctrl/Cmd + F: Focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                this.elements.searchInput.focus();
            }
        });
    }

    // ==================== API METHODS ====================

    async loadThemes() {
        if (this.state.isLoading) {
            console.log('[ThemeManager] Already loading, skipping...');
            return;
        }

        try {
            console.log('[ThemeManager] Starting to load themes...');
            console.log('[ThemeManager] Config:', this.config);
            console.log('[ThemeManager] Endpoints:', this.endpoints);
            console.log('[ThemeManager] Current state:', this.state);

            this.showLoading();
            this.state.isLoading = true;

            // Build query parameters
            const params = new URLSearchParams({
                page: this.state.currentPage,
                limit: this.state.itemsPerPage,
                search: this.state.filters.search,
                status: this.state.filters.status,
                type: this.state.filters.type,
                sort: this.state.filters.sort
            });

            const url = `${this.endpoints.themes}?${params}`;
            console.log('[ThemeManager] Fetching from URL:', url);

            const response = await this.fetchWithAuth(url);
            console.log('[ThemeManager] Response status:', response.status);
            console.log('[ThemeManager] Response OK:', response.ok);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('[ThemeManager] Response data:', data);

            if (data.success === false) {
                throw new Error(data.message || 'Failed to load themes');
            }

            // Update state
            this.state.themes = data.data || [];
            this.state.totalItems = data.meta?.total || data.total || this.state.themes.length;
            this.state.totalPages = Math.ceil(this.state.totalItems / this.state.itemsPerPage);

            console.log('[ThemeManager] Loaded themes:', this.state.themes.length);
            console.log('[ThemeManager] Total items:', this.state.totalItems);

            // Update UI
            if (this.state.themes.length === 0) {
                console.log('[ThemeManager] No themes found, showing empty state');
                this.showEmptyState();
            } else {
                console.log('[ThemeManager] Rendering table with', this.state.themes.length, 'themes');
                this.renderTable();
                this.renderPagination();
                this.elements.tableContainer.style.display = 'block';
                this.elements.tableFooter.style.display = 'flex';
            }

        } catch (error) {
            console.error('[ThemeManager] Error loading themes:', error);
            console.error('[ThemeManager] Error stack:', error.stack);
            this.showError(error.message);
        } finally {
            this.hideLoading();
            this.state.isLoading = false;
            console.log('[ThemeManager] Load complete');
        }
    }

    async getTheme(id) {
        try {
            this.showLoadingOverlay(this.config.TRANSLATIONS.loading);

            const response = await this.fetchWithAuth(this.endpoints.theme(id));

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (data.success === false) {
                throw new Error(data.message || 'Failed to load theme');
            }

            return data.data || data;

        } catch (error) {
            console.error('Error getting theme:', error);
            this.showError(error.message);
            throw error;
        } finally {
            this.hideLoadingOverlay();
        }
    }

    async saveTheme(e) {
        e.preventDefault();

        if (!this.validateForm()) return;

        try {
            this.showLoadingOverlay(this.config.TRANSLATIONS.saving);

            // Prepare form data
            const formData = new FormData(this.elements.themeForm);
            const data = Object.fromEntries(formData);

            // Process checkbox values
            data.is_default = data.is_default === 'on' ? 1 : 0;
            data.is_public = data.is_public === 'on' ? 1 : 0;
            data.is_active = data.is_active || '1';

            // Determine method and URL
            const method = this.state.formMode === 'edit' ? 'PUT' : 'POST';
            const url = this.state.formMode === 'edit'
                ? this.endpoints.theme(this.state.currentThemeId)
                : this.endpoints.themes;

            const response = await this.fetchWithAuth(url, {
                method,
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!response.ok || result.success === false) {
                throw new Error(result.message || `HTTP ${response.status}`);
            }

            // Success
            this.hideForm();
            this.loadThemes();

            // Dispatch event for notifications
            window.dispatchEvent(new CustomEvent('theme-saved', {
                detail: {
                    message: 'Theme saved successfully',
                    theme: result.data
                }
            }));

        } catch (error) {
            console.error('Error saving theme:', error);
            this.showError(error.message);
        } finally {
            this.hideLoadingOverlay();
        }
    }

    async deleteTheme(id) {
        try {
            this.showLoadingOverlay(this.config.TRANSLATIONS.deleting);

            const response = await this.fetchWithAuth(this.endpoints.theme(id), {
                method: 'DELETE'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success === false) {
                throw new Error(result.message || 'Failed to delete theme');
            }

            // Update selection
            this.state.selectedThemes.delete(id.toString());
            this.updateSelectionUI();

            // Reload if needed
            if (this.state.themes.length === 1 && this.state.currentPage > 1) {
                this.state.currentPage--;
            }

            this.loadThemes();

            // Dispatch event
            window.dispatchEvent(new CustomEvent('theme-deleted', {
                detail: {
                    message: 'Theme deleted successfully',
                    themeId: id
                }
            }));

        } catch (error) {
            console.error('Error deleting theme:', error);
            this.showError(error.message);
        } finally {
            this.hideLoadingOverlay();
        }
    }

    async activateTheme(id) {
        try {
            this.showLoadingOverlay(this.config.TRANSLATIONS.processing);

            const response = await this.fetchWithAuth(this.endpoints.activate(id), {
                method: 'POST'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success === false) {
                throw new Error(result.message || 'Failed to activate theme');
            }

            this.loadThemes();

            // Ask to reload page
            setTimeout(() => {
                if (confirm('Theme activated. Reload page to apply changes?')) {
                    location.reload();
                }
            }, 1000);

            // Dispatch event
            window.dispatchEvent(new CustomEvent('theme-activated', {
                detail: {
                    message: 'Theme activated successfully',
                    themeId: id
                }
            }));

        } catch (error) {
            console.error('Error activating theme:', error);
            this.showError(error.message);
        } finally {
            this.hideLoadingOverlay();
        }
    }

    async deactivateTheme(id) {
        try {
            this.showLoadingOverlay(this.config.TRANSLATIONS.processing);

            const response = await this.fetchWithAuth(this.endpoints.deactivate(id), {
                method: 'POST'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success === false) {
                throw new Error(result.message || 'Failed to deactivate theme');
            }

            this.loadThemes();
            this.showSuccess('Theme deactivated successfully');

        } catch (error) {
            console.error('Error deactivating theme:', error);
            this.showError(error.message);
        } finally {
            this.hideLoadingOverlay();
        }
    }

    async bulkActivate() {
        const selected = Array.from(this.state.selectedThemes);
        if (selected.length === 0) {
            this.showWarning(this.config.TRANSLATIONS.no_themes_selected);
            return;
        }

        if (!confirm(`Activate ${selected.length} theme(s)?`)) return;

        try {
            this.showLoadingOverlay(this.config.TRANSLATIONS.processing);

            const response = await this.fetchWithAuth(this.endpoints.bulkActions, {
                method: 'POST',
                body: JSON.stringify({
                    action: 'activate',
                    ids: selected
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success === false) {
                throw new Error(result.message || 'Failed to activate themes');
            }

            this.clearSelection();
            this.loadThemes();

            this.showSuccess(this.config.TRANSLATIONS.themes_activated.replace('{count}', selected.length));

        } catch (error) {
            console.error('Error bulk activating themes:', error);
            this.showError(error.message);
        } finally {
            this.hideLoadingOverlay();
        }
    }

    async bulkDeactivate() {
        const selected = Array.from(this.state.selectedThemes);
        if (selected.length === 0) {
            this.showWarning(this.config.TRANSLATIONS.no_themes_selected);
            return;
        }

        if (!confirm(`Deactivate ${selected.length} theme(s)?`)) return;

        try {
            this.showLoadingOverlay(this.config.TRANSLATIONS.processing);

            const response = await this.fetchWithAuth(this.endpoints.bulkActions, {
                method: 'POST',
                body: JSON.stringify({
                    action: 'deactivate',
                    ids: selected
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success === false) {
                throw new Error(result.message || 'Failed to deactivate themes');
            }

            this.clearSelection();
            this.loadThemes();

            this.showSuccess(this.config.TRANSLATIONS.themes_deactivated.replace('{count}', selected.length));

        } catch (error) {
            console.error('Error bulk deactivating themes:', error);
            this.showError(error.message);
        } finally {
            this.hideLoadingOverlay();
        }
    }

    async bulkDelete() {
        const selected = Array.from(this.state.selectedThemes);
        if (selected.length === 0) {
            this.showWarning(this.config.TRANSLATIONS.no_themes_selected);
            return;
        }

        const message = this.config.TRANSLATIONS.confirm_delete_multiple.replace('{count}', selected.length);
        if (!confirm(message)) return;

        try {
            this.showLoadingOverlay(this.config.TRANSLATIONS.deleting);

            const response = await this.fetchWithAuth(this.endpoints.bulkActions, {
                method: 'POST',
                body: JSON.stringify({
                    action: 'delete',
                    ids: selected
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success === false) {
                throw new Error(result.message || 'Failed to delete themes');
            }

            this.clearSelection();

            // Reload if needed
            if (this.state.themes.length <= selected.length && this.state.currentPage > 1) {
                this.state.currentPage--;
            }

            this.loadThemes();

            this.showSuccess(this.config.TRANSLATIONS.themes_deleted.replace('{count}', selected.length));

        } catch (error) {
            console.error('Error bulk deleting themes:', error);
            this.showError(error.message);
        } finally {
            this.hideLoadingOverlay();
        }
    }

    async exportAll() {
        try {
            this.showLoadingOverlay('Preparing export...');

            const response = await this.fetchWithAuth(this.endpoints.export);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            // Create download link
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `themes-export-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            this.showSuccess('Themes exported successfully');

        } catch (error) {
            console.error('Error exporting themes:', error);
            this.showError(error.message);
        } finally {
            this.hideLoadingOverlay();
        }
    }

    async validateSlug() {
        const slug = this.elements.themeSlug.value.trim();
        const currentId = this.state.currentThemeId;

        if (!slug) return true;

        try {
            const response = await this.fetchWithAuth(this.endpoints.validateSlug, {
                method: 'POST',
                body: JSON.stringify({ slug, theme_id: currentId })
            });

            const data = await response.json();

            if (data.valid === false) {
                this.elements.themeSlug.classList.add('is-invalid');
                this.elements.themeSlug.nextElementSibling?.remove();

                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = data.message || 'This slug is already in use';
                this.elements.themeSlug.parentNode.appendChild(errorDiv);

                return false;
            }

            this.elements.themeSlug.classList.remove('is-invalid');
            return true;

        } catch (error) {
            console.error('Error validating slug:', error);
            return true; // Don't block form submission on validation error
        }
    }

    // ==================== UI METHODS ====================

    renderTable() {
        if (!this.elements.tableBody) return;

        let html = '';

        this.state.themes.forEach(theme => {
            const isActive = theme.is_active == 1;
            const isDefault = theme.is_default == 1;
            const isPublic = theme.is_public == 1;
            const isSelected = this.state.selectedThemes.has(theme.id.toString());
            const createdAt = theme.created_at ? this.formatDate(theme.created_at) : 'N/A';

            html += `
            <tr class="${isSelected ? 'table-primary' : ''} ${isActive ? 'active' : ''} ${isDefault ? 'default' : ''}">
                <td class="ps-3">
                    <div class="form-check">
                        <input class="form-check-input theme-checkbox" type="checkbox" 
                               value="${theme.id}" ${isSelected ? 'checked' : ''}
                               onchange="themeManager.toggleThemeSelection(${theme.id}, this.checked)">
                    </div>
                </td>
                <td class="fw-semibold">${theme.id}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="color-preview-circle" style="background-color: ${theme.primary_color || '#3b82f6'}"></div>
                        <div>
                            <div class="fw-medium">
                                ${this.escapeHtml(theme.name || 'Unnamed')}
                                ${isDefault ? '<span class="badge bg-info ms-2">Default</span>' : ''}
                                ${isPublic ? '<span class="badge bg-success ms-2">Public</span>' : ''}
                            </div>
                            <small class="text-muted text-truncate-2 d-block" style="max-width: 200px;">
                                ${this.escapeHtml(theme.description || '')}
                            </small>
                        </div>
                    </div>
                </td>
                <td>
                    <small class="text-muted">${this.escapeHtml(theme.author || 'Unknown')}</small>
                </td>
                <td>
                    <span class="badge bg-secondary">${this.escapeHtml(theme.version || '1.0.0')}</span>
                </td>
                <td>
                    <span class="badge ${isActive ? 'bg-success' : 'bg-secondary'}">
                        ${isActive ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td>
                    <div class="d-flex gap-1">
                        <div class="color-preview-circle" style="background-color: ${theme.primary_color || '#3b82f6'}" 
                             title="Primary: ${theme.primary_color || '#3b82f6'}"></div>
                        <div class="color-preview-circle" style="background-color: ${theme.secondary_color || '#64748b'}" 
                             title="Secondary: ${theme.secondary_color || '#64748b'}"></div>
                    </div>
                </td>
                <td>
                    <small class="text-muted">${createdAt}</small>
                </td>
                <td class="text-end pe-3">
                    <div class="btn-group btn-group-sm">
                        ${this.permissions.canEdit ? `
                        <button type="button" class="btn btn-outline-primary" 
                                onclick="themeManager.editTheme(${theme.id})" 
                                title="Edit theme">
                            <i class="fas fa-edit"></i>
                        </button>
                        ` : ''}
                        
                        ${this.permissions.canActivate ? (isActive ? `
                        <button type="button" class="btn btn-outline-warning" 
                                onclick="themeManager.deactivateTheme(${theme.id})" 
                                title="Deactivate theme">
                            <i class="fas fa-toggle-off"></i>
                        </button>
                        ` : `
                        <button type="button" class="btn btn-outline-success" 
                                onclick="themeManager.activateTheme(${theme.id})" 
                                title="Activate theme">
                            <i class="fas fa-toggle-on"></i>
                        </button>
                        `) : ''}
                        
                        <button type="button" class="btn btn-outline-info" 
                                onclick="themeManager.previewTheme(${theme.id})" 
                                title="Preview theme">
                            <i class="fas fa-eye"></i>
                        </button>
                        
                        ${!isDefault && this.permissions.canDelete ? `
                        <button type="button" class="btn btn-outline-danger" 
                                onclick="themeManager.showDeleteModal(${theme.id}, '${this.escapeHtml(theme.name)}')" 
                                title="Delete theme">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
            `;
        });

        this.elements.tableBody.innerHTML = html;
    }

    renderPagination() {
        if (this.state.totalPages <= 1) {
            this.elements.tableFooter.style.display = 'none';
            return;
        }

        this.elements.tableFooter.style.display = 'flex';

        // Update info text
        const start = (this.state.currentPage - 1) * this.state.itemsPerPage + 1;
        const end = Math.min(this.state.currentPage * this.state.itemsPerPage, this.state.totalItems);
        this.elements.paginationInfo.textContent = `Showing ${start}-${end} of ${this.state.totalItems} themes`;

        // Build pagination
        let html = '';

        // Previous button
        const prevDisabled = this.state.currentPage === 1 ? 'disabled' : '';
        html += `
        <li class="page-item ${prevDisabled}">
            <a class="page-link" href="#" onclick="themeManager.goToPage(${this.state.currentPage - 1}); return false;" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
        `;

        // Page numbers
        const maxVisible = 5;
        let startPage = Math.max(1, this.state.currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(this.state.totalPages, startPage + maxVisible - 1);

        if (endPage - startPage + 1 < maxVisible) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="themeManager.goToPage(1); return false;">1</a></li>`;
            if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            const active = i === this.state.currentPage ? 'active' : '';
            html += `
            <li class="page-item ${active}">
                <a class="page-link" href="#" onclick="themeManager.goToPage(${i}); return false;">
                    ${i}
                </a>
            </li>
            `;
        }

        if (endPage < this.state.totalPages) {
            if (endPage < this.state.totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            html += `<li class="page-item"><a class="page-link" href="#" onclick="themeManager.goToPage(${this.state.totalPages}); return false;">${this.state.totalPages}</a></li>`;
        }

        // Next button
        const nextDisabled = this.state.currentPage === this.state.totalPages ? 'disabled' : '';
        html += `
        <li class="page-item ${nextDisabled}">
            <a class="page-link" href="#" onclick="themeManager.goToPage(${this.state.currentPage + 1}); return false;" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
        `;

        this.elements.pagination.innerHTML = html;
    }

    goToPage(page) {
        if (page < 1 || page > this.state.totalPages || page === this.state.currentPage) return;

        this.state.currentPage = page;
        this.state.filters.page = page;
        this.loadThemes();

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ==================== FORM METHODS ====================

    showAddForm() {
        this.state.formMode = 'add';
        this.state.currentThemeId = null;

        this.elements.modalTitle.innerHTML = '<i class="fas fa-plus me-2"></i> Add New Theme';
        this.elements.saveButtonText.textContent = 'Save Theme';

        // Reset form
        this.elements.themeForm.reset();
        this.elements.themeId.value = '';
        this.elements.themeStatus.value = '1';
        this.elements.themeIsDefault.checked = false;
        this.elements.themeIsPublic.checked = true;
        this.elements.themeCustomCSS.value = '';

        // Reset color pickers
        $('#themePrimaryColor').colorpicker('setValue', '#3b82f6');
        $('#themeSecondaryColor').colorpicker('setValue', '#64748b');
        $('#themeSuccessColor').colorpicker('setValue', '#10b981');
        $('#themeDangerColor').colorpicker('setValue', '#ef4444');

        // Show first tab
        const firstTab = document.querySelector('#themeFormTabs button');
        if (firstTab) {
            const tab = new bootstrap.Tab(firstTab);
            tab.show();
        }

        this.elements.themeModal.show();
        this.updateColorPreview();
    }

    async editTheme(id) {
        try {
            const theme = await this.getTheme(id);

            this.state.formMode = 'edit';
            this.state.currentThemeId = id;

            this.elements.modalTitle.innerHTML = '<i class="fas fa-edit me-2"></i> Edit Theme';
            this.elements.saveButtonText.textContent = 'Update Theme';

            // Populate form
            this.elements.themeId.value = theme.id;
            this.elements.themeName.value = theme.name || '';
            this.elements.themeSlug.value = theme.slug || '';
            this.elements.themeDescription.value = theme.description || '';
            this.elements.themeVersion.value = theme.version || '1.0.0';
            this.elements.themeAuthor.value = theme.author || '';
            this.elements.themeStatus.value = theme.is_active ? '1' : '0';
            this.elements.themeIsDefault.checked = theme.is_default == 1;
            this.elements.themeIsPublic.checked = theme.is_public == 1;
            this.elements.themeCustomCSS.value = theme.custom_css || '';

            // Set color pickers
            $('#themePrimaryColor').colorpicker('setValue', theme.primary_color || '#3b82f6');
            $('#themeSecondaryColor').colorpicker('setValue', theme.secondary_color || '#64748b');
            $('#themeSuccessColor').colorpicker('setValue', theme.success_color || '#10b981');
            $('#themeDangerColor').colorpicker('setValue', theme.danger_color || '#ef4444');

            this.elements.themeModal.show();
            this.updateColorPreview();

        } catch (error) {
            console.error('Error loading theme for edit:', error);
            // Error already shown by getTheme()
        }
    }

    generateSlug() {
        const name = this.elements.themeName.value.trim();
        const slugField = this.elements.themeSlug;

        if (name && (!slugField.value || this.state.formMode === 'add')) {
            const slug = name
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');

            slugField.value = slug;

            // Validate slug
            this.validateSlug();
        }
    }

    validateForm() {
        const form = this.elements.themeForm;

        // Clear previous validation
        form.classList.remove('was-validated');
        const invalidFields = form.querySelectorAll('.is-invalid');
        invalidFields.forEach(field => field.classList.remove('is-invalid'));

        // Check required fields
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            }
        });

        // Validate version format
        const version = this.elements.themeVersion.value.trim();
        if (version && !/^\d+\.\d+\.\d+$/.test(version)) {
            this.elements.themeVersion.classList.add('is-invalid');
            isValid = false;
        }

        // Validate slug format
        const slug = this.elements.themeSlug.value.trim();
        if (slug && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) {
            this.elements.themeSlug.classList.add('is-invalid');
            isValid = false;
        }

        if (!isValid) {
            form.classList.add('was-validated');
            // Show first invalid field
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) firstInvalid.focus();
            return false;
        }

        return true;
    }

    resetForm() {
        this.elements.themeForm.reset();
        this.elements.themeForm.classList.remove('was-validated');
        this.state.currentThemeId = null;
        this.state.formMode = 'add';
    }

    updateColorPreview() {
        const colors = {
            primary: this.elements.themePrimaryColor.value || '#3b82f6',
            secondary: this.elements.themeSecondaryColor.value || '#64748b',
            success: this.elements.themeSuccessColor.value || '#10b981',
            danger: this.elements.themeDangerColor.value || '#ef4444'
        };

        let html = '';

        Object.entries(colors).forEach(([name, color]) => {
            html += `
            <div class="text-center">
                <div class="color-preview-circle mb-1" style="background-color: ${color}; width: 40px; height: 40px;"></div>
                <small class="text-muted d-block">${name.charAt(0).toUpperCase() + name.slice(1)}</small>
                <small class="text-muted d-block">${color}</small>
            </div>
            `;
        });

        this.elements.colorPreview.innerHTML = html;
    }

    // ==================== SELECTION METHODS ====================

    toggleThemeSelection(themeId, isSelected) {
        const idStr = themeId.toString();

        if (isSelected) {
            this.state.selectedThemes.add(idStr);
        } else {
            this.state.selectedThemes.delete(idStr);
            this.state.isSelectAll = false;
            this.elements.selectAllThemes.checked = false;
            this.elements.selectAllThemes.indeterminate = false;
        }

        this.updateSelectionUI();
    }

    toggleSelectAll(isSelected) {
        if (isSelected) {
            this.state.themes.forEach(theme => {
                this.state.selectedThemes.add(theme.id.toString());
            });
            this.state.isSelectAll = true;
        } else {
            this.state.selectedThemes.clear();
            this.state.isSelectAll = false;
        }

        // Update checkboxes
        const checkboxes = document.querySelectorAll('.theme-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = isSelected;
        });

        this.updateSelectionUI();
    }

    clearSelection() {
        this.state.selectedThemes.clear();
        this.state.isSelectAll = false;

        // Update UI
        this.elements.selectAllThemes.checked = false;
        this.elements.selectAllThemes.indeterminate = false;

        const checkboxes = document.querySelectorAll('.theme-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });

        this.updateSelectionUI();
    }

    updateSelectionUI() {
        const count = this.state.selectedThemes.size;

        if (count > 0) {
            this.elements.bulkSelectionInfo.style.display = 'block';
            this.elements.selectedCount.textContent = count;

            // Update select all checkbox state
            if (count === this.state.themes.length) {
                this.elements.selectAllThemes.checked = true;
                this.elements.selectAllThemes.indeterminate = false;
            } else if (count > 0) {
                this.elements.selectAllThemes.checked = false;
                this.elements.selectAllThemes.indeterminate = true;
            } else {
                this.elements.selectAllThemes.checked = false;
                this.elements.selectAllThemes.indeterminate = false;
            }
        } else {
            this.elements.bulkSelectionInfo.style.display = 'none';
            this.elements.selectAllThemes.checked = false;
            this.elements.selectAllThemes.indeterminate = false;
        }
    }

    // ==================== FILTER METHODS ====================

    applyFilters() {
        this.state.filters = {
            search: this.elements.searchInput.value.trim(),
            status: this.elements.statusFilter.value,
            type: this.elements.typeFilter.value,
            sort: this.elements.sortFilter.value,
            page: 1,
            limit: this.state.itemsPerPage
        };

        this.state.currentPage = 1;
        this.clearSelection();
        this.loadThemes();
    }

    resetFilters() {
        this.elements.searchInput.value = '';
        this.elements.statusFilter.value = '';
        this.elements.typeFilter.value = '';
        this.elements.sortFilter.value = 'created_at:desc';

        this.state.filters = {
            search: '',
            status: '',
            type: '',
            sort: 'created_at:desc',
            page: 1,
            limit: this.state.itemsPerPage
        };

        this.state.currentPage = 1;
        this.clearSelection();
        this.loadThemes();
    }

    // ==================== MODAL METHODS ====================

    showDeleteModal(id, name) {
        this.state.currentThemeId = id;
        this.elements.deleteThemeName.textContent = name;
        this.elements.deleteModal.show();
    }

    confirmDelete() {
        if (this.state.currentThemeId) {
            this.elements.deleteModal.hide();
            this.deleteTheme(this.state.currentThemeId);
        }
    }

    async previewTheme(id) {
        try {
            this.showLoadingOverlay('Loading preview...');

            const theme = await this.getTheme(id);
            this.state.previewThemeId = id;

            // Create preview HTML
            const previewHtml = this.createPreviewHtml(theme);
            const blob = new Blob([previewHtml], { type: 'text/html' });
            const url = URL.createObjectURL(blob);

            this.elements.previewFrame.src = url;
            this.elements.previewModal.show();

        } catch (error) {
            console.error('Error previewing theme:', error);
            this.showError(error.message);
        } finally {
            this.hideLoadingOverlay();
        }
    }

    activatePreviewTheme() {
        if (this.state.previewThemeId && this.permissions.canActivate) {
            this.elements.previewModal.hide();
            this.activateTheme(this.state.previewThemeId);
        }
    }

    hideForm() {
        this.elements.themeModal.hide();
    }

    showLoadingOverlay(message = 'Processing...') {
        this.elements.loadingMessage.textContent = message;
        this.elements.loadingOverlay.show();
    }

    hideLoadingOverlay() {
        this.elements.loadingOverlay.hide();
    }

    // ==================== UTILITY METHODS ====================

    createPreviewHtml(theme) {
        const colors = {
            primary: theme.primary_color || '#3b82f6',
            secondary: theme.secondary_color || '#64748b',
            success: theme.success_color || '#10b981',
            danger: theme.danger_color || '#ef4444',
            warning: '#f59e0b',
            info: '#0ea5e9',
            dark: '#1e293b',
            light: '#f8fafc'
        };

        return `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>${this.escapeHtml(theme.name)} Preview</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                    background: ${colors.light};
                    color: ${colors.dark};
                    line-height: 1.5;
                    padding: 2rem;
                }
                .preview-container { max-width: 1000px; margin: 0 auto; }
                
                /* Header */
                .preview-header {
                    background: white;
                    padding: 2rem;
                    border-radius: 1rem;
                    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
                    margin-bottom: 2rem;
                    border-left: 4px solid ${colors.primary};
                }
                .theme-name { 
                    color: ${colors.primary};
                    font-size: 2rem;
                    font-weight: 800;
                    margin-bottom: 0.5rem;
                }
                .theme-meta { color: ${colors.secondary}; margin-bottom: 1rem; }
                .theme-description { color: #64748b; }
                
                /* Color Palette */
                .color-palette {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 1rem;
                    margin-bottom: 2rem;
                }
                .color-card {
                    background: white;
                    padding: 1.5rem;
                    border-radius: 0.75rem;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                    text-align: center;
                }
                .color-box {
                    width: 80px;
                    height: 80px;
                    border-radius: 12px;
                    margin: 0 auto 1rem;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                }
                .color-name { font-weight: 600; margin-bottom: 0.25rem; }
                .color-value { color: ${colors.secondary}; font-family: monospace; }
                
                /* Components */
                .components-section { margin-bottom: 2rem; }
                .section-title {
                    color: ${colors.dark};
                    font-size: 1.25rem;
                    font-weight: 600;
                    margin-bottom: 1rem;
                    padding-bottom: 0.5rem;
                    border-bottom: 2px solid ${colors.light};
                }
                .components-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                    gap: 1.5rem;
                }
                .component-card {
                    background: white;
                    padding: 1.5rem;
                    border-radius: 0.75rem;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                }
                
                /* Buttons */
                .btn {
                    display: inline-block;
                    padding: 0.625rem 1.25rem;
                    border-radius: 0.5rem;
                    font-weight: 500;
                    text-decoration: none;
                    border: none;
                    cursor: pointer;
                    font-size: 0.875rem;
                    margin: 0.25rem;
                    transition: all 0.2s ease;
                }
                .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
                .btn-primary { background: ${colors.primary}; color: white; }
                .btn-secondary { background: ${colors.secondary}; color: white; }
                .btn-success { background: ${colors.success}; color: white; }
                .btn-danger { background: ${colors.danger}; color: white; }
                .btn-warning { background: ${colors.warning}; color: white; }
                .btn-info { background: ${colors.info}; color: white; }
                .btn-outline-primary { 
                    background: transparent; 
                    color: ${colors.primary}; 
                    border: 2px solid ${colors.primary}; 
                }
                
                /* Alerts */
                .alert {
                    padding: 1rem 1.25rem;
                    border-radius: 0.5rem;
                    margin-bottom: 1rem;
                    border-left: 4px solid;
                }
                .alert-primary { background: rgba(59, 130, 246, 0.1); border-color: ${colors.primary}; color: ${colors.primary}; }
                .alert-success { background: rgba(16, 185, 129, 0.1); border-color: ${colors.success}; color: ${colors.success}; }
                .alert-danger { background: rgba(239, 68, 68, 0.1); border-color: ${colors.danger}; color: ${colors.danger}; }
                .alert-warning { background: rgba(245, 158, 11, 0.1); border-color: ${colors.warning}; color: ${colors.warning}; }
                
                /* Cards */
                .card {
                    background: white;
                    border-radius: 0.75rem;
                    overflow: hidden;
                    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
                    margin-bottom: 1rem;
                }
                .card-header {
                    background: ${colors.light};
                    padding: 1rem 1.5rem;
                    border-bottom: 1px solid #e2e8f0;
                    font-weight: 600;
                }
                .card-body { padding: 1.5rem; }
                .card-footer {
                    background: ${colors.light};
                    padding: 1rem 1.5rem;
                    border-top: 1px solid #e2e8f0;
                }
                
                /* Badges */
                .badge {
                    display: inline-block;
                    padding: 0.25rem 0.5rem;
                    border-radius: 0.25rem;
                    font-size: 0.75rem;
                    font-weight: 600;
                    margin: 0.125rem;
                }
                .badge-primary { background: ${colors.primary}; color: white; }
                .badge-secondary { background: ${colors.secondary}; color: white; }
                .badge-success { background: ${colors.success}; color: white; }
                .badge-danger { background: ${colors.danger}; color: white; }
                
                /* Custom CSS */
                ${theme.custom_css || ''}
            </style>
        </head>
        <body>
            <div class="preview-container">
                <!-- Header -->
                <div class="preview-header">
                    <h1 class="theme-name">${this.escapeHtml(theme.name)}</h1>
                    <div class="theme-meta">
                        ${theme.version ? `Version ${theme.version}` : ''}
                        ${theme.author ? ` • By ${this.escapeHtml(theme.author)}` : ''}
                    </div>
                    ${theme.description ? `<p class="theme-description">${this.escapeHtml(theme.description)}</p>` : ''}
                </div>
                
                <!-- Color Palette -->
                <h2 class="section-title">Color Palette</h2>
                <div class="color-palette">
                    ${Object.entries(colors).map(([name, color]) => `
                    <div class="color-card">
                        <div class="color-box" style="background: ${color}"></div>
                        <div class="color-name">${name.charAt(0).toUpperCase() + name.slice(1)}</div>
                        <div class="color-value">${color}</div>
                    </div>
                    `).join('')}
                </div>
                
                <!-- Components -->
                <h2 class="section-title">UI Components</h2>
                
                <!-- Buttons -->
                <div class="components-section">
                    <h3>Buttons</h3>
                    <div class="component-card">
                        <button class="btn btn-primary">Primary</button>
                        <button class="btn btn-secondary">Secondary</button>
                        <button class="btn btn-success">Success</button>
                        <button class="btn btn-danger">Danger</button>
                        <button class="btn btn-warning">Warning</button>
                        <button class="btn btn-info">Info</button>
                        <button class="btn btn-outline-primary">Outline</button>
                    </div>
                </div>
                
                <!-- Alerts -->
                <div class="components-section">
                    <h3>Alerts</h3>
                    <div class="component-card">
                        <div class="alert alert-primary">This is a primary alert</div>
                        <div class="alert alert-success">This is a success alert</div>
                        <div class="alert alert-danger">This is a danger alert</div>
                        <div class="alert alert-warning">This is a warning alert</div>
                    </div>
                </div>
                
                <!-- Badges -->
                <div class="components-section">
                    <h3>Badges</h3>
                    <div class="component-card">
                        <span class="badge badge-primary">Primary</span>
                        <span class="badge badge-secondary">Secondary</span>
                        <span class="badge badge-success">Success</span>
                        <span class="badge badge-danger">Danger</span>
                    </div>
                </div>
                
                <!-- Card -->
                <div class="components-section">
                    <h3>Cards</h3>
                    <div class="card">
                        <div class="card-header">Card Header</div>
                        <div class="card-body">
                            <h4>Card Title</h4>
                            <p>This is a sample card showing how your theme will look with content.</p>
                            <button class="btn btn-primary">Action Button</button>
                        </div>
                        <div class="card-footer">Card Footer</div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div style="text-align: center; margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e2e8f0; color: #64748b;">
                    <p>This is a preview of the "${this.escapeHtml(theme.name)}" theme.</p>
                    <p><small>Preview generated on ${new Date().toLocaleDateString()}</small></p>
                </div>
            </div>
        </body>
        </html>
        `;
    }

    // ==================== HELPER METHODS ====================

    async fetchWithAuth(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': this.csrfToken
            },
            credentials: 'same-origin'
        };

        const response = await fetch(url, {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        });

        return response;
    }

    showLoading() {
        this.elements.loadingState.style.display = 'block';
        this.elements.emptyState.style.display = 'none';
        this.elements.errorState.style.display = 'none';
        this.elements.tableContainer.style.display = 'none';
        this.elements.tableFooter.style.display = 'none';
    }

    hideLoading() {
        this.elements.loadingState.style.display = 'none';
    }

    showEmptyState() {
        this.elements.emptyState.style.display = 'block';
        this.elements.loadingState.style.display = 'none';
        this.elements.errorState.style.display = 'none';
        this.elements.tableContainer.style.display = 'none';
        this.elements.tableFooter.style.display = 'none';
    }

    showError(message) {
        this.elements.errorMessage.textContent = message;
        this.elements.errorState.style.display = 'block';
        this.elements.loadingState.style.display = 'none';
        this.elements.emptyState.style.display = 'none';
        this.elements.tableContainer.style.display = 'none';
        this.elements.tableFooter.style.display = 'none';
    }

    showSuccess(message) {
        toastr.success(message);
    }

    showError(message) {
        toastr.error(message);
    }

    showWarning(message) {
        toastr.warning(message);
    }

    showInfo(message) {
        toastr.info(message);
    }

    reportError() {
        const error = this.elements.errorMessage.textContent;
        const url = `/admin/support/report?type=theme_manager&error=${encodeURIComponent(error)}`;
        window.open(url, '_blank');
    }

    formatDate(dateString) {
        try {
            const date = new Date(dateString);
            const now = new Date();
            const diffMs = now - date;
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

            if (diffDays === 0) {
                return 'Today';
            } else if (diffDays === 1) {
                return 'Yesterday';
            } else if (diffDays < 7) {
                return `${diffDays} days ago`;
            } else if (diffDays < 30) {
                const weeks = Math.floor(diffDays / 7);
                return `${weeks} week${weeks > 1 ? 's' : ''} ago`;
            } else {
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }
        } catch (e) {
            return dateString;
        }
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    truncateText(text, maxLength = 100) {
        if (!text || text.length <= maxLength) return text;
        return text.substring(0, maxLength) + '...';
    }
}

// ==================== INITIALIZATION ====================

let themeManager;

document.addEventListener('DOMContentLoaded', () => {
    try {
        themeManager = new ThemeManager();
        window.themeManager = themeManager;

        // Global error handler
        window.addEventListener('error', (e) => {
            console.error('Global error:', e.error);
            toastr.error('An unexpected error occurred. Please refresh the page.');
        });

        // Handle unhandled promise rejections
        window.addEventListener('unhandledrejection', (e) => {
            console.error('Unhandled promise rejection:', e.reason);
            toastr.error('An unexpected error occurred. Please try again.');
        });

        console.log('ThemeManager initialized successfully');

    } catch (error) {
        console.error('Failed to initialize ThemeManager:', error);
        document.body.innerHTML = `
            <div class="container py-5">
                <div class="alert alert-danger">
                    <h4>Failed to load Theme Manager</h4>
                    <p>An error occurred while initializing the theme management system.</p>
                    <pre class="mb-0">${error.message}</pre>
                </div>
                <button class="btn btn-primary" onclick="location.reload()">Reload Page</button>
            </div>
        `;
    }
});