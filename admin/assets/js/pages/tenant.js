/**
 * Tenants Management
 * Complete CRUD with Permissions
 */
(function() {
    'use strict';

    const AF = window.AdminFramework;
    const API = '/api/tenants';

    const state = {
        page: 1,
        perPage: 10,
        filters: {},
        permissions: {}
    };

    let el = {};

    // ════════════════════════════════════════════════════════════
    // RENDER FUNCTIONS
    // ════════════════════════════════════════════════════════════
    
    function renderTable(items) {
        if (!items || !items.length) {
            AF.Table.showEmpty({
                loading: el.loading,
                container: el.container,
                empty: el.empty,
                error: el.error
            });
            return;
        }

        const rows = items.map(item => {
            const statusClass = item.status === 'active' ? 'success' : 'warning';
            
            return `
                <tr>
                    <td>${item.id}</td>
                    <td>
                        <div class="tenant-name">
                            <strong>${AF.escapeHtml(item.name)}</strong>
                        </div>
                    </td>
                    <td>
                        ${item.domain 
                            ? `<code class="domain-badge">${AF.escapeHtml(item.domain)}</code>` 
                            : '<span class="text-muted">No domain</span>'}
                    </td>
                    <td>
                        <span class="user-badge">
                            <i class="fas fa-user"></i>
                            ID: ${item.owner_user_id}
                        </span>
                    </td>
                    <td>
                        ${AF.badge(item.status === 'active' ? 'Active' : 'Suspended', statusClass)}
                    </td>
                    <td>
                        <span class="date-display">
                            ${AF.formatDate(item.created_at)}
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            ${state.permissions.canEdit 
                                ? AF.actionButton('edit', `Tenants.edit(${item.id})`, 'Edit') 
                                : ''}
                            ${state.permissions.canDelete 
                                ? AF.actionButton('trash', `Tenants.remove(${item.id})`, 'Delete', 'danger') 
                                : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        AF.Table.render(el.tbody, rows);
        AF.Table.showTable({
            loading: el.loading,
            container: el.container,
            empty: el.empty,
            error: el.error
        });
    }

    // ════════════════════════════════════════════════════════════
    // FORM FUNCTIONS
    // ════════════════════════════════════════════════════════════
    
    async function save(e) {
        e.preventDefault();

        if (!AF.Form.validate('tenantForm')) {
            console.log('[Tenants] Form validation failed');
            return;
        }

        const formData = AF.Form.getData('tenantForm');
        const id = el.formId.value.trim();
        const isEdit = !!id;

        const data = {
            name: formData.name,
            domain: formData.domain || null,
            owner_user_id: parseInt(formData.owner_user_id),
            status: formData.status
        };

        if (isEdit) {
            data.id = parseInt(id);
        }

        console.log('[Tenants] Saving:', data);

        try {
            AF.Loading.show(el.btnSubmit, 'Saving...');

            if (isEdit) {
                await AF.put(`${API}/${data.id}`, data);
                AF.success('Tenant updated successfully!');
            } else {
                await AF.post(API, data);
                AF.success('Tenant created successfully!');
            }

            AF.Form.hide('tenantFormContainer');
            load();
            
        } catch (err) {
            console.error('[Tenants] Save error:', err);
            AF.error(err.message);
        } finally {
            AF.Loading.hide(el.btnSubmit);
        }
    }

    async function edit(id) {
        console.log('[Tenants] Edit ID:', id);

        try {
            AF.Loading.show(el.btnSubmit, 'Loading...');

            const response = await AF.get(`${API}/${id}`);
            const item = response.data;

            console.log('[Tenants] Loaded:', item);

            // Reset form
            el.form.reset();
            el.form.classList.remove('was-validated');

            // Fill form
            el.formId.value = item.id;
            el.formName.value = item.name;
            el.formDomain.value = item.domain || '';
            el.formOwnerUserId.value = item.owner_user_id;
            el.formStatus.value = item.status;

            // Show form
            AF.Form.show('tenantFormContainer', 'Edit Tenant');

            // Scroll to form
            setTimeout(() => {
                const container = AF.$('tenantFormContainer');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 200);

        } catch (err) {
            console.error('[Tenants] Edit error:', err);
            AF.error(`Failed to load tenant: ${err.message}`);
        } finally {
            AF.Loading.hide(el.btnSubmit);
        }
    }

    async function remove(id) {
        AF.Modal.confirm(
            'Are you sure you want to delete this tenant? This action cannot be undone.',
            async () => {
                try {
                    console.log('[Tenants] Deleting ID:', id);
                    
                    await AF.delete(`${API}/${id}`);
                    
                    AF.success('Tenant deleted successfully!');
                    load();
                    
                } catch (err) {
                    console.error('[Tenants] Delete error:', err);
                    AF.error(`Delete failed: ${err.message}`);
                }
            }
        );
    }

    function add() {
        console.log('[Tenants] Add new');

        // Reset form
        el.form.reset();
        el.form.classList.remove('was-validated');
        el.formId.value = '';

        // Show form
        AF.Form.show('tenantFormContainer', 'Add Tenant');
    }

    // ════════════════════════════════════════════════════════════
    // DATA LOADING
    // ════════════════════════════════════════════════════════════
    
    async function load(page = 1) {
        try {
            console.log('[Tenants] Loading page:', page);

            AF.Table.showLoading({
                loading: el.loading,
                container: el.container,
                empty: el.empty,
                error: el.error
            });

            state.page = page;

            const params = new URLSearchParams({
                page: page,
                per_page: state.perPage,
                ...state.filters
            });

            const data = await AF.get(`${API}?${params}`);

            console.log('[Tenants] Data received:', data.data?.items?.length || 0, 'items');

            renderTable(data.data?.items || []);
            AF.Table.renderPagination(el.pagination, el.paginationInfo, data.data?.meta || {});

        } catch (err) {
            console.error('[Tenants] Load error:', err);
            AF.Table.showError({
                loading: el.loading,
                container: el.container,
                empty: el.empty,
                error: el.error,
                errorMessage: el.errorMessage
            }, err.message);
        }
    }

    function applyFilters() {
        console.log('[Tenants] Applying filters');

        state.filters = {};

        const search = el.searchInput.value.trim();
        if (search) state.filters.search = search;

        const status = el.statusFilter.value;
        if (status) state.filters.status = status;

        console.log('[Tenants] Filters:', state.filters);

        load(1);
    }

    function resetFilters() {
        console.log('[Tenants] Resetting filters');

        el.searchInput.value = '';
        el.statusFilter.value = '';
        state.filters = {};

        load(1);
    }

    // ════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ════════════════════════════════════════════════════════════
    
    function init() {
        console.log('%c════════════════════════════════════', 'color:#3b82f6');
        console.log('%c[Tenants] Initializing...', 'color:#3b82f6;font-weight:bold');
        console.log('%c════════════════════════════════════', 'color:#3b82f6');

        // Get elements
        el = {
            loading: AF.$('tableLoading'),
            container: AF.$('tableContainer'),
            empty: AF.$('emptyState'),
            error: AF.$('errorState'),
            errorMessage: AF.$('errorMessage'),
            tbody: AF.$('tableBody'),
            pagination: AF.$('pagination'),
            paginationInfo: AF.$('paginationInfo'),

            form: AF.$('tenantForm'),
            formId: AF.$('formId'),
            formName: AF.$('formName'),
            formDomain: AF.$('formDomain'),
            formOwnerUserId: AF.$('formOwnerUserId'),
            formStatus: AF.$('formStatus'),

            searchInput: AF.$('searchInput'),
            statusFilter: AF.$('statusFilter'),

            btnSubmit: AF.$('btnSubmitForm'),
            btnAdd: AF.$('btnAddTenant'),
            btnClose: AF.$('btnCloseForm'),
            btnCancel: AF.$('btnCancelForm'),
            btnApply: AF.$('btnApplyFilters'),
            btnReset: AF.$('btnResetFilters'),
            btnRetry: AF.$('btnRetry'),
            btnRefresh: AF.$('btnRefresh')
        };

        // Load permissions
        try {
            const permsScript = AF.$('pagePermissions');
            if (permsScript) {
                state.permissions = JSON.parse(permsScript.textContent);
                console.log('[Tenants] Permissions loaded:', state.permissions);
            }
        } catch (e) {
            console.error('[Tenants] Failed to load permissions:', e);
            state.permissions = { 
                canView: false, 
                canCreate: false, 
                canEdit: false, 
                canDelete: false 
            };
        }

        // Event listeners
        if (el.form) el.form.onsubmit = save;
        if (el.btnAdd) el.btnAdd.onclick = add;
        if (el.btnClose) el.btnClose.onclick = () => AF.Form.hide('tenantFormContainer');
        if (el.btnCancel) el.btnCancel.onclick = () => AF.Form.hide('tenantFormContainer');
        if (el.btnApply) el.btnApply.onclick = applyFilters;
        if (el.btnReset) el.btnReset.onclick = resetFilters;
        if (el.btnRetry) el.btnRetry.onclick = () => load(state.page);
        if (el.btnRefresh) el.btnRefresh.onclick = () => load(state.page);

        // Search on enter
        if (el.searchInput) {
            el.searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    applyFilters();
                }
            });
        }

        // Pagination clicks
        if (el.pagination) {
            el.pagination.addEventListener('click', (e) => {
                const page = e.target.dataset.page;
                if (page && !e.target.disabled) {
                    load(parseInt(page));
                }
            });
        }

        // Load initial data
        load();

        console.log('%c[Tenants] ✅ Initialized successfully!', 'color:#10b981;font-weight:bold');
        console.log('%c════════════════════════════════════', 'color:#3b82f6');
    }

    // ════════════════════════════════════════════════════════════
    // PUBLIC API
    // ════════════════════════════════════════════════════════════
    
    window.Tenants = {
        init,
        load,
        edit,
        remove,
        add
    };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        if (window.AdminFramework) {
            init();
        }
    }

})();