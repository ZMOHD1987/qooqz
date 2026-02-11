/**
 * Addresses Management - Production Version
 * Full CRUD + Countries/Cities + Multilingual + Owner-aware
 */
(function () {
    'use strict';

    const AF = window.AdminFramework || {};
    const CFG = window.ADDRESSES_CONFIG || {};

    const API = CFG.apiUrl || '/api/addresses';
    const COUNTRIES_API = CFG.countriesApi || '/api/countries';
    const CITIES_API = CFG.citiesApi || '/api/cities';

    const S = CFG.strings || {};
    function t(key, fallback) { return S[key] || fallback || key; }

    const PER_PAGE = 10;
    let currentPage = 1;

    const state = {
        language: CFG.lang || 'ar',
        items: [],
        countries: [],
        cities: []
    };

    let el = {};

    // ═══════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════
    
    function esc(txt) {
        if (!txt) return '';
        const d = document.createElement('div');
        d.textContent = txt;
        return d.innerHTML;
    }

    async function apiFetch(url, options = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' }
        };
        const res = await fetch(url, { ...defaults, ...options });
        return await res.json();
    }

    function showMessage(msg, type = 'success') {
        if (AF.success && type === 'success') return AF.success(msg);
        if (AF.error && type === 'error') return AF.error(msg);
        alert(msg);
    }

    // ═══════════════════════════════════════════════════════════
    // GET USER LOCATION
    // ═══════════════════════════════════════════════════════════
    
    function getUserLocation() {
        if (!navigator.geolocation) {
            showMessage(t('location_not_supported', 'Geolocation is not supported by your browser'), 'error');
            return;
        }

        const btnGetLocation = document.getElementById('btnGetLocation');
        if (btnGetLocation) {
            btnGetLocation.disabled = true;
            btnGetLocation.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + t('getting_location', 'Getting location...');
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                if (el.latitude) el.latitude.value = lat.toFixed(7);
                if (el.longitude) el.longitude.value = lng.toFixed(7);

                showMessage(t('location_success', 'Location retrieved successfully!'), 'success');
                
                if (btnGetLocation) {
                    btnGetLocation.disabled = false;
                    btnGetLocation.innerHTML = '<i class="fas fa-map-marker-alt"></i> ' + t('get_location', 'Get Location');
                }
            },
            (error) => {
                let errorMsg = t('location_error', 'Unable to retrieve your location');
                
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMsg = t('location_denied', 'Location access denied. Please enable location permissions.');
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMsg = t('location_unavailable', 'Location information is unavailable.');
                        break;
                    case error.TIMEOUT:
                        errorMsg = t('location_timeout', 'Location request timed out.');
                        break;
                }

                showMessage(errorMsg, 'error');
                
                if (btnGetLocation) {
                    btnGetLocation.disabled = false;
                    btnGetLocation.innerHTML = '<i class="fas fa-map-marker-alt"></i> ' + t('get_location', 'Get Location');
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }

    // ═══════════════════════════════════════════════════════════
    // LOAD COUNTRIES
    // ═══════════════════════════════════════════════════════════
    
    async function loadCountries(selectedId = null) {
        try {
            const url = `${COUNTRIES_API}?language=${encodeURIComponent(state.language)}`;
            console.log('📡 Loading countries from:', url);
            
            const result = await apiFetch(url);
            console.log('📦 Countries response:', result);
            
            // Handle different response formats
            if (result.data) {
                if (Array.isArray(result.data.data)) {
                    state.countries = result.data.data;
                } else if (Array.isArray(result.data)) {
                    state.countries = result.data;
                }
            } else if (Array.isArray(result)) {
                state.countries = result;
            } else {
                state.countries = [];
            }

            if (el.country) {
                el.country.innerHTML = '<option value="">' + t('select_country', 'Select Country') + '</option>';
                state.countries.forEach(country => {
                    const option = document.createElement('option');
                    option.value = country.id;
                    option.textContent = country.name;
                    if (selectedId && String(selectedId) === String(country.id)) {
                        option.selected = true;
                    }
                    el.country.appendChild(option);
                });

                // Trigger city load if country selected
                if (selectedId) {
                    await loadCities(selectedId);
                }
            }

            console.log('✓ Countries loaded:', state.countries.length);
        } catch (e) {
            console.error('❌ loadCountries error:', e);
            showMessage(t('failed_load_countries', 'Failed to load countries'), 'error');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // LOAD CITIES
    // ═══════════════════════════════════════════════════════════
    
    async function loadCities(countryId, selectedId = null) {
        if (!el.city) return;

        el.city.innerHTML = '<option value="">' + t('select_city', 'Select City') + '</option>';
        el.city.disabled = true;

        if (!countryId) {
            return;
        }

        try {
            const url = `${CITIES_API}?country_id=${encodeURIComponent(countryId)}&language=${encodeURIComponent(state.language)}`;
            console.log('📡 Loading cities from:', url);
            
            const result = await apiFetch(url);
            console.log('📦 Cities response:', result);
            
            // Handle different response formats
            if (result.data) {
                if (Array.isArray(result.data.data)) {
                    state.cities = result.data.data;
                } else if (Array.isArray(result.data)) {
                    state.cities = result.data;
                }
            } else if (Array.isArray(result)) {
                state.cities = result;
            } else {
                state.cities = [];
            }

            el.city.disabled = false;
            state.cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city.id;
                option.textContent = city.name;
                if (selectedId && String(selectedId) === String(city.id)) {
                    option.selected = true;
                }
                el.city.appendChild(option);
            });

            console.log('✓ Cities loaded:', state.cities.length);
        } catch (e) {
            console.error('❌ loadCities error:', e);
            showMessage(t('failed_load_cities', 'Failed to load cities'), 'error');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // LOAD ADDRESSES
    // ═══════════════════════════════════════════════════════════
    
    async function loadAddresses() {
        if (!el.tbody) return;

        el.tbody.innerHTML = '<tr><td colspan="7" style="text-align:center">' + t('loading', 'Loading...') + '</td></tr>';

        try {
            const params = new URLSearchParams({
                tenant_id: CFG.tenantId,
                language: state.language
            });

            // Add owner filters only if provided (for non-super-admin or filtered view)
            if (CFG.ownerType) {
                params.append('owner_type', CFG.ownerType);
            }
            if (CFG.ownerId) {
                params.append('owner_id', CFG.ownerId);
            }

            const url = `${API}?${params}`;
            console.log('📡 Loading addresses from:', url);
            
            const result = await apiFetch(url);
            console.log('📦 API Response:', result);
            
            // Handle different response formats
            let items = [];
            if (result.data) {
                // Format: {success: true, data: {data: [], meta: {}}}
                if (Array.isArray(result.data.data)) {
                    items = result.data.data;
                }
                // Format: {success: true, data: []}
                else if (Array.isArray(result.data)) {
                    items = result.data;
                }
            }
            // Format: {data: []}
            else if (result.items) {
                items = result.items;
            }
            // Format: []
            else if (Array.isArray(result)) {
                items = result;
            }

            state.items = items;
            currentPage = 1;
            renderPage();
            console.log('✓ Addresses loaded:', state.items.length);
        } catch (e) {
            console.error('❌ loadAddresses error:', e);
            el.tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:red">' + t('error_loading', 'Error loading addresses') + '</td></tr>';
            showMessage(t('failed_load_list', 'Failed to load addresses'), 'error');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // PAGINATION
    // ═══════════════════════════════════════════════════════════

    function renderPage() {
        const total = state.items.length;
        const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * PER_PAGE;
        const pageItems = state.items.slice(start, start + PER_PAGE);
        renderTable(pageItems);
        renderPagination(total, totalPages);
    }

    function renderPagination(total, totalPages) {
        const infoEl = document.getElementById('paginationInfo');
        const pagEl = document.getElementById('pagination');
        if (!infoEl || !pagEl) return;

        if (total === 0) {
            infoEl.textContent = '';
            pagEl.innerHTML = '';
            return;
        }

        const start = (currentPage - 1) * PER_PAGE + 1;
        const end = Math.min(currentPage * PER_PAGE, total);
        infoEl.textContent = t('pagination_showing', 'Showing') + ' ' + start + '-' + end + ' ' + t('pagination_of', 'of') + ' ' + total;

        let html = '';
        // Prev
        html += '<button class="page-btn" data-page="' + (currentPage - 1) + '"' + (currentPage <= 1 ? ' disabled' : '') + '>&laquo;</button>';

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                html += '<button class="page-btn' + (i === currentPage ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                html += '<span class="page-ellipsis">...</span>';
            }
        }

        // Next
        html += '<button class="page-btn" data-page="' + (currentPage + 1) + '"' + (currentPage >= totalPages ? ' disabled' : '') + '>&raquo;</button>';

        pagEl.innerHTML = html;

        pagEl.querySelectorAll('.page-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const page = parseInt(this.getAttribute('data-page'));
                if (page >= 1 && page <= totalPages) {
                    goToPage(page);
                }
            });
        });
    }

    function goToPage(page) {
        currentPage = page;
        renderPage();
        const table = document.getElementById('addressesTable');
        if (table) table.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ═══════════════════════════════════════════════════════════
    // RENDER TABLE
    // ═══════════════════════════════════════════════════════════
    
    function renderTable(items) {
        if (!el.tbody) return;

        console.log('🎨 Rendering table with items:', items);

        if (!items || items.length === 0) {
            el.tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#888">' + t('no_addresses', 'No addresses found') + '</td></tr>';
            return;
        }

        el.tbody.innerHTML = items.map(addr => {
            const countryName = addr.country_name || addr.country || '';
            const cityName = addr.city_name || addr.city || '';
            const addressLine = addr.address_line1 || addr.address_line || '';
            const postalCode = addr.postal_code || '';
            const isPrimary = addr.is_primary || addr.is_default || false;

            const editBtn = CFG.permissions.canEdit 
                ? `<button class="btn btn-sm btn-secondary btnEdit" data-id="${addr.id}">${t('edit', 'Edit')}</button>` 
                : '';
            const deleteBtn = CFG.permissions.canDelete 
                ? `<button class="btn btn-sm btn-danger btnDelete" data-id="${addr.id}">${t('delete', 'Delete')}</button>` 
                : '';

            return `
                <tr>
                    <td>${addr.id}</td>
                    <td>${esc(countryName)}</td>
                    <td>${esc(cityName)}</td>
                    <td>${esc(addressLine)}</td>
                    <td>${esc(postalCode)}</td>
                    <td>${isPrimary ? t('primary_yes', '✔') : ''}</td>
                    <td>${editBtn} ${deleteBtn}</td>
                </tr>
            `;
        }).join('');

        // Attach event listeners
        el.tbody.querySelectorAll('.btnEdit').forEach(btn => {
            btn.onclick = () => editAddress(btn.dataset.id);
        });

        el.tbody.querySelectorAll('.btnDelete').forEach(btn => {
            btn.onclick = () => deleteAddress(btn.dataset.id);
        });

        console.log('✓ Table rendered with', items.length, 'rows');
    }

    // ═══════════════════════════════════════════════════════════
    // ADD ADDRESS
    // ═══════════════════════════════════════════════════════════
    
    function addAddress() {
        if (el.form) el.form.reset();
        if (el.formCard) el.formCard.style.display = 'block';
        if (el.formTitle) el.formTitle.textContent = t('add_address', 'Add Address');
        if (el.btnDelete) el.btnDelete.style.display = 'none';

        // Set default values for Super Admin fields
        if (CFG.canEditAllFields) {
            const ownerTypeSelect = document.getElementById('ownerTypeSelect');
            const ownerIdInput = document.getElementById('ownerIdInput');
            if (ownerTypeSelect) ownerTypeSelect.value = 'user';
            if (ownerIdInput) ownerIdInput.value = CFG.ownerId || '';
        }

        // Reset selects
        loadCountries();
        if (el.city) {
            el.city.innerHTML = '<option value="">' + t('select_city', 'Select City') + '</option>';
            el.city.disabled = true;
        }
        
        // Clear coordinates
        if (el.latitude) el.latitude.value = '';
        if (el.longitude) el.longitude.value = '';
    }

    // ═══════════════════════════════════════════════════════════
    // EDIT ADDRESS
    // ═══════════════════════════════════════════════════════════
    
    async function editAddress(id) {
        try {
            const url = `${API}/${id}?language=${encodeURIComponent(state.language)}`;
            const result = await apiFetch(url);
            
            const addr = result.data || result;

            if (el.formCard) el.formCard.style.display = 'block';
            if (el.formTitle) el.formTitle.textContent = t('edit_address', 'Edit Address');
            if (el.btnDelete) el.btnDelete.style.display = 'block';

            // Fill form
            if (el.form) {
                el.form.id.value = addr.id || '';
                el.form.address_line1.value = addr.address_line1 || addr.address_line || '';
                el.form.address_line2.value = addr.address_line2 || '';
                el.form.postal_code.value = addr.postal_code || '';
                el.form.is_primary.value = addr.is_primary || addr.is_default || '0';
                
                // Fill coordinates
                if (el.latitude) el.latitude.value = addr.latitude || '';
                if (el.longitude) el.longitude.value = addr.longitude || '';

                // Fill Super Admin fields if available
                if (CFG.canEditAllFields) {
                    const ownerTypeSelect = document.getElementById('ownerTypeSelect');
                    const ownerIdInput = document.getElementById('ownerIdInput');
                    if (ownerTypeSelect) ownerTypeSelect.value = addr.owner_type || 'user';
                    if (ownerIdInput) ownerIdInput.value = addr.owner_id || '';
                }
            }

            // Load countries and cities
            await loadCountries(addr.country_id);
            await loadCities(addr.country_id, addr.city_id);

        } catch (e) {
            console.error('❌ editAddress error:', e);
            showMessage(t('failed_load', 'Failed to load address'), 'error');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // SAVE ADDRESS
    // ═══════════════════════════════════════════════════════════
    
    async function saveAddress(e) {
        e.preventDefault();

        const formData = new FormData(el.form);
        const data = Object.fromEntries(formData.entries());

        // Add required fields
        data.tenant_id = CFG.tenantId;
        
        // For non-super-admin, use config owner values
        if (!CFG.canEditAllFields) {
            data.owner_type = CFG.ownerType || 'user';
            data.owner_id = CFG.ownerId || 1;
        }
        // For super-admin, values come from form (already in data)

        const id = data.id;
        if (id) delete data.id;

        console.log('💾 Saving address:', { id, data });

        try {
            const url = id ? `${API}/${id}` : API;
            const method = id ? 'PUT' : 'POST';

            const result = await apiFetch(url, {
                method,
                body: JSON.stringify(data)
            });

            console.log('📥 Save response:', result);

            if (result.success !== false) {
                showMessage(id ? t('address_updated', 'Address updated successfully') : t('address_created', 'Address created successfully'), 'success');
                if (el.formCard) el.formCard.style.display = 'none';
                loadAddresses();
            } else {
                const errorMsg = result.message || result.error || t('save_failed', 'Save failed');
                showMessage(errorMsg, 'error');
                console.error('Save failed:', result);
            }
        } catch (e) {
            console.error('❌ saveAddress error:', e);
            const errorMsg = e.message || t('save_failed', 'Failed to save address');
            showMessage(errorMsg, 'error');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // DELETE ADDRESS
    // ═══════════════════════════════════════════════════════════
    
    async function deleteAddress(id) {
        if (!confirm(t('confirm_delete', 'Are you sure you want to delete this address?'))) {
            return;
        }

        try {
            const result = await apiFetch(`${API}/${id}`, {
                method: 'DELETE',
                body: JSON.stringify({ csrf_token: CFG.csrf })
            });

            if (result.success !== false) {
                showMessage(t('address_deleted', 'Address deleted successfully'), 'success');
                loadAddresses();
            } else {
                showMessage(result.message || t('delete_failed', 'Delete failed'), 'error');
            }
        } catch (e) {
            console.error('❌ deleteAddress error:', e);
            showMessage(t('delete_failed', 'Failed to delete address'), 'error');
        }
    }

    // ═══════════════════════════════════════════════════════════
    // INIT
    // ═══════════════════════════════════════════════════════════
    
    async function init() {
        // Get elements
        el = {
            tbody: document.querySelector('#addressesTable tbody'),
            form: document.getElementById('addressForm'),
            formCard: document.getElementById('addressFormCard'),
            formTitle: document.getElementById('addressFormTitle'),
            country: document.getElementById('countrySelect'),
            city: document.getElementById('citySelect'),
            latitude: document.getElementById('latitude'),
            longitude: document.getElementById('longitude'),
            btnAdd: document.getElementById('btnAddAddress'),
            btnClose: document.getElementById('btnCloseForm'),
            btnDelete: document.getElementById('btnDeleteAddress'),
            btnGetLocation: document.getElementById('btnGetLocation')
        };

        // Attach events
        if (el.form) {
            el.form.onsubmit = saveAddress;
        }

        if (el.btnAdd) {
            el.btnAdd.onclick = addAddress;
        }

        if (el.btnClose) {
            el.btnClose.onclick = () => {
                if (el.formCard) el.formCard.style.display = 'none';
            };
        }

        if (el.btnDelete) {
            el.btnDelete.onclick = () => {
                const id = el.form?.id?.value;
                if (id) deleteAddress(id);
            };
        }

        if (el.btnGetLocation) {
            el.btnGetLocation.onclick = getUserLocation;
        }

        if (el.country) {
            el.country.onchange = () => {
                const countryId = el.country.value;
                loadCities(countryId);
            };
        }

        // Initial load
        await loadCountries();
        await loadAddresses();

        console.log('✓ Addresses module initialized');
    }

    // ═══════════════════════════════════════════════════════════
    // EXPOSE API
    // ═══════════════════════════════════════════════════════════
    
    window.Addresses = {
        init,
        load: loadAddresses,
        add: addAddress,
        edit: editAddress,
        delete: deleteAddress
    };

    // Auto-init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();