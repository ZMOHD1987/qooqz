<?php
declare(strict_types=1);

/**
 * /admin/fragments/tenant_users.php
 * Production Version - Embedded-friendly with Strict Permission Control
 *
 * PERMISSION SYSTEM:
 * This page implements dual-layer permission control:
 * 
 * 1. Role-based permissions (permissions table + role_permissions):
 *    - tenant_users.manage
 *    - tenant_users.create
 *    - tenant_users.view
 *    - tenant_users.edit
 *    - tenant_users.delete
 * 
 * 2. Resource-based permissions (resource_permissions table):
 *    - can_view_all: View all tenant users across all tenants (super admin)
 *    - can_view_tenant: View all users within current tenant
 *    - can_view_own: View only own user record
 *    - can_create: Create new tenant user assignments
 *    - can_edit_all: Edit any tenant user
 *    - can_edit_own: Edit only own user record
 *    - can_delete_all: Delete any tenant user
 *    - can_delete_own: Delete only own user record
 * 
 * DATA FILTERING:
 * - Super admins see all data across all tenants
 * - Users with can_view_tenant see only their tenant's data
 * - Users with entity_id assignment see only their entity's data
 * - Users with can_view_own see only their own records
 * 
 * Changes made:
 * - When loaded as fragment/embedded or AJAX we now load admin_context.php so helper functions (admin_user, admin_csrf, is_super_admin, admin_roles, admin_permissions, etc.) are available.
 * - Expose client-side globals: window.APP_CONFIG, window.CSRF_TOKEN, window.USER_LANGUAGE, window.USER_DIRECTION, and pagePermissions JSON.
 * - Robust translation loader that runs early (before module init) and stores translations at window.TENANT_USERS_TRANSLATIONS.
 * - Embedded init polling waits for AdminFramework + TenantUsers and initializes the module.
 * - Preserves standalone header/footer behavior.
 * - Strict permission enforcement with proper access denial
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

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════

// Method 1: Using role-based permissions
$canManageTenantUsers = can('tenant_users.manage') || can('tenant_users.create');

// Method 2: Using resource-based permissions (recommended for granular control)
$canViewAll = can_view_all('tenant_users');
$canViewOwn = can_view_own('tenant_users');
$canViewTenant = can_view_tenant('tenant_users');
$canCreate = can_create('tenant_users');
$canEditAll = can_edit_all('tenant_users');
$canEditOwn = can_edit_own('tenant_users');
$canDeleteAll = can_delete_all('tenant_users');
$canDeleteOwn = can_delete_own('tenant_users');

// Combined permissions for UI
$canView = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit = $canEditAll || $canEditOwn || $canManageTenantUsers;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManageTenantUsers;

// If user has no view permission at all, deny access (super admin always has access)
// However, if permissions are not configured at all in the database, allow tenant-scoped access as fallback
if (!$canView && !is_super_admin()) {
    // Check if this is a case of unconfigured permissions (all permission functions return false)
    // by attempting to verify if the permission system is functional
    $resourcePerms = function_exists('admin_resource_permissions') ? admin_resource_permissions() : [];
    $permissionsConfigured = !empty($resourcePerms);
    
    // If permissions are properly configured but user has no access, deny
    // If permissions are not configured, allow access (fallback mode)
    if ($permissionsConfigured) {
        if ($isFragment) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        } else {
            http_response_code(403);
            die('Access denied: You do not have permission to view tenant users');
        }
    } else {
        // Fallback: permissions not configured, grant basic tenant-scoped view access
        $canView = true;
        $canViewTenant = true;
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
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/api';

?>
<!-- Force load CSS if embedded -->
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/tenant_users.css?v=<?= time() ?>">
<?php endif; ?>

<!-- Page Meta -->
<meta data-page="tenant_users"
      data-i18n-files="/languages/TenantUsers/<?= rawurlencode($lang) ?>.json">

<!-- Page Container -->
<div class="page-container" id="tenantUsersPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="tenant_users.title"><?= __t('tenant_users.title', 'Tenant Users Management') ?></h1>
            <p class="page-subtitle" data-i18n="tenant_users.subtitle"><?= __t('tenant_users.subtitle', 'Manage users assigned to tenants') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddTenantUser" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="tenant_users.add_new"><?= __t('tenant_users.add_new', 'Add New User') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div id="tenantUserFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title"><?= __t('form.add_title', 'Add Tenant User') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm" aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="tenantUserForm" novalidate>
                <!-- Hidden Fields -->
                <input type="hidden" id="formId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-row">
                    <!-- Tenant ID -->
                    <div class="form-group">
                        <label for="formTenantId" class="required" data-i18n="form.fields.tenant_id.label">
                            <?= __t('form.fields.tenant_id.label', 'Tenant ID') ?>
                        </label>
                        <input type="number"
                               id="formTenantId"
                               name="tenant_id"
                               class="form-control"
                               required
                               min="1"
                               data-i18n-placeholder="form.fields.tenant_id.placeholder"
                               placeholder="<?= __t('form.fields.tenant_id.placeholder', 'Enter tenant ID') ?>">
                        <div class="invalid-feedback" data-i18n="form.fields.tenant_id.required">
                            <?= __t('form.fields.tenant_id.required', 'Tenant ID is required') ?>
                        </div>
                    </div>

                    <!-- User ID -->
                    <div class="form-group">
                        <label for="formUserId" class="required" data-i18n="form.fields.user_id.label">
                            <?= __t('form.fields.user_id.label', 'User ID') ?>
                        </label>
                        <input type="number"
                               id="formUserId"
                               name="user_id"
                               class="form-control"
                               required
                               min="1"
                               data-i18n-placeholder="form.fields.user_id.placeholder"
                               placeholder="<?= __t('form.fields.user_id.placeholder', 'Enter user ID') ?>">
                        <div class="invalid-feedback" data-i18n="form.fields.user_id.required">
                            <?= __t('form.fields.user_id.required', 'User ID is required') ?>
                        </div>
                    </div>

                    <!-- Role -->
                    <div class="form-group">
                        <label for="formRoleId" class="required" data-i18n="form.fields.role_id.label">
                            <?= __t('form.fields.role_id.label', 'Role') ?>
                        </label>
                        <select id="formRoleId" name="role_id" class="form-control" required disabled>
                            <option value="" data-i18n="form.fields.role_id.enter_tenant_first">
                                <?= __t('form.fields.role_id.enter_tenant_first', 'Enter tenant ID first') ?>
                            </option>
                        </select>
                        <div class="invalid-feedback" data-i18n="form.fields.role_id.required">
                            <?= __t('form.fields.role_id.required', 'Role is required') ?>
                        </div>
                    </div>

                    <!-- Entity -->
                    <div class="form-group">
                        <label for="formEntityId" data-i18n="form.fields.entity_id.label">
                            <?= __t('form.fields.entity_id.label', 'Entity (Optional)') ?>
                        </label>
                        <select id="formEntityId" name="entity_id" class="form-control" disabled>
                            <option value="" data-i18n="form.fields.entity_id.enter_tenant_first">
                                <?= __t('form.fields.entity_id.enter_tenant_first', 'Enter tenant ID first') ?>
                            </option>
                        </select>
                        <small class="form-text text-muted" data-i18n="form.fields.entity_id.help">
                            <?= __t('form.fields.entity_id.help', 'Optionally assign user to a specific entity') ?>
                        </small>
                    </div>
                </div>

                <!-- Entity Info Box -->
                <div id="entityInfo" style="display:none;margin-top:1rem;padding:1rem;background:var(--background-tertiary,#0f172a);border:1px solid var(--border-color,#334155);border-radius:8px">
                    <div style="display:flex;align-items:center;gap:0.5rem">
                        <i class="fas fa-store" style="color:var(--warning-color,#f59e0b)"></i>
                        <div>
                            <strong style="color:var(--text-primary,#fff)" data-i18n="form.entity_info.name">
                                <?= __t('form.entity_info.name', 'Store:') ?>
                            </strong>
                            <span id="entityName" style="color:var(--text-primary,#fff)"></span>
                            <br>
                            <small id="entitySlug" style="color:var(--text-secondary,#94a3b8)"></small>
                            <span id="entityStatus" class="badge" style="margin-<?= $dir === 'rtl' ? 'right' : 'left' ?>:0.5rem"></span>
                        </div>
                    </div>
                </div>


                <!-- Tenant Info Box -->
                <div id="tenantInfo" style="display:none;margin-top:1rem;padding:1rem;background:var(--background-tertiary,#0f172a);border:1px solid var(--border-color,#334155);border-radius:8px">
                    <div style="display:flex;align-items:center;gap:0.5rem">
                        <i class="fas fa-building" style="color:var(--primary-color,#3b82f6)"></i>
                        <div>
                            <strong style="color:var(--text-primary,#fff)" data-i18n="form.tenant_info.name">
                                <?= __t('form.tenant_info.name', 'Name:') ?>
                            </strong>
                            <span id="tenantName" style="color:var(--text-primary,#fff)"></span>
                            <br>
                            <small id="tenantDomain" style="color:var(--text-secondary,#94a3b8)"></small>
                            <span id="tenantStatus" class="badge" style="margin-<?= $dir === 'rtl' ? 'right' : 'left' ?>:0.5rem"></span>
                        </div>
                    </div>
                </div>

                <!-- User Info Box -->
                <div id="userInfo" style="display:none;margin-top:1rem;padding:1rem;background:var(--background-tertiary,#0f172a);border:1px solid var(--border-color,#334155);border-radius:8px">
                    <div style="display:flex;align-items:center;gap:0.5rem">
                        <i class="fas fa-user" style="color:var(--success-color,#10b981)"></i>
                        <div>
                            <strong style="color:var(--text-primary,#fff)" data-i18n="form.user_info.name">
                                <?= __t('form.user_info.name', 'Name:') ?>
                            </strong>
                            <span id="userName" style="color:var(--text-primary,#fff)"></span>
                            <br>
                            <small id="userEmail" style="color:var(--text-secondary,#94a3b8)"></small>
                            <span id="userStatus" class="badge" style="margin-<?= $dir === 'rtl' ? 'right' : 'left' ?>:0.5rem"></span>
                        </div>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1rem">
                    <!-- Status -->
                    <div class="form-group">
                        <label for="formIsActive" data-i18n="form.fields.status.label">
                            <?= __t('form.fields.status.label', 'Status') ?>
                        </label>
                        <select id="formIsActive" name="is_active" class="form-control">
                            <option value="1" data-i18n="form.fields.status.active">
                                <?= __t('form.fields.status.active', 'Active') ?>
                            </option>
                            <option value="0" data-i18n="form.fields.status.inactive">
                                <?= __t('form.fields.status.inactive', 'Inactive') ?>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitForm">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save"><?= __t('form.buttons.save', 'Save') ?></span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelForm" data-i18n="form.buttons.cancel">
                        <?= __t('form.buttons.cancel', 'Cancel') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteTenantUser" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash"></i>
                        <span data-i18n="table.actions.delete"><?= __t('table.actions.delete', 'Delete') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchInput" data-i18n="filters.search">
                        <?= __t('filters.search', 'Search') ?>
                    </label>
                    <input type="text"
                           id="searchInput"
                           class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= __t('filters.search_placeholder', 'Search...') ?>">
                </div>

                <div class="filter-group">
                    <label for="tenantFilter" data-i18n="filters.tenant_id">
                        <?= __t('filters.tenant_id', 'Tenant ID') ?>
                    </label>
                    <input type="number"
                           id="tenantFilter"
                           class="form-control"
                           data-i18n-placeholder="filters.tenant_placeholder"
                           placeholder="<?= __t('filters.tenant_placeholder', 'Filter by tenant') ?>">
                </div>

                <div class="filter-group">
                    <label for="userFilter" data-i18n="filters.user_id">
                        <?= __t('filters.user_id', 'User ID') ?>
                    </label>
                    <input type="number"
                           id="userFilter"
                           class="form-control"
                           data-i18n-placeholder="filters.user_placeholder"
                           placeholder="<?= __t('filters.user_placeholder', 'Filter by user') ?>">
                </div>

                <div class="filter-group">
                    <label for="entityFilter" data-i18n="filters.entity_id">
                        <?= __t('filters.entity_id', 'Entity') ?>
                    </label>
                    <input type="number"
                           id="entityFilter"
                           class="form-control"
                           data-i18n-placeholder="filters.entity_placeholder"
                           placeholder="<?= __t('filters.entity_placeholder', 'Filter by entity') ?>">
                </div>

                <div class="filter-group">
                    <label for="statusFilter" data-i18n="filters.status">
                        <?= __t('filters.status', 'Status') ?>
                    </label>
                    <select id="statusFilter" class="form-control">
                        <option value="" data-i18n="filters.status_options.all">
                            <?= __t('filters.status_options.all', 'All Status') ?>
                        </option>
                        <option value="1" data-i18n="filters.status_options.active">
                            <?= __t('filters.status_options.active', 'Active') ?>
                        </option>
                        <option value="0" data-i18n="filters.status_options.inactive">
                            <?= __t('filters.status_options.inactive', 'Inactive') ?>
                        </option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button id="btnApplyFilters" class="btn btn-secondary" data-i18n="filters.apply">
                        <?= __t('filters.apply', 'Apply') ?>
                    </button>
                    <button id="btnResetFilters" class="btn btn-outline" data-i18n="filters.reset">
                        <?= __t('filters.reset', 'Reset') ?>
                    </button>
                    <button id="btnExportExcel" class="btn btn-success" data-i18n="table.actions.export" style="margin-<?= $dir === 'rtl' ? 'right' : 'left' ?>:auto">
                        <i class="fas fa-file-excel"></i> <?= __t('table.actions.export', 'Export Excel') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table (loading / container / empty / error) -->
    <div class="card table-card">
        <div class="card-body">
            <div id="tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="tenant_users.loading"><?= __t('tenant_users.loading', 'Loading...') ?></p>
            </div>

            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="tenantUsersTable">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id"><?= __t('table.headers.id', 'ID') ?></th>
                                <th data-i18n="table.headers.username"><?= __t('table.headers.username', 'Username') ?></th>
                                <th data-i18n="table.headers.email"><?= __t('table.headers.email', 'Email') ?></th>
                                <th data-i18n="table.headers.tenant"><?= __t('table.headers.tenant', 'Tenant') ?></th>
                                <th data-i18n="table.headers.entity"><?= __t('table.headers.entity', 'Entity') ?></th>
                                <th data-i18n="table.headers.role"><?= __t('table.headers.role', 'Role') ?></th>
                                <th data-i18n="table.headers.joined_at"><?= __t('table.headers.joined_at', 'Joined At') ?></th>
                                <th data-i18n="table.headers.status"><?= __t('table.headers.status', 'Status') ?></th>
                                <th data-i18n="table.headers.actions"><?= __t('table.headers.actions', 'Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <span data-i18n="pagination.showing"><?= __t('pagination.showing', 'Showing') ?></span>
                        <span id="paginationInfo">0-0 of 0</span>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>

            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">👥</div>
                <h3 data-i18n="table.empty.title"><?= __t('table.empty.title', 'No Tenant Users Found') ?></h3>
                <p data-i18n="table.empty.message"><?= __t('table.empty.message', 'Start by adding users to tenants') ?></p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="if(window.TenantUsers)window.TenantUsers.add()">
                    <i class="fas fa-plus"></i>
                    <span data-i18n="table.empty.add_first"><?= __t('table.empty.add_first', 'Add First User') ?></span>
                </button>
                <?php endif; ?>
            </div>

            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="messages.error.load_failed"><?= __t('messages.error.load_failed', 'Error Loading Data') ?></h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary" data-i18n="tenant_users.retry">
                    <?= __t('tenant_users.retry', 'Retry') ?>
                </button>
            </div>
        </div>
    </div>

</div>

<!-- Expose client-side globals for the module -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE = window.APP_CONFIG.API_BASE || <?= json_encode($apiBase) ?>;
window.APP_CONFIG.TENANT_ID = window.APP_CONFIG.TENANT_ID || <?= json_encode($tenantId) ?>;
window.APP_CONFIG.CSRF_TOKEN = window.APP_CONFIG.CSRF_TOKEN || <?= json_encode($csrf) ?>;
window.APP_CONFIG.IS_SUPER_ADMIN = window.APP_CONFIG.IS_SUPER_ADMIN || <?= is_super_admin() ? 'true' : 'false' ?>;

window.USER_LANGUAGE = window.USER_LANGUAGE || <?= json_encode($lang) ?>;
window.USER_DIRECTION = window.USER_DIRECTION || <?= json_encode($dir) ?>;
window.CSRF_TOKEN = window.CSRF_TOKEN || <?= json_encode($csrf) ?>;

// Page permissions available to JS - Granular permissions
window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate' => $canCreate,
    'canEdit' => $canEdit,
    'canDelete' => $canDelete,
    'canView' => $canView,
    'canViewAll' => $canViewAll,
    'canViewOwn' => $canViewOwn,
    'canViewTenant' => $canViewTenant,
    'canEditAll' => $canEditAll,
    'canEditOwn' => $canEditOwn,
    'canDeleteAll' => $canDeleteAll,
    'canDeleteOwn' => $canDeleteOwn,
    'isSuperAdmin' => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<!-- Translation loader (runs early) -->
<script type="text/javascript">
(function(){
    async function applyTranslations() {
        try {
            const lang = window.USER_LANGUAGE || 'en';
            const url = `/languages/TenantUsers/${encodeURIComponent(lang)}.json`;
            console.log('[TenantUsers] Loading translations from', url);
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Translation fetch failed: ' + res.status);
            const translations = await res.json();
            window.TENANT_USERS_TRANSLATIONS = translations;
            // apply translations to elements with data-i18n
            const container = document.getElementById('tenantUsersPageContainer');
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
            console.log('[TenantUsers] Translations applied');
        } catch (err) {
            console.warn('[TenantUsers] Translation load/apply failed:', err);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyTranslations);
    } else {
        setTimeout(applyTranslations, 50);
    }
})();
</script>

<!-- Page Permissions JSON for scripts that prefer it in DOM -->
<script id="pagePermissions" type="application/json">
<?= json_encode(['canCreate' => $canCreate, 'canEdit' => $canEdit, 'canDelete' => $canDelete], JSON_UNESCAPED_UNICODE) ?>
</script>

<!-- Load AdminFramework + Page module when embedded; otherwise load normally -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/tenant_users.js?v=<?= time() ?>"></script>

<script>
(function(){
    console.log('[TenantUsers] Embedded mode - waiting for framework & module...');
    let attempts = 0, maxAttempts = 50;
    const interval = setInterval(function(){
        attempts++;
        if (window.AdminFramework && window.TenantUsers && typeof window.TenantUsers.init === 'function') {
            clearInterval(interval);
            console.log('[TenantUsers] Module ready - initializing...');
            try {
                const maybePromise = window.TenantUsers.init();
                if (maybePromise && typeof maybePromise.then === 'function') {
                    maybePromise.then(()=>console.log('[TenantUsers] Initialized')).catch(e=>console.error('[TenantUsers] Init failed', e));
                } else {
                    console.log('[TenantUsers] Initialized (sync)');
                }
            } catch (e) {
                console.error('[TenantUsers] Init threw', e);
            }
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[TenantUsers] Timeout waiting for module. Framework present:', !!window.AdminFramework, 'Module present:', !!window.TenantUsers);
        } else if (attempts % 10 === 0) {
            console.log('[TenantUsers] waiting...', attempts, '/', maxAttempts);
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/tenant_users.js?v=<?= time() ?>"></script>
<?php endif; ?>

<?php
// Load footer if standalone
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>