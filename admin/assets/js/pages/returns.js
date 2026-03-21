(function () {
    'use strict';

    const CONFIG = window.RETURNS_CONFIG || {};
    const PERMS  = window.PAGE_PERMISSIONS || {};

    const API = {
        returns: CONFIG.apiUrl       || '/api/returns',
        items:   CONFIG.itemsApi     || '/api/return_items',
        history: CONFIG.historyApi   || '/api/return_status_history',
        orders:  CONFIG.ordersApi    || '/api/orders',
        users:   CONFIG.usersApi     || '/api/users'
    };

    const state = {
        page: 1, perPage: CONFIG.itemsPerPage || 20, total: 0,
        returns: [], currentReturn: null,
        returnItems: [], returnHistory: [],
        filters: {}, permissions: PERMS,
        lang: CONFIG.lang || window.USER_LANGUAGE || 'en',
        csrfToken: window.APP_CONFIG?.CSRF_TOKEN || '',
        tenantId: CONFIG.tenantId || window.APP_CONFIG?.TENANT_ID || 1
    };

    let el = {};

    // Translation helper
    function t(key, fb) {
        if (window._admin && typeof window._admin.t === 'function') {
            const val = window._admin.t(key);
            if (val && val !== key) return val;
        }
        return fb !== undefined ? fb : key;
    }

    function esc(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    // API helper
    async function apiCall(url, opts) {
        const defaults = {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        };
        if (opts && opts.method && opts.method !== 'GET') {
            defaults.headers['X-CSRF-Token'] = state.csrfToken;
        }
        const config = Object.assign({}, defaults, opts || {});
        if (opts && opts.headers) {
            config.headers = Object.assign({}, defaults.headers, opts.headers);
        }
        const res = await fetch(url, config);
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) throw new Error(data.error || 'HTTP ' + res.status);
        return data;
    }

    // Load list
    async function loadReturns(page) {
        try {
            showLoading();
            state.page = page || 1;
            const params = new URLSearchParams({
                page: state.page,
                limit: state.perPage,
                tenant_id: state.tenantId,
                lang: state.lang
            });
            Object.keys(state.filters).forEach(function (k) {
                if (state.filters[k]) params.set(k, state.filters[k]);
            });
            const result = await apiCall(API.returns + '?' + params);
            if (result.success) {
                state.returns = result.data.items || result.data || [];
                state.total   = (result.data.meta && result.data.meta.total) || state.returns.length;
                renderTable(state.returns);
                updatePagination(state.total);
                showTable();
            } else {
                throw new Error(result.message);
            }
        } catch (err) {
            showError(err.message);
        }
    }

    function statusBadge(status) {
        return '<span class="badge badge-' + esc(status) + '">' + esc(t('status.' + status, status)) + '</span>';
    }

    function renderTable(items) {
        if (!el.tbody) return;
        if (!items.length) { showEmpty(); return; }
        el.tbody.innerHTML = items.map(function (r) {
            return '<tr data-id="' + r.id + '">' +
                '<td>#' + r.id + '</td>' +
                '<td><strong>' + esc(r.return_number || '-') + '</strong></td>' +
                '<td>' + esc(r.order_number || '-') + '</td>' +
                '<td>' + esc(r.user_email   || '-') + '</td>' +
                '<td>' + statusBadge(r.status) + '</td>' +
                '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc(r.reason || '-') + '</td>' +
                '<td>' + (r.requested_at ? new Date(r.requested_at).toLocaleDateString() : '-') + '</td>' +
                '<td>' +
                    '<div class="table-actions">' +
                        '<button class="btn btn-sm btn-secondary" onclick="Returns.edit(' + r.id + ')" title="' + t('form.edit_title', 'Edit') + '"><i class="fas fa-edit"></i></button>' +
                        (state.permissions.canDelete ? '<button class="btn btn-sm btn-danger" onclick="Returns.remove(' + r.id + ')" title="' + t('form.buttons.delete', 'Delete') + '"><i class="fas fa-trash"></i></button>' : '') +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('');
    }

    async function showForm(data) {
        state.currentReturn = data || null;
        state.returnItems   = [];
        state.returnHistory = [];

        if (el.form) el.form.reset();
        if (el.formContainer) el.formContainer.style.display = 'block';
        if (el.formContainer) el.formContainer.scrollIntoView({ behavior: 'smooth' });

        // Reset tabs
        el.formContainer && el.formContainer.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
        el.formContainer && el.formContainer.querySelectorAll('.tab-content').forEach(function (c) { c.style.display = 'none'; });
        const firstTab = el.formContainer && el.formContainer.querySelector('.tab-btn[data-tab="details"]');
        if (firstTab) firstTab.classList.add('active');
        const detailsPane = document.getElementById('ret-tab-details');
        if (detailsPane) detailsPane.style.display = 'block';

        if (data) {
            if (el.formTitle) el.formTitle.textContent = t('form.edit_title', 'Edit Return') + ' #' + data.id;
            if (el.formId) el.formId.value = data.id;
            if (el.returnNumber) el.returnNumber.value = data.return_number || '';
            if (el.status) el.status.value = data.status;
            if (el.reason) el.reason.value = data.reason || '';
            if (el.adminNotes) el.adminNotes.value = data.admin_notes || '';
            if (el.btnDelete) el.btnDelete.style.display = 'inline-flex';
            await loadReturnDetails(data.id);
        } else {
            if (el.formTitle) el.formTitle.textContent = t('form.add_title', 'New Return Request');
            if (el.formId) el.formId.value = '';
            if (el.btnDelete) el.btnDelete.style.display = 'none';
        }
    }

    function hideForm() {
        if (el.formContainer) el.formContainer.style.display = 'none';
        state.currentReturn = null;
    }

    async function loadReturnDetails(returnId) {
        // Items
        try {
            const res = await apiCall(API.items + '?return_id=' + returnId + '&tenant_id=' + state.tenantId);
            if (res.success) {
                state.returnItems = res.data.items || res.data || [];
                renderItems();
            }
        } catch (e) { /* silent */ }

        // History
        try {
            const res = await apiCall(API.history + '?return_id=' + returnId + '&tenant_id=' + state.tenantId + '&order_by=id&order_dir=ASC');
            if (res.success) {
                state.returnHistory = res.data.items || res.data || [];
                renderHistory();
            }
        } catch (e) { /* silent */ }
    }

    function renderItems() {
        if (!el.itemsList) return;
        if (!state.returnItems.length) {
            el.itemsList.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:20px">' + t('items.empty', 'No items') + '</p>';
            return;
        }
        el.itemsList.innerHTML =
            '<div class="items-table-wrapper"><table class="items-table">' +
            '<thead><tr>' +
            '<th>' + t('items.headers.product', 'Product') + '</th>' +
            '<th>' + t('items.headers.quantity', 'Qty') + '</th>' +
            '<th>' + t('items.headers.reason', 'Reason') + '</th>' +
            '<th>' + t('items.headers.refund_amount', 'Refund') + '</th>' +
            '</tr></thead><tbody>' +
            state.returnItems.map(function (item) {
                return '<tr>' +
                    '<td>#' + item.product_id + '</td>' +
                    '<td>' + esc(String(item.quantity)) + '</td>' +
                    '<td>' + esc(item.reason || '-') + '</td>' +
                    '<td>' + (item.refund_amount != null ? parseFloat(item.refund_amount).toFixed(2) : '-') + '</td>' +
                    '</tr>';
            }).join('') +
            '</tbody></table></div>';
    }

    function renderHistory() {
        if (!el.historyList) return;
        if (!state.returnHistory.length) {
            el.historyList.innerHTML = '<p style="color:var(--text-secondary);text-align:center;padding:20px">' + t('history.empty', 'No history') + '</p>';
            return;
        }
        el.historyList.innerHTML = '<div class="history-list">' +
            state.returnHistory.map(function (h) {
                return '<div class="history-item">' +
                    '<div class="history-item-content">' +
                    '<div>' + statusBadge(h.status) +
                    (h.changed_by ? ' &nbsp;<small style="color:var(--text-secondary)">by #' + h.changed_by + '</small>' : '') +
                    '</div>' +
                    (h.notes ? '<div style="margin-top:4px;font-size:0.85rem;color:var(--text-secondary)">' + esc(h.notes) + '</div>' : '') +
                    '</div>' +
                    '<div class="history-item-date">' + (h.created_at ? new Date(h.created_at).toLocaleString() : '') + '</div>' +
                    '</div>';
            }).join('') +
            '</div>';
    }

    async function saveReturn(e) {
        e.preventDefault();
        const formData = new FormData(el.form);
        const id = formData.get('id');
        const data = {
            tenant_id:   state.tenantId,
            status:      formData.get('status'),
            reason:      formData.get('reason') || null,
            admin_notes: formData.get('admin_notes') || null
        };
        if (id) data.id = id;

        try {
            const method = id ? 'PUT' : 'POST';
            const res = await apiCall(API.returns, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            if (res.success) {
                showNotification(id ? t('messages.updated', 'Updated') : t('messages.created', 'Created'), 'success');
                hideForm();
                loadReturns(state.page);
            } else {
                throw new Error(res.message);
            }
        } catch (err) {
            showNotification(err.message, 'error');
        }
    }

    async function deleteReturn(id) {
        if (!confirm(t('messages.confirm_delete', 'Are you sure?'))) return;
        try {
            const res = await apiCall(API.returns, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id, tenant_id: state.tenantId })
            });
            if (res.success) {
                showNotification(t('messages.deleted', 'Deleted'), 'success');
                hideForm();
                loadReturns(state.page);
            }
        } catch (err) {
            showNotification(err.message, 'error');
        }
    }

    // UI helpers
    function showLoading() {
        if (el.loading) el.loading.style.display = 'flex';
        if (el.container) el.container.style.display = 'none';
        if (el.empty) el.empty.style.display = 'none';
    }
    function showTable() {
        if (el.loading) el.loading.style.display = 'none';
        if (el.container) el.container.style.display = 'block';
        if (el.empty) el.empty.style.display = 'none';
    }
    function showEmpty() {
        if (el.loading) el.loading.style.display = 'none';
        if (el.container) el.container.style.display = 'none';
        if (el.empty) el.empty.style.display = 'flex';
    }
    function showError(msg) {
        if (el.loading) el.loading.style.display = 'none';
        if (el.container) el.container.style.display = 'none';
        if (el.empty) el.empty.style.display = 'none';
        console.error('[Returns]', msg);
    }
    function showNotification(msg, type) {
        if (window._admin && typeof window._admin.notify === 'function') {
            window._admin.notify(msg, type);
        } else {
            alert(msg);
        }
    }
    function updatePagination(total) {
        if (!el.pagination) return;
        const pages = Math.ceil(total / state.perPage);
        let html = '';
        for (let i = 1; i <= pages; i++) {
            html += '<button class="pagination-btn ' + (i === state.page ? 'active' : '') + '" onclick="Returns.load(' + i + ')">' + i + '</button>';
        }
        el.pagination.innerHTML = html;
        if (el.paginationInfo) {
            const start = ((state.page - 1) * state.perPage) + 1;
            const end   = Math.min(state.page * state.perPage, total);
            el.paginationInfo.textContent = start + '-' + end + ' / ' + total;
        }
    }

    // Init
    function init() {
        el = {
            container:     document.getElementById('ret-tableContainer'),
            loading:       document.getElementById('ret-tableLoading'),
            empty:         document.getElementById('ret-emptyState'),
            tbody:         document.getElementById('ret-tableBody'),
            pagination:    document.getElementById('ret-pagination'),
            paginationInfo:document.getElementById('ret-paginationInfo'),
            formContainer: document.getElementById('ret-formContainer'),
            form:          document.getElementById('ret-form'),
            formTitle:     document.getElementById('ret-formTitle'),
            formId:        document.getElementById('ret-formId'),
            returnNumber:  document.getElementById('ret-returnNumber'),
            status:        document.getElementById('ret-status'),
            reason:        document.getElementById('ret-reason'),
            adminNotes:    document.getElementById('ret-adminNotes'),
            btnDelete:     document.getElementById('ret-btnDelete'),
            itemsList:     document.getElementById('ret-itemsList'),
            historyList:   document.getElementById('ret-historyList')
        };

        // Bind events
        document.getElementById('ret-btnAdd')?.addEventListener('click', function () { showForm(); });
        document.getElementById('ret-btnCloseForm')?.addEventListener('click', hideForm);
        document.getElementById('ret-btnCancelForm')?.addEventListener('click', hideForm);
        if (el.form) el.form.addEventListener('submit', saveReturn);
        if (el.btnDelete) el.btnDelete.addEventListener('click', function () {
            var id = el.formId && el.formId.value ? parseInt(el.formId.value, 10) : null;
            if (id) deleteReturn(id);
        });

        document.getElementById('ret-btnApplyFilters')?.addEventListener('click', function () {
            state.filters = {
                search: (document.getElementById('ret-searchInput') || {}).value || '',
                status: (document.getElementById('ret-statusFilter') || {}).value || ''
            };
            loadReturns(1);
        });
        document.getElementById('ret-btnResetFilters')?.addEventListener('click', function () {
            state.filters = {};
            const si = document.getElementById('ret-searchInput');
            const sf = document.getElementById('ret-statusFilter');
            if (si) si.value = '';
            if (sf) sf.value = '';
            loadReturns(1);
        });

        // Tabs inside form
        if (el.formContainer) {
            el.formContainer.querySelectorAll('.tab-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    el.formContainer.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
                    el.formContainer.querySelectorAll('.tab-content').forEach(function (c) { c.style.display = 'none'; });
                    btn.classList.add('active');
                    const pane = document.getElementById('ret-tab-' + btn.dataset.tab);
                    if (pane) pane.style.display = 'block';
                });
            });
        }

        loadReturns(1);
    }

    window.Returns = {
        init:   init,
        load:   loadReturns,
        edit:   async function (id) {
            try {
                const res = await apiCall(API.returns + '?id=' + id + '&tenant_id=' + state.tenantId);
                if (res.success) await showForm(res.data);
            } catch (e) { console.error(e); }
        },
        remove: deleteReturn
    };

    // Initialization is driven by the fragment's inline script which waits
    // for the admin:i18n:applied event so translations are ready first.
    // Do NOT self-invoke init() here.
})();