(function() {
    'use strict';

    let currentPage = 1;
    let filters = {};
    let permissions = {};

    function loadPermissions() {
        const script = document.getElementById('pagePermissions');
        if (script) {
            permissions = JSON.parse(script.textContent);
        }
    }

    async function loadTenantUsers(page = 1) {
        try {
            showLoading();
            const response = await Admin.ajax('/api/tenant_users?page=' + page + '&' + new URLSearchParams(filters));
            renderTable(response);
            renderPagination(response.total || 0, response.per_page || 10);
        } catch (error) {
            showError('Failed to load tenant users');
        }
    }

    function renderTable(data) {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        if (!data || !data.length) {
            showEmpty();
            return;
        }
        data.forEach(user => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${user.id}</td>
                <td>${user.username}</td>
                <td>${user.email}</td>
                <td>${user.role_name}</td>
                <td>${new Date(user.joined_at).toLocaleDateString()}</td>
                <td>${user.is_active ? 'Active' : 'Inactive'}</td>
                <td>
                    ${permissions.canEdit ? `<button onclick="editTenantUser(${user.id})">Edit</button>` : ''}
                    ${permissions.canDelete ? `<button onclick="deleteTenantUser(${user.id})">Delete</button>` : ''}
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderPagination(total, perPage) {
        const pages = Math.ceil(total / perPage);
        const pagination = document.getElementById('pagination');
        pagination.innerHTML = '';
        for (let i = 1; i <= pages; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            btn.onclick = () => loadTenantUsers(i);
            pagination.appendChild(btn);
        }
    }

    function openAddForm() {
        AdminModal.openModalByUrl('/admin/fragments/tenant_users_form.php?action=add');
    }

    function editTenantUser(id) {
        AdminModal.openModalByUrl('/admin/fragments/tenant_users_form.php?action=edit&id=' + id);
    }

    async function deleteTenantUser(id) {
        if (confirm('Are you sure?')) {
            try {
                await Admin.ajax('/api/tenant_users', { method: 'DELETE', body: JSON.stringify({ id, user_id: permissions.userId }) });
                loadTenantUsers(currentPage);
            } catch (error) {
                alert('Delete failed');
            }
        }
    }

    function applyFilters() {
        filters.search = document.getElementById('searchInput').value;
        filters.role_id = document.getElementById('roleFilter').value;
        loadTenantUsers(1);
    }

    function showLoading() {
        document.getElementById('tableBody').innerHTML = '<tr><td colspan="7">Loading...</td></tr>';
    }

    function showError(message) {
        document.getElementById('tableBody').innerHTML = `<tr><td colspan="7">${message}</td></tr>`;
    }

    function showEmpty() {
        document.getElementById('tableBody').innerHTML = '<tr><td colspan="7">No tenant users found</td></tr>';
    }

    function bindEvents() {
        document.getElementById('btnAddTenantUser').addEventListener('click', openAddForm);
        document.getElementById('btnApplyFilters').addEventListener('click', applyFilters);
    }

    function init() {
        loadPermissions();
        bindEvents();
        loadTenantUsers();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();