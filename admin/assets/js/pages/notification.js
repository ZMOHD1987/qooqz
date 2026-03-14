/**
 * /admin/assets/js/pages/notification.js
 * Notification System – 5-tab admin module
 * Tabs: types | list | channels | counters | deliveries
 */
(function (window) {
    'use strict';

    /* ──────────────────────────────────────────
       HELPERS
    ────────────────────────────────────────── */
    const cfg = () => window.NOTIFICATIONS_CONFIG || {};
    const perm = () => window.PAGE_PERMISSIONS || {};
    const t = (key) => {
        const tr = window.NOTIF_TRANSLATIONS || {};
        return key.split('.').reduce((o, k) => (o && o[k] !== undefined) ? o[k] : null, tr) ?? key;
    };

    let _csrfToken = () => window.APP_CONFIG?.CSRF_TOKEN || window.CSRF_TOKEN || '';

    async function apiFetch(url, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': _csrfToken(),
            },
            credentials: 'same-origin',
        };
        const res = await fetch(url, Object.assign({}, defaults, options, {
            headers: Object.assign({}, defaults.headers, options.headers || {}),
        }));
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.message || json.error || `HTTP ${res.status}`);
        return json;
    }

    function showToast(msg, type = 'success') {
        if (window.AdminFramework?.toast) {
            window.AdminFramework.toast(msg, type);
            return;
        }
        if (window.showToast) {
            window.showToast(msg, type);
            return;
        }
        // fallback
        const el = document.createElement('div');
        el.textContent = msg;
        el.style.cssText = `
            position:fixed; top:20px; right:20px; z-index:99999;
            padding:12px 20px; border-radius:8px; color:#fff; font-size:0.9rem;
            background:${type === 'success' ? '#22c55e' : '#ef4444'};
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            animation: slideInRight 0.3s ease;
        `;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    }

    function confirmDialog(msg) {
        return window.confirm(msg);
    }

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function truncate(str, len = 60) {
        if (!str) return '';
        return str.length > len ? str.substring(0, len) + '…' : str;
    }

    function dateFmt(val) {
        if (!val) return '—';
        try {
            return new Date(val).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        } catch { return val; }
    }

    /* ──────────────────────────────────────────
       TAB DEFINITIONS
    ────────────────────────────────────────── */
    const TABS = {
        types: {
            apiKey: 'types',
            titleKey: 'types.title',
            addKey: 'types.add_new',
            emptyKey: 'types.empty_title',
            emptyMsgKey: 'types.empty_message',
            addFirstKey: 'types.add_first',
            showStatus: true,
            showPriority: false,
            showDeliveryStatus: false,
            showRecipientType: false,
            showTenant: false,
        },
        list: {
            apiKey: 'list',
            titleKey: 'list.title',
            addKey: 'list.add_new',
            emptyKey: 'list.empty_title',
            emptyMsgKey: 'list.empty_message',
            addFirstKey: 'list.add_first',
            showStatus: false,
            showPriority: true,
            showDeliveryStatus: false,
            showRecipientType: false,
            showTenant: true,
        },
        channels: {
            apiKey: 'channels',
            titleKey: 'channels.title',
            addKey: 'channels.add_new',
            emptyKey: 'channels.empty_title',
            emptyMsgKey: 'channels.empty_message',
            addFirstKey: 'channels.add_first',
            showStatus: true,
            showPriority: false,
            showDeliveryStatus: false,
            showRecipientType: false,
            showTenant: false,
        },
        counters: {
            apiKey: 'counters',
            titleKey: 'counters.title',
            addKey: 'counters.add_new',
            emptyKey: 'counters.empty_title',
            emptyMsgKey: 'counters.empty_message',
            addFirstKey: 'counters.add_first',
            showStatus: false,
            showPriority: false,
            showDeliveryStatus: false,
            showRecipientType: true,
            showTenant: true,
        },
        deliveries: {
            apiKey: 'deliveries',
            titleKey: 'deliveries.title',
            addKey: 'deliveries.add_new',
            emptyKey: 'deliveries.empty_title',
            emptyMsgKey: 'deliveries.empty_message',
            addFirstKey: 'deliveries.add_first',
            showStatus: false,
            showPriority: false,
            showDeliveryStatus: true,
            showRecipientType: false,
            showTenant: false,
        },
    };

    /* ──────────────────────────────────────────
       STATE
    ────────────────────────────────────────── */
    let state = {
        currentTab: 'types',
        currentPage: 1,
        filters: {},
        editingId: null,
        notifTypes: [],     // cache
        channels: [],       // cache
    };

    /* ──────────────────────────────────────────
       DOM REFERENCES
    ────────────────────────────────────────── */
    let $;
    function getEl(id) { return document.getElementById(id); }
    function getEls(sel, ctx) { return (ctx || document).querySelectorAll(sel); }

    /* ──────────────────────────────────────────
       TABLE HEADERS PER TAB
    ────────────────────────────────────────── */
    function getHeaders(tab) {
        const H = tab => ({ id: t('table.headers.id'), ...tab });
        const act = t('table.headers.actions');
        switch (tab) {
            case 'types':
                return [
                    t('table.headers.id'), t('table.headers.code'), t('table.headers.name'),
                    t('table.headers.description'), t('table.headers.status'),
                    t('table.headers.default_template'), t('table.headers.actions')
                ];
            case 'list':
                return [
                    t('table.headers.id'), t('table.headers.tenant'), t('table.headers.sender'),
                    t('table.headers.entity'), t('table.headers.title'),
                    t('table.headers.message'), t('table.headers.priority'), t('table.headers.type'),
                    t('table.headers.sent_at'), t('table.headers.expires_at'), act
                ];
            case 'channels':
                return [
                    t('table.headers.id'), t('table.headers.code'), t('table.headers.name'),
                    t('table.headers.status'), t('table.headers.created_at'), act
                ];
            case 'counters':
                return [
                    t('table.headers.id'), t('table.headers.tenant'), t('table.headers.recipient_type'),
                    t('table.headers.recipient_id'), t('table.headers.unread_count'),
                    t('table.headers.updated_at'), act
                ];
            case 'deliveries':
                return [
                    t('table.headers.id'), t('table.headers.tenant'), t('table.headers.notification'),
                    t('table.headers.channel'), t('table.headers.delivery_status'), t('table.headers.attempts'),
                    t('table.headers.sent_at'), t('table.headers.error'), act
                ];
        }
        return [];
    }

    /* ──────────────────────────────────────────
       RENDER ROW PER TAB
    ────────────────────────────────────────── */
    function renderRow(tab, item) {
        const editBtn = perm().canEdit ?
            `<button class="btn btn-sm btn-secondary notif-edit-btn" data-id="${item.id}" title="${t('table.actions.edit')}">
                <i class="fas fa-edit"></i>
             </button>` : '';
        const deleteBtn = perm().canDelete ?
            `<button class="btn btn-sm btn-danger notif-delete-btn" data-id="${item.id}" title="${t('table.actions.delete')}">
                <i class="fas fa-trash"></i>
             </button>` : '';
        const actions = `<div class="action-btns">${editBtn}${deleteBtn}</div>`;

        switch (tab) {
            case 'types': {
                const status = (item.is_active == 1)
                    ? `<span class="badge badge-success">${t('table.status.active')}</span>`
                    : `<span class="badge badge-danger">${t('table.status.inactive')}</span>`;
                return `<tr data-id="${item.id}">
                    <td>${esc(item.id)}</td>
                    <td><code style="background:rgba(59,130,246,0.1);padding:2px 6px;border-radius:4px;font-size:0.8rem;">${esc(item.code)}</code></td>
                    <td>${esc(item.name)}</td>
                    <td><span class="text-truncate" title="${esc(item.description)}">${esc(truncate(item.description))}</span></td>
                    <td>${status}</td>
                    <td><span style="color:var(--text-secondary,#94a3b8);font-size:0.8rem;">${item.default_template ? '✓' : '—'}</span></td>
                    <td>${actions}</td>
                </tr>`;
            }
            case 'list': {
                const p = item.priority || 'normal';
                const priorityBadge = `<span class="badge badge-${p}">${t('priority_labels.' + p) || p}</span>`;
                const tName = item.tenant_name ? `${esc(item.tenant_name)} <small>(${esc(item.tenant_id)})</small>` : esc(item.tenant_id);
                const sName = item.sender_name ? `${esc(item.sender_name)} <small>(${esc(item.sender_entity_id)})</small>` : (item.sender_entity_id || '—');
                const rName = item.recipient_name ? `${esc(item.recipient_name)} <small>(${esc(item.entity_id)})</small>` : (item.entity_id || '—');

                return `<tr data-id="${item.id}">
                    <td>${esc(item.id)}</td>
                    <td>${tName}</td>
                    <td>${sName}</td>
                    <td>${rName}</td>
                    <td><span class="text-truncate" title="${esc(item.title)}">${esc(truncate(item.title, 40))}</span></td>
                    <td><span class="text-truncate" title="${esc(item.message)}">${esc(truncate(item.message, 50))}</span></td>
                    <td>${priorityBadge}</td>
                    <td>${esc(item.type_name || item.notification_type_id || '—')}</td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${dateFmt(item.sent_at)}</td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${dateFmt(item.expires_at)}</td>
                    <td>${actions}</td>
                </tr>`;
            }
            case 'channels': {
                const status = (item.is_active == 1)
                    ? `<span class="badge badge-success">${t('table.status.active')}</span>`
                    : `<span class="badge badge-danger">${t('table.status.inactive')}</span>`;
                return `<tr data-id="${item.id}">
                    <td>${esc(item.id)}</td>
                    <td><code style="background:rgba(59,130,246,0.1);padding:2px 6px;border-radius:4px;font-size:0.8rem;">${esc(item.code)}</code></td>
                    <td>${esc(item.name)}</td>
                    <td>${status}</td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${dateFmt(item.created_at)}</td>
                    <td>${actions}</td>
                </tr>`;
            }
            case 'counters': {
                const rt = item.recipient_type || '';
                const rtLabel = t('recipient_type_labels.' + rt) || rt;
                const tName = item.tenant_name ? `${esc(item.tenant_name)} <small>(${esc(item.tenant_id)})</small>` : esc(item.tenant_id);
                const rName = item.recipient_name ? `${esc(item.recipient_name)} <small>(${esc(item.recipient_id)})</small>` : esc(item.recipient_id);
                const extraBtns = `
                    <button class="btn btn-sm btn-secondary notif-increment-btn" data-id="${item.id}" title="${t('table.actions.increment')}">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button class="btn btn-sm btn-outline notif-reset-btn" data-id="${item.id}" title="${t('table.actions.reset')}">
                        <i class="fas fa-redo"></i>
                    </button>`;
                return `<tr data-id="${item.id}">
                    <td>${esc(item.id)}</td>
                    <td>${tName}</td>
                    <td><span class="badge badge-info">${esc(rtLabel)}</span></td>
                    <td>${rName}</td>
                    <td><strong style="color:${item.unread_count > 0 ? '#f59e0b' : '#94a3b8'};">${esc(item.unread_count ?? 0)}</strong></td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${dateFmt(item.updated_at)}</td>
                    <td><div class="action-btns">${editBtn}${extraBtns}${deleteBtn}</div></td>
                </tr>`;
            }
            case 'deliveries': {
                const ds = item.delivery_status || 'pending';
                const dsBadge = `<span class="badge badge-${ds}">${t('delivery_status_labels.' + ds) || ds}</span>`;
                const tName = item.tenant_name ? `${esc(item.tenant_name)} <small>(${esc(item.tenant_id)})</small>` : esc(item.tenant_id);
                return `<tr data-id="${item.id}">
                    <td>${esc(item.id)}</td>
                    <td>${tName}</td>
                    <td><span class="text-truncate" title="${esc(item.notification_title)}">${esc(truncate(item.notification_title, 35))}</span></td>
                    <td>${esc(item.channel_name || item.channel_id)}</td>
                    <td>${dsBadge}</td>
                    <td>${esc(item.attempts ?? 0)}</td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">${dateFmt(item.sent_at)}</td>
                    <td style="font-size:0.8rem;color:var(--text-secondary,#94a3b8);">
                        ${item.error_message ? `<span title="${esc(item.error_message)}" style="color:#ef4444;">${esc(truncate(item.error_message, 35))}</span>` : '—'}
                    </td>
                    <td>${actions}</td>
                </tr>`;
            }
        }
        return '';
    }

    /* ──────────────────────────────────────────
       LOAD DATA
    ────────────────────────────────────────── */
    async function loadData() {
        const tab = state.currentTab;
        const tabDef = TABS[tab];
        const apiUrl = cfg().api[tabDef.apiKey];
        const page = state.currentPage;
        const limit = cfg().itemsPerPage || 25;
        const offset = (page - 1) * limit;

        // Show loading
        setStates({ loading: true, table: false, empty: false, error: false });

        // Build query params
        const params = new URLSearchParams({
            page, limit,
            order_by: 'id', order_dir: 'DESC',
        });

        // Common filters
        const f = state.filters;
        if (f.search) params.set('search', f.search);
        if (f.is_active !== undefined && f.is_active !== '') params.set('is_active', f.is_active);
        if (f.priority) params.set('priority', f.priority);
        if (f.delivery_status) params.set('delivery_status', f.delivery_status);
        if (f.recipient_type) params.set('recipient_type', f.recipient_type);
        if (f.tenant_id) params.set('tenant_id', f.tenant_id);

        try {
            const json = await apiFetch(`${apiUrl}?${params}`);
            const items = json.data?.items || json.items || [];
            const meta = json.data?.meta || json.meta || {};
            const total = meta.total ?? items.length;

            if (items.length === 0) {
                setStates({ loading: false, table: false, empty: true, error: false });
                updateEmptyState(tab);
                getEl('resultsCount').style.display = 'none';
                return;
            }

            // Render headers
            const thead = getEl('tableHead');
            thead.innerHTML = getHeaders(tab).map(h => `<th>${h}</th>`).join('');

            // Render rows
            const tbody = getEl('tableBody');
            tbody.innerHTML = items.map(item => renderRow(tab, item)).join('');

            // Results count
            const countEl = getEl('resultsCount');
            countEl.style.display = 'block';
            getEl('resultsCountText').textContent = `${meta.from ?? 1}–${meta.to ?? items.length} / ${total}`;

            // Pagination
            renderPagination(meta);

            setStates({ loading: false, table: true, empty: false, error: false });

            // Bind row actions
            bindRowActions();

        } catch (err) {
            console.error('[Notifications] Load error:', err);
            setStates({ loading: false, table: false, empty: false, error: true });
            const em = getEl('errorMessage');
            if (em) em.textContent = err.message;
        }
    }

    function updateEmptyState(tab) {
        const tabDef = TABS[tab];
        const et = getEl('emptyTitle');
        const em = getEl('emptyMessage');
        const ea = getEl('emptyAddLabel');
        if (et) et.textContent = t(tabDef.emptyKey);
        if (em) em.textContent = t(tabDef.emptyMsgKey);
        if (ea) ea.textContent = t(tabDef.addFirstKey);
    }

    function setStates({ loading, table, empty, error }) {
        const show = (id, v) => { const el = getEl(id); if (el) el.style.display = v ? '' : 'none'; };
        show('tableLoading', loading);
        show('tableContainer', table);
        show('emptyState', empty);
        show('errorState', error);
    }

    /* ──────────────────────────────────────────
       PAGINATION
    ────────────────────────────────────────── */
    function renderPagination(meta) {
        const pag = getEl('pagination');
        const inf = getEl('paginationInfo');
        if (!pag) return;

        const total = meta.total ?? 0;
        const totalPages = meta.total_pages ?? 1;
        const from = meta.from ?? 0;
        const to = meta.to ?? 0;

        if (inf) inf.textContent = `${from}–${to} / ${total}`;

        if (totalPages <= 1) { pag.innerHTML = ''; return; }

        let html = '';
        if (state.currentPage > 1) {
            html += `<button data-page="${state.currentPage - 1}">‹</button>`;
        }

        const range = 2;
        let start = Math.max(1, state.currentPage - range);
        let end = Math.min(totalPages, state.currentPage + range);
        if (start > 1) html += `<button data-page="1">1</button>${start > 2 ? '<button disabled>…</button>' : ''}`;
        for (let i = start; i <= end; i++) {
            html += `<button data-page="${i}" class="${i === state.currentPage ? 'active' : ''}">${i}</button>`;
        }
        if (end < totalPages) {
            html += `${end < totalPages - 1 ? '<button disabled>…</button>' : ''}<button data-page="${totalPages}">${totalPages}</button>`;
        }
        if (state.currentPage < totalPages) {
            html += `<button data-page="${state.currentPage + 1}">›</button>`;
        }

        pag.innerHTML = html;
        pag.querySelectorAll('button[data-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.currentPage = parseInt(btn.dataset.page, 10);
                loadData();
            });
        });
    }

    /* ──────────────────────────────────────────
       FORM
    ────────────────────────────────────────── */
    function showForm(editData = null) {
        const tab = state.currentTab;
        state.editingId = editData ? editData.id : null;

        const formContainer = getEl('notifFormContainer');
        const formTitle = getEl('notifFormTitle');
        const formId = getEl('formId');
        const formTabInput = getEl('formTab');
        const deleteBtn = getEl('btnDeleteRecord');

        // Show correct form section
        getEls('.notif-form-section').forEach(sec => {
            sec.style.display = sec.dataset.formTab === tab ? '' : 'none';
        });

        if (formTitle) formTitle.textContent = editData ? t('form.edit_title') : t('form.add_title');
        if (formId) formId.value = editData ? editData.id : '';
        if (formTabInput) formTabInput.value = tab;
        if (deleteBtn) deleteBtn.style.display = editData && perm().canDelete ? '' : 'none';

        // Reset form
        const form = getEl('notifForm');
        if (form) {
            // Collect all inputs in current section
            const section = form.querySelector(`.notif-form-section[data-form-tab="${tab}"]`);
            if (section) {
                section.querySelectorAll('input, textarea, select').forEach(el => {
                    if (el.name === 'csrf_token' || el.name === 'id' || el.name === '_tab') return;
                    el.value = '';
                });
            }
        }

        // Populate edit data
        if (editData) {
            Object.keys(editData).forEach(key => {
                const el = form.querySelector(`[name="${key}"]`);
                if (el && editData[key] !== null && editData[key] !== undefined) {
                    if (key === 'expires_at' || key === 'sent_at') {
                        // datetime-local format
                        try {
                            const d = new Date(editData[key]);
                            if (!isNaN(d)) {
                                el.value = d.toISOString().slice(0, 16);
                            }
                        } catch { el.value = editData[key]; }
                    } else {
                        el.value = editData[key];
                    }
                }
            });
        }

        formContainer.style.display = '';
        formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Trigger lookup hints for any prefilled IDs (edit mode)
        setTimeout(triggerLookupForPrefilled, 50);
    }

    function hideForm() {
        const fc = getEl('notifFormContainer');
        if (fc) fc.style.display = 'none';
        state.editingId = null;
        const form = getEl('notifForm');
        if (form) form.reset();
        // Clear all lookup hints
        document.querySelectorAll('.id-lookup-hint').forEach(h => {
            h.className = 'id-lookup-hint';
            h.textContent = '';
        });
    }

    /* ──────────────────────────────────────────
       COLLECT FORM DATA
    ────────────────────────────────────────── */
    function collectFormData() {
        const tab = state.currentTab;
        const form = getEl('notifForm');
        const section = form.querySelector(`.notif-form-section[data-form-tab="${tab}"]`);
        const data = {};

        if (state.editingId) {
            data.id = state.editingId;
        }

        if (section) {
            section.querySelectorAll('input, textarea, select').forEach(el => {
                if (!el.name || el.name === 'csrf_token' || el.name === '_tab') return;
                const v = el.value.trim();
                data[el.name] = v === '' ? null : v;
            });
        }

        return data;
    }

    /* ──────────────────────────────────────────
       SAVE
    ────────────────────────────────────────── */
    async function saveRecord(data) {
        const tab = state.currentTab;
        const apiUrl = cfg().api[TABS[tab].apiKey];
        const isEdit = !!data.id;
        const method = isEdit ? 'PUT' : 'POST';

        const submitBtn = getEl('btnSubmitForm');
        if (submitBtn) {
            submitBtn.disabled = true;
            const span = submitBtn.querySelector('span');
            if (span) span.textContent = t(isEdit ? 'form.buttons.updating' : 'form.buttons.saving');
        }

        try {
            await apiFetch(apiUrl, {
                method,
                body: JSON.stringify(data),
            });
            showToast(t(isEdit ? 'messages.success.updated' : 'messages.success.created'), 'success');
            hideForm();
            await loadData();
        } catch (err) {
            showToast(err.message || t('messages.error.save_failed'), 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                const span = submitBtn.querySelector('span');
                if (span) span.textContent = t('form.buttons.save');
            }
        }
    }

    /* ──────────────────────────────────────────
       DELETE
    ────────────────────────────────────────── */
    async function deleteRecord(id) {
        if (!confirmDialog(t('table.actions.confirm_delete'))) return;
        const tab = state.currentTab;
        const apiUrl = cfg().api[TABS[tab].apiKey];
        try {
            await apiFetch(apiUrl, {
                method: 'DELETE',
                body: JSON.stringify({ id }),
            });
            showToast(t('messages.success.deleted'), 'success');
            await loadData();
        } catch (err) {
            showToast(err.message || t('messages.error.delete_failed'), 'error');
        }
    }

    /* ──────────────────────────────────────────
       COUNTER SPECIAL ACTIONS
    ────────────────────────────────────────── */
    async function incrementCounter(id) {
        try {
            await apiFetch(cfg().api.counters + '/increment', {
                method: 'POST',
                body: JSON.stringify({ id, amount: 1 }),
            });
            showToast('Counter incremented', 'success');
            await loadData();
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    async function resetCounter(id) {
        if (!confirmDialog('Reset unread count to 0?')) return;
        try {
            await apiFetch(cfg().api.counters + '/reset', {
                method: 'POST',
                body: JSON.stringify({ id }),
            });
            showToast('Counter reset', 'success');
            await loadData();
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    /* ──────────────────────────────────────────
       ROW ACTIONS
    ────────────────────────────────────────── */
    function bindRowActions() {
        getEls('.notif-edit-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.dataset.id, 10);
                const tab = state.currentTab;
                const apiUrl = cfg().api[TABS[tab].apiKey];
                try {
                    const json = await apiFetch(`${apiUrl}?id=${id}`);
                    const item = json.data || json;
                    await populateDropdowns();
                    showForm(item);
                } catch (err) {
                    showToast(err.message, 'error');
                }
            });
        });

        getEls('.notif-delete-btn').forEach(btn => {
            btn.addEventListener('click', () => deleteRecord(parseInt(btn.dataset.id, 10)));
        });

        getEls('.notif-increment-btn').forEach(btn => {
            btn.addEventListener('click', () => incrementCounter(parseInt(btn.dataset.id, 10)));
        });

        getEls('.notif-reset-btn').forEach(btn => {
            btn.addEventListener('click', () => resetCounter(parseInt(btn.dataset.id, 10)));
        });
    }

    /* ──────────────────────────────────────────
       POPULATE DROPDOWNS (types select, channel select)
    ────────────────────────────────────────── */
    async function populateDropdowns() {
        // Notification types
        if (!state.notifTypes.length) {
            try {
                const json = await apiFetch(cfg().api.types + '?limit=1000&is_active=1');
                state.notifTypes = json.data?.items || json.items || [];
            } catch { state.notifTypes = []; }
        }
        const tSelect = getEl('fNotifTypeId');
        if (tSelect) {
            const current = tSelect.value;
            tSelect.innerHTML = `<option value="">-- ${t('form.fields.notification_type_id.label')} --</option>`;
            state.notifTypes.forEach(ty => {
                const opt = document.createElement('option');
                opt.value = ty.id;
                opt.textContent = `${ty.name} (${ty.code})`;
                if (String(ty.id) === String(current)) opt.selected = true;
                tSelect.appendChild(opt);
            });
        }

        // Channels
        if (!state.channels.length) {
            try {
                const json = await apiFetch(cfg().api.channels + '?limit=1000&is_active=1');
                state.channels = json.data?.items || json.items || [];
            } catch { state.channels = []; }
        }
        const chSelect = getEl('fChannelId');
        if (chSelect) {
            const current = chSelect.value;
            chSelect.innerHTML = `<option value="">-- ${t('form.fields.channel_id.label')} --</option>`;
            state.channels.forEach(ch => {
                const opt = document.createElement('option');
                opt.value = ch.id;
                opt.textContent = `${ch.name} (${ch.code})`;
                if (String(ch.id) === String(current)) opt.selected = true;
                chSelect.appendChild(opt);
            });
        }
    }

    /* ──────────────────────────────────────────
       ID LOOKUP HINTS
    ────────────────────────────────────────── */
    const _lookupTimers = {};

    function setHint(inputEl, state, text) {
        const hint = document.querySelector(`.id-lookup-hint[data-for="${inputEl.id}"]`);
        if (!hint) return;
        hint.className = 'id-lookup-hint' + (state ? ' ' + state : '');
        hint.textContent = text || '';
    }

    async function doLookup(inputEl, lookupType) {
        const id = parseInt(inputEl.value, 10);
        if (!id || id <= 0) {
            setHint(inputEl, '', '');
            return;
        }

        setHint(inputEl, 'loading', '');

        const apiBase = cfg().api;
        const tenantId = cfg().tenantId || window.APP_CONFIG?.TENANT_ID || 1;

        try {
            let name = null;

            if (lookupType === 'tenant') {
                // GET /api/tenants/{id}  – path-segment style
                const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/tenants/${id}`, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }).then(r => r.json()).catch(() => null);
                const row = json?.data || json;
                name = row?.name || null;
            }

            else if (lookupType === 'entity') {
                // GET /api/entities?id=X&tenant_id=X
                const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/entities?id=${id}&tenant_id=${tenantId}`, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }).then(r => r.json()).catch(() => null);
                const row = json?.data || json;
                name = row?.store_name || null;
            }

            else if (lookupType === 'notification') {
                // Use our own notification API
                const json = await fetch(`${apiBase.list}?id=${id}`, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                }).then(r => r.json()).catch(() => null);
                const row = json?.data || json;
                name = row?.title || null;
            }

            else if (lookupType === 'recipient') {
                // Depends on the recipient_type select value
                const rtSelect = document.getElementById('fRecipientType');
                const rType = rtSelect?.value || 'user';

                if (rType === 'user') {
                    const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/user?id=${id}`, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    }).then(r => r.json()).catch(() => null);
                    const row = json?.data || json;
                    name = row?.username || row?.email || null;
                } else if (rType === 'entity') {
                    const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/entities?id=${id}&tenant_id=${tenantId}`, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    }).then(r => r.json()).catch(() => null);
                    const row = json?.data || json;
                    name = row?.store_name || null;
                } else if (rType === 'tenant') {
                    const json = await fetch(`${apiBase.types.replace('/notification_types', '')}/tenants/${id}`, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    }).then(r => r.json()).catch(() => null);
                    const row = json?.data || json;
                    name = row?.name || null;
                }
            }

            if (name) {
                setHint(inputEl, 'found', name);
            } else {
                setHint(inputEl, 'not-found', 'Not found');
            }
        } catch (e) {
            setHint(inputEl, 'not-found', 'Lookup error');
        }
    }

    function initLookupHints() {
        const form = document.getElementById('notifForm');
        if (!form) return;

        form.querySelectorAll('[data-lookup]').forEach(input => {
            const lookupType = input.dataset.lookup;

            // Fire on input with 600ms debounce
            input.addEventListener('input', () => {
                clearTimeout(_lookupTimers[input.id]);
                setHint(input, '', '');
                if (!input.value) return;
                _lookupTimers[input.id] = setTimeout(() => doLookup(input, lookupType), 600);
            });

            // Also fire on blur immediately
            input.addEventListener('blur', () => {
                clearTimeout(_lookupTimers[input.id]);
                if (input.value) doLookup(input, lookupType);
            });
        });

        // Re-trigger recipient lookup when recipient_type changes
        const rtSelect = document.getElementById('fRecipientType');
        if (rtSelect) {
            rtSelect.addEventListener('change', () => {
                const recipientInput = document.getElementById('fRecipientId');
                if (recipientInput?.value) doLookup(recipientInput, 'recipient');
            });
        }
    }

    /* Helper to fire lookup hints for all prefilled ID inputs (edit mode) */
    function triggerLookupForPrefilled() {
        const form = document.getElementById('notifForm');
        if (!form) return;
        form.querySelectorAll('[data-lookup]').forEach(input => {
            if (input.value && parseInt(input.value, 10) > 0) {
                doLookup(input, input.dataset.lookup);
            }
        });
    }

    function switchTab(tab) {
        state.currentTab = tab;
        state.currentPage = 1;
        state.filters = {};

        // Update tab buttons
        getEls('.notif-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });

        // Update Add button label
        const addLabel = getEl('btnAddLabel');
        if (addLabel) addLabel.textContent = t(TABS[tab].addKey);

        // Update filters visibility
        const tabDef = TABS[tab];
        const show = (id, v) => { const el = getEl(id); if (el) el.style.display = v ? '' : 'none'; };
        show('filterStatusGroup', tabDef.showStatus);
        show('filterPriorityGroup', tabDef.showPriority);
        show('filterDeliveryStatusGroup', tabDef.showDeliveryStatus);
        show('filterRecipientTypeGroup', tabDef.showRecipientType);
        show('filterTenantGroup', tabDef.showTenant && perm().isSuperAdmin);

        // Reset filter inputs
        const si = getEl('searchInput');
        if (si) si.value = '';
        const st = getEl('statusFilter');
        if (st) st.value = '';
        const pr = getEl('priorityFilter');
        if (pr) pr.value = '';
        const ds = getEl('deliveryStatusFilter');
        if (ds) ds.value = '';
        const rt = getEl('recipientTypeFilter');
        if (rt) rt.value = '';

        hideForm();
        loadData();
    }

    /* ──────────────────────────────────────────
       EVENTS
    ────────────────────────────────────────── */
    function bindEvents() {
        // Tab clicks
        getEls('.notif-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });

        // Add button
        const addBtn = getEl('btnAddRecord');
        if (addBtn) {
            addBtn.addEventListener('click', async () => {
                await populateDropdowns();
                showForm(null);
            });
        }

        // Close / cancel form
        const closeBtn = getEl('btnCloseForm');
        const cancelBtn = getEl('btnCancelForm');
        if (closeBtn) closeBtn.addEventListener('click', hideForm);
        if (cancelBtn) cancelBtn.addEventListener('click', hideForm);

        // Delete (from form)
        const deleteBtn = getEl('btnDeleteRecord');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => {
                if (state.editingId) deleteRecord(state.editingId);
            });
        }

        // Form submit
        const form = getEl('notifForm');
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const data = collectFormData();
                await saveRecord(data);
            });
        }

        // Filters
        const applyBtn = getEl('btnApplyFilters');
        const resetBtn = getEl('btnResetFilters');
        if (applyBtn) {
            applyBtn.addEventListener('click', () => {
                state.filters = {};
                state.currentPage = 1;
                const si = getEl('searchInput');
                if (si?.value) state.filters.search = si.value.trim();
                const sf = getEl('statusFilter');
                if (sf?.value !== '') state.filters.is_active = sf.value;
                const pf = getEl('priorityFilter');
                if (pf?.value) state.filters.priority = pf.value;
                const df = getEl('deliveryStatusFilter');
                if (df?.value) state.filters.delivery_status = df.value;
                const rf = getEl('recipientTypeFilter');
                if (rf?.value) state.filters.recipient_type = rf.value;
                const tf = getEl('tenantFilter');
                if (tf?.value) state.filters.tenant_id = tf.value;
                loadData();
            });
        }
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                state.filters = {};
                state.currentPage = 1;
                ['searchInput', 'statusFilter', 'priorityFilter', 'deliveryStatusFilter', 'recipientTypeFilter'].forEach(id => {
                    const el = getEl(id);
                    if (el) el.value = '';
                });
                loadData();
            });
        }

        // Retry button
        const retryBtn = getEl('btnRetry');
        if (retryBtn) retryBtn.addEventListener('click', loadData);

        // Enter key in search
        const si = getEl('searchInput');
        if (si) {
            si.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') applyBtn?.click();
            });
        }
    }

    /* ──────────────────────────────────────────
       PUBLIC API
    ────────────────────────────────────────── */
    const Notifications = {
        async init() {
            console.log('[Notifications] Initializing...');
            bindEvents();
            initLookupHints();
            switchTab('types');
        },
        add() {
            populateDropdowns().then(() => showForm(null));
        },
        reload() {
            loadData();
        },
    };

    window.Notifications = Notifications;
    console.log('[Notifications] Module registered.');

})(window);
