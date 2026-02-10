<?php
declare(strict_types=1);
// admin/fragments/permissions.php
if (!function_exists('admin_user')) {
    require_once __DIR__ . '/../includes/admin_context.php';
}

$isSuperAdmin = is_super_admin();
$canManage = $isSuperAdmin || can('manage_permissions');

if (!$canManage) {
    echo '<div style="padding:2rem;background:#1e293b;border-radius:8px;margin:2rem;">
        <h3 style="color:#ef4444;"><i class="fas fa-ban"></i> Access Denied</h3>
        <p style="color:#e2e8f0;">You don\'t have permission to manage permissions.</p>
    </div>';
    return;
}

$pdo = admin_db();
$currentTenantId = admin_tenant_id();
$csrf = admin_csrf();
$currentUser = admin_user();

// Get all tenants for super admin (include explicit Global option)
$allTenants = [];
if ($isSuperAdmin && $pdo instanceof PDO) {
    $stmt = $pdo->query("SELECT id, name FROM tenants ORDER BY name");
    $allTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf) ?>">
    <title>Permissions Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/admin/assets/css/permissions-system.css?v=<?= time() ?>">
</head>
<body>

<div class="alerts-container" id="alertsContainer"></div>

<div class="container">
    
    <!-- Header with Tenant Selector -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-shield-halved"></i>
                Permissions Management
            </h1>
            <p class="page-subtitle">Manage Roles, Permissions, and Access Control</p>
        </div>
        <div class="page-actions">
            <?php if ($isSuperAdmin && !empty($allTenants)): ?>
            <select id="tenantSelector" class="form-control" style="width:240px;">
                <option value="0" <?= ($currentTenantId === null || $currentTenantId == 0) ? 'selected' : '' ?>>Global (no tenant)</option>
                <?php foreach ($allTenants as $tenant): ?>
                <option value="<?= (int)$tenant['id'] ?>" <?= $tenant['id'] == $currentTenantId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tenant['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <div style="min-width:200px;padding:6px 10px;background:#0f172a;color:#cbd5e1;border-radius:6px;text-align:center;">
                Tenant: <?= $currentTenantId === null ? '<strong>Global</strong>' : 'ID ' . (int)$currentTenantId ?>
            </div>
            <?php endif; ?>
            <button class="btn btn-secondary btn-sm" onclick="PermissionsApp.refreshAll()">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Main Tabs -->
    <div class="main-tabs">
        <button class="main-tab active" data-tab="roles">
            <i class="fas fa-users-cog"></i> Roles
        </button>
        <button class="main-tab" data-tab="permissions">
            <i class="fas fa-key"></i> Permissions
        </button>
        <button class="main-tab" data-tab="assign">
            <i class="fas fa-link"></i> Assign
        </button>
        <button class="main-tab" data-tab="resources">
            <i class="fas fa-table-cells"></i> Resources
        </button>
    </div>

    <!-- TAB: ROLES -->
    <div class="tab-content active" id="tab-roles">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-users-cog"></i> Roles List
                </h3>
                <div class="actions">
                    <input type="text" id="rolesSearch" class="form-control" placeholder="Search roles..." style="width:250px;">
                    <button class="btn btn-primary" onclick="PermissionsApp.openRoleModal()">
                        <i class="fas fa-plus"></i> Add Role
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="rolesLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="rolesContent" style="display:none;">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Key</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rolesTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="rolesEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-users-cog"></i>
                    <h3>No Roles</h3>
                    <button class="btn btn-primary" onclick="PermissionsApp.openRoleModal()">
                        <i class="fas fa-plus"></i> Add First Role
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: PERMISSIONS -->
    <div class="tab-content" id="tab-permissions">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-key"></i> Permissions List
                </h3>
                <div class="actions">
                    <input type="text" id="permissionsSearch" class="form-control" placeholder="Search permissions..." style="width:250px;">
                    <button class="btn btn-primary" onclick="PermissionsApp.openPermissionModal()">
                        <i class="fas fa-plus"></i> Add Permission
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="permissionsLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="permissionsContent" style="display:none;">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Key</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="permissionsTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="permissionsEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-key"></i>
                    <h3>No Permissions</h3>
                    <button class="btn btn-primary" onclick="PermissionsApp.openPermissionModal()">
                        <i class="fas fa-plus"></i> Add First Permission
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: ASSIGN -->
    <div class="tab-content" id="tab-assign">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-tag"></i> Select Role</h3>
                <input type="text" id="assignRolesSearch" class="form-control" placeholder="Search roles..." style="width:250px;">
            </div>
            <div class="card-body">
                <div class="role-selector" id="assignRoleSelector"></div>
            </div>
        </div>
        <div class="card" id="assignCard" style="display:none;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-link"></i> Permissions for <span id="assignRoleName"></span>
                </h3>
                <div class="actions">
                    <input type="text" id="assignPermSearch" class="form-control" placeholder="Search..." style="width:200px;">
                    <button class="btn btn-primary btn-sm" onclick="PermissionsApp.selectAllAssign()">
                        <i class="fas fa-check-double"></i> Select All
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="PermissionsApp.deselectAllAssign()">
                        <i class="fas fa-times"></i> Clear
                    </button>
                    <button class="btn btn-success" id="btnSaveAssign" onclick="PermissionsApp.saveAssign()">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
            <div class="card-body" id="assignContent"></div>
        </div>
    </div>

    <!-- TAB: RESOURCES -->
    <div class="tab-content" id="tab-resources">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-tag"></i> Select Role</h3>
                <input type="text" id="resourceRolesSearch" class="form-control" placeholder="Search roles..." style="width:250px;">
            </div>
            <div class="card-body">
                <div class="role-selector" id="resourcesRoleSelector"></div>
            </div>
        </div>
        <div class="card" id="resourcesCard" style="display:none;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table-cells"></i> Resources for <span id="resourceRoleName"></span>
                </h3>
                <div class="actions">
                    <input type="text" id="resourcesSearch" class="form-control" placeholder="Search..." style="width:200px;">
                    <button class="btn btn-primary btn-sm" onclick="PermissionsApp.openResourcePermModal()">
                        <i class="fas fa-plus"></i> Add Resource Permission
                    </button>
                    <button class="btn btn-success" id="btnSaveResource" onclick="PermissionsApp.saveResources()">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="resourcesLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="resourcesContent" style="display:none;">
                    <div class="table-responsive">
                        <table class="data-table resource-table">
                            <thead>
                                <tr>
                                    <th class="sticky-col">Permission / Resource</th>
                                    <th>View All</th>
                                    <th>View Own</th>
                                    <th>View Tenant</th>
                                    <th>Create</th>
                                    <th>Edit All</th>
                                    <th>Edit Own</th>
                                    <th>Delete All</th>
                                    <th>Delete Own</th>
                                </tr>
                            </thead>
                            <tbody id="resourcesTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="resourcesEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-table-cells"></i>
                    <h3>No Resource Permissions</h3>
                    <p>This role has no resource-level permissions configured</p>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- MODALS -->
<div class="modal" id="roleModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="roleModalTitle">Add Role</h3>
            <button class="modal-close" onclick="PermissionsApp.closeRoleModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="roleForm" onsubmit="return false;">
                <input type="hidden" id="roleId">
                <div class="form-group">
                    <label class="form-label">Display Name *</label>
                    <input type="text" class="form-control" id="roleDisplayName" required placeholder="e.g., Super Admin">
                </div>
                <div class="form-group">
                    <label class="form-label">Key Name *</label>
                    <input type="text" class="form-control" id="roleKeyName" required pattern="[a-z_]+" placeholder="e.g., super_admin">
                    <small class="form-text">lowercase and underscores only</small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="PermissionsApp.closeRoleModal()">Cancel</button>
            <button class="btn btn-primary" id="btnSaveRole" onclick="PermissionsApp.saveRole()">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<div class="modal" id="permissionModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="permissionModalTitle">Add Permission</h3>
            <button class="modal-close" onclick="PermissionsApp.closePermissionModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="permissionForm" onsubmit="return false;">
                <input type="hidden" id="permissionId">
                <div class="form-group">
                    <label class="form-label">Display Name *</label>
                    <input type="text" class="form-control" id="permissionDisplayName" required placeholder="e.g., Manage Users">
                </div>
                <div class="form-group">
                    <label class="form-label">Key Name *</label>
                    <input type="text" class="form-control" id="permissionKeyName" required pattern="[a-z_]+" placeholder="e.g., manage_users">
                    <small class="form-text">lowercase and underscores only</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" id="permissionDescription" rows="3" placeholder="Describe what this permission allows..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="PermissionsApp.closePermissionModal()">Cancel</button>
            <button class="btn btn-primary" id="btnSavePermission" onclick="PermissionsApp.savePermission()">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<!-- Resource Permission Modal (create/edit single resource_permission) -->
<div class="modal" id="resourcePermModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="resourcePermModalTitle">Add Resource Permission</h3>
            <button class="modal-close" onclick="PermissionsApp.closeResourcePermModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="resourcePermForm" onsubmit="return false;">
                <input type="hidden" id="rpId">
                <div class="form-group">
                    <label>Resource Type *</label>
                    <input id="rpResourceType" class="form-control" required placeholder="e.g., users">
                </div>

                <div class="form-group">
                    <label>Permission *</label>
                    <select id="rpPermissionId" class="form-control" required>
                        <option value="">Loading...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Role (optional)</label>
                    <select id="rpRoleId" class="form-control">
                        <option value="">— Any / Global —</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tenant (optional)</label>
                    <select id="rpTenantId" class="form-control">
                        <option value="0">Global (no tenant)</option>
                        <?php foreach ($allTenants as $tenant): ?>
                            <option value="<?= (int)$tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group flags-grid">
                    <label><input type="checkbox" id="rp_can_view_all"> View All</label>
                    <label><input type="checkbox" id="rp_can_view_own"> View Own</label>
                    <label><input type="checkbox" id="rp_can_view_tenant"> View Tenant</label>
                    <label><input type="checkbox" id="rp_can_create"> Create</label>
                    <label><input type="checkbox" id="rp_can_edit_all"> Edit All</label>
                    <label><input type="checkbox" id="rp_can_edit_own"> Edit Own</label>
                    <label><input type="checkbox" id="rp_can_delete_all"> Delete All</label>
                    <label><input type="checkbox" id="rp_can_delete_own"> Delete Own</label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="PermissionsApp.closeResourcePermModal()">Cancel</button>
            <button class="btn btn-primary" id="btnSaveResourcePerm" onclick="PermissionsApp.saveResourcePerm()">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<script>
window.APP_CONFIG = {
    API_BASE: '/api',
    TENANT_ID: <?= $currentTenantId === null ? 0 : (int)$currentTenantId ?>,
    CSRF_TOKEN: '<?= htmlspecialchars($csrf, ENT_QUOTES) ?>',
    IS_SUPER_ADMIN: <?= $isSuperAdmin ? 'true' : 'false' ?>
};
</script>
<script src="/admin/assets/js/permissions-system.js?v=<?= time() ?>"></script>

</body>
</html>