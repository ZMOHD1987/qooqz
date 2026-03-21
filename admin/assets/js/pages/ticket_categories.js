(function () {
    'use strict';

    const CONFIG = window.TC_CONFIG || {};
    const PERMS  = window.PAGE_PERMISSIONS || {};

    const API = {
        categories: CONFIG.apiUrl || '/api/ticket_categories'
    };

    const state = {
        page: 1, perPage: CONFIG.itemsPerPage || 25, total: 0,
        items: [], allCategories: [],
        currentItem: null,
        filters: {},
        lang: window.USER_LANGUAGE || 'en',
        csrfToken: window.APP_CONFIG?.CSRF_TOKEN || '',
        tenantId: CONFIG.tenantId || window.APP_CONFIG?.TENANT_ID || 1
    };

    let el = {};

    // ──────────────────────────────────────────────
    // Translation helper
    // ──────────────────────────────────────────────
    function t(key, fb = '') {
        if (window._admin && typeof window._admin.t === 'function') {
            const val = window._admin.t(key);
            if (val && val !== key) return val;
        }
        return fb || key;
    }

    function esc(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    // ──────────────────────────────────────────────
    // API Helper
    // ──────────────────────────────────────────────
    async function apiCall(url, opts = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        };
        if (opts.method && opts.method !== 'GET') {
            defaults.headers['X-CSRF-Token'] = state.csrfToken;
        }
        const config = { ...defaults, ...opts };
        if (config.headers && opts.headers) {
            config.headers = { ...defaults.headers, ...opts.headers };
        }
        const res  = await fetch(url, config);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
        return data;
    }

    // ──────────────────────────────────────────────
    // Load Categories
    // ──────────────────────────────────────────────
    async function loadCategories(page = 1) {
        try {
            showLoading();
            state.page = page;
            const params = new URLSearchParams({
                page, limit: state.perPage,
                tenant_id: state.tenantId,
                lang: state.lang
            });
            if (state.filters.search)    params.set('search', state.filters.search);
            if (state.filters.is_active !== undefined && state.filters.is_active !== '') {
                params.set('is_active', state.filters.is_active);
            }

            const result = await apiCall(`${API.categories}?${params}`);
            if (result.success) {
                state.items = result.data.items || result.data || [];
                state.total = result.data.meta?.total || result.data.total || state.items.length;
                state.allCategories = state.items;
                renderTable(state.items);
                updatePagination(state.total);
                showTable();
            } else {
                throw new Error(result.message || 'Load failed');
            }
        } catch (err) {
            showError(err.message);
        }
    }

    // ──────────────────────────────────────────────
    // Load parent select options
    // ──────────────────────────────────────────────
    async function loadParentOptions(excludeId = null) {
        try {
            const params = new URLSearchParams({
                tenant_id: state.tenantId, lang: state.lang, limit: 200
            });
            const result = await apiCall(`${API.categories}?${params}`);
            const items  = (result.success) ? (result.data.items || result.data || []) : [];
            if (!el.tcParent) return;
            el.tcParent.innerHTML = `<option value="" data-i18n="form.fields.parent.none">${t('form.fields.parent.none', '— None —')}</option>`;
            items.forEach(item => {
                if (excludeId && item.id === excludeId) return;
                const opt = document.createElement('option');
                opt.value       = item.id;
                opt.textContent = item.name || `#${item.id}`;
                el.tcParent.appendChild(opt);
            });
        } catch (e) {
            console.warn('Failed to load parent options', e);
        }
    }

    // ──────────────────────────────────────────────
    // Render Table
    // ──────────────────────────────────────────────
    function renderTable(items) {
        if (!el.tbody) return;
        if (!items.length) { showEmpty(); return; }

        el.tbody.innerHTML = items.map(item => {
            const statusBadge = item.is_active
                ? `<span class="badge badge-active">${t('table.active', 'Active')}</span>`
                : `<span class="badge badge-inactive">${t('table.inactive', 'Inactive')}</span>`;

            const actions = `
                <div class="table-actions">
                    ${PERMS.canEdit   ? `<button class="btn btn-sm btn-secondary tc-edit"   data-id="${item.id}" title="${t('table.actions.edit','Edit')}"><i class="fas fa-edit"></i></button>` : ''}
                    ${PERMS.canDelete ? `<button class="btn btn-sm btn-danger    tc-delete" data-id="${item.id}" title="${t('table.actions.delete','Delete')}"><i class="fas fa-trash"></i></button>` : ''}
                </div>`;

            return `
            <tr data-id="${item.id}">
                <td>#${item.id}</td>
                <td><strong>${esc(item.name || '')}</strong></td>
                <td>${esc(item.description || '—')}</td>
                <td>${esc(item.parent_name || '—')}</td>
                <td>${esc(String(item.priority_level ?? 3))}</td>
                <td>${statusBadge}</td>
                <td>${actions}</td>
            </tr>`;
        }).join('');
    }

    // ──────────────────────────────────────────────
    // Show / hide states
    // ──────────────────────────────────────────────
    function showLoading() {
        if (el.loading)  el.loading.style.display  = 'flex';
        if (el.table)    el.table.style.display     = 'none';
        if (el.empty)    el.empty.style.display     = 'none';
    }
    function showTable() {
        if (el.loading)  el.loading.style.display  = 'none';
        if (el.table)    el.table.style.display     = 'block';
        if (el.empty)    el.empty.style.display     = 'none';
    }
    function showEmpty() {
        if (el.loading)  el.loading.style.display  = 'none';
        if (el.table)    el.table.style.display     = 'none';
        if (el.empty)    el.empty.style.display     = 'block';
    }
    function showError(msg) {
        if (el.loading)  el.loading.style.display  = 'none';
        if (el.table)    el.table.style.display     = 'none';
        if (el.empty)    el.empty.style.display     = 'none';
        notify(msg || t('messages.error.load_failed', 'Failed to load'), 'error');
    }

    // ──────────────────────────────────────────────
    // Notification helper
    // ──────────────────────────────────────────────
    function notify(msg, type = 'success') {
        if (window.AdminFramework && typeof window.AdminFramework.notify === 'function') {
            window.AdminFramework.notify(msg, type);
            return;
        }
        const container = document.getElementById('notificationsContainer') || document.body;
        const div = document.createElement('div');
        div.className = `notification notification-${type}`;
        div.textContent = msg;
        div.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;padding:12px 20px;border-radius:6px;color:#fff;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:fadeIn .3s ease;';
        div.style.background = type === 'error' ? '#ef4444' : type === 'warning' ? '#f59e0b' : '#10b981';
        container.appendChild(div);
        setTimeout(() => div.remove(), 4000);
    }

    // ──────────────────────────────────────────────
    // Pagination
    // ──────────────────────────────────────────────
    function updatePagination(total) {
        const pages    = Math.ceil(total / state.perPage);
        const start    = total ? (state.page - 1) * state.perPage + 1 : 0;
        const end      = Math.min(state.page * state.perPage, total);

        if (el.paginationInfo) el.paginationInfo.textContent = `${start}-${end} of ${total}`;
        if (!el.pagination) return;

        el.pagination.innerHTML = '';
        for (let i = 1; i <= pages; i++) {
            const btn = document.createElement('button');
            btn.className  = `btn btn-sm ${i === state.page ? 'btn-primary' : 'btn-outline'}`;
            btn.textContent = String(i);
            btn.addEventListener('click', () => loadCategories(i));
            el.pagination.appendChild(btn);
        }
    }

    // ──────────────────────────────────────────────
    // Form helpers
    // ──────────────────────────────────────────────
    function openForm(data = null) {
        state.currentItem = data;
        if (el.form)          el.form.reset();
        if (el.formContainer) el.formContainer.style.display = 'block';
        if (el.formContainer) el.formContainer.scrollIntoView({ behavior: 'smooth' });
        if (el.formTitle)     el.formTitle.textContent = data
            ? `${t('form.edit_title', 'Edit Category')} #${data.id}`
            : t('form.add_title', 'New Ticket Category');

        if (el.btnDelete) el.btnDelete.style.display = data ? 'inline-flex' : 'none';

        loadParentOptions(data?.id || null);

        if (data) {
            if (el.formId)       el.formId.value       = data.id;
            if (el.tcName)       el.tcName.value        = data.name        || '';
            if (el.tcDescription) el.tcDescription.value = data.description || '';
            if (el.tcPriority)   el.tcPriority.value    = String(data.priority_level ?? 3);
            if (el.tcStatus)     el.tcStatus.value      = String(data.is_active ?? 1);
            if (el.tcParent && data.parent_id)
                setTimeout(() => { if (el.tcParent) el.tcParent.value = data.parent_id; }, 300);

            // Render existing translations
            renderTranslationRows(data.translations || []);
        } else {
            renderTranslationRows([]);
        }
    }

    function closeForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
        state.currentItem = null;
    }

    // ──────────────────────────────────────────────
    // Translations sub-form
    // ──────────────────────────────────────────────
    function renderTranslationRows(translations) {
        const container = el.transContainer;
        if (!container) return;
        container.innerHTML = '';
        // Always show English and Arabic rows
        const langs = ['en', 'ar'];
        langs.forEach(langCode => {
            const existing = translations.find(tr => tr.language_code === langCode) || {};
            addTranslationRow(langCode, existing.name || '', existing.description || '');
        });
    }

    function addTranslationRow(langCode = '', name = '', description = '') {
        const container = el.transContainer;
        if (!container) return;
        const langLabel = langCode === 'ar'
            ? t('form.fields.language.ar', 'Arabic')
            : t('form.fields.language.en', 'English');

        const row = document.createElement('div');
        row.className = 'translation-row';
        row.style.cssText = 'display:grid; grid-template-columns:100px 1fr 1fr auto; gap:8px; align-items:center; margin-bottom:8px; padding:8px; background:var(--bg-secondary,#f8fafc); border-radius:6px;';
        row.innerHTML = `
            <span style="font-weight:600; font-size:0.85rem; color:var(--text-secondary)">${esc(langLabel)}</span>
            <input type="text" name="translations[${langCode}][name]"
                   class="form-control" placeholder="${t('form.fields.name.placeholder','Name')}"
                   value="${esc(name)}" data-lang="${langCode}" data-field="name">
            <input type="text" name="translations[${langCode}][description]"
                   class="form-control" placeholder="${t('form.fields.description.placeholder','Description')}"
                   value="${esc(description)}" data-lang="${langCode}" data-field="description">
            <input type="hidden" name="translations[${langCode}][language_code]" value="${langCode}">
        `;
        container.appendChild(row);
    }

    function collectTranslations() {
        const container = el.transContainer;
        if (!container) return [];
        const rows  = container.querySelectorAll('.translation-row');
        const trans = [];
        rows.forEach(row => {
            const nameEl = row.querySelector('[data-field="name"]');
            const descEl = row.querySelector('[data-field="description"]');
            const lang   = nameEl?.dataset.lang || 'en';
            if (nameEl?.value?.trim()) {
                trans.push({
                    language_code: lang,
                    name:          nameEl.value.trim(),
                    description:   descEl?.value?.trim() || null
                });
            }
        });
        return trans;
    }

    // ──────────────────────────────────────────────
    // Save (Create / Update)
    // ──────────────────────────────────────────────
    async function saveCategory(e) {
        e.preventDefault();

        const translations = collectTranslations();
        const enTrans = translations.find(tr => tr.language_code === 'en');
        if (!enTrans?.name) {
            notify(t('messages.error.save_failed', 'Please enter an English category name.'), 'error');
            return;
        }

        const payload = {
            id:             state.currentItem?.id || null,
            tenant_id:      state.tenantId,
            parent_id:      el.tcParent?.value   ? parseInt(el.tcParent.value, 10) : null,
            priority_level: parseInt(el.tcPriority?.value || '3', 10),
            is_active:      parseInt(el.tcStatus?.value   || '1', 10),
            name:           enTrans.name,
            description:    enTrans.description,
            language_code:  'en',
            translations
        };

        try {
            const isEdit   = !!payload.id;
            const method   = isEdit ? 'PUT' : 'POST';
            const url      = `${API.categories}?tenant_id=${state.tenantId}`;
            const result   = await apiCall(url, {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            if (result.success) {
                notify(t(isEdit ? 'messages.updated' : 'messages.created', isEdit ? 'Updated' : 'Created'), 'success');
                closeForm();
                loadCategories(state.page);
            } else {
                throw new Error(result.message || t('messages.error.save_failed', 'Save failed'));
            }
        } catch (err) {
            notify(err.message, 'error');
        }
    }

    // ──────────────────────────────────────────────
    // Delete
    // ──────────────────────────────────────────────
    async function deleteCategory(id) {
        if (!confirm(t('messages.confirm_delete', 'Are you sure?'))) return;
        try {
            const result = await apiCall(
                `${API.categories}?tenant_id=${state.tenantId}`,
                {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, tenant_id: state.tenantId })
                }
            );
            if (result.success) {
                notify(t('messages.deleted', 'Category deleted'), 'success');
                closeForm();
                loadCategories(state.page);
            } else {
                throw new Error(result.message || t('messages.error.delete_failed', 'Delete failed'));
            }
        } catch (err) {
            notify(err.message, 'error');
        }
    }

    // ──────────────────────────────────────────────
    // Load single for editing
    // ──────────────────────────────────────────────
    async function editCategory(id) {
        try {
            const result = await apiCall(
                `${API.categories}?tenant_id=${state.tenantId}&id=${id}&lang=${state.lang}`
            );
            if (result.success) {
                openForm(result.data);
            } else {
                throw new Error(result.message || 'Not found');
            }
        } catch (err) {
            notify(err.message, 'error');
        }
    }

    // ──────────────────────────────────────────────
    // Event Wiring
    // ──────────────────────────────────────────────
    function bindEvents() {
        // Add button
        const btnAdd = document.getElementById('btnAddTicketCategory');
        if (btnAdd) btnAdd.addEventListener('click', () => openForm());

        const btnAddFirst = document.getElementById('btnAddFirstTCCategory');
        if (btnAddFirst) btnAddFirst.addEventListener('click', () => openForm());

        // Close / cancel form
        const btnClose  = document.getElementById('btnCloseTCForm');
        const btnCancel = document.getElementById('btnCancelTCForm');
        if (btnClose)  btnClose.addEventListener('click',  closeForm);
        if (btnCancel) btnCancel.addEventListener('click', closeForm);

        // Form submit
        if (el.form) el.form.addEventListener('submit', saveCategory);

        // Delete button inside form
        if (el.btnDelete) {
            el.btnDelete.addEventListener('click', () => {
                if (state.currentItem?.id) deleteCategory(state.currentItem.id);
            });
        }

        // Table row actions (event delegation)
        if (el.tbody) {
            el.tbody.addEventListener('click', e => {
                const editBtn   = e.target.closest('.tc-edit');
                const deleteBtn = e.target.closest('.tc-delete');
                if (editBtn)   editCategory(parseInt(editBtn.dataset.id, 10));
                if (deleteBtn) deleteCategory(parseInt(deleteBtn.dataset.id, 10));
            });
        }

        // Filters
        const btnApply = document.getElementById('btnTCApplyFilters');
        const btnReset = document.getElementById('btnTCResetFilters');
        if (btnApply) btnApply.addEventListener('click', applyFilters);
        if (btnReset) btnReset.addEventListener('click', resetFilters);

        // Search on enter
        if (el.search) {
            el.search.addEventListener('keydown', e => {
                if (e.key === 'Enter') applyFilters();
            });
        }

        // Add translation row button
        const btnAddTrans = document.getElementById('btnAddTranslation');
        if (btnAddTrans) btnAddTrans.addEventListener('click', () => addTranslationRow());
    }

    function applyFilters() {
        state.filters = {
            search:    el.search?.value?.trim() || '',
            is_active: el.statusFilter?.value ?? ''
        };
        loadCategories(1);
    }

    function resetFilters() {
        state.filters = {};
        if (el.search)       el.search.value       = '';
        if (el.statusFilter) el.statusFilter.value = '';
        loadCategories(1);
    }

    // ──────────────────────────────────────────────
    // Init
    // ──────────────────────────────────────────────
    function init() {
        el = {
            formContainer:   document.getElementById('tcFormContainer'),
            form:            document.getElementById('ticketCategoryForm'),
            formTitle:       document.getElementById('tcFormTitle'),
            formId:          document.getElementById('tcFormId'),
            tcName:          document.getElementById('tcName'),
            tcDescription:   document.getElementById('tcDescription'),
            tcParent:        document.getElementById('tcParent'),
            tcPriority:      document.getElementById('tcPriority'),
            tcStatus:        document.getElementById('tcStatus'),
            transContainer:  document.getElementById('tcTranslationsContainer'),
            btnDelete:       document.getElementById('btnDeleteTC'),
            loading:         document.getElementById('tcTableLoading'),
            table:           document.getElementById('tcTableContainer'),
            empty:           document.getElementById('tcEmptyState'),
            tbody:           document.getElementById('tcTableBody'),
            paginationInfo:  document.getElementById('tcPaginationInfo'),
            pagination:      document.getElementById('tcPagination'),
            search:          document.getElementById('tcSearch'),
            statusFilter:    document.getElementById('tcStatusFilter')
        };

        bindEvents();
        loadCategories(1);
    }

    // ──────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────
    window.TicketCategories = {
        init,
        reload: () => loadCategories(state.page)
    };

})();
