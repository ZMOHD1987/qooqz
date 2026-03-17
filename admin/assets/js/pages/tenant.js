/**
 * Tenants Management – Production Version
 * Version: 2.0.0
 *
 * ✅ AdminFramework integration
 * ✅ Tabs: Basic Info | Users | Addresses (embedded sub-fragments)
 * ✅ Translation support via window.TENANTS_TRANSLATIONS
 * ✅ Granular permissions from window.PAGE_PERMISSIONS
 * ✅ RTL/LTR direction switching
 * ✅ Pagination + filters
 */
(function () {
    'use strict';

    const AF  = window.AdminFramework;
    const API = '/api/tenants';

    const state = {
        page: 1,
        perPage: window.TENANTS_CONFIG?.itemsPerPage || 25,
        filters: {},
        permissions: {},
        language: window.USER_LANGUAGE || 'en',
        currentTenantId: null
    };

    let el = {};

    // ─────────────────────────────────────────────
    // TRANSLATION HELPER
    // ─────────────────────────────────────────────
    function t(key, fallback) {
        const keys = key.split('.');
        let val = window.TENANTS_TRANSLATIONS;
        for (const k of keys) {
            if (val && val[k] !== undefined) {
                val = val[k];
            } else {
                return fallback !== undefined ? fallback : key;
            }
        }
        return (typeof val === 'string') ? val : (fallback !== undefined ? fallback : key);
    }

    // ─────────────────────────────────────────────
    // DIRECTION HELPER
    // ─────────────────────────────────────────────
    function setDirection(lang) {
        if (!lang) return;
        const rtlLangs = ['ar', 'he', 'fa', 'ur', 'ps'];
        const isRtl = rtlLangs.includes(String(lang).toLowerCase().substring(0, 2));
        const dir = isRtl ? 'rtl' : 'ltr';
        try { document.documentElement.dir = dir; } catch (e) { /* ignore */ }
        const container = document.getElementById('tenantsPageContainer');
        if (container) {
            container.dir = dir;
            container.classList.toggle('rtl', isRtl);
            container.classList.toggle('ltr', !isRtl);
        }
    }

    // ─────────────────────────────────────────────
    // TABS
    // ─────────────────────────────────────────────
    function activateTab(tabId) {
        document.querySelectorAll('#tenantFormTabs .tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabId);
        });
        document.querySelectorAll('#tenantForm .tab-content').forEach(tc => {
            const show = tc.id === tabId;
            tc.classList.toggle('active', show);
            tc.style.display = show ? '' : 'none';
        });

        // Lazy-load sub-fragments when tab is first opened
        if (tabId === 'tab-users' && state.currentTenantId) {
            loadSubFragment('tenantUsersContainer',
                `${window.TENANTS_CONFIG?.tenantUsersUrl || '/admin/fragments/tenant_users.php'}?embedded=1&tenant_id=${state.currentTenantId}&lang=${state.language}`
            );
        }
        if (tabId === 'tab-addresses' && state.currentTenantId) {
            loadSubFragment('tenantAddressesContainer',
                `${window.TENANTS_CONFIG?.addressesUrl || '/admin/fragments/addresses.php'}?embedded=1&owner_type=entity&tenant_id=${state.currentTenantId}&lang=${state.language}`
            );
        }
    }

    function enableSubTabs(tenantId) {
        state.currentTenantId = tenantId;
        const btnUsers = document.getElementById('tabBtnUsers');
        const btnAddr  = document.getElementById('tabBtnAddresses');
        if (btnUsers) btnUsers.disabled = false;
        if (btnAddr)  btnAddr.disabled  = false;
    }

    async function loadSubFragment(containerId, url) {
        const container = document.getElementById(containerId);
        if (!container) return;
        // Only load once (detect by checking for iframe)
        if (container.querySelector('iframe')) return;
        container.innerHTML = `<iframe src="${url}" style="width:100%;min-height:500px;border:none;" loading="lazy"></iframe>`;
    }

    // ─────────────────────────────────────────────
    // ESCAPE HTML
    // ─────────────────────────────────────────────
    function esc(text) {
        if (text === null || text === undefined) return '';
        const d = document.createElement('div');
        d.textContent = String(text);
        return d.innerHTML;
    }

    // ─────────────────────────────────────────────
    // RENDER TABLE
    // ─────────────────────────────────────────────
    function renderTable(items) {
        if (!items || !items.length) {
            if (el.loading)    el.loading.style.display    = 'none';
            if (el.container)  el.container.style.display  = 'none';
            if (el.empty)      el.empty.style.display      = 'block';
            if (el.error)      el.error.style.display      = 'none';
            return;
        }

        const rows = items.map(item => {
            const isActive   = item.status === 'active';
            const statusText = isActive ? t('table.status.active', 'Active') : t('table.status.suspended', 'Suspended');
            const statusCls  = isActive
                ? 'badge-status badge-active'
                : 'badge-status badge-suspended';

            const ownerDisplay = item.owner_username
                ? `${esc(item.owner_username)} <small>(ID: ${item.owner_user_id})</small>`
                : `<span class="text-muted">ID: ${item.owner_user_id}</span>`;

            const domainDisplay = item.domain
                ? `<code class="domain-badge">${esc(item.domain)}</code>`
                : `<span class="text-muted">${t('table.no_domain', 'No domain')}</span>`;

            return `
                <tr>
                    <td>${item.id}</td>
                    <td><strong>${esc(item.name)}</strong></td>
                    <td>${domainDisplay}</td>
                    <td>${ownerDisplay}</td>
                    <td><span class="${statusCls}">${esc(statusText)}</span></td>
                    <td><span class="date-display">${AF.formatDate ? AF.formatDate(item.created_at) : esc(item.created_at || '')}</span></td>
                    <td>
                        <div class="table-actions">
                            ${state.permissions.canEdit
                                ? `<button class="btn btn-sm btn-outline" onclick="Tenants.edit(${item.id})">${t('table.actions.edit', 'Edit')}</button>`
                                : ''}
                            ${state.permissions.canDelete
                                ? `<button class="btn btn-sm btn-danger" onclick="Tenants.remove(${item.id})">${t('table.actions.delete', 'Delete')}</button>`
                                : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        if (el.tbody) el.tbody.innerHTML = rows;

        if (el.loading)   el.loading.style.display   = 'none';
        if (el.container) el.container.style.display = 'block';
        if (el.empty)     el.empty.style.display     = 'none';
        if (el.error)     el.error.style.display     = 'none';
    }

    // ─────────────────────────────────────────────
    // DATA LOADING
    // ─────────────────────────────────────────────
    async function load(page = 1) {
        try {
            console.log('[Tenants] Loading page:', page);

            if (el.loading)   el.loading.style.display   = 'block';
            if (el.container) el.container.style.display = 'none';
            if (el.empty)     el.empty.style.display     = 'none';
            if (el.error)     el.error.style.display     = 'none';

            state.page = page;

            const params = new URLSearchParams({
                page:     page,
                per_page: state.perPage,
                format:   'json',
                ...state.filters
            });

            console.log('[Tenants] URL:', `${API}?${params}`);
            const response = await AF.get(`${API}?${params}`);
            console.log('[Tenants] Response:', response);

            const data  = response?.data || response;
            const items = data?.items || (Array.isArray(data) ? data : []);
            const meta  = data?.meta  || {};

            renderTable(items);

            // Pagination
            if (el.pagination && AF.Table?.renderPagination) {
                AF.Table.renderPagination(el.pagination, el.paginationInfo, meta);
            } else if (el.pagination) {
                renderPagination(meta);
            }

        } catch (err) {
            console.error('[Tenants] Load error:', err);
            if (el.loading)   el.loading.style.display   = 'none';
            if (el.container) el.container.style.display = 'none';
            if (el.empty)     el.empty.style.display     = 'none';
            if (el.error)     el.error.style.display     = 'block';
            if (el.errorMessage) el.errorMessage.textContent = err?.message || t('messages.error.load_failed', 'Failed to load tenants');
        }
    }

    function renderPagination(meta) {
        if (!el.pagination) return;
        const { page = 1, last_page = 1, total = 0, per_page = 25 } = meta;
        if (el.paginationInfo) {
            const from = ((page - 1) * per_page) + 1;
            const to   = Math.min(page * per_page, total);
            el.paginationInfo.textContent = total > 0 ? `${from}–${to} / ${total}` : '';
        }
        const pages = [];
        for (let i = 1; i <= last_page; i++) {
            pages.push(`<button class="pagination-btn${i === page ? ' active' : ''}" data-page="${i}">${i}</button>`);
        }
        el.pagination.innerHTML = pages.join('');
    }

    // ─────────────────────────────────────────────
    // FILTERS
    // ─────────────────────────────────────────────
    function applyFilters() {
        state.filters = {};
        const search = el.searchInput?.value?.trim();
        if (search) state.filters.search = search;
        const status = el.statusFilter?.value;
        if (status) state.filters.status = status;
        const owner = el.ownerFilter?.value?.trim();
        if (owner && !isNaN(owner)) state.filters.owner_user_id = owner;
        load(1);
    }

    function resetFilters() {
        if (el.searchInput)  el.searchInput.value  = '';
        if (el.statusFilter) el.statusFilter.value = '';
        if (el.ownerFilter)  el.ownerFilter.value  = '';
        state.filters = {};
        load(1);
    }

    // ─────────────────────────────────────────────
    // FORM
    // ─────────────────────────────────────────────
    async function save(e) {
        e.preventDefault();
        if (AF.Form?.validate && !AF.Form.validate('tenantForm')) return;

        const formData = AF.Form?.getData ? AF.Form.getData('tenantForm') : getFormData();
        const id       = el.formId?.value?.trim();
        const isEdit   = !!id;

        const data = {
            name:          (formData.name || '').trim(),
            domain:        (formData.domain || '').trim() || null,
            owner_user_id: parseInt(formData.owner_user_id) || 0,
            status:        formData.status || 'active'
        };

        if (!data.name || data.name.length < 3) {
            AF.error ? AF.error(t('form.validation.name_required', 'Please enter a valid tenant name')) : alert(t('form.validation.name_required', 'Please enter a valid name'));
            return;
        }
        if (!data.owner_user_id || data.owner_user_id < 1) {
            AF.error ? AF.error(t('form.validation.owner_required', 'Please enter a valid user ID')) : alert('Invalid owner user ID');
            return;
        }

        if (isEdit) data.id = parseInt(id);

        try {
            if (AF.Loading?.show) AF.Loading.show(el.btnSubmit, isEdit ? t('form.buttons.updating', 'Updating…') : t('form.buttons.saving', 'Saving…'));

            let response;
            if (isEdit) {
                response = await AF.put(`${API}/${data.id}`, data);
            } else {
                response = await AF.post(API, data);
            }

            const savedItem = response?.data || response;
            if (AF.success) AF.success(isEdit ? t('messages.success.updated', 'Tenant updated') : t('messages.success.created', 'Tenant created'));

            // Enable sub-tabs now we have an ID
            const savedId = savedItem?.id || (isEdit ? parseInt(id) : null);
            if (savedId) enableSubTabs(savedId);

            if (AF.Form?.hide) {
                // Don't hide form – stay on Basic tab so user can switch to Users/Addresses
            }
            await load(state.page);

        } catch (err) {
            console.error('[Tenants] Save error:', err);
            if (AF.error) AF.error(err?.message || t('messages.error.save_failed', 'Failed to save tenant'));
        } finally {
            if (AF.Loading?.hide) AF.Loading.hide(el.btnSubmit);
        }
    }

    function getFormData() {
        const form = document.getElementById('tenantForm');
        const data = {};
        new FormData(form).forEach((v, k) => { data[k] = v; });
        return data;
    }

    async function edit(id) {
        console.log('[Tenants] Edit ID:', id);
        try {
            if (AF.Loading?.show) AF.Loading.show(el.btnSubmit, t('page.loading', 'Loading…'));

            const response = await AF.get(`${API}/${id}`);
            const item     = response?.data || response;

            if (!item || !item.id) throw new Error(t('messages.error.not_found', 'Tenant not found'));

            // Reset form
            const form = document.getElementById('tenantForm');
            if (form) {
                form.reset();
                form.classList.remove('was-validated');
            }
            if (el.formId)         el.formId.value         = item.id;
            if (el.formName)       el.formName.value       = item.name || '';
            if (el.formDomain)     el.formDomain.value     = item.domain || '';
            if (el.formOwnerUserId) el.formOwnerUserId.value = item.owner_user_id || '';
            if (el.formStatus)     el.formStatus.value     = item.status || 'active';

            // Enable sub-tabs
            enableSubTabs(item.id);

            // Switch to Basic tab
            activateTab('tab-basic');

            // Show form
            const titleEl = document.getElementById('formTitle');
            if (titleEl) titleEl.querySelector('span[data-i18n]').textContent = t('form.edit_title', 'Edit Tenant');
            const container = document.getElementById('tenantFormContainer');
            if (container) {
                container.style.display = 'block';
                setTimeout(() => container.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
            }

        } catch (err) {
            console.error('[Tenants] Edit error:', err);
            if (AF.error) AF.error(err?.message || t('messages.error.load_failed', 'Failed to load tenant'));
        } finally {
            if (AF.Loading?.hide) AF.Loading.hide(el.btnSubmit);
        }
    }

    function add() {
        console.log('[Tenants] Add new');
        state.currentTenantId = null;

        const form = document.getElementById('tenantForm');
        if (form) {
            form.reset();
            form.classList.remove('was-validated');
        }
        if (el.formId) el.formId.value = '';

        // Disable sub-tabs until saved
        const btnUsers = document.getElementById('tabBtnUsers');
        const btnAddr  = document.getElementById('tabBtnAddresses');
        if (btnUsers) btnUsers.disabled = true;
        if (btnAddr)  btnAddr.disabled  = true;

        // Reset sub-fragment containers
        const usersContainer = document.getElementById('tenantUsersContainer');
        const addrContainer  = document.getElementById('tenantAddressesContainer');
        if (usersContainer) usersContainer.innerHTML = '<div class="sub-fragment-placeholder"><i class="fas fa-users fa-2x"></i><p>' + t('tabs.users', 'Users') + '</p></div>';
        if (addrContainer)  addrContainer.innerHTML  = '<div class="sub-fragment-placeholder"><i class="fas fa-map-marker-alt fa-2x"></i><p>' + t('tabs.addresses', 'Addresses') + '</p></div>';

        activateTab('tab-basic');

        const titleEl = document.getElementById('formTitle');
        if (titleEl) titleEl.querySelector('span[data-i18n]').textContent = t('form.add_title', 'Add Tenant');

        const container = document.getElementById('tenantFormContainer');
        if (container) {
            container.style.display = 'block';
            setTimeout(() => container.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
        }
    }

    async function remove(id) {
        const msg = t('table.actions.confirm_delete', 'Are you sure you want to delete this tenant?');
        if (AF.Modal?.confirm) {
            AF.Modal.confirm(msg, async () => {
                try {
                    await AF.delete(`${API}/${id}`);
                    if (AF.success) AF.success(t('messages.success.deleted', 'Tenant deleted'));
                    load();
                } catch (err) {
                    console.error('[Tenants] Delete error:', err);
                    if (AF.error) AF.error(err?.message || t('messages.error.delete_failed', 'Failed to delete tenant'));
                }
            });
        } else if (confirm(msg)) {
            try {
                await AF.delete(`${API}/${id}`);
                if (AF.success) AF.success(t('messages.success.deleted', 'Tenant deleted'));
                load();
            } catch (err) {
                console.error('[Tenants] Delete error:', err);
                if (AF.error) AF.error(err?.message || t('messages.error.delete_failed', 'Failed to delete'));
            }
        }
    }

    function hideForm() {
        const container = document.getElementById('tenantFormContainer');
        if (container) container.style.display = 'none';
        state.currentTenantId = null;
    }

    // ─────────────────────────────────────────────
    // INITIALIZATION
    // ─────────────────────────────────────────────
    function init() {
        console.log('%c[Tenants] Initializing…', 'color:#3b82f6;font-weight:bold');

        // Set direction
        setDirection(state.language);

        // Gather DOM elements
        el = {
            loading:        document.getElementById('tableLoading'),
            container:      document.getElementById('tableContainer'),
            empty:          document.getElementById('emptyState'),
            error:          document.getElementById('errorState'),
            errorMessage:   document.getElementById('errorMessage'),
            tbody:          document.getElementById('tableBody'),
            pagination:     document.getElementById('pagination'),
            paginationInfo: document.getElementById('paginationInfo'),

            form:           document.getElementById('tenantForm'),
            formId:         document.getElementById('formId'),
            formName:       document.getElementById('formName'),
            formDomain:     document.getElementById('formDomain'),
            formOwnerUserId:document.getElementById('formOwnerUserId'),
            formStatus:     document.getElementById('formStatus'),

            searchInput:    document.getElementById('searchInput'),
            statusFilter:   document.getElementById('statusFilter'),
            ownerFilter:    document.getElementById('ownerFilter'),

            btnSubmit:      document.getElementById('btnSubmitForm'),
            btnAdd:         document.getElementById('btnAddTenant'),
            btnClose:       document.getElementById('btnCloseForm'),
            btnCancel:      document.getElementById('btnCancelForm'),
            btnApply:       document.getElementById('btnApplyFilters'),
            btnReset:       document.getElementById('btnResetFilters'),
            btnRetry:       document.getElementById('btnRetry'),
            btnRefresh:     document.getElementById('btnRefresh')
        };

        // Load permissions
        try {
            const permsEl = document.getElementById('pagePermissions');
            if (permsEl) {
                state.permissions = JSON.parse(permsEl.textContent || '{}');
            } else {
                state.permissions = window.PAGE_PERMISSIONS || {};
            }
        } catch (e) {
            console.warn('[Tenants] Failed to load permissions:', e);
            state.permissions = window.PAGE_PERMISSIONS || {};
        }

        // Tab buttons
        document.querySelectorAll('#tenantFormTabs .tab-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                if (!this.disabled) activateTab(this.dataset.tab);
            });
        });

        // Form events
        if (el.form)       el.form.onsubmit       = save;
        if (el.btnAdd)     el.btnAdd.onclick       = add;
        if (el.btnClose)   el.btnClose.onclick     = hideForm;
        if (el.btnCancel)  el.btnCancel.onclick    = hideForm;
        if (el.btnApply)   el.btnApply.onclick     = applyFilters;
        if (el.btnReset)   el.btnReset.onclick     = resetFilters;
        if (el.btnRetry)   el.btnRetry.onclick     = () => load(state.page);
        if (el.btnRefresh) el.btnRefresh.onclick   = () => load(state.page);

        if (el.searchInput) {
            el.searchInput.addEventListener('keypress', e => {
                if (e.key === 'Enter') applyFilters();
            });
        }

        if (el.pagination) {
            el.pagination.addEventListener('click', e => {
                const page = e.target.dataset.page;
                if (page && !e.target.disabled) load(parseInt(page));
            });
        }

        // Initial load
        load(1);

        console.log('%c[Tenants] ✅ Initialized', 'color:#10b981;font-weight:bold');
    }

    // ─────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────
    window.Tenants = { init, load, edit, remove, add };

    // Auto-init in standalone mode
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (window.AdminFramework) init();
        });
    } else {
        if (window.AdminFramework) init();
    }

})();
