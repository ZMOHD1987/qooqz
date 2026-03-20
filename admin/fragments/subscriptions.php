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
$canManage = $isSuperAdmin || in_array('manage_subscriptions', $permissions, true);
$canCreate = $canManage;
$canEdit   = $canManage;
$canDelete = $canManage;

if (!$canManage && !$isSuperAdmin) { http_response_code(403); die('Access denied'); }

// ─── Translation helper ───
$_stAllowedLangs = ['ar','en','fr','de','es','it','pt','ru','zh','ja','ko','tr','nl','sv','pl','uk','hi','bn','id','ms','th','vi','cs','ro','hu','el'];
$_stLangCode = in_array($lang, $_stAllowedLangs) ? $lang : 'en';
$_stStringsFile = __DIR__ . '/../../languages/Subscriptions/' . $_stLangCode . '.json';
$_stStrings = file_exists($_stStringsFile) ? (json_decode(file_get_contents($_stStringsFile), true) ?: []) : [];
function _st(string $key, string $fallback = ''): string {
    global $_stStrings;
    $parts = explode('.', $key);
    $val = $_stStrings;
    foreach ($parts as $p) {
        if (!is_array($val) || !isset($val[$p])) return $fallback ?: $key;
        $val = $val[$p];
    }
    return is_string($val) ? $val : ($fallback ?: $key);
}
?>
<link rel="stylesheet" href="/admin/assets/css/pages/subscriptions.css?v=<?= time() ?>">

<div class="page-container" dir="<?= $dir ?>">
  <div class="page-header">
    <div>
      <h2><?= _st('title', 'Subscription Management') ?></h2>
      <p class="page-subtitle"><?= _st('subtitle', 'Manage subscription plans, subscriptions, invoices, and escrow transactions') ?></p>
    </div>
    <div class="page-header-actions">
      <button class="btn btn-primary" id="btnAddItem">+ <?= _st('add_plan', 'Add Plan') ?></button>
    </div>
  </div>

  <!-- Tenant Selector -->
  <?php if ($isSuperAdmin): ?>
  <div class="entity-selector" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px">
    <div class="form-group" style="margin:0">
      <label><?= _st('tenant_id', 'Tenant ID') ?></label>
      <div style="display:flex;gap:6px">
        <input type="number" class="form-control" id="tenantIdInput" placeholder="<?= _st('enter_tenant_id', 'Enter Tenant ID') ?>" min="1" style="width:140px">
        <button class="btn btn-secondary btn-sm" id="btnVerifyTenant"><?= _st('verify', 'Verify') ?></button>
      </div>
      <small id="tenantName" style="display:none;color:var(--success-color,#28a745);margin-top:4px"></small>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="tabs-wrapper">
    <button class="tab-btn active" data-tab="plans"><?= _st('tabs.plans', 'Plans') ?></button>
    <button class="tab-btn" data-tab="subscriptions"><?= _st('tabs.subscriptions', 'Subscriptions') ?></button>
    <button class="tab-btn" data-tab="invoices"><?= _st('tabs.invoices', 'Invoices') ?></button>
    <button class="tab-btn" data-tab="escrow"><?= _st('tabs.escrow', 'Escrow') ?></button>
  </div>

  <!-- ═══ Plans Tab ═══ -->
  <div class="tab-content active" id="tabPlans">
    <div class="stats-grid" id="plansStatsGrid">
      <div class="stat-card"><div class="stat-value" id="planStatTotal">0</div><div class="stat-label"><?= _st('stats.total', 'Total') ?></div></div>
      <div class="stat-card stat-active"><div class="stat-value" id="planStatActive">0</div><div class="stat-label"><?= _st('stats.active', 'Active') ?></div></div>
      <div class="stat-card"><div class="stat-value" id="planStatInactive">0</div><div class="stat-label"><?= _st('stats.inactive', 'Inactive') ?></div></div>
      <div class="stat-card stat-featured"><div class="stat-value" id="planStatFeatured">0</div><div class="stat-label"><?= _st('stats.featured', 'Featured') ?></div></div>
    </div>
    <div class="filter-bar">
      <input type="text" class="form-control" id="planSearchInput" placeholder="<?= _st('filter.search', 'Search...') ?>">
      <select class="form-control" id="planTypeFilter">
        <option value=""><?= _st('filter.all_types', 'All Types') ?></option>
        <option value="free"><?= _st('filter.free', 'Free') ?></option>
        <option value="basic"><?= _st('filter.basic', 'Basic') ?></option>
        <option value="standard"><?= _st('filter.standard', 'Standard') ?></option>
        <option value="premium"><?= _st('filter.premium', 'Premium') ?></option>
        <option value="enterprise"><?= _st('filter.enterprise', 'Enterprise') ?></option>
        <option value="custom"><?= _st('filter.custom', 'Custom') ?></option>
      </select>
      <select class="form-control" id="planPeriodFilter">
        <option value=""><?= _st('filter.all_periods', 'All Periods') ?></option>
        <option value="monthly"><?= _st('filter.monthly', 'Monthly') ?></option>
        <option value="quarterly"><?= _st('filter.quarterly', 'Quarterly') ?></option>
        <option value="semi_annual"><?= _st('filter.semi_annual', 'Semi-Annual') ?></option>
        <option value="annual"><?= _st('filter.annual', 'Annual') ?></option>
        <option value="lifetime"><?= _st('filter.lifetime', 'Lifetime') ?></option>
      </select>
      <button class="btn btn-secondary" id="btnPlanFilter"><?= _st('filter.apply', 'Filter') ?></button>
      <button class="btn btn-secondary" id="btnPlanClear"><?= _st('filter.clear', 'Clear') ?></button>
    </div>
    <div class="card">
      <div class="card-body" style="overflow-x:auto">
        <table class="data-table" id="plansTable">
          <thead>
            <tr>
              <th><?= _st('table.id', 'ID') ?></th>
              <th><?= _st('table.plan_name', 'Plan Name') ?></th>
              <th><?= _st('table.code', 'Code') ?></th>
              <th><?= _st('table.plan_type', 'Type') ?></th>
              <th><?= _st('table.billing_period', 'Billing Period') ?></th>
              <th><?= _st('table.price', 'Price') ?></th>
              <th><?= _st('table.is_active', 'Active') ?></th>
              <th><?= _st('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="plansBody">
            <tr><td colspan="8" class="text-center"><?= _st('table.loading', 'Loading...') ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="pagination-wrapper">
      <div class="pagination-info" id="plansPaginationInfo"></div>
      <div class="pagination" id="plansPagination"></div>
    </div>
  </div>

  <!-- ═══ Subscriptions Tab ═══ -->
  <div class="tab-content" id="tabSubscriptions">
    <div class="stats-grid" id="subsStatsGrid">
      <div class="stat-card"><div class="stat-value" id="subStatTotal">0</div><div class="stat-label"><?= _st('stats.total', 'Total') ?></div></div>
      <div class="stat-card stat-active"><div class="stat-value" id="subStatActive">0</div><div class="stat-label"><?= _st('stats.active', 'Active') ?></div></div>
      <div class="stat-card"><div class="stat-value" id="subStatTrial">0</div><div class="stat-label"><?= _st('stats.trial', 'Trial') ?></div></div>
      <div class="stat-card stat-expired"><div class="stat-value" id="subStatCancelled">0</div><div class="stat-label"><?= _st('stats.cancelled', 'Cancelled') ?></div></div>
    </div>
    <div class="filter-bar">
      <input type="text" class="form-control" id="subSearchInput" placeholder="<?= _st('filter.search', 'Search...') ?>">
      <select class="form-control" id="subStatusFilter">
        <option value=""><?= _st('filter.all_status', 'All Status') ?></option>
        <option value="trial"><?= _st('status.trial', 'Trial') ?></option>
        <option value="active"><?= _st('status.active', 'Active') ?></option>
        <option value="paused"><?= _st('status.paused', 'Paused') ?></option>
        <option value="cancelled"><?= _st('status.cancelled', 'Cancelled') ?></option>
        <option value="expired"><?= _st('status.expired', 'Expired') ?></option>
        <option value="suspended"><?= _st('status.suspended', 'Suspended') ?></option>
      </select>
      <button class="btn btn-secondary" id="btnSubFilter"><?= _st('filter.apply', 'Filter') ?></button>
      <button class="btn btn-secondary" id="btnSubClear"><?= _st('filter.clear', 'Clear') ?></button>
    </div>
    <div class="card">
      <div class="card-body" style="overflow-x:auto">
        <table class="data-table" id="subsTable">
          <thead>
            <tr>
              <th><?= _st('table.id', 'ID') ?></th>
              <th><?= _st('table.subscription_number', 'Sub #') ?></th>
              <th><?= _st('table.tenant_id', 'Tenant') ?></th>
              <th><?= _st('table.status', 'Status') ?></th>
              <th><?= _st('table.billing_period', 'Billing Period') ?></th>
              <th><?= _st('table.price', 'Price') ?></th>
              <th><?= _st('table.start_date', 'Start Date') ?></th>
              <th><?= _st('table.next_billing_date', 'Next Billing') ?></th>
              <th><?= _st('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="subsBody">
            <tr><td colspan="9" class="text-center"><?= _st('table.loading', 'Loading...') ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="pagination-wrapper">
      <div class="pagination-info" id="subsPaginationInfo"></div>
      <div class="pagination" id="subsPagination"></div>
    </div>
  </div>

  <!-- ═══ Invoices Tab ═══ -->
  <div class="tab-content" id="tabInvoices">
    <div class="stats-grid" id="invStatsGrid">
      <div class="stat-card"><div class="stat-value" id="invStatTotal">0</div><div class="stat-label"><?= _st('stats.total', 'Total') ?></div></div>
      <div class="stat-card stat-active"><div class="stat-value" id="invStatPaid">0</div><div class="stat-label"><?= _st('stats.paid', 'Paid') ?></div></div>
      <div class="stat-card"><div class="stat-value" id="invStatPending">0</div><div class="stat-label"><?= _st('stats.pending', 'Pending') ?></div></div>
      <div class="stat-card stat-expired"><div class="stat-value" id="invStatOverdue">0</div><div class="stat-label"><?= _st('stats.overdue', 'Overdue') ?></div></div>
    </div>
    <div class="filter-bar">
      <input type="text" class="form-control" id="invSearchInput" placeholder="<?= _st('filter.search', 'Search...') ?>">
      <select class="form-control" id="invStatusFilter">
        <option value=""><?= _st('filter.all_status', 'All Status') ?></option>
        <option value="pending"><?= _st('status.pending', 'Pending') ?></option>
        <option value="paid"><?= _st('status.paid', 'Paid') ?></option>
        <option value="overdue"><?= _st('status.overdue', 'Overdue') ?></option>
        <option value="cancelled"><?= _st('status.cancelled', 'Cancelled') ?></option>
        <option value="refunded"><?= _st('status.refunded', 'Refunded') ?></option>
      </select>
      <button class="btn btn-secondary" id="btnInvFilter"><?= _st('filter.apply', 'Filter') ?></button>
      <button class="btn btn-secondary" id="btnInvClear"><?= _st('filter.clear', 'Clear') ?></button>
    </div>
    <div class="filter-bar" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;align-items:flex-end;">
        <div class="filter-group">
            <label>Status</label>
            <select id="invoiceFilterStatus" class="form-control">
                <option value="">All</option>
                <option value="pending">Pending</option>
                <option value="paid">Paid</option>
                <option value="overdue">Overdue</option>
                <option value="cancelled">Cancelled</option>
                <option value="refunded">Refunded</option>
            </select>
        </div>
        <div class="filter-group">
            <label>From</label>
            <input type="date" id="invoiceFilterDateFrom" class="form-control">
        </div>
        <div class="filter-group">
            <label>To</label>
            <input type="date" id="invoiceFilterDateTo" class="form-control">
        </div>
        <button class="btn btn-primary" onclick="if(typeof loadInvoices==='function')loadInvoices();">Filter</button>
        <button class="btn btn-secondary" onclick="document.getElementById('invoiceFilterStatus').value='';document.getElementById('invoiceFilterDateFrom').value='';document.getElementById('invoiceFilterDateTo').value='';if(typeof loadInvoices==='function')loadInvoices();">Clear</button>
        <button class="btn btn-success" onclick="if(typeof exportInvoicesCSV==='function')exportInvoicesCSV();">Export CSV</button>
    </div>
    <div class="card">
      <div class="card-body" style="overflow-x:auto">
        <table class="data-table" id="invTable">
          <thead>
            <tr>
              <th><?= _st('table.id', 'ID') ?></th>
              <th><?= _st('table.invoice_number', 'Invoice #') ?></th>
              <th><?= _st('table.tenant_id', 'Tenant') ?></th>
              <th><?= _st('table.amount', 'Amount') ?></th>
              <th><?= _st('table.total_amount', 'Total') ?></th>
              <th><?= _st('table.status', 'Status') ?></th>
              <th><?= _st('table.due_date', 'Due Date') ?></th>
              <th><?= _st('table.paid_at', 'Paid At') ?></th>
              <th><?= _st('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="invBody">
            <tr><td colspan="9" class="text-center"><?= _st('table.loading', 'Loading...') ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="pagination-wrapper">
      <div class="pagination-info" id="invPaginationInfo"></div>
      <div class="pagination" id="invPagination"></div>
    </div>
  </div>

  <!-- ═══ Escrow Tab ═══ -->
  <div class="tab-content" id="tabEscrow">
    <div class="stats-grid" id="escStatsGrid">
      <div class="stat-card"><div class="stat-value" id="escStatTotal">0</div><div class="stat-label"><?= _st('stats.total', 'Total') ?></div></div>
      <div class="stat-card stat-active"><div class="stat-value" id="escStatReleased">0</div><div class="stat-label"><?= _st('stats.released', 'Released') ?></div></div>
      <div class="stat-card"><div class="stat-value" id="escStatPending">0</div><div class="stat-label"><?= _st('stats.pending', 'Pending') ?></div></div>
      <div class="stat-card stat-expired"><div class="stat-value" id="escStatDisputed">0</div><div class="stat-label"><?= _st('stats.disputed', 'Disputed') ?></div></div>
    </div>
    <div class="filter-bar">
      <input type="text" class="form-control" id="escSearchInput" placeholder="<?= _st('filter.search', 'Search...') ?>">
      <select class="form-control" id="escStatusFilter">
        <option value=""><?= _st('filter.all_status', 'All Status') ?></option>
        <option value="pending"><?= _st('status.pending', 'Pending') ?></option>
        <option value="funded"><?= _st('status.funded', 'Funded') ?></option>
        <option value="in_transit"><?= _st('status.in_transit', 'In Transit') ?></option>
        <option value="delivered"><?= _st('status.delivered', 'Delivered') ?></option>
        <option value="released"><?= _st('status.released', 'Released') ?></option>
        <option value="disputed"><?= _st('status.disputed', 'Disputed') ?></option>
        <option value="refunded"><?= _st('status.refunded', 'Refunded') ?></option>
        <option value="cancelled"><?= _st('status.cancelled', 'Cancelled') ?></option>
      </select>
      <button class="btn btn-secondary" id="btnEscFilter"><?= _st('filter.apply', 'Filter') ?></button>
      <button class="btn btn-secondary" id="btnEscClear"><?= _st('filter.clear', 'Clear') ?></button>
    </div>
    <div class="card">
      <div class="card-body" style="overflow-x:auto">
        <table class="data-table" id="escTable">
          <thead>
            <tr>
              <th><?= _st('table.id', 'ID') ?></th>
              <th><?= _st('table.escrow_number', 'Escrow #') ?></th>
              <th><?= _st('table.order_id', 'Order') ?></th>
              <th><?= _st('table.buyer_id', 'Buyer') ?></th>
              <th><?= _st('table.seller_id', 'Seller') ?></th>
              <th><?= _st('table.amount', 'Amount') ?></th>
              <th><?= _st('table.status', 'Status') ?></th>
              <th><?= _st('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="escBody">
            <tr><td colspan="8" class="text-center"><?= _st('table.loading', 'Loading...') ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="pagination-wrapper">
      <div class="pagination-info" id="escPaginationInfo"></div>
      <div class="pagination" id="escPagination"></div>
    </div>
  </div>

  <!-- ═══ Plan Modal ═══ -->
  <div class="modal" id="planModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3 id="planModalTitle"><?= _st('modal.add_plan', 'Add Subscription Plan') ?></h3>
        <button class="modal-close" id="btnClosePlanModal">&times;</button>
      </div>
      <form id="planForm">
        <input type="hidden" id="planId" value="">
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.plan_name', 'Plan Name') ?> *</label>
            <input type="text" class="form-control" id="planName" name="plan_name" required>
          </div>
          <div class="form-group">
            <label><?= _st('form.code', 'Code') ?></label>
            <input type="text" class="form-control" id="planCode" name="code">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.plan_type', 'Plan Type') ?> *</label>
            <select class="form-control" id="planType" name="plan_type" required>
              <option value="free"><?= _st('filter.free', 'Free') ?></option>
              <option value="basic"><?= _st('filter.basic', 'Basic') ?></option>
              <option value="standard"><?= _st('filter.standard', 'Standard') ?></option>
              <option value="premium"><?= _st('filter.premium', 'Premium') ?></option>
              <option value="enterprise"><?= _st('filter.enterprise', 'Enterprise') ?></option>
              <option value="custom"><?= _st('filter.custom', 'Custom') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _st('form.billing_period', 'Billing Period') ?> *</label>
            <select class="form-control" id="planBillingPeriod" name="billing_period" required>
              <option value="monthly"><?= _st('filter.monthly', 'Monthly') ?></option>
              <option value="quarterly"><?= _st('filter.quarterly', 'Quarterly') ?></option>
              <option value="semi_annual"><?= _st('filter.semi_annual', 'Semi-Annual') ?></option>
              <option value="annual"><?= _st('filter.annual', 'Annual') ?></option>
              <option value="lifetime"><?= _st('filter.lifetime', 'Lifetime') ?></option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.price', 'Price') ?> *</label>
            <input type="number" class="form-control" id="planPrice" name="price" step="0.01" min="0" required>
          </div>
          <div class="form-group">
            <label><?= _st('form.currency_code', 'Currency') ?></label>
            <input type="text" class="form-control" id="planCurrency" name="currency_code" value="SAR" maxlength="3">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.setup_fee', 'Setup Fee') ?></label>
            <input type="number" class="form-control" id="planSetupFee" name="setup_fee" step="0.01" min="0" value="0">
          </div>
          <div class="form-group">
            <label><?= _st('form.commission_rate', 'Commission Rate (%)') ?></label>
            <input type="number" class="form-control" id="planCommission" name="commission_rate" step="0.01" min="0" max="100" value="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.max_products', 'Max Products') ?></label>
            <input type="number" class="form-control" id="planMaxProducts" name="max_products" min="0" placeholder="<?= _st('form.unlimited', 'Unlimited') ?>">
          </div>
          <div class="form-group">
            <label><?= _st('form.max_branches', 'Max Branches') ?></label>
            <input type="number" class="form-control" id="planMaxBranches" name="max_branches" min="0" placeholder="<?= _st('form.unlimited', 'Unlimited') ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.max_orders_per_month', 'Max Orders/Month') ?></label>
            <input type="number" class="form-control" id="planMaxOrders" name="max_orders_per_month" min="0" placeholder="<?= _st('form.unlimited', 'Unlimited') ?>">
          </div>
          <div class="form-group">
            <label><?= _st('form.max_staff', 'Max Staff') ?></label>
            <input type="number" class="form-control" id="planMaxStaff" name="max_staff" min="0" placeholder="<?= _st('form.unlimited', 'Unlimited') ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.trial_period_days', 'Trial Period (Days)') ?></label>
            <input type="number" class="form-control" id="planTrialDays" name="trial_period_days" min="0" value="0">
          </div>
          <div class="form-group">
            <label><?= _st('form.sort_order', 'Sort Order') ?></label>
            <input type="number" class="form-control" id="planSortOrder" name="sort_order" min="0" value="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.analytics_access', 'Analytics Access') ?></label>
            <select class="form-control" id="planAnalytics" name="analytics_access">
              <option value="0"><?= _st('no', 'No') ?></option>
              <option value="1"><?= _st('yes', 'Yes') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _st('form.priority_support', 'Priority Support') ?></label>
            <select class="form-control" id="planPrioritySupport" name="priority_support">
              <option value="0"><?= _st('no', 'No') ?></option>
              <option value="1"><?= _st('yes', 'Yes') ?></option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.featured_listing', 'Featured Listing') ?></label>
            <select class="form-control" id="planFeaturedListing" name="featured_listing">
              <option value="0"><?= _st('no', 'No') ?></option>
              <option value="1"><?= _st('yes', 'Yes') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _st('form.custom_domain', 'Custom Domain') ?></label>
            <select class="form-control" id="planCustomDomain" name="custom_domain">
              <option value="0"><?= _st('no', 'No') ?></option>
              <option value="1"><?= _st('yes', 'Yes') ?></option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.api_access', 'API Access') ?></label>
            <select class="form-control" id="planApiAccess" name="api_access">
              <option value="0"><?= _st('no', 'No') ?></option>
              <option value="1"><?= _st('yes', 'Yes') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _st('form.is_active', 'Active') ?></label>
            <select class="form-control" id="planIsActive" name="is_active">
              <option value="1"><?= _st('yes', 'Yes') ?></option>
              <option value="0"><?= _st('no', 'No') ?></option>
            </select>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelPlanModal"><?= _st('cancel', 'Cancel') ?></button>
          <button type="submit" class="btn btn-primary"><?= _st('save', 'Save') ?></button>
        </div>
      </form>
    </div>
  </div>

  <!-- ═══ Subscription Modal ═══ -->
  <div class="modal" id="subModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3 id="subModalTitle"><?= _st('modal.add_subscription', 'Add Subscription') ?></h3>
        <button class="modal-close" id="btnCloseSubModal">&times;</button>
      </div>
      <form id="subForm">
        <input type="hidden" id="subId" value="">
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.tenant_id', 'Tenant ID') ?> *</label>
            <input type="number" class="form-control" id="subTenantId" name="tenant_id" required min="1">
          </div>
          <div class="form-group">
            <label><?= _st('form.plan_id', 'Plan') ?> *</label>
            <select class="form-control" id="subPlanId" name="plan_id" required>
              <option value=""><?= _st('form.select_plan', 'Select Plan...') ?></option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.billing_period', 'Billing Period') ?> *</label>
            <select class="form-control" id="subBillingPeriod" name="billing_period" required>
              <option value="monthly"><?= _st('filter.monthly', 'Monthly') ?></option>
              <option value="quarterly"><?= _st('filter.quarterly', 'Quarterly') ?></option>
              <option value="semi_annual"><?= _st('filter.semi_annual', 'Semi-Annual') ?></option>
              <option value="annual"><?= _st('filter.annual', 'Annual') ?></option>
              <option value="lifetime"><?= _st('filter.lifetime', 'Lifetime') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _st('form.price', 'Price') ?> *</label>
            <input type="number" class="form-control" id="subPrice" name="price" step="0.01" min="0" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.status', 'Status') ?></label>
            <select class="form-control" id="subStatus" name="status">
              <option value="trial"><?= _st('status.trial', 'Trial') ?></option>
              <option value="active"><?= _st('status.active', 'Active') ?></option>
              <option value="paused"><?= _st('status.paused', 'Paused') ?></option>
              <option value="cancelled"><?= _st('status.cancelled', 'Cancelled') ?></option>
              <option value="expired"><?= _st('status.expired', 'Expired') ?></option>
              <option value="suspended"><?= _st('status.suspended', 'Suspended') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _st('form.currency_code', 'Currency') ?></label>
            <input type="text" class="form-control" id="subCurrency" name="currency_code" value="SAR" maxlength="3">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.start_date', 'Start Date') ?></label>
            <input type="date" class="form-control" id="subStartDate" name="start_date">
          </div>
          <div class="form-group">
            <label><?= _st('form.end_date', 'End Date') ?></label>
            <input type="date" class="form-control" id="subEndDate" name="end_date">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.trial_end_date', 'Trial End Date') ?></label>
            <input type="date" class="form-control" id="subTrialEndDate" name="trial_end_date">
          </div>
          <div class="form-group">
            <label><?= _st('form.next_billing_date', 'Next Billing Date') ?></label>
            <input type="date" class="form-control" id="subNextBilling" name="next_billing_date">
          </div>
        </div>
        <div class="form-group">
          <label><?= _st('form.auto_renew', 'Auto Renew') ?></label>
          <select class="form-control" id="subAutoRenew" name="auto_renew">
            <option value="1"><?= _st('yes', 'Yes') ?></option>
            <option value="0"><?= _st('no', 'No') ?></option>
          </select>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelSubModal"><?= _st('cancel', 'Cancel') ?></button>
          <button type="submit" class="btn btn-primary"><?= _st('save', 'Save') ?></button>
        </div>
      </form>
    </div>
  </div>

  <!-- ═══ Invoice Modal ═══ -->
  <div class="modal" id="invModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3 id="invModalTitle"><?= _st('modal.add_invoice', 'Add Invoice') ?></h3>
        <button class="modal-close" id="btnCloseInvModal">&times;</button>
      </div>
      <form id="invForm">
        <input type="hidden" id="invId" value="">
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.subscription_id', 'Subscription') ?> *</label>
            <input type="number" class="form-control" id="invSubId" name="subscription_id" required min="1">
          </div>
          <div class="form-group">
            <label><?= _st('form.tenant_id', 'Tenant ID') ?> *</label>
            <input type="number" class="form-control" id="invTenantId" name="tenant_id" required min="1">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.amount', 'Amount') ?> *</label>
            <input type="number" class="form-control" id="invAmount" name="amount" step="0.01" min="0" required>
          </div>
          <div class="form-group">
            <label><?= _st('form.tax_amount', 'Tax Amount') ?></label>
            <input type="number" class="form-control" id="invTaxAmount" name="tax_amount" step="0.01" min="0" value="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.total_amount', 'Total Amount') ?> *</label>
            <input type="number" class="form-control" id="invTotalAmount" name="total_amount" step="0.01" min="0" required>
          </div>
          <div class="form-group">
            <label><?= _st('form.currency_code', 'Currency') ?></label>
            <input type="text" class="form-control" id="invCurrency" name="currency_code" value="SAR" maxlength="3">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.billing_period_start', 'Billing Start') ?></label>
            <input type="date" class="form-control" id="invBillingStart" name="billing_period_start">
          </div>
          <div class="form-group">
            <label><?= _st('form.billing_period_end', 'Billing End') ?></label>
            <input type="date" class="form-control" id="invBillingEnd" name="billing_period_end">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.due_date', 'Due Date') ?></label>
            <input type="date" class="form-control" id="invDueDate" name="due_date">
          </div>
          <div class="form-group">
            <label><?= _st('form.status', 'Status') ?></label>
            <select class="form-control" id="invStatus" name="status">
              <option value="pending"><?= _st('status.pending', 'Pending') ?></option>
              <option value="paid"><?= _st('status.paid', 'Paid') ?></option>
              <option value="overdue"><?= _st('status.overdue', 'Overdue') ?></option>
              <option value="cancelled"><?= _st('status.cancelled', 'Cancelled') ?></option>
              <option value="refunded"><?= _st('status.refunded', 'Refunded') ?></option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label><?= _st('form.notes', 'Notes') ?></label>
          <textarea class="form-control" id="invNotes" name="notes" rows="3"></textarea>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelInvModal"><?= _st('cancel', 'Cancel') ?></button>
          <button type="submit" class="btn btn-primary"><?= _st('save', 'Save') ?></button>
        </div>
      </form>
    </div>
  </div>

  <!-- ═══ Escrow Modal ═══ -->
  <div class="modal" id="escModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3 id="escModalTitle"><?= _st('modal.add_escrow', 'Add Escrow Transaction') ?></h3>
        <button class="modal-close" id="btnCloseEscModal">&times;</button>
      </div>
      <form id="escForm">
        <input type="hidden" id="escId" value="">
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.order_id', 'Order ID') ?> *</label>
            <input type="number" class="form-control" id="escOrderId" name="order_id" required min="1">
          </div>
          <div class="form-group">
            <label><?= _st('form.tenant_id', 'Tenant ID') ?></label>
            <input type="number" class="form-control" id="escTenantId" name="tenant_id" min="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.buyer_id', 'Buyer ID') ?> *</label>
            <input type="number" class="form-control" id="escBuyerId" name="buyer_id" required min="1">
          </div>
          <div class="form-group">
            <label><?= _st('form.seller_id', 'Seller ID') ?> *</label>
            <input type="number" class="form-control" id="escSellerId" name="seller_id" required min="1">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.seller_type', 'Seller Type') ?></label>
            <input type="text" class="form-control" id="escSellerType" name="seller_type" value="vendor">
          </div>
          <div class="form-group">
            <label><?= _st('form.amount', 'Amount') ?> *</label>
            <input type="number" class="form-control" id="escAmount" name="amount" step="0.01" min="0" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('form.currency_code', 'Currency') ?></label>
            <input type="text" class="form-control" id="escCurrencyCode" name="currency_code" value="SAR" maxlength="3">
          </div>
          <div class="form-group">
            <label><?= _st('form.escrow_fee', 'Escrow Fee') ?></label>
            <input type="number" class="form-control" id="escFee" name="escrow_fee" step="0.01" min="0" value="0">
          </div>
        </div>
        <div class="form-group">
          <label><?= _st('form.status', 'Status') ?></label>
          <select class="form-control" id="escStatus" name="status">
            <option value="pending"><?= _st('status.pending', 'Pending') ?></option>
            <option value="funded"><?= _st('status.funded', 'Funded') ?></option>
            <option value="in_transit"><?= _st('status.in_transit', 'In Transit') ?></option>
            <option value="delivered"><?= _st('status.delivered', 'Delivered') ?></option>
            <option value="released"><?= _st('status.released', 'Released') ?></option>
            <option value="disputed"><?= _st('status.disputed', 'Disputed') ?></option>
            <option value="refunded"><?= _st('status.refunded', 'Refunded') ?></option>
            <option value="cancelled"><?= _st('status.cancelled', 'Cancelled') ?></option>
          </select>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelEscModal"><?= _st('cancel', 'Cancel') ?></button>
          <button type="submit" class="btn btn-primary"><?= _st('save', 'Save') ?></button>
        </div>
      </form>
    </div>
  </div>

  <!-- ═══ Translations Modal ═══ -->
  <div class="modal" id="translationsModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3><?= _st('translations.title', 'Plan Translations') ?></h3>
        <button class="modal-close" id="btnCloseTranslations">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="transPlanId">
        <div class="form-row">
          <div class="form-group">
            <label><?= _st('translations.language', 'Language') ?></label>
            <select class="form-control" id="transLang">
              <option value="ar">العربية</option>
              <option value="en">English</option>
              <option value="fr">Français</option>
              <option value="de">Deutsch</option>
              <option value="es">Español</option>
              <option value="tr">Türkçe</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label><?= _st('translations.plan_name', 'Plan Name') ?></label>
          <input type="text" class="form-control" id="transPlanName">
        </div>
        <div class="form-group">
          <label><?= _st('translations.description', 'Description') ?></label>
          <textarea class="form-control" id="transDescription" rows="3"></textarea>
        </div>
        <div class="form-group">
          <label><?= _st('translations.features', 'Features') ?></label>
          <textarea class="form-control" id="transFeatures" rows="3"></textarea>
        </div>
        <button class="btn btn-primary btn-sm" id="btnSaveTranslation"><?= _st('translations.save', 'Save Translation') ?></button>
        <table class="data-table" style="margin-top:10px">
          <thead>
            <tr>
              <th><?= _st('translations.language', 'Language') ?></th>
              <th><?= _st('translations.plan_name', 'Plan Name') ?></th>
              <th><?= _st('translations.description', 'Description') ?></th>
              <th><?= _st('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="translationsBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
window.SUBSCRIPTIONS_CONFIG = {
    csrf: <?= json_encode($csrf) ?>,
    lang: <?= json_encode($lang) ?>,
    dir: <?= json_encode($dir) ?>,
    canManage: <?= json_encode($canManage) ?>,
    canCreate: <?= json_encode($canCreate) ?>,
    canEdit: <?= json_encode($canEdit) ?>,
    canDelete: <?= json_encode($canDelete) ?>,
    isSuperAdmin: <?= json_encode($isSuperAdmin) ?>,
    tenantId: <?= json_encode((int)$tenantId) ?>,
    entityId: <?= json_encode((int)$entityId) ?>,
    userId: <?= json_encode((int)$userId) ?>,
    strings: <?= json_encode($_stStrings) ?>
};
</script>
<script src="/admin/assets/js/pages/subscriptions.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>