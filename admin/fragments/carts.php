<?php
declare(strict_types=1);

/**
 * /admin/fragments/carts.php
 * Complete Cart Management Dashboard
 *
 * ✅ Carts listing with filters and pagination
 * ✅ Cart items management (view items per cart)
 * ✅ Cart events / activity log
 * ✅ Edit cart status, delete carts
 * ✅ RTL / LTR support
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
$dir = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
$csrf = admin_csrf();
$tenantId = admin_tenant_id();
$userId = admin_user_id();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════
$canManageCarts = can('carts.manage') || can('carts.view');
$canViewAll = can_view_all('carts');
$canViewOwn = can_view_own('carts');
$canViewTenant = can_view_tenant('carts');
$canEditAll = can_edit_all('carts');
$canEditOwn = can_edit_own('carts');
$canDeleteAll = can_delete_all('carts');
$canDeleteOwn = can_delete_own('carts');

$canView = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit = $canEditAll || $canEditOwn || $canManageCarts;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManageCarts;

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view carts');
    }
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
if (!function_exists('__t')) {
    function __t($key, $fallback = '') {
        if (function_exists('i18n_get')) {
            $v = i18n_get($key);
            return $v ?? ($fallback ?? $key);
        }
        return $fallback ?? $key;
    }
}

$apiBase = '/api';

?>
<!-- Force load CSS if embedded -->
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/carts.css?v=<?= time() ?>">
<?php endif; ?>

<div class="page-container" id="cartsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h2 class="page-title"><?= __t('carts.title', 'Cart Management') ?></h2>
            <p class="page-subtitle"><?= __t('carts.subtitle', 'Manage shopping carts, items, and activity') ?></p>
        </div>
        <div class="page-header-actions">
            <button class="btn btn-secondary" id="btnRefreshCarts"><i class="fas fa-sync-alt"></i> <?= __t('carts.refresh', 'Refresh') ?></button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card"><div class="stat-value" id="statTotal">0</div><div class="stat-label"><?= __t('carts.stats.total', 'Total Carts') ?></div></div>
        <div class="stat-card stat-active"><div class="stat-value" id="statActive">0</div><div class="stat-label"><?= __t('carts.stats.active', 'Active') ?></div></div>
        <div class="stat-card stat-abandoned"><div class="stat-value" id="statAbandoned">0</div><div class="stat-label"><?= __t('carts.stats.abandoned', 'Abandoned') ?></div></div>
        <div class="stat-card stat-converted"><div class="stat-value" id="statConverted">0</div><div class="stat-label"><?= __t('carts.stats.converted', 'Converted') ?></div></div>
    </div>

    <!-- Main Tabs -->
    <div class="tabs-nav">
        <button class="tab-btn active" data-tab="carts"><i class="fas fa-shopping-cart"></i> <?= __t('carts.tab_carts', 'Carts') ?></button>
        <button class="tab-btn" data-tab="items"><i class="fas fa-box"></i> <?= __t('carts.tab_items', 'Cart Items') ?></button>
        <button class="tab-btn" data-tab="events"><i class="fas fa-history"></i> <?= __t('carts.tab_events', 'Events Log') ?></button>
    </div>

    <!-- ═══════════════ TAB: CARTS ═══════════════ -->
    <div class="tab-content active" id="tab-carts">
        <!-- Filter Bar -->
        <div class="filter-bar">
            <input type="text" class="form-control" id="cartSearchInput" placeholder="<?= __t('carts.filter.search', 'Search by user ID, session...') ?>">
            <select class="form-control" id="cartStatusFilter">
                <option value=""><?= __t('carts.filter.all_status', 'All Status') ?></option>
                <option value="active"><?= __t('carts.filter.active', 'Active') ?></option>
                <option value="abandoned"><?= __t('carts.filter.abandoned', 'Abandoned') ?></option>
                <option value="converted"><?= __t('carts.filter.converted', 'Converted') ?></option>
                <option value="expired"><?= __t('carts.filter.expired', 'Expired') ?></option>
            </select>
            <input type="number" class="form-control" id="cartEntityFilter" placeholder="<?= __t('carts.filter.entity_id', 'Entity ID') ?>" min="1" style="width:120px">
            <button class="btn btn-secondary" id="btnCartFilter"><?= __t('carts.filter.apply', 'Filter') ?></button>
            <button class="btn btn-outline" id="btnCartClearFilter"><?= __t('carts.filter.clear', 'Clear') ?></button>
        </div>

        <!-- Carts Table -->
        <div class="card">
            <div class="card-body" style="overflow-x:auto">
                <table class="data-table" id="cartsTable">
                    <thead>
                        <tr>
                            <th><?= __t('carts.table.id', 'ID') ?></th>
                            <th><?= __t('carts.table.entity', 'Entity') ?></th>
                            <th><?= __t('carts.table.user', 'User') ?></th>
                            <th><?= __t('carts.table.items', 'Items') ?></th>
                            <th><?= __t('carts.table.total', 'Total') ?></th>
                            <th><?= __t('carts.table.currency', 'Currency') ?></th>
                            <th><?= __t('carts.table.status', 'Status') ?></th>
                            <th><?= __t('carts.table.coupon', 'Coupon') ?></th>
                            <th><?= __t('carts.table.last_activity', 'Last Activity') ?></th>
                            <th><?= __t('carts.table.actions', 'Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="cartsBody">
                        <tr><td colspan="10" class="text-center"><?= __t('carts.loading', 'Loading...') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Pagination -->
        <div class="pagination-wrapper">
            <div class="pagination-info" id="cartsPaginationInfo"></div>
            <div class="pagination" id="cartsPagination"></div>
        </div>
    </div>

    <!-- ═══════════════ TAB: CART ITEMS ═══════════════ -->
    <div class="tab-content" id="tab-items" style="display:none">
        <div class="filter-bar">
            <input type="number" class="form-control" id="itemCartIdFilter" placeholder="<?= __t('carts.items.filter_cart_id', 'Cart ID') ?>" min="1" style="width:120px">
            <input type="text" class="form-control" id="itemSkuFilter" placeholder="<?= __t('carts.items.filter_sku', 'SKU') ?>" style="width:140px">
            <input type="number" class="form-control" id="itemProductFilter" placeholder="<?= __t('carts.items.filter_product_id', 'Product ID') ?>" min="1" style="width:130px">
            <button class="btn btn-secondary" id="btnItemFilter"><?= __t('carts.filter.apply', 'Filter') ?></button>
            <button class="btn btn-outline" id="btnItemClearFilter"><?= __t('carts.filter.clear', 'Clear') ?></button>
        </div>

        <div class="card">
            <div class="card-body" style="overflow-x:auto">
                <table class="data-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th><?= __t('carts.items.id', 'ID') ?></th>
                            <th><?= __t('carts.items.cart_id', 'Cart') ?></th>
                            <th><?= __t('carts.items.product', 'Product') ?></th>
                            <th><?= __t('carts.items.sku', 'SKU') ?></th>
                            <th><?= __t('carts.items.qty', 'Qty') ?></th>
                            <th><?= __t('carts.items.unit_price', 'Unit Price') ?></th>
                            <th><?= __t('carts.items.discount', 'Discount') ?></th>
                            <th><?= __t('carts.items.tax', 'Tax') ?></th>
                            <th><?= __t('carts.items.total', 'Total') ?></th>
                            <th><?= __t('carts.items.gift', 'Gift') ?></th>
                            <th><?= __t('carts.items.added_at', 'Added') ?></th>
                            <th><?= __t('carts.items.actions', 'Actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <tr><td colspan="12" class="text-center"><?= __t('carts.loading', 'Loading...') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="pagination-wrapper">
            <div class="pagination-info" id="itemsPaginationInfo"></div>
            <div class="pagination" id="itemsPagination"></div>
        </div>
    </div>

    <!-- ═══════════════ TAB: EVENTS ═══════════════ -->
    <div class="tab-content" id="tab-events" style="display:none">
        <div class="filter-bar">
            <input type="number" class="form-control" id="eventCartIdFilter" placeholder="<?= __t('carts.events.filter_cart_id', 'Cart ID') ?>" min="1" style="width:120px">
            <select class="form-control" id="eventTypeFilter">
                <option value=""><?= __t('carts.events.all_types', 'All Event Types') ?></option>
                <option value="item_added">item_added</option>
                <option value="item_removed">item_removed</option>
                <option value="item_updated">item_updated</option>
                <option value="coupon_applied">coupon_applied</option>
                <option value="coupon_removed">coupon_removed</option>
                <option value="status_changed">status_changed</option>
                <option value="cart_expired">cart_expired</option>
                <option value="cart_converted">cart_converted</option>
            </select>
            <select class="form-control" id="eventActorFilter">
                <option value=""><?= __t('carts.events.all_actors', 'All Actors') ?></option>
                <option value="user"><?= __t('carts.events.user', 'User') ?></option>
                <option value="admin"><?= __t('carts.events.admin', 'Admin') ?></option>
                <option value="system"><?= __t('carts.events.system', 'System') ?></option>
            </select>
            <button class="btn btn-secondary" id="btnEventFilter"><?= __t('carts.filter.apply', 'Filter') ?></button>
            <button class="btn btn-outline" id="btnEventClearFilter"><?= __t('carts.filter.clear', 'Clear') ?></button>
        </div>

        <div class="card">
            <div class="card-body" style="overflow-x:auto">
                <table class="data-table" id="eventsTable">
                    <thead>
                        <tr>
                            <th><?= __t('carts.events.id', 'ID') ?></th>
                            <th><?= __t('carts.events.cart_id', 'Cart') ?></th>
                            <th><?= __t('carts.events.event_type', 'Event Type') ?></th>
                            <th><?= __t('carts.events.actor', 'Actor') ?></th>
                            <th><?= __t('carts.events.actor_id', 'Actor ID') ?></th>
                            <th><?= __t('carts.events.item_id', 'Item ID') ?></th>
                            <th><?= __t('carts.events.old_value', 'Old Value') ?></th>
                            <th><?= __t('carts.events.new_value', 'New Value') ?></th>
                            <th><?= __t('carts.events.note', 'Note') ?></th>
                            <th><?= __t('carts.events.created_at', 'Date') ?></th>
                        </tr>
                    </thead>
                    <tbody id="eventsBody">
                        <tr><td colspan="10" class="text-center"><?= __t('carts.loading', 'Loading...') ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="pagination-wrapper">
            <div class="pagination-info" id="eventsPaginationInfo"></div>
            <div class="pagination" id="eventsPagination"></div>
        </div>
    </div>

    <!-- ═══════════════ CART DETAIL MODAL ═══════════════ -->
    <div class="modal" id="cartDetailModal" style="display:none">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3 id="cartDetailTitle"><?= __t('carts.detail.title', 'Cart Details') ?></h3>
                <button class="modal-close" id="btnCloseCartDetail">&times;</button>
            </div>
            <div class="modal-body" id="cartDetailBody">
                <div class="cart-detail-grid">
                    <div class="detail-section">
                        <h4><?= __t('carts.detail.info', 'Cart Info') ?></h4>
                        <div class="detail-fields" id="cartInfoFields"></div>
                    </div>
                    <div class="detail-section">
                        <h4><?= __t('carts.detail.amounts', 'Amounts') ?></h4>
                        <div class="detail-fields" id="cartAmountFields"></div>
                    </div>
                </div>
                <div class="detail-section" style="margin-top:16px">
                    <h4><?= __t('carts.detail.items_in_cart', 'Items in Cart') ?></h4>
                    <div style="overflow-x:auto">
                        <table class="data-table" id="cartDetailItemsTable">
                            <thead>
                                <tr>
                                    <th><?= __t('carts.items.product', 'Product') ?></th>
                                    <th><?= __t('carts.items.sku', 'SKU') ?></th>
                                    <th><?= __t('carts.items.qty', 'Qty') ?></th>
                                    <th><?= __t('carts.items.unit_price', 'Unit Price') ?></th>
                                    <th><?= __t('carts.items.total', 'Total') ?></th>
                                </tr>
                            </thead>
                            <tbody id="cartDetailItemsBody">
                                <tr><td colspan="5" class="text-center"><?= __t('carts.loading', 'Loading...') ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="detail-section" style="margin-top:16px">
                    <h4><?= __t('carts.detail.events', 'Recent Events') ?></h4>
                    <div style="overflow-x:auto">
                        <table class="data-table" id="cartDetailEventsTable">
                            <thead>
                                <tr>
                                    <th><?= __t('carts.events.event_type', 'Event') ?></th>
                                    <th><?= __t('carts.events.actor', 'Actor') ?></th>
                                    <th><?= __t('carts.events.note', 'Note') ?></th>
                                    <th><?= __t('carts.events.created_at', 'Date') ?></th>
                                </tr>
                            </thead>
                            <tbody id="cartDetailEventsBody">
                                <tr><td colspan="4" class="text-center"><?= __t('carts.loading', 'Loading...') ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════ EDIT CART MODAL ═══════════════ -->
    <div class="modal" id="editCartModal" style="display:none">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="editCartTitle"><?= __t('carts.edit.title', 'Edit Cart') ?></h3>
                <button class="modal-close" id="btnCloseEditCart">&times;</button>
            </div>
            <form id="editCartForm">
                <input type="hidden" id="editCartId" value="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-group">
                    <label><?= __t('carts.edit.status', 'Status') ?></label>
                    <select class="form-control" id="editCartStatus" name="status">
                        <option value="active"><?= __t('carts.status.active', 'Active') ?></option>
                        <option value="abandoned"><?= __t('carts.status.abandoned', 'Abandoned') ?></option>
                        <option value="converted"><?= __t('carts.status.converted', 'Converted') ?></option>
                        <option value="expired"><?= __t('carts.status.expired', 'Expired') ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= __t('carts.edit.coupon_code', 'Coupon Code') ?></label>
                    <input type="text" class="form-control" id="editCartCoupon" name="coupon_code" maxlength="100">
                </div>
                <div class="form-group">
                    <label><?= __t('carts.edit.expires_at', 'Expires At') ?></label>
                    <input type="datetime-local" class="form-control" id="editCartExpires" name="expires_at">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-outline" id="btnCancelEditCart"><?= __t('carts.edit.cancel', 'Cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __t('carts.edit.save', 'Save Changes') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══════════════ EDIT ITEM MODAL ═══════════════ -->
    <div class="modal" id="editItemModal" style="display:none">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?= __t('carts.items.edit_title', 'Edit Cart Item') ?></h3>
                <button class="modal-close" id="btnCloseEditItem">&times;</button>
            </div>
            <form id="editItemForm">
                <input type="hidden" id="editItemId" value="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label><?= __t('carts.items.qty', 'Quantity') ?></label>
                        <input type="number" class="form-control" id="editItemQty" name="quantity" min="1" required>
                    </div>
                    <div class="form-group">
                        <label><?= __t('carts.items.unit_price', 'Unit Price') ?></label>
                        <input type="number" class="form-control" id="editItemPrice" name="unit_price" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><?= __t('carts.items.discount', 'Discount Amount') ?></label>
                        <input type="number" class="form-control" id="editItemDiscount" name="discount_amount" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label><?= __t('carts.items.gift', 'Is Gift') ?></label>
                        <select class="form-control" id="editItemGift" name="is_gift">
                            <option value="0"><?= __t('carts.no', 'No') ?></option>
                            <option value="1"><?= __t('carts.yes', 'Yes') ?></option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label><?= __t('carts.items.special_instructions', 'Special Instructions') ?></label>
                    <textarea class="form-control" id="editItemInstructions" name="special_instructions" rows="2"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-outline" id="btnCancelEditItem"><?= __t('carts.edit.cancel', 'Cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= __t('carts.edit.save', 'Save Changes') ?></button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Expose client-side globals for the module -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE = window.APP_CONFIG.API_BASE || '<?= $apiBase ?>';
window.APP_CONFIG.TENANT_ID = window.APP_CONFIG.TENANT_ID || <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN = window.APP_CONFIG.CSRF_TOKEN || '<?= addslashes($csrf) ?>';
window.APP_CONFIG.USER_ID = window.APP_CONFIG.USER_ID || <?= admin_user_id() ?>;

window.CARTS_CONFIG = {
    apiCarts: '<?= $apiBase ?>/carts',
    apiCartItems: '<?= $apiBase ?>/cart_items',
    apiCartEvents: '<?= $apiBase ?>/cart_events',
    tenantId: <?= $tenantId ?>,
    lang: '<?= addslashes($lang) ?>',
    dir: '<?= addslashes($dir) ?>',
    csrfToken: '<?= addslashes($csrf) ?>',
    canEdit: <?= json_encode($canEdit) ?>,
    canDelete: <?= json_encode($canDelete) ?>,
    itemsPerPage: 25
};
</script>

<!-- Load JS -->
<script src="/admin/assets/js/pages/carts.js?v=<?= time() ?>"></script>

<?php if (!$isFragment): ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>
