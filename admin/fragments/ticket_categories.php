<?php
declare(strict_types=1);

/**
 * /admin/fragments/ticket_categories.php
 * Admin fragment – Ticket Categories Management
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT / HEADER
// ════════════════════════════════════════════════════════════
if ($isFragment) {
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
$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

$canCreate = can('tickets.manage') || can('tickets.create') || is_super_admin();
$canEdit   = can('tickets.manage') || can('tickets.edit')   || is_super_admin();
$canDelete = can('tickets.manage') || can('tickets.delete') || is_super_admin();
$canView   = $canCreate || $canEdit || $canDelete || can('tickets.view') || is_super_admin();

if (!$canView) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied');
}

// ════════════════════════════════════════════════════════════
// DB-DRIVEN CSS VARS HELPER
// ════════════════════════════════════════════════════════════
if (!function_exists('renderTCFragmentThemeVars')) {
    function renderTCFragmentThemeVars(array $theme): void {
        echo ':root {' . PHP_EOL;
        foreach ($theme['color_settings'] ?? [] as $c) {
            if (empty($c['setting_key']) || !isset($c['color_value'])) continue;
            $k = htmlspecialchars($c['setting_key'], ENT_QUOTES);
            $v = htmlspecialchars($c['color_value'], ENT_QUOTES);
            echo "    --{$k}: {$v};" . PHP_EOL;
        }
        echo '}' . PHP_EOL;
    }
}

$apiBase = '/api';
?>
<style id="db-theme-vars-ticket-categories">
<?php renderTCFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
</style>
<link rel="stylesheet" href="/admin/assets/css/pages/ticket_categories.css?v=<?= time() ?>">

<meta data-page="ticket_categories"
      data-assets-css="/admin/assets/css/pages/ticket_categories.css"
      data-i18n-files="/languages/ticket_categories/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="ticketCategoriesPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="ticket_categories.title">Ticket Categories</h1>
            <p class="page-subtitle" data-i18n="ticket_categories.subtitle">Manage support ticket categories</p>
        </div>
        <?php if ($canCreate): ?>
        <div class="page-header-actions">
            <button id="btnAddTicketCategory" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="ticket_categories.add_new">Add Category</span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form Container -->
    <div id="tcFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="tcFormTitle" data-i18n="form.add_title">New Ticket Category</h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseTCForm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="ticketCategoryForm" novalidate>
                <input type="hidden" id="tcFormId"       name="id">
                <input type="hidden" name="csrf_token"   value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="tenant_id"    value="<?= $tenantId ?>">

                <div class="form-row">
                    <div class="form-group" style="flex:2">
                        <label for="tcName" class="required" data-i18n="form.fields.name.label">Category Name</label>
                        <input type="text" id="tcName" name="name" class="form-control" required
                               data-i18n-placeholder="form.fields.name.placeholder" placeholder="Enter category name">
                    </div>
                    <div class="form-group">
                        <label for="tcLang" data-i18n="form.fields.language.label">Language</label>
                        <select id="tcLang" name="language_code" class="form-control">
                            <option value="en" data-i18n="form.fields.language.en">English</option>
                            <option value="ar" data-i18n="form.fields.language.ar">Arabic</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="tcDescription" data-i18n="form.fields.description.label">Description</label>
                    <textarea id="tcDescription" name="description" class="form-control" rows="3"
                              data-i18n-placeholder="form.fields.description.placeholder"
                              placeholder="Optional description"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="tcParent" data-i18n="form.fields.parent.label">Parent Category</label>
                        <select id="tcParent" name="parent_id" class="form-control">
                            <option value="" data-i18n="form.fields.parent.none">— None (Top Level) —</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tcPriority" data-i18n="form.fields.priority_level.label">Priority Level</label>
                        <select id="tcPriority" name="priority_level" class="form-control">
                            <option value="1">1 — Highest</option>
                            <option value="2">2</option>
                            <option value="3" selected>3 — Normal</option>
                            <option value="4">4</option>
                            <option value="5">5 — Lowest</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tcStatus" data-i18n="form.fields.status.label">Status</label>
                        <select id="tcStatus" name="is_active" class="form-control">
                            <option value="1" data-i18n="table.active">Active</option>
                            <option value="0" data-i18n="table.inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Translations Section -->
                <div class="translations-section" style="margin-top:20px; border-top:1px solid var(--border-color); padding-top:15px;">
                    <h4 data-i18n="form.translations_heading" style="margin-bottom:12px; font-size:0.95rem; color:var(--text-secondary)">Translations</h4>
                    <div id="tcTranslationsContainer">
                        <!-- Dynamic translation rows added by JS -->
                    </div>
                    <button type="button" id="btnAddTranslation" class="btn btn-sm btn-outline" style="margin-top:8px">
                        <i class="fas fa-plus"></i> Add Translation
                    </button>
                </div>

                <div class="form-actions" style="margin-top:20px">
                    <button type="submit" class="btn btn-primary" id="btnSaveTCForm">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save">Save Category</span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelTCForm" data-i18n="form.buttons.cancel">Cancel</button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteTC" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash"></i> <span data-i18n="form.buttons.delete">Delete Category</span>
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
                    <label for="tcSearch" data-i18n="filters.search">Search</label>
                    <input type="text" id="tcSearch" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder" placeholder="Search by name...">
                </div>
                <div class="filter-group">
                    <label for="tcStatusFilter" data-i18n="filters.status">Status</label>
                    <select id="tcStatusFilter" class="form-control">
                        <option value="" data-i18n="filters.all_statuses">All Statuses</option>
                        <option value="1" data-i18n="filters.active">Active</option>
                        <option value="0" data-i18n="filters.inactive">Inactive</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="btnTCApplyFilters" class="btn btn-secondary" data-i18n="filters.apply">Apply</button>
                    <button id="btnTCResetFilters" class="btn btn-outline"   data-i18n="filters.reset">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <div id="tcTableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="ticket_categories.loading">Loading categories...</p>
            </div>
            <div id="tcTableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="ticketCategoriesTable">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id">ID</th>
                                <th data-i18n="table.headers.name">Name</th>
                                <th data-i18n="table.headers.description">Description</th>
                                <th data-i18n="table.headers.parent">Parent</th>
                                <th data-i18n="table.headers.priority">Priority</th>
                                <th data-i18n="table.headers.status">Status</th>
                                <th data-i18n="table.headers.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tcTableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <div class="pagination-info"><span id="tcPaginationInfo">0-0 of 0</span></div>
                    <div class="pagination" id="tcPagination"></div>
                </div>
            </div>
            <div id="tcEmptyState" class="empty-state" style="display:none">
                <div class="empty-icon">🏷️</div>
                <h3 data-i18n="table.empty.title">No Categories Found</h3>
                <p data-i18n="table.empty.message">No ticket categories have been created yet.</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" id="btnAddFirstTCCategory" data-i18n="table.empty.add_first">
                    Create First Category
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
window.APP_CONFIG       = { API_BASE: '<?= $apiBase ?>', TENANT_ID: <?= $tenantId ?>, CSRF_TOKEN: '<?= addslashes($csrf) ?>' };
window.USER_LANGUAGE    = '<?= addslashes($lang) ?>';
window.ADMIN_LANG       = window.ADMIN_LANG || '<?= addslashes($lang) ?>';
window.PAGE_PERMISSIONS = <?= json_encode(['canCreate' => $canCreate, 'canEdit' => $canEdit, 'canDelete' => $canDelete]) ?>;
window.TC_CONFIG = {
    apiUrl:  '<?= $apiBase ?>/ticket_categories',
    lang:    '<?= addslashes($lang) ?>',
    itemsPerPage: 25,
    tenantId: <?= $tenantId ?>
};
</script>

<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/ticket_categories.js?v=<?= time() ?>"></script>
<script>
(function () {
    var attempts = 0;
    var interval = setInterval(function () {
        attempts++;
        if (window.TicketCategories && typeof window.TicketCategories.init === 'function') {
            clearInterval(interval);
            window.TicketCategories.init();
        } else if (attempts > 50) {
            clearInterval(interval);
        }
    }, 100);
})();
</script>
<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>
