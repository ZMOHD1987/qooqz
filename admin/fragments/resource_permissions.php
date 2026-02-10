<?php
declare(strict_types=1);
// Fragment: Resource Permissions management page
// Expects AdminFramework (AF) and assets in admin/assets/{js,css}/pages/resource_permissions.{js,css}

?>
<div class="page-header">
  <h1>Resource Permissions</h1>
  <p class="subtitle">Manage resource-level permissions (create, view, edit, delete) per role/tenant.</p>
</div>

<div class="card">
  <div class="card-body toolbar">
    <div class="filters" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
      <select id="rpTenantFilter" class="form-control form-control-sm" style="min-width:160px;">
        <option value="">All Tenants</option>
      </select>

      <select id="rpRoleFilter" class="form-control form-control-sm" style="min-width:160px;">
        <option value="">All Roles</option>
      </select>

      <select id="rpPermissionFilter" class="form-control form-control-sm" style="min-width:200px;">
        <option value="">All Permissions</option>
      </select>

      <input id="rpSearch" class="form-control form-control-sm" placeholder="Search resource or permission..." style="min-width:220px;" />

      <button id="rpBtnApply" class="btn btn-sm btn-primary">Apply</button>
      <button id="rpBtnReset" class="btn btn-sm btn-secondary">Reset</button>

      <div style="flex:1"></div>

      <?php if (!empty($canCreate) ? $canCreate : true): ?>
        <button id="rpBtnAdd" class="btn btn-sm btn-success">Add Permission</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="card-body">
    <div id="rpLoading" class="loading" style="display:none">Loading...</div>

    <div id="rpError" class="alert alert-danger" style="display:none"></div>

    <div id="rpTableContainer" class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th>ID</th>
            <th>Resource</th>
            <th>Permission</th>
            <th>Role</th>
            <th>Tenant</th>
            <th>Flags</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="rpTableBody"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal / Form -->
<div id="rpFormContainer" class="modal" style="display:none;">
  <div class="modal-dialog">
    <form id="rpForm" class="modal-content">
      <div class="modal-header">
        <h5 id="rpFormTitle">Add Resource Permission</h5>
        <button type="button" class="btn-close" data-action="close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rpFormId" name="id" />

        <div class="mb-2">
          <label class="form-label">Permission</label>
          <select id="rpFormPermissionId" name="permission_id" class="form-control"></select>
        </div>

        <div class="mb-2">
          <label class="form-label">Resource Type</label>
          <input id="rpFormResourceType" name="resource_type" class="form-control" placeholder="e.g. invoices, users" />
        </div>

        <div class="mb-2">
          <label class="form-label">Role</label>
          <select id="rpFormRoleId" name="role_id" class="form-control"></select>
        </div>

        <div class="mb-2">
          <label class="form-label">Tenant (optional)</label>
          <select id="rpFormTenantId" name="tenant_id" class="form-control">
            <option value="">Global (all tenants)</option>
          </select>
        </div>

        <div class="flags-grid" style="display:grid; grid-template-columns: repeat(2, 1fr); gap:8px;">
          <label><input type="checkbox" id="rp_can_view_all" name="can_view_all" /> Can view all</label>
          <label><input type="checkbox" id="rp_can_view_own" name="can_view_own" /> Can view own</label>
          <label><input type="checkbox" id="rp_can_view_tenant" name="can_view_tenant" /> Can view tenant</label>
          <label><input type="checkbox" id="rp_can_create" name="can_create" /> Can create</label>
          <label><input type="checkbox" id="rp_can_edit_all" name="can_edit_all" /> Can edit all</label>
          <label><input type="checkbox" id="rp_can_edit_own" name="can_edit_own" /> Can edit own</label>
          <label><input type="checkbox" id="rp_can_delete_all" name="can_delete_all" /> Can delete all</label>
          <label><input type="checkbox" id="rp_can_delete_own" name="can_delete_own" /> Can delete own</label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="rpBtnCancel" class="btn btn-secondary" data-action="close">Cancel</button>
        <button type="submit" id="rpBtnSave" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<link rel="stylesheet" href="/admin/assets/css/pages/resource_permissions.css">
<script src="/admin/assets/js/pages/resource_permissions.js"></script>