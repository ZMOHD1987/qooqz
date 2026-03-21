<?php
declare(strict_types=1);

/**
 * /admin/fragments/pos.php
 * POS Cashier System – Tenant Employee Interface
 * Allows cashiers to: select products, manage order items, process payments,
 * and manage cashier sessions linked to pos_sessions table.
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    header('Location: /admin/login.php');
    exit;
}

$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

$isSuperAdmin = is_super_admin();
// Allow cashiers, store managers, and admins
$canAccess = $isSuperAdmin || can('pos.access') || can('pos.cashier') || can('manage_pos');
// Fallback: any logged-in tenant user can access POS
if (!$canAccess) {
    $canAccess = !empty($tenantId);
}

if (!$canAccess) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied');
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPER
// ════════════════════════════════════════════════════════════
if (!function_exists('__pos_t')) {
    function __pos_t(string $key, string $fallback = ''): string {
        static $cache = null;
        if ($cache === null) {
            $lang = $GLOBALS['ADMIN_UI']['lang'] ?? ($GLOBALS['lang'] ?? 'ar');
            $file = __DIR__ . '/../../languages/POS/' . $lang . '.json';
            if (!is_file($file)) {
                $file = __DIR__ . '/../../languages/POS/ar.json';
            }
            $cache = [];
            if (is_file($file)) {
                $raw = file_get_contents($file);
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $cache = $decoded;
                }
            }
        }
        // Traverse dotted key (e.g. "pos.tab.history")
        $parts = explode('.', $key);
        $cur   = $cache;
        foreach ($parts as $p) {
            if (!is_array($cur) || !isset($cur[$p])) {
                return htmlspecialchars($fallback ?: $key, ENT_QUOTES, 'UTF-8');
            }
            $cur = $cur[$p];
        }
        return htmlspecialchars(is_string($cur) ? $cur : ($fallback ?: $key), ENT_QUOTES, 'UTF-8');
    }
}

// Get current user's entity_id if set (from tenant_users context)
$userEntityId = $user['entity_id'] ?? null;
// Get the tenant_user_id (tenant_users.id) for the cashier_user_id field
$userTenantUserId = $user['tenant_user_id'] ?? null;

$canEditOrders = $isSuperAdmin || can('pos.edit_orders') || can('manage_pos');

// Theme CSS vars and generated_css are provided by header.php
// via <style id="dynamic-theme-vars"> and <style id="dynamic-theme-db">.
// No per-fragment duplication needed.
?>

<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/pos.css">
<?php endif; ?>

<!-- Page Meta (fragment loader uses these) -->
<meta data-page="pos"
      data-assets-css="/admin/assets/css/pages/pos.css"
      data-i18n-files="/languages/POS/<?= rawurlencode($lang) ?>.json">

<?php
// Load default active currency from DB
$defaultCurrency = 'SAR'; // fallback
$defaultCurrencySymbol = 'ر.س';
if (!empty($GLOBALS['ADMIN_DB']) && $GLOBALS['ADMIN_DB'] instanceof PDO) {
    try {
        $currStmt = $GLOBALS['ADMIN_DB']->prepare(
            "SELECT code, symbol FROM currencies WHERE is_active = 1 ORDER BY id ASC LIMIT 1"
        );
        $currStmt->execute();
        $currRow = $currStmt->fetch(PDO::FETCH_ASSOC);
        if ($currRow && !empty($currRow['code'])) {
            $defaultCurrency = (string)$currRow['code'];
            $defaultCurrencySymbol = (string)($currRow['symbol'] ?? $currRow['code']);
        }
    } catch (Throwable $e) { /* use fallback */ }
}
?>
<!-- POS Config -->
<script>
window.POS_CONFIG = {
    TENANT_ID:        <?= (int)$tenantId ?>,
    ENTITY_ID:        <?= $userEntityId ? (int)$userEntityId : 'null' ?>,
    USER_ID:          <?= (int)($user['id'] ?? 0) ?>,
    TENANT_USER_ID:   <?= $userTenantUserId ? (int)$userTenantUserId : 'null' ?>,
    IS_SUPER_ADMIN:   <?= $isSuperAdmin ? 'true' : 'false' ?>,
    CAN_EDIT_ORDERS:  <?= $canEditOrders ? 'true' : 'false' ?>,
    LANG:             '<?= htmlspecialchars($lang, ENT_QUOTES) ?>',
    DIR:              '<?= htmlspecialchars($dir, ENT_QUOTES) ?>',
    CSRF:             '<?= htmlspecialchars($csrf, ENT_QUOTES) ?>',
    CURRENCY:         '<?= htmlspecialchars($defaultCurrency, ENT_QUOTES) ?>',
    CURRENCY_SYMBOL:  '<?= htmlspecialchars($defaultCurrencySymbol, ENT_QUOTES) ?>',
};
</script>

<!-- ══════════════════════════════════════
     POS PAGE CONTAINER
     ══════════════════════════════════════ -->
<div class="pos-page" id="posPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Alerts -->
    <div id="posAlerts" style="padding:0 16px;padding-top:8px"></div>

    <!-- ── Session Status Bar ── -->
    <div class="pos-session-bar" id="posSessionBar">
        <span style="color:var(--text-secondary,#64748b)"><?= __pos_t('common.loading', 'Loading...') ?></span>
    </div>

    <!-- ── Entity / Tenant Context Nav (shown when session active, for switching entity context) ── -->
    <div class="pos-context-nav" id="posContextNav" style="display:none">
        <div class="pos-context-nav-inner">
            <span class="pos-context-label" aria-label="<?= __pos_t('pos.session.entity_label', 'Branch / Entity') ?>">🏪</span>
            <div class="pos-entity-nav-tabs" id="posEntityNavTabs"></div>
        </div>
    </div>

    <!-- ════════════════════════════════════
         OPEN SESSION VIEW
         (shown when no active session)
         ════════════════════════════════════ -->
    <div class="pos-open-session" id="posOpenSession" style="display:none">
        <div class="pos-open-session-card">
            <span class="pos-icon">🏧</span>
            <h2><?= __pos_t('pos.session.open_title', 'Open Cashier Session') ?></h2>
            <p><?= __pos_t('pos.session.open_subtitle', 'Start the workday by opening a new session') ?></p>

            <form id="posOpenSessionForm" style="text-align:right">
                <!-- Entity / Branch (shown for super admin or users without a preset entity) -->
                <div id="posEntitySelectWrapper" style="margin-bottom:14px">
                    <label style="display:block;font-size:.82rem;margin-bottom:4px;color:var(--text-secondary,#94a3b8)">
                        <?= __pos_t('pos.session.entity_label', 'Branch / Entity') ?>
                    </label>
                    <select name="entity_id" id="posEntitySelect" class="form-control" required>
                        <option value=""><?= __pos_t('pos.select_entity', 'Select Entity/Branch') ?></option>
                    </select>
                </div>

                <!-- Opening balance -->
                <div style="margin-bottom:14px">
                    <label style="display:block;font-size:.82rem;margin-bottom:4px;color:var(--text-secondary,#94a3b8)">
                        <?= __pos_t('pos.session.opening_balance', 'Opening Balance') ?> (<?= htmlspecialchars($defaultCurrencySymbol ?: $defaultCurrency, ENT_QUOTES) ?>)
                    </label>
                    <input type="number" name="opening_balance" class="form-control"
                           step="0.01" min="0" value="0" placeholder="0.00">
                </div>

                <!-- Cashier user (hidden, auto-set to current tenant_user id) -->
                <input type="hidden" name="cashier_user_id"
                       value="<?= $userTenantUserId ? (int)$userTenantUserId : 0 ?>">

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">
                    🟢 <?= __pos_t('pos.session.open_btn', 'Open Session') ?>
                </button>
            </form>
        </div>
    </div>

    <!-- ════════════════════════════════════
         TAB NAVIGATION (visible when session active)
         ════════════════════════════════════ -->
    <div class="pos-tab-nav" id="posTabNav" style="display:none">
        <button class="pos-tab-btn active" data-tab="pos">
            🏪 <?= __pos_t('pos.tab.pos', 'Point of Sale') ?>
        </button>
        <button class="pos-tab-btn" data-tab="history">
            📋 <?= __pos_t('pos.tab.history', 'Sales History') ?>
        </button>
        <?php if ($isSuperAdmin || can('manage_pos') || can('pos.reports')): ?>
        <button class="pos-tab-btn" data-tab="reports">
            📊 <?= __pos_t('pos.tab.reports', 'Reports') ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════
         MAIN POS LAYOUT  (tab: pos)
         ════════════════════════════════════ -->
    <div class="pos-layout" id="posMainLayout" style="display:none">

        <!-- ── Left Panel: Products ── -->
        <div class="pos-products-panel">

            <!-- Search + Barcode Button -->
            <div class="pos-search-bar">
                <input type="search" id="posSearch"
                       placeholder="<?= __pos_t('pos.products.search', 'Search or scan barcode...') ?>"
                       autocomplete="off">
                <button class="pos-barcode-btn" id="posBarcodeBtn" title="<?= __pos_t('pos.barcode.toggle', 'Barcode Scanner') ?>">
                    <span id="posBarcodeIcon">📷</span>
                </button>
            </div>

            <!-- Barcode Mode Banner -->
            <div class="pos-barcode-banner" id="posBarcodeBanner" style="display:none">
                <span>🔍 <?= __pos_t('pos.barcode.hardware_mode', 'Barcode Reader Mode – scan now') ?></span>
                <button class="pos-barcode-mode-btn" id="posBarcodeCameraBtn" title="<?= __pos_t('pos.barcode.camera', 'Camera') ?>">
                    📷 <?= __pos_t('pos.barcode.use_camera', 'Use Camera') ?>
                </button>
                <button class="pos-barcode-close" id="posBarcodeClose">✕</button>
            </div>

            <!-- Parent Category Tabs (from API) -->
            <div class="pos-category-tabs pos-parent-cats" id="posParentCats">
                <button class="pos-cat-tab active" data-parent-id="0"><?= __pos_t('pos.products.all', 'All') ?></button>
            </div>

            <!-- Sub Category Tabs (shown when parent selected) -->
            <div class="pos-sub-cat-tabs" id="posSubCats" style="display:none"></div>

            <!-- Active Offers / Discounts Banner -->
            <div class="pos-active-discounts-banner" id="posDiscountsBanner" style="display:none"></div>

            <!-- Products Grid -->
            <div class="pos-products-grid" id="posProductsGrid">
                <div class="pos-loading" style="grid-column:1/-1">
                    <div class="pos-spinner"></div>
                    <span><?= __pos_t('common.loading', 'Loading...') ?></span>
                </div>
            </div>
        </div>

        <!-- ── Right Panel: Cart + Payment ── -->
        <div class="pos-cart-panel">

            <!-- Cart Header -->
            <div class="pos-cart-header">
                <h3>🛒 <?= __pos_t('pos.cart.title', 'Cart') ?> <span class="pos-cart-count" id="posCartCount">0</span></h3>
            </div>

            <!-- Cart Items -->
            <div style="flex:1;overflow:hidden;display:flex;flex-direction:column">
                <!-- Empty state -->
                <div class="pos-cart-empty" id="posCartEmpty">
                    <span class="pos-cart-empty-icon">🛒</span>
                    <p><?= __pos_t('pos.cart.empty', 'Cart is empty') ?></p>
                    <small style="color:var(--text-secondary,#64748b)"><?= __pos_t('pos.cart.empty_hint', 'Click a product to add it') ?></small>
                </div>
                <!-- Items list -->
                <div class="pos-cart-items" id="posCartItems"></div>
            </div>

            <!-- Cart Footer: Totals + Payment -->
            <div class="pos-cart-footer">

                <!-- Totals -->
                <div class="pos-totals">
                    <?php $currLabel = htmlspecialchars($defaultCurrencySymbol ?: $defaultCurrency, ENT_QUOTES); ?>
                    <div class="pos-total-row">
                        <span><?= __pos_t('pos.subtotal', 'Subtotal') ?></span>
                        <span id="posSubtotal">0.00 <?= $currLabel ?></span>
                    </div>
                    <div class="pos-total-row">
                        <span><?= __pos_t('pos.tax', 'Tax') ?></span>
                        <span id="posTax">0.00 <?= $currLabel ?></span>
                    </div>
                    <!-- Manual Discount -->
                    <div class="pos-discount-row">
                        <span style="font-size:.82rem;color:var(--text-secondary,#94a3b8);white-space:nowrap"><?= __pos_t('pos.discount', 'Discount') ?>:</span>
                        <input type="number" id="posDiscount" step="0.01" min="0" placeholder="0.00">
                    </div>
                    <!-- Coupon Discount Row (shown when coupon applied) -->
                    <div class="pos-total-row pos-coupon-discount-row" id="posCouponRow" style="display:none;color:var(--success-color,#10b981)">
                        <span><?= __pos_t('pos.coupon.label', 'Coupon') ?></span>
                        <span id="posCouponDiscountAmt">0.00 <?= $currLabel ?></span>
                    </div>
                    <div class="pos-total-row">
                        <span><?= __pos_t('pos.total', 'Total') ?></span>
                        <span id="posTotal">0.00 <?= $currLabel ?></span>
                    </div>
                    <div class="pos-total-row grand">
                        <span><?= __pos_t('pos.grand_total', 'Grand Total') ?></span>
                        <span class="amount" id="posGrandTotal">0.00 <?= $currLabel ?></span>
                    </div>
                </div>

                <!-- Coupon Code Input -->
                <div class="pos-coupon-section">
                    <div class="pos-coupon-input-row">
                        <input type="text" id="posCouponInput"
                               placeholder="<?= __pos_t('pos.coupon.placeholder', 'Enter coupon code') ?>"
                               autocomplete="off" autocapitalize="characters">
                        <button class="pos-coupon-apply-btn" id="posApplyCoupon">
                            🏷 <?= __pos_t('pos.coupon.apply', 'Apply') ?>
                        </button>
                        <button class="pos-coupon-clear-btn" id="posClearCoupon" style="display:none">✕</button>
                    </div>
                    <div id="posCouponStatus" class="pos-coupon-status"></div>
                </div>

                <!-- Payment Method -->
                <div style="font-size:.78rem;color:var(--text-secondary,#64748b);margin-bottom:6px"><?= __pos_t('pos.payment.title', 'Payment Method') ?></div>
                <div class="pos-payment-methods">
                    <button class="pos-pay-method-btn" data-method="cash">
                        <span class="icon">💵</span>
                        <?= __pos_t('pos.payment.cash', 'Cash') ?>
                    </button>
                    <button class="pos-pay-method-btn" data-method="card">
                        <span class="icon">💳</span>
                        <?= __pos_t('pos.payment.card', 'Card') ?>
                    </button>
                </div>

                <!-- Amount paid (cash only) -->
                <div class="pos-amount-paid-row">
                    <label for="posAmountPaid"><?= __pos_t('pos.payment.amount_paid', 'Amount Paid') ?>:</label>
                    <input type="number" id="posAmountPaid" step="0.01" min="0" placeholder="0.00">
                </div>

                <!-- Change display -->
                <div class="pos-change-display" id="posChange"></div>

                <!-- Checkout button -->
                <button class="pos-checkout-btn" id="posCheckoutBtn" disabled>
                    ✅ <?= __pos_t('pos.cart.checkout', 'Checkout') ?>
                </button>

                <!-- Clear cart -->
                <button class="pos-clear-btn" id="posClearBtn">
                    🗑 <?= __pos_t('pos.cart.clear', 'Clear Cart') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════
         SALES HISTORY PANEL  (tab: history)
         ════════════════════════════════════ -->
    <div class="pos-panel" id="posHistoryPanel" style="display:none">
        <div class="pos-panel-header">
            <h2>📋 <?= __pos_t('pos.tab.history', 'Sales History') ?></h2>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <button class="btn btn-sm btn-outline pos-export-btn" id="posExportHistoryCSV" title="<?= __pos_t('pos.reports.export_csv', 'Export CSV') ?>">
                    📥 <?= __pos_t('pos.reports.export_csv', 'Export CSV') ?>
                </button>
                <button class="btn btn-sm btn-outline pos-export-btn" id="posExportHistoryExcel" title="<?= __pos_t('pos.reports.export_excel', 'Export Excel') ?>">
                    📊 <?= __pos_t('pos.reports.export_excel', 'Export Excel') ?>
                </button>
                <button class="btn btn-sm btn-outline" id="posRefreshHistory">
                    🔄 <?= __pos_t('common.refresh', 'Refresh') ?>
                </button>
            </div>
        </div>
        <!-- History Filters Bar -->
        <div class="pos-filters-bar" id="posHistoryFilters">
            <input type="date" id="posHistoryFrom" class="pos-filter-input" title="<?= __pos_t('pos.reports.date_from', 'From Date') ?>" placeholder="<?= __pos_t('pos.reports.date_from', 'From Date') ?>">
            <input type="date" id="posHistoryTo" class="pos-filter-input" title="<?= __pos_t('pos.reports.date_to', 'To Date') ?>" placeholder="<?= __pos_t('pos.reports.date_to', 'To Date') ?>">
            <select id="posHistoryPayMethod" class="pos-filter-input">
                <option value=""><?= __pos_t('pos.reports.all_payments', 'All Payments') ?></option>
                <option value="cash"><?= __pos_t('pos.payment.cash', 'Cash') ?></option>
                <option value="card"><?= __pos_t('pos.payment.card', 'Card') ?></option>
                <option value="transfer"><?= __pos_t('pos.payment.transfer', 'Bank Transfer') ?></option>
            </select>
            <button class="btn btn-sm btn-primary" id="posApplyHistoryFilter">
                🔍 <?= __pos_t('pos.reports.filter_btn', 'Filter') ?>
            </button>
            <button class="btn btn-sm btn-outline" id="posResetHistoryFilter">
                ↺ <?= __pos_t('pos.reports.reset_filter', 'Reset') ?>
            </button>
        </div>
        <div id="posHistoryContent">
            <div class="pos-loading"><div class="pos-spinner"></div></div>
        </div>
    </div>

    <!-- ════════════════════════════════════
         REPORTS PANEL  (tab: reports)
         ════════════════════════════════════ -->
    <?php if ($isSuperAdmin || can('manage_pos') || can('pos.reports')): ?>
    <div class="pos-panel" id="posReportsPanel" style="display:none">
        <div class="pos-panel-header">
            <h2>📊 <?= __pos_t('pos.tab.reports', 'Session Reports') ?></h2>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <button class="btn btn-sm btn-outline pos-export-btn" id="posExportCSV" title="<?= __pos_t('pos.reports.export_csv', 'Export CSV') ?>">
                    📥 <?= __pos_t('pos.reports.export_csv', 'Export CSV') ?>
                </button>
                <button class="btn btn-sm btn-outline pos-export-btn" id="posExportExcel" title="<?= __pos_t('pos.reports.export_excel', 'Export Excel') ?>">
                    📊 <?= __pos_t('pos.reports.export_excel', 'Export Excel') ?>
                </button>
                <button class="btn btn-sm btn-outline" id="posRefreshReports">
                    🔄 <?= __pos_t('common.refresh', 'Refresh') ?>
                </button>
            </div>
        </div>
        <!-- Reports Filters Bar -->
        <div class="pos-filters-bar" id="posReportsFilters">
            <input type="date" id="posReportsFrom" class="pos-filter-input" title="<?= __pos_t('pos.reports.date_from', 'From Date') ?>" placeholder="<?= __pos_t('pos.reports.date_from', 'From Date') ?>">
            <input type="date" id="posReportsTo" class="pos-filter-input" title="<?= __pos_t('pos.reports.date_to', 'To Date') ?>" placeholder="<?= __pos_t('pos.reports.date_to', 'To Date') ?>">
            <select id="posReportsPayMethod" class="pos-filter-input">
                <option value=""><?= __pos_t('pos.reports.all_payments', 'All Payments') ?></option>
                <option value="cash"><?= __pos_t('pos.payment.cash', 'Cash') ?></option>
                <option value="card"><?= __pos_t('pos.payment.card', 'Card') ?></option>
                <option value="transfer"><?= __pos_t('pos.payment.transfer', 'Bank Transfer') ?></option>
            </select>
            <button class="btn btn-sm btn-primary" id="posApplyReportsFilter">
                🔍 <?= __pos_t('pos.reports.filter_btn', 'Filter') ?>
            </button>
            <button class="btn btn-sm btn-outline" id="posResetReportsFilter">
                ↺ <?= __pos_t('pos.reports.reset_filter', 'Reset') ?>
            </button>
        </div>
        <div id="posReportsContent">
            <div class="pos-loading"><div class="pos-spinner"></div></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ════════════════════════════════════
         CAMERA SCANNER OVERLAY
         ════════════════════════════════════ -->
    <div class="pos-camera-overlay" id="posCameraOverlay" style="display:none">
        <div class="pos-camera-box">
            <div class="pos-camera-header">
                <span>📷 <?= __pos_t('pos.barcode.camera_title', 'Camera Barcode Scanner') ?></span>
                <button class="pos-camera-close" id="posCameraClose">✕</button>
            </div>
            <video id="posCameraVideo" autoplay playsinline muted
                   style="width:100%;border-radius:8px;background:var(--background-main,#000)"></video>
            <div class="pos-camera-guide">
                <div class="pos-scan-line"></div>
            </div>
            <p class="pos-camera-status" id="posCameraStatus">
                <?= __pos_t('pos.barcode.camera_hint', 'Point camera at barcode') ?>
            </p>
            <div style="text-align:center;margin-top:8px">
                <button class="btn btn-outline btn-sm" id="posCameraStop">
                    ⏹ <?= __pos_t('pos.barcode.stop_camera', 'Stop Camera') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════
         MODAL BACKDROP (receipt / close session)
         ════════════════════════════════════ -->
    <div class="pos-modal-backdrop" id="posModalBackdrop" style="display:none">
        <div class="pos-modal"></div>
    </div>

</div><!-- /.pos-page -->

<!-- Scripts -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/pos.js?v=<?= time() ?>"></script>
<?php else: ?>
<script src="/admin/assets/js/admin_framework.js"></script>
<script src="/admin/assets/js/pages/pos.js"></script>
<?php endif; ?>