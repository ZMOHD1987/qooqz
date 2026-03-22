(function () {
    'use strict';

    const CONFIG = window.TICKETS_CONFIG || {};
    const PERMS  = window.PAGE_PERMISSIONS || {};

    const API = {
        tickets   : CONFIG.apiUrl        || '/api/support_tickets',
        categories: CONFIG.categoriesApi || '/api/ticket_categories',
        messages  : CONFIG.messagesApi   || '/api/ticket_messages',
        history   : CONFIG.historyApi    || '/api/ticket_status_history',
        users     : CONFIG.usersApi      || '/api/users',
        orders    : CONFIG.ordersApi     || '/api/orders',
        entities  : CONFIG.entitiesApi   || '/api/entities'
    };

    const state = {
        page          : 1,
        perPage       : CONFIG.itemsPerPage || 20,
        total         : 0,
        tickets       : [],
        categories    : [],
        users         : [],
        currentTicket : null,
        messages      : [],
        history       : [],
        filters       : {},
        permissions   : PERMS,
        lang          : CONFIG.lang || window.USER_LANGUAGE || 'en',
        csrfToken     : window.APP_CONFIG?.CSRF_TOKEN || '',
        tenantId      : CONFIG.tenantId || window.APP_CONFIG?.TENANT_ID || 1
    };

    let el = {};

    // ─────────────────────────────────────────────────────────
    // t() — Translation helper
    //
    // Resolution order:
    //   1. window._admin.t()       (admin framework)
    //   2. window.TRANSLATIONS     (flat JSON — no "strings" wrapper)
    //   3. fallback string
    //
    // JSON keys are FLAT:
    //   t('tickets.title')        → TRANSLATIONS.tickets.title
    //   t('status.open')          → TRANSLATIONS.status.open
    //   t('form.fields.user.select') → TRANSLATIONS.form.fields.user.select
    // ─────────────────────────────────────────────────────────
    function t(key, fb) {
        const fallback = fb !== undefined ? fb : key;

        // 1) Admin framework
        if (window._admin && typeof window._admin.t === 'function') {
            const val = window._admin.t(key);
            if (val && val !== key) return val;
        }

        // 2) window.TRANSLATIONS — dot-notation traversal
        if (window.TRANSLATIONS) {
            const parts = key.split('.');
            let cur = window.TRANSLATIONS;
            for (const part of parts) {
                if (cur !== null && typeof cur === 'object' &&
                    Object.prototype.hasOwnProperty.call(cur, part)) {
                    cur = cur[part];
                } else {
                    cur = null;
                    break;
                }
            }
            if (cur && typeof cur === 'string') return cur;
        }

        return fallback;
    }

    // ─────────────────────────────────────────────────────────
    // HTML-escape helper
    // ─────────────────────────────────────────────────────────
    function esc(text) {
        if (text === null || text === undefined) return '';
        const d = document.createElement('div');
        d.textContent = String(text);
        return d.innerHTML;
    }

    // ─────────────────────────────────────────────────────────
    // API helper
    // ─────────────────────────────────────────────────────────
    async function apiCall(url, opts = {}) {
        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
        if (opts.method && opts.method !== 'GET') {
            headers['X-CSRF-Token'] = state.csrfToken;
        }
        if (opts.headers) Object.assign(headers, opts.headers);

        const res  = await fetch(url, { credentials: 'same-origin', ...opts, headers });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
        return data;
    }

    // ─────────────────────────────────────────────────────────
    // Load tickets list
    // ─────────────────────────────────────────────────────────
    async function loadTickets(page = 1) {
        try {
            showLoading();
            state.page = page;

            const params = new URLSearchParams({
                page,
                limit     : state.perPage,
                tenant_id : state.tenantId,
                lang      : state.lang
            });
            Object.entries(state.filters).forEach(([k, v]) => { if (v) params.set(k, v); });

            const result = await apiCall(`${API.tickets}?${params}`);
            if (!result.success) throw new Error(result.message);

            state.tickets = result.data.items || result.data || [];
            state.total   = result.data.meta?.total ?? state.tickets.length;
            renderTable(state.tickets);
            updatePagination(state.total);
            state.tickets.length ? showTable() : showEmpty();

        } catch (err) {
            showError(err.message);
        }
    }

    // ─────────────────────────────────────────────────────────
    // Load dropdowns (categories, users)
    // ─────────────────────────────────────────────────────────
    async function loadDropdowns() {
        // Categories
        try {
            const res = await apiCall(
                `${API.categories}?tenant_id=${state.tenantId}&lang=${state.lang}`
            );
            if (res.success) {
                state.categories = res.data.items || res.data || [];
                populateSelect(
                    el.category, state.categories, 'id', 'name',
                    t('form.fields.category.select', 'Select category')
                );
            }
        } catch (e) { console.warn('[Tickets] categories:', e); }

        // Users
        try {
            const res = await apiCall(`${API.users}?limit=200&tenant_id=${state.tenantId}`);
            if (res.success) {
                state.users = res.data.items || res.data || [];
                populateSelect(
                    el.user, state.users, 'id', 'email',
                    t('form.fields.user.select', 'Select customer')
                );
                populateSelect(
                    el.assigned, state.users, 'id', 'email',
                    t('form.fields.assigned_to.select', 'Select agent'),
                    true  // include Unassigned option
                );
            }
        } catch (e) { console.warn('[Tickets] users:', e); }

        // Empty orders / entities until a user is selected
        populateSelect(el.order,  [], 'id', 'order_number',
            t('form.fields.order.select',  'Select order (optional)'));
        populateSelect(el.entity, [], 'id', 'store_name',
            t('form.fields.entity.select', 'Select entity'));
    }

    // ─────────────────────────────────────────────────────────
    // Load orders + entities for a specific user
    // ─────────────────────────────────────────────────────────
    async function loadUserOrdersAndEntities(userId) {
        if (!userId) {
            populateSelect(el.order,  [], 'id', 'order_number',
                t('form.fields.order.select',  'Select order (optional)'));
            populateSelect(el.entity, [], 'id', 'store_name',
                t('form.fields.entity.select', 'Select entity'));
            return;
        }

        try {
            const res = await apiCall(
                `${API.orders}?user_id=${userId}&tenant_id=${state.tenantId}&limit=100&lang=${state.lang}`
            );
            if (res.success) {
                const orders = res.data.items || res.data || [];
                populateSelect(el.order, orders, 'id', 'order_number',
                    t('form.fields.order.select', 'Select order (optional)'));
            }
        } catch (e) { console.warn('[Tickets] orders:', e); }

        try {
            const res = await apiCall(
                `${API.entities}?user_id=${userId}&tenant_id=${state.tenantId}&limit=100&lang=${state.lang}`
            );
            if (res.success) {
                const entities = res.data.items || res.data || [];
                populateSelect(el.entity, entities, 'id', 'store_name',
                    t('form.fields.entity.select', 'Select entity'));
                if (entities.length === 1 && el.entity) el.entity.value = entities[0].id;
            }
        } catch (e) { console.warn('[Tickets] entities:', e); }
    }

    // ─────────────────────────────────────────────────────────
    // Populate <select> element
    // ─────────────────────────────────────────────────────────
    function populateSelect(sel, items, valKey, txtKey, placeholder, includeUnassigned = false) {
        if (!sel) return;
        sel.innerHTML = '';

        if (placeholder) {
            const opt = document.createElement('option');
            opt.value       = '';
            opt.textContent = placeholder;
            sel.appendChild(opt);
        }

        if (includeUnassigned) {
            const opt = document.createElement('option');
            opt.value       = '0';
            opt.textContent = t('form.fields.assigned_to.unassigned', 'Unassigned');
            sel.appendChild(opt);
        }

        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value       = item[valKey];
            opt.textContent = item[txtKey] || `ID: ${item[valKey]}`;
            sel.appendChild(opt);
        });
    }

    // ─────────────────────────────────────────────────────────
    // Render tickets table
    // ⚠ Loop variable is "ticket" — NOT "t" — to avoid shadowing t()
    // ─────────────────────────────────────────────────────────
    function renderTable(items) {
        if (!el.tbody) return;
        if (!items.length) { showEmpty(); return; }

        el.tbody.innerHTML = items.map(ticket => {
            const statusClass =
                ticket.status === 'open'   ? 'badge-active'   :
                ticket.status === 'closed' ? 'badge-inactive' : 'badge-secondary';

            const priorityClass =
                ticket.priority === 'urgent' ? 'badge-danger'  :
                ticket.priority === 'high'   ? 'badge-warning' : '';

            const updatedDate   = ticket.updated_at
                ? new Date(ticket.updated_at).toLocaleDateString(state.lang)
                : '-';
            const statusLabel   = t('status.'   + ticket.status,   ticket.status);
            const priorityLabel = t('priority.' + ticket.priority, ticket.priority);
            const guestLabel    = t('table.guest', 'Guest');
            const editTitle     = t('table.actions.edit',   'Edit');
            const deleteTitle   = t('table.actions.delete', 'Delete');

            return `
            <tr data-id="${ticket.id}">
                <td>#${esc(ticket.id)}</td>
                <td>
                    <strong>${esc(ticket.subject)}</strong><br>
                    <small style="color:var(--text-secondary)">${esc(ticket.ticket_number || '')}</small>
                </td>
                <td>${esc(ticket.user_email || guestLabel)}</td>
                <td>${esc(ticket.category_name || '-')}</td>
                <td><span class="badge ${priorityClass}">${esc(priorityLabel)}</span></td>
                <td><span class="badge ${statusClass}">${esc(statusLabel)}</span></td>
                <td>${esc(updatedDate)}</td>
                <td>
                    <div class="table-actions">
                        <button class="btn btn-sm btn-secondary"
                                onclick="Tickets.edit(${ticket.id})"
                                title="${esc(editTitle)}">
                            <i class="fas fa-edit"></i>
                        </button>
                        ${state.permissions.canDelete
                            ? `<button class="btn btn-sm btn-danger"
                                       onclick="Tickets.remove(${ticket.id})"
                                       title="${esc(deleteTitle)}">
                                   <i class="fas fa-trash"></i>
                               </button>`
                            : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    // ─────────────────────────────────────────────────────────
    // Show / hide form
    // ─────────────────────────────────────────────────────────
    async function showForm(data = null) {
        state.currentTicket = data;
        state.messages      = [];
        state.history       = [];

        el.form.reset();
        el.formContainer.style.display = 'block';
        el.formContainer.scrollIntoView({ behavior: 'smooth' });

        // Reset to Details tab
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
        document.querySelector('.tab-btn[data-tab="details"]')?.classList.add('active');
        const detailsPane = document.getElementById('tab-details');
        if (detailsPane) detailsPane.style.display = 'block';

        if (data) {
            // ── Edit mode ──
            el.formTitle.textContent = `${t('form.edit_title', 'Edit Ticket')} #${data.id}`;
            el.formId.value          = data.id;
            el.subject.value         = data.subject      || '';
            el.description.value     = data.description  || '';
            el.status.value          = data.status       || 'open';
            el.priority.value        = data.priority     || 'normal';

            if (data.category_id && el.category) el.category.value = data.category_id;
            if (data.assigned_to  && el.assigned) el.assigned.value  = data.assigned_to;

            if (data.user_id && el.user) {
                el.user.value = data.user_id;
                await loadUserOrdersAndEntities(data.user_id);
                if (data.order_id  && el.order)  el.order.value  = data.order_id;
                if (data.entity_id && el.entity) el.entity.value = data.entity_id;
            }

            if (el.btnDelete) el.btnDelete.style.display = 'inline-flex';
            loadTicketData(data.id);
        } else {
            // ── Create mode ──
            el.formTitle.textContent = t('form.add_title', 'New Support Ticket');
            el.formId.value          = '';
            if (el.btnDelete) el.btnDelete.style.display = 'none';
            populateSelect(el.order,  [], 'id', 'order_number',
                t('form.fields.order.select',  'Select order (optional)'));
            populateSelect(el.entity, [], 'id', 'store_name',
                t('form.fields.entity.select', 'Select entity'));
        }
    }

    function hideForm() {
        el.formContainer.style.display = 'none';
        state.currentTicket = null;
    }

    // ─────────────────────────────────────────────────────────
    // Load messages + status history for a ticket
    // ─────────────────────────────────────────────────────────
    async function loadTicketData(id) {
        try {
            const res = await apiCall(
                `${API.messages}?ticket_id=${id}&tenant_id=${state.tenantId}&lang=${state.lang}`
            );
            if (res.success) {
                state.messages = res.data.items || res.data || [];
                renderMessages();
            }
        } catch (e) { console.warn('[Tickets] messages:', e); }

        try {
            const res = await apiCall(
                `${API.history}?ticket_id=${id}&tenant_id=${state.tenantId}&lang=${state.lang}`
            );
            if (res.success) {
                state.history = res.data.items || res.data || [];
                renderHistory();
            }
        } catch (e) { console.warn('[Tickets] history:', e); }
    }

    // ─────────────────────────────────────────────────────────
    // Render messages list
    // ─────────────────────────────────────────────────────────
    function renderMessages() {
        if (!el.messagesList) return;

        if (!state.messages.length) {
            el.messagesList.innerHTML =
                `<p style="color:var(--text-secondary);text-align:center;padding:20px">
                    ${t('messages.empty', 'No messages yet.')}
                 </p>`;
            return;
        }

        el.messagesList.innerHTML = state.messages.map(msg => `
            <div class="message-item ${msg.is_internal ? 'is-internal' : ''}"
                 style="margin-bottom:15px;padding:12px;border-radius:8px;
                        background:${msg.is_internal ? 'rgba(245,158,11,0.06)' : 'rgba(255,255,255,0.03)'};
                        border-left:4px solid ${msg.is_internal ? '#f59e0b' : 'var(--primary-color)'};">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:0.85rem;">
                    <strong>${esc(msg.sender_email || t('messages.system', 'System'))}</strong>
                    <span style="color:var(--text-secondary)">
                        ${msg.is_internal
                            ? `<em style="color:#f59e0b;margin-inline-end:6px">
                                   ${t('messages.internal_note', 'Internal Note')}
                               </em>`
                            : ''}
                        ${new Date(msg.created_at).toLocaleString(state.lang)}
                    </span>
                </div>
                <div style="color:var(--text-primary);white-space:pre-wrap">${esc(msg.message)}</div>
            </div>
        `).join('');
    }

    // ─────────────────────────────────────────────────────────
    // Render status history
    // ─────────────────────────────────────────────────────────
    function renderHistory() {
        if (!el.historyList) return;

        if (!state.history.length) {
            el.historyList.innerHTML =
                `<p style="color:var(--text-secondary);text-align:center;padding:20px">
                    ${t('history.empty', 'No history yet.')}
                 </p>`;
            return;
        }

        el.historyList.innerHTML = state.history.map(h => `
            <div style="display:flex;align-items:center;gap:8px;padding:10px;
                        border-bottom:1px solid var(--border-color);">
                <span class="badge badge-secondary">
                    ${esc(t('status.' + (h.old_status || 'new'), h.old_status || 'New'))}
                </span>
                <i class="fas fa-arrow-right" style="color:var(--text-secondary)"></i>
                <span class="badge badge-active">
                    ${esc(t('status.' + h.new_status, h.new_status))}
                </span>
                <span style="margin-inline-start:auto;font-size:0.8rem;color:var(--text-secondary)">
                    ${new Date(h.created_at).toLocaleString(state.lang)}
                </span>
            </div>
        `).join('');
    }

    // ─────────────────────────────────────────────────────────
    // Save ticket (create / update)
    // ─────────────────────────────────────────────────────────
    async function saveTicket(e) {
        e.preventDefault();
        const fd = new FormData(el.form);
        const id = fd.get('id');

        const payload = {
            tenant_id  : state.tenantId,
            subject    : fd.get('subject'),
            description: fd.get('description'),
            category_id: fd.get('category_id')  || null,
            user_id    : fd.get('user_id')       || null,
            order_id   : fd.get('order_id')      || null,
            entity_id  : fd.get('entity_id')     || null,
            status     : fd.get('status'),
            priority   : fd.get('priority'),
            assigned_to: fd.get('assigned_to')   || null
        };
        if (id) payload.id = id;

        try {
            const res = await apiCall(API.tickets, {
                method : id ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body   : JSON.stringify(payload)
            });
            if (!res.success) throw new Error(res.message);

            notify(
                id ? t('notifications.updated', 'Ticket updated successfully')
                   : t('notifications.created', 'Ticket created successfully'),
                'success'
            );
            hideForm();
            loadTickets(state.page);

        } catch (err) {
            notify(err.message || t('errors.save_failed', 'Failed to save ticket'), 'error');
        }
    }

    // ─────────────────────────────────────────────────────────
    // Send reply / internal note
    // ─────────────────────────────────────────────────────────
    async function sendReply() {
        if (!state.currentTicket) return;
        const message = el.replyText?.value?.trim();
        if (!message) return;

        try {
            const res = await apiCall(API.messages, {
                method : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body   : JSON.stringify({
                    tenant_id      : state.tenantId,
                    ticket_id      : state.currentTicket.id,
                    sender_user_id : window.APP_CONFIG?.USER_ID || null,
                    message,
                    is_internal    : el.replyInternal?.checked ? 1 : 0
                })
            });
            if (!res.success) throw new Error(res.message);

            el.replyText.value       = '';
            el.replyInternal.checked = false;
            notify(t('notifications.reply_sent', 'Reply sent successfully'), 'success');
            await loadTicketData(state.currentTicket.id);

        } catch (err) {
            notify(err.message, 'error');
        }
    }

    // ─────────────────────────────────────────────────────────
    // Delete ticket
    // ─────────────────────────────────────────────────────────
    async function deleteTicket(id) {
        if (!id) return;
        if (!confirm(t('confirm.delete', 'Are you sure you want to delete this ticket?'))) return;

        try {
            const res = await apiCall(API.tickets, {
                method : 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body   : JSON.stringify({ id, tenant_id: state.tenantId })
            });
            if (!res.success) throw new Error(res.message);

            notify(t('notifications.deleted', 'Ticket deleted'), 'success');
            hideForm();
            loadTickets(state.page);

        } catch (err) {
            notify(err.message, 'error');
        }
    }

    // ─────────────────────────────────────────────────────────
    // Pagination
    // ─────────────────────────────────────────────────────────
    function updatePagination(total) {
        if (!el.pagination || !el.paginationInfo) return;
        const pages = Math.ceil(total / state.perPage);
        let html = '';
        for (let i = 1; i <= pages; i++) {
            html += `<button class="pagination-btn${i === state.page ? ' active' : ''}"
                             onclick="Tickets.load(${i})">${i}</button>`;
        }
        el.pagination.innerHTML       = html;
        const from                    = total ? ((state.page - 1) * state.perPage) + 1 : 0;
        const to                      = Math.min(state.page * state.perPage, total);
        el.paginationInfo.textContent = `${from}-${to} ${t('pagination.of', 'of')} ${total}`;
    }

    // ─────────────────────────────────────────────────────────
    // UI state helpers
    // ─────────────────────────────────────────────────────────
    function showLoading() {
        if (el.loading)   el.loading.style.display   = 'flex';
        if (el.container) el.container.style.display = 'none';
        if (el.empty)     el.empty.style.display     = 'none';
    }
    function showTable() {
        if (el.loading)   el.loading.style.display   = 'none';
        if (el.container) el.container.style.display = 'block';
        if (el.empty)     el.empty.style.display     = 'none';
    }
    function showEmpty() {
        if (el.loading)   el.loading.style.display   = 'none';
        if (el.container) el.container.style.display = 'none';
        if (el.empty)     el.empty.style.display     = 'flex';
    }
    function showError(msg) {
        console.error('[Tickets]', msg);
        notify(msg || t('errors.load_failed', 'Failed to load tickets'), 'error');
        showEmpty();
    }

    // ─────────────────────────────────────────────────────────
    // Notification helper — uses framework if available
    // ─────────────────────────────────────────────────────────
    function notify(msg, type = 'info') {
        if (window._admin && typeof window._admin.notify === 'function') {
            return window._admin.notify(msg, type);
        }
        if (window.AdminFramework && typeof window.AdminFramework.notify === 'function') {
            return window.AdminFramework.notify(msg, type);
        }
        // Fallback toast
        const toast = document.createElement('div');
        toast.textContent = msg;
        Object.assign(toast.style, {
            position       : 'fixed',
            bottom         : '24px',
            insetInlineEnd : '24px',
            padding        : '12px 20px',
            borderRadius   : '8px',
            color          : '#fff',
            fontSize       : '0.9rem',
            zIndex         : '99999',
            boxShadow      : '0 4px 12px rgba(0,0,0,0.25)',
            background     : type === 'success' ? '#10b981'
                           : type === 'error'   ? '#ef4444'
                           :                      '#3b82f6'
        });
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3500);
    }

    // ─────────────────────────────────────────────────────────
    // Filter helpers
    // ─────────────────────────────────────────────────────────
    function applyFilters() {
        state.filters = {
            search  : document.getElementById('searchInput')?.value    || '',
            status  : document.getElementById('statusFilter')?.value   || '',
            priority: document.getElementById('priorityFilter')?.value || ''
        };
        loadTickets(1);
    }

    function resetFilters() {
        state.filters = {};
        const s = document.getElementById('searchInput');
        const f = document.getElementById('statusFilter');
        const p = document.getElementById('priorityFilter');
        if (s) s.value = '';
        if (f) f.value = '';
        if (p) p.value = '';
        loadTickets(1);
    }

    // ─────────────────────────────────────────────────────────
    // Init
    // ─────────────────────────────────────────────────────────
    async function init() {
        console.log('[Tickets] Initializing...');

        el = {
            container     : document.getElementById('tableContainer'),
            loading       : document.getElementById('tableLoading'),
            empty         : document.getElementById('emptyState'),
            tbody         : document.getElementById('tableBody'),
            pagination    : document.getElementById('pagination'),
            paginationInfo: document.getElementById('paginationInfo'),
            formContainer : document.getElementById('ticketFormContainer'),
            form          : document.getElementById('ticketForm'),
            formTitle     : document.getElementById('formTitle'),
            formId        : document.getElementById('formId'),
            subject       : document.getElementById('ticketSubject'),
            description   : document.getElementById('ticketDescription'),
            status        : document.getElementById('ticketStatus'),
            priority      : document.getElementById('ticketPriority'),
            category      : document.getElementById('ticketCategory'),
            user          : document.getElementById('ticketUser'),
            order         : document.getElementById('ticketOrder'),
            entity        : document.getElementById('ticketEntity'),
            assigned      : document.getElementById('ticketAssigned'),
            messagesList  : document.getElementById('ticketMessagesList'),
            historyList   : document.getElementById('ticketHistoryList'),
            replyText     : document.getElementById('ticketReply'),
            replyInternal : document.getElementById('replyInternal'),
            btnDelete     : document.getElementById('btnDeleteTicket')
        };

        // Button / form events
        document.getElementById('btnAddTicket')?.addEventListener('click', () => showForm());
        document.getElementById('btnCloseForm')?.addEventListener('click', hideForm);
        document.getElementById('btnCancelForm')?.addEventListener('click', hideForm);
        el.form?.addEventListener('submit', saveTicket);
        el.btnDelete?.addEventListener('click', () => deleteTicket(state.currentTicket?.id));
        document.getElementById('btnSendReply')?.addEventListener('click', sendReply);

        // User change → reload orders + entities
        el.user?.addEventListener('change', () => loadUserOrdersAndEntities(el.user.value));

        // Filters
        document.getElementById('btnApplyFilters')?.addEventListener('click', applyFilters);
        document.getElementById('btnResetFilters')?.addEventListener('click', resetFilters);
        document.getElementById('searchInput')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') applyFilters();
        });

        // Tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
                btn.classList.add('active');
                const pane = document.getElementById(`tab-${btn.dataset.tab}`);
                if (pane) pane.style.display = 'block';
            });
        });

        await loadDropdowns();
        await loadTickets(1);
    }

    // ─────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────
    window.Tickets = {
        init,
        load  : loadTickets,
        edit  : async (id) => {
            try {
                const res = await apiCall(`${API.tickets}?id=${id}&tenant_id=${state.tenantId}`);
                if (res.success) await showForm(res.data);
                else throw new Error(res.message);
            } catch (e) {
                console.error('[Tickets] edit:', e);
                notify(e.message, 'error');
            }
        },
        remove: deleteTicket
    };

    // Initialization is driven by the fragment's inline script.
    // Do NOT self-invoke here — translations must be ready first.
})();