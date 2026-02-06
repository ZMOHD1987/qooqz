(function () {
    'use strict';

    // ────────────────────────────────────────────────
    // CONFIG & UTILITIES
    // ────────────────────────────────────────────────
    const CONFIG = window.VENDORS_CONFIG || {};
    const API_BASE = CONFIG.apiUrl || '/api/vendors';
    const COUNTRIES_API = '/api/countries';
    const CITIES_API = '/api/cities';

    const STRINGS = window.STRINGS || {};
    const t = key => STRINGS[key] || key;

    const IS_ADMIN = !!CONFIG.isAdmin;
    const LANG = CONFIG.lang || 'en';
    const DIRECTION = CONFIG.direction || 'ltr';
    const CSRF_TOKEN = CONFIG.csrfToken;

    const ITEMS_PER_PAGE = 25;

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
    let isLoading = false;

    // ────────────────────────────────────────────────
    // HELPERS
    // ────────────────────────────────────────────────
    function showNotification(message, type = 'info') {
        const notif = document.createElement('div');
        notif.className = `notification ${type}`;
        notif.textContent = message;
        refs.notificationArea.appendChild(notif);
        setTimeout(() => notif.remove(), 5000);
    }

    async function apiCall(url, options = {}) {
        try {
            const defaultOptions = {
                headers: {
                    'X-CSRF-Token': CSRF_TOKEN,
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            };

            const res = await fetch(url, { ...defaultOptions, ...options });

            if (!res.ok) {
                const errorData = await res.json().catch(() => ({}));
                throw new Error(errorData.message || `HTTP ${res.status}`);
            }

            return await res.json();
        } catch (err) {
            console.error('API Error:', err);
            throw err;
        }
    }

    function setLoadingState(isLoading) {
        if (refs.tbody) {
            refs.tbody.innerHTML = isLoading
                ? `<tr><td colspan="10" class="loading-row">${t('messages.loading')}</td></tr>`
                : '';
        }
    }

    // ────────────────────────────────────────────────
    // DATA LOADING FUNCTIONS
    // ────────────────────────────────────────────────
    async function loadCountries(selectEl, selectedId = '') {
        if (!selectEl) return;

        selectEl.innerHTML = `<option value="">${t('form.loading_countries')}</option>`;

        try {
            const data = await apiCall(`${COUNTRIES_API}?lang=${LANG}&limit=1000`);
            const list = Array.isArray(data) ? data : (data.data || []);

            selectEl.innerHTML = `<option value="">${t('form.select_country')}</option>`;

            list.forEach(c => {
                const opt = new Option(c.name + (c.iso2 ? ` (${c.iso2})` : ''), c.id);
                selectEl.appendChild(opt);
            });

            if (selectedId) selectEl.value = selectedId;
        } catch (err) {
            console.error('Countries load failed:', err);
            selectEl.innerHTML = `<option value="">${t('failed_load')}</option>`;
            showNotification(t('failed_load_countries'), 'error');
        }
    }

    async function loadCities(countryId, selectEl, selectedId = '') {
        if (!selectEl) return;

        if (!countryId) {
            selectEl.innerHTML = `<option value="">${t('form.select_country_first')}</option>`;
            return;
        }

        selectEl.innerHTML = `<option value="">${t('form.loading_cities')}</option>`;

        try {
            const data = await apiCall(`${CITIES_API}?country_id=${countryId}&lang=${LANG}&limit=1000`);
            const list = Array.isArray(data) ? data : (data.data || []);

            selectEl.innerHTML = `<option value="">${t('form.select_city')}</option>`;

            list.forEach(c => {
                selectEl.appendChild(new Option(c.name, c.id));
            });

            if (selectedId) selectEl.value = selectedId;
        } catch (err) {
            console.error('Cities load failed:', err);
            selectEl.innerHTML = `<option value="">${t('failed_load')}</option>`;
        }
    }

    async function loadParentVendors(selectEl, excludeId = null) {
        if (!selectEl) return;

        selectEl.innerHTML = `<option value="">${t('form.loading_parents')}</option>`;

        try {
            const data = await apiCall(`${API_BASE}?parents=1&limit=1000`);
            const list = Array.isArray(data) ? data : (data.data || []);

            selectEl.innerHTML = `<option value="">${t('form.select_parent')}</option>`;

            list.forEach(v => {
                if (excludeId && Number(v.id) === Number(excludeId)) return;
                selectEl.appendChild(new Option(v.store_name, v.id));
            });
        } catch (err) {
            console.error('Parents load failed:', err);
            selectEl.innerHTML = `<option value="">${t('failed_load')}</option>`;
        }
    }

    async function loadVendors(page = 1) {
        if (isLoading) return;
        isLoading = true;

        currentPage = page;
        setLoadingState(true);

        const params = new URLSearchParams({
            page,
            limit: ITEMS_PER_PAGE,
            search: refs.searchInput?.value?.trim() || '',
            vendor_type: refs.typeFilter?.value || '',
            country_id: refs.countryFilter?.value || '',
            city_id: refs.cityFilter?.value || '',
            status: refs.statusFilter?.value || '',
            is_verified: refs.verifiedFilter?.value || ''
        });

        try {
            const data = await apiCall(`${API_BASE}?${params}`);
            const vendors = data.data || [];
            const total = data.total || 0;

            renderTable(vendors);
            refs.totalInfo.textContent = `${t('messages.total_label')} ${total}`;
            refs.pageInfo.textContent = `Page ${page} of ${Math.ceil(total / ITEMS_PER_PAGE)} || 1`;

            renderPagination(Math.ceil(total / ITEMS_PER_PAGE), page);
        } catch (err) {
            console.error('Vendors load failed:', err);
            refs.tbody.innerHTML = `<tr><td colspan="10" class="error-row">${t('messages.error_loading')}</td></tr>`;
            showNotification(t('messages.error_loading'), 'error');
        } finally {
            isLoading = false;
        }
    }

    // ────────────────────────────────────────────────
    // RENDER FUNCTIONS
    // ────────────────────────────────────────────────
    function renderTable(vendors) {
        refs.tbody.innerHTML = '';

        if (!vendors.length) {
            refs.tbody.innerHTML = `<tr><td colspan="10">${t('messages.no_vendors')}</td></tr>`;
            return;
        }

        const fragment = document.createDocumentFragment();

        vendors.forEach(v => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${v.id}</td>
                <td>${v.store_name || '-'}</td>
                <td>${v.email || '-'}</td>
                <td>${v.phone || '-'}</td>
                <td>${v.vendor_type || '-'}</td>
                <td>${v.country_name || '-'}</td>
                <td>${v.city_name || '-'}</td>
                <td>${v.status || '-'}</td>
                <td>${v.is_verified ? t('messages.yes') : t('messages.no')}</td>
                <td class="actions">
                    <button class="editBtn btn small" data-id="${v.id}">Edit</button>
                    ${IS_ADMIN ? `<button class="deleteBtn btn small danger" data-id="${v.id}">Delete</button>` : ''}
                    ${IS_ADMIN ? `<button class="verifyBtn btn small ${v.is_verified ? 'warning' : 'success'}" data-id="${v.id}" data-ver="${v.is_verified ? 0 : 1}">
                        ${v.is_verified ? t('messages.unverify') : t('messages.verify')}
                    </button>` : ''}
                </td>
            `;
            fragment.appendChild(tr);
        });

        refs.tbody.appendChild(fragment);

        // Event delegation (better performance)
        refs.tbody.addEventListener('click', handleTableClick, { once: true });
    }

    function handleTableClick(e) {
        const btn = e.target.closest('button');
        if (!btn) return;

        const id = btn.dataset.id;
        if (!id) return;

        if (btn.classList.contains('editBtn')) {
            editVendor(id);
        } else if (btn.classList.contains('deleteBtn')) {
            deleteVendor(id);
        } else if (btn.classList.contains('verifyBtn')) {
            toggleVerify(id, btn.dataset.ver);
        }
    }

    function renderPagination(totalPages, current) {
        refs.paginationControls.innerHTML = '';
        if (totalPages <= 1) return;

        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.className = `btn ${i === current ? 'primary' : 'outline'}`;
            btn.textContent = i;
            btn.dataset.page = i;
            refs.paginationControls.appendChild(btn);
        }

        refs.paginationControls.addEventListener('click', e => {
            const btn = e.target.closest('button[data-page]');
            if (btn) loadVendors(Number(btn.dataset.page));
        }, { once: true });
    }

    // ────────────────────────────────────────────────
    // FORM & CRUD OPERATIONS
    // ────────────────────────────────────────────────
    async function editVendor(id) {
        try {
            const { data: vendor } = await apiCall(`${API_BASE}/${id}`);

            // Fill basic fields
            refs.vendorId.value = vendor.id;
            refs.vendorFormEl.querySelectorAll('[name]').forEach(el => {
                if (vendor[el.name] !== undefined) {
                    el.value = vendor[el.name] || '';
                }
            });

            // Load related selects
            await Promise.all([
                loadCountries(refs.countrySelect, vendor.country_id),
                loadParentVendors(refs.parentVendorSelect, vendor.id)
            ]);

            if (vendor.country_id) {
                await loadCities(vendor.country_id, refs.citySelect, vendor.city_id);
            }

            // Branch logic
            if (refs.isBranchSelect) {
                refs.isBranchSelect.value = vendor.is_branch ? '1' : '0';
                toggleParentVendor();
            }

            // Images preview
            refs.logoPreview.innerHTML = vendor.logo_url ? `<img src="${vendor.logo_url}" alt="Logo">` : '';
            refs.coverPreview.innerHTML = vendor.cover_image_url ? `<img src="${vendor.cover_image_url}" alt="Cover">` : '';
            refs.bannerPreview.innerHTML = vendor.banner_url ? `<img src="${vendor.banner_url}" alt="Banner">` : '';

            // Translations
            refs.translationsContainer.innerHTML = '';
            if (vendor.translations) {
                Object.entries(vendor.translations).forEach(([code, tr]) => {
                    addTranslationPanel(code, code.toUpperCase(), tr);
                });
            }

            refs.formTitle.textContent = `${t('messages.edit_vendor')}: ${vendor.store_name || ''}`;
            refs.vendorForm.style.display = 'block';
        } catch (err) {
            showNotification(t('messages.error_loading_vendor'), 'error');
        }
    }

    async function saveVendor(e) {
        e.preventDefault();
        const form = e.target;
        const isUpdate = !!refs.vendorId.value;
        const url = isUpdate ? `${API_BASE}/${refs.vendorId.value}` : API_BASE;
        const method = isUpdate ? 'PUT' : 'POST';

        try {
            await apiCall(url, { method, body: new FormData(form) });
            showNotification(t('messages.saved'), 'success');
            refs.vendorForm.style.display = 'none';
            loadVendors(currentPage);
        } catch (err) {
            showNotification(t('messages.save_failed'), 'error');
        }
    }

    async function deleteVendor(id) {
        if (!confirm(t('messages.confirm_delete'))) return;

        try {
            await apiCall(`${API_BASE}/${id}`, { method: 'DELETE' });
            showNotification(t('messages.deleted'), 'success');
            loadVendors(currentPage);
        } catch (err) {
            showNotification(t('messages.delete_failed'), 'error');
        }
    }

    async function toggleVerify(id, newValue) {
        if (!confirm(t('messages.confirm_toggle_verify'))) return;

        try {
            const fd = new FormData();
            fd.append('action', 'toggle_verify');
            fd.append('value', newValue);

            await apiCall(`${API_BASE}/${id}`, { method: 'POST', body: fd });
            showNotification(t('messages.updated'), 'success');
            loadVendors(currentPage);
        } catch (err) {
            showNotification(t('messages.toggle_failed'), 'error');
        }
    }

    function toggleParentVendor() {
        if (!refs.parentVendorWrap || !refs.isBranchSelect) return;

        const show = refs.isBranchSelect.value === '1';
        refs.parentVendorWrap.style.display = show ? 'block' : 'none';

        if (show && !refs.parentVendorSelect.options.length) {
            loadParentVendors(refs.parentVendorSelect, refs.vendorId.value || 0);
        }
    }

    function addTranslationPanel(code, name, data = {}) {
        const panel = document.createElement('div');
        panel.className = 'tr-lang-panel';
        panel.innerHTML = `
            <div class="lang-header">
                <strong>${name} (${code})</strong>
                <button type="button" class="remove-lang btn small danger">Remove</button>
            </div>
            <div class="tr-body">
                <textarea name="translations[${code}][description]" rows="3" placeholder="${t('messages.description')}">${data.description || ''}</textarea>
                <textarea name="translations[${code}][return_policy]" rows="2" placeholder="${t('messages.return_policy')}">${data.return_policy || ''}</textarea>
                <textarea name="translations[${code}][shipping_policy]" rows="2" placeholder="${t('messages.shipping_policy')}">${data.shipping_policy || ''}</textarea>
                <input name="translations[${code}][meta_title]" placeholder="${t('messages.meta_title')}" value="${data.meta_title || ''}">
                <input name="translations[${code}][meta_description]" placeholder="${t('messages.meta_description')}" value="${data.meta_description || ''}">
            </div>
        `;

        panel.querySelector('.remove-lang').onclick = () => panel.remove();
        refs.translationsContainer.appendChild(panel);
    }

    // ────────────────────────────────────────────────
    // IMAGE PREVIEW
    // ────────────────────────────────────────────────
    function setupImagePreview(inputName, previewEl) {
        const input = refs.vendorFormEl.querySelector(`[name="${inputName}"]`);
        if (!input || !previewEl) return;

        input.addEventListener('change', e => {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = ev => {
                previewEl.innerHTML = `<img src="${ev.target.result}" alt="Preview" style="max-width:120px;max-height:120px;">`;
            };
            reader.readAsDataURL(file);
        });
    }

    // ────────────────────────────────────────────────
    // EVENT LISTENERS & INIT
    // ────────────────────────────────────────────────
    function initEventListeners() {
        // Form submit
        refs.vendorFormEl?.addEventListener('submit', saveVendor);

        // New vendor
        refs.newVendorBtn?.addEventListener('click', () => {
            refs.vendorFormEl.reset();
            refs.vendorId.value = '';
            refs.logoPreview.innerHTML = refs.coverPreview.innerHTML = refs.bannerPreview.innerHTML = '';
            refs.translationsContainer.innerHTML = '';

            addTranslationPanel(LANG, LANG.toUpperCase());
            Promise.all([
                loadCountries(refs.countrySelect),
                loadParentVendors(refs.parentVendorSelect)
            ]);

            refs.formTitle.textContent = t('messages.new_vendor');
            refs.vendorForm.style.display = 'block';
        });

        // Cancel
        refs.cancelBtn?.addEventListener('click', () => {
            refs.vendorForm.style.display = 'none';
        });

        // Refresh
        refs.refreshBtn?.addEventListener('click', () => loadVendors(currentPage));

        // Clear filters
        refs.clearFiltersBtn?.addEventListener('click', () => {
            [refs.searchInput, refs.typeFilter, refs.countryFilter, refs.cityFilter, refs.statusFilter, refs.verifiedFilter]
                .forEach(el => el && (el.value = ''));
            loadVendors(1);
        });

        // Search with debounce
        let searchTimer;
        refs.searchInput?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadVendors(1), 600);
        });

        refs.clearSearchBtn?.addEventListener('click', () => {
            refs.searchInput.value = '';
            loadVendors(1);
        });

        // Filters change
        ['typeFilter', 'countryFilter', 'cityFilter', 'statusFilter', 'verifiedFilter'].forEach(id => {
            refs[id]?.addEventListener('change', () => loadVendors(1));
        });

        // Country → Cities
        refs.countrySelect?.addEventListener('change', () => {
            loadCities(refs.countrySelect.value, refs.citySelect);
        });

        // Is branch toggle
        refs.isBranchSelect?.addEventListener('change', toggleParentVendor);

        // Add translation
        refs.addLangBtn?.addEventListener('click', () => addTranslationPanel('', ''));

        // Get coordinates
        refs.getCoordsBtn?.addEventListener('click', () => {
            if (!navigator.geolocation) {
                showNotification(t('messages.geolocation_not_supported'), 'error');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                pos => {
                    refs.vendorFormEl.querySelector('[name="latitude"]').value = pos.coords.latitude.toFixed(7);
                    refs.vendorFormEl.querySelector('[name="longitude"]').value = pos.coords.longitude.toFixed(7);
                    showNotification(t('messages.location_updated'), 'success');
                },
                () => showNotification(t('messages.location_error'), 'error')
            );
        });

        // Image previews
        setupImagePreview('logo', refs.logoPreview);
        setupImagePreview('cover', refs.coverPreview);
        setupImagePreview('banner', refs.bannerPreview);
    }

    // ────────────────────────────────────────────────
    // START APPLICATION
    // ────────────────────────────────────────────────
    console.log('Vendors Admin Panel initialized');
    initEventListeners();
    loadVendors(1);
    loadCountries(refs.countryFilter);
    loadCountries(refs.countrySelect);

})();