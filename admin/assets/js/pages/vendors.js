(function () {
    'use strict';

    // Config
    const CONFIG = window.VENDORS_CONFIG || {};
    const API = CONFIG.apiUrl || '/api/vendors';
    const COUNTRIES_API = '/api/countries';
    const CITIES_API = '/api/cities';
    const STRINGS = window.STRINGS || {};
    const IS_ADMIN = !!CONFIG.isAdmin;
    const LANG = CONFIG.lang || 'en';
    const ITEMS_PER_PAGE = CONFIG.itemsPerPage || 25;

    function t(key, fallback) {
        return (STRINGS && STRINGS[key]) || fallback || key;
    }

    // Refs (IDs must match those in your HTML)
    const refs = {
        tbody: document.getElementById('vendorsTableBody'),
        totalInfo: document.getElementById('totalInfo'),
        pageInfo: document.getElementById('pageInfo'),
        paginationControls: document.getElementById('paginationControls'),
        searchInput: document.getElementById('searchInput'),
        clearSearchBtn: document.getElementById('clearSearchBtn'),
        typeFilter: document.getElementById('typeFilter'),
        countryFilter: document.getElementById('countryFilter'),
        cityFilter: document.getElementById('cityFilter'),
        statusFilter: document.getElementById('statusFilter'),
        verifiedFilter: document.getElementById('verifiedFilter'),
        clearFiltersBtn: document.getElementById('clearFiltersBtn'),
        refreshBtn: document.getElementById('refreshBtn'),
        newVendorBtn: document.getElementById('newVendorBtn'),
        vendorForm: document.getElementById('vendorForm'),
        formTitle: document.getElementById('formTitle'),
        vendorFormEl: document.getElementById('vendorFormEl'),
        vendorId: document.getElementById('vendorId'),
        cancelBtn: document.getElementById('cancelBtn'),
        countrySelect: document.getElementById('vendor_country'),
        citySelect: document.getElementById('vendor_city'),
        parentVendorSelect: document.getElementById('vendor_parent_id'),
        isBranchSelect: document.getElementById('vendor_is_branch'),
        parentVendorWrap: document.getElementById('parentVendorWrap'),
        getCoordsBtn: document.getElementById('getCoordsBtn'),
        logoPreview: document.getElementById('logoPreview'),
        coverPreview: document.getElementById('coverPreview'),
        bannerPreview: document.getElementById('bannerPreview'),
        translationsContainer: document.getElementById('translationsContainer'),
        addLangBtn: document.getElementById('addLangBtn'),
        notificationArea: document.getElementById('notificationArea')
    };

    let currentPage = 1;

    // In-memory cache + in-flight promises
    const _cache = {
        countries: null,
        countriesPromise: null,
        cities: new Map(),          // countryId -> list
        citiesPromises: new Map(),  // countryId -> promise
        parents: null,
        parentsPromise: null
    };

    function showNotification(msg, type = 'info') {
        if (!refs.notificationArea) {
            console[type === 'error' ? 'error' : 'log']('[notif]', type, msg);
            return;
        }
        const el = document.createElement('div');
        el.className = `notification ${type}`;
        el.textContent = msg;
        refs.notificationArea.appendChild(el);
        setTimeout(() => el.remove(), 5000);
    }

    async function apiCall(url, options = {}, retries = 1, backoff = 300) {
        options = { credentials: 'include', ...options };
        options.headers = { ...(options.headers || {}) };
        if (CONFIG.csrfToken) options.headers['X-CSRF-Token'] = CONFIG.csrfToken;

        try {
            const res = await fetch(url, options);
            const ct = res.headers.get('content-type') || '';
            const body = ct.includes('application/json') ? await res.json() : await res.text();

            if (!res.ok) {
                const msg = (body && body.message) ? body.message : `HTTP ${res.status}`;
                throw new Error(msg);
            }
            if (body && typeof body === 'object' && body.success === false) {
                throw new Error(body.message || 'API returned success:false');
            }
            return body;
        } catch (err) {
            if (retries > 0) {
                await new Promise(r => setTimeout(r, backoff));
                return apiCall(url, options, retries - 1, Math.round(backoff * 1.5));
            }
            console.error('apiCall error:', err, 'url:', url);
            throw err;
        }
    }

    // Cached helpers
    async function getCountriesData() {
        if (_cache.countries) return _cache.countries;
        if (_cache.countriesPromise) return _cache.countriesPromise;
        _cache.countriesPromise = (async () => {
            const url = `${COUNTRIES_API}?lang=${LANG}&limit=1000`;
            const data = await apiCall(url).catch(err => { _cache.countriesPromise = null; throw err; });
            const list = Array.isArray(data) ? data : (data.data || []);
            _cache.countries = list;
            _cache.countriesPromise = null;
            return list;
        })();
        return _cache.countriesPromise;
    }

    async function getCitiesData(countryId) {
        if (!countryId) return [];
        if (_cache.cities.has(countryId)) return _cache.cities.get(countryId);
        if (_cache.citiesPromises.has(countryId)) return _cache.citiesPromises.get(countryId);
        const p = (async () => {
            const url = `${CITIES_API}?country_id=${countryId}&lang=${LANG}&limit=1000`;
            const data = await apiCall(url).catch(err => { _cache.citiesPromises.delete(countryId); throw err; });
            const list = Array.isArray(data) ? data : (data.data || []);
            _cache.cities.set(countryId, list);
            _cache.citiesPromises.delete(countryId);
            return list;
        })();
        _cache.citiesPromises.set(countryId, p);
        return p;
    }

    async function getParentVendorsData() {
        if (_cache.parents) return _cache.parents;
        if (_cache.parentsPromise) return _cache.parentsPromise;
        _cache.parentsPromise = (async () => {
            const data = await apiCall(`${API}?parents=1&limit=1000`).catch(err => { _cache.parentsPromise = null; throw err; });
            const list = Array.isArray(data) ? data : (data.data || []);
            _cache.parents = list;
            _cache.parentsPromise = null;
            return list;
        })();
        return _cache.parentsPromise;
    }

    // Rendering
    function setLoadingRow(msg) {
        if (!refs.tbody) return;
        refs.tbody.innerHTML = `<tr><td colspan="10" class="loading-row">${msg}</td></tr>`;
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function renderTable(vendors) {
        if (!refs.tbody) return;
        refs.tbody.innerHTML = '';
        if (!vendors || vendors.length === 0) {
            refs.tbody.innerHTML = `<tr><td colspan="10" class="loading-row">${t('messages.no_vendors','No vendors')}</td></tr>`;
            return;
        }
        vendors.forEach(v => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(v.id)}</td>
                <td>${escapeHtml(v.store_name)}</td>
                <td>${escapeHtml(v.email || '')}</td>
                <td>${escapeHtml(v.phone || '')}</td>
                <td>${escapeHtml(v.vendor_type || '')}</td>
                <td>${escapeHtml(v.country_name || '')}</td>
                <td>${escapeHtml(v.city_name || '')}</td>
                <td>${escapeHtml(v.status || '')}</td>
                <td>${v.is_verified ? t('messages.yes','Yes') : t('messages.no','No')}</td>
                <td>
                    <button class="editBtn btn small" data-id="${escapeHtml(v.id)}">Edit</button>
                    ${IS_ADMIN ? `<button class="deleteBtn btn small danger" data-id="${escapeHtml(v.id)}">Delete</button>` : ''}
                    ${IS_ADMIN ? `<button class="verifyBtn btn small" data-id="${escapeHtml(v.id)}" data-ver="${v.is_verified ? 0 : 1}">${v.is_verified ? t('messages.unverify','Unverify') : t('messages.verify','Verify')}</button>` : ''}
                </td>
            `;
            refs.tbody.appendChild(tr);
        });

        refs.tbody.querySelectorAll('.editBtn').forEach(btn => btn.addEventListener('click', () => editVendor(btn.dataset.id)));
        refs.tbody.querySelectorAll('.deleteBtn').forEach(btn => btn.addEventListener('click', () => deleteVendor(btn.dataset.id)));
        refs.tbody.querySelectorAll('.verifyBtn').forEach(btn => btn.addEventListener('click', () => toggleVerify(btn.dataset.id, btn.dataset.ver)));
    }

    function renderPagination(totalPages, current) {
        if (!refs.paginationControls) return;
        refs.paginationControls.innerHTML = '';
        if (totalPages <= 1) return;
        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.className = `btn ${i === current ? 'primary' : 'outline'}`;
            btn.textContent = i;
            btn.addEventListener('click', () => loadVendors(i));
            refs.paginationControls.appendChild(btn);
        }
    }

    // Core: load vendors (auto-call enabled in init)
    async function loadVendors(page = 1) {
        currentPage = page;
        if (!refs.tbody) {
            console.warn('vendorsTableBody element not found; aborting loadVendors');
            return;
        }
        setLoadingRow(t('messages.loading','Loading...'));
        const params = new URLSearchParams({
            page,
            limit: ITEMS_PER_PAGE,
            search: refs.searchInput?.value || '',
            vendor_type: refs.typeFilter?.value || '',
            country_id: refs.countryFilter?.value || '',
            city_id: refs.cityFilter?.value || '',
            status: refs.statusFilter?.value || '',
            is_verified: refs.verifiedFilter?.value || ''
        });
        try {
            const data = await apiCall(`${API}?${params}`);
            const list = data.data || [];
            renderTable(list);
            if (refs.totalInfo) refs.totalInfo.textContent = `${t('messages.total_label','Total')} ${data.total || list.length}`;
            if (refs.pageInfo) refs.pageInfo.textContent = `Page ${page} of ${Math.max(1, Math.ceil((data.total || 0) / ITEMS_PER_PAGE))}`;
            renderPagination(Math.ceil((data.total || 0) / ITEMS_PER_PAGE), page);
        } catch (err) {
            console.error('loadVendors failed', err);
            refs.tbody.innerHTML = `<tr><td colspan="10" class="loading-row" style="color: var(--error);">${t('messages.error_loading','Error loading')}</td></tr>`;
            showNotification(err.message || t('messages.error_loading','Failed to load vendors'), 'error');
        }
    }

    // Edit / Save / Delete / Verify
    async function editVendor(id) {
        try {
            const data = await apiCall(`${API}/${id}`);
            const vendor = data.data || {};
            if (refs.vendorId) refs.vendorId.value = vendor.id || '';
            if (refs.vendorFormEl) {
                Object.entries(vendor).forEach(([key, value]) => {
                    const el = refs.vendorFormEl.querySelector(`[name="${key}"]`);
                    if (el) el.value = value === null ? '' : value;
                });
            }
            // related selects
            if (refs.countrySelect) await loadCountries(refs.countrySelect, vendor.country_id);
            if (vendor.country_id && refs.citySelect) await loadCities(vendor.country_id, refs.citySelect, vendor.city_id);
            if (refs.parentVendorSelect) await loadParentVendors(refs.parentVendorSelect, vendor.id);
            if (refs.isBranchSelect) {
                refs.isBranchSelect.value = vendor.is_branch || 0;
                toggleParentVendor();
            }
            if (refs.logoPreview && vendor.logo_url) refs.logoPreview.innerHTML = `<img src="${vendor.logo_url}" alt="Logo">`;
            if (refs.coverPreview && vendor.cover_image_url) refs.coverPreview.innerHTML = `<img src="${vendor.cover_image_url}" alt="Cover">`;
            if (refs.bannerPreview && vendor.banner_url) refs.bannerPreview.innerHTML = `<img src="${vendor.banner_url}" alt="Banner">`;
            // translations
            if (refs.translationsContainer) {
                refs.translationsContainer.innerHTML = '';
                if (vendor.translations) {
                    Object.entries(vendor.translations).forEach(([lang, tr]) => addTranslationPanel(lang, lang, tr));
                }
            }
            if (refs.formTitle) refs.formTitle.textContent = t('messages.edit_vendor','Edit vendor') + ': ' + (vendor.store_name || '');
            if (refs.vendorForm) refs.vendorForm.style.display = 'block';
        } catch (err) {
            showNotification(t('messages.error_loading_vendor','Error loading vendor') + (err.message ? `: ${err.message}` : ''), 'error');
        }
    }

    async function saveVendor(e) {
        e.preventDefault();
        if (!refs.vendorFormEl) return;
        const fd = new FormData(refs.vendorFormEl);
        const isUpdate = fd.get('id');
        const url = isUpdate ? `${API}/${isUpdate}` : API;
        const method = isUpdate ? 'PUT' : 'POST';
        try {
            await apiCall(url, { method, body: fd });
            showNotification(t('messages.saved','Saved'), 'success');
            loadVendors(currentPage);
            if (refs.vendorForm) refs.vendorForm.style.display = 'none';
            _cache.parents = null; // invalidate parents cache
        } catch (err) {
            showNotification(t('messages.save_failed','Save failed') + (err.message ? `: ${err.message}` : ''), 'error');
        }
    }

    async function deleteVendor(id) {
        if (!confirm(t('messages.confirm_delete','Are you sure?'))) return;
        try {
            await apiCall(`${API}/${id}`, { method: 'DELETE' });
            showNotification(t('messages.deleted','Deleted'), 'success');
            loadVendors(currentPage);
            _cache.parents = null;
        } catch (err) {
            showNotification(t('messages.delete_failed','Delete failed') + (err.message ? `: ${err.message}` : ''), 'error');
        }
    }

    async function toggleVerify(id, value) {
        if (!confirm(t('messages.confirm_toggle_verify','Confirm?'))) return;
        try {
            const fd = new FormData();
            fd.append('action', 'toggle_verify');
            fd.append('value', value);
            await apiCall(`${API}/${id}`, { method: 'POST', body: fd });
            showNotification(t('messages.updated','Updated'), 'success');
            loadVendors(currentPage);
        } catch (err) {
            showNotification(t('messages.toggle_failed','Toggle failed') + (err.message ? `: ${err.message}` : ''), 'error');
        }
    }

    // Parent toggle
    function toggleParentVendor() {
        if (!refs.parentVendorWrap || !refs.isBranchSelect) return;
        if (String(refs.isBranchSelect.value) === '1') {
            refs.parentVendorWrap.style.display = 'block';
            loadParentVendors(refs.parentVendorSelect, refs.vendorId?.value || 0);
        } else {
            refs.parentVendorWrap.style.display = 'none';
            if (refs.parentVendorSelect) refs.parentVendorSelect.value = '';
        }
    }

    // Translations panel
    function addTranslationPanel(code, name, data = {}) {
        if (!refs.translationsContainer) return;
        const panel = document.createElement('div');
        panel.className = 'tr-lang-panel';
        panel.innerHTML = `
            <div style="display:flex;justify-content:space-between;">
                <strong>${name} (${code})</strong>
                <button class="remove-lang btn small danger">Remove</button>
            </div>
            <div class="tr-body" style="margin-top:16px;">
                <textarea name="translations[${code}][description]" placeholder="${t('messages.description','Description')}" rows="3">${data.description || ''}</textarea>
                <textarea name="translations[${code}][return_policy]" placeholder="${t('messages.return_policy','Return policy')}" rows="2">${data.return_policy || ''}</textarea>
                <textarea name="translations[${code}][shipping_policy]" placeholder="${t('messages.shipping_policy','Shipping policy')}" rows="2">${data.shipping_policy || ''}</textarea>
                <input name="translations[${code}][meta_title]" placeholder="${t('messages.meta_title','Meta title')}" value="${data.meta_title || ''}">
                <input name="translations[${code}][meta_description]" placeholder="${t('messages.meta_description','Meta description')}" value="${data.meta_description || ''}">
            </div>
        `;
        panel.querySelector('.remove-lang').addEventListener('click', () => panel.remove());
        refs.translationsContainer.appendChild(panel);
    }

    // Image previews
    function previewImage(input, previewEl) {
        if (!input || !previewEl) return;
        input.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => previewEl.innerHTML = `<img src="${e.target.result}" style="max-width:100px;max-height:100px;">`;
            reader.readAsDataURL(file);
        });
    }

    // Loaders for countries/cities/parents into select elements
    async function loadCountries(selectEl, selectedId = '') {
        if (!selectEl) return;
        selectEl.innerHTML = `<option value="">${t('form.loading_countries','Loading...')}</option>`;
        try {
            const list = await getCountriesData();
            selectEl.innerHTML = `<option value="">${t('form.select_country','Select country')}</option>`;
            list.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name + (c.iso2 ? ` (${c.iso2})` : '');
                selectEl.appendChild(opt);
            });
            if (selectedId) selectEl.value = selectedId;
        } catch (err) {
            console.error('Load countries error:', err);
            selectEl.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
        }
    }

    async function loadCities(countryId, selectEl, selectedId = '') {
        if (!selectEl) return;
        if (!countryId) {
            selectEl.innerHTML = `<option value="">${t('form.select_country_first','Select country first')}</option>`;
            return;
        }
        selectEl.innerHTML = `<option value="">${t('form.loading_cities','Loading...')}</option>`;
        try {
            const list = await getCitiesData(countryId);
            selectEl.innerHTML = `<option value="">${t('form.select_city','Select city')}</option>`;
            list.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                selectEl.appendChild(opt);
            });
            if (selectedId) selectEl.value = selectedId;
        } catch (err) {
            console.error('Load cities error:', err);
            selectEl.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
        }
    }

    async function loadParentVendors(selectEl, excludeId = 0) {
        if (!selectEl) return;
        selectEl.innerHTML = `<option value="">${t('form.loading_parents','Loading...')}</option>`;
        try {
            const list = await getParentVendorsData();
            selectEl.innerHTML = `<option value="">${t('form.select_parent','Select parent')}</option>`;
            list.forEach(v => {
                if (excludeId && Number(v.id) === Number(excludeId)) return;
                const opt = document.createElement('option');
                opt.value = v.id;
                opt.textContent = v.store_name || v.name || `Vendor ${v.id}`;
                selectEl.appendChild(opt);
            });
        } catch (err) {
            console.error('Load parents error:', err);
            selectEl.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
        }
    }

    // Get coordinates
    if (refs.getCoordsBtn) {
        refs.getCoordsBtn.addEventListener('click', () => {
            if (!navigator.geolocation) {
                showNotification(t('messages.geolocation_not_supported','Geolocation not supported'), 'error');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                pos => {
                    const lat = refs.vendorFormEl?.querySelector('[name="latitude"]');
                    const lng = refs.vendorFormEl?.querySelector('[name="longitude"]');
                    if (lat) lat.value = pos.coords.latitude.toFixed(7);
                    if (lng) lng.value = pos.coords.longitude.toFixed(7);
                    showNotification(t('messages.location_updated','Location updated'), 'success');
                },
                () => showNotification(t('messages.location_error','Unable to get location'), 'error')
            );
        });
    }

    // Debounce helper
    function debounce(fn, ms = 400) {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), ms);
        };
    }

    // Bind events
    function bindEvents() {
        if (refs.refreshBtn) refs.refreshBtn.addEventListener('click', () => loadVendors(currentPage));
        if (refs.clearFiltersBtn) refs.clearFiltersBtn.addEventListener('click', () => {
            [refs.searchInput, refs.typeFilter, refs.countryFilter, refs.cityFilter, refs.statusFilter, refs.verifiedFilter].forEach(el => el && (el.value = ''));
            loadVendors(1);
        });
        if (refs.searchInput) {
            refs.searchInput.addEventListener('input', debounce(() => loadVendors(1), 500));
        }
        if (refs.clearSearchBtn) refs.clearSearchBtn.addEventListener('click', () => {
            if (refs.searchInput) refs.searchInput.value = '';
            loadVendors(1);
        });
        [refs.typeFilter, refs.countryFilter, refs.cityFilter, refs.statusFilter, refs.verifiedFilter].forEach(el => {
            if (el) el.addEventListener('change', () => loadVendors(1));
        });
        if (refs.countrySelect) refs.countrySelect.addEventListener('change', () => loadCities(refs.countrySelect.value, refs.citySelect));
        if (refs.isBranchSelect) refs.isBranchSelect.addEventListener('change', toggleParentVendor);
        if (refs.addLangBtn) refs.addLangBtn.addEventListener('click', () => addTranslationPanel('', ''));
        if (refs.newVendorBtn) refs.newVendorBtn.addEventListener('click', async () => {
            refs.vendorFormEl?.reset();
            if (refs.vendorId) refs.vendorId.value = '';
            refs.logoPreview && (refs.logoPreview.innerHTML = '');
            refs.coverPreview && (refs.coverPreview.innerHTML = '');
            refs.bannerPreview && (refs.bannerPreview.innerHTML = '');
            refs.translationsContainer && (refs.translationsContainer.innerHTML = '');
            addTranslationPanel(LANG, LANG.toUpperCase());
            loadParentVendors(refs.parentVendorSelect).catch(() => {});
            loadCountries(refs.countrySelect).catch(() => {});
            if (refs.formTitle) refs.formTitle.textContent = t('messages.new_vendor','New vendor');
            if (refs.vendorForm) refs.vendorForm.style.display = 'block';
        });
        if (refs.cancelBtn) refs.cancelBtn.addEventListener('click', () => { if (refs.vendorForm) refs.vendorForm.style.display = 'none'; });
        if (refs.vendorFormEl) refs.vendorFormEl.addEventListener('submit', saveVendor);
        // image previews
        if (refs.vendorFormEl) {
            previewImage(refs.vendorFormEl.querySelector('[name="logo"]'), refs.logoPreview);
            previewImage(refs.vendorFormEl.querySelector('[name="cover"]'), refs.coverPreview);
            previewImage(refs.vendorFormEl.querySelector('[name="banner"]'), refs.bannerPreview);
        }
    }

    // Expose debug surface
    window._vendors = window._vendors || {};
    window._vendors.loadVendors = loadVendors;
    window._vendors.apiUrl = API;
    window._vendors.clearCache = () => { _cache.countries = null; _cache.parents = null; _cache.cities.clear(); };

    // Init: auto-load vendors (no button)
    function init() {
        console.info('Vendors JS initializing. API:', API);
        bindEvents();

        // populate filter country select once (cached)
        if (refs.countryFilter) {
            getCountriesData().then(list => {
                refs.countryFilter.innerHTML = `<option value="">${t('form.select_country','Select country')}</option>`;
                list.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = `${c.name}${c.iso2 ? ' ('+c.iso2+')' : ''}`;
                    refs.countryFilter.appendChild(opt);
                });
            }).catch(err => {
                console.warn('Initial countries load failed', err);
                if (refs.countryFilter) refs.countryFilter.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
            });
        }

        // populate form country select (if present) using same cached data to avoid duplicate requests
        if (refs.countrySelect) {
            getCountriesData().then(list => {
                refs.countrySelect.innerHTML = `<option value="">${t('form.select_country','Select country')}</option>`;
                list.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = `${c.name}${c.iso2 ? ' ('+c.iso2+')' : ''}`;
                    refs.countrySelect.appendChild(opt);
                });
            }).catch(() => {
                if (refs.countrySelect) refs.countrySelect.innerHTML = `<option value="">${t('failed_load','Failed to load')}</option>`;
            });
        }

        // Load vendors automatically (original behavior)
        loadVendors();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(); 