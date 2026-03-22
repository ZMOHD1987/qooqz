(function() {
    'use strict';

    // Prevent double init
    if (window.UsersPageInitialized) {
        console.warn('[Users] Already initialized, skipping...');
        return;
    }

    // ════════════════════════════════════════════════════════════
    // GLOBALS
    // ════════════════════════════════════════════════════════════

    let currentPage = 1;
    let filters = {};
    let permissions = {};
    let languages = [];
    let userLanguage = window.USER_LANGUAGE || window.ADMIN_LANG || 'en';

    // ── Translation ──
    var CFG = window.USERS_CONFIG || {};
    var S = CFG.strings || {};
    function t(key, fb) {
        var parts = key.split('.');
        var v = S;
        for (var i = 0; i < parts.length; i++) {
            if (!v || typeof v !== 'object') return fb || key;
            v = v[parts[i]];
        }
        return (typeof v === 'string') ? v : (fb || key);
    }

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

    /* ── Toast Notifications (usr-toast-*) ── */
    function showNotification(msg, type) {
        var container = document.querySelector('.usr-notifications');
        if (!container) {
            container = document.createElement('div');
            container.className = 'usr-notifications';
            document.body.appendChild(container);
        }
        var n = document.createElement('div');
        n.className = 'usr-toast usr-toast-' + (type || 'info');
        n.textContent = msg;
        container.appendChild(n);
        setTimeout(function(){ n.style.opacity = '0'; setTimeout(function(){ n.remove(); }, 300); }, 3000);
    }

    function formatDate(dateString) {
        if (!dateString) return t('na', 'N/A');
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
            } else {
                permissions = { canCreate: false, canEdit: false, canDelete: false };
            }
        } catch (e) {
            console.error('[Users] Failed to parse permissions:', e);
            permissions = { canCreate: false, canEdit: false, canDelete: false };
        }
    }

    // ════════════════════════════════════════════════════════════
    // API HELPER
    // ════════════════════════════════════════════════════════════

    async function apiFetch(url, options = {}) {
        try {
            const response = await fetch(url, options);
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('[Users] API Error:', error);
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
                languages = Array.isArray(result.data) ? result.data : (result.data.items || []);

                // Populate filter dropdown
                const filterSelect = getEl('languageFilter');
                if (filterSelect) {
                    filterSelect.innerHTML = '<option value="">' + t('filter.all_languages', 'All Languages') + '</option>';
                    languages.forEach(function(lang) {
                        const option = document.createElement('option');
                        option.value = lang.code;
                        option.textContent = lang.name;
                        filterSelect.appendChild(option);
                    });
                }

                // Populate form dropdown
                const formSelect = getEl('preferred_language');
                if (formSelect) {
                    formSelect.innerHTML = '';
                    languages.forEach(function(lang) {
                        const option = document.createElement('option');
                        option.value = lang.code;
                        option.textContent = lang.name;
                        formSelect.appendChild(option);
                    });
                }
            }
        } catch (e) {
            console.error('[Users] loadLanguages:', e);
        }
    }

    // ════════════════════════════════════════════════════════════
    // LOAD USERS
    // ════════════════════════════════════════════════════════════

    async function loadUsers(page) {
        if (page === undefined) page = 1;

        try {
            showLoading();

            var params = new URLSearchParams({ page: page, per_page: 10 });
            var key;
            for (key in filters) {
                if (filters.hasOwnProperty(key)) {
                    params.set(key, filters[key]);
                }
            }

            var result = await apiFetch('/api/users_account?' + params.toString());

            if (result.success && result.data) {
                currentPage = page;
                var items = result.data.items || result.data || [];
                renderTable(items);
                renderPagination(result.data.meta || {});
            } else {
                throw new Error(result.message || t('messages.load_error', 'Failed to load users'));
            }

        } catch (error) {
            console.error('[Users] loadUsers:', error);
            showError(t('messages.load_error', 'Failed to load users') + ': ' + error.message);
        }
    }

    // ════════════════════════════════════════════════════════════
    // RENDER TABLE
    // ════════════════════════════════════════════════════════════

    function renderTable(data) {
        var tbody = getEl('tableBody');
        var tableContainer = getEl('tableContainer');
        var emptyState = getEl('emptyState');
        var loadingState = getEl('tableLoading');
        var errorState = getEl('errorState');

        if (!tbody) return;

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

        data.forEach(function(user) {
            var tr = document.createElement('tr');

            var statusClass = user.is_active ? 'badge-active' : 'badge-inactive';
            var statusText = user.is_active ? t('active', 'Active') : t('inactive', 'Inactive');

            // Find language name
            var langName = user.preferred_language || t('na', 'N/A');
            for (var i = 0; i < languages.length; i++) {
                if (languages[i].code === user.preferred_language) {
                    langName = languages[i].name;
                    break;
                }
            }

            var statusCell = permissions.canEdit
                ? '<td><label class="toggle-switch" title="' + statusText + '">' +
                      '<input type="checkbox" class="usr-status-toggle" data-user-id="' + user.id + '" aria-label="' + t('toggle_status', 'Toggle status') + ' ' + escapeHtml(user.username) + '"' + (user.is_active ? ' checked' : '') + '>' +
                      '<span class="toggle-slider"></span>' +
                  '</label></td>'
                : '<td><span class="badge ' + statusClass + '">' + statusText + '</span></td>';

            tr.innerHTML =
                '<td>' + user.id + '</td>' +
                '<td><strong>' + escapeHtml(user.username) + '</strong></td>' +
                '<td>' + escapeHtml(user.email) + '</td>' +
                '<td>' + escapeHtml(langName) + '</td>' +
                '<td>' + escapeHtml(user.phone || t('na', 'N/A')) + '</td>' +
                '<td>' + formatDate(user.created_at) + '</td>' +
                statusCell +
                '<td class="actions-cell">' +
                    (permissions.canEdit ? '<button onclick="Users.edit(' + user.id + ')" class="btn btn-sm btn-icon btn-primary" title="' + t('edit', 'Edit') + '" aria-label="' + t('edit', 'Edit') + '"><i class="fas fa-edit" aria-hidden="true"></i></button>' : '') +
                    (permissions.canDelete ? '<button onclick="Users.delete(' + user.id + ')" class="btn btn-sm btn-icon btn-danger" title="' + t('delete', 'Delete') + '" aria-label="' + t('delete', 'Delete') + '"><i class="fas fa-trash" aria-hidden="true"></i></button>' : '') +
                '</td>';

            tbody.appendChild(tr);
        });

        // Apply DB-driven hover effects on table action buttons
        if (window.Admin && Admin.buttons && Admin.buttons.applyHoverEffects) {
            Admin.buttons.applyHoverEffects(tbody);
        }

        // Bind toggle switch events
        tbody.querySelectorAll('.usr-status-toggle').forEach(function(toggle) {
            toggle.addEventListener('change', function() {
                var userId = parseInt(this.dataset.userId);
                var newStatus = this.checked ? 1 : 0;
                toggleUserStatus(userId, newStatus, this);
            });
        });
    }

    // ════════════════════════════════════════════════════════════
    // TOGGLE USER STATUS
    // ════════════════════════════════════════════════════════════

    async function toggleUserStatus(id, newStatus, toggleEl) {
        try {
            var csrfToken = window.CSRF_TOKEN || safeGetValue('csrf_token') || '';

            var result = await apiFetch('/api/users_account', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ id: id, is_active: newStatus })
            });

            if (result.success) {
                var msg = newStatus
                    ? t('messages.user_activated', 'User activated!')
                    : t('messages.user_deactivated', 'User deactivated!');
                showNotification(msg, 'success');
                // Update toggle title
                if (toggleEl && toggleEl.parentElement) {
                    toggleEl.parentElement.title = newStatus ? t('active', 'Active') : t('inactive', 'Inactive');
                }
            } else {
                // Revert toggle on failure
                if (toggleEl) toggleEl.checked = !newStatus;
                throw new Error(result.message || t('messages.save_failed', 'Save failed'));
            }
        } catch (error) {
            console.error('[Users] toggleUserStatus:', error);
            // Revert toggle on error
            if (toggleEl) toggleEl.checked = !newStatus;
            showNotification(t('messages.status_error', 'Failed to update status: ') + error.message, 'error');
        }
    }

    // ════════════════════════════════════════════════════════════
    // RENDER PAGINATION
    // ════════════════════════════════════════════════════════════

    function renderPagination(meta) {
        var pagination = getEl('pagination');
        var paginationInfo = getEl('paginationInfo');

        if (!meta || !meta.total) {
            if (pagination) pagination.innerHTML = '';
            if (paginationInfo) paginationInfo.textContent = '0-0 of 0';
            return;
        }

        var start = (meta.page - 1) * meta.per_page + 1;
        var end = Math.min(start + meta.per_page - 1, meta.total);
        if (paginationInfo) paginationInfo.textContent = start + '-' + end + ' of ' + meta.total;

        if (!pagination) return;

        var totalPages = Math.ceil(meta.total / meta.per_page);
        pagination.innerHTML = '';

        var prevBtn = document.createElement('button');
        prevBtn.textContent = '\u2039';
        prevBtn.disabled = meta.page === 1;
        prevBtn.onclick = function() { loadUsers(meta.page - 1); };
        pagination.appendChild(prevBtn);

        for (var i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= meta.page - 2 && i <= meta.page + 2)) {
                var btn = document.createElement('button');
                btn.textContent = i;
                btn.className = i === meta.page ? 'active' : '';
                btn.onclick = (function(pageNum) { return function() { loadUsers(pageNum); }; })(i);
                pagination.appendChild(btn);
            } else if (i === meta.page - 3 || i === meta.page + 3) {
                var dots = document.createElement('span');
                dots.textContent = '...';
                dots.className = 'pagination-ellipsis';
                pagination.appendChild(dots);
            }
        }

        var nextBtn = document.createElement('button');
        nextBtn.textContent = '\u203A';
        nextBtn.disabled = meta.page === totalPages;
        nextBtn.onclick = function() { loadUsers(meta.page + 1); };
        pagination.appendChild(nextBtn);
    }

    // ════════════════════════════════════════════════════════════
    // MODAL FUNCTIONS
    // ════════════════════════════════════════════════════════════

    function openModal() { safeShow('userModal'); }
    function closeModal() {
        safeHide('userModal');
        var form = getEl('userForm');
        if (form) form.reset();
    }

    function openAddForm() {
        safeSetValue('formAction', 'add');
        safeSetValue('editingId', '');
        safeSetText('modalTitle', t('modal.add_title', 'Add User'));

        var form = getEl('userForm');
        if (form) form.reset();

        var password = getEl('password');
        if (password) password.required = true;

        safeSetText('passwordLabel', t('form.required', '*'));
        safeSetValue('is_active', true);
        safeHide('btnDeleteUser');

        openModal();
    }

    async function editUser(id) {
        try {
            var result = await apiFetch('/api/users_account?id=' + id);

            if (result.success && result.data) {
                var user = result.data;

                safeSetValue('formAction', 'edit');
                safeSetValue('editingId', user.id);
                safeSetText('modalTitle', t('modal.edit_title', 'Edit User'));
                safeSetValue('username', user.username);
                safeSetValue('email', user.email);
                safeSetValue('password', '');

                var password = getEl('password');
                if (password) password.required = false;

                safeSetText('passwordLabel', t('form.optional', '(optional)'));
                safeSetValue('preferred_language', user.preferred_language || 'en');
                safeSetValue('phone', user.phone);
                safeSetValue('is_active', user.is_active == 1);

                if (permissions.canDelete) safeShow('btnDeleteUser');

                openModal();
            }
        } catch (error) {
            console.error('[Users] editUser:', error);
            showNotification(t('messages.load_user_error', 'Failed to load user: ') + error.message, 'error');
        }
    }

    async function submitForm(e) {
        e.preventDefault();

        var data = {
            username: safeGetValue('username'),
            email: safeGetValue('email'),
            preferred_language: safeGetValue('preferred_language'),
            phone: safeGetValue('phone'),
            is_active: safeGetValue('is_active') ? 1 : 0
        };

        var password = safeGetValue('password');
        if (password) data.password = password;

        var action = safeGetValue('formAction');
        var method = action === 'edit' ? 'PUT' : 'POST';

        if (action === 'edit') data.id = parseInt(safeGetValue('editingId'));

        try {
            var csrfToken = window.CSRF_TOKEN || safeGetValue('csrf_token') || '';

            var result = await apiFetch('/api/users_account', {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(data)
            });

            if (result.success) {
                closeModal();
                loadUsers(currentPage);
                showNotification(action === 'edit' ? t('messages.user_updated', 'User updated!') : t('messages.user_added', 'User added!'), 'success');
            } else {
                throw new Error(result.message || t('messages.save_failed', 'Save failed'));
            }
        } catch (error) {
            console.error('[Users] submitForm:', error);
            showNotification(t('messages.save_error', 'Failed to save: ') + error.message, 'error');
        }
    }

    async function deleteUser(id) {
        if (!confirm(t('messages.confirm_delete', 'Delete this user?'))) return;

        try {
            var result = await apiFetch('/api/users_account', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN || ''
                },
                body: JSON.stringify({ id: id })
            });

            if (result.success) {
                closeModal();
                loadUsers(currentPage);
                showNotification(t('messages.user_deleted', 'User deleted!'), 'success');
            } else {
                throw new Error(result.message || t('messages.delete_failed', 'Delete failed'));
            }
        } catch (error) {
            console.error('[Users] deleteUser:', error);
            showNotification(t('messages.delete_error', 'Failed to delete: ') + error.message, 'error');
        }
    }

    // ════════════════════════════════════════════════════════════
    // FILTER FUNCTIONS
    // ════════════════════════════════════════════════════════════

    function applyFilters() {
        filters = {};

        var search = safeGetValue('searchInput');
        if (search && search.trim()) filters.search = search.trim();

        var language = safeGetValue('languageFilter');
        if (language) filters.preferred_language = language;

        var status = safeGetValue('statusFilter');
        if (status !== '' && status !== null) filters.is_active = status;

        loadUsers(1);
    }

    function resetFilters() {
        var ids = ['searchInput', 'languageFilter', 'statusFilter'];
        ids.forEach(function(id) {
            var el = getEl(id);
            if (el) {
                if (el.tagName === 'SELECT') el.selectedIndex = 0;
                else el.value = '';
            }
        });

        filters = {};
        loadUsers(1);
    }

    // ════════════════════════════════════════════════════════════
    // STATE HELPERS
    // ════════════════════════════════════════════════════════════

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
        var events = {
            'btnAddUser': openAddForm,
            'btnCloseModal': closeModal,
            'btnCancelForm': closeModal,
            'btnApplyFilters': applyFilters,
            'btnResetFilters': resetFilters,
            'btnRetry': function() { loadUsers(currentPage); }
        };

        var id, el;
        for (id in events) {
            if (events.hasOwnProperty(id)) {
                el = getEl(id);
                if (el && !el._bound) {
                    el.addEventListener('click', events[id]);
                    el._bound = true;
                }
            }
        }

        var btnDelete = getEl('btnDeleteUser');
        if (btnDelete && !btnDelete._bound) {
            btnDelete.addEventListener('click', function() {
                var editId = safeGetValue('editingId');
                if (editId) deleteUser(parseInt(editId));
            });
            btnDelete._bound = true;
        }

        var form = getEl('userForm');
        if (form && !form._bound) {
            form.addEventListener('submit', submitForm);
            form._bound = true;
        }

        // Close modal on backdrop click
        var backdrop = getEl('userModal');
        if (backdrop && !backdrop._bound) {
            backdrop.addEventListener('click', function(e) {
                if (e.target === backdrop) closeModal();
            });
            backdrop._bound = true;
        }

        var searchInput = getEl('searchInput');
        if (searchInput && !searchInput._bound) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyFilters();
                }
            });
            searchInput._bound = true;
        }
    }

    // ════════════════════════════════════════════════════════════
    // INITIALIZE
    // ════════════════════════════════════════════════════════════

    async function init() {
        if (!getEl('tableBody')) {
            console.error('[Users] tableBody not found!');
            return Promise.resolve(false);
        }

        loadPermissions();
        bindEvents();

        await loadLanguages();
        await loadUsers();

        window.UsersPageInitialized = true;

        // Apply DB-driven hover effects on static page buttons
        if (window.Admin && Admin.buttons && Admin.buttons.applyHoverEffects) {
            Admin.buttons.applyHoverEffects(document.querySelector('.page-container'));
        }

        console.log('[Users] Initialized');
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

    // ════════════════════════════════════════════════════════════
    // AUTO-INIT
    // ════════════════════════════════════════════════════════════

    function tryInit(attempt) {
        if (attempt === undefined) attempt = 1;
        if (getEl('tableBody')) {
            init();
        } else if (attempt < 20) {
            setTimeout(function() { tryInit(attempt + 1); }, 200);
        } else {
            console.error('[Users] tableBody not found after 20 attempts');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { setTimeout(tryInit, 100); });
    } else {
        setTimeout(tryInit, 100);
    }

})();
