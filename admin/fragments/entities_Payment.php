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

    <!-- Entity Selector -->
    <?php if (!$entityId): ?>
    <div class="card">
        <div class="card-body">
            <select id="entitySelector" class="form-control">
                <option value="" data-i18n="select_entity"><?= htmlspecialchars(_pt('select_entity', 'Select Entity...')) ?></option>
            </select>
            <button id="btnLoadEntityPayments" class="btn btn-primary" disabled data-i18n="load"><?= htmlspecialchars(_pt('load', 'Load')) ?></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="content-tabs" <?= $entityId ? '' : 'style="display:none;"' ?>>
        <button class="tab-btn active" data-tab="payment_methods" data-i18n="payment_methods.title"><?= htmlspecialchars(_pt('payment_methods.title', 'Payment Methods')) ?></button>
        <button class="tab-btn" data-tab="bank_accounts" data-i18n="bank_accounts.title"><?= htmlspecialchars(_pt('bank_accounts.title', 'Bank Accounts')) ?></button>

        <!-- Payment Methods -->
        <div class="tab-content active" id="tab-payment_methods">
            <table class="data-table" id="paymentMethodsTable">
                <thead>
                    <tr>
                        <th data-i18n="table.id"><?= htmlspecialchars(_pt('table.id', 'ID')) ?></th>
                        <th data-i18n="payment_methods.gateway"><?= htmlspecialchars(_pt('payment_methods.gateway', 'Gateway')) ?></th>
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
                    <label data-i18n="payment_methods.gateway"><?= htmlspecialchars(_pt('payment_methods.gateway', 'Gateway')) ?> *</label>
                    <select name="gateway_name" id="pmGateway" class="form-control" required>
                        <option value="" data-i18n="payment_methods.select_gateway"><?= htmlspecialchars(_pt('payment_methods.select_gateway', 'Select')) ?></option>
                        <option value="stripe">Stripe</option>
                        <option value="paypal">PayPal</option>
                        <option value="moyasar">Moyasar</option>
                        <option value="tap">Tap</option>
                        <option value="paytabs">PayTabs</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="other">Other</option>
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
(function(){
    var API_BASE = '<?= $apiBase ?>';
    var CSRF = <?= json_encode($csrf) ?>;
    var STRINGS = <?= json_encode($_paymentStrings, JSON_UNESCAPED_UNICODE) ?>;

    // Translation helper - resolves dot-separated keys
    function t(key, fallback) {
        var keys = key.split('.');
        var val = STRINGS;
        for (var i = 0; i < keys.length; i++) {
            if (val && typeof val === 'object' && keys[i] in val) {
                val = val[keys[i]];
            } else {
                return fallback || key;
            }
        }
        return (typeof val === 'string') ? val : (fallback || key);
    }

    // Modal helpers
    window.openModal = function(id){ document.getElementById(id).style.display='block'; };
    window.closeModal = function(id){ document.getElementById(id).style.display='none'; };

    // Close modal buttons (delegated)
    document.querySelectorAll('.btn-close-modal').forEach(function(btn){
        btn.addEventListener('click', function(){ closeModal(btn.dataset.modal); });
    });

    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.querySelectorAll('.tab-btn').forEach(function(b){ b.classList.remove('active'); });
            document.querySelectorAll('.tab-content').forEach(function(c){ c.classList.remove('active'); });
            btn.classList.add('active');
            var target = document.getElementById('tab-' + btn.dataset.tab);
            if(target) target.classList.add('active');
        });
    });

    // Add Payment / Bank buttons
    document.getElementById('btnAddPayment')?.addEventListener('click', function(){
        document.getElementById('paymentModalTitle').textContent = t('payment_methods.add', 'Add Payment Method');
        document.getElementById('paymentMethodForm').reset();
        document.getElementById('pmEditId').value = '';
        openModal('paymentMethodModal');
    });
    document.getElementById('btnAddBank')?.addEventListener('click', function(){
        document.getElementById('bankModalTitle').textContent = t('bank_accounts.add', 'Add Bank Account');
        document.getElementById('bankAccountForm').reset();
        document.getElementById('baEditId').value = '';
        openModal('bankAccountModal');
    });

    // Entity selector logic
    var entitySelector = document.getElementById('entitySelector');
    var btnLoad = document.getElementById('btnLoadEntityPayments');
    var entityId = <?= $entityId ?>;

    if(entitySelector){
        fetch(API_BASE + '/entities')
        .then(function(r){ return r.json(); })
        .then(function(d){
            if(d.success && d.data && d.data.items){
                var defaultEntityId = null;
                d.data.items.forEach(function(ent){
                    var opt = document.createElement('option');
                    opt.value = ent.id;
                    opt.textContent = ent.store_name || ent.name || ('Entity #' + ent.id);
                    if(ent.is_main == 1 && !defaultEntityId) defaultEntityId = ent.id;
                    entitySelector.appendChild(opt);
                });

                if(!defaultEntityId && d.data.items.length > 0) defaultEntityId = d.data.items[0].id;

                if(defaultEntityId){
                    entitySelector.value = defaultEntityId;
                    if(btnLoad) btnLoad.disabled = false;
                }

                entitySelector.addEventListener('change', function(){
                    if(btnLoad) btnLoad.disabled = !entitySelector.value;
                });

                if(btnLoad){
                    btnLoad.addEventListener('click', function(){
                        var val = entitySelector.value;
                        if(!val) return;
                        entityId = parseInt(val);
                        document.querySelectorAll('input[name="entity_id"]').forEach(function(inp){
                            inp.value = entityId;
                        });
                        document.querySelector('.content-tabs').style.display = 'block';
                        loadPayments();
                        loadBanks();
                    });
                }

                if(defaultEntityId && btnLoad){
                    btnLoad.click();
                }
            }
        });
    }

    // Submit Payment Method
    document.getElementById('paymentMethodForm')?.addEventListener('submit', function(e){
        e.preventDefault();
        if(!entityId){ alert(t('select_entity_first', 'Please select an entity first.')); return; }
        var editId = document.getElementById('pmEditId').value;
        var method = editId ? 'PUT' : 'POST';
        fetch(API_BASE + '/entity_payment_methods', {
            method: method,
            headers: {'X-CSRF-TOKEN': CSRF},
            body: new FormData(this)
        }).then(function(r){ return r.json(); }).then(function(d){
            if(d.success){
                closeModal('paymentMethodModal');
                document.getElementById('paymentMethodForm').reset();
                document.getElementById('pmEditId').value = '';
                alert(t('saved', 'Saved successfully'));
                loadPayments();
            } else {
                alert(d.message || t('unknown_error', 'Unknown error'));
            }
        });
    });

    // Submit Bank Account
    document.getElementById('bankAccountForm')?.addEventListener('submit', function(e){
        e.preventDefault();
        if(!entityId){ alert(t('select_entity_first', 'Please select an entity first.')); return; }
        var editId = document.getElementById('baEditId').value;
        var method = editId ? 'PUT' : 'POST';
        fetch(API_BASE + '/entity_bank_accounts', {
            method: method,
            headers: {'X-CSRF-TOKEN': CSRF},
            body: new FormData(this)
        }).then(function(r){ return r.json(); }).then(function(d){
            if(d.success){
                closeModal('bankAccountModal');
                document.getElementById('bankAccountForm').reset();
                document.getElementById('baEditId').value = '';
                alert(t('saved', 'Saved successfully'));
                loadBanks();
            } else {
                alert(d.message || t('unknown_error', 'Unknown error'));
            }
        });
    });

    // XSS escape helper
    function esc(str){
        if(str == null) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    // Load Payment Methods
    function loadPayments(){
        if(!entityId) return;
        fetch(API_BASE + '/entity_payment_methods?entity_id=' + entityId)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('paymentMethodsBody');
            tbody.innerHTML = '';
            if(d.success && d.data){
                var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                items.forEach(function(p){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(p.id) + '</td>' +
                        '<td>' + esc(p.gateway_name) + '</td>' +
                        '<td>' + esc(p.account_email || '') + '</td>' +
                        '<td>' + esc(p.account_id || '') + '</td>' +
                        '<td>' + (p.is_active ? t('table.yes', 'Yes') : t('table.no', 'No')) + '</td>' +
                        '<td>' +
                            '<button class="btn btn-sm btn-info edit-payment-btn" data-id="' + esc(p.id) + '">' + t('table.edit', 'Edit') + '</button> ' +
                            '<button class="btn btn-sm btn-danger delete-payment-btn" data-id="' + esc(p.id) + '">' + t('table.delete', 'Delete') + '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
            }
        });
    }

    // Load Bank Accounts
    function loadBanks(){
        if(!entityId) return;
        fetch(API_BASE + '/entity_bank_accounts?entity_id=' + entityId)
        .then(function(r){ return r.json(); })
        .then(function(d){
            var tbody = document.getElementById('bankAccountsBody');
            tbody.innerHTML = '';
            if(d.success && d.data){
                var items = Array.isArray(d.data) ? d.data : (d.data.items || []);
                items.forEach(function(b){
                    var tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(b.id) + '</td>' +
                        '<td>' + esc(b.bank_name) + '</td>' +
                        '<td>' + esc(b.account_holder_name) + '</td>' +
                        '<td>' + esc(b.account_number) + '</td>' +
                        '<td>' + esc(b.iban || '') + '</td>' +
                        '<td>' + esc(b.swift_code || '') + '</td>' +
                        '<td>' + (b.is_primary ? t('table.yes', 'Yes') : t('table.no', 'No')) + '</td>' +
                        '<td>' + (b.is_verified ? t('table.yes', 'Yes') : t('table.no', 'No')) + '</td>' +
                        '<td>' +
                            '<button class="btn btn-sm btn-info edit-bank-btn" data-id="' + esc(b.id) + '">' + t('table.edit', 'Edit') + '</button> ' +
                            '<button class="btn btn-sm btn-danger delete-bank-btn" data-id="' + esc(b.id) + '">' + t('table.delete', 'Delete') + '</button>' +
                        '</td>';
                    tbody.appendChild(tr);
                });
            }
        });
    }

    // Edit Payment - delegated click
    document.addEventListener('click', function(e){
        var editPm = e.target.closest('.edit-payment-btn');
        if(editPm){
            var recId = editPm.dataset.id;
            fetch(API_BASE + '/entity_payment_methods?entity_id=' + entityId + '&id=' + recId)
            .then(function(r){ return r.json(); })
            .then(function(d){
                if(d.success && d.data){
                    var rec = d.data;
                    document.getElementById('pmEditId').value = rec.id;
                    document.getElementById('pmGateway').value = rec.gateway_name || '';
                    document.getElementById('pmEmail').value = rec.account_email || '';
                    document.getElementById('pmAccountId').value = rec.account_id || '';
                    document.getElementById('pmActive').value = rec.is_active ? '1' : '0';
                    document.getElementById('paymentModalTitle').textContent = t('payment_methods.edit', 'Edit Payment Method');
                    openModal('paymentMethodModal');
                }
            });
            return;
        }

        var editBa = e.target.closest('.edit-bank-btn');
        if(editBa){
            var recId2 = editBa.dataset.id;
            fetch(API_BASE + '/entity_bank_accounts?entity_id=' + entityId + '&id=' + recId2)
            .then(function(r){ return r.json(); })
            .then(function(d){
                if(d.success && d.data){
                    var rec = d.data;
                    document.getElementById('baEditId').value = rec.id;
                    document.getElementById('baBankName').value = rec.bank_name || '';
                    document.getElementById('baHolderName').value = rec.account_holder_name || '';
                    document.getElementById('baAccountNumber').value = rec.account_number || '';
                    document.getElementById('baIban').value = rec.iban || '';
                    document.getElementById('baSwift').value = rec.swift_code || '';
                    document.getElementById('baPrimary').value = rec.is_primary ? '1' : '0';
                    document.getElementById('baVerified').value = rec.is_verified ? '1' : '0';
                    document.getElementById('bankModalTitle').textContent = t('bank_accounts.edit', 'Edit Bank Account');
                    openModal('bankAccountModal');
                }
            });
            return;
        }

        // Delete Payment
        var delPm = e.target.closest('.delete-payment-btn');
        if(delPm){
            if(!confirm(t('confirm_delete_payment', 'Are you sure you want to delete this payment method?'))) return;
            var delId = delPm.dataset.id;
            fetch(API_BASE + '/entity_payment_methods?id=' + delId + '&entity_id=' + entityId, {
                method: 'DELETE',
                headers: {'X-CSRF-TOKEN': CSRF}
            }).then(function(r){ return r.json(); }).then(function(d){
                if(d.success){ alert(t('deleted', 'Deleted successfully')); loadPayments(); }
                else alert(d.message || t('delete_failed', 'Delete failed'));
            });
            return;
        }

        // Delete Bank
        var delBa = e.target.closest('.delete-bank-btn');
        if(delBa){
            if(!confirm(t('confirm_delete_bank', 'Are you sure you want to delete this bank account?'))) return;
            var delId2 = delBa.dataset.id;
            fetch(API_BASE + '/entity_bank_accounts?id=' + delId2 + '&entity_id=' + entityId, {
                method: 'DELETE',
                headers: {'X-CSRF-TOKEN': CSRF}
            }).then(function(r){ return r.json(); }).then(function(d){
                if(d.success){ alert(t('deleted', 'Deleted successfully')); loadBanks(); }
                else alert(d.message || t('delete_failed', 'Delete failed'));
            });
            return;
        }
    });

    // Load initial data if entityId exists
    if(entityId){
        loadPayments();
        loadBanks();
    }
})();
</script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>
