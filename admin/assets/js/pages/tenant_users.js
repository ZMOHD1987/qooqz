/**
 * Tenant Users Management - Production Version with Full Translation Support
 * Version: 4.0.0 - Complete & Production Ready with i18n Support
 * Compatible with AdminFramework and fragments
 * Supports automatic RTL/LTR direction based on language
 */
(function() {
    'use strict';

    const AF = window.AdminFramework;
    const API = '/api/tenant_users';

    const state = {
        page: 1,
        perPage: 10,
        filters: {},
        permissions: {},
        translations: {},
        language: window.USER_LANGUAGE || 'ar'
    };

    let el = {};

    // ----------------------------
    // Direction helper
    // ----------------------------
    function setDirectionForLang(lang) {
        if (!lang) return;
        const rtlLangs = ['ar','he','fa','ur','ps'];
        const isRtl = rtlLangs.includes(String(lang).toLowerCase().substring(0,2));
        const dir = isRtl ? 'rtl' : 'ltr';

        try { document.documentElement.dir = dir; } catch (e) { /* ignore */ }

        if (document.body) {
            document.body.classList.toggle('rtl', isRtl);
            document.body.classList.toggle('ltr', !isRtl);
        }

        const container = document.getElementById('tenantUsersPageContainer') || document.querySelector('.page-container');
        if (container) {
            container.dir = dir;
            container.classList.toggle('rtl', isRtl);
            container.classList.toggle('ltr', !isRtl);
        }

        // flip helper icons if used
        document.querySelectorAll('.flip-on-rtl').forEach(el => {
            el.classList.toggle('is-rtl', isRtl);
        });

        console.log('[TenantUsers] direction applied:', dir);
    }

    // ----------------------------
    // TRANSLATION SYSTEM
    // ----------------------------
    async function loadTranslations(lang = state.language) {
        try {
            console.log('[TenantUsers] Loading translations for:', lang);
            const response = await fetch(`/languages/TenantUsers/${lang}.json`, { credentials: 'same-origin' });
            if (!response.ok) throw new Error(`Failed to load translations: ${response.status}`);
            const data = await response.json();
            state.translations = data;
            state.language = lang;
            console.log('[TenantUsers] Translations loaded successfully');
            return true;
        } catch (error) {
            console.error('[TenantUsers] Failed to load translations:', error);
            if (lang !== 'en') {
                console.log('[TenantUsers] Falling back to English');
                return loadTranslations('en');
            }
            state.translations = getFallbackTranslations();
            return true;
        }
    }

    function getFallbackTranslations() {
        return {
            tenant_users: {
                title: "Tenant Users Management",
                subtitle: "Manage users assigned to tenants",
                add_new: "Add New User",
                loading: "Loading...",
                no_data: "No data available",
                error: "An error occurred",
                retry: "Retry"
            },
            table: {
                headers: {
                    id: "ID",
                    username: "Username",
                    email: "Email",
                    tenant: "Tenant",
                    role: "Role",
                    joined_at: "Joined At",
                    status: "Status",
                    actions: "Actions"
                },
                actions: {
                    edit: "Edit",
                    delete: "Delete",
                    confirm_delete: "Are you sure you want to delete this user? This action cannot be undone."
                },
                status: {
                    active: "Active",
                    inactive: "Inactive"
                },
                empty: {
                    title: "No Tenant Users Found",
                    message: "Start by adding users to tenants",
                    add_first: "Add First User"
                }
            },
            filters: {
                search: "Search",
                search_placeholder: "Search...",
                tenant_id: "Tenant ID",
                tenant_placeholder: "Filter by tenant",
                user_id: "User ID",
                user_placeholder: "Filter by user",
                status: "Status",
                status_options: {
                    all: "All Status",
                    active: "Active",
                    inactive: "Inactive"
                },
                apply: "Apply",
                reset: "Reset"
            },
            form: {
                add_title: "Add New Tenant User",
                edit_title: "Edit Tenant User",
                fields: {
                    tenant_id: {
                        label: "Tenant ID",
                        placeholder: "Enter tenant ID"
                    },
                    user_id: {
                        label: "User ID",
                        placeholder: "Enter user ID"
                    },
                    role_id: {
                        label: "Role",
                        placeholder: "Select role",
                        enter_tenant_first: "Enter tenant ID first",
                        loading: "Loading..."
                    },
                    status: {
                        label: "Status",
                        active: "Active",
                        inactive: "Inactive"
                    }
                },
                tenant_info: {
                    title: "Tenant Information",
                    name: "Name:",
                    domain: "Domain:",
                    status: "Status:"
                },
                user_info: {
                    title: "User Information",
                    name: "Name:",
                    email: "Email:",
                    status: "Status:"
                },
                buttons: {
                    save: "Save",
                    cancel: "Cancel",
                    saving: "Saving...",
                    updating: "Updating..."
                }
            },
            messages: {
                success: {
                    created: "User created successfully",
                    updated: "User updated successfully",
                    deleted: "User deleted successfully"
                },
                error: {
                    load_failed: "Failed to load data",
                    save_failed: "Failed to save data",
                    delete_failed: "Failed to delete data",
                    not_found: "Item not found"
                }
            },
            validation: {
                required: "Required"
            }
        };
    }

    function t(key, fallback = '') {
        const keys = key.split('.');
        let value = state.translations;
        for (const k of keys) {
            value = value && value[k];
        }
        return value || fallback || key;
    }

    function tReplace(key, replacements = {}) {
        let text = t(key);
        for (const [placeholder, value] of Object.entries(replacements)) {
            text = text.replace(new RegExp(`{${placeholder}}`, 'g'), value);
        }
        return text;
    }

    // ----------------------------
    // API RESPONSE NORMALIZER
    // ----------------------------
    function normalizeApiResponse(response) {
        // Accept shapes:
        // - { success:true, message:'OK', data: [...], meta: {...} }
        // - { items: [...], meta: {...} }
        // - [...] (array)
        // - { ...single object... }
        let wrapper = null;
        if (response && typeof response === 'object' && response.data !== undefined) wrapper = response;
        const topMeta = response && typeof response === 'object' && response.meta ? response.meta : null;
        const payload = wrapper ? wrapper.data : response;
        const metaFromPayload = payload && typeof payload === 'object' && payload.meta ? payload.meta : null;
        const meta = topMeta || metaFromPayload || null;
        return { payload, meta };
    }

    // ----------------------------
    // API HELPERS
    // ----------------------------
    async function getUser(id) {
        const cached = AF.Cache.get(`user_${id}`);
        if (cached) return cached;
        try {
            const res = await AF.get(`/api/users_account/${id}`);
            const { payload } = normalizeApiResponse(res);
            let user = null;
            if (Array.isArray(payload)) user = payload[0] || null;
            else user = payload || null;
            if (user) AF.Cache.set(`user_${id}`, user);
            return user;
        } catch (e) {
            console.error('[TenantUsers] getUser failed:', e);
            return null;
        }
    }

    async function getTenant(id) {
        const cached = AF.Cache.get(`tenant_${id}`);
        if (cached) return cached;
        try {
            const res = await AF.get(`/api/tenants/${id}`);
            const { payload } = normalizeApiResponse(res);
            const tenant = Array.isArray(payload) ? payload[0] : payload;
            if (tenant) AF.Cache.set(`tenant_${id}`, tenant);
            return tenant;
        } catch (e) {
            console.error('[TenantUsers] getTenant failed:', e);
            return null;
        }
    }

    async function getRoles(tenantId) {
        const key = `roles_${tenantId}`;
        const cached = AF.Cache.get(key);
        if (cached) return cached;
        try {
            const response = await AF.get(`/api/roles?tenant_id=${tenantId}`);
            const { payload } = normalizeApiResponse(response);
            let roles = [];
            if (Array.isArray(payload)) roles = payload;
            else if (payload && Array.isArray(payload.items)) roles = payload.items;
            if (roles.length > 0) AF.Cache.set(key, roles);
            return roles;
        } catch (e) {
            console.error('[TenantUsers] getRoles failed:', e);
            return [];
        }
    }

    // ----------------------------
    // RENDER FUNCTIONS
    // ----------------------------
    function renderTable(items) {
        console.log('[TenantUsers] Rendering table with', items?.length || 0, 'items');
        if (!el.tbody) { console.error('[TenantUsers] tbody element not found!'); return; }
        if (el.loading) el.loading.style.display = 'none';

        if (!items || !items.length) {
            if (el.empty) {
                el.empty.innerHTML = `
                    <div class="empty-icon">👥</div>
                    <h3>${t('table.empty.title')}</h3>
                    <p>${t('table.empty.message')}</p>
                    ${state.permissions.canCreate ? `<button class="btn btn-primary" onclick="TenantUsers.add()">${t('table.empty.add_first')}</button>` : ''}
                `;
                el.empty.style.display = 'block';
            }
            if (el.container) el.container.style.display = 'none';
            if (el.error) el.error.style.display = 'none';
            el.tbody.innerHTML = '';
            return;
        }

        let html = '';
        for (const item of items) {
            const username = item.username || t('validation.required', 'Unknown');
            const email = item.email || t('validation.required', 'N/A');
            const tenantName = item.tenant_name || t('validation.required', 'Unknown');
            const roleName = item.role_name || t('validation.required', 'N/A');
            const joinedAt = item.joined_at || t('validation.required', 'N/A');
            const statusText = item.is_active ? t('table.status.active') : t('table.status.inactive');
            const statusClass = item.is_active ? 'badge-success' : 'badge-danger';

            html += `
                <tr>
                    <td>${item.id}</td>
                    <td>
                        <strong>${escapeHtml(username)}</strong>
                        <br><small style="color:#94a3b8">${t('table.headers.id')}: ${item.user_id}</small>
                    </td>
                    <td>${escapeHtml(email)}</td>
                    <td>
                        <strong>${escapeHtml(tenantName)}</strong>
                        <br><small style="color:#94a3b8">${t('table.headers.id')}: ${item.tenant_id}</small>
                    </td>
                    <td>
                        <span class="badge badge-info" style="background-color: #3b82f6; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                            ${escapeHtml(roleName)}
                        </span>
                    </td>
                    <td>${joinedAt}</td>
                    <td>
                        <span class="badge ${statusClass}"
                              style="background-color: ${item.is_active ? '#10b981' : '#ef4444'}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                            ${statusText}
                        </span>
                    </td>
                    <td>
                        <div class="table-actions" style="display: flex; gap: 8px;">
                            ${state.permissions.canEdit ? `<button class="btn btn-sm btn-outline" onclick="TenantUsers.edit(${item.id})" style="padding: 4px 8px; border: 1px solid #d1d5db; background: white; color: #374151; border-radius: 4px; font-size: 12px;">${t('table.actions.edit')}</button>` : ''}
                            ${state.permissions.canDelete ? `<button class="btn btn-sm btn-danger" onclick="TenantUsers.remove(${item.id})" style="padding: 4px 8px; background-color: #ef4444; color: white; border: none; border-radius: 4px; font-size: 12px;">${t('table.actions.delete')}</button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }

        el.tbody.innerHTML = html;
        if (el.container) el.container.style.display = 'block';
        if (el.empty) el.empty.style.display = 'none';
        if (el.error) el.error.style.display = 'none';
        console.log('[TenantUsers] Table rendered successfully!');
    }

    // ----------------------------
    // FORM FUNCTIONS
    // ----------------------------
    const verifyTenant = AF.debounce(async () => {
        const id = el.formTenantId.value.trim();
        console.log('[TenantUsers] Verifying tenant ID:', id);
        if (!id || isNaN(id)) {
            el.tenantInfo.style.display = 'none';
            el.formRoleId.disabled = true;
            el.formRoleId.innerHTML = `<option value="">${t('form.fields.role_id.enter_tenant_first')}</option>`;
            return;
        }

        try {
            el.formRoleId.innerHTML = `<option value="">${t('form.fields.role_id.loading', 'Loading...')}</option>`;
            el.formRoleId.disabled = true;

            const tenant = await getTenant(id);
            if (tenant) {
                el.tenantName.textContent = tenant.name || t('validation.required', 'Unknown');
                el.tenantDomain.textContent = tenant.domain ? `${t('form.tenant_info.domain')} ${tenant.domain}` : '';
                el.tenantStatus.textContent = tenant.status || '';
                el.tenantStatus.className = `badge ${tenant.status === 'active' ? 'badge-success' : 'badge-warning'}`;
                el.tenantInfo.style.display = 'block';

                const roles = await getRoles(id);
                if (roles && roles.length > 0) {
                    const options = [`<option value="">${t('form.fields.role_id.placeholder')}</option>`];
                    roles.forEach(role => {
                        const displayName = role.display_name || role.key_name || role.name || `Role ${role.id}`;
                        options.push(`<option value="${role.id}">${escapeHtml(displayName)}</option>`);
                    });
                    el.formRoleId.innerHTML = options.join('');
                    el.formRoleId.disabled = false;
                    console.log('[TenantUsers] Roles dropdown populated with', roles.length, 'roles');
                } else {
                    el.formRoleId.innerHTML = `<option value="">${t('form.fields.role_id.no_roles', 'No roles available')}</option>`;
                    el.formRoleId.disabled = true;
                    AF.warning(t('form.fields.role_id.no_roles', 'No roles available'));
                }
            } else {
                el.tenantInfo.style.display = 'none';
                el.formRoleId.disabled = true;
                el.formRoleId.innerHTML = `<option value="">${t('form.fields.tenant_id.not_found', 'Tenant not found')}</option>`;
                AF.warning(t('form.fields.tenant_id.not_found', 'Tenant not found'));
            }
        } catch (error) {
            console.error('[TenantUsers] Verify tenant error:', error);
            el.tenantInfo.style.display = 'none';
            el.formRoleId.disabled = true;
            el.formRoleId.innerHTML = `<option value="">${t('messages.error.load_failed', 'Error loading tenant')}</option>`;
            AF.error(t('messages.error.load_failed', 'Error loading tenant'));
        }
    }, 300);

    const verifyUser = AF.debounce(async () => {
        const id = el.formUserId.value.trim();
        if (!id || isNaN(id)) {
            el.userInfo.style.display = 'none';
            return;
        }
        const user = await getUser(id);
        if (user) {
            el.userName.textContent = user.username || t('validation.required', 'Unknown');
            el.userEmail.textContent = user.email || '';
            el.userStatus.textContent = user.is_active ? t('form.fields.status.active') : t('form.fields.status.inactive');
            el.userStatus.className = `badge ${user.is_active ? 'badge-success' : 'badge-danger'}`;
            el.userInfo.style.display = 'block';
        } else {
            el.userInfo.style.display = 'none';
            AF.warning(t('form.fields.user_id.not_found', 'User not found'));
        }
    }, 300);

    async function save(e) {
  e.preventDefault();
  if (!AF.Form.validate('tenantUserForm')) return;

  const formData = AF.Form.getData('tenantUserForm');
  const id = el.formId.value.trim();
  const isEdit = !!id;

  const data = {
    tenant_id: parseInt(formData.tenant_id),
    user_id: parseInt(formData.user_id),
    role_id: formData.role_id === '' ? null : parseInt(formData.role_id),
    is_active: formData.is_active === '1' ? 1 : 0
  };
  if (isEdit) data.id = parseInt(id);

  try {
    AF.Loading.show(el.btnSubmit, isEdit ? t('form.buttons.updating') : t('form.buttons.saving'));

    // perform request
    let response;
    if (isEdit) {
      // AF.api used earlier for PUT — adjust per your framework
      response = await AF.api(`${API}/${data.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
    } else {
      response = await AF.post(API, data);
    }

    console.log('[TenantUsers] Save response:', response);

    // Normalize response (support wrapper {success,data} or direct payload)
    const { payload } = normalizeApiResponse(response);

    // Determine success: success flag or returned id/items
    const serverOk = (response && typeof response === 'object' && response.success === true)
      || (payload && (payload.id || (Array.isArray(payload) && payload.length > 0) || payload.items));

    if (serverOk) {
      AF.success(isEdit ? t('messages.success.updated') : t('messages.success.created'));
      AF.Form.hide('tenantUserFormContainer');
      await load(state.page);
      return;
    }

    // If response didn't indicate success but contains id -> accept it
    if (payload && payload.id) {
      AF.success(isEdit ? t('messages.success.updated') : t('messages.success.created'));
      AF.Form.hide('tenantUserFormContainer');
      await load(state.page);
      return;
    }

    // Otherwise treat as failure (server returned success:false or unknown shape)
    const message = (response && response.message) ? response.message : t('messages.error.save_failed');
    AF.error(message);

  } catch (err) {
    console.warn('[TenantUsers] Save caught error:', err);

    // Attempt safe verification: maybe the DB insert succeeded but server errored afterwards.
    // Verify by querying the list for the same tenant_id + user_id.
    try {
      const verifyParams = new URLSearchParams({
        tenant_id: String(data.tenant_id || ''),
        user_id: String(data.user_id || ''),
        page: state.page,
        per_page: state.perPage
      });
      const verifyRes = await AF.get(`${API}?${verifyParams}`);
      console.log('[TenantUsers] Verify response after save error:', verifyRes);
      const { payload: verifyPayload } = normalizeApiResponse(verifyRes);

      // find matching record
      let found = null;
      if (Array.isArray(verifyPayload)) {
        found = verifyPayload.find(i => Number(i.user_id) === Number(data.user_id) && Number(i.tenant_id) === Number(data.tenant_id));
      } else if (verifyPayload && Array.isArray(verifyPayload.items)) {
        found = verifyPayload.items.find(i => Number(i.user_id) === Number(data.user_id) && Number(i.tenant_id) === Number(data.tenant_id));
      }

      if (found) {
        // Consider it success (record exists)
        console.info('[TenantUsers] Save likely succeeded despite error — record found:', found);
        AF.success(isEdit ? t('messages.success.updated') : t('messages.success.created'));
        AF.Form.hide('tenantUserFormContainer');
        await load(state.page);
        return;
      }
    } catch (verifyErr) {
      console.warn('[TenantUsers] Verification request failed:', verifyErr);
      // fall through to show original error
    }

    // If we reach here, verification didn't confirm success — show friendly error.
    const msg = (err && err.message) ? err.message : t('messages.error.save_failed');
    AF.error(msg);
  } finally {
    AF.Loading.hide(el.btnSubmit);
  }
}

    async function edit(id) {
        console.log('[TenantUsers] Starting edit for ID:', id);
        try {
            const response = await AF.get(`${API}/${id}`);
            const { payload } = normalizeApiResponse(response);

            let item = null;
            if (Array.isArray(payload)) item = payload.find(i => i.id == id) || payload[0] || null;
            else if (payload && Array.isArray(payload.items)) item = payload.items.find(i => i.id == id) || payload.items[0] || null;
            else if (payload && (payload.id || payload.user_id)) item = payload;
            else if (payload && payload.data && Array.isArray(payload.data)) item = payload.data.find(i => i.id == id) || null;

            if (!item) throw new Error(t('messages.error.not_found', 'Item not found'));

            el.form.reset();
            el.form.classList.remove('was-validated');
            el.tenantInfo.style.display = 'none';
            el.userInfo.style.display = 'none';
            AF.Form.show('tenantUserFormContainer', t('form.edit_title'));
            el.formId.value = String(item.id || '');
            el.formTenantId.value = String(item.tenant_id || '');
            el.formUserId.value = String(item.user_id || '');
            el.formIsActive.value = item.is_active ? '1' : '0';

            if (item.tenant_id) {
                const tenant = await getTenant(item.tenant_id);
                if (tenant) {
                    el.tenantName.textContent = tenant.name || t('validation.required', 'Unknown');
                    el.tenantDomain.textContent = tenant.domain ? `${t('form.tenant_info.domain')} ${tenant.domain}` : '';
                    el.tenantStatus.textContent = tenant.status || '';
                    el.tenantStatus.className = `badge ${tenant.status === 'active' ? 'badge-success' : 'badge-warning'}`;
                    el.tenantInfo.style.display = 'block';
                    const roles = await getRoles(item.tenant_id);
                    const roleOptions = [`<option value="">${t('form.fields.role_id.placeholder')}</option>`];
                    roles.forEach(role => {
                        const displayName = role.display_name || role.key_name || role.name || `Role ${role.id}`;
                        const selected = role.id == item.role_id ? ' selected' : '';
                        roleOptions.push(`<option value="${role.id}"${selected}>${escapeHtml(displayName)}</option>`);
                    });
                    el.formRoleId.innerHTML = roleOptions.join('');
                    el.formRoleId.disabled = false;
                } else {
                    el.formRoleId.innerHTML = `<option value="">${t('form.fields.tenant_id.not_found')}</option>`;
                    el.formRoleId.disabled = true;
                }
            }

            if (item.user_id) {
                const user = await getUser(item.user_id);
                if (user) {
                    el.userName.textContent = user.username || t('validation.required', 'Unknown');
                    el.userEmail.textContent = user.email || '';
                    el.userStatus.textContent = user.is_active ? t('form.fields.status.active') : t('form.fields.status.inactive');
                    el.userStatus.className = `badge ${user.is_active ? 'badge-success' : 'badge-danger'}`;
                    el.userInfo.style.display = 'block';
                }
            }

            setTimeout(() => {
                const container = AF.$('tenantUserFormContainer');
                if (container) container.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 200);

        } catch (err) {
            console.error('[TenantUsers] Edit error:', err);
            AF.error(t('messages.error.load_failed'));
        }
    }

    async function remove(id) {
        AF.Modal.confirm(t('table.actions.confirm_delete'), async () => {
            try {
                // AF.delete should send JSON body or query param depending on implementation
                await AF.delete(`${API}/${id}`, { id: id });
                AF.success(t('messages.success.deleted'));
                load();
            } catch (err) {
                console.error('[TenantUsers] Delete error:', err);
                AF.error(t('messages.error.delete_failed'));
            }
        });
    }

    function add() {
        console.log('[TenantUsers] Opening new form');
        el.form.reset();
        el.form.classList.remove('was-validated');
        el.formId.value = '';
        el.tenantInfo.style.display = 'none';
        el.userInfo.style.display = 'none';
        el.formRoleId.innerHTML = `<option value="">${t('form.fields.role_id.enter_tenant_first')}</option>`;
        el.formRoleId.disabled = true;
        AF.Form.show('tenantUserFormContainer', t('form.add_title'));
    }

    // ----------------------------
    // DATA LOADING
    // ----------------------------
    async function load(page = 1) {
        try {
            console.log('[TenantUsers] Loading page:', page);
            if (el.loading) {
                el.loading.innerHTML = `<div class="spinner"></div><p>${t('tenant_users.loading')}</p>`;
                el.loading.style.display = 'block';
            }
            if (el.container) el.container.style.display = 'none';
            if (el.empty) el.empty.style.display = 'none';
            if (el.error) el.error.style.display = 'none';
            state.page = page;
            const params = new URLSearchParams({ page: page, per_page: state.perPage, ...state.filters });
            const response = await AF.get(`${API}?${params}`);

            const { payload, meta } = normalizeApiResponse(response);

            // Determine items array in flexible ways
            let items = [];
            if (Array.isArray(payload)) items = payload;
            else if (payload && Array.isArray(payload.items)) items = payload.items;
            else if (payload && Array.isArray(payload.data)) items = payload.data;
            else if (payload && typeof payload === 'object' && Object.keys(payload).length > 0) items = [payload];
            else items = [];

            const finalMeta = meta || { total: Array.isArray(items) ? items.length : 0, page: page, per_page: state.perPage, pages: 1 };

            console.log('[TenantUsers] Loaded', items.length, 'items', 'meta=', finalMeta);

            // Update pagination UI if helper exists
            if (el.pagination && typeof AF.Table !== 'undefined' && typeof AF.Table.renderPagination === 'function') {
                AF.Table.renderPagination(el.pagination, el.paginationInfo, finalMeta);
            } else if (el.paginationInfo) {
                el.paginationInfo.textContent = `${finalMeta.page || page} / ${finalMeta.pages || 1} — ${finalMeta.total || 0}`;
            }

            renderTable(items || []);
        } catch (err) {
            console.error('[TenantUsers] Load error:', err);
            if (el.loading) el.loading.style.display = 'none';
            if (el.container) el.container.style.display = 'none';
            if (el.empty) el.empty.style.display = 'none';
            if (el.error) {
                el.error.innerHTML = `<div class="error-icon">⚠️</div><h3>${t('messages.error.load_failed')}</h3><p id="errorMessage">${err.message}</p><button id="btnRetry" class="btn btn-secondary">${t('tenant_users.retry')}</button>`;
                el.error.style.display = 'block';
            }
            if (el.tbody) el.tbody.innerHTML = '';
        }
    }

    function applyFilters() {
        state.filters = {};
        if (el.searchInput) {
            const s = el.searchInput.value.trim(); if (s) state.filters.search = s;
        }
        if (el.tenantFilter) {
            const t = el.tenantFilter.value.trim(); if (t) state.filters.tenant_id = t;
        }
        if (el.userFilter) {
            const u = el.userFilter.value.trim(); if (u) state.filters.user_id = u;
        }
        if (el.statusFilter) {
            const st = el.statusFilter.value; if (st !== '') state.filters.is_active = st;
        }
        load(1);
    }

    function resetFilters() {
        if (el.searchInput) el.searchInput.value = '';
        if (el.tenantFilter) el.tenantFilter.value = '';
        if (el.userFilter) el.userFilter.value = '';
        if (el.statusFilter) el.statusFilter.value = '';
        state.filters = {};
        load(1);
    }

    // ----------------------------
    // UTILITIES
    // ----------------------------
    function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }

    // ----------------------------
    // INITIALIZATION
    // ----------------------------
    async function init() {
        console.log('[TenantUsers] Initializing...');
        const translationsLoaded = await loadTranslations();
        if (translationsLoaded) console.log('[TenantUsers] Translations ready'); else console.warn('[TenantUsers] Using default texts');
        // apply direction based on language
        setDirectionForLang(state.language || window.USER_LANGUAGE || 'en');
        // Get elements
        el = {
            loading: AF.$('tableLoading'),
            container: AF.$('tableContainer'),
            empty: AF.$('emptyState'),
            error: AF.$('errorState'),
            errorMessage: AF.$('errorMessage'),
            tbody: AF.$('tableBody'),
            pagination: AF.$('pagination'),
            paginationInfo: AF.$('paginationInfo'),
            form: AF.$('tenantUserForm'),
            formId: AF.$('formId'),
            formTenantId: AF.$('formTenantId'),
            formUserId: AF.$('formUserId'),
            formRoleId: AF.$('formRoleId'),
            formIsActive: AF.$('formIsActive'),
            tenantInfo: AF.$('tenantInfo'),
            tenantName: AF.$('tenantName'),
            tenantDomain: AF.$('tenantDomain'),
            tenantStatus: AF.$('tenantStatus'),
            userInfo: AF.$('userInfo'),
            userName: AF.$('userName'),
            userEmail: AF.$('userEmail'),
            userStatus: AF.$('userStatus'),
            searchInput: AF.$('searchInput'),
            tenantFilter: AF.$('tenantFilter'),
            userFilter: AF.$('userFilter'),
            statusFilter: AF.$('statusFilter'),
            btnSubmit: AF.$('btnSubmitForm'),
            btnAdd: AF.$('btnAddTenantUser'),
            btnClose: AF.$('btnCloseForm'),
            btnCancel: AF.$('btnCancelForm'),
            btnApply: AF.$('btnApplyFilters'),
            btnReset: AF.$('btnResetFilters'),
            btnRetry: AF.$('btnRetry')
        };

        // Load permissions
        try {
            const permsScript = AF.$('pagePermissions');
            if (permsScript) state.permissions = JSON.parse(permsScript.textContent);
        } catch (e) { state.permissions = { canCreate: false, canEdit: false, canDelete: false }; }

        // Event listeners
        if (el.form) el.form.onsubmit = save;
        if (el.formTenantId) el.formTenantId.oninput = verifyTenant;
        if (el.formUserId) el.formUserId.oninput = verifyUser;
        if (el.btnAdd) el.btnAdd.onclick = add;
        if (el.btnClose) el.btnClose.onclick = () => AF.Form.hide('tenantUserFormContainer');
        if (el.btnCancel) el.btnCancel.onclick = () => AF.Form.hide('tenantUserFormContainer');
        if (el.btnApply) el.btnApply.onclick = applyFilters;
        if (el.btnReset) el.btnReset.onclick = resetFilters;
        if (el.btnRetry) el.btnRetry.onclick = () => load(state.page);

        // Load initial data
        load();
        console.log('[TenantUsers] Initialized successfully!');
    }

    // ----------------------------
    // PUBLIC API
    // ----------------------------
    window.TenantUsers = {
        init,
        load,
        edit,
        remove,
        add,
        verifyTenant,
        verifyUser,
        setLanguage: async (lang) => {
            await loadTranslations(lang);
            setDirectionForLang(lang);
            load(state.page);
        }
    };

    // fragment support
    window.page = { run: init };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            if (window.AdminFramework && !window.page.__fragment_init) init();
        });
    } else {
        if (window.AdminFramework && !window.page.__fragment_init) init();
    }
    window.page.__fragment_init = false;

})();