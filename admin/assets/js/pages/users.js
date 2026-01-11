/**
 * admin/assets/js/pages/users.js
 * User management - Respects translations and theme from ADMIN_UI_PAYLOAD
 */
(function () {
    if (!window.USERS_CONFIG) return;

    const cfg = window.USERS_CONFIG;
    const trans = cfg.translations || {};
    const el = id => document.getElementById(id);
    const tbody = el('vusersTbody');
    const form = el('vusersForm');
    const modal = el('vusersFormWrap');

    // Translation helper with fallback
    const t = (key, fallback = '') => {
        const parts = key.split('.');
        let value = trans;
        for (const part of parts) {
            if (value && typeof value === 'object' && part in value) {
                value = value[part];
            } else {
                return fallback || key;
            }
        }
        return typeof value === 'string' ? value : fallback || key;
    };

    const getCsrf = () => cfg.csrfToken || form.querySelector('[name="csrf_token"]')?.value || "";

    /* ================= Load Roles ================= */
    async function loadRoles(selectedId = null) {
        try {
            const r = await fetch('/api/routes/roles.php');
            const j = await r.json();
            if (j.success) {
                let html = `<option value="">${t('form.select_role', 'Select Role')}</option>`;
                let filterHtml = `<option value="">${t('filters.all_roles', 'All Roles')}</option>`;
                
                j.data.forEach(role => {
                    const roleTitle = role.display_name || role.key_name || role.name || 'Role ' + role.id;
                    const sel = (role.id == selectedId) ? 'selected' : '';
                    
                    html += `<option value="${role.id}" ${sel}>${roleTitle}</option>`;
                    filterHtml += `<option value="${role.id}">${roleTitle}</option>`;
                });
                
                if (el('vusersRole')) el('vusersRole').innerHTML = html;
                if (el('vusersRoleFilter') && el('vusersRoleFilter').options.length <= 1) {
                    el('vusersRoleFilter').innerHTML = filterHtml;
                }
            }
        } catch (e) { console.error("Error loading roles", e); }
    }

    /* ================= Load Countries and Cities ================= */
    async function loadCountries(selectedId = null, cityId = null) {
        try {
            const r = await fetch('/api/helpers/countries.php');
            const j = await r.json();
            if (j.success) {
                let html = `<option value="">${t('form.select_country', 'Select Country')}</option>`;
                let fHtml = `<option value="">${t('filters.all_countries', 'All Countries')}</option>`;
                j.data.forEach(c => {
                    const sel = (c.id == selectedId) ? 'selected' : '';
                    html += `<option value="${c.id}" ${sel}>${c.name}</option>`;
                    fHtml += `<option value="${c.id}">${c.name}</option>`;
                });
                el('vusersCountry').innerHTML = html;
                if (el('vusersCountryFilter').options.length <= 1) el('vusersCountryFilter').innerHTML = fHtml;
                if (selectedId) loadCities(selectedId, cityId);
            }
        } catch (e) { console.error("Error loading countries", e); }
    }

    async function loadCities(countryId, selectedId = null) {
        const citySel = el('vusersCity');
        if (!countryId) { 
            citySel.innerHTML = `<option value="">${t('form.select_country_first', 'Select Country First')}</option>`; 
            return; 
        }
        try {
            const r = await fetch(`/api/helpers/cities.php?country_id=${countryId}`);
            const j = await r.json();
            let html = `<option value="">${t('form.select_city', 'Select City')}</option>`;
            if (j.success) {
                j.data.forEach(c => {
                    const sel = (c.id == selectedId) ? 'selected' : '';
                    html += `<option value="${c.id}" ${sel}>${c.name}</option>`;
                });
            }
            citySel.innerHTML = html;
        } catch (e) { console.error("Error loading cities", e); }
    }

    el('vusersCountry').onchange = (e) => loadCities(e.target.value);

    /* ================= Load Languages ================= */
    async function loadLanguages(selectedLang = null) {
        try {
            const r = await fetch('/api/routes/languages.php');
            const j = await r.json();
            if (j.success && j.data) {
                let html = `<option value="">${t('form.select_language', 'Select Language')}</option>`;
                let filterHtml = `<option value="">${t('filters.all_languages', 'All Languages')}</option>`;
                
                j.data.forEach(lang => {
                    const langCode = lang.code || lang.iso_code || lang.locale || lang.id;
                    const langName = lang.name || lang.display_name || langCode;
                    const sel = (langCode == selectedLang) ? 'selected' : '';
                    
                    html += `<option value="${langCode}" ${sel}>${langName}</option>`;
                    filterHtml += `<option value="${langCode}">${langName}</option>`;
                });
                
                if (el('vusersLang')) el('vusersLang').innerHTML = html;
                if (el('vusersLangFilter') && el('vusersLangFilter').options.length <= 1) {
                    el('vusersLangFilter').innerHTML = filterHtml;
                }
            }
        } catch (e) { 
            console.error("Error loading languages", e);
            // Fallback to basic language options
            const basicLangs = [
                {code: 'en', name: 'English'},
                {code: 'ar', name: 'العربية'}
            ];
            let html = `<option value="">${t('form.select_language', 'Select Language')}</option>`;
            basicLangs.forEach(lang => {
                const sel = (lang.code == selectedLang) ? 'selected' : '';
                html += `<option value="${lang.code}" ${sel}>${lang.name}</option>`;
            });
            if (el('vusersLang')) el('vusersLang').innerHTML = html;
        }
    }

    /* ================= Load Users Table ================= */
    async function loadUsers() {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:50px;">${t('messages.loading', 'Loading...')}</td></tr>`;
        
        const params = new URLSearchParams({
            q: el('vusersSearch').value,
            role_id: el('vusersRoleFilter').value,
            country_id: el('vusersCountryFilter').value,
            is_active: el('vusersStatusFilter').value
        });

        try {
            const r = await fetch(`${cfg.apiUrl}?${params.toString()}`);
            const j = await r.json();
            tbody.innerHTML = '';

            if (j.success && j.data.length > 0) {
                j.data.forEach(u => {
                    const tr = document.createElement('tr');
                    
                    const displayRole = u.role_display_name || u.role_name || `Role ID: ${u.role_id}`;
                    const statusLabel = u.is_active == 1 ? t('status.active', 'Active') : t('status.inactive', 'Inactive');
                    const statusClass = u.is_active == 1 ? 'badge-active' : 'badge-inactive';

                    tr.innerHTML = `
                        <td>
                            <div style="font-weight:700; color: var(--theme-text-primary);">${u.username}</div>
                            <div style="font-size:0.75rem; color: var(--theme-text-secondary);">${u.email}</div>
                        </td>
                        <td>
                            <span class="badge" style="background:rgba(59,130,246,0.1); color: var(--theme-primary); padding:4px 10px; border-radius:6px; font-size:0.75rem;">
                                ${displayRole}
                            </span>
                        </td>
                        <td>
                            <div style="font-size:0.85rem; color: var(--theme-text-primary);">${u.country_name || t('no_country', 'No Country')}</div>
                            <div style="font-size:0.75rem; color: var(--theme-text-secondary);">${u.city_name || t('no_city', 'No City')}</div>
                        </td>
                        <td>
                            <span class="badge ${statusClass}">${statusLabel}</span>
                        </td>
                        <td style="text-align:center;">
                            <button class="btn-icon edit-btn" title="${t('edit', 'Edit')}">✏️</button>
                            <button class="btn-icon delete delete-btn" title="${t('delete', 'Delete')}">🗑</button>
                        </td>
                    `;

                    tr.querySelector('.edit-btn').onclick = () => editUser(u);
                    tr.querySelector('.delete-btn').onclick = () => deleteUser(u.id);

                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px;">${t('messages.no_data', 'No users found.')}</td></tr>`;
            }
        } catch (e) { 
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color: var(--theme-error);">${t('messages.error_fetch', 'Failed to load data.')}</td></tr>`; 
        }
    }

    /* ================= Form Operations ================= */
    form.onsubmit = async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const id = el('vusersId').value;
        fd.append('action', id ? 'update' : 'create');
        fd.set('csrf_token', getCsrf());

        try {
            const r = await fetch(cfg.apiUrl, { method: 'POST', body: fd });
            const j = await r.json();
            if (j.success) { 
                closeModal(); 
                loadUsers(); 
            } else { 
                alert(j.message || t('messages.error_save', 'Error saving data')); 
            }
        } catch (err) { 
            alert(t('messages.error_save', 'Server connection failed')); 
        }
    };

    async function deleteUser(id) {
        if (!confirm(t('messages.delete_confirm', 'Are you sure?'))) return;
        const fd = new FormData();
        fd.append('id', id);
        fd.append('action', 'delete');
        fd.set('csrf_token', getCsrf());
        try {
            await fetch(cfg.apiUrl, { method: 'POST', body: fd });
            loadUsers();
        } catch (e) {
            alert(t('messages.error_delete', 'Failed to delete'));
        }
    }

    function editUser(u) {
        el('vusersFormTitle').innerText = t('edit_user', 'Edit User');
        el('vusersId').value = u.id;
        el('vusersUsername').value = u.username;
        el('vusersDisplayName').value = u.display_name || '';
        el('vusersEmail').value = u.email;
        el('vusersPhone').value = u.phone || '';
        el('vusersPassword').value = '';
        
        // Set radio button for status
        const activeRadio = form.querySelector('input[name="is_active"][value="1"]');
        const inactiveRadio = form.querySelector('input[name="is_active"][value="0"]');
        if (u.is_active == 1) {
            if (activeRadio) activeRadio.checked = true;
        } else {
            if (inactiveRadio) inactiveRadio.checked = true;
        }

        loadRoles(u.role_id);
        loadCountries(u.country_id, u.city_id);
        loadLanguages(u.preferred_language);
        modal.style.display = 'flex';
    }

    function closeModal() {
        modal.style.display = 'none';
        form.reset();
        el('vusersId').value = '';
    }

    el('vusersNew').onclick = () => {
        el('vusersFormTitle').innerText = t('add_user', 'Add New User');
        closeModal();
        loadRoles();
        loadCountries();
        loadLanguages();
        modal.style.display = 'flex';
    };

    el('vusersCancel').onclick = closeModal;
    if (el('vusersCloseX')) el('vusersCloseX').onclick = closeModal;

    // Search and filter
    let timer;
    el('vusersSearch').oninput = () => {
        clearTimeout(timer);
        timer = setTimeout(loadUsers, 400);
    };
    el('vusersRoleFilter').onchange = loadUsers;
    el('vusersCountryFilter').onchange = loadUsers;
    el('vusersStatusFilter').onchange = loadUsers;
    el('vusersRefresh').onclick = () => { location.reload(); };

    // Initialize
    loadUsers();
    loadRoles();
    loadCountries();
    loadLanguages();
})();
