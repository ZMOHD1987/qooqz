/**
 * htdocs/admin/assets/js/pages/users.js
 * إصدار نهائي: معالجة display_name وعرض الموقع (الدولة/المدينة)
 */
(function () {
    if (!window.USERS_CONFIG) return;

    const cfg = window.USERS_CONFIG;
    const el = id => document.getElementById(id);
    const tbody = el('vusersTbody');
    const form = el('vusersForm');
    const modal = el('vusersFormWrap');

    const getCsrf = () => cfg.csrfToken || form.querySelector('[name="csrf_token"]')?.value || "";

    /* ================= 1. جلب الأدوار (حل مشكلة Unknown) ================= */
    async function loadRoles(selectedId = null) {
        try {
            const r = await fetch('/api/routes/roles.php');
            const j = await r.json();
            if (j.success) {
                let html = '<option value="">Select Role</option>';
                let filterHtml = '<option value="">All Roles</option>';
                
                j.data.forEach(role => {
                    // الحل: استخدام display_name كما هو وارد في الـ API الخاص بك
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

    /* ================= 2. جلب الدول والمدن ================= */
    async function loadCountries(selectedId = null, cityId = null) {
        try {
            const r = await fetch('/api/helpers/countries.php');
            const j = await r.json();
            if (j.success) {
                let html = '<option value="">Select Country</option>';
                let fHtml = '<option value="">All Countries</option>';
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
        if (!countryId) { citySel.innerHTML = '<option value="">Select Country First</option>'; return; }
        try {
            const r = await fetch(`/api/helpers/cities.php?country_id=${countryId}`);
            const j = await r.json();
            let html = '<option value="">Select City</option>';
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

    /* ================= 3. عرض الجدول (حل مشكلة عرض الـ ID بدل الاسم) ================= */
    async function loadUsers() {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:50px;">Fetching records...</td></tr>';
        
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
                    
                    // تحويل رقم الدور لاسم في حال لم يرسله السيرفر كاسم جاهز
                    // ملاحظة: يفضل دائماً عمل JOIN في SQL لجلب role_name مباشرة
                    const displayRole = u.role_display_name || u.role_name || 'Role ID: ' + u.role_id;

                    tr.innerHTML = `
                        <td>
                            <div style="font-weight:700; color:#fff;">${u.username}</div>
                            <div style="font-size:0.75rem; color:#64748b;">${u.email}</div>
                        </td>
                        <td>
                            <span class="badge" style="background:rgba(59,130,246,0.1); color:#3b82f6; padding:4px 10px; border-radius:6px; font-size:0.75rem;">
                                ${displayRole}
                            </span>
                        </td>
                        <td>
                            <div style="font-size:0.85rem; color:#f1f5f9;">${u.country_name || 'No Country'}</div>
                            <div style="font-size:0.75rem; color:#64748b;">${u.city_name || 'No City'}</div>
                        </td>
                        <td>
                            <label class="switch">
                                <input type="checkbox" ${u.is_active == 1 ? 'checked' : ''} class="status-toggle">
                                <span class="slider"></span>
                            </label>
                        </td>
                        <td style="text-align:center;">
                            <button class="btn-icon edit-btn">✏️</button>
                            <button class="btn-icon delete delete-btn">🗑</button>
                        </td>
                    `;

                    tr.querySelector('.edit-btn').onclick = () => editUser(u);
                    tr.querySelector('.delete-btn').onclick = () => deleteUser(u.id);
                    tr.querySelector('.status-toggle').onchange = () => toggleStatus(u.id, u.is_active);

                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:40px;">No users found.</td></tr>';
            }
        } catch (e) { tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:red;">Failed to load data.</td></tr>'; }
    }

    /* ================= 4. العمليات وإدارة النموذج ================= */
    form.onsubmit = async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const id = el('vusersId').value;
        fd.append('action', id ? 'update' : 'create');
        fd.set('csrf_token', getCsrf());

        try {
            const r = await fetch(cfg.apiUrl, { method: 'POST', body: fd });
            const j = await r.json();
            if (j.success) { closeModal(); loadUsers(); } else { alert(j.message || "Error saving data"); }
        } catch (err) { alert("Server connection failed"); }
    };

    async function deleteUser(id) {
        if (!confirm("Are you sure?")) return;
        const fd = new FormData();
        fd.append('id', id);
        fd.append('action', 'delete');
        fd.set('csrf_token', getCsrf());
        await fetch(cfg.apiUrl, { method: 'POST', body: fd });
        loadUsers();
    }

    async function toggleStatus(id, current) {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('is_active', current == 1 ? 0 : 1);
        fd.append('action', 'update');
        fd.set('csrf_token', getCsrf());
        await fetch(cfg.apiUrl, { method: 'POST', body: fd });
        // لا داعي لإعادة تحميل الجدول لسرعة الاستجابة، لكن سنحدث الحالة داخلياً
        loadUsers(); 
    }

    function editUser(u) {
        el('vusersFormTitle').innerText = "Edit User";
        el('vusersId').value = u.id;
        el('vusersUsername').value = u.username;
        el('vusersEmail').value = u.email;
        el('vusersPassword').value = '';
        el('vusersStatus').value = u.is_active;

        loadRoles(u.role_id);
        loadCountries(u.country_id, u.city_id);
        modal.style.display = 'flex';
    }

    function closeModal() {
        modal.style.display = 'none';
        form.reset();
        el('vusersId').value = '';
    }

    el('vusersNew').onclick = () => {
        el('vusersFormTitle').innerText = "Add New User";
        closeModal();
        loadRoles();
        loadCountries();
        modal.style.display = 'flex';
    };

    el('vusersCancel').onclick = closeModal;
    if (el('vusersClose')) el('vusersClose').onclick = closeModal;

    // البحث والفلترة
    let timer;
    el('vusersSearch').oninput = () => {
        clearTimeout(timer);
        timer = setTimeout(loadUsers, 400);
    };
    el('vusersRoleFilter').onchange = loadUsers;
    el('vusersCountryFilter').onchange = loadUsers;
    el('vusersStatusFilter').onchange = loadUsers;
    el('vusersRefresh').onclick = () => { location.reload(); };

    // تشغيل الصفحة
    loadUsers();
    loadRoles();
    loadCountries();
})();
