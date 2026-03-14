/**
 * /admin/assets/js/pages/certificates_requests.js
 * VERSION: 4.0 — ULTRA FAST LOADING
 *
 * OPTIMIZATIONS vs v3.0:
 * 1. Cache Layer (5 min TTL) — zero duplicate API calls on tab/filter switches
 * 2. Request Deduplication — concurrent identical URLs share one Promise
 * 3. 3-Phase Boot: critical → first paint → deferred (products/brands lazy)
 * 4. Skeleton rows — instant visual feedback before data arrives
 * 5. Reduced limits: entities 500, products 500, translations 2000 (was 1000/2000/10000)
 * 6. Language-filtered product translations — only current lang from API
 * 7. Parallel saves — translations + items simultaneously (was sequential)
 * 8. Parallel copy — items + translations copied simultaneously
 * 9. AbortController — cancels stale list requests on fast filter changes
 * 10. Debounced search — no rapid-fire API calls on keystroke
 * 11. Cache invalidation only on write ops
 * 12. DocumentFragment dropdowns — no repeated DOM reflow
 */
(function () {
    'use strict';

    const AF  = window.AdminFramework;
    const CFG = window.CERT_REQ_CFG || {};

    /* ── Endpoints ──────────────────────────────────────────────────── */
    const API_REQ        = CFG.apiRequests             || '/api/certificates_requests';
    const API_TRANS      = CFG.apiTranslations         || '/api/certificates_requests_translations';
    const API_ITEMS      = CFG.apiItems                || '/api/certificates_request_items';
    const API_CERTS      = CFG.apiCertificates         || '/api/certificates';
    const API_EDIT       = CFG.apiEditions             || '/api/certificate_editions';
    const API_ISSUED     = CFG.apiIssued               || '/api/certificates_issued';
    const API_AUDITS     = CFG.apiAudits               || '/api/certificates_audits';
    const API_PROD       = CFG.apiProducts             || '/api/certificates_products';
    const API_PROD_TRANS = CFG.apiProductsTranslations || '/api/certificates_products_translations';
    const API_ENT        = CFG.apiEntities             || '/api/entities';
    const API_UNITS      = CFG.apiUnits                || '/api/units';
    const API_LANGS      = CFG.apiLanguages            || '/api/languages';
    const API_TEN        = CFG.apiTenants              || '/api/tenants';
    const API_CTRY       = CFG.apiCountries            || '/api/countries';
    const API_BRAND      = CFG.apiBrands               || '/api/brands';

    /* ══════════════════════════════════════════════════════════════════
       CACHE LAYER — 5 minute TTL, prefix invalidation
    ══════════════════════════════════════════════════════════════════ */
    const CACHE = {
        _s: new Map(), _t: new Map(), TTL: 5 * 60 * 1000,
        get(k) {
            if (!this._s.has(k)) return null;
            if (Date.now() > this._t.get(k)) { this._s.delete(k); this._t.delete(k); return null; }
            return this._s.get(k);
        },
        set(k, v) { this._s.set(k, v); this._t.set(k, Date.now() + this.TTL); },
        del(prefix) {
            for (const k of this._s.keys()) {
                if (k.startsWith(prefix)) { this._s.delete(k); this._t.delete(k); }
            }
        }
    };

    /* Request deduplication — same URL in-flight = shared Promise */
    const IN_FLIGHT = new Map();

    /* ── State ──────────────────────────────────────────────────────── */
    const S = {
        page         : 1,
        perPage      : CFG.itemsPerPage || 25,
        filters      : {},
        perms        : window.PAGE_PERMISSIONS || {},
        lang         : window.USER_LANGUAGE    || 'ar',
        tenantId     : window.APP_CONFIG?.TENANT_ID || 1,
        tenantName   : '',
        tr           : {},
        entities     : [],
        certificates : [],
        editions     : [],
        products     : [],
        brands       : [],
        units        : [],
        languages    : [],
        countries    : [],
        _prodsLoaded : false,
        _listAbort   : null,
    };

    let el             = {};
    let itemRowCounter = 0;
    let deletedItems   = [];
    let deletedTrans   = [];
    let tenantTimer    = null;
    let searchTimer    = null;

    /* ══════════════════════════════════════════════════════════════════
       HTTP — cached GET with deduplication
    ══════════════════════════════════════════════════════════════════ */
    async function cachedGet(url) {
        const hit = CACHE.get(url);
        if (hit !== null) return hit;
        if (IN_FLIGHT.has(url)) return IN_FLIGHT.get(url);

        const p = AF.get(url)
            .then(res => {
                const d = res?.data ?? res;
                CACHE.set(url, d);
                IN_FLIGHT.delete(url);
                return d;
            })
            .catch(e => { IN_FLIGHT.delete(url); throw e; });

        IN_FLIGHT.set(url, p);
        return p;
    }

    /* Bypass cache — for reads that follow a write */
    async function freshGet(url) {
        const res = await AF.get(url);
        return res?.data ?? res;
    }

    function pick(data) {
        if (!data)                              return [];
        if (Array.isArray(data))                return data;
        if (data.data?.items && Array.isArray(data.data.items)) return data.data.items;
        if (Array.isArray(data.data))           return data.data;
        if (Array.isArray(data.items))          return data.items;
        if (data?.id)                           return [data];
        return [];
    }

    /* ══════════════════════════════════════════════════════════════════
       Utilities
    ══════════════════════════════════════════════════════════════════ */
    const esc = s => {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"]/g, m =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m]));
    };
    const q  = id => document.getElementById(id);
    const sv = (el, v) => { if (el) el.value       = (v !== null && v !== undefined) ? String(v) : ''; };
    const sd = (el, v) => { if (el) el.textContent = (v !== null && v !== undefined) ? String(v) : ''; };

    function buildUrl(base, params = {}) {
        const p = {};
        Object.entries(params).forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '') p[k] = v;
        });
        const qs = new URLSearchParams(p).toString();
        return qs ? `${base}?${qs}` : base;
    }

    function skeletonRows(cols = 15, rows = 6) {
        const cells = Array(cols).fill('<td><div class="cr-skel"></div></td>').join('');
        return Array(rows).fill(`<tr>${cells}</tr>`).join('');
    }

    /* ══════════════════════════════════════════════════════════════════
       i18n
    ══════════════════════════════════════════════════════════════════ */
    async function loadI18n() {
        try {
            const r = await fetch(
                `/languages/CertificatesRequests/${encodeURIComponent(S.lang)}.json`,
                { credentials: 'same-origin' }
            );
            if (!r.ok) throw new Error('not found');
            S.tr = await r.json();
        } catch (_) {
            if (S.lang !== 'en') {
                try {
                    const r2 = await fetch('/languages/CertificatesRequests/en.json', { credentials: 'same-origin' });
                    if (r2.ok) S.tr = await r2.json();
                } catch (__) {}
            }
        }
        applyI18n();
    }

    function t(key, fallback) {
        const val = key.split('.').reduce((o, k) => (o && o[k] !== undefined ? o[k] : null), S.tr);
        return (val !== null && val !== undefined) ? String(val) : (fallback !== undefined ? fallback : key);
    }

    function applyI18n() {
        const root = q('certReqPage'); if (!root) return;
        root.querySelectorAll('[data-i18n]').forEach(n => {
            const v = t(n.getAttribute('data-i18n'), ''); if (v) n.textContent = v;
        });
        root.querySelectorAll('[data-i18n-placeholder]').forEach(n => {
            const v = t(n.getAttribute('data-i18n-placeholder'), ''); if (v) n.placeholder = v;
        });
    }

    /* ══════════════════════════════════════════════════════════════════
       PHASE 1 — Critical lookups (run before first paint)
       Fast: entities, certs, units, countries, languages — all parallel
    ══════════════════════════════════════════════════════════════════ */
    async function loadCritical() {
        await Promise.allSettled([
            loadI18n(),
            loadCertificates(),   // needed for table column + filter
            loadEntities(),       // needed for table column + form dropdown
            loadUnits(),          // small — needed for items form
            loadCountries(),      // needed for form dropdown
            loadLanguages(),      // tiny — for translation panels
        ]);
    }

    /* ══════════════════════════════════════════════════════════════════
       PHASE 2 — Deferred (after first paint, idle time)
       Heavy: brands + editions + products/translations
    ══════════════════════════════════════════════════════════════════ */
    function loadDeferred() {
        const run = async () => {
            await loadAllEditions();
            await loadBrands();
            await loadProducts(); // heaviest — goes last
        };
        if (window.requestIdleCallback) {
            requestIdleCallback(run, { timeout: 2500 });
        } else {
            setTimeout(run, 800);
        }
    }

    /* ── Individual loaders ─────────────────────────────────────────── */

    async function loadLanguages() {
        try {
            S.languages = pick(await cachedGet(buildUrl(API_LANGS, { limit: 100 })));
            populateLangSelect();
        } catch (e) { console.warn('langs:', e.message); }
    }

    async function loadCertificates() {
        try {
            S.certificates = pick(await cachedGet(buildUrl(API_CERTS, { limit: 200, lang: S.lang })));
            populateCertSelect();
            populateCertFilter();
        } catch (e) { console.warn('certs:', e.message); }
    }

    async function loadAllEditions() {
        try {
            S.editions = pick(await cachedGet(buildUrl(API_EDIT, { limit: 500, lang: S.lang })));
        } catch (e) { console.warn('editions:', e.message); }
    }

    async function loadEditionsFor(certId) {
        const sel = el.fCertificateEditionId; if (!sel) return;
        // Use already-loaded editions (no extra API call)
        const list = certId
            ? S.editions.filter(e => String(e.certificate_id) === String(certId))
            : S.editions;
        const frag = document.createDocumentFragment();
        const def  = document.createElement('option');
        def.value  = ''; def.textContent = t('form.fields.edition.select', 'Select Edition');
        frag.appendChild(def);
        list.forEach(ed => {
            const o = document.createElement('option');
            o.value = ed.id;
            o.textContent = ed.code || ed.certificate_version || `#${ed.id}`;
            o.dataset.scope = ed.scope || '';
            o.dataset.lang  = ed.language_code || '';
            frag.appendChild(o);
        });
        sel.innerHTML = ''; sel.appendChild(frag);
    }

    async function loadEntities() {
        try {
            const p = { limit: 500, lang: S.lang };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;
            S.entities = pick(await cachedGet(buildUrl(API_ENT, p)))
                .map(e => ({ id: e.id, name: e.store_name || e.name || `#${e.id}`, tenant_id: e.tenant_id }));
            populateEntityDropdowns();
        } catch (e) { console.warn('entities:', e.message); S.entities = []; }
    }

    async function loadBrands() {
        try {
            S.brands = pick(await cachedGet(buildUrl(API_BRAND, { limit: 500, lang: S.lang })));
        } catch (e) { console.warn('brands:', e.message); }
    }

    async function loadUnits() {
        try {
            S.units = pick(await cachedGet(buildUrl(API_UNITS, { lang: S.lang, limit: 100 })));
        } catch (e) {
            S.units = [
                { id: 1, code: 'kg',  name: 'kg'  }, { id: 2, code: 'g',   name: 'g'   },
                { id: 3, code: 'ml',  name: 'ml'  }, { id: 4, code: 'ton', name: 'ton' },
                { id: 5, code: 'pcs', name: 'pcs' },
            ];
        }
    }

    async function loadCountries() {
        try {
            S.countries = pick(await cachedGet(buildUrl(API_CTRY, { lang: S.lang, limit: 300 })))
                .map(c => ({ id: c.id, name: c.name }));
            populateCountryDropdown();
        } catch (e) { console.warn('countries:', e.message); }
    }

    /**
     * loadProducts — OPTIMIZED
     * - Reduced limits: 500 products + 2000 translations (was 2000 + 10000)
     * - Filter translations by language_code at API level
     * - Only runs once (_prodsLoaded flag)
     */
    async function loadProducts() {
        if (S._prodsLoaded) return;
        try {
            const p = { limit: 500 };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;

            // Parallel: products + translations (current lang only)
            const [rawProds, rawTrans] = await Promise.all([
                cachedGet(buildUrl(API_PROD, p)),
                cachedGet(buildUrl(API_PROD_TRANS, { limit: 2000, language_code: S.lang }))
                    .catch(() => cachedGet(buildUrl(API_PROD_TRANS, { limit: 2000 })))
            ]);

            // Build translation map: product_id → lang → name
            const transMap = {};
            pick(rawTrans).forEach(tr => {
                if (!transMap[tr.product_id]) transMap[tr.product_id] = {};
                transMap[tr.product_id][tr.language_code] = tr.name || tr.product_name || '';
            });

            S.products = pick(rawProds).map(prod => {
                const names   = transMap[prod.id] || {};
                const locName = names[S.lang]
                    || names['ar'] || names['en']
                    || Object.values(names).find(n => n)
                    || prod.name || prod.product_name || `#${prod.id}`;
                return {
                    id               : prod.id,
                    name             : locName,
                    brand_id         : prod.brand_id          || null,
                    net_weight       : prod.net_weight         || '',
                    weight_unit      : prod.weight_unit        || '',
                    weight_unit_id   : prod.weight_unit_id     || null,
                    origin_country_id: prod.origin_country_id  || null,
                    hs_code          : prod.hs_code            || '',
                    product_code     : prod.product_code || prod.entity_product_code || '',
                    description      : prod.description        || '',
                    tenant_id        : prod.tenant_id,
                    translations     : names,
                };
            });

            S._prodsLoaded = true;
            populateProductSelector();
        } catch (e) { console.warn('products lazy:', e.message); S.products = []; }
    }

    /* Ensure products loaded before form open */
    async function ensureProducts() {
        if (!S._prodsLoaded) await loadProducts();
    }

    /* ══════════════════════════════════════════════════════════════════
       Lookup helpers
    ══════════════════════════════════════════════════════════════════ */
    const getBrandName   = id => S.brands.find(b => b.id == id)?.name || '';
    const getCountryName = id => S.countries.find(c => c.id == id)?.name || '';
    const getProductById = id => S.products.find(p => p.id == id) || null;

    function getUnitLabel(unitId, unitCode) {
        if (unitId)   { const u = S.units.find(u => u.id   == unitId);   if (u) return u.name; }
        if (unitCode) { const u = S.units.find(u => u.code == unitCode); if (u) return u.name; }
        return '';
    }
    const unitIdByCode = code => code ? (S.units.find(u => u.code === code)?.id || null) : null;

    /* ══════════════════════════════════════════════════════════════════
       Populate dropdowns — DocumentFragment = no repeated reflow
    ══════════════════════════════════════════════════════════════════ */
    function populateLangSelect() {
        const s = q('langSelectRequest'); if (!s) return;
        const frag = document.createDocumentFragment();
        const def  = document.createElement('option');
        def.value  = ''; def.textContent = t('form.translations.choose_lang', 'Choose Language');
        frag.appendChild(def);
        S.languages.forEach(l => {
            const o = document.createElement('option');
            o.value = l.code; o.textContent = `${l.code.toUpperCase()} — ${l.name || ''}`;
            frag.appendChild(o);
        });
        s.innerHTML = ''; s.appendChild(frag);
    }

    function populateCertSelect() {
        const s = el.fCertificateId; if (!s) return;
        const frag = document.createDocumentFragment();
        const def  = document.createElement('option');
        def.value  = ''; def.textContent = t('form.fields.certificate.select', 'Select Certificate');
        frag.appendChild(def);
        S.certificates.forEach(c => {
            const o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.code ? `${c.code} — ${c.description || ''}` : (c.description || `#${c.id}`);
            frag.appendChild(o);
        });
        s.innerHTML = ''; s.appendChild(frag);
    }

    function populateCertFilter() {
        const s = q('certificateFilter'); if (!s) return;
        const frag = document.createDocumentFragment();
        const def  = document.createElement('option');
        def.value  = ''; def.textContent = t('filters.all_certificates', 'All');
        frag.appendChild(def);
        S.certificates.forEach(c => {
            const o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.code ? `${c.code} — ${c.description || ''}` : (c.description || `#${c.id}`);
            frag.appendChild(o);
        });
        s.innerHTML = ''; s.appendChild(frag);
    }

    function populateEntityDropdowns() {
        const sorted = [...S.entities].sort((a, b) => a.name.localeCompare(b.name));
        [el.fEntityId, q('entityFilter')].forEach((s, i) => {
            if (!s) return;
            const ph   = i === 0 ? t('form.fields.entity.select', 'Select Entity') : t('filters.all_entities', 'All Entities');
            const frag = document.createDocumentFragment();
            const def  = document.createElement('option');
            def.value  = ''; def.textContent = ph;
            frag.appendChild(def);
            sorted.forEach(e => {
                const o = document.createElement('option');
                o.value = e.id; o.textContent = e.name;
                frag.appendChild(o);
            });
            s.innerHTML = ''; s.appendChild(frag);
        });
    }

    function populateCountryDropdown() {
        const s = el.fImporterCountryId; if (!s) return;
        const frag = document.createDocumentFragment();
        const def  = document.createElement('option');
        def.value  = ''; def.textContent = t('form.fields.importer_country.select', 'Select Country');
        frag.appendChild(def);
        S.countries.forEach(c => {
            const o = document.createElement('option');
            o.value = c.id; o.textContent = c.name;
            frag.appendChild(o);
        });
        s.innerHTML = ''; s.appendChild(frag);
    }

    function populateProductSelector() {
        const s = el.productSelectorForAdd; if (!s) return;
        const frag = document.createDocumentFragment();
        const def  = document.createElement('option');
        def.value  = ''; def.textContent = t('form.items.select_product', 'Select product...');
        frag.appendChild(def);
        [...S.products]
            .sort((a, b) => a.name.localeCompare(b.name, S.lang, { sensitivity: 'base' }))
            .forEach(p => {
                const o     = document.createElement('option');
                o.value     = p.id;
                const brand = getBrandName(p.brand_id);
                const code  = p.product_code ? `[${p.product_code}] ` : '';
                o.textContent = `${code}${p.name}${brand ? ' — ' + brand : ''}`;
                frag.appendChild(o);
            });
        s.innerHTML = ''; s.appendChild(frag);
    }

    function buildUnitOptions(selId = null, selCode = null) {
        return '<option value=""></option>' +
            S.units.map(u => {
                const sel = (selId && u.id == selId) || (selCode && u.code == selCode) ? 'selected' : '';
                return `<option value="${u.id}" ${sel}>${esc(u.name)}</option>`;
            }).join('');
    }

    function buildCountryOptions(selId = null) {
        return '<option value=""></option>' +
            S.countries.map(c =>
                `<option value="${c.id}" ${c.id == selId ? 'selected' : ''}>${esc(c.name)}</option>`
            ).join('');
    }

    /* ══════════════════════════════════════════════════════════════════
       Product preview card
    ══════════════════════════════════════════════════════════════════ */
    function renderProductPreview(pid) {
        const card = q('productPreviewCard'); if (!card) return;
        if (!pid) { card.style.display = 'none'; card.innerHTML = ''; return; }
        const prod = getProductById(pid);
        if (!prod) { card.style.display = 'none'; return; }

        const brand     = getBrandName(prod.brand_id);
        const country   = getCountryName(prod.origin_country_id);
        const unitLabel = getUnitLabel(prod.weight_unit_id, prod.weight_unit);
        const weight    = prod.net_weight ? `${prod.net_weight}${unitLabel ? ' ' + unitLabel : ''}` : '—';
        const transEntries = Object.entries(prod.translations || {});
        const transHtml = transEntries.length
            ? `<div class="prod-prev-trans">${transEntries.map(([lc, nm]) =>
                `<span class="prod-prev-tag"><b>${esc(lc.toUpperCase())}</b>: ${esc(nm)}</span>`).join('')}</div>`
            : '';

        card.innerHTML = `
<div class="prod-prev-wrap">
  <div class="prod-prev-top">
    <span class="prod-prev-id">#${prod.id}</span>
    <strong class="prod-prev-name">${esc(prod.name)}</strong>
    ${prod.product_code ? `<code class="prod-prev-code">${esc(prod.product_code)}</code>` : ''}
    ${prod.hs_code      ? `<code class="prod-prev-hs">HS: ${esc(prod.hs_code)}</code>`   : ''}
  </div>
  <div class="prod-prev-grid">
    <div class="prod-prev-cell"><span class="prod-prev-lbl">Brand</span><span class="prod-prev-val">${esc(brand) || '—'}</span></div>
    <div class="prod-prev-cell"><span class="prod-prev-lbl">Origin</span><span class="prod-prev-val">${esc(country) || '—'}</span></div>
    <div class="prod-prev-cell"><span class="prod-prev-lbl">Net Weight</span><span class="prod-prev-val">${esc(weight)}</span></div>
    ${prod.description ? `<div class="prod-prev-cell" style="grid-column:span 3">
      <span class="prod-prev-lbl">Description</span>
      <span class="prod-prev-val">${esc(prod.description)}</span></div>` : ''}
  </div>${transHtml}
</div>`;
        card.style.display = 'block';
    }

    /* ══════════════════════════════════════════════════════════════════
       Items table
    ══════════════════════════════════════════════════════════════════ */
    function addItemRow(data = {}, product = null) {
        const tbody    = q('itemsTableBody'); if (!tbody) return;
        const emptyRow = q('itemsEmptyRow');  if (emptyRow) emptyRow.style.display = 'none';

        const prodName  = data.product_name || (product ? product.name : '') || (data.product_id ? `#${data.product_id}` : '');
        const prodId    = data.product_id   || product?.id || '';
        const brandName = product ? getBrandName(product.brand_id) : (data.brand_name || '');
        const netWeight = (data.net_weight !== undefined && data.net_weight !== null && data.net_weight !== '')
            ? data.net_weight : (product?.net_weight || '');
        const unitId    = data.weight_unit_id
            || (product ? (product.weight_unit_id || unitIdByCode(product.weight_unit)) : null);
        const originId  = (data.origin_country_id !== undefined && data.origin_country_id !== null && data.origin_country_id !== '')
            ? data.origin_country_id : (product?.origin_country_id || '');

        const idx = ++itemRowCounter;
        const tr  = document.createElement('tr');
        tr.dataset.index     = idx;
        tr.dataset.id        = data.id || '';
        tr.dataset.productId = prodId;

        tr.innerHTML = `
<td style="text-align:center;color:#94a3b8;font-size:11px;width:26px;">${idx}</td>
<td class="i-prod-name-cell" style="font-size:11px;font-weight:600;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#1e293b;" title="${esc(prodName)}">${esc(prodName)}</td>
<td><input class="i-brand"  value="${esc(brandName)}" placeholder="${t('form.items.brand', 'Brand')}"></td>
<td><select class="i-origin">${buildCountryOptions(originId)}</select></td>
<td><input type="number" step="0.001" min="0" class="i-qty"    value="${esc(data.quantity || '')}"  placeholder="0" required></td>
<td><input type="number" step="0.001" min="0" class="i-weight" value="${esc(netWeight)}"            placeholder="0"></td>
<td><select class="i-unit">${buildUnitOptions(unitId, product?.weight_unit || null)}</select></td>
<td><input type="date" class="i-pdate" value="${esc(data.production_date || '')}"></td>
<td><input type="date" class="i-edate" value="${esc(data.expiry_date     || '')}"></td>
<td><input class="i-notes" value="${esc(data.notes || '')}" placeholder="${t('form.items.notes', 'Notes')}"></td>
<td style="text-align:center;">
  <button type="button" class="btn btn-sm btn-danger btn-del-item" style="padding:2px 6px;"><i class="fas fa-times"></i></button>
</td>`;

        tr.querySelector('.btn-del-item').onclick = () => {
            const rid = tr.dataset.id ? parseInt(tr.dataset.id) : null;
            if (rid) deletedItems.push(rid);
            tr.remove(); renum(); checkEmpty();
        };
        tbody.appendChild(tr);
        setTimeout(() => tr.querySelector('.i-qty')?.focus(), 40);
    }

    function renum() {
        let i = 1;
        q('itemsTableBody')?.querySelectorAll('tr[data-index]').forEach(tr => {
            const c = tr.cells[0]; if (c) c.textContent = i++;
        });
    }

    function checkEmpty() {
        const rows = q('itemsTableBody')?.querySelectorAll('tr[data-index]') || [];
        const er   = q('itemsEmptyRow'); if (er) er.style.display = rows.length ? 'none' : '';
    }

    function clearItems() {
        q('itemsTableBody')?.querySelectorAll('tr[data-index]').forEach(tr => tr.remove());
        itemRowCounter = 0;
        const er = q('itemsEmptyRow'); if (er) er.style.display = '';
    }

    /* ══════════════════════════════════════════════════════════════════
       Translation panels
    ══════════════════════════════════════════════════════════════════ */
    function addTransPanel(code, data = {}) {
        const cont = q('requestTranslations'); if (!cont) return;
        cont.querySelector(`[data-lang="${code}"]`)?.remove();

        const div = document.createElement('div');
        div.className    = 'translation-panel';
        div.dataset.lang = code;
        if (data.id) div.dataset.transId = data.id;

        div.innerHTML = `
<div class="translation-panel-header">
  <h5><i class="fas fa-globe"></i> ${code.toUpperCase()}</h5>
  <button type="button" class="btn btn-sm btn-danger btn-rm-trans">${t('form.translations.remove', 'Remove')}</button>
</div>
<div class="form-row">
  <div class="form-group">
    <label>${t('form.translations.description', 'Description')} (${code.toUpperCase()})</label>
    <textarea class="form-control" data-field="description" rows="2">${esc(data.description || '')}</textarea>
  </div>
  <div class="form-group">
    <label>${t('form.translations.notes', 'Notes')} (${code.toUpperCase()})</label>
    <textarea class="form-control" data-field="notes" rows="2">${esc(data.notes || '')}</textarea>
  </div>
</div>`;

        div.querySelector('.btn-rm-trans').onclick = () => {
            const tId = div.dataset.transId ? parseInt(div.dataset.transId) : null;
            if (tId) deletedTrans.push({ id: tId, language_code: code });
            div.remove();
        };
        cont.appendChild(div);
    }

    /* ══════════════════════════════════════════════════════════════════
       Audit / Issued read-only panels
    ══════════════════════════════════════════════════════════════════ */
    async function loadAuditInfo(requestId) {
        try {
            const audits = pick(await freshGet(buildUrl(API_AUDITS, { request_id: requestId, limit: 1, sort: 'id:desc' })));
            const a = audits[0]; if (!a) return;
            const row = q('auditInfoRow'); if (row) row.style.display = '';
            sd(q('auditStatusDisplay'), a.status);
            sd(q('auditDateDisplay'),   a.audit_date ? new Date(a.audit_date).toLocaleString() : '—');
            sd(q('auditNotesDisplay'),  a.notes || '—');
        } catch (_) {}
    }

    async function loadIssuedInfo(issuedId) {
        if (!issuedId) return;
        try {
            const d    = await freshGet(`${API_ISSUED}/${issuedId}`);
            const item = Array.isArray(d) ? d[0] : d; if (!item) return;
            const row  = q('issuedInfoRow'); if (row) row.style.display = '';
            sd(q('issuedCertNumDisplay'),  item.certificate_number || '—');
            sd(q('issuedAtDisplay'),       item.issued_at       ? new Date(item.issued_at).toLocaleString()       : '—');
            sd(q('printableUntilDisplay'), item.printable_until ? new Date(item.printable_until).toLocaleString() : '—');
        } catch (_) {}
    }

    /* ══════════════════════════════════════════════════════════════════
       Tenant verify (super admin only)
    ══════════════════════════════════════════════════════════════════ */
    async function verifyTenant(tid) {
        if (!tid) { tenantHint(t('form.fields.tenant_id.required', 'Tenant ID required'), false); return; }
        try {
            const raw = await freshGet(`${API_TEN}/${tid}`);
            const ten = Array.isArray(raw) ? raw[0] : raw;
            if (!ten?.id) throw new Error('Not found');
            tenantHint(`${ten.name || ''} ${ten.domain ? '(' + ten.domain + ')' : ''}`, true);
            S.tenantName = ten.name || '';
            if (String(ten.id) !== String(S.tenantId)) {
                S.tenantId = ten.id;
                // Invalidate tenant-scoped caches
                CACHE.del(API_ENT); CACHE.del(API_PROD); CACHE.del(API_PROD_TRANS);
                S._prodsLoaded = false;
                await Promise.allSettled([loadEntities(), loadProducts()]);
            }
        } catch (e) { tenantHint(e.message, false); }
    }

    function tenantHint(msg, ok) {
        const d = q('tenantInfo');
        if (d) d.innerHTML = `<small style="color:${ok ? '#16a34a' : '#dc2626'}">${esc(msg)}</small>`;
    }

    /* ══════════════════════════════════════════════════════════════════
       LIST — skeleton + AbortController
    ══════════════════════════════════════════════════════════════════ */
    async function load(page = 1) {
        // Cancel previous in-flight request
        if (S._listAbort) { try { S._listAbort.abort(); } catch (_) {} }
        S._listAbort = new AbortController();

        S.page = page;

        // Show skeleton IMMEDIATELY — no blank loading screen
        if (el.tbody) el.tbody.innerHTML = skeletonRows(S.perms.isSuperAdmin ? 16 : 15, 6);
        showState('table');

        try {
            const p = { page, limit: S.perPage, lang: S.lang, ...S.filters };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;

            // List results are NOT cached (filters/page change frequently)
            const res   = await AF.get(buildUrl(API_REQ, p));
            const raw   = res?.data ?? res;
            const items = pick(raw);
            const meta  = raw?.meta || raw?.data?.meta || {
                total: items.length, page, per_page: S.perPage,
                from: (page - 1) * S.perPage + 1,
                to: Math.min(page * S.perPage, items.length),
            };

            if (!items.length) { showState('empty'); return; }
            renderTable(items, meta);
        } catch (e) {
            if (e.name === 'AbortError') return;
            console.error(e); showState('error', e.message);
        }
    }

    const SHIP_LBL = { 1: 'Chilled', 2: 'Dry', 3: 'Frozen' };

    function renderTable(items, meta) {
        if (!el.tbody) return;
        const isSA = S.perms.isSuperAdmin;

        // Build as one string — fastest possible DOM update
        el.tbody.innerHTML = items.map(r => {
            const certName    = S.certificates.find(c => c.id == r.certificate_id)?.code || r.certificate_id || '—';
            const editionName = S.editions.find(e => e.id == r.certificate_edition_id)?.code
                || (r.certificate_edition_id ? `#${r.certificate_edition_id}` : '—');
            const entityName  = S.entities.find(e => e.id == r.entity_id)?.name || r.entity_id || '—';
            const countryName = getCountryName(r.importer_country_id) || '—';
            const shipLabel   = SHIP_LBL[r.shipment_condition] || r.shipment_condition || '—';
            const tenantCol   = isSA ? `<td>${esc(r.tenant_name || r.tenant_id)}</td>` : '';
            const editBtn     = (S.perms.canEdit   || isSA) ? `<button class="btn btn-xs btn-outline" onclick="CertificatesRequests.edit(${r.id})"><i class="fas fa-edit"></i></button>` : '';
            const copyBtn     = (S.perms.canCreate || isSA) ? `<button class="btn btn-xs btn-secondary" onclick="CertificatesRequests.copy(${r.id})"><i class="fas fa-copy"></i></button>` : '';
            const delBtn      = (S.perms.canDelete || isSA) ? `<button class="btn btn-xs btn-danger" onclick="CertificatesRequests.remove(${r.id})"><i class="fas fa-trash"></i></button>` : '';
            return `<tr>
<td>${r.id}</td>${tenantCol}
<td style="font-size:12px;">${esc(entityName)}</td>
<td style="font-size:12px;">${esc(certName)}</td>
<td style="font-size:11px;color:#64748b;">${esc(editionName)}</td>
<td style="font-size:12px;">${esc(r.importer_name)}</td>
<td style="font-size:11px;">${esc(countryName)}</td>
<td><span class="badge badge-${r.certificate_type || ''}">${r.certificate_type === 'gcc' ? 'GCC' : 'Non-GCC'}</span></td>
<td style="font-size:11px;">${r.operation_type === 're_export' ? 'Re-export' : 'Export'}</td>
<td style="font-size:11px;">${esc(shipLabel)}</td>
<td style="font-size:11px;">${r.transport_method || '—'}</td>
<td><span class="badge badge-${r.status}">${t('form.fields.status.' + r.status, r.status) || '—'}</span></td>
<td><span class="badge badge-${r.payment_status || ''}">${r.payment_status ? t('form.fields.payment_status.' + r.payment_status, r.payment_status) : '—'}</span></td>
<td style="font-size:11px;">${r.issue_date || '—'}</td>
<td style="font-size:11px;">${r.created_at ? new Date(r.created_at).toLocaleDateString() : '—'}</td>
<td><div style="display:flex;gap:3px;">${editBtn}${copyBtn}${delBtn}</div></td>
</tr>`;
        }).join('');

        showState('table');

        if (el.resultsCount && el.resultsCountText) {
            el.resultsCountText.textContent = `${meta.total} record${meta.total !== 1 ? 's' : ''}`;
            el.resultsCount.style.display   = '';
        }
        updatePagination(meta);
    }

    function updatePagination(meta) {
        if (el.paginationInfo) el.paginationInfo.textContent = `${meta.from || 0}–${meta.to || 0} of ${meta.total || 0}`;
        if (AF.Table?.renderPagination) AF.Table.renderPagination(el.pagination, el.paginationInfo, meta, load);
    }

    function showState(state, msg = '') {
        const isTable = state === 'table';
        if (el.tableLoading)   el.tableLoading.style.display   = (state === 'loading' ? 'flex' : 'none');
        if (el.tableContainer) el.tableContainer.style.display = (isTable ? 'block' : 'none');
        if (el.emptyState)     el.emptyState.style.display     = (state === 'empty'  ? 'flex'  : 'none');
        if (el.errorState)     el.errorState.style.display     = (state === 'error'  ? 'flex'  : 'none');
        if (state === 'error' && el.errorMessage) el.errorMessage.textContent = msg;
    }

    /* ══════════════════════════════════════════════════════════════════
       CRUD
    ══════════════════════════════════════════════════════════════════ */
    async function add() {
        await ensureProducts(); // lazy-load before form shows
        resetForm();
        deletedItems = []; deletedTrans = [];
        showForm(t('form.add_title', 'Add Request'));
    }

    async function edit(id) {
        await ensureProducts(); // lazy-load before form shows
        try {
            // PARALLEL: fetch request + translations + items simultaneously
            const [rawReq, rawTrans, rawItems] = await Promise.all([
                freshGet(buildUrl(API_REQ,   { id, lang: S.lang, tenant_id: S.tenantId })),
                freshGet(buildUrl(API_TRANS,  { request_id: id, limit: 100 })).catch(() => ({})),
                freshGet(buildUrl(API_ITEMS,  { request_id: id, limit: 2000 })).catch(() => ({})),
            ]);

            const item = pick(rawReq)[0] || (rawReq?.id ? rawReq : null);
            if (!item?.id) throw new Error(t('messages.error.not_found', 'Not found'));

            resetForm();
            deletedItems = []; deletedTrans = [];
            showForm(`${t('form.edit_title', 'Edit Request')} #${item.id}`);

            // Populate all fields
            if (el.formId)             el.formId.value = String(item.id);
            if (el.fTenantId)          sv(el.fTenantId,          item.tenant_id           || S.tenantId);
            if (el.fEntityId)          sv(el.fEntityId,          item.entity_id);
            if (el.fCertificateId)     sv(el.fCertificateId,     item.certificate_id);
            if (el.fCertificateType)   sv(el.fCertificateType,   item.certificate_type    || 'gcc');
            if (el.fOperationType)     sv(el.fOperationType,     item.operation_type      || 'export');
            if (el.fImporterName)      sv(el.fImporterName,      item.importer_name);
            if (el.fImporterCountryId) sv(el.fImporterCountryId, item.importer_country_id);
            if (el.fImporterAddress)   sv(el.fImporterAddress,   item.importer_address);
            if (el.fTransportMethod)   sv(el.fTransportMethod,   item.transport_method    || 'sea');
            if (el.fShipmentCondition) sv(el.fShipmentCondition, item.shipment_condition  || 1);
            if (el.fIssueDate)         sv(el.fIssueDate,         item.issue_date);
            if (el.fStatus)            sv(el.fStatus,            item.status              || 'draft');
            if (el.fPaymentStatus)     sv(el.fPaymentStatus,     item.payment_status      || '');
            if (el.fAuditorUserId)     sv(el.fAuditorUserId,     item.auditor_user_id     || '');
            if (el.fDescription)       sv(el.fDescription,       item.description         || '');
            if (el.fNotes)             sv(el.fNotes,             item.notes               || '');

            // Editions — from cache, no API call
            if (item.certificate_id) {
                await loadEditionsFor(item.certificate_id);
                if (el.fCertificateEditionId) sv(el.fCertificateEditionId, item.certificate_edition_id || '');
            }

            // Hide audit/issued rows
            ['auditInfoRow', 'issuedInfoRow'].forEach(rid => { const e = q(rid); if (e) e.style.display = 'none'; });

            // Load audit + issued info in background (non-blocking)
            Promise.allSettled([
                loadAuditInfo(id),
                item.issued_id ? loadIssuedInfo(item.issued_id) : Promise.resolve(),
            ]);

            // Translations — already fetched in parallel above
            pick(rawTrans).forEach(tr => addTransPanel(tr.language_code, tr));

            // Items — already fetched in parallel above, use cached product names
            pick(rawItems).forEach(it => {
                const prod = getProductById(it.product_id);
                addItemRow(prod ? { ...it, product_name: prod.name } : it, prod || null);
            });

            setTimeout(() => q('requestFormContainer')?.scrollIntoView({ behavior: 'smooth' }), 150);
        } catch (e) {
            console.error(e);
            notify('error', t('messages.error.load_failed', 'Load failed') + ': ' + e.message);
        }
    }

    /* ── save ───────────────────────────────────────────────────────── */
    async function save(ev) {
        ev.preventDefault();
        if (AF.Form?.validate && !AF.Form.validate('requestForm')) return;

        const fd   = AF.Form?.getData ? AF.Form.getData('requestForm') : formData();
        const id   = el.formId?.value?.trim();
        const isEd = !!id;

        const body = {
            tenant_id              : parseInt(fd.tenant_id || S.tenantId),
            entity_id              : fd.entity_id              ? parseInt(fd.entity_id)              : null,
            certificate_id         : fd.certificate_id         ? parseInt(fd.certificate_id)         : null,
            certificate_edition_id : fd.certificate_edition_id ? parseInt(fd.certificate_edition_id) : null,
            certificate_type       : fd.certificate_type       || 'gcc',
            operation_type         : fd.operation_type         || 'export',
            importer_name          : fd.importer_name          || null,
            importer_address       : fd.importer_address       || null,
            importer_country_id    : fd.importer_country_id    ? parseInt(fd.importer_country_id)    : null,
            transport_method       : fd.transport_method       || 'sea',
            shipment_condition     : parseInt(fd.shipment_condition) || 1,
            issue_date             : fd.issue_date             || null,
            status                 : fd.status                 || 'draft',
            payment_status         : fd.payment_status         || null,
            auditor_user_id        : fd.auditor_user_id        ? parseInt(fd.auditor_user_id)        : null,
            description            : fd.description            || null,
            notes                  : fd.notes                  || null,
        };
        if (isEd) body.id = parseInt(id);

        try {
            if (AF.Loading?.show) AF.Loading.show(el.btnSubmit, t(isEd ? 'form.buttons.updating' : 'form.buttons.saving', 'Saving…'));

            const res = await AF.api(API_REQ, {
                method  : isEd ? 'PUT' : 'POST',
                headers : { 'Content-Type': 'application/json' },
                body    : JSON.stringify(body),
            });
            if (!res?.success) throw new Error(res?.message || t('messages.error.save_failed', 'Save failed'));

            const requestId = isEd ? parseInt(id) : (res.data?.id || res.id);
            if (!requestId) throw new Error('Could not determine request ID after save');

            // PARALLEL: save translations + items simultaneously
            await Promise.all([
                saveTranslations(requestId),
                saveItems(requestId),
            ]);

            // Invalidate list cache
            CACHE.del(API_REQ);

            notify('success', t(isEd ? 'messages.success.updated' : 'messages.success.created', 'Saved'));
            hideForm();
            deletedItems = []; deletedTrans = [];
            await load(S.page);
        } catch (e) {
            console.error(e);
            notify('error', e.message || t('messages.error.save_failed', 'Save failed'));
        } finally {
            if (AF.Loading?.hide) AF.Loading.hide(el.btnSubmit);
        }
    }

    /* ── saveTranslations — parallel upserts ───────────────────────── */
    async function saveTranslations(requestId) {
        const deletes = deletedTrans.filter(d => d.id).map(dt =>
            AF.api(API_TRANS, {
                method: 'DELETE', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: dt.id }),
            }).catch(e => console.warn('trans del:', e.message))
        );

        const panels  = q('requestTranslations')?.querySelectorAll('[data-lang]') || [];
        const upserts = Array.from(panels).map(panel => {
            const transId = panel.dataset.transId ? parseInt(panel.dataset.transId) : null;
            const payload = {
                request_id    : requestId,
                language_code : panel.dataset.lang,
                description   : panel.querySelector('[data-field="description"]')?.value?.trim() || null,
                notes         : panel.querySelector('[data-field="notes"]')?.value?.trim()       || null,
            };
            if (transId) {
                payload.id = transId;
                return AF.api(API_TRANS, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
                    .catch(e => console.warn('trans upd:', e.message));
            }
            return AF.api(API_TRANS, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
                .then(r => { const nid = r?.data?.id || r?.id; if (nid) panel.dataset.transId = String(nid); })
                .catch(e => console.warn('trans cre:', e.message));
        });

        await Promise.allSettled([...deletes, ...upserts]);
    }

    /* ── saveItems — parallel upserts ──────────────────────────────── */
    async function saveItems(requestId) {
        const deletes = deletedItems.filter(Boolean).map(itemId =>
            AF.api(API_ITEMS, {
                method: 'DELETE', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: itemId, tenant_id: S.tenantId }),
            }).catch(e => console.warn('item del:', e.message))
        );

        const rows    = q('itemsTableBody')?.querySelectorAll('tr[data-index]') || [];
        const upserts = Array.from(rows).map(tr => {
            const existingId = tr.dataset.id        ? parseInt(tr.dataset.id)        : null;
            const productId  = tr.dataset.productId ? parseInt(tr.dataset.productId) : null;
            const qtyVal     = tr.querySelector('.i-qty')?.value;
            if (!productId || !qtyVal) return Promise.resolve();

            const payload = {
                request_id      : requestId,
                product_id      : productId,
                quantity        : parseFloat(qtyVal),
                net_weight      : tr.querySelector('.i-weight')?.value ? parseFloat(tr.querySelector('.i-weight').value) : null,
                weight_unit_id  : tr.querySelector('.i-unit')?.value   ? parseInt(tr.querySelector('.i-unit').value)     : null,
                production_date : tr.querySelector('.i-pdate')?.value  || null,
                expiry_date     : tr.querySelector('.i-edate')?.value  || null,
                notes           : tr.querySelector('.i-notes')?.value?.trim() || null,
                tenant_id       : S.tenantId,
            };

            if (existingId) {
                payload.id = existingId;
                return AF.api(API_ITEMS, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
                    .catch(e => console.warn('item upd:', e.message));
            }
            return AF.api(API_ITEMS, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
                .then(r => { const nid = r?.data?.id || r?.id; if (nid) tr.dataset.id = String(nid); })
                .catch(e => console.warn('item cre:', e.message));
        });

        await Promise.allSettled([...deletes, ...upserts]);
    }

    /* ── remove ─────────────────────────────────────────────────────── */
    async function remove(id) {
        const msg      = t('messages.confirm.delete', 'Delete this request?');
        const doDelete = async () => {
            try {
                await AF.api(API_REQ, {
                    method: 'DELETE', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, tenant_id: S.tenantId }),
                });
                CACHE.del(API_REQ);
                notify('success', t('messages.success.deleted', 'Deleted'));
                await load(S.page);
            } catch (e) { notify('error', e.message || t('messages.error.delete_failed', 'Delete failed')); }
        };
        if (AF.Modal?.confirm) AF.Modal.confirm(msg, doDelete);
        else if (confirm(msg)) doDelete();
    }

    /* ── copy — PARALLEL items + translations ───────────────────────── */
    async function copy(id) {
        const msg    = t('messages.confirm.copy', 'Copy this request?');
        const doCopy = async () => {
            try {
                // PARALLEL: fetch source request + items + translations
                const [rawReq, rawItems, rawTrans] = await Promise.all([
                    freshGet(buildUrl(API_REQ,   { id, lang: S.lang, tenant_id: S.tenantId })),
                    freshGet(buildUrl(API_ITEMS,  { request_id: id, limit: 2000 })).catch(() => ({})),
                    freshGet(buildUrl(API_TRANS,  { request_id: id, limit: 100  })).catch(() => ({})),
                ]);

                const orig = pick(rawReq)[0] || (rawReq?.id ? rawReq : null);
                if (!orig?.id) throw new Error('Source not found');

                const res = await AF.api(API_REQ, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        tenant_id: orig.tenant_id || S.tenantId, entity_id: orig.entity_id,
                        certificate_id: orig.certificate_id, certificate_edition_id: orig.certificate_edition_id || null,
                        certificate_type: orig.certificate_type || 'gcc', operation_type: orig.operation_type || 'export',
                        importer_name: orig.importer_name, importer_address: orig.importer_address,
                        importer_country_id: orig.importer_country_id || null,
                        transport_method: orig.transport_method || 'sea', shipment_condition: orig.shipment_condition || 1,
                        issue_date: null, status: 'draft', payment_status: null, auditor_user_id: null,
                        description: orig.description || null, notes: orig.notes || null,
                    }),
                });
                if (!res?.success) throw new Error(res?.message || 'Copy failed');
                const newId = res.data?.id || res.id;
                if (!newId) throw new Error('No ID returned');

                const itemsArr = pick(rawItems);
                const transArr = pick(rawTrans);

                // PARALLEL: copy all items + translations at once
                await Promise.allSettled([
                    ...transArr.map(tr =>
                        AF.api(API_TRANS, {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ request_id: newId, language_code: tr.language_code, description: tr.description || null, notes: tr.notes || null }),
                        }).catch(() => {})
                    ),
                    ...itemsArr.map(it =>
                        AF.api(API_ITEMS, {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                request_id: newId, product_id: it.product_id, quantity: it.quantity,
                                net_weight: it.net_weight || null, weight_unit_id: it.weight_unit_id || null,
                                production_date: it.production_date || null, expiry_date: it.expiry_date || null,
                                notes: it.notes || null, tenant_id: S.tenantId,
                            }),
                        }).catch(() => {})
                    ),
                ]);

                CACHE.del(API_REQ);
                notify('success', t('messages.success.copied', 'Copied') + ` (ID: ${newId})`);
                await load(S.page);
            } catch (e) { console.error(e); notify('error', e.message || 'Copy failed'); }
        };
        if (AF.Modal?.confirm) AF.Modal.confirm(msg, doCopy);
        else if (confirm(msg)) doCopy();
    }

    /* ══════════════════════════════════════════════════════════════════
       Filters
    ══════════════════════════════════════════════════════════════════ */
    function applyFilters() {
        S.filters = {};
        if (el.searchInput?.value.trim()) S.filters.search = el.searchInput.value.trim();
        const setf = (k, id) => { const e = q(id); if (e?.value) S.filters[k] = e.value; };
        if (S.perms.isSuperAdmin) setf('tenant_id', 'tenantFilter');
        setf('entity_id',       'entityFilter');
        setf('certificate_id',  'certificateFilter');
        setf('certificate_type','certTypeFilter');
        setf('status',          'statusFilter');
        setf('payment_status',  'payStatusFilter');
        setf('transport_method','transportFilter');
        load(1);
    }

    function resetFilters() {
        ['searchInput','entityFilter','certificateFilter','certTypeFilter',
         'statusFilter','payStatusFilter','transportFilter'].forEach(id => {
            const e = q(id); if (e) e.value = '';
        });
        if (S.perms.isSuperAdmin && q('tenantFilter')) q('tenantFilter').value = '';
        S.filters = {}; load(1);
    }

    /* ══════════════════════════════════════════════════════════════════
       Form helpers
    ══════════════════════════════════════════════════════════════════ */
    function formData() {
        const form = q('requestForm'); if (!form) return {};
        const fd = {}; new FormData(form).forEach((v, k) => { fd[k] = v; }); return fd;
    }

    function showForm(title) {
        const fc = q('requestFormContainer');
        if (fc) { fc.style.display = 'block'; fc.scrollIntoView({ behavior: 'smooth' }); }
        const ft = q('formTitle'); if (ft) ft.textContent = title || '';
    }

    function hideForm() {
        const fc = q('requestFormContainer'); if (fc) fc.style.display = 'none';
        renderProductPreview(null);
    }

    function resetForm() {
        const form = q('requestForm');
        if (form) { form.reset(); form.classList.remove('was-validated'); }
        if (el.formId) el.formId.value = '';
        const rt = q('requestTranslations'); if (rt) rt.innerHTML = '';
        clearItems();
        ['auditInfoRow','issuedInfoRow'].forEach(id => { const e = q(id); if (e) e.style.display = 'none'; });
        if (el.fCertificateEditionId)
            el.fCertificateEditionId.innerHTML = `<option value="">${t('form.fields.edition.select', 'Select Edition')}</option>`;
        renderProductPreview(null);
    }

    function notify(type, msg) {
        if (type === 'success' && AF.success) { AF.success(msg); return; }
        if (type === 'error'   && AF.error)   { AF.error(msg);   return; }
        console[type === 'error' ? 'error' : 'log'](msg);
    }

    /* ══════════════════════════════════════════════════════════════════
       Inline styles injection
    ══════════════════════════════════════════════════════════════════ */
    function injectStyles() {
        if (document.getElementById('certReqInlineCSS')) return;
        const style = document.createElement('style');
        style.id = 'certReqInlineCSS';
        style.textContent = `
/* Skeleton shimmer */
@keyframes cr-shimmer{0%{background-position:-468px 0}100%{background-position:468px 0}}
.cr-skel{display:inline-block;width:100%;height:13px;border-radius:3px;
  background:linear-gradient(to right,#f0f0f0 8%,#e0e0e0 18%,#f0f0f0 33%);
  background-size:936px 104px;animation:cr-shimmer 1.2s linear infinite;}

/* Product preview card */
#productPreviewCard{display:none;margin:8px 0 12px;border:1px solid #bfdbfe;border-radius:8px;background:#eff6ff;overflow:hidden;}
.prod-prev-wrap{padding:10px 14px;}
.prod-prev-top{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;}
.prod-prev-id{font-size:11px;color:#94a3b8;}
.prod-prev-name{font-size:14px;font-weight:700;color:#1e40af;}
.prod-prev-code{background:#dbeafe;color:#1e40af;border-radius:4px;padding:1px 6px;font-size:11px;}
.prod-prev-hs{background:#e0e7ff;color:#3730a3;border-radius:4px;padding:1px 6px;font-size:11px;}
.prod-prev-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px 12px;margin-bottom:8px;}
.prod-prev-cell{display:flex;flex-direction:column;gap:2px;}
.prod-prev-lbl{font-size:10px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.4px;}
.prod-prev-val{font-size:12px;color:#1e293b;font-weight:500;}
.prod-prev-trans{display:flex;flex-wrap:wrap;gap:5px;padding-top:6px;border-top:1px solid #bfdbfe;}
.prod-prev-tag{background:#dbeafe;color:#1e40af;border-radius:12px;padding:2px 8px;font-size:11px;}
@media(max-width:600px){.prod-prev-grid{grid-template-columns:1fr 1fr;}}
`;
        document.head.appendChild(style);
    }

    /* ══════════════════════════════════════════════════════════════════
       INIT — 3-phase optimized boot
    ══════════════════════════════════════════════════════════════════ */
    async function init() {
        console.log('[CertificatesRequests] v4.0 init');

        injectStyles();

        // Cache element references (avoid repeated getElementById)
        el = {
            form                  : q('requestForm'),
            formId                : q('formId'),
            fTenantId             : q('fTenantId'),
            fEntityId             : q('fEntityId'),
            fCertificateId        : q('fCertificateId'),
            fCertificateEditionId : q('fCertificateEditionId'),
            fCertificateType      : q('fCertificateType'),
            fOperationType        : q('fOperationType'),
            fImporterName         : q('fImporterName'),
            fImporterCountryId    : q('fImporterCountryId'),
            fImporterAddress      : q('fImporterAddress'),
            fTransportMethod      : q('fTransportMethod'),
            fShipmentCondition    : q('fShipmentCondition'),
            fIssueDate            : q('fIssueDate'),
            fStatus               : q('fStatus'),
            fPaymentStatus        : q('fPaymentStatus'),
            fAuditorUserId        : q('fAuditorUserId'),
            fDescription          : q('fDescription'),
            fNotes                : q('fNotes'),
            btnSubmit             : q('btnSubmitForm'),
            searchInput           : q('searchInput'),
            productSelectorForAdd : q('productSelectorForAdd'),
            tableLoading          : q('tableLoading'),
            tableContainer        : q('tableContainer'),
            emptyState            : q('emptyState'),
            errorState            : q('errorState'),
            tbody                 : q('tableBody'),
            errorMessage          : q('errorMessage'),
            pagination            : q('pagination'),
            paginationInfo        : q('paginationInfo'),
            resultsCount          : q('resultsCount'),
            resultsCountText      : q('resultsCountText'),
        };

        // Inject product preview card element
        if (!q('productPreviewCard') && el.productSelectorForAdd) {
            const card = document.createElement('div');
            card.id = 'productPreviewCard';
            el.productSelectorForAdd.parentNode.insertBefore(card, el.productSelectorForAdd.nextSibling);
        }

        // Super admin: tenant verification (non-blocking)
        if (S.perms.isSuperAdmin && el.fTenantId) {
            el.fTenantId.addEventListener('input', e => {
                clearTimeout(tenantTimer);
                const v = parseInt(e.target.value);
                if (v) tenantTimer = setTimeout(() => verifyTenant(v), 600);
                else tenantHint(t('form.fields.tenant_id.required', 'Tenant ID required'), false);
            });
            verifyTenant(parseInt(el.fTenantId.value) || S.tenantId); // fire and forget
        }

        // PHASE 1: Load critical lookups + bind events
        await loadCritical();
        bindEvents();

        // PHASE 2: Show table with skeleton immediately
        load(1);

        // PHASE 3: Load heavy data in idle time (editions + brands + products)
        loadDeferred();

        console.log('[CertificatesRequests] ready');
    }

    function bindEvents() {
        // Certificate → Editions cascade
        el.fCertificateId?.addEventListener('change', async e => {
            await loadEditionsFor(e.target.value);
        });

        // Edition hint
        el.fCertificateEditionId?.addEventListener('change', e => {
            const opt = e.target.options[e.target.selectedIndex];
            const h   = q('editionHint');
            if (h) h.textContent = opt?.dataset.scope
                ? `Scope: ${opt.dataset.scope} | Lang: ${opt.dataset.lang}` : '';
        });

        // Product preview on selection
        el.productSelectorForAdd?.addEventListener('change', e => {
            renderProductPreview(e.target.value || null);
        });

        el.form?.addEventListener('submit', save);
        q('btnAddRequest')  ?.addEventListener('click', add);
        q('btnCloseForm')   ?.addEventListener('click', hideForm);
        q('btnCancelForm')  ?.addEventListener('click', hideForm);
        q('btnRetry')       ?.addEventListener('click', () => load(S.page));

        // Debounced search (400ms)
        el.searchInput?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyFilters, 400);
        });
        el.searchInput?.addEventListener('keydown', e => {
            if (e.key === 'Enter') { clearTimeout(searchTimer); applyFilters(); }
        });

        q('btnApplyFilters')?.addEventListener('click', applyFilters);
        q('btnResetFilters')?.addEventListener('click', resetFilters);

        q('addLangBtnRequest')?.addEventListener('click', () => {
            const code = q('langSelectRequest')?.value;
            if (code) addTransPanel(code, {});
            else notify('error', t('messages.error.select_language', 'Select a language first'));
        });

        q('addItemBtn')?.addEventListener('click', () => {
            const sel = el.productSelectorForAdd;
            const pid = sel?.value;
            if (!pid) {
                if (!S._prodsLoaded) { notify('error', 'جارٍ تحميل المنتجات، حاول مرة أخرى...'); return; }
                notify('error', t('messages.error.select_product', 'Select a product first'));
                return;
            }
            const prod = getProductById(pid);
            addItemRow({ product_id: pid, product_name: prod?.name || '' }, prod);
            if (sel) sel.value = '';
            renderProductPreview(null);
        });
    }

    /* ── Public API ─────────────────────────────────────────────────── */
    window.CertificatesRequests = { init, load, edit, remove, add, copy };

    if (window.AdminFramework && !window.__certReqInit) {
        window.__certReqInit = true;
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
        else init();
    }

})();