(function() {
    'use strict';

    let currentPage = 1;
    let filters = {};
    let permissions = {};
    let roles = [];

    function loadPermissions() {
        const script = document.getElementById('pagePermissions');
        if (script) {
            permissions = JSON.parse(script.textContent);
        }
    }

    async function loadRoles() {
        try {
            const response = await fetch('/api/roles', {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const result = await response.json();
            roles = result.data || [];

            const select = document.getElementById('roleFilter');
            select.innerHTML = '<option value="">All Roles</option>';
            
            roles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.id;
                option.textContent = role.display_name || role.name;
                select.appendChild(option);
            });

            // For form
            const formRoleSelect = document.getElementById('roleList');
            formRoleSelect.innerHTML = '';
            roles.forEach(role => {
                const option = document.createElement('option');
                option.value = role.id;
                option.textContent = role.display_name || role.name;
                formRoleSelect.appendChild(option);
            });

            console.log('✓ Roles loaded:', roles.length);
        } catch (e) {
            console.warn('Failed to load roles:', e);
        }
    }

    async function loadUsers(page = 1) {
        try {
            showLoading();

            const params = new URLSearchParams({
                page: page,
                per_page: 10,
                ...filters
            });

            const response = await fetch('/api/users_account?' + params.toString(), {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const result = await response.json();
            
            console.log('API Response:', result);

            if (result.success && result.data) {
                currentPage = page;
                renderTable(result.data.items || []);
                renderPagination(result.data.meta || {});
            } else {
                throw new Error(result.message || 'Failed to load data');
            }

        } catch (error) {
            console.error('Load error:', error);
            showError('Failed to load users: ' + error.message);
        }
    }

    function renderTable(data) {
        const tbody = document.getElementById('tableBody');
        const tableContainer = document.getElementById('tableContainer');
        const emptyState = document.getElementById('emptyState');
        const loadingState = document.getElementById('tableLoading');
        const errorState = document.getElementById('errorState');

        loadingState.style.display = 'none';
        errorState.style.display = 'none';

        if (!data || !data.length) {
            tableContainer.style.display = 'none';
            emptyState.style.display = 'flex';
            return;
        }

        emptyState.style.display = 'none';
        tableContainer.style.display = 'block';

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
                    ${permissions.canEdit ? `<button onclick="Users.edit(${user.id})" class="btn btn-sm btn-icon" title="Edit"><i class="fas fa-edit"></i></button>` : ''}
                    ${permissions.canDelete ? `<button onclick="Users.delete(${user.id})" class="btn btn-sm btn-icon btn-danger" title="Delete"><i class="fas fa-trash"></i></button>` : ''}
                </td>
            `;
            
            tbody.appendChild(tr);
        });

        console.log('✓ Table rendered with', data.length, 'rows');
    }

    function renderPagination(meta) {
        const pagination = document.getElementById('pagination');
        const paginationInfo = document.getElementById('paginationInfo');

        if (!meta || !meta.total) {
            pagination.innerHTML = '';
            paginationInfo.textContent = '0-0 of 0';
            return;
        }

        const start = (meta.page - 1) * meta.per_page + 1;
        const end = Math.min(start + meta.per_page - 1, meta.total);
        paginationInfo.textContent = `${start}-${end} of ${meta.total}`;

        const totalPages = Math.ceil(meta.total / meta.per_page);
        pagination.innerHTML = '';

        // Previous button
        const prevBtn = document.createElement('button');
        prevBtn.textContent = '‹';
        prevBtn.disabled = meta.page === 1;
        prevBtn.onclick = () => loadUsers(meta.page - 1);
        pagination.appendChild(prevBtn);

        // Page buttons
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

        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.textContent = '›';
        nextBtn.disabled = meta.page === totalPages;
        nextBtn.onclick = () => loadUsers(meta.page + 1);
        pagination.appendChild(nextBtn);
    }

    function openAddForm() {
        window.open('/admin/fragments/users_account_form.php?action=add', '_blank', 'width=800,height=600');
    }

    function editUser(id) {
        window.open('/admin/fragments/users_account_form.php?action=edit&id=' + id, '_blank', 'width=800,height=600');
    }

    async function deleteUser(id) {
        if (!confirm('Are you sure you want to delete this user?')) return;

        try {
            const response = await fetch('/api/users_account', {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': window.CSRF_TOKEN || ''
                },
                body: JSON.stringify({ id: id })
            });

            if (!response.ok) throw new Error('HTTP ' + response.status);

            const result = await response.json();

            if (result.success) {
                loadUsers(currentPage);
                console.log('✓ User deleted:', id);
            } else {
                throw new Error(result.message || 'Delete failed');
            }

        } catch (error) {
            console.error('Delete error:', error);
            alert('Failed to delete: ' + error.message);
        }
    }

    function applyFilters() {
        filters = {};
        
        const search = document.getElementById('searchInput').value.trim();
        if (search) filters.search = search;

        const roleId = document.getElementById('roleFilter').value;
        if (roleId) filters.role_id = roleId;

        const status = document.getElementById('statusFilter').value;
        if (status !== '') filters.is_active = status;

        console.log('Applying filters:', filters);
        loadUsers(1);
    }

    function resetFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('roleFilter').selectedIndex = 0;
        document.getElementById('statusFilter').selectedIndex = 0;
        filters = {};
        loadUsers(1);
    }

    function showLoading() {
        document.getElementById('tableLoading').style.display = 'flex';
        document.getElementById('tableContainer').style.display = 'none';
        document.getElementById('emptyState').style.display = 'none';
        document.getElementById('errorState').style.display = 'none';
    }

    function showError(message) {
        document.getElementById('tableLoading').style.display = 'none';
        document.getElementById('tableContainer').style.display = 'none';
        document.getElementById('emptyState').style.display = 'none';
        document.getElementById('errorState').style.display = 'flex';
        document.getElementById('errorMessage').textContent = message;
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

    function bindEvents() {
        const btnAdd = document.getElementById('btnAddUser');
        if (btnAdd) btnAdd.addEventListener('click', openAddForm);

        const btnApply = document.getElementById('btnApplyFilters');
        if (btnApply) btnApply.addEventListener('click', applyFilters);

        const btnReset = document.getElementById('btnResetFilters');
        if (btnReset) btnReset.addEventListener('click', resetFilters);

        const btnRetry = document.getElementById('btnRetry');
        if (btnRetry) btnRetry.addEventListener('click', () => loadUsers(currentPage));

        // Enter key in search
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') applyFilters();
            });
        }
    }

    function init() {
        loadPermissions();
        bindEvents();
        loadRoles();
        loadUsers();

        console.log('✓ Users page initialized');
    }

    // Expose public API
    window.Users = {
        reload: loadUsers,
        add: openAddForm,
        edit: editUser,
        delete: deleteUser
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();