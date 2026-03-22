<?php
declare(strict_types=1);

/**
 * /admin/fragments/returns.php
 * Admin fragment – Return Requests Management
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

$canCreate = can('returns.manage') || can('returns.create') || is_super_admin();
$canEdit   = can('returns.manage') || can('returns.edit')   || is_super_admin();
$canDelete = can('returns.manage') || can('returns.delete') || is_super_admin();
$canView   = $canCreate || $canEdit || $canDelete || can('returns.view') || is_super_admin();

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
if (!function_exists('renderReturnsFragmentThemeVars')) {
    function renderReturnsFragmentThemeVars(array $theme): void {
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
<style id="db-theme-vars-returns">
<?php renderReturnsFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
</style>
<link rel="stylesheet" href="/admin/assets/css/pages/returns.css?v=<?= time() ?>">

<meta data-page="returns"
      data-assets-css="/admin/assets/css/pages/returns.css"
      data-i18n-files="/languages/Returns/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="returnsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="returns.title">Return Requests</h1>
            <p class="page-subtitle" data-i18n="returns.subtitle">Manage customer return requests</p>
        </div>
        <?php if ($canCreate): ?>
        <div class="page-header-actions">
            <button id="ret-btnAdd" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="returns.add_new">New Return</span>
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Form Container -->
    <div id="ret-formContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="ret-formTitle" data-i18n="form.add_title">New Return Request</h3>
            <button type="button" class="btn btn-sm btn-outline" id="ret-btnCloseForm">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="ret-form" novalidate>
                <input type="hidden" id="ret-formId"    name="id">
                <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="tenant_id"   value="<?= $tenantId ?>">

                <!-- Tabs -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="details">
                        <i class="fas fa-info-circle"></i>
                        <span data-i18n="form.tabs.details">Details</span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="items">
                        <i class="fas fa-box"></i>
                        <span data-i18n="form.tabs.items">Items</span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="history">
                        <i class="fas fa-history"></i>
                        <span data-i18n="form.tabs.history">Status History</span>
                    </button>
                </div>

                <!-- Tab: Details -->
                <div class="tab-content active" id="ret-tab-details" style="display:block">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ret-returnNumber" data-i18n="form.fields.return_number.label">Return Number</label>
                            <input type="text" id="ret-returnNumber" name="return_number" class="form-control" readonly
                                   placeholder="Auto-generated">
                        </div>
                        <div class="form-group">
                            <label for="ret-status" data-i18n="form.fields.status.label">Status</label>
                            <select id="ret-status" name="status" class="form-control">
                                <option value="pending"    data-i18n="status.pending">Pending</option>
                                <option value="approved"   data-i18n="status.approved">Approved</option>
                                <option value="rejected"   data-i18n="status.rejected">Rejected</option>
                                <option value="processing" data-i18n="status.processing">Processing</option>
                                <option value="completed"  data-i18n="status.completed">Completed</option>
                                <option value="cancelled"  data-i18n="status.cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="ret-reason" data-i18n="form.fields.reason.label">Reason</label>
                        <textarea id="ret-reason" name="reason" class="form-control" rows="3"
                                  data-i18n-placeholder="form.fields.reason.placeholder"
                                  placeholder="Reason for return request"></textarea>
                    </div>

                    <?php if ($canEdit): ?>
                    <div class="form-group">
                        <label for="ret-adminNotes" data-i18n="form.fields.admin_notes.label">Admin Notes</label>
                        <textarea id="ret-adminNotes" name="admin_notes" class="form-control" rows="3"
                                  data-i18n-placeholder="form.fields.admin_notes.placeholder"
                                  placeholder="Internal notes (not visible to customer)"></textarea>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Tab: Items -->
                <div class="tab-content" id="ret-tab-items" style="display:none">
                    <div id="ret-itemsList">
                        <p style="color:var(--text-secondary);text-align:center;padding:20px" data-i18n="items.empty">
                            No items in this return
                        </p>
                    </div>
                </div>

                <!-- Tab: History -->
                <div class="tab-content" id="ret-tab-history" style="display:none">
                    <div id="ret-historyList">
                        <p style="color:var(--text-secondary);text-align:center;padding:20px" data-i18n="history.empty">
                            No status history yet
                        </p>
                    </div>
                </div>

                <div class="form-actions">
                    <?php if ($canEdit): ?>
                    <button type="submit" class="btn btn-primary" id="ret-btnSave">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save">Save Return</span>
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline" id="ret-btnCancelForm" data-i18n="form.buttons.cancel">
                        Cancel
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="ret-btnDelete" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash"></i>
                        <span data-i18n="form.buttons.delete">Delete Return</span>
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
                    <label for="ret-searchInput" data-i18n="filters.search">Search</label>
                    <input type="text" id="ret-searchInput" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="Search by return number...">
                </div>
                <div class="filter-group">
                    <label for="ret-statusFilter" data-i18n="filters.status">Status</label>
                    <select id="ret-statusFilter" class="form-control">
                        <option value="" data-i18n="filters.all_statuses">All Statuses</option>
                        <option value="pending"    data-i18n="status.pending">Pending</option>
                        <option value="approved"   data-i18n="status.approved">Approved</option>
                        <option value="rejected"   data-i18n="status.rejected">Rejected</option>
                        <option value="processing" data-i18n="status.processing">Processing</option>
                        <option value="completed"  data-i18n="status.completed">Completed</option>
                        <option value="cancelled"  data-i18n="status.cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="ret-btnApplyFilters" class="btn btn-secondary" data-i18n="filters.apply">Apply</button>
                    <button id="ret-btnResetFilters" class="btn btn-outline"   data-i18n="filters.reset">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <div id="ret-tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="returns.loading">Loading returns...</p>
            </div>
            <div id="ret-tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="ret-table">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id">ID</th>
                                <th data-i18n="table.headers.return_number">Return #</th>
                                <th data-i18n="table.headers.order">Order</th>
                                <th data-i18n="table.headers.customer">Customer</th>
                                <th data-i18n="table.headers.status">Status</th>
                                <th data-i18n="table.headers.reason">Reason</th>
                                <th data-i18n="table.headers.requested_at">Requested</th>
                                <th data-i18n="table.headers.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ret-tableBody"></tbody>
                    </table>
                </div>
                <div class="pagination-wrapper">
                    <div class="pagination-info"><span id="ret-paginationInfo">0-0 / 0</span></div>
                    <div class="pagination" id="ret-pagination"></div>
                </div>
            </div>
            <div id="ret-emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">🔄</div>
                <h3 data-i18n="table.empty.title">No Return Requests Found</h3>
                <p data-i18n="table.empty.message">There are no return requests matching your criteria.</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" id="ret-btnAddFirst" onclick="document.getElementById('ret-btnAdd')?.click()"
                        data-i18n="table.empty.add_first">
                    Create First Return
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
window.APP_CONFIG    = { API_BASE: '<?= $apiBase ?>', TENANT_ID: <?= $tenantId ?>, CSRF_TOKEN: '<?= addslashes($csrf) ?>' };
window.USER_LANGUAGE = '<?= addslashes($lang) ?>';
window.ADMIN_LANG    = window.ADMIN_LANG || '<?= addslashes($lang) ?>';
window.PAGE_PERMISSIONS = <?= json_encode(['canCreate' => $canCreate, 'canEdit' => $canEdit, 'canDelete' => $canDelete]) ?>;
window.RETURNS_CONFIG = {
    apiUrl:     '<?= $apiBase ?>/returns',
    itemsApi:   '<?= $apiBase ?>/return_items',
    historyApi: '<?= $apiBase ?>/return_status_history',
    ordersApi:  '<?= $apiBase ?>/orders',
    usersApi:   '<?= $apiBase ?>/users',
    lang:       '<?= addslashes($lang) ?>',
    itemsPerPage: 20,
    tenantId:   <?= $tenantId ?>
};
</script>

<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/returns.js?v=<?= time() ?>"></script>
<script>
(function () {
    var initialized = false;
    var poll;

    function cleanup() {
        clearInterval(poll);
        window.removeEventListener('admin:i18n:applied', tryInit);
    }

    // Call init() only after BOTH translations and the Returns module are ready.
    function tryInit() {
        if (initialized) return;
        if (!window.TRANSLATIONS) return;
        if (!window.Returns || typeof window.Returns.init !== 'function') return;
        initialized = true;
        cleanup();
        window.Returns.init();
    }

    // Primary trigger: admin:i18n:applied fires after applyTranslations() sets
    // window.TRANSLATIONS and translates all [data-i18n] elements in the fragment.
    window.addEventListener('admin:i18n:applied', tryInit);

    // Also try immediately – covers re-visits where TRANSLATIONS is already set.
    tryInit();

    // Fallback poll covers cases where admin:i18n:applied already fired before
    // this script registered, and when returns.js loads after the event fires.
    // Runs for up to 6 s (60 × 100 ms).
    var pollCount = 0;
    poll = setInterval(function () {
        pollCount++;
        tryInit();
        if (initialized || pollCount >= 60) {
            cleanup();
            if (!initialized) {
                console.warn('[Returns] init timed out');
            }
        }
    }, 100);
})();
</script>
<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>