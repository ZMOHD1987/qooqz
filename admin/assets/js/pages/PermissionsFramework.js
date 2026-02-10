/**
 * Permissions Management
 * Fixed version
 */
(function() {
    'use strict';

    const AF = window.AdminFramework;
    const API = '/api/resource_permissions';

    let el = {};
    let permissions = [];
    let changes = new Map();

    // ════════════════════════════════════════════════════════════
    // RENDER FUNCTIONS
    // ════════════════════════════════════════════════════════════
    
    function renderMatrix(data) {
        console.log('[Permissions] renderMatrix called with:', data);

        if (!data || !Array.isArray(data) || data.length === 0) {
            console.log('[Permissions] No data to render');
            showEmpty();
            return;
        }

        permissions = data;

        const rows = [];

        data.forEach(perm => {
            const id = perm.id;
            
            const row = `
                <tr data-perm-id="${id}">
                    <td class="sticky-col">
                        <div class="permission-row-header">
                            <span class="permission-name">${AF.escapeHtml(perm.key_name)}</span>
                            <span class="resource-type">${AF.escapeHtml(perm.resource_type)}</span>
                        </div>
                    </td>
                    <td>
                        <input type="checkbox" 
                               class="permission-checkbox" 
                               data-perm-id="${id}"
                               data-field="can_view_all"
                               ${perm.can_view_all ? 'checked' : ''}>
                    </td>
                    <td>
                        <input type="checkbox" 
                               class="permission-checkbox" 
                               data-perm-id="${id}"
                               data-field="can_view_own"
                               ${perm.can_view_own ? 'checked' : ''}>
                    </td>
                    <td>
                        <input type="checkbox" 
                               class="permission-checkbox" 
                               data-perm-id="${id}"
                               data-field="can_view_tenant"
                               ${perm.can_view_tenant ? 'checked' : ''}>
                    </td>
                    <td>
                        <input type="checkbox" 
                               class="permission-checkbox" 
                               data-perm-id="${id}"
                               data-field="can_create"
                               ${perm.can_create ? 'checked' : ''}>
                    </td>
                    <td>
                        <input type="checkbox" 
                               class="permission-checkbox" 
                               data-perm-id="${id}"
                               data-field="can_edit_all"
                               ${perm.can_edit_all ? 'checked' : ''}>
                    </td>
                    <td>
                        <input type="checkbox" 
                               class="permission-checkbox" 
                               data-perm-id="${id}"
                               data-field="can_edit_own"
                               ${perm.can_edit_own ? 'checked' : ''}>
                    </td>
                    <td>
                        <input type="checkbox" 
                               class="permission-checkbox" 
                               data-perm-id="${id}"
                               data-field="can_delete_all"
                               ${perm.can_delete_all ? 'checked' : ''}>
                    </td>
                    <td>
                        <input type="checkbox" 
                               class="permission-checkbox" 
                               data-perm-id="${id}"
                               data-field="can_delete_own"
                               ${perm.can_delete_own ? 'checked' : ''}>
                    </td>
                </tr>
            `;
            
            rows.push(row);
        });

        console.log('[Permissions] Generated', rows.length, 'rows');

        el.matrixBody.innerHTML = rows.join('');
        showMatrix();

        // Add change listeners
        document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', handleCheckboxChange);
        });

        console.log('[Permissions] Matrix rendered successfully');
    }

    function handleCheckboxChange(e) {
        const id = parseInt(e.target.dataset.permId);
        const field = e.target.dataset.field;
        const value = e.target.checked ? 1 : 0;

        console.log('[Permissions] Checkbox changed:', { id, field, value });

        if (!changes.has(id)) {
            const perm = permissions.find(p => p.id === id);
            if (perm) {
                changes.set(id, { ...perm });
            }
        }

        const change = changes.get(id);
        if (change) {
            change[field] = value;
        }

        // Enable save button
        if (el.btnSave) {
            el.btnSave.disabled = false;
            el.btnSave.classList.add('btn-warning');
            el.btnSave.innerHTML = '<i class="fas fa-save"></i> Save Changes (' + changes.size + ')';
        }

        console.log('[Permissions] Total changes:', changes.size);
    }

    async function saveChanges() {
        if (changes.size === 0) {
            AF.info('No changes to save');
            return;
        }

        const updates = Array.from(changes.values());

        console.log('[Permissions] Saving changes:', updates);

        try {
            AF.Loading.show(el.btnSave, 'Saving...');

            const response = await AF.post(API + '/batch', { items: updates });

            console.log('[Permissions] Save response:', response);

            AF.success(`${updates.length} permissions updated successfully!`);

            // Reset changes
            changes.clear();
            
            if (el.btnSave) {
                el.btnSave.disabled = true;
                el.btnSave.classList.remove('btn-warning');
                el.btnSave.innerHTML = '<i class="fas fa-save"></i> Save Changes';
            }

            // Reload
            load();
        } catch (err) {
            console.error('[Permissions] Save error:', err);
            AF.error(`Save failed: ${err.message}`);
        } finally {
            AF.Loading.hide(el.btnSave);
        }
    }

    // ════════════════════════════════════════════════════════════
    // UI STATES
    // ════════════════════════════════════════════════════════════
    
    function showLoading() {
        if (el.matrixLoading) el.matrixLoading.style.display = 'flex';
        if (el.matrixContainer) el.matrixContainer.style.display = 'none';
        if (el.emptyState) el.emptyState.style.display = 'none';
        if (el.errorState) el.errorState.style.display = 'none';
    }

    function showMatrix() {
        if (el.matrixLoading) el.matrixLoading.style.display = 'none';
        if (el.matrixContainer) el.matrixContainer.style.display = 'block';
        if (el.emptyState) el.emptyState.style.display = 'none';
        if (el.errorState) el.errorState.style.display = 'none';
    }

    function showEmpty() {
        if (el.matrixLoading) el.matrixLoading.style.display = 'none';
        if (el.matrixContainer) el.matrixContainer.style.display = 'none';
        if (el.emptyState) el.emptyState.style.display = 'flex';
        if (el.errorState) el.errorState.style.display = 'none';
    }

    function showError(msg) {
        if (el.matrixLoading) el.matrixLoading.style.display = 'none';
        if (el.matrixContainer) el.matrixContainer.style.display = 'none';
        if (el.emptyState) el.emptyState.style.display = 'none';
        if (el.errorState) el.errorState.style.display = 'flex';
        if (el.errorMessage) el.errorMessage.textContent = msg;
    }

    // ═════════��══════════════════════════════════════════════════
    // DATA LOADING
    // ════════════════════════════════════════════════════════════
    
    async function load() {
        try {
            console.log('[Permissions] Loading data...');
            showLoading();

            const response = await AF.get(API);
            
            console.log('[Permissions] API response:', response);

            let data = [];

            // Handle different response formats
            if (response.data) {
                if (Array.isArray(response.data)) {
                    data = response.data;
                } else if (response.data.items && Array.isArray(response.data.items)) {
                    data = response.data.items;
                }
            } else if (Array.isArray(response)) {
                data = response;
            }

            console.log('[Permissions] Processed data:', data.length, 'items');

            if (data.length === 0) {
                showEmpty();
                return;
            }

            renderMatrix(data);

        } catch (err) {
            console.error('[Permissions] Load error:', err);
            showError(err.message || 'Failed to load permissions');
        }
    }

    // ════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ════════════════════════════════════════════════════════════
    
    function init() {
        console.log('%c════════════════════════════════════', 'color:#3b82f6');
        console.log('%c[Permissions] Initializing...', 'color:#3b82f6;font-weight:bold');
        console.log('%c════════════════════════════════════', 'color:#3b82f6');

        // Get elements
        el = {
            matrixLoading: AF.$('matrixLoading'),
            matrixContainer: AF.$('matrixContainer'),
            emptyState: AF.$('emptyState'),
            errorState: AF.$('errorState'),
            errorMessage: AF.$('errorMessage'),
            matrixBody: AF.$('matrixBody'),
            btnSave: AF.$('btnSavePermissions'),
            btnRetry: AF.$('btnRetry')
        };

        console.log('[Permissions] Elements found:', {
            matrixLoading: !!el.matrixLoading,
            matrixContainer: !!el.matrixContainer,
            emptyState: !!el.emptyState,
            errorState: !!el.errorState,
            matrixBody: !!el.matrixBody,
            btnSave: !!el.btnSave,
            btnRetry: !!el.btnRetry
        });

        // Event listeners
        if (el.btnSave) {
            el.btnSave.onclick = saveChanges;
            console.log('[Permissions] Save button listener attached');
        }

        if (el.btnRetry) {
            el.btnRetry.onclick = load;
            console.log('[Permissions] Retry button listener attached');
        }

        // Load data
        load();

        console.log('%c[Permissions] ✅ Initialized successfully!', 'color:#10b981;font-weight:bold');
        console.log('%c════════════════════════════════════', 'color:#3b82f6');
    }

    // ════════════════════════════════════════════════════════════
    // PUBLIC API
    // ════════════════════════════════════════════════════════════
    
    window.PermissionsManager = {
        init,
        load,
        saveChanges
    };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        if (window.AdminFramework) {
            init();
        } else {
            console.log('[Permissions] Waiting for AdminFramework...');
            const checkFramework = setInterval(() => {
                if (window.AdminFramework) {
                    clearInterval(checkFramework);
                    console.log('[Permissions] AdminFramework loaded!');
                    init();
                }
            }, 100);
        }
    }

})();