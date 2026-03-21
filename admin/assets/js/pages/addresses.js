/*!
 * /admin/assets/js/pages/addresses.js — v2.0
 * Production — Full CRUD + Countries/Cities + Owner-aware
 */
(function () {
    'use strict';

    const AF  = window.AdminFramework || {};
    const CFG = window.ADDRESSES_CONFIG || {};

    const API          = CFG.apiUrl      || '/api/addresses';
    const COUNTRIES_API = CFG.countriesApi || '/api/countries';
    const CITIES_API    = CFG.citiesApi    || '/api/cities';
    const ENTITIES_API  = CFG.entitiesApi  || '/api/entities';

    /* ── i18n ─────────────────────────────────────────────── */
    const S = CFG.strings || {};
    function t(key, fallback) { return S[key] || fallback || key; }

    /* ── State ───────────────────────────────────────────── */
    const state = {
        lang:      CFG.lang || 'ar',
        items:     [],
        countries: [],
        cities:    [],
        entities:  [],
        page:      1,
        perPage:   10
    };

    let el = {};

    /* ════════════════════════════════════════════════════════
       API HELPER
       - عند الفشل يُظهر الخطأ ولا يُكسر التطبيق
       ════════════════════════════════════════════════════════ */
    async function apiFetch(url, options = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: {
                'Content-Type':    'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token':    CFG.csrf || ''
            }
        };

        // دمج الـ headers بشكل صحيح
        const config = {
            ...defaults,
            ...options,
            headers: { ...defaults.headers, ...(options.headers || {}) }
        };

        const res = await fetch(url, config);

        const ct = res.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || data.message || `HTTP ${res.status}`);
            return data;
        }

        const text = await res.text();
        if (!res.ok) throw new Error(text || `HTTP ${res.status}`);
        try { return JSON.parse(text); } catch { return { success: true }; }
    }

    /* ── Notification ────────────────────────────────────── */
    function notify(msg, type = 'info') {
        if (AF.notify)   return AF.notify(msg, type);
        if (AF.success && type === 'success') return AF.success(msg);
        if (AF.error   && type === 'error')   return AF.error(msg);
        console.log(`[Addresses][${type}]`, msg);
    }

    /* ── Escape HTML ─────────────────────────────────────── */
    function esc(txt) {
        if (txt == null) return '';
        const d = document.createElement('div');
        d.textContent = String(txt);
        return d.innerHTML;
    }

    /* ── Extract items من أي response format ─────────────── */
    function extractItems(result) {
        if (!result) return [];
        if (Array.isArray(result))            return result;
        if (Array.isArray(result.data))       return result.data;
        if (Array.isArray(result.data?.data)) return result.data.data;
        if (Array.isArray(result.data?.items))return result.data.items;
        if (Array.isArray(result.items))      return result.items;
        return [];
    }

    /* ════════════════════════════════════════════════════════
       GEOLOCATION
       ════════════════════════════════════════════════════════ */
    function getUserLocation() {
        if (!navigator.geolocation) {
            return notify(t('location_not_supported', 'Geolocation not supported'), 'error');
        }

        const btn = el.btnGetLocation;
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }

        navigator.geolocation.getCurrentPosition(
            pos => {
                if (el.latitude)  el.latitude.value  = pos.coords.latitude.toFixed(7);
                if (el.longitude) el.longitude.value = pos.coords.longitude.toFixed(7);
                notify(t('location_success', 'Location retrieved'), 'success');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-map-marker-alt"></i> ' + t('get_location', 'Get Location'); }
            },
            err => {
                const msgs = {
                    1: t('location_denied',      'Location access denied'),
                    2: t('location_unavailable', 'Location unavailable'),
                    3: t('location_timeout',     'Location request timed out')
                };
                notify(msgs[err.code] || t('location_error', 'Unable to get location'), 'error');
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-map-marker-alt"></i> ' + t('get_location', 'Get Location'); }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    /* ════════════════════════════════════════════════════════
       LOAD COUNTRIES
       ════════════════════════════════════════════════════════ */
    async function loadCountries(selectedId = null) {
        try {
            const data = await apiFetch(`${COUNTRIES_API}?language=${encodeURIComponent(state.lang)}&limit=500`);
            state.countries = extractItems(data);

            if (!el.country) return;
            el.country.innerHTML = '<option value="">' + t('select_country', 'Select Country') + '</option>';
            state.countries.forEach(c => {
                const o = document.createElement('option');
                o.value       = c.id;
                o.textContent = c.name;
                if (selectedId && String(selectedId) === String(c.id)) o.selected = true;
                el.country.appendChild(o);
            });

            if (selectedId) await loadCities(selectedId);
        } catch (e) {
            console.error('[Addresses] loadCountries:', e);
            notify(t('failed_load_countries', 'Failed to load countries'), 'error');
        }
    }

    /* ════════════════════════════════════════════════════════
       LOAD CITIES
       ════════════════════════════════════════════════════════ */
    async function loadCities(countryId, selectedId = null) {
        if (!el.city) return;
        el.city.innerHTML = '<option value="">' + t('select_city', 'Select City') + '</option>';
        el.city.disabled = !countryId;
        if (!countryId) return;

        try {
            const data = await apiFetch(
                `${CITIES_API}?country_id=${encodeURIComponent(countryId)}&language=${encodeURIComponent(state.lang)}&limit=1000`
            );
            state.cities = extractItems(data);

            el.city.disabled = false;
            state.cities.forEach(c => {
                const o = document.createElement('option');
                o.value       = c.id;
                o.textContent = c.name;
                if (selectedId && String(selectedId) === String(c.id)) o.selected = true;
                el.city.appendChild(o);
            });
        } catch (e) {
            console.error('[Addresses] loadCities:', e);
            notify(t('failed_load_cities', 'Failed to load cities'), 'error');
        }
    }

    /* ════════════════════════════════════════════════════════
       LOAD ENTITIES  (tenant mode)
       ════════════════════════════════════════════════════════ */
    async function loadEntities(selectedId = null) {
        if (!el.entitySelect) return;
        try {
            const data = await apiFetch(
                `${ENTITIES_API}?tenant_id=${encodeURIComponent(CFG.tenantId)}&limit=500&lang=${encodeURIComponent(state.lang)}`
            );
            state.entities = extractItems(data);

            el.entitySelect.innerHTML = '<option value="">' + t('select_entity', 'Select Entity') + '</option>';
            state.entities.forEach(e => {
                const o = document.createElement('option');
                o.value       = e.id;
                o.textContent = e.store_name || e.name || ('Entity #' + e.id);
                if (selectedId && String(selectedId) === String(e.id)) o.selected = true;
                el.entitySelect.appendChild(o);
            });

            // Auto-select if single entity
            if (state.entities.length === 1 && !selectedId) {
                el.entitySelect.value = state.entities[0].id;
            }
        } catch (e) {
            console.error('[Addresses] loadEntities:', e);
        }
    }

    /* ════════════════════════════════════════════════════════
       LOAD ADDRESSES
       ════════════════════════════════════════════════════════ */
    async function loadAddresses() {
        showState('loading');

        try {
            const params = new URLSearchParams({
                tenant_id: CFG.tenantId,
                language:  state.lang,
                limit:     500
            });

            if (CFG.tenantMode) {
                params.set('filter_tenant_id', CFG.tenantId);
            } else {
                if (CFG.ownerType) params.set('owner_type', CFG.ownerType);
                if (CFG.ownerId)   params.set('owner_id',   CFG.ownerId);
            }

            const data = await apiFetch(`${API}?${params}`);
            state.items = extractItems(data);
            state.page  = 1;

            if (state.items.length === 0) {
                showState('empty');
            } else {
                showState('table');
                renderPage();
            }
        } catch (e) {
            console.error('[Addresses] loadAddresses:', e);
            showState('error', e.message);
        }
    }

    /* ════════════════════════════════════════════════════════
       RENDER
       ════════════════════════════════════════════════════════ */
    function renderPage() {
        const total      = state.items.length;
        const totalPages = Math.max(1, Math.ceil(total / state.perPage));
        if (state.page > totalPages) state.page = totalPages;

        const start     = (state.page - 1) * state.perPage;
        const pageItems = state.items.slice(start, start + state.perPage);

        renderTable(pageItems);
        renderPagination(total, totalPages);
    }

    function renderTable(items) {
        const tbody = el.tbody;
        if (!tbody) return;

        tbody.innerHTML = items.map(a => {
            const editBtn = CFG.permissions?.canEdit
                ? `<button class="btn btn-sm btn-secondary btnEdit" data-id="${a.id}"><i class="fas fa-edit"></i></button>`
                : '';
            const delBtn = CFG.permissions?.canDelete
                ? `<button class="btn btn-sm btn-danger btnDelete" data-id="${a.id}"><i class="fas fa-trash"></i></button>`
                : '';
            const primary = (a.is_primary == 1 || a.is_default == 1)
                ? '<span class="badge badge-active">✔</span>'
                : '';

            return `
                <tr data-id="${a.id}">
                    <td>${esc(a.id)}</td>
                    <td>${esc(a.country_name || a.country || '')}</td>
                    <td>${esc(a.city_name    || a.city    || '')}</td>
                    <td>${esc(a.address_line1 || a.address_line || '')}</td>
                    <td>${esc(a.postal_code || '')}</td>
                    <td>${primary}</td>
                    <td>
                        <div class="table-actions">
                            ${editBtn}
                            ${delBtn}
                        </div>
                    </td>
                </tr>`;
        }).join('');

        tbody.querySelectorAll('.btnEdit').forEach(b =>
            b.addEventListener('click', () => editAddress(b.dataset.id)));
        tbody.querySelectorAll('.btnDelete').forEach(b =>
            b.addEventListener('click', () => deleteAddress(b.dataset.id)));
    }

    function renderPagination(total, totalPages) {
        const info = document.getElementById('paginationInfo');
        const pag  = document.getElementById('pagination');
        if (!info || !pag) return;

        if (total === 0) { info.textContent = ''; pag.innerHTML = ''; return; }

        const start = (state.page - 1) * state.perPage + 1;
        const end   = Math.min(state.page * state.perPage, total);
        info.textContent = `${start}-${end} / ${total}`;

        if (totalPages <= 1) { pag.innerHTML = ''; return; }

        let html = `<button class="page-btn" data-p="${state.page - 1}" ${state.page <= 1 ? 'disabled' : ''}>&#8249;</button>`;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= state.page - 2 && i <= state.page + 2)) {
                html += `<button class="page-btn${i === state.page ? ' active' : ''}" data-p="${i}">${i}</button>`;
            } else if (i === state.page - 3 || i === state.page + 3) {
                html += '<span class="page-ellipsis">…</span>';
            }
        }

        html += `<button class="page-btn" data-p="${state.page + 1}" ${state.page >= totalPages ? 'disabled' : ''}>&#8250;</button>`;
        pag.innerHTML = html;

        pag.querySelectorAll('.page-btn').forEach(b => {
            b.addEventListener('click', () => {
                const p = parseInt(b.dataset.p);
                if (p >= 1 && p <= totalPages) { state.page = p; renderPage(); }
            });
        });
    }

    /* ════════════════════════════════════════════════════════
       FORM — ADD
       ════════════════════════════════════════════════════════ */
    function addAddress() {
        resetForm();
        if (el.formTitle) el.formTitle.textContent = t('add_address', 'Add Address');
        if (el.btnDelete) el.btnDelete.style.display = 'none';
        showForm();
        loadCountries();
    }

    /* ════════════════════════════════════════════════════════
       FORM — EDIT
       ════════════════════════════════════════════════════════ */
    async function editAddress(id) {
        try {
            const data   = await apiFetch(`${API}?id=${encodeURIComponent(id)}&language=${encodeURIComponent(state.lang)}&format=json`);
            const addr   = data.data || data;
            const record = Array.isArray(addr) ? addr[0] : addr;
            if (!record) throw new Error('Address not found');

            resetForm();
            if (el.formTitle) el.formTitle.textContent = t('edit_address', 'Edit Address');
            if (el.btnDelete) el.btnDelete.style.display = 'inline-flex';

            /* fill hidden id */
            const hiddenId = el.form?.querySelector('[name="id"]');
            if (hiddenId) hiddenId.value = record.id;

            /* fill fields */
            _setVal('address_line1', record.address_line1 || record.address_line || '');
            _setVal('address_line2', record.address_line2 || '');
            _setVal('postal_code',   record.postal_code   || '');
            _setVal('is_primary',    record.is_primary ?? record.is_default ?? 0);
            if (el.latitude)  el.latitude.value  = record.latitude  || '';
            if (el.longitude) el.longitude.value = record.longitude || '';

            /* Super Admin fields */
            if (CFG.canEditAllFields) {
                _setVal('owner_type', record.owner_type || 'user', 'ownerTypeSelect');
                _setVal('owner_id',   record.owner_id   || '',     'ownerIdInput');
            }

            /* Tenant mode entity picker */
            if (CFG.tenantMode) await loadEntities(record.owner_id);

            /* Countries + cities */
            await loadCountries(record.country_id);
            await loadCities(record.country_id, record.city_id);

            showForm();
        } catch (e) {
            console.error('[Addresses] editAddress:', e);
            notify(t('failed_load', 'Failed to load address'), 'error');
        }
    }

    /* ════════════════════════════════════════════════════════
       FORM — SAVE
       ════════════════════════════════════════════════════════ */
    async function saveAddress(e) {
        e.preventDefault();

        const fd   = new FormData(el.form);
        const data = Object.fromEntries(fd.entries());

        data.tenant_id = CFG.tenantId;

        if (CFG.tenantMode) {
            data.owner_type = 'entity';
            if (!data.owner_id) {
                return notify(t('select_entity_required', 'Please select an entity'), 'error');
            }
        } else if (!CFG.canEditAllFields) {
            data.owner_type = CFG.ownerType || 'user';
            data.owner_id   = CFG.ownerId   || 1;
        }

        const id = data.id;
        delete data.id;
        delete data.csrf_token; /* لا يُرسل في body — موجود في header */

        const btn = el.form?.querySelector('[type="submit"]');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }

        try {
            const result = await apiFetch(API, {
                method:  id ? 'PUT' : 'POST',
                body:    JSON.stringify(id ? { id, ...data } : data)
            });

            if (result.success !== false) {
                notify(id
                    ? t('address_updated', 'Address updated')
                    : t('address_created', 'Address created'),
                    'success'
                );
                hideForm();
                loadAddresses();
            } else {
                throw new Error(result.error || result.message || t('save_failed', 'Save failed'));
            }
        } catch (err) {
            console.error('[Addresses] saveAddress:', err);
            notify(err.message || t('save_failed', 'Save failed'), 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> ' + t('save', 'Save'); }
        }
    }

    /* ════════════════════════════════════════════════════════
       DELETE
       ════════════════════════════════════════════════════════ */
    async function deleteAddress(id) {
        if (!confirm(t('confirm_delete', 'Delete this address?'))) return;

        try {
            const result = await apiFetch(`${API}?id=${encodeURIComponent(id)}`, {
                method: 'DELETE',
                body:   JSON.stringify({ id })
            });

            if (result.success !== false) {
                notify(t('address_deleted', 'Address deleted'), 'success');
                loadAddresses();
            } else {
                throw new Error(result.error || t('delete_failed', 'Delete failed'));
            }
        } catch (err) {
            console.error('[Addresses] deleteAddress:', err);
            notify(err.message || t('delete_failed', 'Delete failed'), 'error');
        }
    }

    /* ════════════════════════════════════════════════════════
       UI STATE HELPERS
       ════════════════════════════════════════════════════════ */
    function showState(which, msg = '') {
        const loading = document.getElementById('addressesLoading');
        const wrap    = document.getElementById('addressesTableWrap');
        const empty   = document.getElementById('addressesEmpty');
        const error   = document.getElementById('addressesError');
        const errMsg  = document.getElementById('addressesErrorMsg');

        if (loading) loading.style.display = which === 'loading' ? 'flex'  : 'none';
        if (wrap)    wrap.style.display    = which === 'table'   ? 'block' : 'none';
        if (empty)   empty.style.display   = which === 'empty'   ? 'flex'  : 'none';
        if (error)   error.style.display   = which === 'error'   ? 'flex'  : 'none';
        if (errMsg && msg) errMsg.textContent = msg;
    }

    function showForm() {
        if (el.formCard) {
            el.formCard.style.display = 'block';
            el.formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function hideForm() {
        if (el.formCard) el.formCard.style.display = 'none';
    }

    function resetForm() {
        if (el.form) el.form.reset();
        if (el.city) { el.city.innerHTML = '<option value="">' + t('select_city', 'Select City') + '</option>'; el.city.disabled = true; }
        if (el.latitude)  el.latitude.value  = '';
        if (el.longitude) el.longitude.value = '';
    }

    function _setVal(name, val, id = null) {
        const el2 = id ? document.getElementById(id) : el.form?.querySelector(`[name="${name}"]`);
        if (el2) el2.value = val;
    }

    /* ════════════════════════════════════════════════════════
       INIT
       ════════════════════════════════════════════════════════ */
    async function init() {
        el = {
            tbody:         document.querySelector('#addressesTable tbody') || document.querySelector('#addressesTableBody'),
            form:          document.getElementById('addressForm'),
            formCard:      document.getElementById('addressFormCard'),
            formTitle:     document.getElementById('addressFormTitle'),
            country:       document.getElementById('countrySelect'),
            city:          document.getElementById('citySelect'),
            entitySelect:  document.getElementById('entitySelect'),
            latitude:      document.getElementById('latitude'),
            longitude:     document.getElementById('longitude'),
            btnAdd:        document.getElementById('btnAddAddress'),
            btnClose:      document.getElementById('btnCloseForm'),
            btnCancel:     document.getElementById('btnCancelForm'),
            btnDelete:     document.getElementById('btnDeleteAddress'),
            btnGetLocation:document.getElementById('btnGetLocation'),
            btnRetry:      document.getElementById('btnRetry')
        };

        /* events */
        if (el.form)          el.form.onsubmit          = saveAddress;
        if (el.btnAdd)        el.btnAdd.onclick          = addAddress;
        if (el.btnClose)      el.btnClose.onclick        = hideForm;
        if (el.btnCancel)     el.btnCancel.onclick       = hideForm;
        if (el.btnDelete)     el.btnDelete.onclick       = () => { const id = el.form?.querySelector('[name="id"]')?.value; if (id) deleteAddress(id); };
        if (el.btnGetLocation)el.btnGetLocation.onclick  = getUserLocation;
        if (el.btnRetry)      el.btnRetry.onclick        = loadAddresses;
        if (el.country)       el.country.onchange        = () => loadCities(el.country.value);

        /* initial data */
        if (CFG.tenantMode) await loadEntities();
        await loadAddresses();

        console.log('[Addresses] ✓ Initialized');
    }

    /* ════════════════════════════════════════════════════════
       PUBLIC API
       ════════════════════════════════════════════════════════ */
    window.Addresses = { init, load: loadAddresses, add: addAddress, edit: editAddress, delete: deleteAddress };

    /* auto-init */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();