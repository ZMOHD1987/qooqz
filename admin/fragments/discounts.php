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

// Read context (matching categories.php pattern)
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

// Permissions (matching categories.php pattern)
$isSuperAdmin = in_array('super_admin', $roles, true) || (function_exists('is_super_admin') && is_super_admin());
$canManage = $isSuperAdmin || in_array('manage_discounts', $permissions, true);
$canCreate = $canManage;
$canEdit   = $canManage;
$canDelete = $canManage;

if (!$canManage && !$isSuperAdmin) { http_response_code(403); die('Access denied'); }

// ─── Translation helper ───
$_dtAllowedLangs = ['ar','en','fr','de','es','it','pt','ru','zh','ja','ko','tr','nl','sv','pl','uk','hi','bn','id','ms','th','vi','cs','ro','hu','el'];
$_dtLangCode = in_array($lang, $_dtAllowedLangs) ? $lang : 'en';
$_dtStringsFile = __DIR__ . '/../../languages/Discounts/' . $_dtLangCode . '.json';
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
<link rel="stylesheet" href="/admin/assets/css/pages/discounts.css?v=<?= time() ?>">

<div class="page-container" dir="<?= $dir ?>">
  <div class="page-header">
    <div>
      <h2><?= _dt('title', 'Discount Management') ?></h2>
      <p class="page-subtitle"><?= _dt('subtitle', 'Manage discount codes, rules, and redemptions') ?></p>
    </div>
    <div class="page-header-actions">
      <button class="btn btn-primary" id="btnAddDiscount">+ <?= _dt('add_discount', 'Add Discount') ?></button>
    </div>
  </div>

  <!-- Tenant/Entity Cascade Selector -->
  <div class="entity-selector" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px">
    <?php if ($isSuperAdmin): ?>
    <div class="form-group" style="margin:0">
      <label><?= _dt('tenant_id', 'Tenant ID') ?></label>
      <div style="display:flex;gap:6px">
        <input type="number" class="form-control" id="tenantIdInput" placeholder="<?= _dt('enter_tenant_id', 'Enter Tenant ID') ?>" min="1" style="width:140px">
        <button class="btn btn-secondary btn-sm" id="btnVerifyTenant"><?= _dt('verify', 'Verify') ?></button>
      </div>
      <small id="tenantName" style="display:none;color:var(--success-color,#28a745);margin-top:4px"></small>
    </div>
    <?php endif; ?>
    <div class="form-group" style="margin:0">
      <label><?= _dt('entity_selector', 'Entity') ?></label>
      <select class="form-control" id="entitySelector" style="min-width:200px">
        <option value=""><?= _dt('all_entities', 'All Entities') ?></option>
      </select>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="stats-grid" id="statsGrid">
    <div class="stat-card"><div class="stat-value" id="statTotal">0</div><div class="stat-label"><?= _dt('stats.total', 'Total') ?></div></div>
    <div class="stat-card stat-active"><div class="stat-value" id="statActive">0</div><div class="stat-label"><?= _dt('stats.active', 'Active') ?></div></div>
    <div class="stat-card stat-expired"><div class="stat-value" id="statExpired">0</div><div class="stat-label"><?= _dt('stats.expired', 'Expired') ?></div></div>
    <div class="stat-card stat-redemptions"><div class="stat-value" id="statRedemptions">0</div><div class="stat-label"><?= _dt('stats.total_redemptions', 'Total Redemptions') ?></div></div>
  </div>

  <!-- Filter Bar -->
  <div class="filter-bar">
    <input type="text" class="form-control" id="searchInput" placeholder="<?= _dt('filter.search', 'Search by code or name...') ?>">
    <select class="form-control" id="typeFilter">
      <option value=""><?= _dt('filter.all_types', 'All Types') ?></option>
      <option value="percentage"><?= _dt('filter.percentage', 'Percentage') ?></option>
      <option value="fixed"><?= _dt('filter.fixed', 'Fixed') ?></option>
      <option value="buy_x_get_y"><?= _dt('filter.buy_x_get_y', 'Buy X Get Y') ?></option>
      <option value="free_shipping"><?= _dt('filter.free_shipping', 'Free Shipping') ?></option>
    </select>
    <select class="form-control" id="statusFilter">
      <option value=""><?= _dt('filter.all_status', 'All Status') ?></option>
      <option value="active"><?= _dt('filter.active', 'Active') ?></option>
      <option value="inactive"><?= _dt('filter.inactive', 'Inactive') ?></option>
      <option value="expired"><?= _dt('filter.expired', 'Expired') ?></option>
      <option value="scheduled"><?= _dt('filter.scheduled', 'Scheduled') ?></option>
    </select>
    <input type="date" class="form-control" id="dateFrom" placeholder="<?= _dt('filter.date_from', 'From') ?>" title="<?= _dt('filter.date_from', 'From') ?>">
    <input type="date" class="form-control" id="dateTo" placeholder="<?= _dt('filter.date_to', 'To') ?>" title="<?= _dt('filter.date_to', 'To') ?>">
    <button class="btn btn-secondary" id="btnFilter"><?= _dt('filter.apply', 'Filter') ?></button>
    <button class="btn btn-secondary" id="btnClearFilter"><?= _dt('filter.clear', 'Clear') ?></button>
  </div>

  <!-- Data Table -->
  <div class="card">
    <div class="card-body" style="overflow-x:auto">
      <table class="data-table" id="discountsTable">
        <thead>
          <tr>
            <th><?= _dt('table.id', 'ID') ?></th>
            <th><?= _dt('table.code', 'Code') ?></th>
            <th><?= _dt('table.name', 'Name') ?></th>
            <th><?= _dt('table.type', 'Type') ?></th>
            <th><?= _dt('table.status', 'Status') ?></th>
            <th><?= _dt('table.priority', 'Priority') ?></th>
            <th><?= _dt('table.starts_at', 'Starts At') ?></th>
            <th><?= _dt('table.ends_at', 'Ends At') ?></th>
            <th><?= _dt('table.redemptions', 'Redemptions') ?></th>
            <th><?= _dt('table.actions', 'Actions') ?></th>
          </tr>
        </thead>
        <tbody id="discountsBody">
          <tr><td colspan="10" class="text-center"><?= _dt('table.loading', 'Loading...') ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <div class="pagination-wrapper">
    <div class="pagination-info" id="paginationInfo"></div>
    <div class="pagination" id="pagination"></div>
  </div>

  <!-- Create/Edit Discount Modal -->
  <div class="modal" id="discountModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3 id="modalTitle"><?= _dt('modal.add_title', 'Add Discount') ?></h3>
        <button class="modal-close" id="btnCloseModal">&times;</button>
      </div>
      <form id="discountForm">
        <input type="hidden" id="discountId" value="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="form-group">
          <label><?= _dt('form.entity', 'Entity') ?></label>
          <select class="form-control" id="entitySelect" name="entity_id">
            <option value=""><?= _dt('form.all_entities', 'All Entities (Global)') ?></option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.type', 'Discount Type') ?> *</label>
            <select class="form-control" id="discountType" name="type" required>
              <option value="percentage"><?= _dt('form.percentage', 'Percentage') ?></option>
              <option value="fixed"><?= _dt('form.fixed', 'Fixed Amount') ?></option>
              <option value="buy_x_get_y"><?= _dt('form.buy_x_get_y', 'Buy X Get Y') ?></option>
              <option value="free_shipping"><?= _dt('form.free_shipping', 'Free Shipping') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _dt('form.code', 'Code') ?> *</label>
            <div class="input-group">
              <input type="text" class="form-control" id="discountCode" name="code" required>
              <button type="button" class="btn btn-secondary btn-sm" id="btnGenerateCode"><?= _dt('form.generate_code', 'Auto-Generate') ?></button>
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.auto_apply', 'Auto Apply') ?></label>
            <select class="form-control" id="autoApply" name="auto_apply">
              <option value="0"><?= _dt('no', 'No') ?></option>
              <option value="1"><?= _dt('yes', 'Yes') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _dt('form.priority', 'Priority') ?></label>
            <input type="number" class="form-control" id="discountPriority" name="priority" value="0" min="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.is_stackable', 'Stackable') ?></label>
            <select class="form-control" id="isStackable" name="is_stackable">
              <option value="0"><?= _dt('no', 'No') ?></option>
              <option value="1"><?= _dt('yes', 'Yes') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _dt('form.currency_code', 'Currency Code') ?></label>
            <select class="form-control" id="currencyCode" name="currency_code">
              <option value=""><?= _dt('form.select_currency', 'Select Currency...') ?></option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.max_redemptions', 'Max Redemptions') ?></label>
            <input type="number" class="form-control" id="maxRedemptions" name="max_redemptions" min="0" placeholder="<?= _dt('form.unlimited', 'Unlimited') ?>">
          </div>
          <div class="form-group">
            <label><?= _dt('form.max_redemptions_per_user', 'Max Redemptions Per User') ?></label>
            <input type="number" class="form-control" id="maxRedemptionsPerUser" name="max_redemptions_per_user" min="0" placeholder="<?= _dt('form.unlimited', 'Unlimited') ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('form.starts_at', 'Starts At') ?></label>
            <input type="datetime-local" class="form-control" id="startsAt" name="starts_at">
          </div>
          <div class="form-group">
            <label><?= _dt('form.ends_at', 'Ends At') ?></label>
            <input type="datetime-local" class="form-control" id="endsAt" name="ends_at">
          </div>
        </div>
        <div class="form-group">
          <label><?= _dt('form.status', 'Status') ?></label>
          <select class="form-control" id="discountStatus" name="status">
            <option value="active"><?= _dt('status.active', 'Active') ?></option>
            <option value="inactive"><?= _dt('status.inactive', 'Inactive') ?></option>
            <option value="scheduled"><?= _dt('status.scheduled', 'Scheduled') ?></option>
          </select>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelModal"><?= _dt('cancel', 'Cancel') ?></button>
          <button type="submit" class="btn btn-primary"><?= _dt('save', 'Save') ?></button>
        </div>
      </form>
    </div>
  </div>

  <!-- Translations Modal -->
  <div class="modal" id="translationsModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3><?= _dt('translations.title', 'Discount Translations') ?></h3>
        <button class="modal-close" id="btnCloseTranslations">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="transDiscountId">
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('translations.language', 'Language') ?></label>
            <select class="form-control" id="transLang"></select>
          </div>
        </div>
        <div class="form-group">
          <label><?= _dt('translations.name', 'Name') ?></label>
          <input type="text" class="form-control" id="transName">
        </div>
        <div class="form-group">
          <label><?= _dt('translations.description', 'Description') ?></label>
          <textarea class="form-control" id="transDescription" rows="3"></textarea>
        </div>
        <div class="form-group">
          <label><?= _dt('translations.terms_conditions', 'Terms & Conditions') ?></label>
          <textarea class="form-control" id="transTermsConditions" rows="3"></textarea>
        </div>
        <div class="form-group">
          <label><?= _dt('translations.marketing_badge', 'Marketing Badge') ?></label>
          <input type="text" class="form-control" id="transMarketingBadge">
        </div>
        <button class="btn btn-primary btn-sm" id="btnSaveTranslation"><?= _dt('translations.save', 'Save Translation') ?></button>
        <table class="data-table" style="margin-top:10px">
          <thead>
            <tr>
              <th><?= _dt('translations.language', 'Language') ?></th>
              <th><?= _dt('translations.name', 'Name') ?></th>
              <th><?= _dt('translations.description', 'Description') ?></th>
              <th><?= _dt('translations.terms_conditions', 'Terms & Conditions') ?></th>
              <th><?= _dt('translations.marketing_badge', 'Marketing Badge') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="translationsBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Scopes Modal -->
  <div class="modal" id="scopesModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3><?= _dt('scopes.title', 'Discount Scopes') ?></h3>
        <button class="modal-close" id="btnCloseScopes">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="scopesDiscountId">
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('scopes.scope_type', 'Scope Type') ?></label>
            <select class="form-control" id="scopeType">
              <option value="all"><?= _dt('scopes.all', 'All') ?></option>
              <option value="product"><?= _dt('scopes.product', 'Product') ?></option>
              <option value="category"><?= _dt('scopes.category', 'Category') ?></option>
              <option value="brand"><?= _dt('scopes.brand', 'Brand') ?></option>
              <option value="collection"><?= _dt('scopes.collection', 'Collection') ?></option>
              <option value="supplier"><?= _dt('scopes.supplier', 'Supplier') ?></option>
              <option value="customer_group"><?= _dt('scopes.customer_group', 'Customer Group') ?></option>
              <option value="entity"><?= _dt('scopes.entity', 'Entity') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label id="scopeIdLabel"><?= _dt('scopes.scope_id', 'Scope ID') ?></label>
            <input type="text" class="form-control" id="scopeId" placeholder="<?= _dt('scopes.enter_id', 'Enter ID') ?>">
            <span id="scopeIdName" class="lookup-name"></span>
          </div>
          <div class="form-group form-group-btn">
            <button class="btn btn-primary btn-sm" id="btnAddScope">+ <?= _dt('scopes.add', 'Add Scope') ?></button>
          </div>
        </div>
        <table class="data-table" style="margin-top:10px">
          <thead>
            <tr>
              <th><?= _dt('scopes.scope_type', 'Scope Type') ?></th>
              <th><?= _dt('scopes.scope_id', 'Scope ID') ?></th>
              <th><?= _dt('scopes.name', 'Name') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="scopesBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Conditions Modal -->
  <div class="modal" id="conditionsModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3><?= _dt('conditions.title', 'Discount Conditions') ?></h3>
        <button class="modal-close" id="btnCloseConditions">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="conditionsDiscountId">
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('conditions.condition_type', 'Condition Type') ?></label>
            <select class="form-control" id="conditionType">
              <option value="min_cart_total"><?= _dt('conditions.min_cart_total', 'Min Cart Total') ?></option>
              <option value="min_items_count"><?= _dt('conditions.min_items_count', 'Min Items Count') ?></option>
              <option value="first_order_only"><?= _dt('conditions.first_order_only', 'First Order Only') ?></option>
              <option value="weekend_only"><?= _dt('conditions.weekend_only', 'Weekend Only') ?></option>
              <option value="specific_payment_method"><?= _dt('conditions.specific_payment_method', 'Specific Payment Method') ?></option>
              <option value="customer_segment"><?= _dt('conditions.customer_segment', 'Customer Segment') ?></option>
              <option value="geo_location"><?= _dt('conditions.geo_location', 'Geo Location') ?></option>
              <option value="time_window"><?= _dt('conditions.time_window', 'Time Window') ?></option>
              <option value="custom_rule"><?= _dt('conditions.custom_rule', 'Custom Rule') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _dt('conditions.operator', 'Operator') ?></label>
            <select class="form-control" id="conditionOperator">
              <option value="=">=</option>
              <option value="!=">!=</option>
              <option value=">">&gt;</option>
              <option value=">=">&gt;=</option>
              <option value="<">&lt;</option>
              <option value="<=">&lt;=</option>
              <option value="in">IN</option>
              <option value="not_in">NOT IN</option>
              <option value="between">BETWEEN</option>
              <option value="contains"><?= _dt('conditions.contains', 'Contains') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _dt('conditions.value', 'Value') ?></label>
            <input type="text" class="form-control" id="conditionValue">
          </div>
          <div class="form-group form-group-btn">
            <button class="btn btn-primary btn-sm" id="btnAddCondition">+ <?= _dt('conditions.add', 'Add Condition') ?></button>
          </div>
        </div>
        <table class="data-table" style="margin-top:10px">
          <thead>
            <tr>
              <th><?= _dt('conditions.condition_type', 'Condition Type') ?></th>
              <th><?= _dt('conditions.operator', 'Operator') ?></th>
              <th><?= _dt('conditions.value', 'Value') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="conditionsBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Actions Modal -->
  <div class="modal" id="actionsModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3><?= _dt('actions.title', 'Discount Actions') ?></h3>
        <button class="modal-close" id="btnCloseActions">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="actionsDiscountId">
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('actions.action_type', 'Action Type') ?></label>
            <select class="form-control" id="actionType">
              <option value="percentage"><?= _dt('actions.percentage', 'Percentage') ?></option>
              <option value="fixed"><?= _dt('actions.fixed', 'Fixed Amount') ?></option>
              <option value="free_shipping"><?= _dt('actions.free_shipping', 'Free Shipping') ?></option>
              <option value="buy_x_get_y"><?= _dt('actions.buy_x_get_y', 'Buy X Get Y') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _dt('actions.action_value', 'Action Value') ?></label>
            <input type="text" class="form-control" id="actionValue">
          </div>
          <div class="form-group form-group-btn">
            <button class="btn btn-primary btn-sm" id="btnAddAction">+ <?= _dt('actions.add', 'Add Action') ?></button>
          </div>
        </div>
        <table class="data-table" style="margin-top:10px">
          <thead>
            <tr>
              <th><?= _dt('actions.action_type', 'Action Type') ?></th>
              <th><?= _dt('actions.action_value', 'Action Value') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="actionsBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Exclusions Modal -->
  <div class="modal" id="exclusionsModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3><?= _dt('exclusions.title', 'Discount Exclusions') ?></h3>
        <button class="modal-close" id="btnCloseExclusions">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="exclusionsDiscountId">
        <div class="form-row">
          <div class="form-group">
            <label><?= _dt('exclusions.select_discount', 'Select Discount to Exclude') ?></label>
            <select class="form-control" id="excludeDiscountSelect">
              <option value=""><?= _dt('exclusions.select', 'Select...') ?></option>
            </select>
          </div>
          <div class="form-group form-group-btn">
            <button class="btn btn-primary btn-sm" id="btnAddExclusion">+ <?= _dt('exclusions.add', 'Add Exclusion') ?></button>
          </div>
        </div>
        <table class="data-table" style="margin-top:10px">
          <thead>
            <tr>
              <th><?= _dt('exclusions.excluded_discount', 'Excluded Discount') ?></th>
              <th><?= _dt('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="exclusionsBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Redemptions Modal -->
  <div class="modal" id="redemptionsModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3><?= _dt('redemptions.title', 'Discount Redemptions') ?></h3>
        <button class="modal-close" id="btnCloseRedemptions">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="redemptionsDiscountId">
        <table class="data-table">
          <thead>
            <tr>
              <th><?= _dt('redemptions.user_id', 'User ID') ?></th>
              <th><?= _dt('redemptions.order_id', 'Order ID') ?></th>
              <th><?= _dt('redemptions.amount_discounted', 'Amount Discounted') ?></th>
              <th><?= _dt('redemptions.currency_code', 'Currency') ?></th>
              <th><?= _dt('redemptions.redeemed_at', 'Redeemed At') ?></th>
            </tr>
          </thead>
          <tbody id="redemptionsBody"></tbody>
        </table>
        <div class="pagination-wrapper">
          <div class="pagination-info" id="redemptionsPaginationInfo"></div>
          <div class="pagination" id="redemptionsPagination"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.DISCOUNTS_CONFIG = {
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
    strings: <?= json_encode($_dtStrings) ?>
};
</script>
<script src="/admin/assets/js/pages/discounts.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>