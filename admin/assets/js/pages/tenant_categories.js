/**
 * /admin/assets/js/pages/tenant_categories.js — Production v2.0
 *
 * ─ التغييرات عن النسخة السابقة ─────────────────────────────
 * • translations تُحمَّل من CONFIG.strings (مُحقَنة من PHP) بدلاً
 *   من fetch منفصل — يُقلّل طلب HTTP ويضمن التزامن مع PHP
 * • notify() بـ tc- prefix يتطابق مع CSS
 * • showState() IDs: tcLoading/tcEmpty/tcError/tcTableContainer
 * • btn-outline للتعديل → btn-primary
 * • credentials: 'same-origin' على كل fetch
 * • ESC يُغلق form card
 * • Admin.page.register + window.page للـ fragment navigation
 * • pagination بـ .pagination بدل pagination-buttons
 * ─────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    const CFG           = window.TENANT_CATEGORIES_CONFIG || {};
    const API           = CFG.apiUrl        || '/api/categories-tenants';
    const TENANTS_API   = CFG.tenantsUrl    || '/api/tenants';
    const CATEGORIES_API = CFG.categoriesUrl || '/api/categories';

    // ── i18n ──────────────────────────────────────────────────
    // الترجمات مُحقَنة من PHP في CONFIG.strings — لا fetch إضافي
    const S = CFG.strings || {};
    function t(key, fallback) {
        const parts = key.split('.');
        let val = S;
        for (const k of parts) {
            if (val && typeof val === 'object' && k in val) val = val[k];
            else return fallback || key;
        }
        return typeof val === 'string' ? val : (fallback || key);
    }

    // ── State ─────────────────────────────────────────────────
    const state = {
        page:         1,
        perPage:      25,
        filters:      {},
        items:        [],
        tenants:      [],
        categories:   [],
        isSuperAdmin: CFG.isSuperAdmin || false,
        permissions:  CFG.permissions  || {},
    };

    let el = {};

    // ════════════════════════════════════════════════════════
    // TOAST NOTIFICATIONS  (tc- prefix → matches CSS)
    // ════════════════════════════════════════════════════════
    function notify(message, type = 'info') {
        const AF = window.AdminFramework;
        if (AF) {
            if (type === 'success' && AF.success) return AF.success(message);
            if (type === 'error'   && AF.error)   return AF.error(message);
            if (type === 'warning' && AF.warning)  return AF.warning(message);
            if (AF.notify) return AF.notify(message, type);
        }

        let container = document.getElementById('tcNotifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'tcNotifications';
            container.className = 'tc-notifications';
            const page = document.getElementById('tenantCategoriesPage');
            (page || document.body).insertBefore(container, (page || document.body).firstChild);
        }

        const toast = document.createElement('div');
        toast.className = `tc-toast tc-toast-${type}`;
        toast.setAttribute('role', 'alert');

        const msg = document.createElement('span');
        msg.textContent = message;
        toast.appendChild(msg);

        const close = document.createElement('button');
        close.className = 'tc-toast-close';
        close.setAttribute('aria-label', 'Close');
        close.textContent = '\u00d7';
        close.addEventListener('click', () => toast.remove());
        toast.appendChild(close);

        container.appendChild(toast);
        setTimeout(() => { if (toast.parentNode) toast.remove(); }, 4500);
    }

    // ════════════════════════════════════════════════════════
    // TABLE STATE
    // ════════════════════════════════════════════════════════
    function showState(which, msg = '') {
        const loading   = document.getElementById('tcLoading');
        const empty     = document.getElementById('tcEmpty');
        const error     = document.getElementById('tcError');
        const container = document.getElementById('tcTableContainer');
        const errMsg    = document.getElementById('tcErrorMessage');

        [loading, empty, error, container].forEach(e => { if (e) e.style.display = 'none'; });

        switch (which) {
            case 'loading': if (loading)   loading.style.display   = 'flex';  break;
            case 'empty':   if (empty)     empty.style.display     = 'flex';  break;
            case 'error':
                if (error)  error.style.display = 'flex';
                if (errMsg && msg) errMsg.textContent = msg;
                break;
            default:        if (container) container.style.display = 'block'; break;
        }
    }

    // ════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════
    function esc(txt) {
        if (txt == null) return '';
        const d = document.createElement('div');
        d.textContent = String(txt);
        return d.innerHTML;
    }

    async function apiFetch(url, options = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: {
                'Content-Type':     'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token':     CFG.csrfToken || '',
            },
        };
        const config = {
            ...defaults,
            ...options,
            headers: { ...defaults.headers, ...(options.headers || {}) },
        };
        const res  = await fetch(url, config);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || data.error || `HTTP ${res.status}`);
        return data;
    }

    // ════════════════════════════════════════════════════════
    // DROPDOWNS
    // ════════════════════════════════════════════════════════
    async function loadDropdowns() {
        try {
            const catParams = new URLSearchParams({
                format: 'json', limit: 1000,
                lang: CFG.lang || 'en',
                skip_tc_filter: 1, parent_id: 0,
            });

            const promises = [
                apiFetch(`${CATEGORIES_API}?${catParams}`),
            ];
            if (state.isSuperAdmin) {
                promises.push(apiFetch(`${TENANTS_API}?format=json&limit=1000`));
            }

            const [catResult, tenantResult] = await Promise.all(promises);

            // Categories
            const cats = catResult?.data?.items || catResult?.data || [];
            if (Array.isArray(cats)) {
                state.categories = cats;
                populateSelect('tenantCategoryCategoryId', cats);
                populateDatalist('filterCategoriesList', cats);
            }

            // Tenants (super admin only)
            if (tenantResult) {
                const tenants = tenantResult?.data?.items || tenantResult?.data || [];
                if (Array.isArray(tenants)) {
                    state.tenants = tenants;
                    populateDatalist('tenantsList',       tenants);
                    populateDatalist('filterTenantsList', tenants);
                }
            }
        } catch (e) {
            console.error('[TenantCategories] loadDropdowns:', e);
        }
    }

    function populateSelect(selectId, items) {
        const select = document.getElementById(selectId);
        if (!select || !Array.isArray(items)) return;
        const placeholder = select.querySelector('option[value=""]');
        select.innerHTML = '';
        if (placeholder) select.appendChild(placeholder);
        items.forEach(item => {
            const o = document.createElement('option');
            o.value       = item.id;
            o.textContent = `${item.name || item.id} (#${item.id})`;
            select.appendChild(o);
        });
    }

    function populateDatalist(datalistId, items) {
        const dl = document.getElementById(datalistId);
        if (!dl || !Array.isArray(items)) return;
        dl.innerHTML = '';
        items.forEach(item => {
            const o = document.createElement('option');
            o.value = item.name || item.id;
            o.setAttribute('data-id', item.id);
            dl.appendChild(o);
        });
    }

    function getIdFromDatalist(datalistId, displayValue) {
        const dl      = document.getElementById(datalistId);
        const trimmed = (displayValue || '').trim();
        if (!dl || !trimmed) return null;
        for (const o of dl.querySelectorAll('option')) {
            if (o.value === trimmed) return o.getAttribute('data-id');
        }
        if (/^\d+$/.test(trimmed)) return trimmed;
        return null;
    }

    function setDisplayFromId(hiddenId, displayId, datalistId, idValue) {
        if (!idValue) return;
        const dl = document.getElementById(datalistId);
        if (!dl) return;
        for (const o of dl.querySelectorAll('option')) {
            if (o.getAttribute('data-id') === String(idValue)) {
                const displayEl = document.getElementById(displayId);
                const hiddenEl  = document.getElementById(hiddenId);
                if (displayEl) displayEl.value = o.value;
                if (hiddenEl)  hiddenEl.value  = idValue;
                return;
            }
        }
    }

    // ════════════════════════════════════════════════════════
    // LOAD DATA
    // ════════════════════════════════════════════════════════
    async function loadData(page = 1) {
        showState('loading');
        state.page = page;

        const params = new URLSearchParams({ page, limit: state.perPage, format: 'json' });
        Object.entries(state.filters).forEach(([k, v]) => {
            if (v !== '' && v != null) params.set(k, v);
        });
        if (!state.isSuperAdmin && CFG.tenantId) {
            params.set('tenant_id', CFG.tenantId);
        }

        try {
            const result = await apiFetch(`${API}?${params}`, {
                headers: { 'Accept': 'application/json' },
            });

            if (result.success && result.data) {
                state.items = Array.isArray(result.data) ? result.data : (result.data.items || []);
                const total = Array.isArray(result.data)
                    ? result.data.length
                    : (result.data.meta?.total || state.items.length);

                if (state.items.length === 0) {
                    showState('empty');
                } else {
                    showState('table');
                    renderTable();
                    renderPagination(total);
                }
            } else {
                showState('empty');
            }
        } catch (e) {
            console.error('[TenantCategories] loadData:', e);
            showState('error', e.message || t('error_loading', 'Failed to load data'));
        }
    }

    // ════════════════════════════════════════════════════════
    // RENDER TABLE
    // ════════════════════════════════════════════════════════
    function renderTable() {
        if (!el.tableBody) return;
        el.tableBody.innerHTML = state.items.map(item => {
            const date = item.created_at
                ? new Date(item.created_at).toLocaleDateString()
                : '—';

            // ✅ btn-primary للتعديل (موحَّد)
            const editBtn = state.permissions.canEdit
                ? `<button class="btn btn-sm btn-primary tc-edit-btn"
                           data-id="${esc(item.id)}"
                           aria-label="${t('edit_button','Edit')}">
                       <i class="fas fa-edit" aria-hidden="true"></i>
                   </button>`
                : '';
            const delBtn = state.permissions.canDelete
                ? `<button class="btn btn-sm btn-danger tc-del-btn"
                           data-id="${esc(item.id)}"
                           aria-label="${t('delete_button','Delete')}">
                       <i class="fas fa-trash" aria-hidden="true"></i>
                   </button>`
                : '';

            const statusBtn = state.isSuperAdmin
                ? `<button class="tc-toggle-btn ${item.is_active ? 'tc-toggle-active' : 'tc-toggle-inactive'}"
                           data-id="${esc(item.id)}"
                           data-new-status="${item.is_active ? 0 : 1}">
                       ${item.is_active ? t('toggle_active','Active') : t('toggle_inactive','Inactive')}
                   </button>`
                : '';

            return `
                <tr data-id="${esc(item.id)}">
                    <td>${esc(item.id)}</td>
                    ${state.isSuperAdmin ? `<td>${esc(item.tenant_id)}</td>` : ''}
                    <td><strong>${esc(item.tenant_name || '—')}</strong></td>
                    <td>${esc(item.category_id)}</td>
                    <td><strong>${esc(item.category_name || '—')}</strong></td>
                    <td>${esc(item.sort_order ?? 0)}</td>
                    ${state.isSuperAdmin ? `<td>${statusBtn}</td>` : ''}
                    <td>${esc(date)}</td>
                    <td>
                        <div class="table-actions">
                            ${editBtn}
                            ${delBtn}
                        </div>
                    </td>
                </tr>`;
        }).join('');

        // Delegate events
        el.tableBody.querySelectorAll('.tc-edit-btn').forEach(b =>
            b.addEventListener('click', () => editItem(b.dataset.id)));
        el.tableBody.querySelectorAll('.tc-del-btn').forEach(b =>
            b.addEventListener('click', () => deleteItem(b.dataset.id)));
        el.tableBody.querySelectorAll('.tc-toggle-btn').forEach(b =>
            b.addEventListener('click', () => toggleStatus(b.dataset.id, b.dataset.newStatus)));
    }

    // ════════════════════════════════════════════════════════
    // PAGINATION
    // ════════════════════════════════════════════════════════
    function renderPagination(total) {
        const totalPages = Math.max(1, Math.ceil(total / state.perPage));
        const start = total > 0 ? (state.page - 1) * state.perPage + 1 : 0;
        const end   = Math.min(state.page * state.perPage, total);

        const infoEl = document.getElementById('tcPaginationInfo');
        if (infoEl) {
            infoEl.textContent = total > 0
                ? `${start}–${end} / ${total}`
                : t('no_records', 'No records');
        }

        const pagEl = document.getElementById('tcPagination');
        if (!pagEl) return;
        pagEl.innerHTML = '';
        if (totalPages <= 1) return;

        const makeBtn = (label, targetPage, active = false, disabled = false) => {
            const btn = document.createElement('button');
            btn.className = 'pagination-btn' + (active ? ' active' : '');
            btn.innerHTML = label;
            btn.disabled  = disabled;
            if (!disabled) btn.addEventListener('click', () => loadData(targetPage));
            return btn;
        };

        pagEl.appendChild(makeBtn('&laquo;', state.page - 1, false, state.page <= 1));
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= state.page - 2 && i <= state.page + 2)) {
                pagEl.appendChild(makeBtn(String(i), i, i === state.page, i === state.page));
            } else if (i === state.page - 3 || i === state.page + 3) {
                const sp = document.createElement('span');
                sp.className = 'pagination-dots';
                sp.textContent = '\u2026';
                pagEl.appendChild(sp);
            }
        }
        pagEl.appendChild(makeBtn('&raquo;', state.page + 1, false, state.page >= totalPages));
    }

    // ════════════════════════════════════════════════════════
    // FORM
    // ════════════════════════════════════════════════════════
    function showForm(isEdit = false, data = null) {
        if (!el.formContainer) return;
        el.formContainer.style.display = 'block';
        if (el.form) el.form.reset();
        if (el.formId) el.formId.value = '';

        if (el.formTitle) {
            el.formTitle.textContent = isEdit
                ? t('form_edit_title', 'Edit Tenant Category')
                : t('form_add_title',  'Add Tenant Category');
        }
        if (el.btnDelete) el.btnDelete.style.display = isEdit ? 'inline-flex' : 'none';

        if (isEdit && data) {
            if (el.formId) el.formId.value = data.id;

            if (state.isSuperAdmin && el.tenantDisplay) {
                setDisplayFromId('tenantCategoryTenantIdHidden', 'tenantCategoryTenantId', 'tenantsList', data.tenant_id);
            }

            if (el.categorySelect) el.categorySelect.value = data.category_id;
            if (el.sortOrder)      el.sortOrder.value      = data.sort_order ?? 0;
            if (state.isSuperAdmin && el.isActive) el.isActive.value = data.is_active ?? 1;
        }

        el.formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function hideForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
        if (el.form) el.form.reset();
    }

    async function editItem(id) {
        try {
            const result = await apiFetch(`${API}/${id}?format=json`);
            if (result.success && result.data) {
                const item = Array.isArray(result.data) ? result.data[0] : result.data;
                if (item) showForm(true, item);
                else notify(t('alert_error', 'Error'), 'error');
            } else {
                notify(t('alert_error', 'Error'), 'error');
            }
        } catch (e) {
            console.error('[TenantCategories] editItem:', e);
            notify(t('alert_error', 'Error'), 'error');
        }
    }

    async function saveData(e) {
        if (e) e.preventDefault();

        const id     = el.formId?.value?.trim() || '';
        const isEdit = !!id;

        // tenant_id
        let tenantId = CFG.tenantId;
        if (state.isSuperAdmin && el.tenantDisplay) {
            tenantId = getIdFromDatalist('tenantsList', el.tenantDisplay.value);
            if (!tenantId) {
                notify(t('validation_tenant', 'Please select a tenant'), 'error');
                return;
            }
        }

        // category_id
        const categoryId = el.categorySelect?.value || '';
        if (!categoryId) {
            notify(t('validation_category', 'Please select a category'), 'error');
            return;
        }

        const data = {
            tenant_id:   parseInt(tenantId),
            category_id: parseInt(categoryId),
            sort_order:  parseInt(el.sortOrder?.value || 0) || 0,
            is_active:   state.isSuperAdmin ? (parseInt(el.isActive?.value || 1) || 1) : 1,
        };
        if (isEdit) data.id = parseInt(id);

        if (el.btnSave) {
            el.btnSave.disabled = true;
            el.btnSave.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        try {
            const url    = isEdit ? `${API}/${data.id}` : API;
            const result = await apiFetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                body:   JSON.stringify(data),
            });

            if (result.success) {
                notify(isEdit
                    ? t('alert_updated', 'Updated successfully')
                    : t('alert_added',   'Added successfully'),
                    'success'
                );
                hideForm();
                loadData(state.page);
            } else {
                notify(result.message || t('alert_error', 'Error'), 'error');
            }
        } catch (err) {
            console.error('[TenantCategories] saveData:', err);
            notify(err.message || t('alert_error', 'Error'), 'error');
        } finally {
            if (el.btnSave) {
                el.btnSave.disabled = false;
                el.btnSave.innerHTML = `<i class="fas fa-save" aria-hidden="true"></i> ${t('save_button','Save')}`;
            }
        }
    }

    async function deleteItem(id) {
        if (!confirm(t('confirm_delete', 'Delete this record?'))) return;
        try {
            const result = await apiFetch(`${API}/${id}`, {
                method: 'DELETE',
                body:   JSON.stringify({ id }),
            });
            if (result.success) {
                notify(t('alert_deleted', 'Deleted successfully'), 'success');
                hideForm();
                loadData(state.page);
            } else {
                notify(result.message || t('alert_error', 'Error'), 'error');
            }
        } catch (e) {
            console.error('[TenantCategories] deleteItem:', e);
            notify(t('alert_error', 'Error'), 'error');
        }
    }

    async function toggleStatus(id, newStatus) {
        try {
            const result = await apiFetch(`${API}/${id}`, {
                method: 'PUT',
                body:   JSON.stringify({ is_active: parseInt(newStatus) }),
            });
            if (result.success) {
                notify(t('alert_updated', 'Updated successfully'), 'success');
                loadData(state.page);
            } else {
                notify(result.message || t('alert_error', 'Error'), 'error');
            }
        } catch (e) {
            console.error('[TenantCategories] toggleStatus:', e);
            notify(t('alert_error', 'Error'), 'error');
        }
    }

    // ════════════════════════════════════════════════════════
    // FILTERS
    // ════════════════════════════════════════════════════════
    function applyFilters() {
        state.filters = {};
        if (state.isSuperAdmin && el.filterTenantHidden?.value) {
            state.filters.tenant_id = el.filterTenantHidden.value;
        }
        if (el.filterCategoryHidden?.value) {
            state.filters.category_id = el.filterCategoryHidden.value;
        }
        if (state.isSuperAdmin && el.filterStatus?.value !== '') {
            state.filters.is_active = el.filterStatus.value;
        }
        loadData(1);
    }

    function resetFilters() {
        if (el.filterTenant)          el.filterTenant.value         = '';
        if (el.filterTenantHidden)    el.filterTenantHidden.value   = '';
        if (el.filterCategory)        el.filterCategory.value       = '';
        if (el.filterCategoryHidden)  el.filterCategoryHidden.value = '';
        if (el.filterStatus)          el.filterStatus.value         = '';
        state.filters = {};
        loadData(1);
    }

    // ════════════════════════════════════════════════════════
    // INIT
    // ════════════════════════════════════════════════════════
    async function init() {
        el = {
            formContainer:         document.getElementById('tenantCategoryFormContainer'),
            form:                  document.getElementById('tenantCategoryForm'),
            formTitle:             document.getElementById('formTitle'),
            formId:                document.getElementById('tenantCategoryId'),
            tenantDisplay:         document.getElementById('tenantCategoryTenantId'),
            tenantHidden:          document.getElementById('tenantCategoryTenantIdHidden'),
            categorySelect:        document.getElementById('tenantCategoryCategoryId'),
            sortOrder:             document.getElementById('tenantCategorySortOrder'),
            isActive:              document.getElementById('tenantCategoryIsActive'),
            btnSave:               document.getElementById('btnSaveTenantCategory'),
            btnCancel:             document.getElementById('btnCancelTenantCategoryForm'),
            btnClose:              document.getElementById('btnCloseTenantCategoryForm'),
            btnDelete:             document.getElementById('btnDeleteTenantCategory'),
            btnAdd:                document.getElementById('btnAddTenantCategory'),
            btnAddEmpty:           document.getElementById('btnAddTenantCategoryEmpty'),
            tableBody:             document.getElementById('tenantCategoryTableBody'),
            filterTenant:          document.getElementById('tenantCategoryFilterTenant'),
            filterTenantHidden:    document.getElementById('tenantCategoryFilterTenantHidden'),
            filterCategory:        document.getElementById('tenantCategoryFilterCategory'),
            filterCategoryHidden:  document.getElementById('tenantCategoryFilterCategoryHidden'),
            filterStatus:          document.getElementById('tenantCategoryFilterStatus'),
            btnApply:              document.getElementById('btnApplyTenantCategoryFilters'),
            btnReset:              document.getElementById('btnResetTenantCategoryFilters'),
            btnRetry:              document.getElementById('btnRetryTenantCategories'),
        };

        // ESC closes form
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            if (el.formContainer && el.formContainer.style.display !== 'none') hideForm();
        });

        if (el.form)         el.form.onsubmit     = saveData;
        if (el.btnAdd)       el.btnAdd.onclick     = () => showForm(false);
        if (el.btnAddEmpty)  el.btnAddEmpty.onclick = () => showForm(false);
        if (el.btnCancel)    el.btnCancel.onclick  = hideForm;
        if (el.btnClose)     el.btnClose.onclick   = hideForm;
        if (el.btnApply)     el.btnApply.onclick   = applyFilters;
        if (el.btnReset)     el.btnReset.onclick   = resetFilters;
        if (el.btnRetry)     el.btnRetry.onclick   = () => loadData(state.page);
        if (el.btnDelete) {
            el.btnDelete.onclick = () => {
                if (el.formId?.value) deleteItem(el.formId.value);
            };
        }

        // Datalist input handlers
        if (el.tenantDisplay) {
            el.tenantDisplay.addEventListener('input', function () {
                const id = getIdFromDatalist('tenantsList', this.value);
                if (el.tenantHidden) el.tenantHidden.value = id || '';
            });
        }
        if (el.filterTenant) {
            el.filterTenant.addEventListener('input', function () {
                const id = getIdFromDatalist('filterTenantsList', this.value);
                if (el.filterTenantHidden) el.filterTenantHidden.value = id || '';
            });
        }
        if (el.filterCategory) {
            el.filterCategory.addEventListener('input', function () {
                const id = getIdFromDatalist('filterCategoriesList', this.value);
                if (el.filterCategoryHidden) el.filterCategoryHidden.value = id || '';
            });
        }

        await loadDropdowns();
        await loadData();
    }

    // ════════════════════════════════════════════════════════
    // REGISTER
    // ════════════════════════════════════════════════════════
    window.TenantCategories = {
        init,
        load:         loadData,
        add:          () => showForm(false),
        edit:         editItem,
        remove:       deleteItem,
        toggleStatus,
    };
    window.page = { run: init };

    if (window.Admin?.page?.register) {
        window.Admin.page.register('tenant_categories', init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());