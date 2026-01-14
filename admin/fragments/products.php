<?php
/**
 * admin/fragments/products.php
 * Theme-integrated Products management fragment - Created from scratch
 */
declare(strict_types=1);

// Start session if not started
if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Load bootstrap_admin_ui
$adminBootstrap = realpath(__DIR__ . '/../../api/bootstrap_admin_ui.php') ?: (__DIR__ . '/../../api/bootstrap_admin_ui.php');
$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? null;
if (is_readable($adminBootstrap)) {
    try {
        require_once $adminBootstrap;
    } catch (Throwable $e) {
        // Fallback to defaults
    }
}

// Fallback defaults
if (!isset($ADMIN_UI_PAYLOAD) || !is_array($ADMIN_UI_PAYLOAD)) {
    $ADMIN_UI_PAYLOAD = [
        'lang' => 'en',
        'direction' => 'ltr',
        'strings' => [],
        'user' => ['id' => 0, 'username' => 'guest', 'permissions' => []],
        'csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(16)),
        'theme' => ['colors' => [], 'buttons' => [], 'cards' => [], 'fonts' => [], 'designs' => []]
    ];
}

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Update CSRF token in payload
$ADMIN_UI_PAYLOAD['csrf_token'] = $_SESSION['csrf_token'];

// Ensure user structure
if (!isset($ADMIN_UI_PAYLOAD['user']) || !is_array($ADMIN_UI_PAYLOAD['user'])) {
    $ADMIN_UI_PAYLOAD['user'] = ['id' => 0, 'username' => 'guest', 'permissions' => []];
}
if (!empty($_SESSION['user_id'])) {
    $sessionUser = [
        'id' => (int)($_SESSION['user_id'] ?? 0),
        'username' => $_SESSION['username'] ?? $ADMIN_UI_PAYLOAD['user']['username'] ?? 'guest',
        'permissions' => $_SESSION['permissions'] ?? $ADMIN_UI_PAYLOAD['user']['permissions'] ?? [],
        'role_id' => $_SESSION['role_id'] ?? null
    ];
    $ADMIN_UI_PAYLOAD['user'] = array_merge($ADMIN_UI_PAYLOAD['user'], $sessionUser);
}

$user = $ADMIN_UI_PAYLOAD['user'];
if (empty($user['permissions'])) {
    if (!empty($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        $user['permissions'] = $_SESSION['permissions'];
    } elseif (!empty($_SESSION['permissions_map']) && is_array($_SESSION['permissions_map'])) {
        $user['permissions'] = array_keys(array_filter($_SESSION['permissions_map']));
    } else {
        $user['permissions'] = [];
    }
}

$isAdmin = isset($user['role_id']) && (int)$user['role_id'] === 1;

// Get language and direction
$lang = strtolower($ADMIN_UI_PAYLOAD['lang'] ?? 'en');
$dir = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';

// Ensure strings exists
if (!isset($ADMIN_UI_PAYLOAD['strings']) || !is_array($ADMIN_UI_PAYLOAD['strings'])) {
    $ADMIN_UI_PAYLOAD['strings'] = [];
}

// Helper functions
function s(string $key, $default = '') {
    global $ADMIN_UI_PAYLOAD;
    $strings = $ADMIN_UI_PAYLOAD['strings'] ?? [];
    return isset($strings[$key]) && is_scalar($strings[$key]) ? (string)$strings[$key] : $default;
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function safe_json($v) {
    $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($s === false) {
        array_walk_recursive($v, function (&$item) {
            if (is_string($item)) $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
        });
        $s = @json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
    return $s;
}
?>
<!DOCTYPE html>
<html lang="<?= h($lang) ?>" dir="<?= h($dir) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h(s('products', 'Products')) ?></title>
    <link rel="stylesheet" href="/admin/assets/css/admin-theme.css">
    <link rel="stylesheet" href="/admin/assets/css/pages/products.css">
</head>
<body>
    <div class="products-container">
        <div class="products-header">
            <h1><?= h(s('products_management', 'Products Management')) ?></h1>
            <button id="productNewBtn" class="btn btn-primary">
                <?= h(s('add_product', 'Add Product')) ?>
            </button>
        </div>

        <!-- Filters Section -->
        <div class="products-filters">
            <input type="text" id="productSearch" placeholder="<?= h(s('search_products', 'Search Products')) ?>" />
            <select id="filterVendor">
                <option value="">All Vendors</option>
            </select>
            <select id="filterStatus">
                <option value="">All Status</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
            <button id="productRefresh" class="btn btn-secondary">
                <?= h(s('refresh', 'Refresh')) ?>
            </button>
        </div>

        <!-- Products Table -->
        <div class="products-table-wrapper">
            <table class="products-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?= h(s('sku', 'SKU')) ?></th>
                        <th><?= h(s('name', 'Name')) ?></th>
                        <th><?= h(s('vendor', 'Vendor')) ?></th>
                        <th><?= h(s('price', 'Price')) ?></th>
                        <th><?= h(s('stock', 'Stock')) ?></th>
                        <th><?= h(s('status', 'Status')) ?></th>
                        <th><?= h(s('actions', 'Actions')) ?></th>
                    </tr>
                </thead>
                <tbody id="productsTbody">
                    <tr><td colspan="8"><?= h(s('loading', 'Loading...')) ?></td></tr>
                </tbody>
            </table>
        </div>

        <div id="productsCount" class="products-count"></div>

        <!-- Product Form Section -->
        <div id="productFormSection" class="product-form-section" style="display: none;">
            <div class="form-header">
                <h2 id="productFormTitle"><?= h(s('add_product', 'Add Product')) ?></h2>
                <button id="productCloseForm" class="btn-close">×</button>
            </div>
            
            <div id="productFormErrors" class="form-errors" style="display: none;"></div>

            <form id="productForm">
                <input type="hidden" id="product_id" name="id" />
                <input type="hidden" name="csrf_token" value="<?= h($ADMIN_UI_PAYLOAD['csrf_token']) ?>" />

                <div class="form-row">
                    <div class="form-group">
                        <label><?= h(s('sku', 'SKU')) ?> *</label>
                        <input type="text" name="sku" id="product_sku" required />
                    </div>
                    <div class="form-group">
                        <label><?= h(s('slug', 'Slug')) ?> *</label>
                        <input type="text" name="slug" id="product_slug" required />
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><?= h(s('barcode', 'Barcode')) ?></label>
                        <input type="text" name="barcode" id="product_barcode" />
                    </div>
                    <div class="form-group">
                        <label><?= h(s('product_type', 'Product Type')) ?></label>
                        <select name="product_type" id="product_product_type">
                            <option value="simple">Simple</option>
                            <option value="variable">Variable</option>
                            <option value="digital">Digital</option>
                            <option value="bundle">Bundle</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label><?= h(s('stock_quantity', 'Stock Quantity')) ?></label>
                        <input type="number" name="stock_quantity" id="product_stock_quantity" value="0" />
                    </div>
                    <div class="form-group">
                        <label><?= h(s('is_active', 'Active')) ?></label>
                        <select name="is_active" id="product_is_active">
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                </div>

                <!-- Translations Section -->
                <div class="form-section">
                    <h3><?= h(s('translations', 'Translations')) ?></h3>
                    <div id="product_translations_area"></div>
                    <button type="button" id="productAddLangBtn" class="btn btn-sm">
                        <?= h(s('add_language', 'Add Language')) ?>
                    </button>
                </div>

                <div class="form-actions">
                    <button type="submit" id="productSaveBtn" class="btn btn-primary">
                        <?= h(s('save', 'Save')) ?>
                    </button>
                    <button type="button" id="productResetBtn" class="btn btn-secondary">
                        <?= h(s('reset', 'Reset')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Pass data to JavaScript
        window.ADMIN_UI = <?= safe_json($ADMIN_UI_PAYLOAD) ?>;
        window.CSRF_TOKEN = <?= json_encode($ADMIN_UI_PAYLOAD['csrf_token']) ?>;
        window.CURRENT_USER = <?= safe_json($user) ?>;
        window.AVAILABLE_LANGUAGES = <?= json_encode(['en', 'ar']) ?>;
        window.ADMIN_LANG = <?= json_encode($lang) ?>;
        window.LANG_DIRECTION = <?= json_encode($dir) ?>;
    </script>
    <script src="/admin/assets/js/admin_core.js"></script>
    <script src="/admin/assets/js/pages/products.js"></script>
</body>
</html>
