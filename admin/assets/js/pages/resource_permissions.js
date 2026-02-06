/**
 * Resource Permissions admin page script
 * Requires AdminFramework (AF) utilities: AF.get, AF.post, AF.api, AF.delete, AF.$, AF.Modal, AF.Form, AF.success, AF.error
 */
(function() {
  'use strict';

  const API = '/api/resource_permissions';
  const AFW = window.AdminFramework;
  const state = {
    tenantId: window.ADMIN_TENANT_ID || null,
    roleId: null,
    filters: {},
    permissionsList: [],
    rolesList: [],
    tenantsList: []
  };

  const el = {};

  // small helper to normalize API response (accept wrapper or direct)
  function normalizeApiResponse(resp) {
    if (!resp) return { payload: null, meta: null };
    if (typeof resp === 'object' && resp.data !== undefined) {
      return { payload: resp.data, meta: resp.meta || null, success: resp.success === true };
    }
    return { payload: resp, meta: resp && resp.meta ? resp.meta : null, success: false };
  }

  // DOM helpers
  function $id(id) { return AFW.$(id); }

  // Fetch basic lists: permissions, roles, tenants
  async function loadLookups() {
    try {
      // permissions
      const resPerm = await AFW.get('/api/permissions');
      let perms = normalizeApiResponse(resPerm).payload || [];
      if (Array.isArray(perms.items)) perms = perms.items;
      state.permissionsList = perms;

      // roles
      const resRoles = await AFW.get(`/api/roles?tenant_id=${state.tenantId || ''}`);
      let roles = normalizeApiResponse(resRoles).payload || [];
      if (Array.isArray(roles.items)) roles = roles.items;
      state.rolesList = roles;

      // tenants
      const resTenants = await AFW.get('/api/tenants');
      let tenants = normalizeApiResponse(resTenants).payload || [];
      if (Array.isArray(tenants.items)) tenants = tenants.items || tenants;
      state.tenantsList = tenants;

      populateLookupControls();
    } catch (err) {
      console.error('[RP] loadLookups error', err);
    }
  }

  function populateLookupControls() {
    // permissions select
    const permSel = $id('rpPermissionFilter');
    const permFormSel = $id('rpFormPermissionId');
    if (permSel) {
      permSel.innerHTML = `<option value="">All Permissions</option>${state.permissionsList.map(p => `<option value="${p.id}">${escapeHtml(p.display_name || p.key_name)}</option>`).join('')}`;
    }
    if (permFormSel) {
      permFormSel.innerHTML = `<option value="">Select permission</option>${state.permissionsList.map(p => `<option value="${p.id}">${escapeHtml(p.display_name || p.key_name)}</option>`).join('')}`;
    }

    // roles
    const roleFilter = $id('rpRoleFilter');
    const roleForm = $id('rpFormRoleId');
    const optionsRoles = `<option value="">All Roles</option>` + state.rolesList.map(r => `<option value="${r.id}">${escapeHtml(r.display_name || r.key_name)}</option>`).join('');
    if (roleFilter) roleFilter.innerHTML = optionsRoles;
    if (roleForm) roleForm.innerHTML = `<option value="">Select role</option>` + state.rolesList.map(r => `<option value="${r.id}">${escapeHtml(r.display_name || r.key_name)}</option>`).join('');

    // tenants
    const tenantFilter = $id('rpTenantFilter');
    const tenantForm = $id('rpFormTenantId');
    const optsTenants = `<option value="">All Tenants</option>` + state.tenantsList.map(t => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');
    if (tenantFilter) tenantFilter.innerHTML = optsTenants;
    if (tenantForm) tenantForm.innerHTML = `<option value="">Global (all)</option>` + state.tenantsList.map(t => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');
  }

  // Load list
  async function load() {
    try {
      if (el.loading) el.loading.style.display = 'block';
      if (el.error) el.error.style.display = 'none';
      const params = new URLSearchParams();
      if (state.filters.tenant_id) params.append('tenant_id', state.filters.tenant_id);
      if (state.filters.role_id) params.append('role_id', state.filters.role_id);
      if (state.filters.permission_id) params.append('permission_id', state.filters.permission_id);
      if (state.filters.search) params.append('search', state.filters.search);

      const url = `${API}${params.toString() ? '?' + params.toString() : ''}`;
      const resp = await AFW.get(url);
      const { payload } = normalizeApiResponse(resp);
      let items = payload || [];
      if (Array.isArray(items.items)) items = items.items;
      renderTable(items);
    } catch (err) {
      console.error('[RP] load error', err);
      if (el.error) {
        el.error.style.display = 'block';
        el.error.textContent = 'Failed to load resource permissions';
      }
    } finally {
      if (el.loading) el.loading.style.display = 'none';
    }
  }

  function renderTable(items) {
    const tbody = $id('rpTableBody');
    if (!tbody) return;
    if (!items || !items.length) {
      tbody.innerHTML = `<tr><td colspan="7" class="text-center">No resource permissions found</td></tr>`;
      return;
    }

    const rows = items.map(row => {
      const flags = [];
      if (row.can_view_all) flags.push('view-all');
      if (row.can_view_own) flags.push('view-own');
      if (row.can_view_tenant) flags.push('view-tenant');
      if (row.can_create) flags.push('create');
      if (row.can_edit_all) flags.push('edit-all');
      if (row.can_edit_own) flags.push('edit-own');
      if (row.can_delete_all) flags.push('delete-all');
      if (row.can_delete_own) flags.push('delete-own');

      const tenantName = row.tenant_id ? (row.tenant_name || row.tenant_id) : 'Global';
      const permissionTitle = row.display_name || row.key_name || (row.permission_id ? `#${row.permission_id}` : '');

      return `<tr data-id="${row.id}">
        <td>${row.id}</td>
        <td>${escapeHtml(row.resource_type)}</td>
        <td>${escapeHtml(permissionTitle)}</td>
        <td>${escapeHtml(row.role_id ? (row.role_name || row.role_id) : '—')}</td>
        <td>${escapeHtml(tenantName)}</td>
        <td>${flags.map(f => `<span class="flag">${f}</span>`).join(' ')}</td>
        <td>
          <button class="btn btn-sm btn-outline" data-action="edit" data-id="${row.id}">Edit</button>
          <button class="btn btn-sm btn-danger" data-action="delete" data-id="${row.id}">Delete</button>
        </td>
      </tr>`;
    }).join('');

    tbody.innerHTML = rows;

    // attach row actions
    tbody.querySelectorAll('button[data-action]').forEach(btn => {
      btn.addEventListener('click', onRowAction);
    });
  }

  // Show form for add/edit
  function showForm(title = 'Add Resource Permission') {
    $id('rpFormTitle').textContent = title;
    AFW.Form.show('rpFormContainer', title);
  }

  function hideForm() {
    AFW.Form.hide('rpFormContainer');
  }

  async function onRowAction(e) {
    const btn = e.currentTarget;
    const action = btn.getAttribute('data-action');
    const id = btn.getAttribute('data-id');
    if (action === 'edit') {
      await openEdit(Number(id));
    } else if (action === 'delete') {
      AFW.Modal.confirm('Delete permission?', async () => {
        try {
          await AFW.delete(`${API}`, { id: Number(id) });
          AFW.success('Deleted');
          await load();
        } catch (err) {
          console.error('[RP] delete error', err);
          AFW.error('Delete failed');
        }
      });
    }
  }

  // Open add form
  function openAdd() {
    // reset form
    const form = $id('rpForm');
    form.reset();
    $id('rpFormId').value = '';
    showForm('Add Resource Permission');
  }

  // Open edit form
  async function openEdit(id) {
    try {
      const resp = await AFW.get(`${API}?id=${id}`);
      const { payload } = normalizeApiResponse(resp);
      let row = null;
      if (Array.isArray(payload)) row = payload.find(r => Number(r.id) === Number(id)) || payload[0] || null;
      else if (payload && payload.id) row = payload;
      else if (payload && Array.isArray(payload.items)) row = payload.items.find(r => Number(r.id) === Number(id)) || null;

      if (!row) {
        AFW.error('Permission not found');
        return;
      }

      // fill form fields
      $id('rpFormId').value = row.id || '';
      $id('rpFormPermissionId').value = row.permission_id || '';
      $id('rpFormResourceType').value = row.resource_type || '';
      $id('rpFormRoleId').value = row.role_id || '';
      $id('rpFormTenantId').value = row.tenant_id || '';

      ['can_view_all','can_view_own','can_view_tenant','can_create','can_edit_all','can_edit_own','can_delete_all','can_delete_own'].forEach(f => {
        const elc = $id('rp_' + f);
        if (elc) elc.checked = !!row[f];
      });

      showForm('Edit Resource Permission');
    } catch (err) {
      console.error('[RP] openEdit error', err);
      AFW.error('Failed to load permission for edit');
    }
  }

  // Save form (create or update)
  async function saveForm(e) {
    e.preventDefault();
    const id = $id('rpFormId').value || null;
    const payload = {
      permission_id: parseInt($id('rpFormPermissionId').value) || null,
      resource_type: $id('rpFormResourceType').value || '',
      role_id: parseInt($id('rpFormRoleId').value) || null,
      tenant_id: $id('rpFormTenantId').value === '' ? null : parseInt($id('rpFormTenantId').value),
      can_view_all: $id('rp_can_view_all').checked ? 1 : 0,
      can_view_own: $id('rp_can_view_own').checked ? 1 : 0,
      can_view_tenant: $id('rp_can_view_tenant').checked ? 1 : 0,
      can_create: $id('rp_can_create').checked ? 1 : 0,
      can_edit_all: $id('rp_can_edit_all').checked ? 1 : 0,
      can_edit_own: $id('rp_can_edit_own').checked ? 1 : 0,
      can_delete_all: $id('rp_can_delete_all').checked ? 1 : 0,
      can_delete_own: $id('rp_can_delete_own').checked ? 1 : 0
    };

    try {
      AFW.Loading.show($id('rpBtnSave'));
      if (id) {
        payload.id = parseInt(id);
        // prefer PUT for single update
        await AFW.api(`${API}`, { method: 'PUT', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        AFW.success('Updated');
      } else {
        await AFW.post(API, payload);
        AFW.success('Created');
      }
      hideForm();
      await load();
    } catch (err) {
      console.error('[RP] saveForm error', err);
      AFW.error('Save failed');
    } finally {
      AFW.Loading.hide($id('rpBtnSave'));
    }
  }

  function applyFilters() {
    state.filters = {};
    const t = $id('rpTenantFilter').value;
    const r = $id('rpRoleFilter').value;
    const p = $id('rpPermissionFilter').value;
    const s = $id('rpSearch').value.trim();
    if (t) state.filters.tenant_id = t;
    if (r) state.filters.role_id = r;
    if (p) state.filters.permission_id = p;
    if (s) state.filters.search = s;
    load();
  }

  function resetFilters() {
    $id('rpTenantFilter').value = '';
    $id('rpRoleFilter').value = '';
    $id('rpPermissionFilter').value = '';
    $id('rpSearch').value = '';
    state.filters = {};
    load();
  }

  // Utility
  function escapeHtml(text) { if (text === null || text === undefined) return ''; const div = document.createElement('div'); div.textContent = String(text); return div.innerHTML; }

  // Init
  async function init() {
    // bind elements
    el.loading = $id('rpLoading');
    el.error = $id('rpError');

    // buttons
    $id('rpBtnAdd').addEventListener('click', openAdd);
    $id('rpBtnApply').addEventListener('click', applyFilters);
    $id('rpBtnReset').addEventListener('click', resetFilters);

    // form events
    $id('rpForm').addEventListener('submit', saveForm);
    // modal close buttons
    document.querySelectorAll('#rpFormContainer [data-action="close"]').forEach(b => b.addEventListener('click', hideForm));

    await loadLookups();
    await load();
  }

  // Expose
  window.ResourcePermissions = {
    init,
    load,
    openAdd,
    openEdit
  };

  // Auto-start
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();