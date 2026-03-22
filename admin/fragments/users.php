<?php
declare(strict_types=1);

/**
 * /admin/fragments/users.php
 * Users Management — Production (matches discounts.php pattern)
 *
 * DB Table columns: id, username, email, password_hash,
 *   preferred_language, phone, is_active, created_at, updated_at
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// ════════════════════════════════════════════════════════════
// CONTEXT (matching discounts.php / categories.php pattern)
// ════════════════════════════════════════════════════════════
$payload     = $GLOBALS['ADMIN_UI'] ?? [];
$user        = $payload['user'] ?? (function_exists('admin_user') ? admin_user() : []);
$permissions = $user['permissions'] ?? [];
$roles       = $user['roles'] ?? [];
$lang        = $payload['lang'] ?? ($user['preferred_language'] ?? 'en');
$dir         = $payload['direction'] ?? (in_array($lang, ['ar','he','fa','ur']) ? 'rtl' : 'ltr');
$csrf        = $payload['csrf_token'] ?? (function_exists('admin_csrf') ? admin_csrf() : '');
$username    = $user['username'] ?? ($_SESSION['username'] ?? 'unknown');

$isSuperAdmin = in_array('super_admin', $roles, true) || (function_exists('is_super_admin') && is_super_admin());
$canManage = $isSuperAdmin || in_array('manage_users', $permissions, true);
$canCreate = $canManage;
$canEdit   = $canManage;
$canDelete = $canManage;

// ─── Translation helper ───
$_utAllowedLangs = ['ar','en','fr','de','es','it','pt','ru','zh','ja','ko','tr','nl','sv','pl','uk','hi','bn','id','ms','th','vi','cs','ro','hu','el'];
$_utLangCode = in_array($lang, $_utAllowedLangs) ? $lang : 'en';
$_utStringsFile = __DIR__ . '/../../languages/Users/' . $_utLangCode . '.json';
$_utStrings = file_exists($_utStringsFile) ? (json_decode(file_get_contents($_utStringsFile), true) ?: []) : [];
function _ut(string $key, string $fallback = ''): string {
    global $_utStrings;
    $parts = explode('.', $key);
    $val = $_utStrings;
    foreach ($parts as $p) {
        if (!is_array($val) || !isset($val[$p])) return $fallback ?: $key;
        $val = $val[$p];
    }
    return is_string($val) ? $val : ($fallback ?: $key);
}

if (!function_exists('assetVer')) {
    function assetVer(string $path): string
    {
        static $cache = [];
        if (!isset($cache[$path])) {
            $full         = $_SERVER['DOCUMENT_ROOT'] . $path;
            $cache[$path] = file_exists($full) ? (string) filemtime($full) : '0';
        }
        return $cache[$path];
    }
}
?>
<link rel="stylesheet"
      href="/admin/assets/css/pages/users.css?v=<?= assetVer('/admin/assets/css/pages/users.css') ?>">

<meta data-page="users"
      data-i18n-files="/languages/Users/<?= rawurlencode($_utLangCode) ?>.json">

<div class="page-container full-page-admin" dir="<?= $dir ?>">

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <h2><?= _ut('title', 'Users Management') ?></h2>
      <p class="page-subtitle"><?= _ut('subtitle', 'Manage system users') ?></p>
    </div>
    <div class="page-header-actions">
      <?php if ($canCreate): ?>
      <button class="btn btn-primary" id="btnAddUser" data-btn-slug="primary">+ <?= _ut('add_user', 'Add User') ?></button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="card">
    <div class="card-body" style="padding: clamp(8px, 1.5vw, 12px) clamp(12px, 2vw, 16px);">
      <div class="filters-grid">
        <div class="filter-group filter-group--search">
          <label class="filter-label" for="searchInput"><?= _ut('filter.search', 'Search') ?></label>
          <input type="text" class="form-control" id="searchInput" placeholder="<?= _ut('filter.search_placeholder', 'Search by username or email...') ?>">
        </div>
        <div class="filter-group">
          <label class="filter-label" for="languageFilter"><?= _ut('filter.language', 'Language') ?></label>
          <select class="form-control" id="languageFilter">
            <option value=""><?= _ut('filter.all_languages', 'All Languages') ?></option>
          </select>
        </div>
        <div class="filter-group">
          <label class="filter-label" for="statusFilter"><?= _ut('filter.status', 'Status') ?></label>
          <select class="form-control" id="statusFilter">
            <option value=""><?= _ut('filter.all_status', 'All Status') ?></option>
            <option value="1"><?= _ut('filter.active', 'Active') ?></option>
            <option value="0"><?= _ut('filter.inactive', 'Inactive') ?></option>
          </select>
        </div>
        <div class="filter-group filter-group--buttons">
          <label class="filter-label" aria-hidden="true">&nbsp;</label>
          <div class="filter-buttons">
            <button class="btn btn-sm btn-icon btn-primary" id="btnApplyFilters" data-btn-slug="primary" title="<?= _ut('filter.apply', 'Filter') ?>" aria-label="<?= _ut('filter.apply', 'Filter') ?>"><i class="fas fa-search" aria-hidden="true"></i></button>
            <button class="btn btn-sm btn-icon btn-secondary" id="btnResetFilters" data-btn-slug="secondary" title="<?= _ut('filter.clear', 'Clear') ?>" aria-label="<?= _ut('filter.clear', 'Clear') ?>"><i class="fas fa-times" aria-hidden="true"></i></button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Data Table -->
  <div class="card">
    <div class="card-body table-overflow">

      <!-- Loading State -->
      <div id="tableLoading" class="loading-state">
        <div class="spinner"></div>
        <p><?= _ut('loading', 'Loading...') ?></p>
      </div>

      <!-- Table Container -->
      <div id="tableContainer" style="display:none">
        <table class="data-table" id="usersTable">
          <thead>
            <tr>
              <th><?= _ut('table.id', 'ID') ?></th>
              <th><?= _ut('table.username', 'Username') ?></th>
              <th><?= _ut('table.email', 'Email') ?></th>
              <th><?= _ut('table.language', 'Language') ?></th>
              <th><?= _ut('table.phone', 'Phone') ?></th>
              <th><?= _ut('table.created_at', 'Created At') ?></th>
              <th><?= _ut('table.status', 'Status') ?></th>
              <th><?= _ut('table.actions', 'Actions') ?></th>
            </tr>
          </thead>
          <tbody id="tableBody">
            <tr><td colspan="8" class="text-center"><?= _ut('table.loading', 'Loading...') ?></td></tr>
          </tbody>
        </table>
      </div>

      <!-- Empty State -->
      <div id="emptyState" class="empty-state" style="display:none">
        <div class="empty-icon">👥</div>
        <h3><?= _ut('empty_title', 'No Users Found') ?></h3>
        <p><?= _ut('empty_message', 'Start by adding users') ?></p>
        <?php if ($canCreate): ?>
        <button class="btn btn-primary" data-btn-slug="primary" onclick="if(window.Users)Users.add()">
          <i class="fas fa-plus"></i> <?= _ut('add_first', 'Add First User') ?>
        </button>
        <?php endif; ?>
      </div>

      <!-- Error State -->
      <div id="errorState" class="error-state" style="display:none">
        <div class="error-icon">⚠️</div>
        <h3><?= _ut('error_title', 'Error Loading Data') ?></h3>
        <p id="errorMessage"></p>
        <button id="btnRetry" class="btn btn-secondary" data-btn-slug="secondary"><?= _ut('retry', 'Retry') ?></button>
      </div>

    </div>
  </div>

  <!-- Pagination -->
  <div class="pagination-wrapper">
    <div class="pagination-info" id="paginationInfo"></div>
    <div class="pagination" id="pagination"></div>
  </div>

  <!-- Create/Edit User Modal -->
  <div class="usr-modal-backdrop" id="userModal" style="display:none">
    <div class="usr-modal-panel">
      <div class="usr-modal-header">
        <h3 id="modalTitle"><?= _ut('modal.add_title', 'Add User') ?></h3>
        <button class="usr-modal-close" id="btnCloseModal" aria-label="Close">&times;</button>
      </div>
      <form id="userForm">
        <input type="hidden" id="formAction" value="add">
        <input type="hidden" id="editingId" value="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <div class="usr-modal-body">
          <div class="form-row">
            <div class="form-group">
              <label for="username"><?= _ut('form.username', 'Username') ?> *</label>
              <input type="text" id="username" name="username" class="form-control" required placeholder="<?= _ut('form.username_placeholder', 'Enter username') ?>">
            </div>
            <div class="form-group">
              <label for="email"><?= _ut('form.email', 'Email') ?> *</label>
              <input type="email" id="email" name="email" class="form-control" required placeholder="<?= _ut('form.email_placeholder', 'Enter email') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="password">
                <?= _ut('form.password', 'Password') ?>
                <span id="passwordLabel">*</span>
              </label>
              <input type="password" id="password" name="password" class="form-control" placeholder="<?= _ut('form.password_placeholder', 'Enter password') ?>">
            </div>
            <div class="form-group">
              <label for="preferred_language"><?= _ut('form.preferred_language', 'Language') ?></label>
              <select id="preferred_language" name="preferred_language" class="form-control">
                <option value="en">English</option>
                <option value="ar">Arabic</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="phone"><?= _ut('form.phone', 'Phone') ?></label>
              <input type="text" id="phone" name="phone" class="form-control" placeholder="<?= _ut('form.phone_placeholder', 'Enter phone') ?>">
            </div>
            <div class="form-group">
              <label>
                <input type="checkbox" id="is_active" name="is_active" checked>
                <?= _ut('form.active', 'Active') ?>
              </label>
            </div>
          </div>
        </div>

        <div class="usr-modal-footer">
          <button type="submit" class="btn btn-primary" data-btn-slug="primary"><?= _ut('form.save', 'Save') ?></button>
          <button type="button" class="btn btn-secondary" id="btnCancelForm" data-btn-slug="secondary"><?= _ut('form.cancel', 'Cancel') ?></button>
          <?php if ($canDelete): ?>
          <button type="button" id="btnDeleteUser" class="btn btn-danger" data-btn-slug="danger" style="display:none"><?= _ut('form.delete', 'Delete') ?></button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

</div>

<!-- Page Permissions Data -->
<script id="pagePermissions" type="application/json">
<?= json_encode(['canCreate' => $canCreate, 'canEdit' => $canEdit, 'canDelete' => $canDelete]) ?>
</script>

<script>
window.USER_LANGUAGE = '<?= htmlspecialchars($lang) ?>';
window.USERS_CONFIG = {
    lang:    <?= json_encode($_utLangCode) ?>,
    dir:     <?= json_encode($dir) ?>,
    strings: <?= json_encode($_utStrings, JSON_UNESCAPED_UNICODE) ?>
};
</script>

<!-- Load JS if embedded -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/pages/users.js?v=<?= assetVer('/admin/assets/js/pages/users.js') ?>"></script>
<script>
(function(){
    let attempts = 0;
    const check = setInterval(function(){
        attempts++;
        if (window.Users && typeof window.Users.init === 'function') {
            clearInterval(check);
            window.Users.init().catch(function(err){
                console.error('[Users] Init failed:', err);
            });
        } else if (attempts > 30) {
            clearInterval(check);
            console.error('[Users] Timeout after 30 attempts');
        }
    }, 200);
})();
</script>
<?php endif; ?>

<?php
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>
