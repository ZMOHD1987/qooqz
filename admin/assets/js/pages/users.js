(function() {
    'use strict';

    console.log('%c════════════════════════════════════', 'color: #3B82F6');
    console.log('%c👥 Users Script Loading...', 'color: #10B981; font-weight: bold');
    console.log('%c════════════════════════════════════', 'color: #3B82F6');

    // Prevent double init
    if (window.UsersPageInitialized) {
        console.warn('⚠ Users already initialized, skipping...');
        return;
    }

    // ════════════════════════════════════════════════════════════
    // GLOBALS
    // ════════════════════════════════════════════════════════════
    
    let currentPage = 1;
    let filters = {};
    let permissions = {};
    let roles = [];
    let languages = [];
    let countries = [];
    let cities = [];
    let timezones = [];
    let userLanguage = window.USER_LANGUAGE || window.ADMIN_LANG || 'en';

    // ════════════════════════════════════════════════════════════
    // HELPERS
    // ════════════════════════════════════════════════════════════
    
    function getEl(id) { return document.getElementById(id); }
    function safeShow(id) { const el = getEl(id); if (el) el.style.display = 'block'; }
    function safeHide(id) { const el = getEl(id); if (el) el.style.display = 'none'; }
    function safeSetText(id, text) { const el = getEl(id); if (el) el.textContent = text; }
    function safeSetValue(id, value) {
        const el = getEl(id);
        if (!el) return;
        if (el.type === 'checkbox') el.checked = !!value;
        else el.value = value || '';
    }
    function safeGetValue(id) {
        const el = getEl(id);
        if (!el) return null;
        if (el.type === 'checkbox') return el.checked;
        return el.value;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        } catch (e) {
            return dateString;
        }
    }

    // ════════════════════════════════════════════════════════════
    // LOAD PERMISSIONS
    // ════════════════════════════════════════════════════════════
    
    function loadPermissions() {
        try {
            const script = getEl('pagePermissions');
            if (script) {
                permissions = JSON.parse(script.textContent);
                console.log('✓ Permissions:', permissions);
            } else {
                console.warn('⚠ pagePermissions not found');
                permissions = { canCreate: false, canEdit: false, canDelete: false };
            }
        } catch (e) {
            console.error('❌ Failed to parse permissions:', e);
            permissions = { canCreate: false, canEdit: false, canDelete: false };
        }
    }

    // ════════════════════════════════════════════════════════════
    // API HELPER
    // ════════════════════════════════════════════════════════════
    
    async function apiFetch(url, options = {}) {
        try {
            console.log('🔗 API:', url);
            const response = await fetch(url, options);
            const result = await response.json();
            console.log('📦 Response:', result.success ? '✓' : '✗', result);
            return result;
        } catch (error) {
            console.error('❌ API Error:', error);
            throw error;
        }
    }

    // ════════════════════════════════════════════════════════════
    // LOAD LANGUAGES
    // ════════════════════════════════════════════════════════════
    
    async function loadLanguages() {
        try {
            const result = await apiFetch('/api/languages');
            
            if (result.success && result.data) {
                languages = result.data;

                const filterSelect = getEl('languageFilter');
                if (filterSelect) {
                    filterSelect.innerHTML = '<option value="">All Languages</option>';
                    languages.forEach(lang => {
                        const option = document.createElement('option');
                        option.value = lang.code;
                        option.textContent = lang.name;
                        filterSelect.appendChild(option);
                    });
                }

                const formSelect = getEl('preferred_language');
                if (formSelect) {
                    formSelect.innerHTML = '';
                    languages.forEach(lang => {
                        const option = document.createElement('option');
                        option.value = lang.code;
                        option.textContent = lang.name;
                        formSelect.appendChild(option);
                    });
                }

                console.log('✓ Languages:', languages.length);
            }
        } catch (e) {
            console.error('❌ loadLanguages:', e);
        }
    }

    // ════════════════════════════════════════════════════════════
    // LOAD COUNTRIES
    // ════════════════════════════════════════════════════════════
    
    async function loadCountries(languageCode = null) {
        languageCode = languageCode || userLanguage;

        try {
            let url = '/api/countries';
            if (languageCode) url += '?language=' + encodeURIComponent(languageCode);

            const result = await apiFetch(url);
            
            if (result.success && result.data) {
                countries = result.data.data || result.data;

                const filterSelect = getEl('countryFilter');
                if (filterSelect) {
                    filterSelect.innerHTML = '<option value="">All Countries</option>';
                    countries.forEach(country => {
                        const option = document.createElement('option');
                        option.value = country.id;
                        option.textContent = country.name;
                        filterSelect.appendChild(option);
                    });
                }

                const formSelect = getEl('country_id');
                if (formSelect) {
                    formSelect.innerHTML = '<option value="">Select Country</option>';
                    countries.forEach(country => {
                        const option = document.createElement('option');
                        option.value = country.id;
                        option.textContent = country.name;
                        formSelect.appendChild(option);
                    });
                }

                console.log('✓ Countries:', countries.length);
            }
        } catch (e) {
            console.error('❌ loadCountries:', e);
        }
    }

    // ════════════════════════════════════════════════════════════
    // LOAD CITIES
    // ════════════════════════════════════════════════════════════
    
    async function loadCities(countryId, languageCode = null, targetSelectId = 'cityFilter') {
        languageCode = languageCode || userLanguage;

        const select = getEl(targetSelectId);
        if (!select) return;
        
        if (!countryId) {
            select.innerHTML = '<option value="">All Cities</option>';
            select.disabled = true;
            return;
        }

        try {
            let url = '/api/cities?country_id=' + encodeURIComponent(countryId);
            if (languageCode) url += '&language=' + encodeURIComponent(languageCode);

            const result = await apiFetch(url);
            
            if (result.success && result.data) {
                cities = result.data.data || result.data;

                select.innerHTML = '<option value="">All Cities</option>';
                select.disabled = false;
                
                cities.forEach(city => {
                    const option = document.createElement('option');
                    option.value = city.id;
                    option.textContent = city.name;
                    select.appendChild(option);
                });

                console.log('✓ Cities:', cities.length);
            }
        } catch (e) {
            console.error('❌ loadCities:', e);
        }
    }

    // ════════════════════════════════════════════════════════════
    // LOAD ROLES
    // ════════════════════════════════════════════════════════════
    
    async function loadRoles() {
        try {
            const result = await apiFetch('/api/roles');
            
            if (result.success && result.data) {
                roles = result.data;

                const filterSelect = getEl('roleFilter');
                if (filterSelect) {
                    filterSelect.innerHTML = '<option value="">All Roles</option>';
                    roles.forEach(role => {
                        const option = document.createElement('option');
                        option.value = role.id;
                        option.textContent = role.display_name || role.name;
                        filterSelect.appendChild(option);
                    });
                }

                const formSelect = getEl('role_id');
                if (formSelect) {
                    formSelect.innerHTML = '<option value="">Select Role</option>';
                    roles.forEach(role => {
                        const option = document.createElement('option');
                        option.value = role.id;
                        option.textContent = role.display_name || role.name;
                        formSelect.appendChild(option);
                    });
                }

                console.log('✓ Roles:', roles.length);
            }
        } catch (e) {
            console.error('❌ loadRoles:', e);
        }
    }

    // ════════════════════════════════════════════════════════════
    // LOAD TIMEZONES
    // ════════════════════════════════════════════════════════════
    
    async function loadTimezones() {
        try {
            const result = await apiFetch('/api/timezones');
            
            if (result.success && result.data) {
                timezones = result.data;

                const filterSelect = getEl('timezoneFilter');
                if (filterSelect) {
                    filterSelect.innerHTML = '<option value="">All Timezones</option>';
                    timezones.forEach(tz => {
                        const option = document.createElement('option');
                        option.value = tz.timezone;
                        option.textContent = tz.label;
                        filterSelect.appendChild(option);
                    });
                }

                const formSelect = getEl('timezone');
                if (formSelect) {
                    formSelect.innerHTML = '';
                    timezones.forEach(tz => {
                        const option = document.createElement('option');
                        option.value = tz.timezone;
                        option.textContent = tz.label;
                        formSelect.appendChild(option);
                    });
                }

                console.log('✓ Timezones:', timezones.length);
            }
        } catch (e) {
            console.error('❌ loadTimezones:', e);
        }
    }

    // ════════════════════════════════════════════════════════════
    // LOAD USERS
    // ════════════════════════════════════════════════════════════
    
    async function loadUsers(page = 1) {
        console.log('📊 Loading users, page:', page);
        
        try {
            showLoading();

            const params = new URLSearchParams({
                page: page,
                per_page: 10,
                ...filters
            });

            const result = await apiFetch('/api/users_account?' + params.toString());

            if (result.success && result.data) {
                currentPage = page;
                const items = result.data.items || result.data || [];
                console.log('✓ Loaded', items.length, 'users');
                renderTable(items);
                renderPagination(result.data.meta || {});
            } else {
                throw new Error(result.message || 'Failed to load users');
            }

        } catch (error) {
            console.error('❌ loadUsers:', error);
            showError('Failed to load users: ' + error.message);
        }
    }

    // ════════════════════════════════════════════════════════════
    // RENDER TABLE
    // ════════════════════════════════════════════════════════════
    
    function renderTable(data) {
        console.log('🎨 Rendering', data.length, 'rows');
        
        const tbody = getEl('tableBody');
        const tableContainer = getEl('tableContainer');
        const emptyState = getEl('emptyState');
        const loadingState = getEl('tableLoading');
        const errorState = getEl('errorState');

        if (!tbody) {
            console.error('❌ tableBody not found!');
            return;
        }

        if (loadingState) loadingState.style.display = 'none';
        if (errorState) errorState.style.display = 'none';

        if (!data || !data.length) {
            if (tableContainer) tableContainer.style.display = 'none';
            if (emptyState) emptyState.style.display = 'flex';
            return;
        }

        if (emptyState) emptyState.style.display = 'none';
        if (tableContainer) tableContainer.style.display = 'block';

        tbody.innerHTML = '';
        
        data.forEach(user => {
            const tr = document.createElement('tr');
            
            const statusClass = user.is_active ? 'badge-active' : 'badge-inactive';
            const statusText = user.is_active ? 'Active' : 'Inactive';

            tr.innerHTML = `
                <td>${user.id}</td>
                <td><strong>${escapeHtml(user.username)}</strong></td>
                <td>${escapeHtml(user.email)}</td>
                <td>${escapeHtml(user.role_name || 'N/A')}</td>
                <td>${escapeHtml(user.country_name || 'N/A')}</td>
                <td>${escapeHtml(user.city_name || 'N/A')}</td>
                <td>${formatDate(user.created_at)}</td>
                <td><span class="badge ${statusClass}">${statusText}</span></td>
                <td class="table-actions">
                    ${permissions.canEdit ? `<button onclick="Users.edit(${user.id})" class="btn btn-sm btn-icon"><i class="fas fa-edit"></i></button>` : ''}
                    ${permissions.canDelete ? `<button onclick="Users.delete(${user.id})" class="btn btn-sm btn-icon btn-danger"><i class="fas fa-trash"></i></button>` : ''}
                </td>
            `;
            
            tbody.appendChild(tr);
        });

        console.log('✓ Table rendered');
    }

    // ════════════════════════════════════════════════════════════
    // RENDER PAGINATION
    // ════════════════════════════════════════════════════════════
    
    function renderPagination(meta) {
        const pagination = getEl('pagination');
        const paginationInfo = getEl('paginationInfo');

        if (!meta || !meta.total) {
            if (pagination) pagination.innerHTML = '';
            if (paginationInfo) paginationInfo.textContent = '0-0 of 0';
            return;
        }

        const start = (meta.page - 1) * meta.per_page + 1;
        const end = Math.min(start + meta.per_page - 1, meta.total);
        if (paginationInfo) paginationInfo.textContent = `${start}-${end} of ${meta.total}`;

        if (!pagination) return;

        const totalPages = Math.ceil(meta.total / meta.per_page);
        pagination.innerHTML = '';

        const prevBtn = document.createElement('button');
        prevBtn.textContent = '‹';
        prevBtn.disabled = meta.page === 1;
        prevBtn.onclick = () => loadUsers(meta.page - 1);
        pagination.appendChild(prevBtn);

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= meta.page - 2 && i <= meta.page + 2)) {
                const btn = document.createElement('button');
                btn.textContent = i;
                btn.className = i === meta.page ? 'active' : '';
                btn.onclick = () => loadUsers(i);
                pagination.appendChild(btn);
            } else if (i === meta.page - 3 || i === meta.page + 3) {
                const dots = document.createElement('span');
                dots.textContent = '...';
                dots.style.padding = '0 0.5rem';
                pagination.appendChild(dots);
            }
        }

        const nextBtn = document.createElement('button');
        nextBtn.textContent = '›';
        nextBtn.disabled = meta.page === totalPages;
        nextBtn.onclick = () => loadUsers(meta.page + 1);
        pagination.appendChild(nextBtn);
    }

    // ════════════════════════════════════════════════════════════
    // FORM FUNCTIONS
    // ════════════════════════════════════════════════════════════
    
    function openAddForm() {
        console.log('📝 Add Form');
        
        safeSetValue('formAction', 'add');
        safeSetValue('editingId', '');
        safeSetText('formTitle', 'Add User');
        
        const form = getEl('userForm');
        if (form) form.reset();
        
        const password = getEl('password');
        if (password) password.required = true;
        
        safeSetText('passwordLabel', '*');
        safeSetValue('is_active', true);
        safeHide('btnDeleteUser');
        
        const citySelect = getEl('city_id');
        if (citySelect) {
            citySelect.innerHTML = '<option value="">Select City</option>';
            citySelect.disabled = true;
        }
        
        safeShow('formContainer');
        
        setTimeout(() => {
            const fc = getEl('formContainer');
            if (fc) fc.scrollIntoView({ behavior: 'smooth' });
        }, 100);
    }

    async function editUser(id) {
        console.log('📝 Edit:', id);
        
        try {
            const result = await apiFetch('/api/users_account?id=' + id);

            if (result.success && result.data) {
                const user = result.data;
                
                safeSetValue('formAction', 'edit');
                safeSetValue('editingId', user.id);
                safeSetText('formTitle', 'Edit User');
                safeSetValue('username', user.username);
                safeSetValue('email', user.email);
                safeSetValue('password', '');
                
                const password = getEl('password');
                if (password) password.required = false;
                
                safeSetText('passwordLabel', '(optional)');
                safeSetValue('preferred_language', user.preferred_language || 'en');
                safeSetValue('role_id', user.role_id);
                safeSetValue('country_id', user.country_id);
                
                if (user.country_id) {
                    await loadCities(user.country_id, userLanguage, 'city_id');
                    safeSetValue('city_id', user.city_id);
                } else {
                    const citySelect = getEl('city_id');
                    if (citySelect) {
                        citySelect.innerHTML = '<option value="">Select City</option>';
                        citySelect.disabled = true;
                    }
                }
                
                safeSetValue('phone', user.phone);
                safeSetValue('timezone', user.timezone || 'UTC');
                safeSetValue('is_active', user.is_active == 1);
                
                if (permissions.canDelete) safeShow('btnDeleteUser');
                
                safeShow('formContainer');
                
                setTimeout(() => {
                    const fc = getEl('formContainer');
                    if (fc) fc.scrollIntoView({ behavior: 'smooth' });
                }, 100);
            }
        } catch (error) {
            console.error('❌ editUser:', error);
            alert('Failed to load user: ' + error.message);
        }
    }

    function closeForm() {
        safeHide('formContainer');
        const form = getEl('userForm');
        if (form) form.reset();
    }

    async function submitForm(e) {
        e.preventDefault();
        console.log('💾 Submitting');
        
        const data = {
            username: safeGetValue('username'),
            email: safeGetValue('email'),
            preferred_language: safeGetValue('preferred_language'),
            role_id: parseInt(safeGetValue('role_id')) || null,
            country_id: parseInt(safeGetValue('country_id')) || null,
            city_id: parseInt(safeGetValue('city_id')) || null,
            phone: safeGetValue('phone'),
            timezone: safeGetValue('timezone'),
            is_active: safeGetValue('is_active') ? 1 : 0
        };

        const password = safeGetValue('password');
        if (password) data.password = password;

        const action = safeGetValue('formAction');
        const method = action === 'edit' ? 'PUT' : 'POST';
        
        if (action === 'edit') data.id = parseInt(safeGetValue('editingId'));

        try {
            const csrfToken = window.CSRF_TOKEN || safeGetValue('csrf_token') || '';
            
            const result = await apiFetch('/api/users_account', {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(data)
            });
            
            if (result.success) {
                closeForm();
                loadUsers(currentPage);
                alert(action === 'edit' ? 'User updated!' : 'User added!');
            } else {
                throw new Error(result.message || 'Save failed');
            }
        } catch (error) {
            console.error('❌ submitForm:', error);
            alert('Failed to save: ' + error.message);
        }
    }

    async function deleteUser(id) {
        if (!confirm('Delete this user?')) return;

        try {
            const result = await apiFetch('/api/users_account', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN || ''
                },
                body: JSON.stringify({ id: id })
            });

            if (result.success) {
                closeForm();
                loadUsers(currentPage);
                alert('User deleted!');
            } else {
                throw new Error(result.message || 'Delete failed');
            }
        } catch (error) {
            console.error('❌ deleteUser:', error);
            alert('Failed to delete: ' + error.message);
        }
    }

    function applyFilters() {
        filters = {};
        
        const search = safeGetValue('searchInput');
        if (search && search.trim()) filters.search = search.trim();

        const language = safeGetValue('languageFilter');
        if (language) filters.preferred_language = language;

        const countryId = safeGetValue('countryFilter');
        if (countryId) filters.country_id = countryId;

        const cityId = safeGetValue('cityFilter');
        if (cityId) filters.city_id = cityId;

        const timezone = safeGetValue('timezoneFilter');
        if (timezone) filters.timezone = timezone;

        const roleId = safeGetValue('roleFilter');
        if (roleId) filters.role_id = roleId;

        const status = safeGetValue('statusFilter');
        if (status !== '' && status !== null) filters.is_active = status;

        console.log('🔍 Filters:', filters);
        loadUsers(1);
    }

    function resetFilters() {
        ['searchInput', 'languageFilter', 'countryFilter', 'cityFilter', 'timezoneFilter', 'roleFilter', 'statusFilter'].forEach(id => {
            const el = getEl(id);
            if (el) {
                if (el.tagName === 'SELECT') el.selectedIndex = 0;
                else el.value = '';
            }
        });
        
        const cityFilter = getEl('cityFilter');
        if (cityFilter) cityFilter.disabled = true;
        
        filters = {};
        loadUsers(1);
    }

    function showLoading() {
        safeShow('tableLoading');
        safeHide('tableContainer');
        safeHide('emptyState');
        safeHide('errorState');
    }

    function showError(message) {
        safeHide('tableLoading');
        safeHide('tableContainer');
        safeHide('emptyState');
        safeShow('errorState');
        safeSetText('errorMessage', message);
    }

    // ════════════════════════════════════════════════════════════
    // BIND EVENTS
    // ════════════════════════════════════════════════════════════
    
    function bindEvents() {
        console.log('���� Binding...');
        
        const events = {
            'btnAddUser': openAddForm,
            'btnCloseForm': closeForm,
            'btnCancelForm': closeForm,
            'btnApplyFilters': applyFilters,
            'btnResetFilters': resetFilters,
            'btnRetry': () => loadUsers(currentPage)
        };

        for (const [id, handler] of Object.entries(events)) {
            const el = getEl(id);
            if (el && !el._bound) {
                el.addEventListener('click', handler);
                el._bound = true;
            }
        }

        const btnDelete = getEl('btnDeleteUser');
        if (btnDelete && !btnDelete._bound) {
            btnDelete.addEventListener('click', () => {
                const id = safeGetValue('editingId');
                if (id) deleteUser(parseInt(id));
            });
            btnDelete._bound = true;
        }

        const form = getEl('userForm');
        if (form && !form._bound) {
            form.addEventListener('submit', submitForm);
            form._bound = true;
        }

        const countryFilter = getEl('countryFilter');
        if (countryFilter && !countryFilter._bound) {
            countryFilter.addEventListener('change', (e) => {
                loadCities(e.target.value, userLanguage, 'cityFilter');
            });
            countryFilter._bound = true;
        }

        const countryForm = getEl('country_id');
        if (countryForm && !countryForm._bound) {
            countryForm.addEventListener('change', (e) => {
                loadCities(e.target.value, userLanguage, 'city_id');
            });
            countryForm._bound = true;
        }

        const searchInput = getEl('searchInput');
        if (searchInput && !searchInput._bound) {
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyFilters();
                }
            });
            searchInput._bound = true;
        }
        
        console.log('✓ Events bound');
    }

    // ════════════════════════════════════════════════════════════
    // INITIALIZE
    // ════════════════════════════════════════════════════════════
    
    async function init() {
        console.log('🚀 Initializing...');
        
        if (!getEl('tableBody')) {
            console.error('❌ tableBody not found!');
            return Promise.resolve(false);
        }
        
        loadPermissions();
        bindEvents();
        
        console.log('📥 Loading data...');
        await Promise.all([
            loadLanguages(),
            loadCountries(),
            loadTimezones(),
            loadRoles()
        ]);
        
        console.log('📊 Loading users...');
        await loadUsers();
        
        window.UsersPageInitialized = true;
        console.log('✅ Initialized!');
        return Promise.resolve(true);
    }

    // ════════════════════════════════════════════════════════════
    // EXPOSE API
    // ════════════════════════════════════════════════════════════
    
    window.Users = {
        reload: loadUsers,
        add: openAddForm,
        edit: editUser,
        delete: deleteUser,
        init: init
    };

    console.log('✓ Users API ready');

    // ════════════════════════════════════════════════════════════
    // AUTO-INIT
    // ════════════════════════════════════════════════════════════
    
    function tryInit(attempt = 1) {
        if (getEl('tableBody')) {
            console.log('✓ tableBody found');
            init();
        } else if (attempt < 20) {
            setTimeout(() => tryInit(attempt + 1), 200);
        } else {
            console.error('❌ tableBody not found after 20 attempts');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => setTimeout(tryInit, 100));
    } else {
        setTimeout(tryInit, 100);
    }

})();