<?php
declare(strict_types=1);

$isFragment = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
              isset($_GET['embedded']) || 
              isset($_POST['embedded']);

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

$payload = $GLOBALS['ADMIN_UI'] ?? [];
$user = $payload['user'] ?? [];
$tenantId = (int)($user['tenant_id'] ?? ($_GET['tenant_id'] ?? 1));
$lang = $payload['lang'] ?? ($user['preferred_language'] ?? 'en');
$dir = ($lang === 'ar') ? 'rtl' : 'ltr';
$apiBase = $payload['api_base'] ?? '/api';
$csrf = $payload['csrf_token'] ?? bin2hex(random_bytes(32));

// Permissions
$permissions = $user['permissions'] ?? [];
$roles = $user['roles'] ?? [];
$isSuperAdmin = in_array('super_admin', $roles, true);
$canCreate = $isSuperAdmin || in_array('manage_tenant_categories', $permissions, true);
$canEdit = $canCreate;
$canDelete = $canCreate;
?>

<!-- Notifications Container -->
<div id="notificationsContainer" class="notifications-container"></div>

<div class="page-container" id="tenantCategoriesPage" dir="<?= htmlspecialchars($dir) ?>">
    
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="page_title">Tenant Categories</h1>
            <p class="page-subtitle" data-i18n="page_subtitle">Manage categories assigned to tenants</p>
        </div>
        <?php if ($canCreate): ?>
        <div class="page-header-actions">
            <button id="btnAddTenantCategory" class="btn btn-primary" data-i18n="add_button">
                <i class="fas fa-plus"></i> Add Tenant Category
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form Container -->
    <div id="tenantCategoryFormContainer" class="card form-card" style="display:none;">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form_add_title">Add Tenant Category</h3>
            <button type="button" id="btnCloseTenantCategoryForm" class="btn btn-sm btn-outline" data-i18n="close_button">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="tenantCategoryForm" novalidate>
                <input type="hidden" name="id" id="tenantCategoryId">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="tenantCategoryTenantId" class="required" data-i18n="label_tenant">Tenant</label>
                        <?php if ($isSuperAdmin): ?>
                        <input type="text" id="tenantCategoryTenantId" name="tenant_display" 
                               class="form-control" list="tenantsList" data-i18n-placeholder="placeholder_tenant" placeholder="Select or search tenant" autocomplete="off">
                        <datalist id="tenantsList"></datalist>
                        <input type="hidden" name="tenant_id" id="tenantCategoryTenantIdHidden">
                        <?php else: ?>
                        <input type="hidden" id="tenantCategoryTenantIdHidden" name="tenant_id" 
                               value="<?= $tenantId ?>">
                        <input type="text" class="form-control" value="Current Tenant" readonly>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="tenantCategoryCategoryId" class="required" data-i18n="label_category">Category</label>
                        <input type="text" id="tenantCategoryCategoryId" name="category_display" 
                               class="form-control" list="categoriesList" data-i18n-placeholder="placeholder_category" placeholder="Select or search category" autocomplete="off" required>
                        <datalist id="categoriesList"></datalist>
                        <input type="hidden" name="category_id" id="tenantCategoryCategoryIdHidden">
                    </div>
                    <div class="form-group">
                        <label for="tenantCategorySortOrder" data-i18n="label_sort_order">Sort Order</label>
                        <input type="number" id="tenantCategorySortOrder" name="sort_order" 
                               class="form-control" value="0">
                    </div>
                    <?php if ($isSuperAdmin): ?>
                    <div class="form-group">
                        <label for="tenantCategoryIsActive" data-i18n="label_status">Status</label>
                        <select id="tenantCategoryIsActive" name="is_active" class="form-control">
                            <option value="1" data-i18n="active_option">Active</option>
                            <option value="0" data-i18n="inactive_option">Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSaveTenantCategory" data-i18n="save_button">
                        <i class="fas fa-save"></i> Save
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelTenantCategoryForm" data-i18n="cancel_button">
                        Cancel
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" class="btn btn-danger" id="btnDeleteTenantCategory" style="display:none" data-i18n="delete_button">
                        <i class="fas fa-trash"></i> Delete
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
                <?php if ($isSuperAdmin): ?>
                <div class="filter-group">
                    <label for="tenantCategoryFilterTenant" data-i18n="filter_tenant_label">Tenant</label>
                    <input type="text" id="tenantCategoryFilterTenant" class="form-control" 
                           list="filterTenantsList" data-i18n-placeholder="placeholder_filter_tenant" placeholder="All Tenants" autocomplete="off">
                    <datalist id="filterTenantsList"></datalist>
                    <input type="hidden" id="tenantCategoryFilterTenantHidden">
                </div>
                <?php endif; ?>
                <div class="filter-group">
                    <label for="tenantCategoryFilterCategory" data-i18n="filter_category_label">Category</label>
                    <input type="text" id="tenantCategoryFilterCategory" class="form-control" 
                           list="filterCategoriesList" data-i18n-placeholder="placeholder_filter_category" placeholder="All Categories" autocomplete="off">
                    <datalist id="filterCategoriesList"></datalist>
                    <input type="hidden" id="tenantCategoryFilterCategoryHidden">
                </div>
                <?php if ($isSuperAdmin): ?>
                <div class="filter-group">
                    <label for="tenantCategoryFilterStatus" data-i18n="filter_status_label">Status</label>
                    <select id="tenantCategoryFilterStatus" class="form-control">
                        <option value="" data-i18n="all_status">All Status</option>
                        <option value="1" data-i18n="active_option">Active</option>
                        <option value="0" data-i18n="inactive_option">Inactive</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="filter-actions">
                    <button id="btnApplyTenantCategoryFilters" class="btn btn-secondary" data-i18n="filter_apply">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <button id="btnResetTenantCategoryFilters" class="btn btn-outline" data-i18n="filter_reset">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Count -->
    <div id="tenantCategoryResultsCount" class="results-count" style="display:none;">
        <i class="fas fa-list"></i> 
        <span id="tenantCategoryResultsCountText"></span>
    </div>

    <!-- Table Container -->
    <div class="card table-card">
        <div class="card-body">
            <!-- Loading State -->
            <div id="tenantCategoryTableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="loading">Loading tenant categories...</p>
            </div>

            <!-- Table -->
            <div id="tenantCategoryTableContainer" style="display:none;">
                <div class="table-responsive">
                    <table class="data-table" id="tenantCategoriesTable">
                        <thead>
                            <tr>
                                <th data-i18n="table_id">ID</th>
                                <?php if ($isSuperAdmin): ?><th data-i18n="table_tenant_id">Tenant ID</th><?php endif; ?>
                                <th data-i18n="table_tenant_name">Tenant Name</th>
                                <th data-i18n="table_category_id">Category ID</th>
                                <th data-i18n="table_category_name">Category Name</th>
                                <th data-i18n="table_sort_order">Sort Order</th>
                                <?php if ($isSuperAdmin): ?><th data-i18n="table_status">Status</th><?php endif; ?>
                                <th data-i18n="table_created_at">Created At</th>
                                <th data-i18n="table_actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tenantCategoryTableBody"></tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <span id="tenantCategoryPaginationInfo" data-i18n="showing_results">Showing 0 to 0 of 0 results</span>
                    </div>
                    <div class="pagination-buttons">
                        <button id="btnPrevTenantCategoryPage" class="btn btn-outline" disabled data-i18n="previous">Previous</button>
                        <button id="btnNextTenantCategoryPage" class="btn btn-outline" disabled data-i18n="next">Next</button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div id="tenantCategoryEmptyState" class="empty-state" style="display:none;">
                <div class="empty-icon">📋</div>
                <h3 data-i18n="empty_title">No Tenant Categories Found</h3>
                <p data-i18n="empty_description">Start by adding tenant categories</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="TenantCategories.add()" data-i18n="empty_add">
                    <i class="fas fa-plus"></i> Add First Tenant Category
                </button>
                <?php endif; ?>
            </div>

            <!-- Error State -->
            <div id="tenantCategoryErrorState" class="error-state" style="display:none;">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="error_title">Error Loading Data</h3>
                <p id="tenantCategoryErrorMessage" data-i18n="error_message"></p>
                <button id="btnRetryTenantCategories" class="btn btn-secondary" data-i18n="retry_button">
                    <i class="fas fa-redo"></i> Retry
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.TENANT_CATEGORIES_CONFIG = {
    apiUrl: '<?= $apiBase ?>/categories-tenants',
    tenantsUrl: '<?= $apiBase ?>/tenants',
    categoriesUrl: '<?= $apiBase ?>/categories',
    translationsUrl: '/languages/Tenant_categories/<?= addslashes($lang) ?>.json',
    csrfToken: '<?= addslashes($csrf) ?>',
    tenantId: <?= $tenantId ?>,
    lang: '<?= addslashes($lang) ?>',
    isSuperAdmin: <?= $isSuperAdmin ? 'true' : 'false' ?>,
    permissions: {
        canCreate: <?= $canCreate ? 'true' : 'false' ?>,
        canEdit: <?= $canEdit ? 'true' : 'false' ?>,
        canDelete: <?= $canDelete ? 'true' : 'false' ?>
    }
};
</script>

<!-- Load AdminFramework if embedded -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js"></script>
<?php endif; ?>

<script src="/admin/assets/js/pages/tenant_categories.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>