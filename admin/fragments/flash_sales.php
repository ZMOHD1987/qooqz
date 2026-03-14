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

// Permissions (matching categories.php pattern)
$isSuperAdmin = in_array('super_admin', $roles, true) || (function_exists('is_super_admin') && is_super_admin());
$canManage = $isSuperAdmin || in_array('manage_flash_sales', $permissions, true) || in_array('manage_settings', $permissions, true);
$canCreate = $canManage;
$canEdit   = $canManage;
$canDelete = $canManage;

if (!$canManage && !$isSuperAdmin) { http_response_code(403); die('Access denied'); }

// ─── Translation helper ───
$_fsAllowedLangs = ['ar','en','fr','de','es','it','pt','ru','zh','ja','ko','tr','nl','sv','pl','uk','hi','bn','id','ms','th','vi','cs','ro','hu','el'];
$_fsLangCode = in_array($lang, $_fsAllowedLangs) ? $lang : 'en';
$_fsStringsFile = __DIR__ . '/../../languages/FlashSales/' . $_fsLangCode . '.json';
$_fsStrings = file_exists($_fsStringsFile) ? (json_decode(file_get_contents($_fsStringsFile), true) ?: []) : [];
function _fst(string $key, string $fallback = ''): string {
    global $_fsStrings;
    $parts = explode('.', $key);
    $val = $_fsStrings;
    foreach ($parts as $p) {
        if (!is_array($val) || !isset($val[$p])) return $fallback ?: $key;
        $val = $val[$p];
    }
    return is_string($val) ? $val : ($fallback ?: $key);
}
?>
<link rel="stylesheet" href="/admin/assets/css/pages/flash_sales.css?v=<?= time() ?>">

<div class="page-container" dir="<?= $dir ?>">
  <div class="page-header">
    <div>
      <h2><?= _fst('title', 'Flash Sales Management') ?></h2>
      <p class="page-subtitle"><?= _fst('subtitle', 'Manage flash sales, products, and translations') ?></p>
    </div>
    <div class="page-header-actions">
      <button class="btn btn-primary" id="btnAddFlashSale">+ <?= _fst('add_sale', 'Add Flash Sale') ?></button>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="stats-grid" id="statsGrid">
    <div class="stat-card"><div class="stat-value" id="statTotal">0</div><div class="stat-label"><?= _fst('stats.total', 'Total') ?></div></div>
    <div class="stat-card stat-active"><div class="stat-value" id="statActive">0</div><div class="stat-label"><?= _fst('stats.active', 'Active') ?></div></div>
    <div class="stat-card stat-upcoming"><div class="stat-value" id="statUpcoming">0</div><div class="stat-label"><?= _fst('stats.upcoming', 'Upcoming') ?></div></div>
    <div class="stat-card stat-ended"><div class="stat-value" id="statEnded">0</div><div class="stat-label"><?= _fst('stats.ended', 'Ended') ?></div></div>
  </div>

  <!-- Filters -->
  <div class="filter-bar">
    <input type="text" class="form-control" id="searchInput" placeholder="<?= _fst('filter.search', 'Search...') ?>">
    <select class="form-control" id="statusFilter">
      <option value=""><?= _fst('filter.all_status', 'All Status') ?></option>
      <option value="active"><?= _fst('filter.active', 'Active') ?></option>
      <option value="upcoming"><?= _fst('filter.upcoming', 'Upcoming') ?></option>
      <option value="ended"><?= _fst('filter.ended', 'Ended') ?></option>
    </select>
    <select class="form-control" id="activeFilter">
      <option value=""><?= _fst('filter.all_active', 'All') ?></option>
      <option value="1"><?= _fst('filter.enabled', 'Enabled') ?></option>
      <option value="0"><?= _fst('filter.disabled', 'Disabled') ?></option>
    </select>
    <button class="btn btn-secondary" id="btnFilter"><?= _fst('filter.apply', 'Filter') ?></button>
    <button class="btn btn-secondary" id="btnClearFilter"><?= _fst('filter.clear', 'Clear') ?></button>
  </div>

  <!-- Data Table -->
  <div class="card">
    <div class="card-body" style="overflow-x:auto">
      <table class="data-table" id="flashSalesTable">
        <thead>
          <tr>
            <th><?= _fst('table.id', 'ID') ?></th>
            <th><?= _fst('table.name', 'Name') ?></th>
            <th><?= _fst('table.discount', 'Discount') ?></th>
            <th><?= _fst('table.start', 'Start') ?></th>
            <th><?= _fst('table.end', 'End') ?></th>
            <th><?= _fst('table.status', 'Status') ?></th>
            <th><?= _fst('table.products', 'Products') ?></th>
            <th><?= _fst('table.sales', 'Sales') ?></th>
            <th><?= _fst('table.actions', 'Actions') ?></th>
          </tr>
        </thead>
        <tbody id="flashSalesBody">
          <tr><td colspan="9" class="text-center"><?= _fst('table.loading', 'Loading...') ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <div class="pagination-wrapper">
    <div class="pagination-info" id="paginationInfo"></div>
    <div class="pagination" id="pagination"></div>
  </div>

  <!-- Add/Edit Modal -->
  <div class="modal" id="flashSaleModal" style="display:none">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modalTitle"><?= _fst('modal.add_title', 'Add Flash Sale') ?></h3>
        <button class="modal-close" id="btnCloseModal">&times;</button>
      </div>
      <form id="flashSaleForm">
        <input type="hidden" id="flashSaleId" value="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="form-group">
          <label><?= _fst('form.entity', 'Entity') ?></label>
          <select class="form-control" id="entitySelect" name="entity_id">
            <option value=""><?= _fst('form.all_entities', 'All Entities (Global)') ?></option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _fst('form.sale_name', 'Sale Name') ?> *</label>
            <input type="text" class="form-control" id="saleName" name="sale_name" required>
          </div>
          <div class="form-group">
            <label><?= _fst('form.discount_type', 'Discount Type') ?></label>
            <select class="form-control" id="discountType" name="discount_type">
              <option value="percentage"><?= _fst('form.percentage', 'Percentage') ?></option>
              <option value="fixed"><?= _fst('form.fixed', 'Fixed Amount') ?></option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _fst('form.discount_value', 'Discount Value') ?> *</label>
            <input type="number" class="form-control" id="discountValue" name="discount_value" step="0.01" required>
          </div>
          <div class="form-group">
            <label><?= _fst('form.max_discount', 'Max Discount Amount') ?></label>
            <input type="number" class="form-control" id="maxDiscount" name="max_discount_amount" step="0.01">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _fst('form.start_date', 'Start Date') ?> *</label>
            <input type="datetime-local" class="form-control" id="startDate" name="start_date" required>
          </div>
          <div class="form-group">
            <label><?= _fst('form.end_date', 'End Date') ?> *</label>
            <input type="datetime-local" class="form-control" id="endDate" name="end_date" required>
          </div>
        </div>
        <div class="form-group">
          <label><?= _fst('form.description', 'Description') ?></label>
          <textarea class="form-control" id="saleDescription" name="description" rows="3"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _fst('form.banner_image', 'Banner Image URL') ?></label>
            <input type="text" class="form-control" id="bannerImage" name="banner_image">
          </div>
          <div class="form-group">
            <label><?= _fst('form.is_active', 'Active') ?></label>
            <select class="form-control" id="isActive" name="is_active">
              <option value="1"><?= _fst('yes', 'Yes') ?></option>
              <option value="0"><?= _fst('no', 'No') ?></option>
            </select>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelModal"><?= _fst('cancel', 'Cancel') ?></button>
          <button type="submit" class="btn btn-primary"><?= _fst('save', 'Save') ?></button>
        </div>
      </form>
    </div>
  </div>

  <!-- Products Modal -->
  <div class="modal" id="productsModal" style="display:none">
    <div class="modal-content modal-lg">
      <div class="modal-header">
        <h3 id="productsModalTitle"><?= _fst('products.title', 'Flash Sale Products') ?></h3>
        <button class="modal-close" id="btnCloseProducts">&times;</button>
      </div>
      <div class="modal-body">
        <button class="btn btn-primary btn-sm" id="btnAddProduct">+ <?= _fst('products.add', 'Add Product') ?></button>
        <table class="data-table" style="margin-top:10px">
          <thead>
            <tr>
              <th><?= _fst('products.product', 'Product') ?></th>
              <th><?= _fst('products.original_price', 'Original Price') ?></th>
              <th><?= _fst('products.sale_price', 'Sale Price') ?></th>
              <th><?= _fst('products.discount', 'Discount %') ?></th>
              <th><?= _fst('products.stock', 'Stock') ?></th>
              <th><?= _fst('products.sold', 'Sold') ?></th>
              <th><?= _fst('products.max_per_user', 'Max/User') ?></th>
              <th><?= _fst('products.active', 'Active') ?></th>
              <th><?= _fst('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="productsBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Add Product Sub-Modal -->
  <div class="modal" id="addProductModal" style="display:none">
    <div class="modal-content">
      <div class="modal-header">
        <h3><?= _fst('products.add', 'Add Product') ?></h3>
        <button class="modal-close" id="btnCloseAddProduct">&times;</button>
      </div>
      <form id="addProductForm">
        <input type="hidden" id="addProductFlashSaleId">
        <div class="form-group">
          <label><?= _fst('products.select_product', 'Select Product') ?> *</label>
          <select class="form-control" id="productSelect" required>
            <option value=""><?= _fst('products.select', 'Select...') ?></option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _fst('products.original_price', 'Original Price') ?> *</label>
            <input type="number" class="form-control" id="prodOrigPrice" step="0.01" required>
          </div>
          <div class="form-group">
            <label><?= _fst('products.sale_price', 'Sale Price') ?> *</label>
            <input type="number" class="form-control" id="prodSalePrice" step="0.01" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _fst('products.stock', 'Stock Quantity') ?></label>
            <input type="number" class="form-control" id="prodStock" value="0">
          </div>
          <div class="form-group">
            <label><?= _fst('products.max_per_user', 'Max Per User') ?></label>
            <input type="number" class="form-control" id="prodMaxPerUser" value="5">
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelAddProduct"><?= _fst('cancel', 'Cancel') ?></button>
          <button type="submit" class="btn btn-primary"><?= _fst('save', 'Save') ?></button>
        </div>
      </form>
    </div>
  </div>

  <!-- Translations Modal -->
  <div class="modal" id="translationsModal" style="display:none">
    <div class="modal-content">
      <div class="modal-header">
        <h3><?= _fst('translations.title', 'Translations') ?></h3>
        <button class="modal-close" id="btnCloseTranslations">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label><?= _fst('translations.language', 'Language') ?></label>
            <select class="form-control" id="transLang"></select>
          </div>
          <div class="form-group">
            <label><?= _fst('translations.field', 'Field') ?></label>
            <select class="form-control" id="transField">
              <option value="sale_name"><?= _fst('form.sale_name', 'Sale Name') ?></option>
              <option value="description"><?= _fst('form.description', 'Description') ?></option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label><?= _fst('translations.value', 'Value') ?></label>
          <input type="text" class="form-control" id="transValue">
        </div>
        <button class="btn btn-primary btn-sm" id="btnSaveTranslation"><?= _fst('translations.add', 'Add Translation') ?></button>
        <input type="hidden" id="transFlashSaleId">
        <table class="data-table" style="margin-top:10px">
          <thead>
            <tr>
              <th><?= _fst('translations.language', 'Language') ?></th>
              <th><?= _fst('translations.field', 'Field') ?></th>
              <th><?= _fst('translations.value', 'Value') ?></th>
              <th><?= _fst('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="translationsBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
window.FLASH_SALES_CONFIG = {
    csrf: <?= json_encode($csrf) ?>,
    lang: <?= json_encode($lang) ?>,
    dir: <?= json_encode($dir) ?>,
    canManage: <?= json_encode($canManage) ?>,
    canCreate: <?= json_encode($canCreate) ?>,
    canEdit: <?= json_encode($canEdit) ?>,
    canDelete: <?= json_encode($canDelete) ?>,
    isSuperAdmin: <?= json_encode($isSuperAdmin) ?>,
    strings: <?= json_encode($_fsStrings) ?>
};
</script>
<script src="/admin/assets/js/pages/flash_sales.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>