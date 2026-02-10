<?php
declare(strict_types=1);

/**
 * admin/fragments/entities_Payment.php
 * Entity Payment Management - Bank accounts and payment methods
 * 
 * Follows the same auth/permission pattern as entities.php
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
$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS (same pattern as entities.php)
// ════════════════════════════════════════════════════════════
$canManageEntities = can('entities.manage') || can('entities.create');
$canViewAll   = can_view_all('entities');
$canViewOwn   = can_view_own('entities');
$canViewTenant = can_view_tenant('entities');
$canCreate    = can_create('entities');
$canEditAll   = can_edit_all('entities');
$canEditOwn   = can_edit_own('entities');
$canDeleteAll = can_delete_all('entities');
$canDeleteOwn = can_delete_own('entities');

$canView   = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit   = $canEditAll || $canEditOwn || $canManageEntities;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManageEntities;
$canManage = $canEdit || is_super_admin();

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view entity payments');
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

// Local alias for backward compatibility in this file
if (!function_exists('pay_t')) {
    function pay_t(string $key, string $fallback = ''): string {
        return __t($key, $fallback);
    }
}

// ════════════════════════════════════════════════════════════
// ENTITY ID & API BASE
// ════════════════════════════════════════════════════════════
$entityId = (int)($_GET['entity_id'] ?? 0);
$apiBase  = '/api';

?>
<!-- Force load CSS if embedded -->
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/entities_payment.css?v=<?= time() ?>">
<?php endif; ?>

<!-- Page Container -->
<div class="page-container" id="entitiesPaymentPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title"><?= pay_t('entity_payments', 'Entity Payments') ?></h1>
            <p class="page-subtitle"><?= pay_t('entity_payments_subtitle', 'Manage payment methods and bank accounts') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canManage): ?>
            <button type="button" class="btn btn-primary" id="btnAddPaymentMethod">
                <i class="fas fa-plus"></i>
                <span><?= pay_t('add_payment_method', 'Add Payment Method') ?></span>
            </button>
            <button type="button" class="btn btn-primary" id="btnAddBankAccount">
                <i class="fas fa-plus"></i>
                <span><?= pay_t('add_bank_account', 'Add Bank Account') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$entityId): ?>
    <div class="card" id="entitySelectorWrap">
        <div class="card-body">
            <label for="entitySelector"><?= pay_t('select_entity', 'Select Entity') ?></label>
            <select id="entitySelector" class="form-control">
                <option value=""><?= pay_t('select_entity_placeholder', '-- Select Entity --') ?></option>
            </select>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="card">
        <div class="card-body">
            <div class="form-tabs">
                <button type="button" class="tab-btn active" data-tab="payment_methods">
                    <i class="fas fa-credit-card"></i>
                    <span><?= pay_t('payment_methods', 'Payment Methods') ?></span>
                </button>
                <button type="button" class="tab-btn" data-tab="bank_accounts">
                    <i class="fas fa-university"></i>
                    <span><?= pay_t('bank_accounts', 'Bank Accounts') ?></span>
                </button>
            </div>

    <!-- Payment Methods Tab -->
    <div class="tab-content active" id="tab-payment_methods">
        <div id="pmLoading" class="loading-state"><?= pay_t('loading', 'Loading...') ?></div>
        <div id="pmEmpty" class="empty-state" style="display:none;"><?= pay_t('no_payment_methods', 'No payment methods found') ?></div>
        <div class="table-responsive" id="pmTableWrap" style="display:none;">
            <table class="data-table" id="paymentMethodsTable">
                <thead>
                    <tr>
                        <th><?= pay_t('id', 'ID') ?></th>
                        <th><?= pay_t('gateway', 'Gateway') ?></th>
                        <th><?= pay_t('account_email', 'Account Email') ?></th>
                        <th><?= pay_t('account_id', 'Account ID') ?></th>
                        <th><?= pay_t('active', 'Active') ?></th>
                        <th><?= pay_t('created_at', 'Created At') ?></th>
                        <?php if ($canManage): ?>
                        <th><?= pay_t('actions', 'Actions') ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="paymentMethodsBody"></tbody>
            </table>
        </div>
    </div>

    <!-- Bank Accounts Tab -->
    <div class="tab-content" id="tab-bank_accounts">
        <div id="baLoading" class="loading-state"><?= pay_t('loading', 'Loading...') ?></div>
        <div id="baEmpty" class="empty-state" style="display:none;"><?= pay_t('no_bank_accounts', 'No bank accounts found') ?></div>
        <div class="table-responsive" id="baTableWrap" style="display:none;">
            <table class="data-table" id="bankAccountsTable">
                <thead>
                    <tr>
                        <th><?= pay_t('id', 'ID') ?></th>
                        <th><?= pay_t('bank_name', 'Bank Name') ?></th>
                        <th><?= pay_t('account_holder', 'Account Holder') ?></th>
                        <th><?= pay_t('account_number', 'Account Number') ?></th>
                        <th><?= pay_t('iban', 'IBAN') ?></th>
                        <th><?= pay_t('swift_code', 'SWIFT Code') ?></th>
                        <th><?= pay_t('primary', 'Primary') ?></th>
                        <th><?= pay_t('verified', 'Verified') ?></th>
                        <th><?= pay_t('created_at', 'Created At') ?></th>
                        <?php if ($canManage): ?>
                        <th><?= pay_t('actions', 'Actions') ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="bankAccountsBody"></tbody>
            </table>
        </div>
    </div>
        </div>
    </div>

<!-- Payment Method Modal -->
<div class="modal-overlay" id="paymentMethodModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="pmFormTitle"><?= pay_t('add_payment_method', 'Add Payment Method') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="pmCancel" aria-label="<?= pay_t('close', 'Close') ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="paymentMethodForm">
            <input type="hidden" id="pmId" value="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="pmGateway"><?= pay_t('gateway_name', 'Gateway') ?> *</label>
                    <select id="pmGateway" class="form-control" required>
                        <option value=""><?= pay_t('select', 'Select...') ?></option>
                        <option value="stripe">Stripe</option>
                        <option value="paypal">PayPal</option>
                        <option value="moyasar">Moyasar</option>
                        <option value="tap">Tap</option>
                        <option value="paytabs">PayTabs</option>
                        <option value="credit_card"><?= pay_t('credit_card', 'Credit Card') ?></option>
                        <option value="bank_transfer"><?= pay_t('bank_transfer', 'Bank Transfer') ?></option>
                        <option value="cash"><?= pay_t('cash', 'Cash') ?></option>
                        <option value="other"><?= pay_t('other', 'Other') ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="pmEmail"><?= pay_t('account_email', 'Account Email') ?></label>
                    <input type="email" id="pmEmail" class="form-control">
                </div>
                <div class="form-group">
                    <label for="pmAccountId"><?= pay_t('account_id', 'Account ID') ?></label>
                    <input type="text" id="pmAccountId" class="form-control">
                </div>
                <div class="form-group">
                    <label for="pmActive"><?= pay_t('active', 'Active') ?></label>
                    <select id="pmActive" class="form-control">
                        <option value="1"><?= pay_t('yes', 'Yes') ?></option>
                        <option value="0"><?= pay_t('no', 'No') ?></option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline pm-cancel-btn"><?= pay_t('cancel', 'Cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= pay_t('save', 'Save') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Bank Account Modal -->
<div class="modal-overlay" id="bankAccountModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="baFormTitle"><?= pay_t('add_bank_account', 'Add Bank Account') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="baCancel" aria-label="<?= pay_t('close', 'Close') ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form id="bankAccountForm">
            <input type="hidden" id="baId" value="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="baBankName"><?= pay_t('bank_name', 'Bank Name') ?> *</label>
                    <input type="text" id="baBankName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="baHolderName"><?= pay_t('account_holder_name', 'Account Holder Name') ?> *</label>
                    <input type="text" id="baHolderName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="baAccountNumber"><?= pay_t('account_number', 'Account Number') ?> *</label>
                    <input type="text" id="baAccountNumber" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="baIban"><?= pay_t('iban', 'IBAN') ?></label>
                    <input type="text" id="baIban" class="form-control">
                </div>
                <div class="form-group">
                    <label for="baSwift"><?= pay_t('swift_code', 'SWIFT Code') ?></label>
                    <input type="text" id="baSwift" class="form-control">
                </div>
                <div class="form-group">
                    <label for="baPrimary"><?= pay_t('is_primary', 'Primary') ?></label>
                    <select id="baPrimary" class="form-control">
                        <option value="0"><?= pay_t('no', 'No') ?></option>
                        <option value="1"><?= pay_t('yes', 'Yes') ?></option>
                    </select>
                </div>
                <?php if ($canManage): ?>
                <div class="form-group">
                    <label for="baVerified"><?= pay_t('is_verified', 'Verified') ?></label>
                    <select id="baVerified" class="form-control">
                        <option value="0"><?= pay_t('no', 'No') ?></option>
                        <option value="1"><?= pay_t('yes', 'Yes') ?></option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline ba-cancel-btn"><?= pay_t('cancel', 'Cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= pay_t('save', 'Save') ?></button>
            </div>
        </form>
    </div>
</div>

</div><!-- end page-container -->

<div class="notification" id="notification"></div>

<script>
window.ENTITIES_PAYMENT_CONFIG = {
    entityId: <?= $entityId ?>,
    tenantId: <?= $tenantId ?>,
    userId: <?= $userId ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    apiBase: '/api',
    canManage: <?= $canManage ? 'true' : 'false' ?>,
    canView: <?= $canView ? 'true' : 'false' ?>,
    canCreate: <?= ($canCreate || $canManage) ? 'true' : 'false' ?>,
    canEdit: <?= $canEdit ? 'true' : 'false' ?>,
    canDelete: <?= $canDelete ? 'true' : 'false' ?>,
    isSuperAdmin: <?= is_super_admin() ? 'true' : 'false' ?>,
    lang: <?= json_encode($lang) ?>,
    texts: {
        addPaymentMethod: <?= json_encode(pay_t('add_payment_method', 'Add Payment Method')) ?>,
        editPaymentMethod: <?= json_encode(pay_t('edit_payment_method', 'Edit Payment Method')) ?>,
        addBankAccount: <?= json_encode(pay_t('add_bank_account', 'Add Bank Account')) ?>,
        editBankAccount: <?= json_encode(pay_t('edit_bank_account', 'Edit Bank Account')) ?>,
        confirmDelete: <?= json_encode(pay_t('confirm_delete', 'Are you sure you want to delete this record?')) ?>,
        saved: <?= json_encode(pay_t('saved', 'Saved successfully')) ?>,
        deleted: <?= json_encode(pay_t('deleted', 'Deleted successfully')) ?>,
        saveFailed: <?= json_encode(pay_t('save_failed', 'Failed to save')) ?>,
        deleteFailed: <?= json_encode(pay_t('delete_failed', 'Failed to delete')) ?>,
        yes: <?= json_encode(pay_t('yes', 'Yes')) ?>,
        no: <?= json_encode(pay_t('no', 'No')) ?>
    }
};
</script>
<script src="/admin/assets/js/pages/entities_payment.js?v=<?= time() ?>"></script>
