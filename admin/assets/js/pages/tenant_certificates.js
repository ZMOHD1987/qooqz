/**
 * admin/assets/js/pages/tenant_certificates.js
 * Simplified Client Version - Dashboard Links & Notifications Center
 */
var TenantCertificates = (function () {
    'use strict';

    const S = {
        lang: window.USER_LANGUAGE || 'ar',
        tenantId: window.APP_CONFIG?.TENANT_ID || null,
        translations: {},
        requests: [],
        products: [],
        notifs: [],
        activeTab: 'dashboard'
    };

    const q = id => document.getElementById(id);
    const esc = s => String(s || '').replace(/[&<>"]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m]));

    // ======================================================================
    // 1. i18n & Initialization
    // ======================================================================
    async function loadTranslations(lang) {
        lang = (lang || S.lang || 'ar').split('-')[0].toLowerCase();
        const paths = [
            `../languages/TenantCertificates/${lang}.json`,
            `/languages/TenantCertificates/${lang}.json`
        ];
        for (const path of paths) {
            const url = `${path}?v=${Date.now()}`;
            try {
                const res = await fetch(url, { credentials: 'same-origin' });
                if (res.ok) {
                    S.translations = await res.json();
                    S.lang = lang;
                    applyTranslations();
                    return true;
                }
            } catch (err) { }
        }
        if (lang !== 'en') return loadTranslations('en');
        return false;
    }

    function applyTranslations() {
        const root = q('tenantCertificatesRoot') || document.body;
        root.querySelectorAll('[data-i18n]').forEach(node => {
            const key = node.getAttribute('data-i18n');
            let val = getT(key);
            if (node.hasAttribute('data-i18n-vars')) {
                try {
                    const vars = JSON.parse(node.getAttribute('data-i18n-vars'));
                    Object.entries(vars).forEach(([k, v]) => { val = val.replace(new RegExp(`{${k}}`, 'g'), v); });
                } catch (e) { }
            }
            if (val) node.textContent = val;
        });
        root.querySelectorAll('[data-i18n-placeholder]').forEach(node => {
            const val = getT(node.getAttribute('data-i18n-placeholder'));
            if (val) node.placeholder = val;
        });
    }

    function getT(key, fallback = '') {
        if (!key) return fallback;
        const val = key.split('.').reduce((o, k) => (o && o[k] !== undefined ? o[k] : null), S.translations);
        return (val !== null && val !== undefined) ? String(val) : (fallback || key);
    }

    // ======================================================================
    // 2. Tab Logic
    // ======================================================================
    function initTabs() {
        const tabs = document.querySelectorAll('.tp-tab');
        tabs.forEach(tab => {
            tab.onclick = () => switchTab(tab.dataset.tab);
        });
    }

    function switchTab(target) {
        S.activeTab = target;
        document.querySelectorAll('.tp-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === target));
        document.querySelectorAll('.tp-tab-panel').forEach(p => {
            p.classList.toggle('active', p.id === `panel${target.charAt(0).toUpperCase() + target.slice(1)}`);
            p.style.display = p.classList.contains('active') ? 'block' : 'none';
        });

        if (target === 'dashboard') loadDashboard();
        if (target === 'requests') loadRequests();
        if (target === 'products') loadProducts();
        if (target === 'issued') loadIssued();
        if (target === 'notifications') loadNotifications();
    }

    // ======================================================================
    // 3. API & Data Loading
    // ======================================================================
    async function apiFetch(url, params = {}) {
        const qs = new URLSearchParams(params).toString();
        const res = await fetch(qs ? `${url}?${qs}` : url, { credentials: 'same-origin' });
        const d = await res.json();
        return d.data?.items || d.data || d;
    }

    async function loadDashboard() {
        showLoading('tpDashboardTableBody');
        const items = await apiFetch('/api/certificates_requests', { limit: 10 });
        S.requests = items;
        renderDashboardTable(items);
        if (items.length > 0) updateLifecycle(items[0]);
    }

    async function loadRequests() {
        showLoading('reqTableBody');
        const items = await apiFetch('/api/certificates_requests');
        renderTable('reqTableBody', items, renderReqRow);
    }

    async function loadProducts() {
        showLoading('prodTableBody');
        const items = await apiFetch('/api/certificates_products');
        renderTable('prodTableBody', items, renderProdRow);
    }

    async function loadIssued() {
        showLoading('issuedTableBody');
        const items = await apiFetch('/api/certificates_issued');
        renderTable('issuedTableBody', items, renderIssuedRow);
    }

    function showLoading(id) {
        const el = q(id);
        if (el) el.innerHTML = `<tr><td colspan="10" style="text-align:center;padding:40px;"><div class="cm-spinner"></div></td></tr>`;
    }

    // ======================================================================
    // 4. Notifications Logic
    // ======================================================================
    async function loadNotifications() {
        const list = q('notifList');
        if (list) list.innerHTML = `<div style="text-align:center;padding:40px;"><div class="cm-spinner"></div></div>`;

        try {
            const items = await apiFetch('/api/notifications');
            S.notifs = items;
            renderNotifications(items);
        } catch (e) {
            if (list) list.innerHTML = `<div class="tp-alert tp-alert-danger">${e.message}</div>`;
        }
    }

    function renderNotifications(items) {
        const list = q('notifList');
        if (!list) return;
        if (!items || !items.length) {
            list.innerHTML = `<div style="text-align:center;padding:40px;color:var(--tp-text-s);">${getT('notifications.empty')}</div>`;
            return;
        }

        list.innerHTML = items.map(n => `
            <div class="tp-notif-item ${n.is_read ? 'read' : 'unread'}" onclick="TenantCertificates.viewNotif(${n.id})">
                <div class="tp-notif-icon"><i class="fas ${n.is_read ? 'fa-envelope-open' : 'fa-envelope'}"></i></div>
                <div class="tp-notif-body">
                    <div class="tp-notif-title">${esc(n.title)}</div>
                    <div class="tp-notif-msg">${esc(n.message.substring(0, 100))}${n.message.length > 100 ? '...' : ''}</div>
                    <div class="tp-notif-time">${n.sent_at || '--'}</div>
                </div>
                ${!n.is_read ? '<div class="tp-unread-dot"></div>' : ''}
            </div>
        `).join('');
    }

    async function viewNotif(id) {
        const n = S.notifs.find(x => x.id == id);
        if (!n) return;

        q('notifModalTitle').textContent = n.title;
        q('notifModalMeta').textContent = n.sent_at;
        q('notifModalMessage').textContent = n.message;
        q('modalNotifDetails').style.display = 'flex';

        if (!n.is_read) {
            await fetch('/api/notifications', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, is_read: 1 })
            });
            n.is_read = 1;
            renderNotifications(S.notifs);
        }
    }

    async function markAllRead() {
        try {
            await fetch('/api/notifications', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mark_all_read: true })
            });
            S.notifs.forEach(n => n.is_read = 1);
            renderNotifications(S.notifs);
        } catch (e) { alert(e.message); }
    }

    // ======================================================================
    // 5. Rendering Tables
    // ======================================================================
    function renderTable(id, items, rowFn) {
        const body = q(id);
        if (!body) return;
        if (!items || !items.length) {
            body.innerHTML = `<tr><td colspan="20" style="text-align:center;padding:40px;color:var(--tp-text-s);">${getT('portal.table.no_requests')}</td></tr>`;
            return;
        }
        body.innerHTML = items.map(rowFn).join('');
    }

    function renderDashboardTable(items) {
        renderTable('tpDashboardTableBody', items, r => `
            <tr onclick="TenantCertificates.updateLifecycle(${JSON.stringify(r).replace(/"/g, '&quot;')})" style="cursor:pointer">
                <td>#${r.id}</td>
                <td>${esc(r.entity_name)}</td>
                <td><span class="tp-status-badge ${getStatusClass(r.status)}">${getT(`form.fields.status.${r.status}`, r.status)}</span></td>
                <td>${r.created_at?.split(' ')[0] || '--'}</td>
                <td>
                    <button class="btn-tp btn-view" onclick="event.stopPropagation();TenantCertificates.viewRequestDetails(${r.id})"><i class="fas fa-eye"></i></button>
                </td>
            </tr>
        `);
    }

    function renderReqRow(r) {
        return `<tr>
            <td>#${r.id}</td>
            <td>${esc(r.entity_name)}</td>
            <td>${esc(r.importer_name)}</td>
            <td><span class="tp-status-badge ${getStatusClass(r.status)}">${getT(`form.fields.status.${r.status}`, r.status)}</span></td>
            <td><span class="tp-status-badge">${getT(`form.fields.payment_status.${r.payment_status}`, r.payment_status)}</span></td>
            <td>${r.created_at?.split(' ')[0] || '--'}</td>
            <td>
                <button class="btn-tp btn-view" onclick="TenantCertificates.viewRequestDetails(${r.id})"><i class="fas fa-eye"></i></button>
            </td>
        </tr>`;
    }

    function renderProdRow(p) {
        return `<tr>
            <td>${p.id}</td>
            <td>${esc(p.entity_name)}</td>
            <td>${esc(p.brand_name)}</td>
            <td>${esc(p.name)}</td>
            <td><code>${esc(p.entity_product_code)}</code></td>
        </tr>`;
    }

    function renderIssuedRow(i) {
        return `<tr>
            <td>${i.id}</td>
            <td><strong>${i.certificate_number}</strong></td>
            <td>${i.issued_at?.split(' ')[0]}</td>
            <td>${i.printable_until?.split(' ')[0]}</td>
            <td>
                <button class="btn-tp btn-print" onclick="TenantCertificates.print(${i.id})"><i class="fas fa-print"></i></button>
            </td>
        </tr>`;
    }

    function getStatusClass(s) {
        if (s === 'under_review') return 'badge-under_review';
        if (s === 'approved' || s === 'payment_pending') return 'badge-payment_pending';
        if (s === 'issued') return 'badge-issued';
        return 'badge-draft';
    }

    function updateLifecycle(r) {
        const steps = q('tpSteps')?.querySelectorAll('.tp-step');
        if (!steps) return;
        const s = r.status;
        steps.forEach(st => st.classList.remove('active', 'complete'));
        if (s === 'under_review') steps[0].classList.add('active'); else if (s !== 'draft') steps[0].classList.add('complete');
        if (s === 'approved') steps[1].classList.add('active'); else if (['payment_pending', 'issued'].includes(s)) steps[1].classList.add('complete');
        if (s === 'payment_pending') steps[2].classList.add('active'); else if (s === 'issued' || r.payment_status === 'paid') steps[2].classList.add('complete');
        if (r.payment_status === 'paid' && s !== 'issued') steps[3].classList.add('active'); else if (s === 'issued') steps[3].classList.add('complete');
        if (s === 'issued') steps[4].classList.add('active', 'complete');
    }

    // ======================================================================
    // 6. Detailed View
    // ======================================================================
    async function viewRequestDetails(id) {
        q('detContent').innerHTML = `<div style="text-align:center;padding:40px;"><div class="cm-spinner"></div></div>`;
        q('modalRequestDetails').style.display = 'flex';
        q('detModalTitle').textContent = `Request #${id}`;

        try {
            const [req, items] = await Promise.all([
                apiFetch(`/api/certificates_requests/${id}`),
                apiFetch('/api/certificates_request_items', { request_id: id })
            ]);
            const r = Array.isArray(req) ? req[0] : req;

            q('detContent').innerHTML = `
                <div class="tp-card" style="padding:20px; border:none; background:rgba(255,255,255,0.02);">
                    <p><b>Entity:</b> ${esc(r.entity_name)}</p>
                    <p><b>Importer:</b> ${esc(r.importer_name)}</p>
                    <p><b>Status:</b> <span class="tp-status-badge ${getStatusClass(r.status)}">${r.status}</span></p>
                    <p><b>Payment:</b> ${r.payment_status}</p>
                </div>
                <div class="tp-card">
                    <div class="tp-card-header"><h4>Items</h4></div>
                    <table class="tp-table tp-small">
                        <thead><tr><th>Code</th><th>Name</th><th>Qty</th></tr></thead>
                        <tbody>${items.map(it => `<tr><td>${esc(it.product_code)}</td><td>${esc(it.product_name)}</td><td>${it.quantity}</td></tr>`).join('')}</tbody>
                    </table>
                </div>
            `;
        } catch (e) { q('detContent').innerHTML = `<div class="tp-alert tp-alert-danger">${e.message}</div>`; }
    }

    return {
        init: async function () {
            const root = q('tenantCertificatesRoot');
            if (root) {
                if (root.dataset.lang) S.lang = root.dataset.lang;
                if (root.dataset.tenantId) S.tenantId = root.dataset.tenantId;
            }
            await loadTranslations(S.lang);
            initTabs();
            loadDashboard();
        },
        loadDashboard,
        updateLifecycle,
        viewRequestDetails,
        closeRequestDetails: () => q('modalRequestDetails').style.display = 'none',
        viewNotif,
        closeNotifDetails: () => q('modalNotifDetails').style.display = 'none',
        markAllRead,
        print: id => window.open(`/api/print_certificate?id=${id}`, '_blank')
    };
})();

if (document.readyState === 'complete' || document.readyState === 'interactive') {
    if (document.getElementById('tenantCertificatesRoot')) TenantCertificates.init();
} else {
    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('tenantCertificatesRoot')) TenantCertificates.init();
    });
}
