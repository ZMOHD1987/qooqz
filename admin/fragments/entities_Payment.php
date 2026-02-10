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
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

// Permissions
$canManagePayment = can('manage_entities') || can('manage_entity_payments') || is_super_admin();
$canView          = can_view_all('entities') || can_view_own('entities') || can_view_tenant('entities') || is_super_admin();

// Access check
if (!$canView && !is_super_admin()) {
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
?>

<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/entities_payment.css?v=<?= time() ?>">
<?php endif; ?>

<div class="page-container" id="entitiesPaymentPage" dir="<?= htmlspecialchars($dir) ?>">

    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

    <!-- Entity Selector (when no entity_id passed) -->
    <?php if (!$entityId): ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="form-group">
                <label for="entitySelector">Select Entity</label>
                <select id="entitySelector" class="form-control">
                    <option value="">-- Select Entity --</option>
                </select>
            </div>
            <button type="button" class="btn btn-primary mt-2" id="btnLoadEntityPayments" disabled>Load</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabs & Content (hidden until entity is selected) -->
    <div class="content-tabs" style="<?= $entityId ? '' : 'display:none' ?>">
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link active" href="#" data-tab="payment_methods">Payment Methods</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" data-tab="bank_accounts">Bank Accounts</a>
            </li>
        </ul>

        <!-- Payment Methods Tab -->
        <div class="tab-pane active" id="tab-payment_methods">
            <div class="d-flex justify-content-between mb-2">
                <h5>Payment Methods</h5>
                <button class="btn btn-sm btn-primary" id="btnAddPaymentMethod">+ Add Payment Method</button>
            </div>
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Gateway</th>
                        <th>Account Email</th>
                        <th>Account ID</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="paymentMethodsBody"></tbody>
            </table>
        </div>

        <!-- Bank Accounts Tab -->
        <div class="tab-pane" id="tab-bank_accounts" style="display:none">
            <div class="d-flex justify-content-between mb-2">
                <h5>Bank Accounts</h5>
                <button class="btn btn-sm btn-primary" id="btnAddBankAccount">+ Add Bank Account</button>
            </div>
            <table class="table table-bordered table-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bank Name</th>
                        <th>Account Holder</th>
                        <th>Account Number</th>
                        <th>IBAN</th>
                        <th>Primary</th>
                        <th>Verified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="bankAccountsBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment Method Modal -->
<div class="modal-overlay" id="paymentMethodModal" style="display:none">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 id="pmFormTitle">Add Payment Method</h5>
            <button type="button" class="close-btn" onclick="document.getElementById('paymentMethodModal').style.display='none'">&times;</button>
        </div>
        <form id="paymentMethodForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="entity_id" id="pmEntityId" value="<?= $entityId ?>">
            <input type="hidden" name="id" id="pmId" value="">
            <div class="form-group">
                <label>Gateway *</label>
                <select name="gateway_name" id="pmGateway" class="form-control" required>
                    <option value="">Select...</option>
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
                <label>Account Email</label>
                <input type="email" name="account_email" id="pmEmail" class="form-control">
            </div>
            <div class="form-group">
                <label>Account ID</label>
                <input type="text" name="account_id" id="pmAccountId" class="form-control">
            </div>
            <div class="form-group">
                <label>Active</label>
                <select name="is_active" id="pmActive" class="form-control">
                    <option value="1">Yes</option>
                    <option value="0">No</option>
                </select>
            </div>
            <div class="form-actions mt-3">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('paymentMethodModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Bank Account Modal -->
<div class="modal-overlay" id="bankAccountModal" style="display:none">
    <div class="modal-dialog">
        <div class="modal-header">
            <h5 id="baFormTitle">Add Bank Account</h5>
            <button type="button" class="close-btn" onclick="document.getElementById('bankAccountModal').style.display='none'">&times;</button>
        </div>
        <form id="bankAccountForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="entity_id" id="baEntityId" value="<?= $entityId ?>">
            <input type="hidden" name="id" id="baId" value="">
            <div class="form-group">
                <label>Bank Name *</label>
                <input type="text" name="bank_name" id="baBankName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Account Holder Name *</label>
                <input type="text" name="account_holder_name" id="baHolderName" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Account Number *</label>
                <input type="text" name="account_number" id="baAccountNumber" class="form-control" required>
            </div>
            <div class="form-group">
                <label>IBAN</label>
                <input type="text" name="iban" id="baIban" class="form-control">
            </div>
            <div class="form-group">
                <label>SWIFT Code</label>
                <input type="text" name="swift_code" id="baSwift" class="form-control">
            </div>
            <div class="form-group">
                <label>Primary</label>
                <select name="is_primary" id="baPrimary" class="form-control">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
            </div>
            <div class="form-group">
                <label>Verified</label>
                <select name="is_verified" id="baVerified" class="form-control">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
            </div>
            <div class="form-actions mt-3">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('bankAccountModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
window.entityId = <?= $entityId ?>;
</script>
<script src="/admin/assets/js/pages/entities_payment.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>
