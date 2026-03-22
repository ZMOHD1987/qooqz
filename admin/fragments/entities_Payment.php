<?php
declare(strict_types=1);

/**
 * /admin/fragments/entities_Payment.php
 * Entity Payment Management - Production Ready
 */

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// Load context
if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// Auth check
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

// User / Tenant context
$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

// Permissions
$canManagePayment = can('manage_entities') || can('manage_entity_payments') || is_super_admin();
$canView          = can_view_all('entities') || can_view_own('entities') || can_view_tenant('entities') || is_super_admin();

// Access check
if (!$canView) {
    http_response_code(403);
    die('Access denied');
}

// Entity ID (optional) – use GET, fallback to session entity
$entityId = isset($_GET['entity_id']) && (int)$_GET['entity_id'] > 0
    ? (int)$_GET['entity_id']
    : (isset($_SESSION['entity_id']) && (int)$_SESSION['entity_id'] > 0
        ? (int)$_SESSION['entity_id']
        : 0);

// API base
$apiBase = '/api';

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

// Load translation file for server-side rendering
$_paymentStrings = [];
$_allowedLangs = ['en', 'ar', 'fa', 'he', 'ur', 'tr', 'fr', 'de', 'es'];
$_safeLang = in_array($lang, $_allowedLangs, true) ? $lang : 'en';
$_langFile = __DIR__ . '/../../languages/EntitiesPayment/' . $_safeLang . '.json';
if (file_exists($_langFile)) {
    $_json = json_decode(file_get_contents($_langFile), true);
    if (isset($_json['strings'])) {
        $_paymentStrings = $_json['strings'];
    }
}

function _pt($key, $fallback = '') {
    global $_paymentStrings;
    $keys = explode('.', $key);
    $val = $_paymentStrings;
    foreach ($keys as $k) {
        if (is_array($val) && isset($val[$k])) {
            $val = $val[$k];
        } else {
            return $fallback ?: $key;
        }
    }
    return is_string($val) ? $val : ($fallback ?: $key);
}
?>

<link rel="stylesheet" href="/admin/assets/css/pages/entities_payment.css?v=<?= time() ?>">
<meta data-page="entities_payment"
      data-i18n-files="/languages/EntitiesPayment/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="entitiesPaymentPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <h1 data-i18n="title"><?= htmlspecialchars(_pt('title', 'Entity Payment Methods')) ?></h1>
        <p data-i18n="subtitle"><?= htmlspecialchars(_pt('subtitle', 'Manage payment gateways and bank accounts')) ?></p>
        <div class="page-header-actions">
            <?php if ($canManagePayment): ?>
                <button id="btnAddPayment" class="btn btn-primary" data-i18n="add_payment"><?= htmlspecialchars(_pt('add_payment', 'Add Payment')) ?></button>
                <button id="btnAddBank" class="btn btn-primary" data-i18n="add_bank"><?= htmlspecialchars(_pt('add_bank', 'Add Bank')) ?></button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Super Admin: Tenant → Entity Cascade -->
    <?php if (is_super_admin()): ?>
    <div class="card" id="entityFilterCard">
        <div class="card-body" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="min-width:150px;">
                <label><?= htmlspecialchars(_pt('tenant_id', 'Tenant ID')) ?></label>
                <div style="display:flex;gap:5px;">
                    <input type="number" id="tenantIdInput" class="form-control" placeholder="<?= htmlspecialchars(_pt('enter_tenant_id', 'Enter Tenant ID')) ?>" min="1" style="width:120px;">
                    <button id="btnVerifyTenant" class="btn btn-secondary" style="white-space:nowrap;"><?= htmlspecialchars(_pt('verify', 'Verify')) ?></button>
                </div>
                <small id="tenantNameDisplay" style="display:none;margin-top:4px;"></small>
            </div>
            <div class="form-group" style="flex:1;min-width:250px;">
                <label><?= htmlspecialchars(_pt('filter_entity', 'Filter by Entity')) ?></label>
                <select id="globalEntityFilter" class="form-control">
                    <option value=""><?= htmlspecialchars(_pt('all_entities', 'All Entities')) ?></option>
                </select>
            </div>
        </div>
    </div>
    <?php elseif (!$entityId): ?>
    <!-- Tenant Admin: Entity Selector -->
    <div class="card">
        <div class="card-body" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="flex:1;min-width:250px;">
                <label><?= htmlspecialchars(_pt('filter_entity', 'Filter by Entity')) ?></label>
                <select id="globalEntityFilter" class="form-control">
                    <option value=""><?= htmlspecialchars(_pt('select_entity', 'Select Entity...')) ?></option>
                </select>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="content-tabs" <?= $entityId ? '' : 'style="display:none;"' ?>>
        <button class="tab-btn active" data-tab="payment_methods" data-i18n="payment_methods.title"><?= htmlspecialchars(_pt('payment_methods.title', 'Payment Methods')) ?></button>
        <button class="tab-btn" data-tab="bank_accounts" data-i18n="bank_accounts.title"><?= htmlspecialchars(_pt('bank_accounts.title', 'Bank Accounts')) ?></button>

        <!-- Payment Methods -->
        <div class="tab-content active" id="tab-payment_methods">
            <div class="filter-bar" id="paymentFilters">
                <div class="filter-group">
                    <label><?= htmlspecialchars(_pt('filter.search', 'Search')) ?></label>
                    <input type="text" id="pmFilterSearch" class="form-control" placeholder="<?= htmlspecialchars(_pt('filter.search_payments', 'Email, Account ID...')) ?>">
                </div>
                <div class="filter-group">
                    <label><?= htmlspecialchars(_pt('filter.payment_method', 'Payment Method')) ?></label>
                    <select id="pmFilterMethod" class="form-control">
                        <option value=""><?= htmlspecialchars(_pt('filter.all', 'All')) ?></option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><?= htmlspecialchars(_pt('filter.status', 'Status')) ?></label>
                    <select id="pmFilterStatus" class="form-control">
                        <option value=""><?= htmlspecialchars(_pt('filter.all', 'All')) ?></option>
                        <option value="1"><?= htmlspecialchars(_pt('filter.active', 'Active')) ?></option>
                        <option value="0"><?= htmlspecialchars(_pt('filter.inactive', 'Inactive')) ?></option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><?= htmlspecialchars(_pt('filter.date_from', 'From')) ?></label>
                    <input type="date" id="pmFilterDateFrom" class="form-control">
                </div>
                <div class="filter-group">
                    <label><?= htmlspecialchars(_pt('filter.date_to', 'To')) ?></label>
                    <input type="date" id="pmFilterDateTo" class="form-control">
                </div>
                <div class="filter-actions">
                    <button type="button" id="btnFilterPayments" class="btn btn-primary"><?= htmlspecialchars(_pt('filter.apply', 'Filter')) ?></button>
                    <button type="button" id="btnClearPaymentFilters" class="btn btn-secondary"><?= htmlspecialchars(_pt('filter.clear', 'Clear')) ?></button>
                </div>
            </div>
            <table class="data-table" id="paymentMethodsTable">
                <thead>
                    <tr>
                        <th data-i18n="table.id"><?= htmlspecialchars(_pt('table.id', 'ID')) ?></th>
                        <th data-i18n="payment_methods.gateway"><?= htmlspecialchars(_pt('payment_methods.gateway', 'Payment Method')) ?></th>
                        <th data-i18n="payment_methods.account_email"><?= htmlspecialchars(_pt('payment_methods.account_email', 'Account Email')) ?></th>
                        <th data-i18n="payment_methods.account_id"><?= htmlspecialchars(_pt('payment_methods.account_id', 'Account ID')) ?></th>
                        <th data-i18n="payment_methods.active"><?= htmlspecialchars(_pt('payment_methods.active', 'Active')) ?></th>
                        <th data-i18n="table.actions"><?= htmlspecialchars(_pt('table.actions', 'Actions')) ?></th>
                    </tr>
                </thead>
                <tbody id="paymentMethodsBody"></tbody>
            </table>
        </div>

        <!-- Bank Accounts -->
        <div class="tab-content" id="tab-bank_accounts">
            <div class="filter-bar" id="bankFilters">
                <div class="filter-group">
                    <label><?= htmlspecialchars(_pt('filter.search', 'Search')) ?></label>
                    <input type="text" id="bankFilterSearch" class="form-control" placeholder="<?= htmlspecialchars(_pt('filter.search_banks', 'Bank name, account, IBAN...')) ?>">
                </div>
                <div class="filter-group">
                    <label><?= htmlspecialchars(_pt('filter.status', 'Status')) ?></label>
                    <select id="bankFilterStatus" class="form-control">
                        <option value=""><?= htmlspecialchars(_pt('filter.all', 'All')) ?></option>
                        <option value="1"><?= htmlspecialchars(_pt('filter.active', 'Active')) ?></option>
                        <option value="0"><?= htmlspecialchars(_pt('filter.inactive', 'Inactive')) ?></option>
                    </select>
                </div>
                <div class="filter-group">
                    <label><?= htmlspecialchars(_pt('filter.date_from', 'From')) ?></label>
                    <input type="date" id="bankFilterDateFrom" class="form-control">
                </div>
                <div class="filter-group">
                    <label><?= htmlspecialchars(_pt('filter.date_to', 'To')) ?></label>
                    <input type="date" id="bankFilterDateTo" class="form-control">
                </div>
                <div class="filter-actions">
                    <button type="button" id="btnFilterBanks" class="btn btn-primary"><?= htmlspecialchars(_pt('filter.apply', 'Filter')) ?></button>
                    <button type="button" id="btnClearBankFilters" class="btn btn-secondary"><?= htmlspecialchars(_pt('filter.clear', 'Clear')) ?></button>
                </div>
            </div>
            <table class="data-table" id="bankAccountsTable">
                <thead>
                    <tr>
                        <th data-i18n="table.id"><?= htmlspecialchars(_pt('table.id', 'ID')) ?></th>
                        <th data-i18n="bank_accounts.bank_name"><?= htmlspecialchars(_pt('bank_accounts.bank_name', 'Bank Name')) ?></th>
                        <th data-i18n="bank_accounts.account_holder"><?= htmlspecialchars(_pt('bank_accounts.account_holder', 'Account Holder')) ?></th>
                        <th data-i18n="bank_accounts.account_number"><?= htmlspecialchars(_pt('bank_accounts.account_number', 'Account Number')) ?></th>
                        <th data-i18n="bank_accounts.iban"><?= htmlspecialchars(_pt('bank_accounts.iban', 'IBAN')) ?></th>
                        <th data-i18n="bank_accounts.swift_code"><?= htmlspecialchars(_pt('bank_accounts.swift_code', 'SWIFT Code')) ?></th>
                        <th data-i18n="bank_accounts.primary"><?= htmlspecialchars(_pt('bank_accounts.primary', 'Primary')) ?></th>
                        <th data-i18n="bank_accounts.verified"><?= htmlspecialchars(_pt('bank_accounts.verified', 'Verified')) ?></th>
                        <th data-i18n="table.actions"><?= htmlspecialchars(_pt('table.actions', 'Actions')) ?></th>
                    </tr>
                </thead>
                <tbody id="bankAccountsBody"></tbody>
            </table>
        </div>
    </div>

    <!-- Payment Method Modal -->
    <div id="paymentMethodModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3 id="paymentModalTitle" data-i18n="payment_methods.add"><?= htmlspecialchars(_pt('payment_methods.add', 'Add Payment Method')) ?></h3>
            <form id="paymentMethodForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="entity_id" value="<?= $entityId ?>">
                <input type="hidden" name="id" id="pmEditId" value="">
                <div class="form-group">
                    <label data-i18n="payment_methods.gateway"><?= htmlspecialchars(_pt('payment_methods.gateway', 'Payment Method')) ?> *</label>
                    <select name="payment_method_id" id="pmPaymentMethodId" class="form-control" required>
                        <option value="" data-i18n="payment_methods.select_gateway"><?= htmlspecialchars(_pt('payment_methods.select_gateway', 'Select Payment Method...')) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label data-i18n="payment_methods.account_email"><?= htmlspecialchars(_pt('payment_methods.account_email', 'Account Email')) ?></label>
                    <input type="email" name="account_email" id="pmEmail" class="form-control">
                </div>
                <div class="form-group">
                    <label data-i18n="payment_methods.account_id"><?= htmlspecialchars(_pt('payment_methods.account_id', 'Account ID')) ?></label>
                    <input type="text" name="account_id" id="pmAccountId" class="form-control">
                </div>
                <div class="form-group">
                    <label data-i18n="payment_methods.active"><?= htmlspecialchars(_pt('payment_methods.active', 'Active')) ?></label>
                    <select name="is_active" id="pmActive" class="form-control">
                        <option value="1" data-i18n="table.yes"><?= htmlspecialchars(_pt('table.yes', 'Yes')) ?></option>
                        <option value="0" data-i18n="table.no"><?= htmlspecialchars(_pt('table.no', 'No')) ?></option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" data-i18n="form.save"><?= htmlspecialchars(_pt('form.save', 'Save')) ?></button>
                    <button type="button" class="btn btn-secondary btn-close-modal" data-modal="paymentMethodModal" data-i18n="form.cancel"><?= htmlspecialchars(_pt('form.cancel', 'Cancel')) ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bank Account Modal -->
    <div id="bankAccountModal" class="modal" style="display:none;">
        <div class="modal-content">
            <h3 id="bankModalTitle" data-i18n="bank_accounts.add"><?= htmlspecialchars(_pt('bank_accounts.add', 'Add Bank Account')) ?></h3>
            <form id="bankAccountForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="entity_id" value="<?= $entityId ?>">
                <input type="hidden" name="id" id="baEditId" value="">
                <div class="form-group">
                    <label data-i18n="bank_accounts.bank_name"><?= htmlspecialchars(_pt('bank_accounts.bank_name', 'Bank Name')) ?> *</label>
                    <input type="text" name="bank_name" id="baBankName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label data-i18n="bank_accounts.account_holder"><?= htmlspecialchars(_pt('bank_accounts.account_holder', 'Account Holder')) ?> *</label>
                    <input type="text" name="account_holder_name" id="baHolderName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label data-i18n="bank_accounts.account_number"><?= htmlspecialchars(_pt('bank_accounts.account_number', 'Account Number')) ?> *</label>
                    <input type="text" name="account_number" id="baAccountNumber" class="form-control" required>
                </div>
                <div class="form-group">
                    <label data-i18n="bank_accounts.iban"><?= htmlspecialchars(_pt('bank_accounts.iban', 'IBAN')) ?></label>
                    <input type="text" name="iban" id="baIban" class="form-control">
                </div>
                <div class="form-group">
                    <label data-i18n="bank_accounts.swift_code"><?= htmlspecialchars(_pt('bank_accounts.swift_code', 'SWIFT Code')) ?></label>
                    <input type="text" name="swift_code" id="baSwift" class="form-control">
                </div>
                <div class="form-group">
                    <label data-i18n="bank_accounts.primary"><?= htmlspecialchars(_pt('bank_accounts.primary', 'Primary')) ?></label>
                    <select name="is_primary" id="baPrimary" class="form-control">
                        <option value="0" data-i18n="table.no"><?= htmlspecialchars(_pt('table.no', 'No')) ?></option>
                        <option value="1" data-i18n="table.yes"><?= htmlspecialchars(_pt('table.yes', 'Yes')) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label data-i18n="bank_accounts.verified"><?= htmlspecialchars(_pt('bank_accounts.verified', 'Verified')) ?></label>
                    <select name="is_verified" id="baVerified" class="form-control">
                        <option value="0" data-i18n="table.no"><?= htmlspecialchars(_pt('table.no', 'No')) ?></option>
                        <option value="1" data-i18n="table.yes"><?= htmlspecialchars(_pt('table.yes', 'Yes')) ?></option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" data-i18n="form.save"><?= htmlspecialchars(_pt('form.save', 'Save')) ?></button>
                    <button type="button" class="btn btn-secondary btn-close-modal" data-modal="bankAccountModal" data-i18n="form.cancel"><?= htmlspecialchars(_pt('form.cancel', 'Cancel')) ?></button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
window.ENTITIES_PAYMENT_CONFIG = {
    apiBase: <?= json_encode($apiBase) ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    entityId: <?= $entityId ?>,
    tenantId: <?= (int)$tenantId ?>,
    userId: <?= (int)$userId ?>,
    canEdit: <?= json_encode($canManagePayment) ?>,
    canDelete: <?= json_encode($canManagePayment) ?>,
    isSuperAdmin: <?= json_encode(is_super_admin()) ?>,
    strings: <?= json_encode($_paymentStrings, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="/admin/assets/js/pages/entities_payment.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>