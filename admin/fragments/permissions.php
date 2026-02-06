<?php
declare(strict_types=1);

/**
 * /admin/fragments/permissions.php
 * Production Version - Updated for new admin_context.php
 * 
 * ✅ Uses new permission system (role-based + resource-based)
 * ✅ Compatible with tenant_users table
 * ✅ No deprecated fields
 * ✅ Production-ready
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT / HEADER
// ════════════════════════════════════════════════════════════
if ($isFragment) {
    // Load admin_context to provide helper functions in fragment mode
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// ════════════════════════════════════════════════════════════
// VERIFY USER IS LOGGED IN
// ════════════════════════════════════════════════════════════
if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    } else {
        header('Location: /admin/login.php');
        exit;
    }
}

// ════════════════════════════════════════════════════════════
// GET USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user = admin_user();
$lang = admin_lang();
$dir = admin_dir();
$csrf = admin_csrf();
$tenantId = admin_tenant_id();
$pdo = admin_db();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════

// Method 1: Using role-based permissions
$canManagePermissions = can('permissions.manage') || can('manage_permissions');

// Method 2: Using resource-based permissions (recommended for granular control)
$canViewAll = can_view_all('permissions');
$canViewOwn = can_view_own('permissions');
$canViewTenant = can_view_tenant('permissions');
$canCreate = can_create('permissions');
$canEditAll = can_edit_all('permissions');
$canEditOwn = can_edit_own('permissions');
$canDeleteAll = can_delete_all('permissions');
$canDeleteOwn = can_delete_own('permissions');

// Combined permissions for UI
$canView = $canViewAll || $canViewOwn || $canViewTenant || $canManagePermissions;
$canEdit = $canEditAll || $canEditOwn || $canManagePermissions;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManagePermissions;

// Super admin always has access
$isSuperAdmin = is_super_admin();
if ($isSuperAdmin) {
    $canView = $canEdit = $canDelete = $canCreate = true;
}

// If user has no view permission at all, deny access
if (!$canView && !$isSuperAdmin) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view permissions');
    }
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
function __t($key, $fallback = '') {
    if (function_exists('i18n_get')) {
        $v = i18n_get($key);
        return $v ?? ($fallback ?? $key);
    }
    return $fallback ?? $key;
}

function __tr($key, $replacements = []) {
    $text = __t($key, $key);
    foreach ($replacements as $ph => $val) {
        $text = str_replace("{" . $ph . "}", (string)$val, $text);
    }
    return $text;
}

// ════════════════════════════════════════════════════════════
// GET TENANTS FOR SUPER ADMIN
// ════════════════════════════════════════════════════════════
$allTenants = [];
if ($isSuperAdmin && $pdo instanceof PDO) {
    $stmt = $pdo->query("SELECT id, name FROM tenants ORDER BY name");
    $allTenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ════════════════════════════════════════════════════════════
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/api';

?>
<!-- Force load CSS if embedded -->
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/permissions-system.css?v=<?= time() ?>">
<?php endif; ?>

<!-- Page Meta -->
<meta data-page="permissions"
      data-i18n-files="/admin/languages/Permissions/<?= rawurlencode($lang) ?>.json">

<!-- Page Container -->
<div class="page-container" id="permissionsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Alerts Container -->
    <div class="alerts-container" id="alertsContainer"></div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="permissions.title"><?= __t('permissions.title', 'Permissions Management') ?></h1>
            <p class="page-subtitle" data-i18n="permissions.subtitle"><?= __t('permissions.subtitle', 'Manage Roles, Permissions, and Access Control') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($isSuperAdmin && !empty($allTenants)): ?>
            <select id="tenantSelector" class="form-control" style="width:240px;">
                <option value="0" <?= ($tenantId === null || $tenantId == 0) ? 'selected' : '' ?> data-i18n="permissions.global_no_tenant"><?= __t('permissions.global_no_tenant', 'Global (no tenant)') ?></option>
                <?php foreach ($allTenants as $tenant): ?>
                <option value="<?= (int)$tenant['id'] ?>" <?= $tenant['id'] == $tenantId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tenant['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <div style="min-width:200px;padding:6px 10px;background:#0f172a;color:#cbd5e1;border-radius:6px;text-align:center;">
                <span data-i18n="permissions.tenant_label"><?= __t('permissions.tenant_label', 'Tenant') ?></span>: 
                <?= $tenantId === null ? '<strong data-i18n="permissions.global">'.  __t('permissions.global', 'Global') .'</strong>' : 'ID ' . (int)$tenantId ?>
            </div>
            <?php endif; ?>
            <button class="btn btn-secondary btn-sm" onclick="PermissionsApp.refreshAll()">
                <i class="fas fa-sync"></i> <span data-i18n="permissions.btn_refresh"><?= __t('permissions.btn_refresh', 'Refresh') ?></span>
            </button>
        </div>
    </div>

    <!-- Main Tabs -->
    <div class="main-tabs">
        <button class="main-tab active" data-tab="roles">
            <i class="fas fa-users-cog"></i> <span data-i18n="permissions.tab_roles"><?= __t('permissions.tab_roles', 'Roles') ?></span>
        </button>
        <button class="main-tab" data-tab="permissions">
            <i class="fas fa-key"></i> <span data-i18n="permissions.tab_permissions"><?= __t('permissions.tab_permissions', 'Permissions') ?></span>
        </button>
        <button class="main-tab" data-tab="assign">
            <i class="fas fa-link"></i> <span data-i18n="permissions.tab_assign"><?= __t('permissions.tab_assign', 'Assign') ?></span>
        </button>
        <button class="main-tab" data-tab="resources">
            <i class="fas fa-table-cells"></i> <span data-i18n="permissions.tab_resources"><?= __t('permissions.tab_resources', 'Resources') ?></span>
        </button>
    </div>

    <!-- TAB: ROLES -->
    <div class="tab-content active" id="tab-roles">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-users-cog"></i> <span data-i18n="permissions.roles_list"><?= __t('permissions.roles_list', 'Roles List') ?></span>
                </h3>
                <div class="actions">
                    <input type="text" id="rolesSearch" class="form-control" data-i18n-placeholder="permissions.search_roles" placeholder="<?= __t('permissions.search_roles', 'Search roles...') ?>" style="width:250px;">
                    <?php if ($canCreate): ?>
                    <button class="btn btn-primary" onclick="PermissionsApp.openRoleModal()">
                        <i class="fas fa-plus"></i> <span data-i18n="permissions.add_role"><?= __t('permissions.add_role', 'Add Role') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div id="rolesLoading" class="loading">
                    <div class="spinner"></div>
                    <p data-i18n="permissions.loading"><?= __t('permissions.loading', 'Loading...') ?></p>
                </div>
                <div id="rolesContent" style="display:none;">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th data-i18n="permissions.table.id"><?= __t('permissions.table.id', 'ID') ?></th>
                                    <th data-i18n="permissions.table.name"><?= __t('permissions.table.name', 'Name') ?></th>
                                    <th data-i18n="permissions.table.key"><?= __t('permissions.table.key', 'Key') ?></th>
                                    <th data-i18n="permissions.table.created"><?= __t('permissions.table.created', 'Created') ?></th>
                                    <th data-i18n="permissions.table.actions"><?= __t('permissions.table.actions', 'Actions') ?></th>
                                </tr>
                            </thead>
                            <tbody id="rolesTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="rolesEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-users-cog"></i>
                    <h3 data-i18n="permissions.no_roles"><?= __t('permissions.no_roles', 'No Roles') ?></h3>
                    <?php if ($canCreate): ?>
                    <button class="btn btn-primary" onclick="PermissionsApp.openRoleModal()">
                        <i class="fas fa-plus"></i> <span data-i18n="permissions.add_first_role"><?= __t('permissions.add_first_role', 'Add First Role') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: PERMISSIONS -->
    <div class="tab-content" id="tab-permissions">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-key"></i> <span data-i18n="permissions.permissions_list"><?= __t('permissions.permissions_list', 'Permissions List') ?></span>
                </h3>
                <div class="actions">
                    <input type="text" id="permissionsSearch" class="form-control" data-i18n-placeholder="permissions.search_permissions" placeholder="<?= __t('permissions.search_permissions', 'Search permissions...') ?>" style="width:250px;">
                    <?php if ($canCreate): ?>
                    <button class="btn btn-primary" onclick="PermissionsApp.openPermissionModal()">
                        <i class="fas fa-plus"></i> <span data-i18n="permissions.add_permission"><?= __t('permissions.add_permission', 'Add Permission') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div id="permissionsLoading" class="loading">
                    <div class="spinner"></div>
                    <p data-i18n="permissions.loading"><?= __t('permissions.loading', 'Loading...') ?></p>
                </div>
                <div id="permissionsContent" style="display:none;">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th data-i18n="permissions.table.id"><?= __t('permissions.table.id', 'ID') ?></th>
                                    <th data-i18n="permissions.table.name"><?= __t('permissions.table.name', 'Name') ?></th>
                                    <th data-i18n="permissions.table.key"><?= __t('permissions.table.key', 'Key') ?></th>
                                    <th data-i18n="permissions.table.description"><?= __t('permissions.table.description', 'Description') ?></th>
                                    <th data-i18n="permissions.table.actions"><?= __t('permissions.table.actions', 'Actions') ?></th>
                                </tr>
                            </thead>
                            <tbody id="permissionsTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="permissionsEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-key"></i>
                    <h3 data-i18n="permissions.no_permissions"><?= __t('permissions.no_permissions', 'No Permissions') ?></h3>
                    <?php if ($canCreate): ?>
                    <button class="btn btn-primary" onclick="PermissionsApp.openPermissionModal()">
                        <i class="fas fa-plus"></i> <span data-i18n="permissions.add_first_permission"><?= __t('permissions.add_first_permission', 'Add First Permission') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: ASSIGN -->
    <div class="tab-content" id="tab-assign">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-tag"></i> <span data-i18n="permissions.select_role"><?= __t('permissions.select_role', 'Select Role') ?></span></h3>
                <input type="text" id="assignRolesSearch" class="form-control" data-i18n-placeholder="permissions.search_roles" placeholder="<?= __t('permissions.search_roles', 'Search roles...') ?>" style="width:250px;">
            </div>
            <div class="card-body">
                <div class="role-selector" id="assignRoleSelector"></div>
            </div>
        </div>
        <div class="card" id="assignCard" style="display:none;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-link"></i> <span data-i18n="permissions.permissions_for"><?= __t('permissions.permissions_for', 'Permissions for') ?></span> <span id="assignRoleName"></span>
                </h3>
                <div class="actions">
                    <input type="text" id="assignPermSearch" class="form-control" data-i18n-placeholder="permissions.search" placeholder="<?= __t('permissions.search', 'Search...') ?>" style="width:200px;">
                    <button class="btn btn-primary btn-sm" onclick="PermissionsApp.selectAllAssign()">
                        <i class="fas fa-check-double"></i> <span data-i18n="permissions.select_all"><?= __t('permissions.select_all', 'Select All') ?></span>
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="PermissionsApp.deselectAllAssign()">
                        <i class="fas fa-times"></i> <span data-i18n="permissions.clear"><?= __t('permissions.clear', 'Clear') ?></span>
                    </button>
                    <?php if ($canEdit): ?>
                    <button class="btn btn-success" id="btnSaveAssign" onclick="PermissionsApp.saveAssign()">
                        <i class="fas fa-save"></i> <span data-i18n="permissions.save"><?= __t('permissions.save', 'Save') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" id="assignContent"></div>
        </div>
    </div>

    <!-- TAB: RESOURCES -->
    <div class="tab-content" id="tab-resources">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-tag"></i> <span data-i18n="permissions.select_role"><?= __t('permissions.select_role', 'Select Role') ?></span></h3>
                <input type="text" id="resourceRolesSearch" class="form-control" data-i18n-placeholder="permissions.search_roles" placeholder="<?= __t('permissions.search_roles', 'Search roles...') ?>" style="width:250px;">
            </div>
            <div class="card-body">
                <div class="role-selector" id="resourcesRoleSelector"></div>
            </div>
        </div>
        <div class="card" id="resourcesCard" style="display:none;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table-cells"></i> <span data-i18n="permissions.resources_for"><?= __t('permissions.resources_for', 'Resources for') ?></span> <span id="resourceRoleName"></span>
                </h3>
                <div class="actions">
                    <input type="text" id="resourcesSearch" class="form-control" data-i18n-placeholder="permissions.search" placeholder="<?= __t('permissions.search', 'Search...') ?>" style="width:200px;">
                    <?php if ($canCreate): ?>
                    <button class="btn btn-primary btn-sm" onclick="PermissionsApp.openResourcePermModal()">
                        <i class="fas fa-plus"></i> <span data-i18n="permissions.add_resource_permission"><?= __t('permissions.add_resource_permission', 'Add Resource Permission') ?></span>
                    </button>
                    <?php endif; ?>
                    <?php if ($canEdit): ?>
                    <button class="btn btn-success" id="btnSaveResource" onclick="PermissionsApp.saveResources()">
                        <i class="fas fa-save"></i> <span data-i18n="permissions.save_changes"><?= __t('permissions.save_changes', 'Save Changes') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div id="resourcesLoading" class="loading">
                    <div class="spinner"></div>
                    <p data-i18n="permissions.loading"><?= __t('permissions.loading', 'Loading...') ?></p>
                </div>
                <div id="resourcesContent" style="display:none;">
                    <div class="table-responsive">
                        <table class="data-table resource-table">
                            <thead>
                                <tr>
                                    <th class="sticky-col" data-i18n="permissions.table.permission_resource"><?= __t('permissions.table.permission_resource', 'Permission / Resource') ?></th>
                                    <th data-i18n="permissions.table.view_all"><?= __t('permissions.table.view_all', 'View All') ?></th>
                                    <th data-i18n="permissions.table.view_own"><?= __t('permissions.table.view_own', 'View Own') ?></th>
                                    <th data-i18n="permissions.table.view_tenant"><?= __t('permissions.table.view_tenant', 'View Tenant') ?></th>
                                    <th data-i18n="permissions.table.create"><?= __t('permissions.table.create', 'Create') ?></th>
                                    <th data-i18n="permissions.table.edit_all"><?= __t('permissions.table.edit_all', 'Edit All') ?></th>
                                    <th data-i18n="permissions.table.edit_own"><?= __t('permissions.table.edit_own', 'Edit Own') ?></th>
                                    <th data-i18n="permissions.table.delete_all"><?= __t('permissions.table.delete_all', 'Delete All') ?></th>
                                    <th data-i18n="permissions.table.delete_own"><?= __t('permissions.table.delete_own', 'Delete Own') ?></th>
                                </tr>
                            </thead>
                            <tbody id="resourcesTableBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="resourcesEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-table-cells"></i>
                    <h3 data-i18n="permissions.no_resource_permissions"><?= __t('permissions.no_resource_permissions', 'No Resource Permissions') ?></h3>
                    <p data-i18n="permissions.no_resource_permissions_desc"><?= __t('permissions.no_resource_permissions_desc', 'This role has no resource-level permissions configured') ?></p>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- MODALS -->
<div class="modal" id="roleModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="roleModalTitle" data-i18n="permissions.add_role"><?= __t('permissions.add_role', 'Add Role') ?></h3>
            <button class="modal-close" onclick="PermissionsApp.closeRoleModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="roleForm" onsubmit="return false;">
                <input type="hidden" id="roleId">
                <div class="form-group">
                    <label class="form-label" data-i18n="permissions.form.display_name"><?= __t('permissions.form.display_name', 'Display Name') ?> *</label>
                    <input type="text" class="form-control" id="roleDisplayName" required data-i18n-placeholder="permissions.form.display_name_placeholder" placeholder="<?= __t('permissions.form.display_name_placeholder', 'e.g., Super Admin') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" data-i18n="permissions.form.key_name"><?= __t('permissions.form.key_name', 'Key Name') ?> *</label>
                    <input type="text" class="form-control" id="roleKeyName" required pattern="[a-z_]+" data-i18n-placeholder="permissions.form.key_name_placeholder" placeholder="<?= __t('permissions.form.key_name_placeholder', 'e.g., super_admin') ?>">
                    <small class="form-text" data-i18n="permissions.form.key_name_hint"><?= __t('permissions.form.key_name_hint', 'lowercase and underscores only') ?></small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="PermissionsApp.closeRoleModal()" data-i18n="permissions.cancel"><?= __t('permissions.cancel', 'Cancel') ?></button>
            <button class="btn btn-primary" id="btnSaveRole" onclick="PermissionsApp.saveRole()">
                <i class="fas fa-save"></i> <span data-i18n="permissions.save"><?= __t('permissions.save', 'Save') ?></span>
            </button>
        </div>
    </div>
</div>

<div class="modal" id="permissionModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="permissionModalTitle" data-i18n="permissions.add_permission"><?= __t('permissions.add_permission', 'Add Permission') ?></h3>
            <button class="modal-close" onclick="PermissionsApp.closePermissionModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="permissionForm" onsubmit="return false;">
                <input type="hidden" id="permissionId">
                <div class="form-group">
                    <label class="form-label" data-i18n="permissions.form.display_name"><?= __t('permissions.form.display_name', 'Display Name') ?> *</label>
                    <input type="text" class="form-control" id="permissionDisplayName" required data-i18n-placeholder="permissions.form.permission_display_placeholder" placeholder="<?= __t('permissions.form.permission_display_placeholder', 'e.g., Manage Users') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" data-i18n="permissions.form.key_name"><?= __t('permissions.form.key_name', 'Key Name') ?> *</label>
                    <input type="text" class="form-control" id="permissionKeyName" required pattern="[a-z_]+" data-i18n-placeholder="permissions.form.permission_key_placeholder" placeholder="<?= __t('permissions.form.permission_key_placeholder', 'e.g., manage_users') ?>">
                    <small class="form-text" data-i18n="permissions.form.key_name_hint"><?= __t('permissions.form.key_name_hint', 'lowercase and underscores only') ?></small>
                </div>
                <div class="form-group">
                    <label class="form-label" data-i18n="permissions.form.description"><?= __t('permissions.form.description', 'Description') ?></label>
                    <textarea class="form-control" id="permissionDescription" rows="3" data-i18n-placeholder="permissions.form.description_placeholder" placeholder="<?= __t('permissions.form.description_placeholder', 'Describe what this permission allows...') ?>"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="PermissionsApp.closePermissionModal()" data-i18n="permissions.cancel"><?= __t('permissions.cancel', 'Cancel') ?></button>
            <button class="btn btn-primary" id="btnSavePermission" onclick="PermissionsApp.savePermission()">
                <i class="fas fa-save"></i> <span data-i18n="permissions.save"><?= __t('permissions.save', 'Save') ?></span>
            </button>
        </div>
    </div>
</div>

<!-- Resource Permission Modal (create/edit single resource_permission) -->
<div class="modal" id="resourcePermModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="resourcePermModalTitle" data-i18n="permissions.add_resource_permission"><?= __t('permissions.add_resource_permission', 'Add Resource Permission') ?></h3>
            <button class="modal-close" onclick="PermissionsApp.closeResourcePermModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="resourcePermForm" onsubmit="return false;">
                <input type="hidden" id="rpId">
                <div class="form-group">
                    <label data-i18n="permissions.form.resource_type"><?= __t('permissions.form.resource_type', 'Resource Type') ?> *</label>
                    <input id="rpResourceType" class="form-control" required data-i18n-placeholder="permissions.form.resource_type_placeholder" placeholder="<?= __t('permissions.form.resource_type_placeholder', 'e.g., users') ?>">
                </div>

                <div class="form-group">
                    <label data-i18n="permissions.form.permission"><?= __t('permissions.form.permission', 'Permission') ?> *</label>
                    <select id="rpPermissionId" class="form-control" required>
                        <option value="" data-i18n="permissions.form.loading"><?= __t('permissions.form.loading', 'Loading...') ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label data-i18n="permissions.form.role_optional"><?= __t('permissions.form.role_optional', 'Role (optional)') ?></label>
                    <select id="rpRoleId" class="form-control">
                        <option value="" data-i18n="permissions.form.any_global"><?= __t('permissions.form.any_global', '— Any / Global —') ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label data-i18n="permissions.form.tenant_optional"><?= __t('permissions.form.tenant_optional', 'Tenant (optional)') ?></label>
                    <select id="rpTenantId" class="form-control">
                        <option value="0" data-i18n="permissions.global_no_tenant"><?= __t('permissions.global_no_tenant', 'Global (no tenant)') ?></option>
                        <?php foreach ($allTenants as $tenant): ?>
                            <option value="<?= (int)$tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group flags-grid">
                    <label><input type="checkbox" id="rp_can_view_all"> <span data-i18n="permissions.flags.view_all"><?= __t('permissions.flags.view_all', 'View All') ?></span></label>
                    <label><input type="checkbox" id="rp_can_view_own"> <span data-i18n="permissions.flags.view_own"><?= __t('permissions.flags.view_own', 'View Own') ?></span></label>
                    <label><input type="checkbox" id="rp_can_view_tenant"> <span data-i18n="permissions.flags.view_tenant"><?= __t('permissions.flags.view_tenant', 'View Tenant') ?></span></label>
                    <label><input type="checkbox" id="rp_can_create"> <span data-i18n="permissions.flags.create"><?= __t('permissions.flags.create', 'Create') ?></span></label>
                    <label><input type="checkbox" id="rp_can_edit_all"> <span data-i18n="permissions.flags.edit_all"><?= __t('permissions.flags.edit_all', 'Edit All') ?></span></label>
                    <label><input type="checkbox" id="rp_can_edit_own"> <span data-i18n="permissions.flags.edit_own"><?= __t('permissions.flags.edit_own', 'Edit Own') ?></span></label>
                    <label><input type="checkbox" id="rp_can_delete_all"> <span data-i18n="permissions.flags.delete_all"><?= __t('permissions.flags.delete_all', 'Delete All') ?></span></label>
                    <label><input type="checkbox" id="rp_can_delete_own"> <span data-i18n="permissions.flags.delete_own"><?= __t('permissions.flags.delete_own', 'Delete Own') ?></span></label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="PermissionsApp.closeResourcePermModal()" data-i18n="permissions.cancel"><?= __t('permissions.cancel', 'Cancel') ?></button>
            <button class="btn btn-primary" id="btnSaveResourcePerm" onclick="PermissionsApp.saveResourcePerm()">
                <i class="fas fa-save"></i> <span data-i18n="permissions.save"><?= __t('permissions.save', 'Save') ?></span>
            </button>
        </div>
    </div>
</div>

<script>
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE = window.APP_CONFIG.API_BASE || '<?= $apiBase ?>';
window.APP_CONFIG.TENANT_ID = window.APP_CONFIG.TENANT_ID || <?= $tenantId === null ? 0 : (int)$tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN = window.APP_CONFIG.CSRF_TOKEN || '<?= htmlspecialchars($csrf, ENT_QUOTES) ?>';
window.APP_CONFIG.IS_SUPER_ADMIN = <?= $isSuperAdmin ? 'true' : 'false' ?>;
window.APP_CONFIG.USER_LANG = '<?= addslashes($lang) ?>';

// Page permissions available to JS
window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate' => $canCreate,
    'canEdit' => $canEdit,
    'canDelete' => $canDelete,
    'canViewAll' => $canViewAll,
    'canViewOwn' => $canViewOwn,
    'canViewTenant' => $canViewTenant,
    'canEditAll' => $canEditAll,
    'canEditOwn' => $canEditOwn,
    'canDeleteAll' => $canDeleteAll,
    'canDeleteOwn' => $canDeleteOwn,
    'isSuperAdmin' => $isSuperAdmin
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<!-- Translation loader (runs early) -->
<script type="text/javascript">
(function(){
    async function applyTranslations() {
        try {
            const lang = window.APP_CONFIG.USER_LANG || 'en';
            const url = `/languages/Permissions/${encodeURIComponent(lang)}.json`;
            console.log('[Permissions] Loading translations from', url);
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Translation fetch failed: ' + res.status);
            const data = await res.json();
            const translations = data.strings || data;
            window.PERMISSIONS_TRANSLATIONS = translations;
            
            // apply translations to elements with data-i18n
            const container = document.getElementById('permissionsPageContainer');
            if (!container) return;
            container.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                const txt = key.split('.').reduce((o,k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) {
                    if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) {
                        el.placeholder = txt;
                    } else {
                        el.textContent = txt;
                    }
                }
            });
            // placeholders
            container.querySelectorAll('[data-i18n-placeholder]').forEach(el=>{
                const key = el.getAttribute('data-i18n-placeholder');
                const txt = key.split('.').reduce((o,k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) el.placeholder = txt;
            });
            console.log('[Permissions] Translations applied');
        } catch (err) {
            console.warn('[Permissions] Translation load/apply failed:', err);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyTranslations);
    } else {
        setTimeout(applyTranslations, 50);
    }
})();
</script>

<script src="/admin/assets/js/permissions-system.js?v=<?= time() ?>"></script>

<?php
// Load footer if standalone
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>