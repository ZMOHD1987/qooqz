<?php
declare(strict_types=1);

/**
 * /admin/fragments/users.php
 * Production Version - Uses admin_context
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT
// ════════════════════════════════════════════════════════════
if (!$isFragment) {
    require_once __DIR__ . '/../includes/header.php';
} else {
    // Load admin_context in fragment mode
    require_once __DIR__ . '/../includes/admin_context.php';
}

// ════════════════════════════════════════════════════════════
// ✅ USE HELPER FUNCTIONS FROM admin_context
// ════════════════════════════════════════════════════════════
$canCreate = can('manage_users');
$canEdit = can('manage_users');
$canDelete = can('manage_users');

$lang = admin_lang();
$userLanguage = admin_user()['preferred_language'] ?? $lang;
$csrf = admin_csrf();
$username = admin_username();

?>

<!-- Force load CSS if embedded -->
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/users.css?v=<?= time() ?>">
<?php endif; ?>

<!-- Page Meta -->
<meta data-page="users" 
      data-i18n-files="/languages/Users/<?= htmlspecialchars($userLanguage) ?>.json"
      data-assets-css="/admin/assets/css/pages/users.css"
      data-assets-js="/admin/assets/js/pages/users.js">

<!-- Page Container -->
<div class="page-container" id="usersPageContainer">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="users.title">Users Management</h1>
            <p class="page-subtitle" data-i18n="users.subtitle">Manage system users</p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddUser" class="btn btn-primary" style="display:inline-flex!important;opacity:1!important;visibility:visible!important">
                <i class="fas fa-plus"></i>
                <span data-i18n="users.add_new">Add User</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div id="formContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 id="formTitle" data-i18n="users.form.add">Add User</h3>
            <button id="btnCloseForm" class="btn btn-sm btn-outline">×</button>
        </div>
        <div class="card-body">
            <form id="userForm">
                <input type="hidden" id="formAction" value="add">
                <input type="hidden" id="editingId" value="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="username" data-i18n="users.form.username">Username *</label>
                        <input type="text" id="username" name="username" required placeholder="Enter username">
                    </div>
                    <div class="form-group">
                        <label for="email" data-i18n="users.form.email">Email *</label>
                        <input type="email" id="email" name="email" required placeholder="Enter email">
                    </div>
                    <div class="form-group">
                        <label for="password">
                            <span data-i18n="users.form.password">Password</span>
                            <span id="passwordLabel">*</span>
                        </label>
                        <input type="password" id="password" name="password" placeholder="Enter password">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="preferred_language" data-i18n="users.form.preferred_language">Language</label>
                        <select id="preferred_language" name="preferred_language">
                            <option value="en">English</option>
                            <option value="ar">Arabic</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="phone" data-i18n="users.form.phone">Phone</label>
                        <input type="text" id="phone" name="phone" data-i18n-placeholder="users.form.phone_placeholder" placeholder="Enter phone">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="is_active" name="is_active" checked>
                            <span data-i18n="users.form.active">Active</span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" data-i18n="users.form.save">Save</button>
                    <button type="button" id="btnCancelForm" class="btn btn-outline" data-i18n="users.form.cancel">Cancel</button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteUser" class="btn btn-danger" style="display:none" data-i18n="users.form.delete">Delete</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchInput" data-i18n="users.search">Search</label>
                    <input type="text" id="searchInput" class="form-control" data-i18n-placeholder="users.search_placeholder" placeholder="Search">
                </div>

                <div class="filter-group">
                    <label for="languageFilter" data-i18n="users.language">Language</label>
                    <select id="languageFilter" class="form-control">
                        <option value="" data-i18n="users.all_languages">All Languages</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="statusFilter" data-i18n="users.status">Status</label>
                    <select id="statusFilter" class="form-control">
                        <option value="" data-i18n="users.all_status">All Status</option>
                        <option value="1" data-i18n="users.active">Active</option>
                        <option value="0" data-i18n="users.inactive">Inactive</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button id="btnApplyFilters" class="btn btn-secondary" data-i18n="users.apply_filters">Apply</button>
                    <button id="btnResetFilters" class="btn btn-outline" data-i18n="users.reset_filters">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card table-card">
        <div class="card-body">
            
            <!-- Loading State -->
            <div id="tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="users.loading">Loading...</p>
            </div>

            <!-- Table Container -->
            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="usersTable">
                        <thead>
                            <tr>
                                <th data-i18n="users.table.id">ID</th>
                                <th data-i18n="users.table.username">Username</th>
                                <th data-i18n="users.table.email">Email</th>
                                <th data-i18n="users.table.created_at">Created At</th>
                                <th data-i18n="users.table.status">Status</th>
                                <th data-i18n="users.table.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <span data-i18n="users.showing">Showing</span>
                        <span id="paginationInfo">0-0 of 0</span>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">👥</div>
                <h3 data-i18n="users.empty_title">No Users Found</h3>
                <p data-i18n="users.empty_message">Start by adding users</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="if(window.Users)Users.add()">
                    <i class="fas fa-plus"></i>
                    <span data-i18n="users.add_first">Add First User</span>
                </button>
                <?php endif; ?>
            </div>

            <!-- Error State -->
            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="users.error_title">Error Loading Data</h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary" data-i18n="users.retry">Retry</button>
            </div>

        </div>
    </div>

</div>

<!-- Page Permissions Data -->
<script id="pagePermissions" type="application/json">
<?= json_encode(['canCreate' => $canCreate, 'canEdit' => $canEdit, 'canDelete' => $canDelete]) ?>
</script>

<script>
window.USER_LANGUAGE = '<?= htmlspecialchars($userLanguage) ?>';
console.log('%c[Users] Page loaded using admin_context', 'color:#10b981;font-weight:bold');
console.log('User:', '<?= htmlspecialchars($username) ?>');
console.log('Can Create:', <?= $canCreate ? 'true' : 'false' ?>);
console.log('Can Edit:', <?= $canEdit ? 'true' : 'false' ?>);
console.log('Can Delete:', <?= $canDelete ? 'true' : 'false' ?>);
console.log('Is Super Admin:', <?= is_super_admin() ? 'true' : 'false' ?>);
console.log('Roles:', <?= json_encode(admin_roles()) ?>);
console.log('Permissions:', <?= json_encode(admin_permissions()) ?>);
</script>

<!-- Load JS if embedded -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/pages/users.js?v=<?= time() ?>"></script>
<script>
(function(){
    console.log('%c[Users] Embedded mode - initializing', 'color:#3b82f6;font-weight:bold');
    let attempts = 0;
    const check = setInterval(function(){
        attempts++;
        if (window.Users && typeof window.Users.init === 'function') {
            clearInterval(check);
            console.log('%c[Users] ✅ Module found!', 'color:#10b981;font-weight:bold');
            window.Users.init().then(()=>{
                console.log('%c[Users] ✅ Initialized successfully!', 'color:#10b981;font-weight:bold');
            }).catch(err=>{
                console.error('%c[Users] ❌ Init failed:', 'color:#ef4444;font-weight:bold', err);
            });
        } else if (attempts > 30) {
            clearInterval(check);
            console.error('%c[Users] ❌ Timeout after 30 attempts', 'color:#ef4444;font-weight:bold');
        }
    }, 200);
})();
</script>
<?php endif; ?>

<?php
// Load footer if standalone
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>