/**
 * /admin/assets/js/pages/addresses.js — Production v2.0
 *
 * ─ التغييرات عن النسخة السابقة ─────────────────────────────
 * • btn-secondary للـ edit → btn-primary
 * • notify() بـ addr- prefix يتطابق مع CSS
 * • showState() IDs محدَّثة لـ addressesLoading/Empty/Error/TableContainer
 * • ESC يُغلق form card
 * • Admin.page.register + window.page للـ fragment navigation
 * • credentials: 'same-origin' موجودة بالفعل في apiFetch ✓
 * ─────────────────────────────────────────────────────────────
 */
(function () {
    'use strict';

    const CFG = window.ADDRESSES_CONFIG || {};

    const API          = CFG.apiUrl       || '/api/addresses';
    const COUNTRIES_API = CFG.countriesApi || '/api/countries';
    const CITIES_API    = CFG.citiesApi    || '/api/cities';
    const ENTITIES_API  = CFG.entitiesApi  || '/api/entities';

    // ── i18n ──────────────────────────────────────────────────
    const S = CFG.strings || {};
    function t(key, fallback) {
        const keys = key.split('.');
        let val = S;
        for (const k of keys) {
            if (val && typeof val === 'object' && k in val) val = val[k];
            else return fallback || key;
        }
        return typeof val === 'string' ? val : (fallback || key);
    }

    // ── State ─────────────────────────────────────────────────
    const state = {
        lang:      CFG.lang || 'en',
        items:     [],
        countries: [],
        cities:    [],
        entities:  [],
        page:      1,
        perPage:   10,
    };

    let el = {};

    // ════════════════════════════════════════════════════════
    // API HELPER
    // ════════════════════════════════════════════════════════
    async function apiFetch(url, options = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: {
                'Content-Type':     'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token':     CFG.csrf || '',
            },
        };
        const config = {
            ...defaults,
            ...options,
            headers: { ...defaults.headers, ...(options.headers || {}) },
        };

        const res = await fetch(url, config);
        const ct  = res.headers.get('content-type') || '';

        if (ct.includes('application/json')) {
            const data = await res.json();
            if (!res.ok) throw new Error(data.error || data.message || `HTTP ${res.status}`);
            return data;
        }

        const text = await res.text();
        if (!res.ok) throw new Error(text || `HTTP ${res.status}`);
        try { return JSON.parse(text); } catch (_) { return { success: true }; }
    }

    // ════════════════════════════════════════════════════════
    // TOAST NOTIFICATIONS  (addr- prefix → matches CSS)
    // ════════════════════════════════════════════════════════
    function notify(message, type = 'info') {
        // Delegate to AdminFramework if available
        const AF = window.AdminFramework;
        if (AF) {
            if (type === 'success' && AF.success) return AF.success(message);
            if (type === 'error'   && AF.error)   return AF.error(message);
            if (type === 'warning' && AF.warning)  return AF.warning(message);
            if (AF.notify) return AF.notify(message, type);
        }

        // Page-level toast
        let container = document.getElementById('addrNotifications');
        if (!container) {
            container = document.createElement('div');
            container.id        = 'addrNotifications';
            container.className = 'addr-notifications';
            const page = document.getElementById('addressesPage');
            (page || document.body).insertBefore(container, (page || document.body).firstChild);
        }

        const toast = document.createElement('div');
        toast.className = `addr-toast addr-toast-${type}`;
        toast.setAttribute('role', 'alert');

        const msg = document.createElement('span');
        msg.textContent = message;
        toast.appendChild(msg);

        const close = document.createElement('button');
        close.className = 'addr-toast-close';
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
        const loading   = document.getElementById('addressesLoading');
        const empty     = document.getElementById('addressesEmpty');
        const error     = document.getElementById('addressesError');
        const container = document.getElementById('addressesTableContainer');
        const errMsg    = document.getElementById('addressesErrorMsg');

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

    function extractItems(result) {
        if (!result)                           return [];
        if (Array.isArray(result))             return result;
        if (Array.isArray(result.data))        return result.data;
        if (Array.isArray(result.data?.data))  return result.data.data;
        if (Array.isArray(result.data?.items)) return result.data.items;
        if (Array.isArray(result.items))       return result.items;
        return [];
    }

    function setField(nameOrId, value, byId = false) {
        const el2 = byId
            ? document.getElementById(nameOrId)
            : el.form?.querySelector(`[name="${nameOrId}"]`);
        if (el2) el2.value = value ?? '';
    }

    // ════════════════════════════════════════════════════════
    // GEOLOCATION
    // ════════════════════════════════════════════════════════
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
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-map-marker-alt" aria-hidden="true"></i> '
                                  + t('get_location', 'Get Location');
                }
            },
            err => {
                const msgs = {
                    1: t('location_denied',      'Location access denied'),
                    2: t('location_unavailable', 'Location unavailable'),
                    3: t('location_timeout',     'Location request timed out'),
                };
                notify(msgs[err.code] || t('location_error', 'Unable to get location'), 'error');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-map-marker-alt" aria-hidden="true"></i> '
                                  + t('get_location', 'Get Location');
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    // ════════════════════════════════════════════════════════
    // LOAD COUNTRIES
    // ════════════════════════════════════════════════════════
    async function loadCountries(selectedId = null) {
        try {
            const data = await apiFetch(
                `${COUNTRIES_API}?language=${encodeURIComponent(state.lang)}&limit=500`
            );
            state.countries = extractItems(data);

            if (!el.country) return;
            el.country.innerHTML = `<option value="">${t('select_country', 'Select Country')}</option>`;
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

    // ════════════════════════════════════════════════════════
    // LOAD CITIES
    // ════════════════════════════════════════════════════════
    async function loadCities(countryId, selectedId = null) {
        if (!el.city) return;
        el.city.innerHTML = `<option value="">${t('select_city', 'Select City')}</option>`;
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

    // ════════════════════════════════════════════════════════
    // LOAD ENTITIES  (tenant mode)
    // ════════════════════════════════════════════════════════
    async function loadEntities(selectedId = null) {
        if (!el.entitySelect) return;
        try {
            const data = await apiFetch(
                `${ENTITIES_API}?tenant_id=${encodeURIComponent(CFG.tenantId)}&limit=500&lang=${encodeURIComponent(state.lang)}`
            );
            state.entities = extractItems(data);
            el.entitySelect.innerHTML = `<option value="">${t('select_entity', 'Select Entity')}</option>`;
            state.entities.forEach(entity => {
                const o = document.createElement('option');
                o.value       = entity.id;
                o.textContent = entity.store_name || entity.name || `Entity #${entity.id}`;
                if (selectedId && String(selectedId) === String(entity.id)) o.selected = true;
                el.entitySelect.appendChild(o);
            });
            if (state.entities.length === 1 && !selectedId) {
                el.entitySelect.value = state.entities[0].id;
            }
        } catch (e) {
            console.error('[Addresses] loadEntities:', e);
        }
    }

    // ════════════════════════════════════════════════════════
    // LOAD ADDRESSES
    // ════════════════════════════════════════════════════════
    async function loadAddresses() {
        showState('loading');
        try {
            const params = new URLSearchParams({ tenant_id: CFG.tenantId, language: state.lang, limit: 500 });

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

    // ════════════════════════════════════════════════════════
    // RENDER
    // ════════════════════════════════════════════════════════
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
            // ✅ btn-primary للتعديل (موحَّد مع بقية الصفحات)
            const editBtn = CFG.permissions?.canEdit
                ? `<button class="btn btn-sm btn-primary btnEdit" data-id="${esc(a.id)}" aria-label="${t('edit','Edit')}">
                       <i class="fas fa-edit" aria-hidden="true"></i>
                   </button>`
                : '';
            const delBtn = CFG.permissions?.canDelete
                ? `<button class="btn btn-sm btn-danger btnDelete" data-id="${esc(a.id)}" aria-label="${t('delete','Delete')}">
                       <i class="fas fa-trash" aria-hidden="true"></i>
                   </button>`
                : '';
            const primary = (a.is_primary == 1 || a.is_default == 1)
                ? '<span class="badge badge-active">✔</span>'
                : '<span class="badge badge-inactive">—</span>';

            return `
                <tr data-id="${esc(a.id)}">
                    <td>${esc(a.id)}</td>
                    <td>${esc(a.country_name || a.country || '')}</td>
                    <td>${esc(a.city_name    || a.city    || '')}</td>
                    <td>${esc(a.address_line1 || a.address_line || '')}</td>
                    <td>${esc(a.postal_code || '—')}</td>
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
        info.textContent = `${start}–${end} / ${total}`;

        if (totalPages <= 1) { pag.innerHTML = ''; return; }

        const makeBtn = (label, targetPage, active = false, disabled = false) => {
            const btn = document.createElement('button');
            btn.className = 'page-btn' + (active ? ' active' : '');
            btn.innerHTML = label;
            btn.disabled  = disabled;
            if (!disabled) btn.addEventListener('click', () => {
                state.page = targetPage;
                renderPage();
            });
            return btn;
        };

        pag.innerHTML = '';
        pag.appendChild(makeBtn('&#8249;', state.page - 1, false, state.page <= 1));

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= state.page - 2 && i <= state.page + 2)) {
                pag.appendChild(makeBtn(String(i), i, i === state.page, i === state.page));
            } else if (i === state.page - 3 || i === state.page + 3) {
                const sp = document.createElement('span');
                sp.className   = 'page-ellipsis';
                sp.textContent = '\u2026';
                pag.appendChild(sp);
            }
        }

        pag.appendChild(makeBtn('&#8250;', state.page + 1, false, state.page >= totalPages));
    }

    // ════════════════════════════════════════════════════════
    // FORM
    // ════════════════════════════════════════════════════════
    function showForm() {
        if (el.formCard) {
            el.formCard.style.display = 'block';
            setTimeout(() => el.formCard.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
        }
    }

    function hideForm() {
        if (el.formCard) el.formCard.style.display = 'none';
    }

    function resetForm() {
        if (el.form)     el.form.reset();
        if (el.city)     { el.city.innerHTML = `<option value="">${t('select_city','Select City')}</option>`; el.city.disabled = true; }
        if (el.latitude)  el.latitude.value  = '';
        if (el.longitude) el.longitude.value = '';
    }

    function addAddress() {
        resetForm();
        if (el.formTitle) el.formTitle.textContent = t('add_address', 'Add Address');
        if (el.btnDelete) el.btnDelete.style.display = 'none';
        showForm();
        loadCountries();
    }

    async function editAddress(id) {
        try {
            const data   = await apiFetch(`${API}?id=${encodeURIComponent(id)}&language=${encodeURIComponent(state.lang)}&format=json`);
            const addr   = data.data || data;
            const record = Array.isArray(addr) ? addr[0] : addr;
            if (!record) throw new Error('Address not found');

            resetForm();
            if (el.formTitle) el.formTitle.textContent = t('edit_address', 'Edit Address');
            if (el.btnDelete) el.btnDelete.style.display = 'inline-flex';

            setField('id',            record.id);
            setField('address_line1', record.address_line1 || record.address_line || '');
            setField('address_line2', record.address_line2 || '');
            setField('postal_code',   record.postal_code   || '');
            setField('is_primary',    record.is_primary ?? record.is_default ?? 0);

            if (el.latitude)  el.latitude.value  = record.latitude  || '';
            if (el.longitude) el.longitude.value = record.longitude || '';

            if (CFG.canEditAllFields) {
                setField('owner_type', record.owner_type || 'user', false);
                const ownerInput = el.form?.querySelector('[name="owner_id"]');
                if (ownerInput) ownerInput.value = record.owner_id || '';
            }

            if (CFG.tenantMode) await loadEntities(record.owner_id);

            await loadCountries(record.country_id);
            await loadCities(record.country_id, record.city_id);

            showForm();
        } catch (e) {
            console.error('[Addresses] editAddress:', e);
            notify(t('failed_load', 'Failed to load address'), 'error');
        }
    }

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
        delete data.csrf_token;

        const btn = el.form?.querySelector('[type="submit"]');
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; }

        try {
            const result = await apiFetch(API, {
                method: id ? 'PUT' : 'POST',
                body:   JSON.stringify(id ? { id, ...data } : data),
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
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = `<i class="fas fa-save" aria-hidden="true"></i> ${t('save','Save')}`;
            }
        }
    }

    async function deleteAddress(id) {
        if (!confirm(t('confirm_delete', 'Delete this address?'))) return;
        try {
            const result = await apiFetch(`${API}?id=${encodeURIComponent(id)}`, {
                method: 'DELETE',
                body:   JSON.stringify({ id }),
            });
            if (result.success !== false) {
                notify(t('address_deleted', 'Address deleted'), 'success');
                hideForm();
                loadAddresses();
            } else {
                throw new Error(result.error || t('delete_failed', 'Delete failed'));
            }
        } catch (err) {
            console.error('[Addresses] deleteAddress:', err);
            notify(err.message || t('delete_failed', 'Delete failed'), 'error');
        }
    }

    // ════════════════════════════════════════════════════════
    // INIT
    // ════════════════════════════════════════════════════════
    async function init() {
        el = {
            tbody:          document.getElementById('addressesTableBody'),
            form:           document.getElementById('addressForm'),
            formCard:       document.getElementById('addressFormCard'),
            formTitle:      document.getElementById('addressFormTitle'),
            country:        document.getElementById('countrySelect'),
            city:           document.getElementById('citySelect'),
            entitySelect:   document.getElementById('entitySelect'),
            latitude:       document.getElementById('latitude'),
            longitude:      document.getElementById('longitude'),
            btnAdd:         document.getElementById('btnAddAddress'),
            btnAddEmpty:    document.getElementById('btnAddAddressEmpty'),
            btnClose:       document.getElementById('btnCloseForm'),
            btnCancel:      document.getElementById('btnCancelForm'),
            btnDelete:      document.getElementById('btnDeleteAddress'),
            btnGetLocation: document.getElementById('btnGetLocation'),
            btnRetry:       document.getElementById('btnRetry'),
        };

        // ESC closes form
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            if (el.formCard && el.formCard.style.display !== 'none') hideForm();
        });

        if (el.form)           el.form.onsubmit             = saveAddress;
        if (el.btnAdd)         el.btnAdd.onclick             = addAddress;
        if (el.btnAddEmpty)    el.btnAddEmpty.onclick        = addAddress;
        if (el.btnClose)       el.btnClose.onclick           = hideForm;
        if (el.btnCancel)      el.btnCancel.onclick          = hideForm;
        if (el.btnRetry)       el.btnRetry.onclick           = loadAddresses;
        if (el.btnGetLocation) el.btnGetLocation.onclick     = getUserLocation;
        if (el.country)        el.country.onchange           = () => loadCities(el.country.value);
        if (el.btnDelete) {
            el.btnDelete.onclick = () => {
                const id = el.form?.querySelector('[name="id"]')?.value;
                if (id) deleteAddress(id);
            };
        }

        if (CFG.tenantMode) await loadEntities();
        await loadAddresses();

        console.log('[Addresses] ✓ Initialized');
    }

    // ════════════════════════════════════════════════════════
    // REGISTER
    // ════════════════════════════════════════════════════════
    window.Addresses = { init, load: loadAddresses, add: addAddress, edit: editAddress };
    window.page = { run: init };

    if (window.Admin?.page?.register) {
        window.Admin.page.register('addresses', init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());