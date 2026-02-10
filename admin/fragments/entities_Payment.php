<?php
declare(strict_types=1);

/**
 * admin/fragments/entities_Payment.php
 * Embedded entity payment management fragment for the admin panel.
 * Loaded as iframe or standalone with URL like:
 *   /admin/fragments/entities_Payment.php?embedded=1&tenant_id=1&lang=ar&entity_id=5
 */

// Bootstrap admin UI
$bootstrap = __DIR__ . '/../../api/bootstrap_admin_ui.php';
if (is_readable($bootstrap)) {
    try { require_once $bootstrap; } catch (Throwable $e) {}
}

$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? ($GLOBALS['ADMIN_UI'] ?? []);
$lang      = $_GET['lang'] ?? $ADMIN_UI_PAYLOAD['lang'] ?? 'ar';
$rtlLangs  = ['ar', 'fa', 'he', 'ur', 'ps', 'sd', 'ku'];
$direction = in_array(substr($lang, 0, 2), $rtlLangs, true) ? 'rtl' : 'ltr';
$strings   = $ADMIN_UI_PAYLOAD['strings'] ?? [];
$theme     = $ADMIN_UI_PAYLOAD['theme'] ?? [];

$csrf      = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);
$tenantId  = (int)($_GET['tenant_id'] ?? $_SESSION['tenant_id'] ?? 1);
$entityId  = (int)($_GET['entity_id'] ?? 0);
$embedded  = !empty($_GET['embedded']);
$canManage = true;

// Theme colors
$primaryColor  = $theme['colors_map']['primary'] ?? '#2563eb';
$background    = $theme['colors_map']['background'] ?? '#0f0f0f';
$cardBg        = $theme['colors_map']['card-background'] ?? $theme['colors_map']['background-card'] ?? '#1a1a1a';
$textPrimary   = $theme['colors_map']['text-primary'] ?? '#ffffff';
$textSecondary = $theme['colors_map']['text-secondary'] ?? '#cccccc';
$borderColor   = $theme['colors_map']['border'] ?? '#333333';
$dangerBg      = $theme['colors_map']['danger'] ?? '#450a0a';
$dangerText    = $theme['colors_map']['danger-text'] ?? '#f87171';
$successColor  = '#16a34a';
$fontFamily    = $theme['fonts'][0]['font_family'] ?? "'Segoe UI', sans-serif";

// Translation helper
function pay_t(string $key, string $fallback = ''): string {
    global $strings;
    return $strings[$key] ?? $fallback;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($direction) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= pay_t('entity_payments', 'Entity Payments') ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: <?= $fontFamily ?>;
    background: <?= $embedded ? 'transparent' : $background ?>;
    color: <?= $textPrimary ?>;
    direction: <?= $direction ?>;
    padding: <?= $embedded ? '0' : '20px' ?>;
    min-height: <?= $embedded ? 'auto' : '100vh' ?>;
}
.pay-card {
    background: <?= $cardBg ?>;
    border: 1px solid <?= $borderColor ?>;
    border-radius: 8px;
    padding: 20px;
}
.pay-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid <?= $borderColor ?>;
}
.pay-header h2 { color: <?= $primaryColor ?>; font-size: 1.1rem; }
.pay-header-actions { display: flex; gap: 8px; }

/* Entity selector */
.entity-selector-wrap {
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid <?= $borderColor ?>;
}
.entity-selector-wrap label { font-size: 0.8rem; color: <?= $textSecondary ?>; margin-bottom: 4px; display: block; }
.entity-selector-wrap select {
    width: 100%;
    padding: 8px 10px;
    background: <?= $background ?>;
    border: 1px solid <?= $borderColor ?>;
    color: <?= $textPrimary ?>;
    border-radius: 4px;
    font-size: 0.85rem;
}
.entity-selector-wrap select:focus { outline: none; border-color: <?= $primaryColor ?>; }

/* Tabs */
.pay-tabs { display: flex; gap: 0; margin-bottom: 16px; border-bottom: 2px solid <?= $borderColor ?>; }
.pay-tab-btn {
    padding: 8px 18px;
    border: none;
    background: transparent;
    color: <?= $textSecondary ?>;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: color 0.2s, border-color 0.2s;
}
.pay-tab-btn:hover { color: <?= $textPrimary ?>; }
.pay-tab-btn.active { color: <?= $primaryColor ?>; border-bottom-color: <?= $primaryColor ?>; }
.pay-tab-content { display: none; }
.pay-tab-content.active { display: block; }

/* Buttons */
.btn {
    padding: 6px 14px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.8rem;
    transition: opacity 0.2s;
}
.btn:hover { opacity: 0.85; }
.btn-primary { background: <?= $primaryColor ?>; color: #fff; }
.btn-gray { background: <?= $borderColor ?>; color: <?= $textSecondary ?>; }
.btn-danger { background: <?= $dangerBg ?>; color: <?= $dangerText ?>; }
.btn-sm { padding: 4px 10px; font-size: 0.75rem; }

/* Data table */
.pay-table-wrap { overflow-x: auto; }
.pay-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.pay-table th, .pay-table td {
    padding: 8px 10px;
    text-align: <?= $direction === 'rtl' ? 'right' : 'left' ?>;
    border-bottom: 1px solid <?= $borderColor ?>;
    white-space: nowrap;
}
.pay-table th {
    background: <?= $background ?>;
    color: <?= $textSecondary ?>;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
}
.pay-table tbody tr:hover { background: <?= $background ?>; }
.pay-table .actions-cell { display: flex; gap: 4px; }

.badge-yes {
    display: inline-block;
    background: <?= $successColor ?>;
    color: #fff;
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 10px;
}
.badge-no {
    display: inline-block;
    background: <?= $borderColor ?>;
    color: <?= $textSecondary ?>;
    font-size: 0.7rem;
    padding: 2px 8px;
    border-radius: 10px;
}

/* Modal */
.pay-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 9998;
    justify-content: center;
    align-items: center;
}
.pay-modal-overlay.open { display: flex; }
.pay-modal {
    background: <?= $cardBg ?>;
    border: 1px solid <?= $borderColor ?>;
    border-radius: 8px;
    padding: 20px;
    width: 90%;
    max-width: 520px;
    max-height: 90vh;
    overflow-y: auto;
}
.pay-modal h3 {
    color: <?= $primaryColor ?>;
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid <?= $borderColor ?>;
    font-size: 0.95rem;
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: 0.8rem; color: <?= $textSecondary ?>; }
.form-group input, .form-group select {
    padding: 8px 10px;
    background: <?= $background ?>;
    border: 1px solid <?= $borderColor ?>;
    color: <?= $textPrimary ?>;
    border-radius: 4px;
    font-size: 0.85rem;
}
.form-group input:focus, .form-group select:focus {
    outline: none;
    border-color: <?= $primaryColor ?>;
}
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 14px;
}

.empty-state {
    text-align: center;
    padding: 30px;
    color: <?= $textSecondary ?>;
    font-size: 0.9rem;
}
.loading-state {
    text-align: center;
    padding: 30px;
    color: <?= $textSecondary ?>;
}
.notification {
    position: fixed;
    top: 10px;
    left: 50%;
    transform: translateX(-50%);
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    z-index: 9999;
    opacity: 0;
    transition: opacity 0.3s;
}
.notification.show { opacity: 1; }
.notification.success { background: <?= $successColor ?>; color: #fff; }
.notification.error { background: <?= $dangerBg ?>; color: <?= $dangerText ?>; }

@media (max-width: 600px) {
    .form-grid { grid-template-columns: 1fr; }
    .pay-header { flex-direction: column; gap: 8px; align-items: flex-start; }
}
</style>
</head>
<body>

<div class="pay-card">
    <div class="pay-header">
        <h2><?= pay_t('entity_payments', 'Entity Payments') ?></h2>
        <div class="pay-header-actions">
            <button type="button" class="btn btn-primary" id="btnAddPaymentMethod">+ <?= pay_t('add_payment_method', 'Add Payment Method') ?></button>
            <button type="button" class="btn btn-primary" id="btnAddBankAccount">+ <?= pay_t('add_bank_account', 'Add Bank Account') ?></button>
        </div>
    </div>

    <?php if (!$entityId): ?>
    <div class="entity-selector-wrap" id="entitySelectorWrap">
        <label for="entitySelector"><?= pay_t('select_entity', 'Select Entity') ?></label>
        <select id="entitySelector">
            <option value=""><?= pay_t('select_entity_placeholder', '-- Select Entity --') ?></option>
        </select>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="pay-tabs">
        <button type="button" class="pay-tab-btn active" data-tab="payment_methods"><?= pay_t('payment_methods', 'Payment Methods') ?></button>
        <button type="button" class="pay-tab-btn" data-tab="bank_accounts"><?= pay_t('bank_accounts', 'Bank Accounts') ?></button>
    </div>

    <!-- Payment Methods Tab -->
    <div class="pay-tab-content active" id="tab-payment_methods">
        <div id="pmLoading" class="loading-state"><?= pay_t('loading', 'Loading...') ?></div>
        <div id="pmEmpty" class="empty-state" style="display:none;"><?= pay_t('no_payment_methods', 'No payment methods found') ?></div>
        <div class="pay-table-wrap" id="pmTableWrap" style="display:none;">
            <table class="pay-table" id="paymentMethodsTable">
                <thead>
                    <tr>
                        <th><?= pay_t('id', 'ID') ?></th>
                        <th><?= pay_t('gateway', 'Gateway') ?></th>
                        <th><?= pay_t('account_email', 'Account Email') ?></th>
                        <th><?= pay_t('account_id', 'Account ID') ?></th>
                        <th><?= pay_t('active', 'Active') ?></th>
                        <th><?= pay_t('created_at', 'Created At') ?></th>
                        <th><?= pay_t('actions', 'Actions') ?></th>
                    </tr>
                </thead>
                <tbody id="paymentMethodsBody"></tbody>
            </table>
        </div>
    </div>

    <!-- Bank Accounts Tab -->
    <div class="pay-tab-content" id="tab-bank_accounts">
        <div id="baLoading" class="loading-state"><?= pay_t('loading', 'Loading...') ?></div>
        <div id="baEmpty" class="empty-state" style="display:none;"><?= pay_t('no_bank_accounts', 'No bank accounts found') ?></div>
        <div class="pay-table-wrap" id="baTableWrap" style="display:none;">
            <table class="pay-table" id="bankAccountsTable">
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
                        <th><?= pay_t('actions', 'Actions') ?></th>
                    </tr>
                </thead>
                <tbody id="bankAccountsBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment Method Modal -->
<div class="pay-modal-overlay" id="paymentMethodModal">
    <div class="pay-modal">
        <h3 id="pmFormTitle"><?= pay_t('add_payment_method', 'Add Payment Method') ?></h3>
        <form id="paymentMethodForm">
            <input type="hidden" id="pmId" value="">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="pmGateway"><?= pay_t('gateway_name', 'Gateway') ?> *</label>
                    <select id="pmGateway" required>
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
                    <input type="email" id="pmEmail">
                </div>
                <div class="form-group">
                    <label for="pmAccountId"><?= pay_t('account_id', 'Account ID') ?></label>
                    <input type="text" id="pmAccountId">
                </div>
                <div class="form-group">
                    <label for="pmActive"><?= pay_t('active', 'Active') ?></label>
                    <select id="pmActive">
                        <option value="1"><?= pay_t('yes', 'Yes') ?></option>
                        <option value="0"><?= pay_t('no', 'No') ?></option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-gray" id="pmCancel"><?= pay_t('cancel', 'Cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= pay_t('save', 'Save') ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Bank Account Modal -->
<div class="pay-modal-overlay" id="bankAccountModal">
    <div class="pay-modal">
        <h3 id="baFormTitle"><?= pay_t('add_bank_account', 'Add Bank Account') ?></h3>
        <form id="bankAccountForm">
            <input type="hidden" id="baId" value="">
            <div class="form-grid">
                <div class="form-group">
                    <label for="baBankName"><?= pay_t('bank_name', 'Bank Name') ?> *</label>
                    <input type="text" id="baBankName" required>
                </div>
                <div class="form-group">
                    <label for="baHolderName"><?= pay_t('account_holder_name', 'Account Holder Name') ?> *</label>
                    <input type="text" id="baHolderName" required>
                </div>
                <div class="form-group">
                    <label for="baAccountNumber"><?= pay_t('account_number', 'Account Number') ?> *</label>
                    <input type="text" id="baAccountNumber" required>
                </div>
                <div class="form-group">
                    <label for="baIban"><?= pay_t('iban', 'IBAN') ?></label>
                    <input type="text" id="baIban">
                </div>
                <div class="form-group">
                    <label for="baSwift"><?= pay_t('swift_code', 'SWIFT Code') ?></label>
                    <input type="text" id="baSwift">
                </div>
                <div class="form-group">
                    <label for="baPrimary"><?= pay_t('is_primary', 'Primary') ?></label>
                    <select id="baPrimary">
                        <option value="0"><?= pay_t('no', 'No') ?></option>
                        <option value="1"><?= pay_t('yes', 'Yes') ?></option>
                    </select>
                </div>
                <?php if ($canManage): ?>
                <div class="form-group">
                    <label for="baVerified"><?= pay_t('is_verified', 'Verified') ?></label>
                    <select id="baVerified">
                        <option value="0"><?= pay_t('no', 'No') ?></option>
                        <option value="1"><?= pay_t('yes', 'Yes') ?></option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-gray" id="baCancel"><?= pay_t('cancel', 'Cancel') ?></button>
                <button type="submit" class="btn btn-primary"><?= pay_t('save', 'Save') ?></button>
            </div>
        </form>
    </div>
</div>

<div class="notification" id="notification"></div>

<script>
window.ENTITIES_PAYMENT_CONFIG = {
    entityId: <?= $entityId ?>,
    tenantId: <?= $tenantId ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    apiBase: '/api',
    canManage: <?= $canManage ? 'true' : 'false' ?>,
    lang: <?= json_encode($lang) ?>,
    embedded: <?= $embedded ? 'true' : 'false' ?>,
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

</body>
</html>
