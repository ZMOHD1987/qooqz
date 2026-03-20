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
$canManage = $isSuperAdmin || in_array('manage_stock', $permissions, true) || in_array('manage_products', $permissions, true);
$canCreate = $canManage;
$canEdit   = $canManage;
$canDelete = $canManage;

if (!$canManage && !$isSuperAdmin) { http_response_code(403); die('Access denied'); }

// ─── Translation helper ───
$_smtAllowedLangs = ['ar','en','fr','de','es','it','pt','ru','zh','ja','ko','tr','nl','sv','pl','uk','hi','bn','id','ms','th','vi','cs','ro','hu','el'];
$_smtLangCode = in_array($lang, $_smtAllowedLangs) ? $lang : 'en';
$_smtStringsFile = __DIR__ . '/../../languages/StockMovements/' . $_smtLangCode . '.json';
$_smtStrings = file_exists($_smtStringsFile) ? (json_decode(file_get_contents($_smtStringsFile), true) ?: []) : [];
function _smt(string $key, string $fallback = ''): string {
    global $_smtStrings;
    $parts = explode('.', $key);
    $val = $_smtStrings;
    foreach ($parts as $p) {
        if (!is_array($val) || !isset($val[$p])) return $fallback ?: $key;
        $val = $val[$p];
    }
    return is_string($val) ? $val : ($fallback ?: $key);
}
?>
<link rel="stylesheet" href="/admin/assets/css/pages/stock_movements.css?v=<?= time() ?>">

<div class="page-container" dir="<?= $dir ?>">
  <div class="page-header">
    <div>
      <h2><?= _smt('title', 'Stock Movements') ?></h2>
      <p class="page-subtitle"><?= _smt('subtitle', 'Track product inventory - restock, sales, returns, adjustments') ?></p>
    </div>
    <div class="page-header-actions">
      <button class="btn btn-primary" id="btnAddMovement">+ <?= _smt('add_movement', 'Add Movement') ?></button>
    </div>
  </div>

  <!-- Product Lookup Section -->
  <div class="product-lookup-section">
    <h4><?= _smt('lookup.title', 'Product Lookup') ?></h4>
    <div class="lookup-methods">
      <div class="lookup-method">
        <label><?= _smt('scan_barcode', 'Scan Barcode') ?></label>
        <div class="input-group">
          <input type="text" class="form-control" id="barcodeInput" placeholder="<?= _smt('scan_placeholder', 'Enter barcode...') ?>">
          <button type="button" class="btn btn-secondary btn-sm" id="btnScanBarcode"><?= _smt('scan_btn', 'Scan') ?></button>
        </div>
      </div>
      <div class="lookup-method">
        <label><?= _smt('lookup.sku', 'Search by SKU') ?></label>
        <div class="input-group">
          <input type="text" class="form-control" id="skuInput" placeholder="<?= _smt('lookup.sku_placeholder', 'Enter SKU...') ?>">
          <button type="button" class="btn btn-secondary btn-sm" id="btnSearchSku"><?= _smt('lookup.search', 'Search') ?></button>
        </div>
      </div>
      <div class="lookup-method">
        <label><?= _smt('lookup.camera', 'Camera Scanner') ?></label>
        <button type="button" class="btn btn-info" id="btnCameraScanner">📷 <?= _smt('lookup.open_camera', 'Open Camera') ?></button>
      </div>
    </div>
    <small id="barcodeResult" style="display:none;margin-top:4px"></small>
    <!-- Camera Preview -->
    <div class="camera-container" id="cameraContainer" style="display:none">
      <video id="cameraVideo" autoplay playsinline></video>
      <canvas id="cameraCanvas" style="display:none"></canvas>
      <div class="camera-controls">
        <button type="button" class="btn btn-danger btn-sm" id="btnStopCamera"><?= _smt('lookup.stop_camera', 'Stop Camera') ?></button>
      </div>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="stats-grid" id="statsGrid">
    <div class="stat-card"><div class="stat-value" id="statTotal">0</div><div class="stat-label"><?= _smt('stats.total', 'Total Movements') ?></div></div>
    <div class="stat-card stat-restocked"><div class="stat-value" id="statRestocked">0</div><div class="stat-label"><?= _smt('stats.restocked', 'Restocked') ?></div></div>
    <div class="stat-card stat-sold"><div class="stat-value" id="statSold">0</div><div class="stat-label"><?= _smt('stats.sold', 'Sold') ?></div></div>
    <div class="stat-card stat-returned"><div class="stat-value" id="statReturned">0</div><div class="stat-label"><?= _smt('stats.returned', 'Returned') ?></div></div>
  </div>

  <!-- Filter Bar -->
  <div class="filter-bar">
    <input type="text" class="form-control" id="searchInput" placeholder="<?= _smt('filter.search', 'Search...') ?>">
    <select class="form-control" id="typeFilter">
      <option value=""><?= _smt('filter.all_types', 'All Types') ?></option>
      <option value="restock"><?= _smt('types.restock', 'Restock') ?></option>
      <option value="sale"><?= _smt('types.sale', 'Sale') ?></option>
      <option value="return"><?= _smt('types.return', 'Return') ?></option>
      <option value="adjustment"><?= _smt('types.adjustment', 'Adjustment') ?></option>
    </select>
    <input type="date" class="form-control" id="dateFrom" placeholder="<?= _smt('filter.date_from', 'From Date') ?>" title="<?= _smt('filter.date_from', 'From Date') ?>">
    <input type="date" class="form-control" id="dateTo" placeholder="<?= _smt('filter.date_to', 'To Date') ?>" title="<?= _smt('filter.date_to', 'To Date') ?>">
    <button class="btn btn-secondary" id="btnFilter"><?= _smt('filter.apply', 'Filter') ?></button>
    <button class="btn btn-secondary" id="btnClearFilter"><?= _smt('filter.clear', 'Clear Filters') ?></button>
  </div>

  <!-- Data Table -->
  <div class="card">
    <div class="card-body" style="overflow-x:auto">
      <table class="data-table" id="movementsTable">
        <thead>
          <tr>
            <th><?= _smt('table.id', 'ID') ?></th>
            <th><?= _smt('table.product', 'Product') ?></th>
            <th><?= _smt('table.variant', 'Variant') ?></th>
            <th><?= _smt('table.type', 'Type') ?></th>
            <th><?= _smt('table.quantity', 'Quantity') ?></th>
            <th><?= _smt('table.reference', 'Reference') ?></th>
            <th><?= _smt('table.notes', 'Notes') ?></th>
            <th><?= _smt('table.date', 'Date') ?></th>
            <th><?= _smt('table.actions', 'Actions') ?></th>
          </tr>
        </thead>
        <tbody id="movementsBody">
          <tr><td colspan="9" class="text-center"><?= _smt('table.no_records', 'No movements found') ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <div class="pagination-wrapper">
    <div class="pagination-info" id="paginationInfo"></div>
    <div class="pagination" id="pagination"></div>
  </div>

  <!-- Add Movement Modal -->
  <div class="modal" id="movementModal" style="display:none">
    <div class="modal-content">
      <div class="modal-header">
        <h3 id="modalTitle"><?= _smt('add_movement', 'Add Movement') ?></h3>
        <button class="modal-close" id="btnCloseModal">&times;</button>
      </div>
      <form id="movementForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" id="movementId" name="id" value="">
        <div class="form-group">
          <label><?= _smt('form.product_id', 'Product ID') ?> *</label>
          <div class="input-group">
            <input type="number" class="form-control" id="productIdInput" name="product_id" required min="1">
            <button type="button" class="btn btn-secondary btn-sm" id="btnLookupProduct"><?= _smt('filter.search', 'Search...') ?></button>
          </div>
          <small id="productName" class="lookup-name"></small>
        </div>
        <div class="form-group">
          <label><?= _smt('form.variant_id', 'Variant ID (optional)') ?></label>
          <input type="number" class="form-control" id="variantIdInput" name="variant_id" min="1">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label><?= _smt('form.type', 'Movement Type') ?> *</label>
            <select class="form-control" id="movementType" name="type" required>
              <option value="restock"><?= _smt('types.restock', 'Restock') ?></option>
              <option value="sale"><?= _smt('types.sale', 'Sale') ?></option>
              <option value="return"><?= _smt('types.return', 'Return') ?></option>
              <option value="adjustment"><?= _smt('types.adjustment', 'Adjustment') ?></option>
            </select>
          </div>
          <div class="form-group">
            <label><?= _smt('form.quantity', 'Quantity') ?> *</label>
            <input type="number" class="form-control" id="changeQuantity" name="change_quantity" required>
          </div>
        </div>
        <div class="form-group">
          <label><?= _smt('form.reference_id', 'Reference ID') ?></label>
          <input type="number" class="form-control" id="referenceId" name="reference_id" min="1">
        </div>
        <div class="form-group">
          <label><?= _smt('form.notes', 'Notes') ?></label>
          <textarea class="form-control" id="movementNotes" name="notes" rows="3"></textarea>
        </div>
        <div class="form-actions">
          <button type="button" class="btn btn-secondary" id="btnCancelModal"><?= _smt('form.cancel', 'Cancel') ?></button>
          <button type="submit" class="btn btn-primary"><?= _smt('form.save', 'Save') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
window.STOCK_MOVEMENTS_CONFIG = {
    canCreate: <?= json_encode($canCreate) ?>,
    canEdit: <?= json_encode($canEdit) ?>,
    canDelete: <?= json_encode($canDelete) ?>,
    isSuperAdmin: <?= json_encode($isSuperAdmin) ?>,
    csrfToken: <?= json_encode($csrf) ?>,
    lang: <?= json_encode($lang) ?>,
    dir: <?= json_encode($dir) ?>,
    strings: <?= json_encode($_smtStrings) ?>
};
</script>
<script src="/admin/assets/js/pages/stock_movements.js?v=<?= time() ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>