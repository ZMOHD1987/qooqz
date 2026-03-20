<?php
declare(strict_types=1);

/**
 * /admin/fragments/categories.php
 * Production Fragment - Uses admin_context when embedded/AJAX
 *
 * This fragment now attempts to load the first page of categories server-side
 * (using the same repository/service layer) and renders initial table rows so
 * the dashboard shows content immediately even if client-side fetch is delayed.
 *
 * It is defensive: if the DB or repository isn't available it falls back to an
 * empty table and logs errors to the server log.
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
    // Load admin_context in fragment mode (provides helpers like can(), admin_lang(), admin_user(), etc.)
    require_once __DIR__ . '/../includes/admin_context.php';
}

// ════════════════════════════════════════════════════════════
// PERMISSIONS / CONTEXT HELPERS
// ════════════════════════════════════════════════════════════
$canCreate = function_exists('can') ? can('manage_categories') : false;
$canEdit   = function_exists('can') ? can('manage_categories') : false;
$canDelete = function_exists('can') ? can('manage_categories') : false;

$lang = function_exists('admin_lang') ? admin_lang() : 'en';
$user = function_exists('admin_user') ? admin_user() : [];
$userLanguage = $user['preferred_language'] ?? $lang;
$csrf = function_exists('admin_csrf') ? admin_csrf() : '';
$username = function_exists('admin_username') ? admin_username() : ($user['username'] ?? 'guest');
$tenantId = $user['tenant_id'] ?? 1;

// ════════════════════════════════════════════════════════════
// Attempt to load initial categories server-side (first page)
// This is optional and defensive: if DB or services are not available we
// gracefully continue and let the client-side JS fetch data instead.
// ════════════════════════════════════════════════════════════
$initialPayload = [
    'items' => [],
    'meta'  => ['total' => 0, 'page' => 1, 'per_page' => 50]
];

try {
    // Attempt to get PDO from globals (same as API uses)
    $pdo = $GLOBALS['ADMIN_DB'] ?? $GLOBALS['DB'] ?? null;
    if ($pdo instanceof PDO) {
        // Require repository/service classes (no autoload assumed)
        require_once API_VERSION_PATH . '/models/categories/repositories/PdoCategoriesRepository.php';
        require_once API_VERSION_PATH . '/models/categories/validators/CategoriesValidator.php';
        require_once API_VERSION_PATH . '/models/categories/services/CategoriesService.php';

        $repo = new PdoCategoriesRepository($pdo);
        $validator = new CategoriesValidator();
        $service = new CategoriesService($repo, $validator);

        // Fetch first page (server-side): parentId=null, featuredOnly=false
        $items = $service->list((int)$tenantId, null, false, $userLanguage);
        if (is_array($items)) {
            $initialPayload['items'] = $items;
            $initialPayload['meta'] = [
                'total' => count($items),
                'page' => 1,
                'per_page' => 50
            ];
        }
    } else {
        error_log('categories fragment: PDO not available in $GLOBALS');
    }
} catch (Throwable $e) {
    // Log full error for debugging; do not expose stack in fragment output
    error_log('categories fragment failed to load initial data: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    // leave initialPayload empty; client JS will fetch later
}
?>

<!-- Force load CSS if embedded -->
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/categories.css?v=<?= time() ?>">
<?php endif; ?>

<!-- Page Meta -->
<meta data-page="categories"
      data-i18n-files="/languages/Categories/<?= htmlspecialchars($userLanguage, ENT_QUOTES) ?>.json"
      data-assets-css="/admin/assets/css/pages/categories.css"
      data-assets-js="/admin/assets/js/pages/categories.js">

<!-- Page Container -->
<div class="page-container" id="categoriesPageContainer">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="categories.title">Categories</h1>
            <p class="page-subtitle" data-i18n="categories.subtitle">Manage product and content categories</p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="catNewBtn" class="btn btn-primary" style="display:inline-flex!important;opacity:1!important;visibility:visible!important">
                <i class="fas fa-plus"></i>
                <span data-i18n="categories.add_new">Add Category</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container (hidden by default) -->
    <div id="catFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 id="catFormTitle" data-i18n="categories.form.add">Add Category</h3>
            <button id="catCloseForm" class="btn btn-sm btn-outline">×</button>
        </div>
        <div class="card-body">
            <form id="catForm" enctype="multipart/form-data">
                <input type="hidden" id="catFormAction" name="action" value="add">
                <input type="hidden" id="catEditingId" name="id" value="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                <input type="hidden" name="tenant_id" id="catTenantId" value="<?= htmlspecialchars($tenantId, ENT_QUOTES) ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="catName" data-i18n="categories.form.name">Name *</label>
                        <input type="text" id="catName" name="name" required placeholder="Category name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="catSlug" data-i18n="categories.form.slug">Slug *</label>
                        <input type="text" id="catSlug" name="slug" required placeholder="category-slug" class="form-control">
                        <small data-i18n="categories.form.slug_help">Lowercase letters, numbers, and hyphens only</small>
                    </div>
                    <div class="form-group">
                        <label for="catType" data-i18n="categories.form.type">Type *</label>
                        <select id="catType" name="type" class="form-control" required>
                            <option value="">Select type</option>
                            <option value="product">Product</option>
                            <option value="service">Service</option>
                            <option value="blog">Blog</option>
                            <option value="page">Page</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:1">
                        <label for="catParentId" data-i18n="categories.form.parent">Parent</label>
                        <select id="catParentId" name="parent_id" class="form-control">
                            <option value="">-- Root --</option>
                        </select>
                    </div>
                    <div class="form-group" style="width:150px">
                        <label for="catSortOrder" data-i18n="categories.form.sort_order">Sort Order</label>
                        <input type="number" id="catSortOrder" name="sort_order" class="form-control" value="0" min="0">
                    </div>
                    <div class="form-group" style="width:160px">
                        <label for="catIsActive" data-i18n="categories.form.status">Status</label>
                        <select id="catIsActive" name="is_active" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group" style="width:160px">
                        <label for="catIsFeatured" data-i18n="categories.form.featured">Featured</label>
                        <select id="catIsFeatured" name="is_featured" class="form-control">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:1">
                        <label for="catDescription" data-i18n="categories.form.description">Description</label>
                        <textarea id="catDescription" name="description" rows="4" class="form-control"></textarea>
                    </div>
                    <div class="form-group" style="width:220px">
                        <label data-i18n="categories.form.image">Image</label>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <img id="catImagePreview" src="/assets/images/no-image.png" alt="Preview" style="width:80px;height:80px;object-fit:cover;border:1px solid #ccc;border-radius:6px;">
                            <div>
                                <button type="button" id="catSelectImageBtn" class="btn btn-secondary">Change</button>
                                <input type="hidden" id="catImageId" name="image_id" value="">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Translations -->
                <div style="margin-top:12px;border-top:1px solid #eee;padding-top:12px;">
                    <h4 data-i18n="categories.form.translations">Translations</h4>
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
                        <select id="catLangSelect" class="form-control" style="min-width:200px;"></select>
                        <button type="button" id="catAddLangBtn" class="btn btn-secondary">Add language</button>
                    </div>
                    <div id="catTranslations" style="margin-top:8px;"></div>
                </div>

                <div class="form-actions" style="margin-top:12px;">
                    <button type="submit" id="catSaveBtn" class="btn btn-primary" data-i18n="categories.form.save">Save</button>
                    <button type="button" id="catCancelBtn" class="btn btn-outline" data-i18n="categories.form.cancel">Cancel</button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="catDeleteBtn" class="btn btn-danger" style="display:none" data-i18n="categories.form.delete">Delete</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card filter-card" style="margin-top:12px;">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="catSearchInput" data-i18n="categories.search">Search</label>
                    <input type="text" id="catSearchInput" class="form-control" placeholder="Search name, slug, description...">
                </div>

                <div class="filter-group">
                    <label for="catTypeFilter" data-i18n="categories.type">Type</label>
                    <select id="catTypeFilter" class="form-control">
                        <option value="">All types</option>
                        <option value="product">Product</option>
                        <option value="service">Service</option>
                        <option value="blog">Blog</option>
                        <option value="page">Page</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="catParentFilter" data-i18n="categories.parent">Parent</label>
                    <select id="catParentFilter" class="form-control">
                        <option value="">All parents</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="catStatusFilter" data-i18n="categories.status">Status</label>
                    <select id="catStatusFilter" class="form-control">
                        <option value="">All</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="catFeaturedFilter" data-i18n="categories.featured">Featured</label>
                    <select id="catFeaturedFilter" class="form-control">
                        <option value="">All</option>
                        <option value="1">Featured</option>
                        <option value="0">Not Featured</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button id="catApplyFiltersBtn" class="btn btn-secondary" data-i18n="categories.apply_filters">Apply</button>
                    <button id="catResetFiltersBtn" class="btn btn-outline" data-i18n="categories.reset_filters">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card table-card" style="margin-top:12px;">
        <div class="card-body">

            <!-- Loading State -->
            <div id="catTableLoading" class="loading-state" <?= empty($initialPayload['items']) ? '' : 'style="display:none"' ?>>
                <div class="spinner"></div>
                <p data-i18n="categories.loading">Loading...</p>
            </div>

            <!-- Table Container -->
            <div id="catTableContainer" style="<?= empty($initialPayload['items']) ? 'display:none' : '' ?>">
                <div class="table-responsive">
                    <table class="data-table" id="categoriesTable">
                        <thead>
                            <tr>
                                <th data-i18n="categories.table.id">ID</th>
                                <th data-i18n="categories.table.image">Image</th>
                                <th data-i18n="categories.table.name">Name</th>
                                <th data-i18n="categories.table.slug">Slug</th>
                                <th data-i18n="categories.table.parent">Parent</th>
                                <th data-i18n="categories.table.type">Type</th>
                                <th data-i18n="categories.table.sort">Sort</th>
                                <th data-i18n="categories.table.status">Status</th>
                                <th data-i18n="categories.table.featured">Featured</th>
                                <th data-i18n="categories.table.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="catTableBody">
                        <?php
                        // Render initial rows server-side if available
                        if (!empty($initialPayload['items']) && is_array($initialPayload['items'])):
                            foreach ($initialPayload['items'] as $cat):
                                $id = htmlspecialchars($cat['id'] ?? '', ENT_QUOTES);
                                $image = htmlspecialchars($cat['image_url'] ?? ($cat['image'] ?? ''), ENT_QUOTES);
                                $name = htmlspecialchars($cat['name'] ?? '', ENT_QUOTES);
                                $slug = htmlspecialchars($cat['slug'] ?? '', ENT_QUOTES);
                                $parent = htmlspecialchars($cat['parent_name'] ?? ($cat['parent_id'] ? $cat['parent_id'] : 'Root'), ENT_QUOTES);
                                $type = htmlspecialchars($cat['type'] ?? 'product', ENT_QUOTES);
                                $sort = htmlspecialchars($cat['sort_order'] ?? 0, ENT_QUOTES);
                                $is_active = !empty($cat['is_active']);
                                $is_featured = !empty($cat['is_featured']);
                                ?>
                                <tr>
                                    <td><?= $id ?></td>
                                    <td><?= $image ? "<img src=\"{$image}\" width=\"50\" height=\"50\" style=\"object-fit:cover;border-radius:4px\">" : '-' ?></td>
                                    <td><strong><?= $name ?></strong></td>
                                    <td><?= $slug ?></td>
                                    <td><?= $parent ?></td>
                                    <td><?= $type ?></td>
                                    <td><?= $sort ?></td>
                                    <td><span class="badge <?= $is_active ? 'badge-active' : 'badge-inactive' ?>"><?= $is_active ? 'Active' : 'Inactive' ?></span></td>
                                    <td><?= $is_featured ? 'Yes' : 'No' ?></td>
                                    <td class="table-actions">
                                        <?= $canEdit ? "<button class=\"btn btn-sm btn-icon\" data-action=\"edit\" data-slug=\"{$slug}\"><i class=\"fas fa-edit\"></i></button>" : '' ?>
                                        <?= $canDelete ? "<button class=\"btn btn-sm btn-icon btn-danger\" data-action=\"delete\" data-slug=\"{$slug}\"><i class=\"fas fa-trash\"></i></button>" : '' ?>
                                    </td>
                                </tr>
                            <?php
                            endforeach;
                        endif;
                        ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination-wrapper" style="margin-top:12px;">
                    <div class="pagination-info">
                        <span data-i18n="categories.showing">Showing</span>
                        <span id="catPaginationInfo"><?= (int)($initialPayload['meta']['total'] ?? 0) ? '1-' . min(50, (int)$initialPayload['meta']['total']) . ' of ' . (int)$initialPayload['meta']['total'] : '0-0 of 0' ?></span>
                    </div>
                    <div class="pagination" id="catPagination"></div>
                </div>
            </div>

            <!-- Empty State -->
            <div id="catEmptyState" class="empty-state" style="<?= empty($initialPayload['items']) ? '' : 'display:none' ?>">
                <div class="empty-icon">📂</div>
                <h3 data-i18n="categories.empty_title">No Categories Found</h3>
                <p data-i18n="categories.empty_message">Start by adding categories</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="if(window.Categories)Categories.add()">
                    <i class="fas fa-plus"></i>
                    <span data-i18n="categories.add_first">Add First Category</span>
                </button>
                <?php endif; ?>
            </div>

            <!-- Error State -->
            <div id="catErrorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="categories.error_title">Error Loading Data</h3>
                <p id="catErrorMessage"></p>
                <button id="catRetryBtn" class="btn btn-secondary" data-i18n="categories.retry">Retry</button>
            </div>

        </div>
    </div>

</div>

<!-- Page Permissions Data -->
<script id="pagePermissions" type="application/json">
<?= json_encode(['canCreate' => $canCreate, 'canEdit' => $canEdit, 'canDelete' => $canDelete]) ?>
</script>

<!-- Initial categories payload for client-side JS to consume (if needed) -->
<script id="CATEGORIES_INITIAL_PAYLOAD" type="application/json">
<?= json_encode($initialPayload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG) ?>
</script>

<script>
window.CATEGORIES_USER_LANG = '<?= htmlspecialchars($userLanguage, ENT_QUOTES) ?>';
console.log('%c[Categories] Page loaded using admin_context', 'color:#10b981;font-weight:bold');
console.log('User:', '<?= htmlspecialchars($username, ENT_QUOTES) ?>');
console.log('Can Create:', <?= $canCreate ? 'true' : 'false' ?>);
console.log('Can Edit:', <?= $canEdit ? 'true' : 'false' ?>);
console.log('Can Delete:', <?= $canDelete ? 'true' : 'false' ?>);
</script>

<!-- Load JS if embedded -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/pages/categories.js?v=<?= time() ?>"></script>
<script>
(function(){
    console.log('%c[Categories] Embedded mode - initializing', 'color:#3b82f6;font-weight:bold');
    let attempts = 0;
    const check = setInterval(function(){
        attempts++;
        if (window.Categories && typeof window.Categories.init === 'function') {
            clearInterval(check);
            console.log('%c[Categories] ✅ Module found!', 'color:#10b981;font-weight:bold');

            // Provide initial payload to client module if it expects it
            try {
                const script = document.getElementById('CATEGORIES_INITIAL_PAYLOAD');
                if (script) {
                    window.CATEGORIES_INITIAL = JSON.parse(script.textContent || '{}');
                }
            } catch (e) {
                console.warn('Could not parse CATEGORIES_INITIAL_PAYLOAD', e);
            }

            window.Categories.init().then(()=>{
                console.log('%c[Categories] ✅ Initialized successfully!', 'color:#10b981;font-weight:bold');
            }).catch(err=>{
                console.error('%c[Categories] ❌ Init failed:', 'color:#ef4444;font-weight:bold', err);
            });
        } else if (attempts > 30) {
            clearInterval(check);
            console.error('%c[Categories] ❌ Timeout after 30 attempts', 'color:#ef4444;font-weight:bold');
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