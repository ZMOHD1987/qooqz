<?php
declare(strict_types=1);

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// Read context
$payload = $GLOBALS['ADMIN_UI'] ?? [];
$user = $payload['user'] ?? (function_exists('admin_user') ? admin_user() : []);
$permissions = $user['permissions'] ?? [];
$roles = $user['roles'] ?? [];
$lang = $payload['lang'] ?? ($user['preferred_language'] ?? 'en');
$dir = $payload['direction'] ?? (in_array($lang, ['ar','he','fa','ur']) ? 'rtl' : 'ltr');
$csrf = $payload['csrf_token'] ?? (function_exists('admin_csrf') ? admin_csrf() : '');

// User context
$tenantId = $payload['tenant_id'] ?? ($_SESSION['tenant_id'] ?? 0);
$entityId = $payload['entity_id'] ?? ($_SESSION['entity_id'] ?? 0);
$userId   = $user['id'] ?? ($_SESSION['user_id'] ?? 0);

// Permissions
$isSuperAdmin = in_array('super_admin', $roles, true) || (function_exists('is_super_admin') && is_super_admin());
$canManage = $isSuperAdmin || in_array('manage_commissions', $permissions, true);
$canCreate = $canManage;
$canEdit   = $canManage;
$canDelete = $canManage;

if (!$canManage && !$isSuperAdmin) { http_response_code(403); die('Access denied'); }

// ─── Translation helper ───
$_dtAllowedLangs = ['ar','en','fr','de','es','it','pt','ru','zh','ja','ko','tr','nl','sv','pl','uk','hi','bn','id','ms','th','vi','cs','ro','hu','el'];
$_dtLangCode = in_array($lang, $_dtAllowedLangs) ? $lang : 'en';
$_dtStringsFile = __DIR__ . '/../../languages/Commissions/' . $_dtLangCode . '.json';
$_dtStrings = file_exists($_dtStringsFile) ? (json_decode(file_get_contents($_dtStringsFile), true) ?: []) : [];
function _dt(string $key, string $fallback = ''): string {
    global $_dtStrings;
    $parts = explode('.', $key);
    $val = $_dtStrings;
    foreach ($parts as $p) {
        if (!is_array($val) || !isset($val[$p])) return $fallback ?: $key;
        $val = $val[$p];
    }
    return is_string($val) ? $val : ($fallback ?: $key);
}
?>
<link rel="stylesheet" href="/admin/assets/css/pages/commissions.css?v=<?= time() ?>">

<div class="page-container" dir="<?= $dir ?>">
  <div class="page-header">
    <div>
      <h2><?= _dt('title', 'Commission Management') ?></h2>
      <p class="page-subtitle"><?= _dt('subtitle', 'Manage commission transactions, invoices, payments, credit notes, and financial balances') ?></p>
    </div>
    <div class="page-header-actions">
      <button class="btn btn-primary" id="btnAddItem">+ <?= _dt('add_transaction', 'Add Transaction') ?></button>
    </div>
  </div>

  <!-- Tenant Selector -->
  <?php if ($isSuperAdmin): ?>
  <div class="entity-selector" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px">
    <div class="form-group" style="margin:0">
      <label><?= _dt('tenant_id', 'Tenant ID') ?></label>
      <div style="display:flex;gap:6px">
        <input type="number" class="form-control" id="tenantIdInput" placeholder="<?= _dt('enter_tenant_id', 'Enter Tenant ID') ?>" min="1" style="width:140px">
        <button class="btn btn-secondary btn-sm" id="btnVerifyTenant"><?= _dt('verify', 'Verify') ?></button>
      </div>
      <small id="tenantName" style="display:none;color:var(--success-color,#28a745);margin-top:4px"></small>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="tabs-wrapper">
    <button class="tab-btn active" data-tab="transactions"><?= _dt('tabs.transactions', 'Transactions') ?></button>
    <button class="tab-btn" data-tab="invoices"><?= _dt('tabs.invoices', 'Invoices') ?></button>
    <button class="tab-btn" data-tab="payments"><?= _dt('tabs.payments', 'Payments') ?></button>
    <button class="tab-btn" data-tab="credit_notes"><?= _dt('tabs.credit_notes', 'Credit Notes') ?></button>
    <button class="tab-btn" data-tab="balances"><?= _dt('tabs.balances', 'Financial Balances') ?></button>
  </div>

  <!-- ═══ Transactions Tab ═══ -->
  <div class="tab-content active" id="tabTransactions">
    <div class="stats-grid stats-grid-5" id="txnStatsGrid">
      <div class="stat-card"><div class="stat-value" id="txnStatTotal">0</div><div class="stat-label"><?= _dt('stats.total', 'Total') ?></div></div>
      <div class="stat-card stat-pending"><div class="stat-value" id="txnStatPending">0</div><div class="stat-label"><?= _dt('stats.pending', 'Pending') ?></div></div>
      <div class="stat-card stat-invoiced"><div class="stat-value" id="txnStatInvoiced">0</div><div class="stat-label"><?= _dt('stats.invoiced', 'Invoiced') ?></div></div>
      <div class="stat-card stat-active"><div class="stat-value" id="txnStatPaid">0</div><div class="stat-label"><?= _dt('stats.paid', 'Paid') ?></div></div>
      <div class="stat-card stat-expired"><div class="stat-value" id="txnStatCancelled">0</div><div class="stat-label"><?= _dt('stats.cancelled', 'Cancelled') ?></div></div>
    </div>
    <div class="filter-bar">
      <input type="number" class="form-control" id="txnFilterEntity" placeholder="<?= _dt('filter.entity_id', 'Entity ID') ?>" min="1" style="max-width:130px">
      <select class="form-control" id="txnFilterStatus">
        <option value=""><?= _dt('filter.all_status', 'All Status') ?></option>
        <option value="pending"><?= _dt('status.pending', 'Pending') ?></option>
        <option value="invoiced"><?= _dt('status.invoiced', 'Invoiced') ?></option>
        <option value="paid"><?= _dt('status.paid', 'Paid') ?></option>
        <option value="cancelled"><?= _dt('status.cancelled', 'Cancelled') ?></option>
      </select>
      <select class="form-control" id="txnFilterType">
        <option value=""><?= _dt('filter.all_types', 'All Types') ?></option>
        <option value="order_commission"><?= _dt('type.order_commission', 'Order Commission') ?></option>
        <option value="subscription_commission"><?= _dt('type.subscription_commission', 'Subscription Commission') ?></option>
        <option value="delivery_commission"><?= _dt('type.delivery_commission', 'Delivery Commission') ?></option>
        <option value="refund_commission"><?= _dt('type.refund_commission', 'Refund Commission') ?></option>
        <option value="adjustment"><?= _dt('type.adjustment', 'Adjustment') ?></option>
      </select>
      <input type="date" class="form-control" id="txnFilterDateFrom" title="<?= _dt('filter.date_from', 'Date From') ?>">
      <input type="date" class="form-control" id="txnFilterDateTo" title="<?= _dt('filter.date_to', 'Date To') ?>">
      <button class="btn btn-secondary" id="btnTxnFilter"><?= _dt('filter.apply', 'Filter') ?></button>
      <button class="btn btn-secondary" id="btnTxnClear"><?= _dt('filter.clear', 'Clear') ?></button>
    </div>
    <div class="card">
      <div class="card-body" style="overflow-x:auto">
        <table class="data-table" id="txnTable">
          <thead>
            <tr>
              <th><?= _dt('table.id', 'ID') ?></th>
              <th><?= _dt('table.entity_id', 'Entity') ?></th>
              <th><?= _dt('table.order_id', 'Order ID') ?></th>
              <th><?= _dt('table.transaction_type', 'Type') ?></th>
              <th><?= _dt('table.base_amount', 'Base Amount') ?></th>
              <th><?= _dt('table.commission_rate', 'Rate (%)') ?></th>
              <th><?= _dt('table.commission_amount', 'Commission') ?></th>
              <th><?= _dt('table.status', 'Status') ?></th>
              <th><?= _dt('table.created_at', 'Created') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="txnBody">
            <tr><td colspan="10" class="text-center"><?= _dt('table.loading', 'Loading...') ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="pagination-wrapper">
      <div class="pagination-info" id="txnPaginationInfo"></div>
      <div class="pagination" id="txnPagination"></div>
    </div>
  </div>

  <!-- ═══ Invoices Tab ═══ -->
  <div class="tab-content" id="tabInvoices">
    <div class="stats-grid" id="cinvStatsGrid">
      <div class="stat-card"><div class="stat-value" id="cinvStatTotal">0</div><div class="stat-label"><?= _dt('stats.total', 'Total') ?></div></div>
      <div class="stat-card stat-active"><div class="stat-value" id="cinvStatPaid">0</div><div class="stat-label"><?= _dt('stats.paid', 'Paid') ?></div></div>
      <div class="stat-card stat-pending"><div class="stat-value" id="cinvStatPending">0</div><div class="stat-label"><?= _dt('stats.pending', 'Pending') ?></div></div>
      <div class="stat-card stat-expired"><div class="stat-value" id="cinvStatOverdue">0</div><div class="stat-label"><?= _dt('stats.overdue', 'Overdue') ?></div></div>
    </div>
    <div class="filter-bar">
      <input type="number" class="form-control" id="cinvFilterEntity" placeholder="<?= _dt('filter.entity_id', 'Entity ID') ?>" min="1" style="max-width:130px">
      <select class="form-control" id="cinvFilterStatus">
        <option value=""><?= _dt('filter.all_status', 'All Status') ?></option>
        <option value="draft"><?= _dt('status.draft', 'Draft') ?></option>
        <option value="issued"><?= _dt('status.issued', 'Issued') ?></option>
        <option value="paid"><?= _dt('status.paid', 'Paid') ?></option>
        <option value="partially_paid"><?= _dt('status.partially_paid', 'Partially Paid') ?></option>
        <option value="overdue"><?= _dt('status.overdue', 'Overdue') ?></option>
        <option value="cancelled"><?= _dt('status.cancelled', 'Cancelled') ?></option>
        <option value="void"><?= _dt('status.void', 'Void') ?></option>
      </select>
      <select class="form-control" id="cinvFilterType">
        <option value=""><?= _dt('filter.all_types', 'All Types') ?></option>
        <option value="monthly"><?= _dt('type.monthly', 'Monthly') ?></option>
        <option value="quarterly"><?= _dt('type.quarterly', 'Quarterly') ?></option>
        <option value="custom"><?= _dt('type.custom', 'Custom') ?></option>
      </select>
      <button class="btn btn-secondary" id="btnCinvFilter"><?= _dt('filter.apply', 'Filter') ?></button>
      <button class="btn btn-secondary" id="btnCinvClear"><?= _dt('filter.clear', 'Clear') ?></button>
    </div>
    <div class="card">
      <div class="card-body" style="overflow-x:auto">
        <table class="data-table" id="cinvTable">
          <thead>
            <tr>
              <th><?= _dt('table.id', 'ID') ?></th>
              <th><?= _dt('table.invoice_number', 'Invoice #') ?></th>
              <th><?= _dt('table.entity_id', 'Entity') ?></th>
              <th><?= _dt('table.invoice_type', 'Type') ?></th>
              <th><?= _dt('table.total_amount', 'Total') ?></th>
              <th><?= _dt('table.tax_amount', 'Tax') ?></th>
              <th><?= _dt('table.status', 'Status') ?></th>
              <th><?= _dt('table.due_date', 'Due Date') ?></th>
              <th><?= _dt('table.paid_at', 'Paid At') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="cinvBody">
            <tr><td colspan="10" class="text-center"><?= _dt('table.loading', 'Loading...') ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="pagination-wrapper">
      <div class="pagination-info" id="cinvPaginationInfo"></div>
      <div class="pagination" id="cinvPagination"></div>
    </div>
  </div>

  <!-- ═══ Payments Tab ═══ -->
  <div class="tab-content" id="tabPayments">
    <div class="filter-bar">
      <input type="number" class="form-control" id="payFilterEntity" placeholder="<?= _dt('filter.entity_id', 'Entity ID') ?>" min="1" style="max-width:130px">
      <input type="number" class="form-control" id="payFilterInvoice" placeholder="<?= _dt('filter.invoice_id', 'Invoice ID') ?>" min="1" style="max-width:130px">
      <select class="form-control" id="payFilterCancelled">
        <option value=""><?= _dt('filter.is_cancelled', 'Is Cancelled') ?></option>
        <option value="0"><?= _dt('filter.no', 'No') ?></option>
        <option value="1"><?= _dt('filter.yes', 'Yes') ?></option>
      </select>
      <button class="btn btn-secondary" id="btnPayFilter"><?= _dt('filter.apply', 'Filter') ?></button>
      <button class="btn btn-secondary" id="btnPayClear"><?= _dt('filter.clear', 'Clear') ?></button>
    </div>
    <div class="card">
      <div class="card-body" style="overflow-x:auto">
        <table class="data-table" id="payTable">
          <thead>
            <tr>
              <th><?= _dt('table.id', 'ID') ?></th>
              <th><?= _dt('table.invoice_id', 'Invoice') ?></th>
              <th><?= _dt('table.entity_id', 'Entity') ?></th>
              <th><?= _dt('table.amount', 'Amount') ?></th>
              <th><?= _dt('table.payment_method', 'Payment Method') ?></th>
              <th><?= _dt('table.payment_reference', 'Reference') ?></th>
              <th><?= _dt('table.payment_date', 'Payment Date') ?></th>
              <th><?= _dt('table.is_cancelled', 'Cancelled') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="payBody">
            <tr><td colspan="9" class="text-center"><?= _dt('table.loading', 'Loading...') ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="pagination-wrapper">
      <div class="pagination-info" id="payPaginationInfo"></div>
      <div class="pagination" id="payPagination"></div>
    </div>
  </div>

  <!-- ═══ Credit Notes Tab ═══ -->
  <div class="tab-content" id="tabCreditNotes">
    <div class="filter-bar">
      <input type="number" class="form-control" id="cnFilterTenant" placeholder="<?= _dt('tenant_id', 'Tenant ID') ?>" min="1" style="max-width:130px">
      <select class="form-control" id="cnFilterStatus">
        <option value=""><?= _dt('filter.all_status', 'All Status') ?></option>
        <option value="draft"><?= _dt('status.draft', 'Draft') ?></option>
        <option value="issued"><?= _dt('status.issued', 'Issued') ?></option>
        <option value="void"><?= _dt('status.void', 'Void') ?></option>
      </select>
      <button class="btn btn-secondary" id="btnCnFilter"><?= _dt('filter.apply', 'Filter') ?></button>
      <button class="btn btn-secondary" id="btnCnClear"><?= _dt('filter.clear', 'Clear') ?></button>
    </div>
    <div class="card">
      <div class="card-body" style="overflow-x:auto">
        <table class="data-table" id="cnTable">
          <thead>
            <tr>
              <th><?= _dt('table.id', 'ID') ?></th>
              <th><?= _dt('table.credit_note_number', 'Credit Note #') ?></th>
              <th><?= _dt('table.invoice_id', 'Invoice') ?></th>
              <th><?= _dt('table.transaction_id', 'Transaction') ?></th>
              <th><?= _dt('table.credit_amount', 'Credit Amount') ?></th>
              <th><?= _dt('table.reason', 'Reason') ?></th>
              <th><?= _dt('table.status', 'Status') ?></th>
              <th><?= _dt('table.created_at', 'Created') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="cnBody">
            <tr><td colspan="9" class="text-center"><?= _dt('table.loading', 'Loading...') ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="pagination-wrapper">
      <div class="pagination-info" id="cnPaginationInfo"></div>
      <div class="pagination" id="cnPagination"></div>
    </div>
  </div>

  <!-- ═══ Financial Balances Tab ═══ -->
  <div class="tab-content" id="tabBalances">
    <div class="card">
      <div class="card-body" style="overflow-x:auto">
        <table class="data-table" id="balTable">
          <thead>
            <tr>
              <th><?= _dt('table.entity_id', 'Entity') ?></th>
              <th><?= _dt('table.entity_name', 'Entity Name') ?></th>
              <th><?= _dt('table.total_sales', 'Total Sales') ?></th>
              <th><?= _dt('table.total_commission', 'Total Commission') ?></th>
              <th><?= _dt('table.total_paid', 'Total Paid') ?></th>
              <th><?= _dt('table.balance', 'Balance') ?></th>
            </tr>
          </thead>
          <tbody id="balBody">
            <tr><td colspan="6" class="text-center"><?= _dt('table.loading', 'Loading...') ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="pagination-wrapper">
      <div class="pagination-info" id="balPaginationInfo"></div>
      <div class="pagination" id="balPagination"></div>
    </div>
  </div>

  <!-- ═══ Transaction Modal ═══ -->
  <div class="modal" id="txnModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3 id="txnModalTitle"><?= _dt('modal.add_transaction', 'Add Commission Transaction') ?></h3>
        <button class="modal-close" id="btnCloseTxnModal">&times;</button>
      </div>
      <form id="txnForm">
        <input type="hidden" id="txnId" value="">
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.entity_id', 'Entity ID') ?> *</label>
            <input type="number" class="form-control" id="txnEntityId" name="entity_id" required min="1">
          </div>
          <div class="form-group">
            <label><?= _dt('form.order_id', 'Order ID') ?></label>
            <input type="number" class="form-control" id="txnOrderId" name="order_id" min="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.transaction_type', 'Transaction Type') ?> *</label>
            <select class="form-control" id="txnType" name="transaction_type" required>
              <option value="sale"><?= _dt('type.sale', 'Sale') ?></option>
              <option value="refund"><?= _dt('type.refund', 'Refund') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _dt('form.order_date', 'Order Date') ?> *</label>
            <input type="datetime-local" class="form-control" id="txnOrderDate" name="order_date" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.status', 'Status') ?></label>
            <select class="form-control" id="txnStatus" name="status">
              <option value="pending"><?= _dt('status.pending', 'Pending') ?></option>
              <option value="invoiced"><?= _dt('status.invoiced', 'Invoiced') ?></option>
              <option value="paid"><?= _dt('status.paid', 'Paid') ?></option>
              <option value="cancelled"><?= _dt('status.cancelled', 'Cancelled') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _dt('form.currency_code', 'Currency') ?></label>
            <input type="text" class="form-control" id="txnCurrency" name="currency_code" value="SAR" maxlength="3">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.order_amount', 'Order Amount') ?> *</label>
            <input type="number" class="form-control" id="txnOrderAmount" name="order_amount" step="0.01" min="0" required>
          </div>
          <div class="form-group">
            <label><?= _dt('form.commission_amount', 'Commission Amount') ?> *</label>
            <input type="number" class="form-control" id="txnCommissionAmount" name="commission_amount" step="0.01" min="0" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.vat_amount', 'VAT Amount') ?></label>
            <input type="number" class="form-control" id="txnVatAmount" name="vat_amount" step="0.01" min="0" value="0">
          </div>
          <div class="form-group">
            <label><?= _dt('form.net_commission', 'Net Commission') ?> *</label>
            <input type="number" class="form-control" id="txnNetCommission" name="net_commission" step="0.01" min="0" required>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelTxnModal"><?= _dt('cancel', 'Cancel') ?></button>
          <button type="submit" class="btn btn-primary"><?= _dt('save', 'Save') ?></button>
        </div>
      </form>
    </div>
  </div>

  <!-- ═══ Invoice Modal ═══ -->
  <div class="modal" id="cinvModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3 id="cinvModalTitle"><?= _dt('modal.add_invoice', 'Add Commission Invoice') ?></h3>
        <button class="modal-close" id="btnCloseCinvModal">&times;</button>
      </div>
      <form id="cinvForm">
        <input type="hidden" id="cinvId" value="">
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.entity_id', 'Entity ID') ?> *</label>
            <input type="number" class="form-control" id="cinvEntityId" name="entity_id" required min="1">
          </div>
          <div class="form-group">
            <label><?= _dt('form.invoice_number', 'Invoice Number') ?></label>
            <div class="input-group">
              <input type="text" class="form-control" id="cinvInvoiceNumber" name="invoice_number" placeholder="<?= _dt('form.auto_generate', 'Auto-generate') ?>">
              <button type="button" class="btn btn-sm btn-secondary" id="btnGenInvNum"><?= _dt('form.auto_generate', 'Auto-generate') ?></button>
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.invoice_type', 'Invoice Type') ?> *</label>
            <select class="form-control" id="cinvInvoiceType" name="invoice_type" required>
              <option value="monthly"><?= _dt('type.monthly', 'Monthly') ?></option>
              <option value="quarterly"><?= _dt('type.quarterly', 'Quarterly') ?></option>
              <option value="custom"><?= _dt('type.custom', 'Custom') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _dt('form.status', 'Status') ?></label>
            <select class="form-control" id="cinvStatus" name="status">
              <option value="draft"><?= _dt('status.draft', 'Draft') ?></option>
              <option value="issued"><?= _dt('status.issued', 'Issued') ?></option>
              <option value="paid"><?= _dt('status.paid', 'Paid') ?></option>
              <option value="partially_paid"><?= _dt('status.partially_paid', 'Partially Paid') ?></option>
              <option value="overdue"><?= _dt('status.overdue', 'Overdue') ?></option>
              <option value="cancelled"><?= _dt('status.cancelled', 'Cancelled') ?></option>
              <option value="void"><?= _dt('status.void', 'Void') ?></option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.total_amount', 'Total Amount') ?> *</label>
            <input type="number" class="form-control" id="cinvTotalAmount" name="total_amount" step="0.01" min="0" required>
          </div>
          <div class="form-group">
            <label><?= _dt('form.tax_amount', 'Tax Amount') ?></label>
            <input type="number" class="form-control" id="cinvTaxAmount" name="tax_amount" step="0.01" min="0" value="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.currency_code', 'Currency') ?></label>
            <input type="text" class="form-control" id="cinvCurrency" name="currency_code" value="SAR" maxlength="3">
          </div>
          <div class="form-group">
            <label><?= _dt('form.due_date', 'Due Date') ?></label>
            <input type="date" class="form-control" id="cinvDueDate" name="due_date">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.period_start', 'Period Start') ?></label>
            <input type="date" class="form-control" id="cinvPeriodStart" name="period_start">
          </div>
          <div class="form-group">
            <label><?= _dt('form.period_end', 'Period End') ?></label>
            <input type="date" class="form-control" id="cinvPeriodEnd" name="period_end">
          </div>
        </div>
        <div class="form-group">
          <label><?= _dt('form.notes', 'Notes') ?></label>
          <textarea class="form-control" id="cinvNotes" name="notes" rows="3"></textarea>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelCinvModal"><?= _dt('cancel', 'Cancel') ?></button>
          <button type="submit" class="btn btn-primary"><?= _dt('save', 'Save') ?></button>
        </div>
      </form>
    </div>
  </div>

  <!-- ═══ Payment Modal ═══ -->
  <div class="modal" id="payModal" style="display:none">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="payModalTitle"><?= _dt('modal.record_payment', 'Record Payment') ?></h3>
        <button class="modal-close" id="btnClosePayModal">&times;</button>
      </div>
      <form id="payForm">
        <input type="hidden" id="payId" value="">
        <div class="form-group">
          <label><?= _dt('form.entity_id', 'Entity ID') ?> *</label>
          <input type="number" class="form-control" id="payEntityId" name="entity_id" required min="1">
        </div>
        <div class="form-group">
          <label><?= _dt('table.invoice_id', 'Invoice') ?> *</label>
          <input type="number" class="form-control" id="payInvoiceId" name="commission_invoice_id" required min="1">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.amount', 'Amount') ?> *</label>
            <input type="number" class="form-control" id="payAmount" name="amount" step="0.01" min="0" required>
          </div>
          <div class="form-group">
            <label><?= _dt('form.currency_code', 'Currency') ?></label>
            <input type="text" class="form-control" id="payCurrency" name="currency_code" value="SAR" maxlength="3">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.payment_method', 'Payment Method') ?></label>
            <input type="text" class="form-control" id="payMethod" name="payment_method" placeholder="bank_transfer">
          </div>
          <div class="form-group">
            <label><?= _dt('form.payment_reference', 'Payment Reference') ?></label>
            <input type="text" class="form-control" id="payReference" name="payment_reference">
          </div>
        </div>
        <div class="form-group">
          <label><?= _dt('form.payment_date', 'Payment Date') ?></label>
          <input type="date" class="form-control" id="payDate" name="payment_date">
        </div>
        <div class="form-group">
          <label><?= _dt('form.notes', 'Notes') ?></label>
          <textarea class="form-control" id="payNotes" name="notes" rows="2"></textarea>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelPayModal"><?= _dt('cancel', 'Cancel') ?></button>
          <button type="submit" class="btn btn-primary"><?= _dt('save', 'Save') ?></button>
        </div>
      </form>
    </div>
  </div>

  <!-- ═══ Credit Note Modal ═══ -->
  <div class="modal" id="cnModal" style="display:none">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="cnModalTitle"><?= _dt('modal.add_credit_note', 'Add Credit Note') ?></h3>
        <button class="modal-close" id="btnCloseCnModal">&times;</button>
      </div>
      <form id="cnForm">
        <input type="hidden" id="cnId" value="">
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.invoice_id', 'Invoice ID') ?> *</label>
            <input type="number" class="form-control" id="cnInvoiceId" name="invoice_id" required min="1">
          </div>
          <div class="form-group">
            <label><?= _dt('form.transaction_id', 'Transaction ID') ?> *</label>
            <input type="number" class="form-control" id="cnTransactionId" name="related_transaction_id" required min="1">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.credit_amount', 'Credit Amount') ?> *</label>
            <input type="number" class="form-control" id="cnAmount" name="credit_amount" step="0.01" min="0" required>
          </div>
          <div class="form-group">
            <label><?= _dt('form.credit_commission', 'Credit Commission') ?> *</label>
            <input type="number" class="form-control" id="cnCreditCommission" name="credit_commission" step="0.01" min="0" required value="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.credit_vat', 'Credit VAT') ?> *</label>
            <input type="number" class="form-control" id="cnCreditVat" name="credit_vat" step="0.01" min="0" required value="0">
          </div>
          <div class="form-group">
            <label><?= _dt('form.net_credit', 'Net Credit') ?> *</label>
            <input type="number" class="form-control" id="cnNetCredit" name="net_credit" step="0.01" min="0" required value="0">
          </div>
        </div>
        <div class="form-group">
          <label><?= _dt('form.status', 'Status') ?></label>
          <select class="form-control" id="cnStatus" name="status">
            <option value="draft"><?= _dt('status.draft', 'Draft') ?></option>
            <option value="issued"><?= _dt('status.issued', 'Issued') ?></option>
            <option value="cancelled"><?= _dt('status.cancelled', 'Cancelled') ?></option>
          </select>
        </div>
        <div class="form-group">
          <label><?= _dt('form.reason', 'Reason') ?></label>
          <textarea class="form-control" id="cnReason" name="reason" rows="3"></textarea>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelCnModal"><?= _dt('cancel', 'Cancel') ?></button>
          <button type="submit" class="btn btn-primary"><?= _dt('save', 'Save') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
window.COMMISSIONS_CONFIG = {
    apiBase: '/api',
    csrfToken: <?= json_encode($csrf) ?>,
    lang: <?= json_encode($lang) ?>,
    dir: <?= json_encode($dir) ?>,
    canCreate: <?= json_encode($canCreate) ?>,
    canEdit: <?= json_encode($canEdit) ?>,
    canDelete: <?= json_encode($canDelete) ?>,
    isSuperAdmin: <?= json_encode($isSuperAdmin) ?>,
    tenantId: <?= json_encode((int)$tenantId) ?>,
    entityId: <?= json_encode((int)$entityId) ?>,
    userId: <?= json_encode((int)$userId) ?>,
    strings: <?= json_encode($_dtStrings) ?>
};
</script>
<script src="/admin/assets/js/pages/commissions.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>