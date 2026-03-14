/**
 * /admin/assets/js/pages/CertificateManagement.js
 * Certificate Management — Audit workflow, payment verification, issuance & logs
 * VERSION: 5.1 — ALLOCATION FIELD FIX
 *
 * FIXES IN THIS VERSION:
 * 1. Allocation API now sends `certificate_id` (version ID) instead of `version_id`.
 * 2. Numeric values (fee_id, amount) are properly converted to numbers.
 * 3. Removed automatic payment creation after audit (payments are now entered manually).
 * 4. Allocation modal receipt input accepts letters and numbers (alphanumeric).
 * 5. Added validation to ensure request_id is never null when submitting audits.
 * 6. Exposed closeModal globally so modal close buttons in settings tabs work.
 * 7. All hardcoded strings replaced with t() i18n calls.
 * 8. issued_id correctly saved to certificates_requests after issuance.
 * 9. auditor_user_id correctly updated (null values filtered from extraFields).
 * 10. Issue button removed from requests table (issuance only via Allocations tab).
 */
(function () {
    'use strict';

    const CFG = window.CERT_MGMT_CFG || {};

    /* ── Endpoints ──────────────────────────────────────────────────── */
    const API_REQ      = CFG.apiRequests      || '/api/certificates_requests';
    const API_AUDITS   = CFG.apiAudits        || '/api/certificates_audits';
    const API_PAY      = CFG.apiPayments      || '/api/certificates_payments';
    const API_ISSUED   = CFG.apiIssued        || '/api/certificates_issued';
    const API_LOGS     = CFG.apiLogs          || '/api/certificates_logs';
    const API_ITEMS    = CFG.apiItems         || '/api/certificates_request_items';
    const API_CERTS    = CFG.apiCertificates  || '/api/certificates';
    const API_ENT      = CFG.apiEntities      || '/api/entities';
    const API_PROD     = CFG.apiProducts      || '/api/certificates_products';
    const API_PT       = CFG.apiProductsTrans || '/api/certificates_products_translations';
    const API_TU       = CFG.apiTenantUsers   || '/api/tenant_users';
    const API_EDI      = CFG.apiEditions      || '/api/certificate_editions';
    const API_FEE      = CFG.apiFees          || '/api/certificates_fee_rules';
    const API_VERSIONS = CFG.apiVersions      || '/api/certificates_versions';
    const API_ALLOC    = CFG.apiAllocations   || '/api/certificate_receipt_allocations';

    /* ── Cache Layer ────────────────────────────────────────────────── */
    const CACHE = {
        _store: new Map(),
        _ttl: new Map(),
        TTL_MS: 5 * 60 * 1000,

        set(key, val) {
            this._store.set(key, val);
            this._ttl.set(key, Date.now() + this.TTL_MS);
        },
        get(key) {
            if (!this._store.has(key)) return null;
            if (Date.now() > this._ttl.get(key)) { this._store.delete(key); this._ttl.delete(key); return null; }
            return this._store.get(key);
        },
        invalidate(prefix) {
            for (const k of this._store.keys()) {
                if (k.startsWith(prefix)) { this._store.delete(k); this._ttl.delete(k); }
            }
        },
        clear() { this._store.clear(); this._ttl.clear(); }
    };

    /* ── Request Deduplication ──────────────────────────────────────── */
    const IN_FLIGHT = new Map();

    /* ── Abort Controllers per tab ──────────────────────────────────── */
    const ABORT = {};

    /* ── CSRF ──────────────────────────────────────────────────────── */
    const CSRF = () => window.CERT_MGMT_CFG?.csrfToken
        || document.querySelector('meta[name="csrf-token"]')?.content || '';

    /* ── HTTP with Cache + Dedup ────────────────────────────────────── */
    async function http(method, url, body, signal) {
        const opts = {
            method,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF() },
            signal
        };
        if (body !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const res = await fetch(url, opts);
        if (!res.ok) {
            let msg = `HTTP ${res.status}`;
            try { const d = await res.json(); msg = d.message || d.error || msg; } catch { }
            throw new Error(msg);
        }
        return res.json().catch(() => ({}));
    }

    async function apiGet(url, useCache = true) {
        if (useCache) {
            const cached = CACHE.get(url);
            if (cached !== null) return cached;
        }
        if (IN_FLIGHT.has(url)) return IN_FLIGHT.get(url);

        const promise = http('GET', url)
            .then(data => {
                if (useCache) CACHE.set(url, data);
                IN_FLIGHT.delete(url);
                return data;
            })
            .catch(e => { IN_FLIGHT.delete(url); throw e; });

        IN_FLIGHT.set(url, promise);
        return promise;
    }

    const apiPost = (url, body) => http('POST', url, body);
    const apiPut  = (url, body) => http('PUT',  url, body);
    const apiDel  = (url, body) => http('DELETE', url, body);

    async function apiGetTab(url, tab) {
        if (ABORT[tab]) { try { ABORT[tab].abort(); } catch {} }
        ABORT[tab] = new AbortController();
        const cached = CACHE.get(url);
        if (cached !== null) return cached;
        const data = await http('GET', url, undefined, ABORT[tab].signal);
        CACHE.set(url, data);
        return data;
    }

    /* ── State ──────────────────────────────────────────────────────── */
    const S = {
        lang:        window.USER_LANGUAGE || 'ar',
        tenantId:    window.APP_CONFIG?.TENANT_ID || 1,
        perms:       CFG.perms || {},
        perPage:     CFG.perPage || 25,
        tr:          {},
        entities:    [],
        certificates:[],
        products:    [],
        tenantUsers: [],
        fees:        [],
        activeTab:   'requests',
        pages:  { requests:1, audits:1, payments:1, issued:1, logs:1, allocations:1 },
        filters:{ requests:{}, audits:{}, payments:{}, issued:{}, logs:{}, allocations:{} },
        currentAuditRequestId: null,
        currentPaymentId:      null,
        currentPayEntityId:    null,
        currentPayReqId:       null,
        _allocCertPool:        [],
        _allocReceiptData:     null,
        _productsLoaded:       false,
        _statsLoading:         false,
    };

    /* ── Helpers ────────────────────────────────────────────────────── */
    const esc = s => {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"]/g, m => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[m]));
    };
    const q  = id => document.getElementById(id);
    const sd = (el, v) => { if (el) el.textContent = (v ?? ''); };

    function buildUrl(base, params = {}) {
        const p = {};
        Object.entries(params).forEach(([k, v]) => { if (v !== null && v !== undefined && v !== '') p[k] = v; });
        const qs = new URLSearchParams(p).toString();
        return qs ? `${base}?${qs}` : base;
    }

    function pick(data) {
        if (!data) return [];
        if (Array.isArray(data)) return data;
        if (data.data && Array.isArray(data.data.items)) return data.data.items;
        if (Array.isArray(data.data)) return data.data;
        if (Array.isArray(data.items)) return data.items;
        if (data?.id) return [data];
        return [];
    }

    function extractSingle(data) {
        if (!data) return null;
        if (data.id) return data;
        if (data.data?.id) return data.data;
        if (data.data?.items?.[0]) return data.data.items[0];
        if (Array.isArray(data.data) && data.data[0]) return data.data[0];
        if (Array.isArray(data.items) && data.items[0]) return data.items[0];
        if (Array.isArray(data) && data[0]) return data[0];
        return null;
    }

    function getMeta(data, items, page) {
        if (data?.data?.meta?.total !== undefined) return data.data.meta;
        if (data?.meta?.total !== undefined) return data.meta;
        if (data?.total !== undefined) return {
            total: data.total, page, per_page: S.perPage,
            from: (page-1)*S.perPage+1, to: Math.min(page*S.perPage, data.total),
            last_page: Math.ceil(data.total/S.perPage)
        };
        return {
            total: items.length, page, per_page: S.perPage,
            from: items.length ? (page-1)*S.perPage+1 : 0,
            to: Math.min(page*S.perPage, items.length),
            last_page: Math.ceil(items.length/S.perPage) || 1
        };
    }

    /* ── Debounce ───────────────────────────────────────────────────── */
    function debounce(fn, ms = 300) {
        let timer;
        return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), ms); };
    }

    /* ── i18n ───────────────────────────────────────────────────────── */
    async function loadI18n() {
        try {
            const r = await fetch(`/languages/CertificateManagement/${encodeURIComponent(S.lang)}.json`, { credentials:'same-origin' });
            if (!r.ok) throw new Error('not found');
            S.tr = await r.json();
        } catch(_) {
            if (S.lang !== 'en') {
                try { const r2=await fetch('/languages/CertificateManagement/en.json',{credentials:'same-origin'}); if(r2.ok) S.tr=await r2.json(); } catch(__) {}
            }
        }
        applyI18n();
    }

    function t(key, fallback) {
        const val = key.split('.').reduce((o,k) => (o && o[k]!==undefined ? o[k] : null), S.tr);
        return (val!==null && val!==undefined) ? String(val) : (fallback!==undefined ? fallback : key);
    }

    function applyI18n(root) {
        const el = root || q('certMgmtPage'); if (!el) return;
        el.querySelectorAll('[data-i18n]').forEach(n => { const v=t(n.getAttribute('data-i18n'),''); if(v) n.textContent=v; });
        el.querySelectorAll('[data-i18n-placeholder]').forEach(n => { const v=t(n.getAttribute('data-i18n-placeholder'),''); if(v) n.placeholder=v; });
    }

    function applyI18nSelects(root) {
        const el = root || q('certMgmtPage'); if (!el) return;
        el.querySelectorAll('select option[data-i18n]').forEach(o => {
            const v = t(o.getAttribute('data-i18n'), '');
            if (v) o.textContent = v;
        });
    }

    /* ── Toast ──────────────────────────────────────────────────────── */
    function toast(msg, ok=true) {
        const el = q('cmToast'); if (!el) return;
        el.textContent = msg;
        el.className = `cm-toast ${ok ? 'cm-toast-ok' : 'cm-toast-err'}`;
        el.style.display = 'block';
        setTimeout(() => { el.style.display='none'; }, 3500);
    }

    /* ── Skeleton HTML ──────────────────────────────────────────────── */
    function skeletonRows(cols = 6, rows = 5) {
        const cells = Array(cols).fill('<td><div class="cm-skeleton"></div></td>').join('');
        return Array(rows).fill(`<tr>${cells}</tr>`).join('');
    }

    /* ══════════════════════════════════════════════════════════════════
       LOOKUPS
    ══════════════════════════════════════════════════════════════════ */
    async function loadLookups() {
        await Promise.allSettled([
            loadEntities(),
            loadCertificates(),
            loadTenantUsers(),
            loadFeesCache()
        ]);
    }

    async function loadEntities() {
        try {
            const p = { limit:500, lang:S.lang };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;
            S.entities = pick(await apiGet(buildUrl(API_ENT, p)))
                .map(e => ({ id:e.id, name:e.store_name||e.name||`#${e.id}` }));
            populateEntityFilter();
        } catch(e) { console.warn('entities:', e.message); }
    }

    async function loadCertificates() {
        try { S.certificates = pick(await apiGet(buildUrl(API_CERTS, { limit:100 }))); }
        catch(e) { console.warn('certs:', e.message); }
    }

    async function loadTenantUsers() {
        try {
            const p = { limit:200, entity_id:'null', is_active:1 };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;
            const all = pick(await apiGet(buildUrl(API_TU, p)));
            S.tenantUsers = all.filter(u => (u.entity_id === null || u.entity_id === undefined || u.entity_id === '') && u.is_active == 1);
            if (!S.tenantUsers.length) S.tenantUsers = all;
            populateAuditorFilter();
            populateAuditAssignSelect();
        } catch(e) { console.warn('tenant users:', e.message); }
    }

    async function loadFeesCache() {
        try {
            const raw = await apiGet(buildUrl(API_FEE, { tenant_id: S.tenantId, limit:100 }));
            S.fees = pick(raw).filter(f => f.is_active != 0);
        } catch(e) { console.warn('fees cache:', e.message); }
    }

    async function ensureProductsLoaded() {
        if (S._productsLoaded) return;
        try {
            const [rawP, rawT] = await Promise.all([
                apiGet(buildUrl(API_PROD, { limit:500 })),
                apiGet(buildUrl(API_PT,   { limit:2000, language_code: S.lang }))
            ]);
            const map = {};
            pick(rawT).forEach(r => {
                if (!map[r.product_id]) map[r.product_id] = {};
                map[r.product_id][r.language_code] = r.name || '';
            });
            S.products = pick(rawP).map(p => {
                const names = map[p.id] || {};
                const name  = names[S.lang] || names['ar'] || names['en'] || Object.values(names)[0] || `#${p.id}`;
                return { id:p.id, name };
            });
            S._productsLoaded = true;
        } catch(e) { console.warn('products lazy:', e.message); }
    }

    const getEntityName  = id => S.entities.find(e => e.id==id)?.name || `#${id}`;
    const getCertName    = id => { const c=S.certificates.find(c=>c.id==id); return c?(c.code||c.description||`#${id}`):`#${id}`; };
    const getProductName = id => S.products.find(p=>p.id==id)?.name || `#${id}`;
    const getUserName    = id => {
        const u = S.tenantUsers.find(u => u.id==id || u.user_id==id);
        return u ? (u.name||u.username||u.email||`#${id}`) : (id ? `#${id}` : '—');
    };

    function populateEntityFilter() {
        const s = q('reqEntityFilter'); if (!s) return;
        const cur = s.value;
        const frag = document.createDocumentFragment();
        const def = document.createElement('option');
        def.value = ''; def.textContent = t('filters.all_entities','All Entities');
        frag.appendChild(def);
        S.entities.forEach(e => {
            const o = document.createElement('option');
            o.value = e.id; o.textContent = e.name;
            if (o.value == cur) o.selected = true;
            frag.appendChild(o);
        });
        s.innerHTML = '';
        s.appendChild(frag);
    }

    function populateAuditorFilter() {
        const s = q('reqAuditorFilter'); if (!s) return;
        const frag = document.createDocumentFragment();
        const def = document.createElement('option');
        def.value = ''; def.textContent = t('filters.all','All');
        frag.appendChild(def);
        S.tenantUsers.forEach(u => {
            const o = document.createElement('option');
            o.value = u.id||u.user_id;
            o.textContent = u.name||u.username||u.email||`#${o.value}`;
            frag.appendChild(o);
        });
        s.innerHTML = '';
        s.appendChild(frag);
    }

    function populateAuditAssignSelect() {
        const s = q('auditAssignUser'); if (!s) return;
        const frag = document.createDocumentFragment();
        const def = document.createElement('option');
        def.value = ''; def.textContent = t('audit.form.no_change','— No Change —');
        frag.appendChild(def);
        S.tenantUsers.forEach(u => {
            const o = document.createElement('option');
            o.value = u.id||u.user_id;
            o.textContent = u.name||u.username||u.email||`#${o.value}`;
            frag.appendChild(o);
        });
        s.innerHTML = '';
        s.appendChild(frag);
    }

    /* ── Badges ─────────────────────────────────────────────────────── */
    const STATUS_CLS = { draft:'badge-draft', under_review:'badge-under_review', payment_pending:'badge-payment_pending', approved:'badge-approved', rejected:'badge-rejected', issued:'badge-issued' };
    const statusBadge    = s => `<span class="badge ${STATUS_CLS[s]||''}">${esc(t('status.'+s, s))}</span>`;
    const auditBadge     = s => { const c=s==='approved'?'badge-approved':s==='rejected'?'badge-rejected':'badge-under_review'; return `<span class="badge ${c}">${esc(t('audit.status.'+s,s))}</span>`; };
    const payVerifyBadge = s => { const c=s==='verified'?'badge-approved':s==='rejected'?'badge-rejected':'badge-under_review'; return `<span class="badge ${c}">${esc(t('pay.status.'+s,s))}</span>`; };

    /* ══════════════════════════════════════════════════════════════════
       STATS
    ══════════════════════════════════════════════════════════════════ */
    async function refreshStats() {
        if (S._statsLoading) return;
        S._statsLoading = true;
        try {
            const base = { limit:1, page:1, tenant_id: S.perms.isSuperAdmin ? undefined : S.tenantId };
            const [r1, r2, r3, r4] = await Promise.all([
                apiGet(buildUrl(API_REQ, { ...base, status:'under_review' })),
                apiGet(buildUrl(API_REQ, { ...base, status:'payment_pending' })),
                apiGet(buildUrl(API_REQ, { ...base, status:'approved' })),
                apiGet(buildUrl(API_REQ, { ...base, status:'issued' }))
            ]);
            sd(q('statPendingNum'),  getMeta(r1, [], 1).total || 0);
            sd(q('statPaymentNum'),  getMeta(r2, [], 1).total || 0);
            sd(q('statApprovedNum'), getMeta(r3, [], 1).total || 0);
            sd(q('statIssuedNum'),   getMeta(r4, [], 1).total || 0);
        } catch(e) { console.warn('refreshStats:', e.message); }
        finally { S._statsLoading = false; }
    }

    /* ══════════════════════════════════════════════════════════════════
       TAB: REQUESTS
    ══════════════════════════════════════════════════════════════════ */
    const COUNTRY_MAP = { 147:'الإمارات', 1:'Afghanistan', 682:'Saudi Arabia', 48:'China', 116:'Kuwait', 139:'Oman', 168:'Qatar', 17:'Bahrain' };

    async function loadRequests(page=1) {
        S.pages.requests = page;

        const tbody = q('reqTableBody');
        if (tbody) tbody.innerHTML = skeletonRows(S.perms.isSuperAdmin ? 11 : 10, 6);
        tabState('requests','table');

        const thead = q('requestsTable')?.querySelector('thead');
        if (thead) {
            const isSA = S.perms.isSuperAdmin;
            const headers = isSA
                ? ['ID', t('req_table.tenant','Tenant'), t('req_table.entity','المنشأة'), t('req_table.certificate','الشهادة'), t('req_table.importer','المستورد'), t('detail.origin','Country'), t('detail.gcc','Type'), t('req_table.status','الحالة'), t('req_table.auditor','المدقق'), t('req_table.created','تاريخ الإنشاء'), t('req_table.actions','إجراءات')]
                : ['ID', t('req_table.entity','المنشأة'), t('req_table.certificate','الشهادة'), t('req_table.importer','المستورد'), t('detail.origin','Country'), t('detail.gcc','Type'), t('req_table.status','الحالة'), t('req_table.auditor','المدقق'), t('req_table.created','تاريخ الإنشاء'), t('req_table.actions','إجراءات')];
            thead.innerHTML = `<tr>${headers.map(h=>`<th>${h}</th>`).join('')}</tr>`;
        }

        try {
            const f = S.filters.requests;
            const p = { page, limit:S.perPage, lang:S.lang };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;
            if (f.search)  p.search          = f.search;
            if (f.entity)  p.entity_id       = f.entity;
            if (f.auditor) p.auditor_user_id = f.auditor;
            if (f.tenant)  p.tenant_id       = f.tenant;

            if (f.status) {
                p.status = f.status;
            } else {
                p.status_exclude = 'approved,issued';
            }

            const url   = buildUrl(API_REQ, p);
            const raw   = await apiGetTab(url, 'requests');
            const items = pick(raw);
            const meta  = getMeta(raw, items, page);

            updateStatBadge('badgeRequests', meta.total);

            if (!items.length) { tabState('requests','empty'); return; }

            const isSA = S.perms.isSuperAdmin;
            const rows = items.map(r => {
                const countryName = COUNTRY_MAP[r.importer_country_id] || (r.importer_country_id ? `#${r.importer_country_id}` : '—');
                const certType = r.certificate_type
                    ? (r.certificate_type === 'gcc' ? t('status.gcc', 'خليجي') : t('status.non_gcc', 'غير خليجي'))
                    : '—';
                return `<tr>
<td>${r.id}</td>
${isSA ? `<td>${esc(r.tenant_name||r.tenant_id||'')}</td>` : ''}
<td class="td-entity">${esc(getEntityName(r.entity_id))}</td>
<td>${esc(getCertName(r.certificate_id))}</td>
<td class="td-long">${esc(r.importer_name||'—')}</td>
<td class="td-sm">${esc(countryName)}</td>
<td class="td-sm"><span class="badge badge-outline">${esc(certType)}</span></td>
<td>${statusBadge(r.status)}</td>
<td class="td-sm">${esc(getUserName(r.auditor_user_id))}</td>
<td class="td-sm">${r.created_at ? new Date(r.created_at).toLocaleDateString() : '—'}</td>
<td>
  <div class="td-actions">
    <button class="btn btn-sm btn-outline" onclick="CertificateManagement.viewDetail(${r.id})" title="${t('common.view','View')}">
        <i class="fas fa-eye"></i> <span class="btn-text">${t('common.view','View')}</span>
    </button>
    ${r.status==='draft' ? `
    <button class="btn btn-sm btn-primary" onclick="CertificateManagement.submitForReview(${r.id})">
        <i class="fas fa-paper-plane"></i> <span class="btn-text">${t('common.submit','Submit')}</span>
    </button>` : ''}
    ${r.status==='payment_pending' ? `
    <button class="btn btn-sm btn-warning" onclick="CertificateManagement.openAddPayment(${r.id})">
        <i class="fas fa-money-bill-wave"></i> <span class="btn-text">${t('button.pay','دفع')}</span>
    </button>` : ''}
  </div>
</td>
</tr>`;
            });

            if (tbody) tbody.innerHTML = rows.join('');
            tabState('requests','table');
            renderPager('req', meta, loadRequests);
        } catch(e) {
            if (e.name === 'AbortError') return;
            console.error(e);
            tabState('requests','error');
        }
    }

    /* ══════════════════════════════════════════════════════════════════
       TAB: AUDITS
    ══════════════════════════════════════════════════════════════════ */
    async function loadAudits(page=1) {
        S.pages.audits = page;
        const tbody = q('auditTableBody');
        if (tbody) tbody.innerHTML = skeletonRows(8, 5);
        tabState('audits','table');

        const auditThead = q('auditTableBody')?.closest('table')?.querySelector('thead');
        if (auditThead) {
            const h = ['ID', t('audit_table.request_id','الطلب'), t('audit_table.entity','المنشأة'), t('audit_table.auditor','المدقق'), t('audit_table.status','الحالة'), t('audit_table.audit_date','تاريخ التدقيق'), t('audit_table.notes','ملاحظات'), t('audit_table.actions','إجراءات')];
            auditThead.innerHTML = `<tr>${h.map(x=>`<th>${x}</th>`).join('')}</tr>`;
        }

        try {
            const f = S.filters.audits;
            const p = { page, limit:S.perPage };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;
            if (f.request_id) p.request_id = f.request_id;
            if (f.status)     p.status     = f.status;

            const raw   = await apiGetTab(buildUrl(API_AUDITS, p), 'audits');
            const items = pick(raw);
            const meta  = getMeta(raw, items, page);

            updateStatBadge('badgeAudits', meta.total);
            if (!items.length) { tabState('audits','empty'); return; }

            const rows = items.map(a => {
                const entName = a.entity_name || getEntityName(a.entity_id);
                let dateStr = '—';
                if (a.audit_date) {
                    try { dateStr = new Date(a.audit_date.replace(/-/g,'/')).toLocaleString(); }
                    catch { dateStr = a.audit_date; }
                }
                const actionBtn = a.status === 'approved'
                    ? `<button class="btn btn-sm btn-outline" onclick="CertificateManagement.viewDetail(${a.request_id})"><i class="fas fa-eye"></i></button>
                       <button class="btn btn-sm btn-warning" onclick="CertificateManagement.openAddPayment(${a.request_id})"><i class="fas fa-money-bill-wave"></i> <span class="btn-text">${t('button.pay','دفع')}</span></button>`
                    : `<button class="btn btn-sm btn-outline" onclick="CertificateManagement.viewDetail(${a.request_id})"><i class="fas fa-eye"></i> <span class="btn-text">${t('common.view','View')}</span></button>`;

                return `<tr>
<td>${a.id}</td>
<td><a href="#" onclick="CertificateManagement.viewDetail(${a.request_id});return false;">#${a.request_id}</a></td>
<td class="td-sm">${esc(entName)}</td>
<td class="td-sm">${esc(getUserName(a.auditor_id))}</td>
<td>${auditBadge(a.status)}</td>
<td class="td-sm">${esc(dateStr)}</td>
<td class="td-notes">${esc(a.notes||'')}</td>
<td><div class="td-actions">${actionBtn}</div></td>
</tr>`;
            });

            if (tbody) tbody.innerHTML = rows.join('');
            tabState('audits','table');
            renderPager('audit', meta, loadAudits);
        } catch(e) {
            if (e.name === 'AbortError') return;
            console.error('loadAudits:', e);
            tabState('audits','error');
        }
    }

    /* ══════════════════════════════════════════════════════════════════
       TAB: PAYMENTS
    ══════════════════════════════════════════════════════════════════ */
    async function loadPayments(page=1) {
        S.pages.payments = page;
        const tbody = q('payTableBody');
        if (tbody) tbody.innerHTML = skeletonRows(9, 5);
        tabState('payments','table');

        const payThead = q('payTableBody')?.closest('table')?.querySelector('thead');
        if (payThead) {
            const h = ['ID', t('pay_table.request_id','الطلب'), t('pay_table.type','النوع'), t('pay_table.amount','المبلغ'), t('pay_table.reference','المرجع'), t('pay_table.date','التاريخ'), t('pay_table.receipt','الإيصال'), t('pay_table.status','الحالة'), t('pay_table.actions','إجراءات')];
            payThead.innerHTML = `<tr>${h.map(x=>`<th>${x}</th>`).join('')}</tr>`;
        }

        try {
            const f = S.filters.payments;
            const p = { page, limit:S.perPage };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;
            if (f.request_id)    p.request_id          = f.request_id;
            if (f.verify_status) p.verification_status = f.verify_status;

            const raw   = await apiGetTab(buildUrl(API_PAY, p), 'payments');
            const items = pick(raw);
            const meta  = getMeta(raw, items, page);

            const waiting = items.filter(i=>i.verification_status==='waiting_verification').length;
            updateStatBadge('badgePayments', waiting||null);
            if (!items.length) { tabState('payments','empty'); return; }

            const rows = items.map(py => {
                const canVerify = (S.perms.canVerifyPay||S.perms.isSuperAdmin) && py.verification_status==='waiting_verification';
                return `<tr>
<td>${py.id}</td>
<td><a href="#" onclick="CertificateManagement.viewDetail(${py.request_id});return false;">#${py.request_id}</a></td>
<td class="td-sm">${esc(py.payment_type||'initial')}</td>
<td class="td-sm">${esc(py.amount||'0.00')} ${esc(py.currency||'AED')}</td>
<td class="td-sm">${esc(py.payment_reference||'—')}</td>
<td class="td-sm">${py.payment_date ? py.payment_date.split(' ')[0] : '—'}</td>
<td>${py.receipt_file ? `<a href="${esc(py.receipt_file)}" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-file"></i></a>` : '—'}</td>
<td>${payVerifyBadge(py.verification_status)}${py.verified_by ? `<br><small>${esc(getUserName(py.verified_by))}</small>` : ''}</td>
<td>
  <div class="td-actions">
    <button class="btn btn-sm btn-outline" onclick="CertificateManagement.viewDetail(${py.request_id})"><i class="fas fa-eye"></i></button>
    ${canVerify ? `<button class="btn btn-sm btn-success" onclick="CertificateManagement.openPaymentVerify(${py.id})">
        <i class="fas fa-check-circle"></i> <span class="btn-text">${t('pay.verify','Verify')}</span>
    </button>` : ''}
  </div>
</td>
</tr>`;
            });

            if (tbody) tbody.innerHTML = rows.join('');
            tabState('payments','table');
            renderPager('pay', meta, loadPayments);
        } catch(e) {
            if (e.name === 'AbortError') return;
            console.error(e);
            tabState('payments','error');
        }
    }

    /* ══════════════════════════════════════════════════════════════════
       TAB: ISSUED
    ══════════════════════════════════════════════════════════════════ */
    async function loadIssued(page=1) {
        S.pages.issued = page;
        const tbody = q('issuedTableBody');
        if (tbody) tbody.innerHTML = skeletonRows(9, 5);
        tabState('issued','table');

        const issThead = q('issuedTableBody')?.closest('table')?.querySelector('thead');
        if (issThead) {
            const h = ['ID', t('iss_table.cert_number','رقم الشهادة'), t('iss_table.version','النسخة'), t('iss_table.issued_at','تاريخ الإصدار'), t('iss_table.printable_until','صالحة حتى'), t('iss_table.issued_by','أصدرها'), t('iss_table.language','اللغة'), t('iss_table.status','الحالة'), t('iss_table.actions','إجراءات')];
            issThead.innerHTML = `<tr>${h.map(x=>`<th>${x}</th>`).join('')}</tr>`;
        }

        try {
            const f = S.filters.issued;
            const p = { page, limit:S.perPage };
            if (f.search)    p.search       = f.search;
            if (f.cancelled !== undefined && f.cancelled !== '') p.is_cancelled = f.cancelled;

            const raw   = await apiGetTab(buildUrl(API_ISSUED, p), 'issued');
            const items = pick(raw);
            const meta  = getMeta(raw, items, page);

            if (!items.length) { tabState('issued','empty'); return; }

            const rows = items.map(ci => `<tr>
<td>${ci.id}</td>
<td><strong>${esc(ci.certificate_number||'—')}</strong></td>
<td class="td-sm">#${ci.version_id||'—'}</td>
<td class="td-sm">${ci.issued_at ? new Date(ci.issued_at).toLocaleString() : '—'}</td>
<td class="td-sm">${ci.printable_until ? new Date(ci.printable_until).toLocaleString() : '—'}</td>
<td class="td-sm">${esc(getUserName(ci.issued_by))}</td>
<td class="td-sm">${esc(ci.language_code||'—')}</td>
<td>${ci.is_cancelled ? `<span class="badge badge-inactive">${t('filters.cancelled','Cancelled')}</span>` : `<span class="badge badge-active">${t('filters.active','Active')}</span>`}</td>
<td>
  <div class="td-actions">
    ${ci.pdf_path ? `<a href="${esc(ci.pdf_path)}" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-file-pdf"></i></a>` : ''}
    ${ci.qr_code_path ? `<a href="${esc(ci.qr_code_path)}" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-qrcode"></i></a>` : ''}
  </div>
</td>
</tr>`);

            if (tbody) tbody.innerHTML = rows.join('');
            tabState('issued','table');
            renderPager('issued', meta, loadIssued);
        } catch(e) {
            if (e.name === 'AbortError') return;
            console.error(e);
            tabState('issued','error');
        }
    }

    /* ══════════════════════════════════════════════════════════════════
       TAB: LOGS
    ══════════════════════════════════════════════════════════════════ */
    const ACTION_ICON = { create:'plus-circle', update:'edit', approve:'check-circle', audit:'search', payment_sent:'credit-card', issue:'stamp', reject:'times-circle' };

    async function loadLogs(page=1) {
        S.pages.logs = page;
        const tbody = q('logTableBody');
        if (tbody) tbody.innerHTML = skeletonRows(6, 5);
        tabState('logs','table');

        const logThead = q('logTableBody')?.closest('table')?.querySelector('thead');
        if (logThead) {
            const h = ['ID', t('log_table.request_id','الطلب'), t('log_table.user','المستخدم'), t('log_table.action','الإجراء'), t('log_table.notes','ملاحظات'), t('log_table.created','التاريخ')];
            logThead.innerHTML = `<tr>${h.map(x=>`<th>${x}</th>`).join('')}</tr>`;
        }

        try {
            const f = S.filters.logs;
            const p = { page, limit:S.perPage };
            if (f.request_id) p.request_id  = f.request_id;
            if (f.action)     p.action_type = f.action;
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;

            const raw   = await apiGetTab(buildUrl(API_LOGS, p), 'logs');
            const items = pick(raw);
            const meta  = getMeta(raw, items, page);

            if (!items.length) { tabState('logs','empty'); return; }

            const rows = items.map(l => `<tr>
<td>${l.id}</td>
<td><a href="#" onclick="CertificateManagement.viewDetail(${l.request_id});return false;">#${l.request_id}</a></td>
<td class="td-sm">${esc(getUserName(l.user_id))}</td>
<td><span class="cm-action-badge cm-action-${esc(l.action_type)}"><i class="fas fa-${ACTION_ICON[l.action_type]||'info-circle'}"></i> ${esc(l.action_type||'')}</span></td>
<td class="td-notes">${esc(l.notes||'')}</td>
<td class="td-sm">${l.created_at ? new Date(l.created_at).toLocaleString() : '—'}</td>
</tr>`);

            if (tbody) tbody.innerHTML = rows.join('');
            tabState('logs','table');
            renderPager('log', meta, loadLogs);
        } catch(e) {
            if (e.name === 'AbortError') return;
            console.error(e);
            tabState('logs','error');
        }
    }

    /* ══════════════════════════════════════════════════════════════════
       TAB: ALLOCATIONS
    ══════════════════════════════════════════════════════════════════ */
    async function loadAllocations(page=1) {
        S.pages.allocations = page;
        const tbody = q('allocTableBody');
        if (tbody) tbody.innerHTML = skeletonRows(7, 5);
        tabState('allocations','table');

        const allocThead = q('allocTableBody')?.closest('table')?.querySelector('thead');
        if (allocThead) {
            const h = ['ID', t('alloc_table.receipt_id','رقم الإيصال'), t('alloc_table.certificate_id','الشهادة'), t('alloc_table.fee_id','قاعدة الرسوم'), t('alloc_table.allocated_amount','المبلغ المخصص'), t('alloc_table.created_at','تاريخ الإنشاء'), t('alloc_table.actions','إجراءات')];
            allocThead.innerHTML = `<tr>${h.map(x=>`<th>${x}</th>`).join('')}</tr>`;
        }

        try {
            const f = S.filters.allocations;
            const p = { page, limit:S.perPage };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;
            if (f.receipt_id)     p.receipt_id     = f.receipt_id;
            if (f.certificate_id) p.certificate_id = f.certificate_id;

            const raw   = await apiGetTab(buildUrl(API_ALLOC, p), 'allocations');
            const items = pick(raw);
            const meta  = getMeta(raw, items, page);

            if (!items.length) { tabState('allocations','empty'); return; }

            const rows = items.map(al => `<tr>
<td>${al.id}</td>
<td><a href="#" onclick="CertificateManagement.openPaymentVerify(${al.receipt_id});return false;">#${al.receipt_id}</a></td>
<td><a href="#" onclick="CertificateManagement.viewDetail(${al.request_id||0});return false;">#${al.certificate_id}</a></td>
<td>${al.fee_id}</td>
<td><strong>${al.allocated_amount}</strong></td>
<td>${al.created_at ? new Date(al.created_at).toLocaleString() : '—'}</td>
<td><button class="btn btn-sm btn-outline cm-text-danger" onclick="CertificateManagement.deleteAllocation(${al.id})"><i class="fas fa-trash"></i></button></td>
</tr>`);

            if (tbody) tbody.innerHTML = rows.join('');
            tabState('allocations','table');
            renderPager('alloc', meta, loadAllocations);
        } catch(e) {
            if (e.name === 'AbortError') return;
            console.error(e);
            tabState('allocations','error');
        }
    }

    /* ══════════════════════════════════════════════════════════════════
       ALLOCATION MODAL
    ══════════════════════════════════════════════════════════════════ */
    async function openAllocationModal(id=null) {
        const modal = q('modalAllocation'); if (!modal) return;
        S._allocReceiptData = null;

        const body = q('allocModalBody') || q('formAllocation') || modal.querySelector('.cm-modal-body') || modal;

        body.innerHTML = `
<input type="hidden" id="alloc_id" value="${id||''}">
<div class="form-group">
    <label style="font-weight:600;">${t('allocation.receipt_id_label','رقم الإيصال (Payment ID)')} <span class="req" style="color:red;">*</span></label>
    <div style="display:flex;gap:8px;align-items:center;margin-top:6px;">
        <input type="text" id="allocReceiptInput" class="form-control" placeholder="${t('allocation.receipt_placeholder','أدخل رقم الإيصال...')}" style="flex:1;">
        <button type="button" class="btn btn-primary" onclick="CertificateManagement.searchAllocReceipt()">
            <i class="fas fa-search"></i> ${t('common.search','بحث')}
        </button>
    </div>
    <div id="allocReceiptInfo" style="margin-top:8px;display:none;"></div>
    <input type="hidden" id="alloc_receipt_id" value="">
</div>
<div id="allocCertsSection" style="display:none;margin-top:4px;">
    <div class="form-group">
        <label style="font-weight:600;">${t('allocation.approved_requests_label','الطلبات المعتمدة')} <span class="req" style="color:red;">*</span></label>
        <div id="allocCertsList" class="cm-checkable-list" style="max-height:220px;overflow-y:auto;border:1px solid #e0e0e0;border-radius:6px;padding:4px;margin-top:6px;"></div>
        <div style="margin-top:6px;color:#666;font-size:0.85em;"><i class="fas fa-check-circle"></i> <span id="allocSelectedCount">0</span> ${t('common.selected','طلبات محددة')}</div>
    </div>
    <div id="allocCertSummary" class="cm-alert cm-alert-info" style="display:none;margin-top:8px;"></div>
    <div class="form-row" style="margin-top:12px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="form-group">
            <label style="font-weight:600;">${t('allocation.fee_rule_label','قاعدة الرسوم')} <span class="req" style="color:red;">*</span></label>
            <select id="alloc_fee_id" class="form-control" style="margin-top:6px;" onchange="CertificateManagement.onFeeRuleChange()">
                <option value="">${t('allocation.auto_select','-- اختيار تلقائي --')}</option>
            </select>
        </div>
        <div class="form-group">
            <label style="font-weight:600;">${t('allocation.amount_label','المبلغ (AED)')} <span class="req" style="color:red;">*</span></label>
            <input type="number" id="alloc_amount" class="form-control" placeholder="0.00" step="0.01" min="0" style="margin-top:6px;">
        </div>
    </div>
    <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;">
        <button type="button" class="btn btn-outline" onclick="CertificateManagement.closeAllocModal()"><i class="fas fa-times"></i> ${t('common.cancel','إلغاء')}</button>
        <button type="button" id="btnSaveAllocation" class="btn btn-success" onclick="CertificateManagement.saveAllocation()">
            <i class="fas fa-save"></i> ${t('allocation.save_button','حفظ التخصيص وإصدار الشهادات')}
        </button>
    </div>
</div>
<div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;" id="allocCancelOnly">
    <button type="button" class="btn btn-outline" onclick="CertificateManagement.closeAllocModal()"><i class="fas fa-times"></i> ${t('common.cancel','إلغاء')}</button>
</div>`;

        const feeEl = q('alloc_fee_id');
        if (feeEl && S.fees.length) {
            feeEl.innerHTML = `<option value="">${t('allocation.auto_select','-- اختيار تلقائي --')}</option>` +
                S.fees.map(f => `<option value="${f.id}">${esc(f.fee_type||'issue_certificate')} (Max ${f.max_items||'∞'}) - ${f.fee_amount||f.amount||0} AED</option>`).join('');
        } else if (feeEl) {
            try {
                await loadFeesCache();
                feeEl.innerHTML = `<option value="">${t('allocation.auto_select','-- اختيار تلقائي --')}</option>` +
                    S.fees.map(f => `<option value="${f.id}">${esc(f.fee_type||'issue_certificate')} (Max ${f.max_items||'∞'}) - ${f.fee_amount||f.amount||0} AED</option>`).join('');
            } catch(e) { console.warn('fees load:', e.message); }
        }

        modal.style.display = 'flex';
        setTimeout(() => { const inp = q('allocReceiptInput'); if (inp) inp.focus(); }, 100);
    }

    function closeAllocModal() { closeModal('modalAllocation'); }

    async function searchAllocReceipt() {
        const receiptId = q('allocReceiptInput')?.value?.trim();
        if (!receiptId) { toast(t('message.receipt_required','يرجى إدخال رقم إيصال صحيح'), false); return; }

        const infoEl = q('allocReceiptInfo');
        const certsSection = q('allocCertsSection');
        const cancelOnly = q('allocCancelOnly');

        infoEl.style.display = 'block';
        infoEl.innerHTML = `<div class="cm-loading-inline"><i class="fas fa-spinner fa-spin"></i> ${t('common.loading','جارٍ البحث...')}</div>`;

        try {
            const [raw, r1, r2] = await Promise.all([
                apiGet(buildUrl(API_PAY, {payment_reference: receiptId, tenant_id: S.tenantId}), false),
                apiGet(buildUrl(API_REQ, { limit:200, tenant_id: S.tenantId, status:'payment_pending' })),
                apiGet(buildUrl(API_REQ, { limit:200, tenant_id: S.tenantId, status:'approved' }))
            ]);

            const py = extractSingle(raw) || pick(raw)[0];
            if (!py || !py.id) {
                infoEl.innerHTML = `<div class="cm-alert cm-alert-danger"><i class="fas fa-exclamation-circle"></i> ${t('allocation.receipt_not_found','الإيصال غير موجود').replace('{{id}}', receiptId)}</div>`;
                certsSection.style.display = 'none';
                return;
            }

            S._allocReceiptData = py;
            q('alloc_receipt_id').value = py.id;

            infoEl.innerHTML = `
<div class="cm-alert cm-alert-info" style="border-right:4px solid #3498db;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <strong><i class="fas fa-receipt"></i> ${t('allocation.receipt_info','إيصال')} #${py.id}</strong>
        ${payVerifyBadge(py.verification_status)}
    </div>
    <div style="margin-top:6px;display:grid;grid-template-columns:1fr 1fr;gap:4px;font-size:0.9em;">
        <span>${t('allocation.amount','المبلغ')}:</span><strong>${py.amount} ${py.currency||'AED'}</strong>
        <span>${t('allocation.date','التاريخ')}:</span><strong>${py.payment_date ? py.payment_date.split(' ')[0] : '—'}</strong>
        <span>${t('allocation.reference','المرجع')}:</span><strong>${esc(py.payment_reference||'—')}</strong>
        ${py.request_id ? `<span>${t('allocation.request','الطلب')}:</span><strong>#${py.request_id}</strong>` : ''}
    </div>
</div>`;

            const allReqs = [...pick(r1), ...pick(r2)];
            S._allocCertPool = allReqs;
            renderAllocCertList(allReqs);

            certsSection.style.display = 'block';
            if (cancelOnly) cancelOnly.style.display = 'none';
        } catch(e) {
            infoEl.innerHTML = `<div class="cm-alert cm-alert-danger"><i class="fas fa-exclamation-circle"></i> ${t('common.error','خطأ:')} ${esc(e.message)}</div>`;
            certsSection.style.display = 'none';
        }
    }

    function renderAllocCertList(items) {
        const list = q('allocCertsList');
        if (!list) return;
        if (!items.length) {
            list.innerHTML = `<div class="cm-loading-inline" style="color:#888;padding:12px;">${t('allocation.no_approved_requests','لا توجد طلبات معتمدة وغير مُصدرة')}</div>`;
            return;
        }
        const frag = items.map(r => {
            const certType = r.certificate_type === 'gcc'
                ? t('status.gcc', 'خليجي')
                : t('status.non_gcc', 'غير خليجي');
            return `<div class="cm-checkable-item" onclick="var c=this.querySelector('input'); c.checked=!c.checked; CertificateManagement.updateSelectedAllocCount()">
    <input type="checkbox" name="alloc_certs" value="${r.id}" id="chkAlloc_${r.id}" onclick="event.stopPropagation(); CertificateManagement.updateSelectedAllocCount()">
    <div class="cm-checkable-item-info">
        <span class="cm-checkable-item-title">${t('common.request','طلب')} #${r.id} — ${esc(r.importer_name||getEntityName(r.entity_id))}</span>
        <span class="cm-checkable-item-sub">${statusBadge(r.status)} | ${esc(certType)} | ${esc(getEntityName(r.entity_id))}</span>
    </div>
</div>`;
        }).join('');
        list.innerHTML = frag;
    }

    function updateSelectedAllocCount() {
        const selected = document.querySelectorAll('input[name="alloc_certs"]:checked');
        const countEl  = q('allocSelectedCount');
        if (countEl) countEl.textContent = selected.length;
        if (selected.length === 1) {
            onAllocRequestChange(selected[0].value);
        } else {
            const summary = q('allocCertSummary');
            if (summary) {
                if (selected.length > 1) {
                    summary.innerHTML = `<strong>${selected.length} ${t('common.selected','طلبات محددة')}.</strong>`;
                    summary.style.display = 'block';
                } else {
                    summary.style.display = 'none';
                }
            }
        }
    }

    function onFeeRuleChange() {
        const feeEl = q('alloc_fee_id');
        const amtEl = q('alloc_amount');
        if (!feeEl || !amtEl) return;
        const match = feeEl.options[feeEl.selectedIndex]?.text.match(/- ([\d.]+)/);
        if (match) amtEl.value = match[1];
    }

    async function onAllocRequestChange(requestId) {
        if (!requestId) { const s=q('allocCertSummary'); if(s) s.style.display='none'; return; }
        try {
            const rRaw = await apiGet(buildUrl(API_REQ, {id: requestId, tenant_id: S.tenantId}));
            const r    = extractSingle(rRaw) || pick(rRaw)[0];
            const itemsCount = parseInt(r?.items_count||r?.request_items_count||0);
            const rule = findMatchingFeeRule(itemsCount, S.fees);
            if (rule) {
                const feeEl = q('alloc_fee_id'); if (feeEl) feeEl.value = rule.id;
                const amtEl = q('alloc_amount');  if (amtEl) amtEl.value = rule.fee_amount||rule.amount;
            }
            const summary = q('allocCertSummary');
            if (summary) {
                summary.innerHTML = `${t('common.request','طلب')} #${requestId}: <strong>${itemsCount} ${t('common.products','منتجات')}</strong> | ${t('allocation.fee_rule_label','رسوم')}: <strong>${rule?.fee_amount||rule?.amount||0} AED</strong>`;
                summary.style.display = 'block';
            }
        } catch(e) { console.warn('onAllocRequestChange:', e.message); }
    }

    function findMatchingFeeRule(itemsCount, fees) {
        if (!fees||!fees.length) return null;
        const sorted = [...fees].sort((a,b) => {
            const ma = (!a.max_items||a.max_items===0) ? Infinity : parseInt(a.max_items);
            const mb = (!b.max_items||b.max_items===0) ? Infinity : parseInt(b.max_items);
            return ma-mb;
        });
        return sorted.find(f => {
            const max = (!f.max_items||f.max_items===0) ? Infinity : parseInt(f.max_items);
            return itemsCount <= max;
        });
    }

    async function saveAllocation() {
        const receiptId = q('alloc_receipt_id').value;
        const feeId     = q('alloc_fee_id').value;
        const amount    = q('alloc_amount').value;
        const selected  = Array.from(document.querySelectorAll('input[name="alloc_certs"]:checked')).map(el=>el.value);

        if (!receiptId||!selected.length||!feeId||!amount) {
            toast(t('message.select_receipt_and_certs','يرجى اختيار الإيصال والرسوم وطلب واحد على الأقل'), false);
            return;
        }
        if (selected.length>30) { toast(t('allocation.max_limit','الحد الأقصى 30 شهادة لكل تخصيص'), false); return; }

        const btn = q('btnSaveAllocation');
        if (btn) { btn.disabled=true; btn.innerHTML=`<i class="fas fa-spinner fa-spin"></i> ${t('common.loading','جارٍ الحفظ...')}`; }

        let successCount = 0;
        const errors = [];

        try {
            for (const reqId of selected) {
                try {
                    // 1. Create version
                    const vId = await createVersion(reqId);
                    // 2. Create allocation linking payment (receipt) to version
                    // FIX: Send certificate_id = version ID (as required by API)
                    await apiPost(API_ALLOC, { 
                        receipt_id: receiptId, 
                        certificate_id: vId, 
                        fee_id: parseInt(feeId), 
                        allocated_amount: parseFloat(amount) 
                    });
                    // 3. Issue certificate from version
                    const iId = await issueCertFromVersion(vId, reqId);
                    // 4. Update request status to issued and save issued_id
                    if (iId) {
                        await patchRequestStatus(reqId, 'issued', { issued_id: iId });
                    }
                    successCount++;
                } catch(err) {
                    errors.push(`${t('common.request','طلب')} #${reqId}: ${err.message}`);
                }
            }
            CACHE.invalidate(API_ALLOC);
            CACHE.invalidate(API_REQ);
            closeModal('modalAllocation');
            if (successCount>0) toast(t('allocation.success','تم بنجاح: {{count}} شهادة').replace('{{count}}', successCount));
            if (errors.length)  toast(t('allocation.errors','أخطاء: {{errors}}').replace('{{errors}}', errors.join(' | ')), false);
            loadAllocations(S.pages.allocations);
            loadRequests(S.pages.requests);
            refreshStats();
        } finally {
            if (btn) { btn.disabled=false; btn.innerHTML=`<i class="fas fa-save"></i> ${t('allocation.save_button','حفظ التخصيص')}`; }
        }
    }

    async function deleteAllocation(id) {
        if (!confirm(t('message.confirm_delete_allocation','هل أنت متأكد من حذف هذا التخصيص؟'))) return;
        try {
            await apiDel(API_ALLOC, { id });
            CACHE.invalidate(API_ALLOC);
            toast(t('message.allocation_deleted','تم حذف التخصيص'));
            loadAllocations(S.pages.allocations);
        } catch(e) { toast(e.message, false); }
    }

    /* ══════════════════════════════════════════════════════════════════
       SETTINGS
    ══════════════════════════════════════════════════════════════════ */
    async function loadCerts() {
        const body = q('certsTableBody'); if (!body) return;
        body.innerHTML = skeletonRows(5, 3);
        const th = body.closest('table')?.querySelector('thead');
        if (th) th.innerHTML = `<tr><th>ID</th><th>${t('settings.code','الكود')}</th><th>${t('settings.name','الاسم')}</th><th>${t('settings.active','نشط')}</th><th>${t('req_table.actions','إجراءات')}</th></tr>`;
        try {
            const items = pick(await apiGet(API_CERTS));
            body.innerHTML = items.map(c=>`<tr>
<td>${c.id}</td><td>${esc(c.code||'')}</td><td>${esc(c.description||'')}</td>
<td>${c.is_active ? '✅':'❌'}</td>
<td><button class="btn btn-sm btn-outline" onclick="CertificateManagement.openCertModal(${c.id})"><i class="fas fa-edit"></i></button></td>
</tr>`).join('') || `<tr><td colspan="5" class="td-center">${t('req_table.empty_title','No certificates found')}</td></tr>`;
        } catch(e) { console.error(e); }
    }

    async function loadEditions() {
        const body = q('editionsTableBody'); if (!body) return;
        body.innerHTML = skeletonRows(7, 3);
        const th = body.closest('table')?.querySelector('thead');
        if (th) th.innerHTML = `<tr><th>ID</th><th>${t('settings.certificates','الشهادة')}</th><th>${t('settings.code','الكود')}</th><th>${t('settings.language','اللغة')}</th><th>${t('settings.scope','النطاق')}</th><th>${t('settings.version','النسخة')}</th><th>${t('req_table.actions','إجراءات')}</th></tr>`;
        try {
            const items = pick(await apiGet(API_EDI));
            body.innerHTML = items.map(ed=>`<tr>
<td>${ed.id}</td><td>${ed.certificate_id}</td><td>${esc(ed.code||'')}</td>
<td>${esc(ed.language_code||'')}</td><td>${esc(ed.scope||'')}</td><td>${esc(ed.certificate_version||'')}</td>
<td><button class="btn btn-sm btn-outline" onclick="CertificateManagement.openEditionModal(${ed.id})"><i class="fas fa-edit"></i></button></td>
</tr>`).join('') || `<tr><td colspan="7" class="td-center">${t('req_table.empty_title','No editions found')}</td></tr>`;
        } catch(e) { console.error(e); }
    }

    async function loadFees() {
        const body = q('feesTableBody'); if (!body) return;
        body.innerHTML = skeletonRows(6, 3);
        const th = body.closest('table')?.querySelector('thead');
        if (th) th.innerHTML = `<tr><th>ID</th><th>${t('settings.fee_type','نوع الرسم')}</th><th>${t('settings.min_items','الحد الأدنى')}</th><th>${t('settings.max_items','الحد الأقصى')}</th><th>${t('settings.fee_amount','قيمة الرسم')}</th><th>${t('req_table.actions','إجراءات')}</th></tr>`;
        try {
            const items = S.fees.length ? S.fees : pick(await apiGet(API_FEE));
            body.innerHTML = items.map(f=>`<tr>
<td>${f.id}</td><td>${esc(f.fee_type||'')}</td><td>${f.min_items||0}</td><td>${f.max_items||'∞'}</td>
<td>${f.fee_amount||f.amount}</td>
<td><button class="btn btn-sm btn-outline" onclick="CertificateManagement.openFeeModal(${f.id})"><i class="fas fa-edit"></i></button></td>
</tr>`).join('') || `<tr><td colspan="6" class="td-center">${t('req_table.empty_title','No fee rules found')}</td></tr>`;
        } catch(e) { console.error(e); }
    }

    function openCertModal(id) {
        q('certModalTitle').textContent = id ? t('settings.edit_cert','Edit Certificate Type') : t('settings.add_cert','Add Certificate Type');
        q('formCert').reset();
        q('cert_id').value = id||'';
        if (id) {
            apiGet(buildUrl(API_CERTS, {id, tenant_id: S.tenantId})).then(res => {
                const item = res.data||res;
                q('cert_code').value   = item.code||'';
                q('cert_desc').value   = item.description||'';
                q('cert_active').checked = !!item.is_active;
            });
        }
        openModal('modalCert');
    }

    async function saveCert() {
        const d = { id:q('cert_id').value, code:q('cert_code').value, description:q('cert_desc').value, is_active:q('cert_active').checked?1:0 };
        try {
            if (d.id) await apiPut(API_CERTS, d); else await apiPost(API_CERTS, d);
            CACHE.invalidate(API_CERTS);
            closeModal('modalCert');
            toast(t('message.saved','Saved successfully'));
            loadCerts();
        } catch(e) { toast(e.message, false); }
    }

    function openEditionModal(id) {
        q('editionModalTitle').textContent = id ? t('settings.edit_edition','Edit Edition') : t('settings.add_edition','Add Edition');
        q('formEdition').reset();
        q('ed_id').value = id||'';
        const edCert = q('ed_cert_id');
        edCert.innerHTML = S.certificates.map(c=>`<option value="${c.id}">${esc(c.code||c.description)}</option>`).join('');
        if (id) {
            apiGet(buildUrl(API_EDI, {id, tenant_id: S.tenantId})).then(res => {
                const item = res.data||res;
                q('ed_cert_id').value = item.certificate_id||'';
                q('ed_code').value    = item.code||'';
                q('ed_lang').value    = item.language_code||'ar';
                q('ed_ver').value     = item.certificate_version||'';
                q('ed_scope').value   = item.scope||'';
            });
        }
        openModal('modalEdition');
    }

    async function saveEdition() {
        const d = { id:q('ed_id').value, certificate_id:q('ed_cert_id').value, code:q('ed_code').value, language_code:q('ed_lang').value, certificate_version:q('ed_ver').value, scope:q('ed_scope').value };
        try {
            if (d.id) await apiPut(API_EDI, d); else await apiPost(API_EDI, d);
            CACHE.invalidate(API_EDI);
            closeModal('modalEdition');
            toast(t('message.saved','Saved successfully'));
            loadEditions();
        } catch(e) { toast(e.message, false); }
    }

    function openFeeModal(id) {
        q('feeModalTitle').textContent = id ? t('settings.edit_fee','Edit Fee Rule') : t('settings.add_fee','Add Fee Rule');
        q('formFee').reset();
        q('fee_id').value = id||'';
        if (id) {
            apiGet(buildUrl(API_FEE, {id, tenant_id: S.tenantId})).then(res => {
                const item = res.data||res;
                q('fee_type').value   = item.fee_type||'';
                q('fee_min').value    = item.min_items||0;
                q('fee_max').value    = item.max_items||'';
                q('fee_amount').value = item.fee_amount||item.amount||0;
            });
        }
        openModal('modalFee');
    }

    async function saveFee() {
        const d = { id:q('fee_id').value, fee_type:q('fee_type').value, min_items:q('fee_min').value||0, max_items:q('fee_max').value||null, fee_amount:q('fee_amount').value };
        try {
            if (d.id) await apiPut(API_FEE, d); else await apiPost(API_FEE, d);
            CACHE.invalidate(API_FEE);
            S.fees = [];
            await loadFeesCache();
            closeModal('modalFee');
            toast(t('message.saved','Saved successfully'));
            loadFees();
        } catch(e) { toast(e.message, false); }
    }

    /* ══════════════════════════════════════════════════════════════════
       MODAL: REQUEST DETAIL
    ══════════════════════════════════════════════════════════════════ */
    async function viewDetail(id) {
        const modal = q('modalDetail'); if (!modal) return;
        const body  = q('detailModalBody');

        body.innerHTML = `<div class="cm-detail-skeleton">
            <div class="cm-skeleton" style="height:24px;width:40%;margin-bottom:16px;"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                ${Array(6).fill('<div class="cm-skeleton" style="height:18px;margin-bottom:8px;"></div>').join('')}
            </div>
        </div>`;
        modal.style.display = 'flex';
        sd(q('detailModalTitle'), `${t('detail.title','Request Details')} #${id}`);

        try {
            const [,, raw, itemsRaw, auditRaw, payRaw] = await Promise.all([
                ensureProductsLoaded(),
                loadFeesCache(),
                apiGet(buildUrl(API_REQ,    { id, lang:S.lang })),
                apiGet(buildUrl(API_ITEMS,  { request_id:id, limit:200 })),
                apiGet(buildUrl(API_AUDITS, { request_id:id, limit:1 })),
                apiGet(buildUrl(API_PAY,    { request_id:id, limit:10 })),
            ]);

            const req = pick(raw)[0]||(raw?.data?.id ? raw.data : raw?.id ? raw : null);
            if (!req) throw new Error('Not found');
            const items = pick(itemsRaw);
            const a     = pick(auditRaw)[0];
            const pay   = pick(payRaw)[0];

            const auditBtn = q('btnAuditFromDetail');
            if (auditBtn) {
                const canShowAudit = S.perms.canAudit && (req.status==='under_review'||req.status==='draft');
                auditBtn.style.display = canShowAudit ? '' : 'none';
                auditBtn.onclick = () => { closeModal('modalDetail'); openAudit(id); };
            }

            const itemsHtml = items.length ? `
<table class="cm-detail-table"><thead><tr>
  <th>#</th><th>${t('req_table.product','Product')}</th>
  <th>${t('detail.qty','Qty')}</th><th>${t('detail.weight','Net Wt')}</th>
  <th>${t('detail.origin','Country of Origin')}</th>
  <th>${t('detail.prod_date','Prod Date')}</th><th>${t('detail.exp_date','Exp Date')}</th>
</tr></thead><tbody>
${items.map((it,i)=>`<tr><td>${i+1}</td><td>${esc(it.product_name||getProductName(it.product_id))}</td>
  <td>${esc(String(it.quantity||'—'))}</td><td>${esc(String(it.net_weight||'—'))}</td>
  <td><strong>${esc(it.country_of_origin||'—')}</strong></td>
  <td>${it.production_date||'—'}</td><td>${it.expiry_date||'—'}</td></tr>`).join('')}
</tbody></table>` : `<p class="cm-no-items">${t('detail.no_items','No items')}</p>`;

            const auditorSelectHtml = S.perms.canAudit ? `
<div class="cm-detail-actions-bar">
    <div class="cm-detail-row" style="align-items:center; gap:10px;">
        <label style="white-space:nowrap">${t('detail.assign_auditor','Assign Auditor')}:</label>
        <select class="form-control" id="detailAuditorSelect" onchange="CertificateManagement.updateAuditor(${req.id}, this.value)">
            <option value="">-- ${t('common.select','Select')} --</option>
            ${S.tenantUsers.map(u=>`<option value="${u.id||u.user_id}" ${req.auditor_user_id==(u.id||u.user_id)?'selected':''}>${esc(u.name||u.username||u.email)}</option>`).join('')}
        </select>
    </div>
</div>` : '';

            body.innerHTML = `
<div class="cm-detail-grid">
  <div class="cm-detail-block">
    <div class="cm-detail-row"><span>${t('req_table.entity','Entity')}</span><strong>${esc(getEntityName(req.entity_id))}</strong></div>
    <div class="cm-detail-row"><span>${t('req_table.certificate','Certificate')}</span><strong>${esc(getCertName(req.certificate_id))}</strong></div>
    <div class="cm-detail-row"><span>${t('req_table.status','Status')}</span>${statusBadge(req.status)}</div>
    <div class="cm-detail-row"><span>${t('req_table.auditor','Auditor')}</span><span>${esc(getUserName(req.auditor_user_id))}</span></div>
  </div>
  <div class="cm-detail-block">
    <div class="cm-detail-row"><span>${t('detail.importer_name','Importer')}</span><strong>${esc(req.importer_name||'—')}</strong></div>
    <div class="cm-detail-row"><span>${t('detail.importer_addr','Address')}</span><span>${esc(req.importer_address||'—')}</span></div>
    <div class="cm-detail-row"><span>${t('detail.transport','Transport')}</span><span>${esc(req.transport_method||'—')}</span></div>
    <div class="cm-detail-row"><span>${t('detail.gcc','Type')}</span><span>${esc(req.certificate_type||'—')}</span></div>
    <div class="cm-detail-row"><span>${t('detail.operation','Operation')}</span><span>${esc(req.operation_type||'—')}</span></div>
  </div>
</div>
${auditorSelectHtml}
${a ? `<div class="cm-detail-audit" style="margin-top:15px;">
  <div class="cm-section-label"><i class="fas fa-search"></i> ${t('detail.last_audit','Last Audit')}</div>
  <div class="cm-detail-row"><span>${t('req_table.status','Status')}</span>${auditBadge(a.status)}</div>
  <div class="cm-detail-row"><span>${t('audit.form.audit_date','Date')}</span><span>${a.audit_date ? new Date(a.audit_date).toLocaleString():'—'}</span></div>
  <div class="cm-detail-row"><span>${t('audit.form.notes','Notes')}</span><span>${esc(a.notes||'—')}</span></div>
</div>` : ''}
${pay ? `<div class="cm-detail-payment" style="margin-top:15px;">
  <div class="cm-section-label"><i class="fas fa-credit-card"></i> ${t('detail.payment','Payment')}</div>
  <div class="cm-detail-row"><span>${t('pay.form.amount','Amount')}</span><strong>${esc(String(pay.amount))} ${esc(pay.currency)}</strong></div>
  <div class="cm-detail-row"><span>${t('req_table.status','Status')}</span>${payVerifyBadge(pay.verification_status)}</div>
  <div class="cm-detail-row"><span>${t('pay.form.reference','Reference')}</span><span>${esc(pay.payment_reference||'—')}</span></div>
</div>` : ''}
<div class="cm-section-label" style="margin-top:15px;"><i class="fas fa-boxes"></i> ${t('detail.items_list','Product Details')}</div>
${itemsHtml}
${S.perms.canAudit && (req.status==='under_review'||req.status==='draft') ? `
<div style="margin-top:20px; padding-top:15px; border-top:1px solid var(--border-color);">
    <div class="cm-section-label"><i class="fas fa-search"></i> ${t('audit.modal.title','Conduct Audit')}</div>
    ${buildInlineAuditForm(req, a)}
</div>` : ''}`;

        } catch(e) {
            body.innerHTML = `<div class="error-state"><i class="fas fa-exclamation-triangle"></i> ${esc(e.message)}</div>`;
        }
    }

    function buildInlineAuditForm(req, existingAudit) {
        const auditId  = existingAudit?.id||'';
        const status   = existingAudit?.status||'approved';
        const notes    = existingAudit?.notes||'';
        const dateVal  = existingAudit?.audit_date ? existingAudit.audit_date.slice(0,16) : new Date().toISOString().slice(0,16);
        const auditorVal = req.auditor_user_id||'';
        const usersOptions = S.tenantUsers.map(u=>`<option value="${u.id||u.user_id}" ${auditorVal==(u.id||u.user_id)?'selected':''}>${esc(u.name||u.username||u.email)}</option>`).join('');

        return `
<form id="inlineAuditForm" novalidate>
    <input type="hidden" id="inlineAuditId" value="${auditId}">
    <input type="hidden" id="inlineAuditReqId" value="${req.id}">
    <div class="form-row">
        <div class="form-group">
            <label>${t('audit.form.status','Decision')} <span class="req">*</span></label>
            <select id="inlineAuditStatus" class="form-control">
                <option value="approved" ${status==='approved'?'selected':''}>${t('audit.status.approved','Approve')}</option>
                <option value="rejected" ${status==='rejected'?'selected':''}>${t('audit.status.rejected','Reject')}</option>
            </select>
        </div>
        <div class="form-group">
            <label>${t('audit.form.audit_date','Audit Date')} <span class="req">*</span></label>
            <input type="datetime-local" id="inlineAuditDate" class="form-control" value="${dateVal}">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>${t('audit.form.assign_auditor','Auditor')}</label>
            <select id="inlineAuditAuditor" class="form-control">
                <option value="">-- ${t('common.select','Select')} --</option>
                ${usersOptions}
            </select>
        </div>
        <div class="form-group">
            <label>${t('audit.form.notes','Notes')}</label>
            <textarea id="inlineAuditNotes" class="form-control" rows="2">${esc(notes)}</textarea>
        </div>
    </div>
    <div style="margin-top:10px;">
        <button type="button" class="btn btn-primary" onclick="CertificateManagement.submitInlineAudit()">
            <i class="fas fa-check"></i> ${t('audit.form.submit','Submit Audit')}
        </button>
    </div>
</form>`;
    }

    async function submitInlineAudit() {
        const reqIdInput = q('inlineAuditReqId');
        if (!reqIdInput) {
            toast(t('message.error', 'Audit form error: missing request ID'), false);
            return;
        }
        const reqIdVal = reqIdInput.value.trim();
        if (!reqIdVal || !/^\d+$/.test(reqIdVal)) {
            toast(t('message.error', 'Invalid request ID'), false);
            return;
        }
        const reqId = parseInt(reqIdVal, 10);

        const auditId = parseInt(q('inlineAuditId')?.value||'0');
        const status  = q('inlineAuditStatus')?.value;
        const date    = q('inlineAuditDate')?.value;
        const notes   = q('inlineAuditNotes')?.value||'';
        const assign  = q('inlineAuditAuditor')?.value;

        if (!status||!date) { toast(t('common.validation','Fill required fields'), false); return; }

        let auditorId = assign ? parseInt(assign) : null;
        if (!auditorId) {
            auditorId = window.APP_CONFIG?.USER_ID;
            if (!auditorId||auditorId<=0) { toast(t('message.no_auditor_selected','يرجى اختيار مدقق'), false); return; }
        }

        try {
            const reqRaw = await apiGet(buildUrl(API_REQ, {id: reqId, tenant_id: S.tenantId}), false);
            const rData  = extractSingle(reqRaw) || pick(reqRaw)[0];
            if (!rData) throw new Error('Could not fetch request data');

            const formattedDate = date.replace('T',' ')+':00';
            const auditPayload  = { request_id:reqId, auditor_id:auditorId, audit_date:formattedDate, status, notes };

            if (auditId) { auditPayload.id=auditId; await apiPut(API_AUDITS, auditPayload); }
            else         { await apiPost(API_AUDITS, auditPayload); }

            const newStatus = status==='approved' ? 'payment_pending' : 'rejected';
            await patchRequestStatus(reqId, newStatus, { auditor_user_id: auditorId });

            // No version creation or issuance here – done only in allocation

            CACHE.invalidate(API_REQ);
            CACHE.invalidate(API_AUDITS);
            CACHE.invalidate(API_PAY);

            closeModal('modalDetail');
            toast(t('audit.success','Audit submitted successfully'));
            loadRequests(S.pages.requests);
            if (S.activeTab==='audits')   loadAudits(S.pages.audits);
            if (S.activeTab==='payments') loadPayments(S.pages.payments);
            refreshStats();
        } catch(e) { toast(t('common.error','Error: ')+e.message, false); }
    }

    /* ══════════════════════════════════════════════════════════════════
       MODAL: AUDIT (standalone)
    ══════════════════════════════════════════════════════════════════ */
    async function openAudit(requestId) {
        const modal = q('modalAudit'); if (!modal) return;
        S.currentAuditRequestId = requestId;

        const dtInput = q('auditFormDate');
        if (dtInput) dtInput.value = new Date().toISOString().slice(0,16);
        const sf = q('auditFormStatus'); if (sf) sf.value='approved';
        const nf = q('auditFormNotes');  if (nf) nf.value='';
        q('auditFormRequestId').value = requestId;
        q('auditFormId').value='';

        modal.style.display='flex';

        try {
            const [reqRaw, itemsRaw, auditsRaw] = await Promise.all([
                apiGet(buildUrl(API_REQ,    { id:requestId, lang:S.lang })),
                apiGet(buildUrl(API_ITEMS,  { request_id:requestId, limit:200 })),
                apiGet(buildUrl(API_AUDITS, { request_id:requestId, limit:1 }))
            ]);

            const req = pick(reqRaw)[0]||(reqRaw?.id?reqRaw:null);
            if (req) {
                q('auditRequestSummary').innerHTML = `
<div class="cm-summary-row"><i class="fas fa-building"></i> <strong>${esc(getEntityName(req.entity_id))}</strong></div>
<div class="cm-summary-row"><i class="fas fa-certificate"></i> ${esc(getCertName(req.certificate_id))}</div>
<div class="cm-summary-row"><i class="fas fa-user-tie"></i> ${esc(req.importer_name||'—')}</div>
<div class="cm-summary-row">${statusBadge(req.status)}</div>`;
                const as = q('auditAssignUser');
                if (as && req.auditor_user_id) as.value=req.auditor_user_id;
            }

            const items = pick(itemsRaw);
            q('auditItemsList').innerHTML = items.length
                ? `<table class="cm-detail-table"><thead><tr><th>#</th><th>${t('req_table.product','Product')}</th><th>${t('detail.qty','Qty')}</th><th>${t('detail.weight','Net Wt')}</th></tr></thead>
<tbody>${items.map((it,i)=>`<tr><td>${i+1}</td><td>${esc(getProductName(it.product_id))}</td><td>${esc(String(it.quantity||'—'))}</td><td>${esc(String(it.net_weight||'—'))}</td></tr>`).join('')}</tbody></table>`
                : `<p class="cm-no-items">${t('detail.no_items','No items')}</p>`;

            const audits = pick(auditsRaw);
            if (audits[0]) {
                q('auditFormId').value = audits[0].id;
                if (sf) sf.value = audits[0].status||'approved';
                if (nf) nf.value = audits[0].notes||'';
                if (dtInput&&audits[0].audit_date) dtInput.value=audits[0].audit_date.slice(0,16);
            }
        } catch(e) { console.warn('openAudit load:', e.message); }
    }

    async function submitAudit() {
        const reqId = S.currentAuditRequestId;
        if (!reqId || typeof reqId !== 'number' || reqId <= 0) {
            toast(t('message.error', 'No request selected for audit'), false);
            return;
        }

        const btn     = q('btnSubmitAudit');
        const auditId = parseInt(q('auditFormId')?.value||'0');
        const status  = q('auditFormStatus')?.value;
        const date    = q('auditFormDate')?.value;
        const notes   = q('auditFormNotes')?.value||'';
        const assign  = q('auditAssignUser')?.value;

        if (!status||!date) { toast(t('common.validation','Fill required fields'), false); return; }

        let auditorId = assign ? parseInt(assign) : null;
        if (!auditorId||auditorId<=0) {
            auditorId = window.APP_CONFIG?.USER_ID;
            if (!auditorId||auditorId<=0) { toast(t('message.no_auditor_selected','No auditor selected'), false); return; }
        }

        if (btn) { btn.disabled=true; btn.innerHTML=`<i class="fas fa-spinner fa-spin"></i>`; }

        try {
            const reqRaw = await apiGet(buildUrl(API_REQ, {id: reqId, tenant_id: S.tenantId}), false);
            const rData  = extractSingle(reqRaw) || pick(reqRaw)[0];
            if (!rData) throw new Error('Could not fetch existing request data');

            const formattedDate = date.replace('T',' ')+':00';
            const auditPayload  = { request_id:reqId, auditor_id:auditorId, audit_date:formattedDate, status, notes };

            if (auditId) { auditPayload.id=auditId; await apiPut(API_AUDITS, auditPayload); }
            else         { await apiPost(API_AUDITS, auditPayload); }

            const newStatus = status==='approved' ? 'payment_pending' : 'rejected';
            await patchRequestStatus(reqId, newStatus, { auditor_user_id: auditorId });

            // No version creation or issuance here – done only in allocation

            CACHE.invalidate(API_REQ);
            CACHE.invalidate(API_AUDITS);
            CACHE.invalidate(API_PAY);

            closeModal('modalAudit');
            toast(t('audit.success','Audit submitted successfully'));
            loadRequests(S.pages.requests);
            if (S.activeTab==='audits')   loadAudits(S.pages.audits);
            if (S.activeTab==='payments') loadPayments(S.pages.payments);
            refreshStats();
        } catch(e) {
            toast(t('common.error','Error: ')+e.message, false);
        } finally {
            if (btn) { btn.disabled=false; btn.innerHTML=`<i class="fas fa-check"></i> ${t('audit.form.submit','Submit Audit')}`; }
        }
    }

    /* ══════════════════════════════════════════════════════════════════
       MODAL: PAYMENT
    ══════════════════════════════════════════════════════════════════ */
    async function openAddPayment(reqId) {
        const modal = q('modalPayment'); if (!modal) return;
        S.currentPaymentId   = null;
        S.currentPayEntityId = null;
        S.currentPayReqId    = reqId || null;

        q('payFormId').value        = '';
        q('payFormRequestId').value = reqId||'';
        const rf=q('payFormRef');    if(rf) rf.value='AUTO';
        const df=q('payFormDate');   if(df) df.value=new Date().toISOString().split('T')[0];
        const tf=q('payFormType');   if(tf) tf.value='initial';
        const af=q('payFormAmount'); if(af) af.value='';
        const cf=q('payFormCurrency');if(cf) cf.value='AED';
        const sf=q('payFormStatus'); if(sf) sf.value='waiting_verification';
        const nf=q('payFormNotes');  if(nf) nf.value='';

        q('paymentDetails').innerHTML = `<div class="cm-alert cm-alert-info">${t('pay.modal.add_title','إنشاء سجل دفع جديد')}${reqId ? ` ${t('common.for','لـ')} #${reqId}` : ''}</div>`;
        modal.style.display='flex';

        if (reqId) {
            try {
                const reqRaw = await apiGet(buildUrl(API_REQ, {id: reqId, tenant_id: S.tenantId}));
                const r = extractSingle(reqRaw);
                if (r) {
                    S.currentPayEntityId = r.entity_id;
                    const itemsCount = r.items_count||r.request_items_count||0;
                    const rule = findMatchingFeeRule(itemsCount, S.fees);
                    if (rule && af) af.value = rule.fee_amount||rule.amount||'';
                    q('paymentDetails').innerHTML = `<div class="cm-alert cm-alert-info">${t('common.request','طلب')} #${reqId} | ${t('req_table.entity','منشأة')}: ${esc(getEntityName(r.entity_id))} | ${t('detail.importer_name','مستورد')}: ${esc(r.importer_name||'—')}</div>`;
                }
            } catch(e) { console.warn('openAddPayment fetch:', e.message); }
        }
    }

    async function openPaymentVerify(payId) {
        const modal = q('modalPayment'); if (!modal) return;
        S.currentPaymentId   = payId;
        S.currentPayEntityId = null;
        S.currentPayReqId    = null;

        q('payFormId').value = payId;
        q('payFormRequestId').value = '';
        const sf=q('payFormStatus'); if(sf) sf.value='verified';
        ['payFormNotes','payFormRef','payFormDate'].forEach(id => { const el=q(id); if(el) el.value=''; });
        const tf=q('payFormType');   if(tf) tf.value='initial';
        const af=q('payFormAmount'); if(af) af.value='';
        const cf=q('payFormCurrency');if(cf) cf.value='AED';

        q('paymentDetails').innerHTML = `<div class="cm-loading-inline"><i class="fas fa-spinner fa-spin"></i> ${t('common.loading','جارٍ التحميل...')}</div>`;
        modal.style.display='flex';

        try {
            const raw = await apiGet(buildUrl(API_PAY, {id: payId, tenant_id: S.tenantId}), false);
            const py  = extractSingle(raw);
            if (py) {
                S.currentPayEntityId = py.entity_id;
                S.currentPayReqId    = py.request_id;
                const rf=q('payFormRef');    if(rf) rf.value = py.payment_reference||'';
                const df=q('payFormDate');   if(df) df.value = py.payment_date ? py.payment_date.split(' ')[0] : '';
                const tf=q('payFormType');   if(tf) tf.value = py.payment_type||'initial';
                const af=q('payFormAmount'); if(af) af.value = py.amount||'';
                const cf=q('payFormCurrency');if(cf) cf.value = py.currency||'AED';
                if(sf) sf.value = py.verification_status||'verified';
                const nf=q('payFormNotes');  if(nf) nf.value = py.notes||'';

                q('paymentDetails').innerHTML = `
<div class="cm-detail-grid">
  <div class="cm-detail-row"><span>${t('common.request','Request')}</span><strong>#${esc(String(py.request_id))}</strong></div>
  <div class="cm-detail-row"><span>${t('pay.form.amount','Amount')}</span><strong>${esc(String(py.amount))} ${esc(py.currency)}</strong></div>
  <div class="cm-detail-row"><span>${t('req_table.status','Status')}</span>${payVerifyBadge(py.verification_status)}</div>
  ${py.receipt_file ? `<div class="cm-detail-row"><span>${t('pay.form.receipt','Receipt')}</span><a href="${esc(py.receipt_file)}" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-file-alt"></i> ${t('common.view','View')}</a></div>` : ''}
</div>`;
            }
        } catch(e) { console.warn('openPaymentVerify:', e.message); }
    }

    async function submitPaymentVerify() {
        const btn    = q('btnSubmitPay');
        const payId  = S.currentPaymentId;
        const status = q('payFormStatus')?.value;
        const notes  = q('payFormNotes')?.value||'';
        const reqId  = q('payFormRequestId')?.value || S.currentPayReqId;

        if (!status) { toast(t('common.validation','Fill required fields'), false); return; }

        const currentUserId = parseInt(
            window.APP_CONFIG?.USER_ID || window.ADMIN_CONFIG?.USER_ID ||
            document.querySelector('meta[name="user-id"]')?.content || 0
        ) || null;

        if (btn) { btn.disabled=true; btn.innerHTML=`<i class="fas fa-spinner fa-spin"></i>`; }

        try {
            const payDate    = q('payFormDate')?.value;
            const payRef     = q('payFormRef')?.value?.trim() || `REF-${Date.now()}`;
            const payDateFmt = payDate
                ? (payDate.includes(' ') ? payDate : payDate + ' 00:00:00')
                : new Date().toISOString().slice(0,19).replace('T',' ');

            const payload = {
                payment_reference:   payRef,
                payment_date:        payDateFmt,
                payment_type:        q('payFormType')?.value||'initial',
                amount:              q('payFormAmount')?.value||0,
                currency:            q('payFormCurrency')?.value||'AED',
                verification_status: status,
                verified_by:  (status==='verified' && currentUserId) ? currentUserId : null,
                verified_at:  (status==='verified') ? new Date().toISOString().slice(0,19).replace('T',' ') : null,
                notes,
            };

            if (payId) {
                payload.id = payId;
                await apiPut(API_PAY, payload);
                if (status==='verified') {
                    const targetReqId = S.currentPayReqId || reqId;
                    if (targetReqId) {
                        try { await patchRequestStatus(targetReqId, 'approved'); }
                        catch(re) { console.warn('patchRequestStatus after verify:', re.message); }
                    }
                }
            } else {
                if (!reqId) throw new Error('Request ID is required for new payment');
                const entityId = S.currentPayEntityId;
                if (!entityId) throw new Error(t('message.error','Entity ID could not be resolved. Please re-open the payment form.'));
                payload.request_id = reqId;
                payload.entity_id  = entityId;
                payload.tenant_id  = S.tenantId;
                await apiPost(API_PAY, payload);
            }

            CACHE.invalidate(API_PAY);
            CACHE.invalidate(API_REQ);

            closeModal('modalPayment');
            toast(t('pay.success','Payment saved successfully'));
            if (S.activeTab==='payments') loadPayments(S.pages.payments);
            loadRequests(S.pages.requests);
            refreshStats();
        } catch(e) {
            toast((t('common.error','Error: '))+e.message, false);
        } finally {
            if (btn) { btn.disabled=false; btn.innerHTML=`<i class="fas fa-check-circle"></i> ${t('pay.form.submit','Confirm')}`; }
        }
    }

    /* ── Request sanitize + patch ────────────────────────────────────── */
    function sanitizeRequest(data) {
        const allowed = [
            'id','tenant_id','entity_id','certificate_type','operation_type',
            'description','importer_name','importer_address','importer_country_id',
            'issue_date','transport_method','notes','status',
            'issued_id','shipment_condition','certificate_id',
            'certificate_edition_id','auditor_user_id'
        ];
        const d = {};
        allowed.forEach(k => { if (data[k] !== undefined) d[k] = data[k]; });
        const sc = parseInt(d.shipment_condition);
        d.shipment_condition = (sc===1||sc===2||sc===3) ? sc : 1;
        return d;
    }

    async function patchRequestStatus(reqId, newStatus, extraFields={}) {
        const reqRaw = await apiGet(buildUrl(API_REQ, {id: reqId, tenant_id: S.tenantId}), false);
        const rData  = extractSingle(reqRaw) || pick(reqRaw)[0];
        if (!rData) throw new Error(`Cannot fetch request #${reqId}`);

        const cleanExtra = Object.fromEntries(
            Object.entries(extraFields).filter(([, v]) => v !== null && v !== undefined)
        );

        const patch = sanitizeRequest({
            ...rData,
            ...(newStatus ? { status: newStatus } : {}),
            ...cleanExtra
        });
        await apiPut(API_REQ, patch);
        CACHE.invalidate(API_REQ);
        return patch;
    }

    /* ── Version + Issue ─────────────────────────────────────────────── */
    async function createVersion(reqId, auditorId=null) {
        const userId = auditorId||(window.APP_CONFIG?.USER_ID&&window.APP_CONFIG.USER_ID!=0 ? window.APP_CONFIG.USER_ID : 1);
        const vRes = await apiPost(API_VERSIONS, {
            request_id:reqId, version_number:1, version_reason:'system_update',
            is_active:1, approved_by:userId, auditor_user_id:userId,
            approved_at:new Date().toISOString().slice(0,19).replace('T',' ')
        });
        const vId = vRes?.data?.id||vRes?.data||vRes?.id||vRes;
        if (!vId||typeof vId==='object') throw new Error('Failed to create version');
        return vId;
    }

    async function issueCertFromVersion(vId, reqId) {
        const userId = (window.APP_CONFIG?.USER_ID&&window.APP_CONFIG.USER_ID!=0) ? window.APP_CONFIG.USER_ID : 1;
        const now    = new Date();
        const expiry = new Date(); expiry.setFullYear(now.getFullYear()+1);
        const certNo = `CERT-${reqId}-${now.getFullYear()}${String(now.getMonth()+1).padStart(2,'0')}${String(now.getDate()).padStart(2,'0')}-${Math.floor(1000+Math.random()*9000)}`;
        const vCode  = `VC-${Math.random().toString(36).substring(2,10).toUpperCase()}${Math.random().toString(36).substring(2,10).toUpperCase()}`;
        const lang   = S.lang || 'ar';
        const iRes = await apiPost(API_ISSUED, {
            version_id:vId, certificate_number:certNo,
            issued_at:now.toISOString().slice(0,19).replace('T',' '),
            printable_until:expiry.toISOString().slice(0,19).replace('T',' '),
            verification_code:vCode, issued_by:userId,
            language_code:lang,
            pdf_path:`/api/print_certificate?id=${reqId}&lang=${encodeURIComponent(lang)}`
        });
        CACHE.invalidate(API_ISSUED);
        const issuedId = iRes?.data?.id||iRes?.data||iRes?.id||iRes;
        // Generate QR + PDF files server-side (fire-and-forget; Chromium may take a few seconds)
        if (issuedId) {
            fetch('/api/generate_certificate_files', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ issued_id: issuedId, request_id: reqId, lang })
            }).catch(() => {});
        }
        return issuedId;
    }

    async function issueCertificate(id) {
        if (!confirm(t('message.confirm_issue','Generate certificate and mark as issued?'))) return;
        try {
            const reqRaw = await apiGet(buildUrl(API_REQ, {id, tenant_id: S.tenantId}), false);
            const req    = pick(reqRaw)[0]||(reqRaw?.data||reqRaw);
            if (!req) throw new Error('Request not found');
            const vId = await createVersion(id);
            const iId = await issueCertFromVersion(vId, id);
            const userId = (window.APP_CONFIG?.USER_ID&&window.APP_CONFIG.USER_ID!=0) ? window.APP_CONFIG.USER_ID : 1;
            const finalReqData = sanitizeRequest({ ...req, status:'issued', issued_id:iId, auditor_user_id:userId });
            await apiPut(API_REQ, finalReqData);
            CACHE.invalidate(API_REQ);
            toast(t('message.issued_success','Certificate issued successfully'));
            loadRequests(S.pages.requests);
            if (S.activeTab==='issued') loadIssued(1);
            refreshStats();
        } catch(e) { toast(e.message, false); }
    }

    /* ── Modals ──────────────────────────────────────────────────────── */
    function openModal(id) { const m=q(id); if(m) m.style.display='flex'; }
    function closeModal(id){ const m=q(id); if(m) m.style.display='none'; }


    function tabState(tab, state) {
        const prefix = { requests:'req', audits:'audit', payments:'pay', issued:'issued', logs:'log', allocations:'alloc' }[tab];
        if (!prefix) return;
        ['Loading','Table','Empty'].forEach(suf => {
            const el = q(`${prefix}${suf}`);
            if (!el) return;
            if (suf==='Loading') el.style.display = state==='loading' ? 'flex' : 'none';
            else if (suf==='Table') el.style.display = state==='table' ? 'block' : 'none';
            else if (suf==='Empty') el.style.display = state==='empty' ? 'flex' : 'none';
        });
    }

    function renderPager(prefix, meta, loadFn) {
        const info  = q(`${prefix}PaginationInfo`);
        const pager = q(`${prefix}Pagination`);
        if (info) sd(info, `${meta.from||0}–${meta.to||0} / ${meta.total||0}`);
        if (pager) {
            const pages = meta.last_page||Math.ceil(meta.total/S.perPage)||1;
            const page  = meta.page||1;
            let html='';
            if (page>1)     html+=`<button class="pagination-btn" onclick="(window.${loadFn.name}||CertificateManagement.noop)(${page-1})"><i class="fas fa-chevron-left"></i></button>`;
            html+=`<span class="pagination-btn active">${page} / ${pages}</span>`;
            if (page<pages) html+=`<button class="pagination-btn" onclick="(window.${loadFn.name}||CertificateManagement.noop)(${page+1})"><i class="fas fa-chevron-right"></i></button>`;
            pager.innerHTML = html;
        }
    }

    function updateStatBadge(id, n) {
        const el = q(id); if (!el) return;
        el.textContent = n ? String(n) : '';
        el.style.display = n ? '' : 'none';
    }

    /* ── Tab switching ───────────────────────────────────────────────── */
    function switchTab(name) {
        S.activeTab = name;
        document.querySelectorAll('.cm-tab').forEach(bt=>bt.classList.toggle('active', bt.dataset.tab===name));
        document.querySelectorAll('.cm-tab-panel').forEach(p=>{
            const isTarget = p.id===`panel${name.charAt(0).toUpperCase()+name.slice(1)}`;
            p.classList.toggle('active', isTarget);
            p.style.display = isTarget?'block':'none';
        });
        const loaders = {
            requests:loadRequests, audits:loadAudits, payments:loadPayments,
            issued:loadIssued, logs:loadLogs, allocations:loadAllocations,
            settings:()=>switchSubTab('certs')
        };
        if (loaders[name]) loaders[name](1);
    }

    function switchSubTab(sub) {
        document.querySelectorAll('.settings-nav-item').forEach(el=>el.classList.toggle('active', el.dataset.sub===sub));
        document.querySelectorAll('.settings-sub-panel').forEach(p=>{
            const isTarget=p.id===`subPanel${sub.charAt(0).toUpperCase()+sub.slice(1)}`;
            p.classList.toggle('active', isTarget);
            p.style.display=isTarget?'block':'none';
        });
        if (sub==='certs')    loadCerts();
        if (sub==='editions') loadEditions();
        if (sub==='fees')     loadFees();
    }

    /* ── Bind events ─────────────────────────────────────────────────── */
    function bindEvents() {
        document.querySelectorAll('.cm-tab').forEach(btn=>btn.addEventListener('click', ()=>switchTab(btn.dataset.tab)));
        document.querySelectorAll('.settings-nav-item').forEach(el=>el.addEventListener('click', ()=>switchSubTab(el.dataset.sub)));

        const filterBind = (applyId, resetId, filterKeys, filterState, loadFn) => {
            q(applyId)?.addEventListener('click', () => {
                const f={};
                Object.entries(filterKeys).forEach(([k,id])=>{ const el=q(id); if(el) f[k]=el.value; });
                S.filters[filterState]=f;
                CACHE.invalidate(filterState === 'requests' ? API_REQ :
                                 filterState === 'audits'   ? API_AUDITS :
                                 filterState === 'payments' ? API_PAY :
                                 filterState === 'issued'   ? API_ISSUED :
                                 filterState === 'logs'     ? API_LOGS : API_ALLOC);
                loadFn(1);
            });
            q(resetId)?.addEventListener('click', () => {
                Object.values(filterKeys).forEach(id=>{ const el=q(id); if(el) el.value=''; });
                S.filters[filterState]={};
                loadFn(1);
            });
        };

        filterBind('btnReqFilter','btnReqReset',{ search:'reqSearch', entity:'reqEntityFilter', status:'reqStatusFilter', auditor:'reqAuditorFilter', tenant:'reqTenantFilter' },'requests', loadRequests);
        filterBind('btnAuditFilter','btnAuditReset',{ request_id:'auditReqIdFilter', status:'auditStatusFilter' },'audits', loadAudits);
        filterBind('btnPayFilter','btnPayReset',{ request_id:'payReqIdFilter', verify_status:'payStatusFilter' },'payments', loadPayments);
        filterBind('btnIssuedFilter','btnIssuedReset',{ search:'issuedSearchFilter', cancelled:'issuedCancelledFilter' },'issued', loadIssued);
        filterBind('btnLogFilter','btnLogReset',{ request_id:'logReqIdFilter', action:'logActionFilter' },'logs', loadLogs);
        filterBind('btnAllocFilter','btnAllocReset',{ receipt_id:'allocReceiptFilter', certificate_id:'allocCertFilter' },'allocations', loadAllocations);

        const debouncedSearch = debounce(() => q('btnReqFilter')?.click(), 400);
        q('reqSearch')?.addEventListener('input', debouncedSearch);
        q('reqSearch')?.addEventListener('keydown', e => { if(e.key==='Enter') { debouncedSearch.cancel?.(); q('btnReqFilter')?.click(); } });

        document.addEventListener('keydown', e => {
            if (e.target?.id === 'allocReceiptInput' && e.key === 'Enter') {
                e.preventDefault(); searchAllocReceipt();
            }
        });

        q('btnSubmitAudit')?.addEventListener('click', submitAudit);
        q('btnCancelAudit')?.addEventListener('click', ()=>closeModal('modalAudit'));
        q('btnCloseAuditModal')?.addEventListener('click', ()=>closeModal('modalAudit'));
        q('btnSubmitPay')?.addEventListener('click', submitPaymentVerify);
        q('btnCancelPay')?.addEventListener('click', ()=>closeModal('modalPayment'));
        q('btnClosePayModal')?.addEventListener('click', ()=>closeModal('modalPayment'));
        q('btnCloseDetail')?.addEventListener('click', ()=>closeModal('modalDetail'));
        q('btnCloseDetailFooter')?.addEventListener('click', ()=>closeModal('modalDetail'));

        ['modalAudit','modalPayment','modalDetail','modalCert','modalEdition','modalFee','modalAllocation'].forEach(id=>{
            const m=q(id);
            if(m) m.addEventListener('click', e=>{ if(e.target===m) closeModal(id); });
        });

        q('allocCertSearch')?.addEventListener('input', e=>{
            const val = e.target.value.toLowerCase();
            const filtered = (S._allocCertPool||[]).filter(r =>
                String(r.id).includes(val) ||
                (r.importer_name||'').toLowerCase().includes(val) ||
                getEntityName(r.entity_id).toLowerCase().includes(val)
            );
            renderAllocCertList(filtered);
        });
    }

    /* ── Init ────────────────────────────────────────────────────────── */
    async function init() {
        const [,] = await Promise.all([
            loadI18n(),
            loadLookups()
        ]);

        applyI18n();
        translateStaticUI();

        bindEvents();
        loadRequests(1);
        refreshStats();

        if (window.requestIdleCallback) {
            requestIdleCallback(() => ensureProductsLoaded(), { timeout: 3000 });
        } else {
            setTimeout(ensureProductsLoaded, 2000);
        }
    }

    function translateStaticUI() {
        const map = {
            'btnReqFilter':    t('filters.apply','تطبيق'),
            'btnReqReset':     t('filters.reset','إعادة تعيين'),
            'btnAuditFilter':  t('filters.apply','تطبيق'),
            'btnAuditReset':   t('filters.reset','إعادة تعيين'),
            'btnPayFilter':    t('filters.apply','تطبيق'),
            'btnPayReset':     t('filters.reset','إعادة تعيين'),
            'btnIssuedFilter': t('filters.apply','تطبيق'),
            'btnIssuedReset':  t('filters.reset','إعادة تعيين'),
            'btnLogFilter':    t('filters.apply','تطبيق'),
            'btnLogReset':     t('filters.reset','إعادة تعيين'),
            'btnAllocFilter':  t('filters.apply','تطبيق'),
            'btnAllocReset':   t('filters.reset','إعادة تعيين'),
        };
        Object.entries(map).forEach(([id, text]) => {
            const el = q(id);
            if (el && text) el.textContent = text;
        });

        applyI18n();

        const statusSel = q('reqStatusFilter');
        if (statusSel) {
            const opts = {
                '': t('filters.all','الكل'),
                'draft': t('status.draft','مسودة'),
                'under_review': t('status.under_review','قيد المراجعة'),
                'payment_pending': t('status.payment_pending','بانتظار الدفع'),
                'approved': t('status.approved','معتمد'),
                'rejected': t('status.rejected','مرفوض'),
                'issued': t('status.issued','صادر'),
            };
            Array.from(statusSel.options).forEach(o => { if (opts[o.value]) o.text = opts[o.value]; });
        }

        const auditStatusSel = q('auditStatusFilter');
        if (auditStatusSel) {
            const opts = {
                '': t('filters.all','الكل'),
                'pending': t('audit.status.pending','قيد الانتظار'),
                'approved': t('audit.status.approved','موافقة'),
                'rejected': t('audit.status.rejected','رفض'),
            };
            Array.from(auditStatusSel.options).forEach(o => { if (opts[o.value]) o.text = opts[o.value]; });
        }

        const payStatusSel = q('payStatusFilter');
        if (payStatusSel) {
            const opts = {
                '': t('filters.all','الكل'),
                'waiting_verification': t('pay.status.waiting_verification','بانتظار التحقق'),
                'verified': t('pay.status.verified','تم التحقق'),
                'rejected': t('pay.status.rejected','مرفوض'),
            };
            Array.from(payStatusSel.options).forEach(o => { if (opts[o.value]) o.text = opts[o.value]; });
        }

        const cancelledSel = q('issuedCancelledFilter');
        if (cancelledSel) {
            const opts = {
                '': t('filters.all','الكل'),
                '0': t('filters.active','نشط'),
                '1': t('filters.cancelled','ملغى'),
            };
            Array.from(cancelledSel.options).forEach(o => { if (opts[o.value] !== undefined) o.text = opts[o.value]; });
        }

        const logActionSel = q('logActionFilter');
        if (logActionSel) {
            const firstOpt = logActionSel.options[0];
            if (firstOpt && firstOpt.value === '') firstOpt.text = t('filters.all','الكل');
        }

        const addPayBtn = document.querySelector('#panelPayments .cm-card-header button');
        if (addPayBtn) addPayBtn.innerHTML = `<i class="fas fa-plus"></i> ${t('button.add_payment','إضافة دفعة')}`;

        const allocHeader = document.querySelector('#panelAllocations .cm-card-header h4');
        if (allocHeader) allocHeader.textContent = t('alloc_table.title','تخصيصات إيصالات الشهادات');
        const addAllocBtn = document.querySelector('#panelAllocations .cm-card-header button');
        if (addAllocBtn) addAllocBtn.innerHTML = `<i class="fas fa-plus"></i> ${t('button.add_allocation','إضافة تخصيص')}`;

        const settingsNavMap = {
            'certs':    t('settings.certificates','أنواع الشهادات'),
            'editions': t('settings.editions','الإصدارات'),
            'fees':     t('settings.fees','قواعد الرسوم'),
        };
        document.querySelectorAll('.settings-nav-item').forEach(el => {
            const sub = el.dataset.sub;
            if (settingsNavMap[sub]) el.innerHTML = `<i class="${el.querySelector('i')?.className||'fas fa-circle'}"></i> ${settingsNavMap[sub]}`;
        });

        const subCertH = document.querySelector('#subPanelCerts .cm-card-header h4');
        if (subCertH) subCertH.textContent = t('settings.certificates','أنواع الشهادات');
        const subEdH = document.querySelector('#subPanelEditions .cm-card-header h4');
        if (subEdH) subEdH.textContent = t('settings.editions','الإصدارات');
        const subFeeH = document.querySelector('#subPanelFees .cm-card-header h4');
        if (subFeeH) subFeeH.textContent = t('settings.fees','قواعد الرسوم');

        const modalBtns = {
            'btnSubmitAudit': `<i class="fas fa-check"></i> ${t('audit.form.submit','حفظ التدقيق')}`,
            'btnSubmitPay': `<i class="fas fa-check-circle"></i> ${t('pay.form.submit','تأكيد')}`,
            'btnCloseDetailFooter': t('common.close','إغلاق'),
        };
        Object.entries(modalBtns).forEach(([id, html]) => {
            const el = q(id);
            if (el) el.innerHTML = html;
        });

        document.querySelectorAll('.cm-modal-footer .btn-outline').forEach(btn => {
            if (btn.textContent.trim() === 'Cancel') btn.textContent = t('common.cancel','إلغاء');
            if (btn.textContent.trim() === 'Close')  btn.textContent = t('common.close','إغلاق');
        });
    }

    async function updateAuditor(requestId, auditorId) {
        if (!S.perms.canAudit) return;
        try {
            await patchRequestStatus(requestId, undefined, { auditor_user_id: auditorId });
            toast(t('message.auditor_assigned','Auditor assigned successfully'));
            loadRequests(S.pages.requests);
        } catch(e) { toast(e.message, false); }
    }

    async function submitForReview(id) {
        if (!confirm(t('message.submitted','Submit this request for review?'))) return;
        try {
            await patchRequestStatus(id, 'under_review');
            toast(t('message.submitted','Request submitted for review'));
            loadRequests(S.pages.requests);
            refreshStats();
        } catch(e) { toast(e.message, false); }
    }

    /* ── Public API ──────────────────────────────────────────────────── */
    window.CertificateManagement = {
        init, switchTab, viewDetail, openAudit, openPaymentVerify, openAddPayment,
        submitPaymentVerify, updateAuditor, submitForReview, issueCertificate,
        submitInlineAudit, submitAudit,
        openCertModal, openEditionModal, openFeeModal, saveCert, saveEdition, saveFee,
        openAllocationModal, closeAllocModal, searchAllocReceipt,
        saveAllocation, deleteAllocation,
        updateSelectedAllocCount, onAllocRequestChange, onFeeRuleChange,
        translateStaticUI, closeModal,
        noop: () => {}
    };

    window.closeModal = closeModal;

    window.loadRequests    = loadRequests;
    window.loadAudits      = loadAudits;
    window.loadPayments    = loadPayments;
    window.loadIssued      = loadIssued;
    window.loadLogs        = loadLogs;
    window.loadAllocations = loadAllocations;

})();