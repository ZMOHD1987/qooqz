<?php
declare(strict_types=1);

/**
 * /admin/fragments/products.php
 * Production Version - Complete Rewrite based on Categories Pattern
 * 
 * ✅ Uses new permission system (role-based + resource-based)
 * ✅ Compatible with tenant_users table
 * ✅ Full multi-language translation support
 * ✅ Advanced product management (variants, attributes, images, categories, pricing)
 * ✅ Production-ready with all APIs integrated
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT / HEADER
// ════════════════════════════════════════════════════════════
if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// ════════════════════════════════════════════════════════════
// VERIFY USER IS LOGGED IN
// ════════════════════════════════════════════════════════════
if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    } else {
        header('Location: /admin/login.php');
        exit;
    }
}

// ════════════════════════════════════════════════════════════
// GET USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user     = admin_user();
$lang     = admin_lang();
$dir      = in_array($lang, ['ar', 'he', 'fa', 'ur'], true) ? 'rtl' : 'ltr';
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();
$userId   = admin_user_id();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════

// Method 1: Using role-based permissions
$canManageProducts = can('products.manage') || can('products.create');

// Method 2: Using resource-based permissions (recommended for granular control)
$canViewAll = can_view_all('products');
$canViewOwn = can_view_own('products');
$canViewTenant = can_view_tenant('products');
$canCreate = can_create('products');
$canEditAll = can_edit_all('products');
$canEditOwn = can_edit_own('products');
$canDeleteAll = can_delete_all('products');
$canDeleteOwn = can_delete_own('products');

// Combined permissions for UI
$canView = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit = $canEditAll || $canEditOwn || $canManageProducts;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManageProducts;
$canDuplicate = $canCreate;

// If user has no view permission at all, deny access
if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view products');
    }
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
function __t($key, $fallback = '') {
    if (function_exists('i18n_get')) {
        $v = i18n_get($key);
        return $v ?? ($fallback ?? $key);
    }
    return $fallback ?? $key;
}

function __tr($key, $replacements = []) {
    $text = __t($key, $key);
    foreach ($replacements as $ph => $val) {
        $text = str_replace("{" . $ph . "}", (string)$val, $text);
    }
    return $text;
}

// ════════════════════════════════════════════════════════════
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/api';

// ════════════════════════════════════════════════════════════
// TRANSLATIONS (server-side — injected via PRODUCTS_CONFIG.strings)
// ════════════════════════════════════════════════════════════
$_prdStrings     = [];
$_prdAllowedLangs = [
    'ar','en','fr','tr','ur','de','es','fa','he','hi',
    'zh','ja','ko','pt','ru','it','nl','sv','pl','th',
    'vi','id','ms','bn','sw','tl',
];
$_prdSafeLang = in_array($lang, $_prdAllowedLangs, true) ? $lang : 'en';
$_prdLangFile = __DIR__ . '/../../languages/Product/' . $_prdSafeLang . '.json';

if (file_exists($_prdLangFile)) {
    $_prdJson = json_decode(file_get_contents($_prdLangFile), true);
    if (is_array($_prdJson)) {
        $_prdStrings = isset($_prdJson['strings']) ? $_prdJson['strings'] : $_prdJson;
    }
}

?>
<!-- Structural layout CSS (uses only var() for all visual properties)
     Button/card/color CSS comes from AdminUiThemeLoader::generateCss()
     injected by header.php via <style id="dynamic-theme-db">. -->
<?php
if (!function_exists('assetVer')) {
    function assetVer(string $path): string {
        static $cache = [];
        if (!isset($cache[$path])) {
            $full = $_SERVER['DOCUMENT_ROOT'] . $path;
            $cache[$path] = file_exists($full) ? (string)filemtime($full) : '1';
        }
        return $cache[$path];
    }
}
?>
<link rel="stylesheet" href="/admin/assets/css/pages/products.css?v=<?= assetVer('/admin/assets/css/pages/products.css') ?>">

<!-- Page Meta -->
<meta data-page="products"
      data-assets-css="/admin/assets/css/pages/products.css"
      data-assets-js="/admin/assets/js/pages/products.js"
      data-i18n-files="/languages/Product/<?= rawurlencode($_prdSafeLang) ?>.json">

<!-- Page Container -->
<div class="page-container" id="productsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="products.title"><?= __t('products.title', 'Products') ?></h1>
            <p class="page-subtitle" data-i18n="products.subtitle"><?= __t('products.subtitle', 'Manage your product catalog') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnImportCsv" class="btn btn-secondary">
                <i class="fas fa-file-csv"></i>
                <span data-i18n="csv.import_button"><?= __t('csv.import_button', 'Import CSV') ?></span>
            </button>
            <button id="btnAddProduct" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="products.add_new"><?= __t('products.add_new', 'Add Product') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Form Container -->
    <div id="productFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title"><?= __t('form.add_title', 'Add Product') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm" aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="productForm" novalidate>
                <!-- Hidden Fields -->
                <input type="hidden" id="formId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="prodTenantId" name="tenant_id" value="<?= $tenantId ?>">
                <input type="hidden" id="prodTranslationsData" name="translations_data">
                <input type="hidden" id="prodAttributesData" name="attributes_data">
                <input type="hidden" id="prodVariantsData" name="variants_data">
                <input type="hidden" id="prodCategoriesData" name="categories_data">

                <!-- Tabs Navigation -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="general">
                        <i class="fas fa-info-circle"></i>
                        <span data-i18n="tabs.general"><?= __t('tabs.general', 'General') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="physical">
                        <i class="fas fa-ruler-combined"></i>
                        <span data-i18n="tabs.physical"><?= __t('tabs.physical', 'Physical Attributes') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="attributes">
                        <i class="fas fa-list-alt"></i>
                        <span data-i18n="tabs.attributes"><?= __t('tabs.attributes', 'Attributes') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="variants">
                        <i class="fas fa-layer-group"></i>
                        <span data-i18n="tabs.variants"><?= __t('tabs.variants', 'Variants') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="images">
                        <i class="fas fa-images"></i>
                        <span data-i18n="tabs.images"><?= __t('tabs.images', 'Images') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="translations">
                        <i class="fas fa-language"></i>
                        <span data-i18n="tabs.translations"><?= __t('tabs.translations', 'Translations') ?></span>
                    </button>
                </div>

                <!-- Tab: General -->
                <div class="tab-content active" id="tab-general">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prodSku" data-i18n="form.fields.sku.label">
                                <?= __t('form.fields.sku.label', 'SKU') ?>
                            </label>
                            <input type="text" id="prodSku" name="sku" class="form-control"
                                   data-i18n-placeholder="form.fields.sku.placeholder"
                                   placeholder="<?= __t('form.fields.sku.placeholder', 'Auto-generated if empty') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="prodSlug" data-i18n="form.fields.slug.label">
                                <?= __t('form.fields.slug.label', 'Slug') ?>
                            </label>
                            <input type="text" id="prodSlug" name="slug" class="form-control"
                                   data-i18n-placeholder="form.fields.slug.placeholder"
                                   placeholder="<?= __t('form.fields.slug.placeholder', 'product-slug') ?>">
                        </div>

                        <div class="form-group">
                            <label for="prodBarcode" data-i18n="form.fields.barcode.label">
                                <?= __t('form.fields.barcode.label', 'Barcode') ?>
                            </label>
                            <input type="text" id="prodBarcode" name="barcode" class="form-control"
                                   data-i18n-placeholder="form.fields.barcode.placeholder"
                                   placeholder="<?= __t('form.fields.barcode.placeholder', 'Enter barcode') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="prodType" data-i18n="form.fields.product_type.label">
                                <?= __t('form.fields.product_type.label', 'Product Type') ?>
                            </label>
                            <select id="prodType" name="product_type_id" class="form-control">
                                <option value="">Loading...</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="prodBrand" data-i18n="form.fields.brand.label">
                                <?= __t('form.fields.brand.label', 'Brand') ?>
                            </label>
                            <select id="prodBrand" name="brand_id" class="form-control">
                                <option value="">Loading...</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="prodIsActive" data-i18n="form.fields.status.label">
                                <?= __t('form.fields.status.label', 'Status') ?>
                            </label>
                            <select id="prodIsActive" name="is_active" class="form-control">
                                <option value="1" data-i18n="form.fields.status.active">
                                    <?= __t('form.fields.status.active', 'Active') ?>
                                </option>
                                <option value="0" data-i18n="form.fields.status.inactive">
                                    <?= __t('form.fields.status.inactive', 'Inactive') ?>
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="prodIsFeatured" data-i18n="form.fields.featured.label">
                                <?= __t('form.fields.featured.label', 'Featured') ?>
                            </label>
                            <select id="prodIsFeatured" name="is_featured" class="form-control">
                                <option value="0" data-i18n="form.fields.featured.no">
                                    <?= __t('form.fields.featured.no', 'No') ?>
                                </option>
                                <option value="1" data-i18n="form.fields.featured.yes">
                                    <?= __t('form.fields.featured.yes', 'Yes') ?>
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="prodIsBestseller" data-i18n="form.fields.bestseller.label">
                                <?= __t('form.fields.bestseller.label', 'Bestseller') ?>
                            </label>
                            <select id="prodIsBestseller" name="is_bestseller" class="form-control">
                                <option value="0" data-i18n="form.fields.bestseller.no">No</option>
                                <option value="1" data-i18n="form.fields.bestseller.yes">Yes</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="prodIsNew" data-i18n="form.fields.new.label">
                                <?= __t('form.fields.new.label', 'New') ?>
                            </label>
                            <select id="prodIsNew" name="is_new" class="form-control">
                                <option value="0" data-i18n="form.fields.new.no">No</option>
                                <option value="1" data-i18n="form.fields.new.yes">Yes</option>
                            </select>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════ -->
                    <!-- English Content (Default Language) - Always Visible -->
                    <!-- ═══════════════════════════════════════════════ -->
                    <div class="english-content-section">
                        <h4>
                            <span class="lang-badge">EN</span>
                            English Content <span class="lang-note">(Default Language — required)</span>
                        </h4>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="enProdName" class="required">Product Name (English)</label>
                                <input type="text" id="enProdName" name="en_name" class="form-control" required
                                       placeholder="Enter product name in English">
                                <div class="invalid-feedback">English product name is required</div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="enProdShortDesc">Short Description (English)</label>
                                <textarea id="enProdShortDesc" name="en_short_description" class="form-control" rows="2"
                                          placeholder="Brief product summary in English"></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="enProdDesc">Full Description (English)</label>
                                <textarea id="enProdDesc" name="en_description" class="form-control" rows="4"
                                          placeholder="Detailed product description in English"></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="enProdSpecs">Specifications (English)</label>
                                <textarea id="enProdSpecs" name="en_specifications" class="form-control" rows="3"
                                          placeholder="Technical specifications in English"></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="enMetaTitle">Meta Title (English)</label>
                                <input type="text" id="enMetaTitle" name="en_meta_title" class="form-control"
                                       placeholder="SEO meta title in English">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="enMetaDescription">Meta Description (English)</label>
                                <textarea id="enMetaDescription" name="en_meta_description" class="form-control" rows="2"
                                          placeholder="SEO meta description in English"></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="enMetaKeywords">Meta Keywords (English)</label>
                                <input type="text" id="enMetaKeywords" name="en_meta_keywords" class="form-control"
                                       placeholder="keyword1, keyword2, keyword3">
                            </div>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════ -->
                    <!-- Pricing (merged into General)                   -->
                    <!-- ═══════════════════════════════════════════════ -->
                    <h4 class="section-heading" data-i18n="tabs.pricing">
                        <i class="fas fa-tag"></i> <?= __t('tabs.pricing', 'Pricing') ?>
                    </h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prodPrice" data-i18n="form.fields.price.label">
                                <?= __t('form.fields.price.label', 'Price') ?>
                            </label>
                            <input type="number" id="prodPrice" name="price" class="form-control" step="0.01" min="0">
                        </div>

                        <div class="form-group">
                            <label for="prodComparePrice" data-i18n="form.fields.compare_price.label">
                                <?= __t('form.fields.compare_price.label', 'Compare at Price') ?>
                            </label>
                            <input type="number" id="prodComparePrice" name="compare_at_price" class="form-control" step="0.01" min="0">
                        </div>

                        <div class="form-group">
                            <label for="prodCostPrice" data-i18n="form.fields.cost_price.label">
                                <?= __t('form.fields.cost_price.label', 'Cost Price') ?>
                            </label>
                            <input type="number" id="prodCostPrice" name="cost_price" class="form-control" step="0.01" min="0">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="prodCurrency" data-i18n="form.fields.currency.label">
                                <?= __t('form.fields.currency.label', 'Currency') ?>
                            </label>
                            <select id="prodCurrency" name="currency_code" class="form-control">
                                <option value=""><?= __t('form.fields.currency.select', 'Select currency') ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="prodTaxRate" data-i18n="form.fields.tax_rate.label">
                                <?= __t('form.fields.tax_rate.label', 'Tax Rate %') ?>
                            </label>
                            <input type="number" id="prodTaxRate" name="tax_rate" class="form-control" step="0.01" min="0">
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════ -->
                    <!-- Stock / Inventory (merged into General)         -->
                    <!-- ═══════════════════════════════════════════════ -->
                    <h4 class="section-heading" data-i18n="tabs.inventory">
                        <i class="fas fa-boxes"></i> <?= __t('tabs.inventory', 'Inventory') ?>
                    </h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prodStockQty" data-i18n="form.fields.stock_quantity.label">
                                <?= __t('form.fields.stock_quantity.label', 'Stock Quantity') ?>
                            </label>
                            <input type="number" id="prodStockQty" name="stock_quantity" class="form-control" value="0" min="0">
                        </div>

                        <div class="form-group">
                            <label for="prodLowStock" data-i18n="form.fields.low_stock_threshold.label">
                                <?= __t('form.fields.low_stock_threshold.label', 'Low Stock Threshold') ?>
                            </label>
                            <input type="number" id="prodLowStock" name="low_stock_threshold" class="form-control" value="5" min="0">
                        </div>

                        <div class="form-group">
                            <label for="prodStockStatus" data-i18n="form.fields.stock_status.label">
                                <?= __t('form.fields.stock_status.label', 'Stock Status') ?>
                            </label>
                            <select id="prodStockStatus" name="stock_status" class="form-control">
                                <option value="in_stock" data-i18n="form.fields.stock_status.in_stock"><?= __t('form.fields.stock_status.in_stock', 'In Stock') ?></option>
                                <option value="out_of_stock" data-i18n="form.fields.stock_status.out_of_stock"><?= __t('form.fields.stock_status.out_of_stock', 'Out of Stock') ?></option>
                                <option value="on_backorder" data-i18n="form.fields.stock_status.on_backorder"><?= __t('form.fields.stock_status.on_backorder', 'On Backorder') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="prodManageStock" data-i18n="form.fields.manage_stock.label">
                                <?= __t('form.fields.manage_stock.label', 'Manage Stock') ?>
                            </label>
                            <select id="prodManageStock" name="manage_stock" class="form-control">
                                <option value="1" data-i18n="form.fields.manage_stock.yes"><?= __t('form.fields.manage_stock.yes', 'Yes') ?></option>
                                <option value="0" data-i18n="form.fields.manage_stock.no"><?= __t('form.fields.manage_stock.no', 'No') ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="prodAllowBackorder" data-i18n="form.fields.allow_backorder.label">
                                <?= __t('form.fields.allow_backorder.label', 'Allow Backorder') ?>
                            </label>
                            <select id="prodAllowBackorder" name="allow_backorder" class="form-control">
                                <option value="0" data-i18n="form.fields.allow_backorder.no"><?= __t('form.fields.allow_backorder.no', 'No') ?></option>
                                <option value="1" data-i18n="form.fields.allow_backorder.yes"><?= __t('form.fields.allow_backorder.yes', 'Yes') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- ═══════════════════════════════════════════════ -->
                    <!-- Categories (merged into General)                -->
                    <!-- ═══════════════════════════════════════════════ -->
                    <h4 class="section-heading" data-i18n="tabs.categories">
                        <i class="fas fa-folder-tree"></i> <?= __t('tabs.categories', 'Categories') ?>
                    </h4>
                    <div class="form-group">
                        <div id="prodCategoriesTree" class="categories-tree"></div>
                    </div>
                </div>

                <!-- Tab: Physical Attributes -->
                <div class="tab-content" id="tab-physical" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prodWeight" data-i18n="form.fields.weight.label">
                                <?= __t('form.fields.weight.label', 'Weight') ?>
                            </label>
                            <input type="number" id="prodWeight" name="weight" class="form-control" step="0.001" min="0">
                        </div>

                        <div class="form-group">
                            <label for="prodWeightUnit" data-i18n="form.fields.weight_unit.label">
                                <?= __t('form.fields.weight_unit.label', 'Weight Unit') ?>
                            </label>
                            <select id="prodWeightUnit" name="weight_unit" class="form-control">
                                <option value="kg">kg</option>
                                <option value="g">g</option>
                                <option value="lb">lb</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="prodLength" data-i18n="form.fields.length.label">
                                <?= __t('form.fields.length.label', 'Length') ?>
                            </label>
                            <input type="number" id="prodLength" name="length" class="form-control" step="0.01" min="0">
                        </div>

                        <div class="form-group">
                            <label for="prodWidth" data-i18n="form.fields.width.label">
                                <?= __t('form.fields.width.label', 'Width') ?>
                            </label>
                            <input type="number" id="prodWidth" name="width" class="form-control" step="0.01" min="0">
                        </div>

                        <div class="form-group">
                            <label for="prodHeight" data-i18n="form.fields.height.label">
                                <?= __t('form.fields.height.label', 'Height') ?>
                            </label>
                            <input type="number" id="prodHeight" name="height" class="form-control" step="0.01" min="0">
                        </div>

                        <div class="form-group">
                            <label for="prodDimensionUnit" data-i18n="form.fields.dimension_unit.label">
                                <?= __t('form.fields.dimension_unit.label', 'Dimension Unit') ?>
                            </label>
                            <select id="prodDimensionUnit" name="dimension_unit" class="form-control">
                                <option value="cm">cm</option>
                                <option value="mm">mm</option>
                                <option value="in">in</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Tab: Attributes -->
                <div class="tab-content" id="tab-attributes" style="display:none">
                    <div class="attrs-controls">
                        <select id="attrSelect" class="form-control"></select>
                        <button type="button" id="btnAddAttribute" class="btn btn-primary" data-i18n="form.buttons.add_attribute">
                            <?= __t('form.buttons.add_attribute', 'Add Attribute') ?>
                        </button>
                    </div>
                    <div id="prodAttributesList"></div>
                </div>

                <!-- Tab: Variants -->
                <div class="tab-content" id="tab-variants" style="display:none">
                    <div class="variant-controls">
                        <button type="button" id="btnGenerateVariants" class="btn btn-secondary" data-i18n="form.buttons.generate_variants">
                            <?= __t('form.buttons.generate_variants', 'Generate Variants from Attributes') ?>
                        </button>
                        <button type="button" id="btnAddVariant" class="btn btn-primary" data-i18n="form.buttons.add_variant">
                            <?= __t('form.buttons.add_variant', 'Add Variant Manually') ?>
                        </button>
                    </div>
                    <div id="prodVariantsList"></div>
                </div>

                <!-- Tab: Images -->
                <div class="tab-content" id="tab-images" style="display:none">
                    <div class="form-group">
                        <label data-i18n="form.fields.images.label">
                            <?= __t('form.fields.images.label', 'Product Images') ?>
                        </label>
                        <div class="image-upload-section">
                            <button type="button" id="prodSelectImageBtn" class="btn btn-secondary btn-full-width" data-i18n="common.select_image">
                                <?= __t('common.select_image', 'Select Images from Studio') ?>
                            </button>
                            <div id="prodImagesPreview" class="images-grid"></div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Translations -->
                <div class="tab-content" id="tab-translations" style="display:none">
                    <div class="translations-section">
                        <h4>
                            <i class="fas fa-language"></i> Translations
                        </h4>
                        <div class="info-hint-box">
                            <i class="fas fa-info-circle"></i>
                            <strong>English</strong> translation fields are in the <strong>General tab</strong>. Use this tab to add translations for other languages (Arabic, French, etc.).
                        </div>
                        <div id="prodTranslations" class="translation-panels"></div>
                        <div class="form-group">
                            <label for="prodLangSelect" data-i18n="form.translations.select_lang">Select Language</label>
                            <div class="lang-add-row">
                                <select id="prodLangSelect" class="form-control">
                                    <option value="">Choose language</option>
                                </select>
                                <button type="button" id="prodAddLangBtn" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Translation
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="btnSubmitForm">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.buttons.save"><?= __t('form.buttons.save', 'Save') ?></span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelForm" data-i18n="form.buttons.cancel">
                        <?= __t('form.buttons.cancel', 'Cancel') ?>
                    </button>
                    <?php if ($canDelete): ?>
                    <button type="button" id="btnDeleteProduct" class="btn btn-danger" style="display:none">
                        <i class="fas fa-trash"></i>
                        <span data-i18n="table.actions.delete"><?= __t('table.actions.delete', 'Delete') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchInput" data-i18n="filters.search">
                        <?= __t('filters.search', 'Search') ?>
                    </label>
                    <input type="text" id="searchInput" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= __t('filters.search_placeholder', 'Search products...') ?>">
                </div>

                <?php if (is_super_admin()): ?>
                <div class="filter-group">
                    <label for="tenantFilter" data-i18n="filters.tenant_id">
                        <?= __t('filters.tenant_id', 'Tenant ID') ?>
                    </label>
                    <input type="number" id="tenantFilter" class="form-control" value="<?= $tenantId ?>"
                           data-i18n-placeholder="filters.tenant_placeholder"
                           placeholder="<?= __t('filters.tenant_placeholder', 'Filter by tenant') ?>">
                </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label for="typeFilter" data-i18n="filters.product_type">Product Type</label>
                    <select id="typeFilter" class="form-control">
                        <option value="">All Types</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="brandFilter" data-i18n="filters.brand">Brand</label>
                    <select id="brandFilter" class="form-control">
                        <option value="">All Brands</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="statusFilter" data-i18n="filters.status">
                        <?= __t('filters.status', 'Status') ?>
                    </label>
                    <select id="statusFilter" class="form-control">
                        <option value="" data-i18n="filters.status_options.all">All Status</option>
                        <option value="1" data-i18n="filters.status_options.active">Active</option>
                        <option value="0" data-i18n="filters.status_options.inactive">Inactive</option>
                    </select>
                </div>

                <div class="filter-buttons">
                    <button id="btnApplyFilters" class="btn btn-secondary" data-i18n="filters.apply">
                        <?= __t('filters.apply', 'Apply') ?>
                    </button>
                    <button id="btnResetFilters" class="btn btn-outline" data-i18n="filters.reset">
                        <?= __t('filters.reset', 'Reset') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Count -->
    <div id="resultsCount" class="results-count" style="display:none;">
        <span>
            <i class="fas fa-box"></i> 
            <span id="resultsCountText"></span>
        </span>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <div id="tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="products.loading"><?= __t('products.loading', 'Loading...') ?></p>
            </div>

            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="productsTable">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id">ID</th>
                                <?php if (is_super_admin()): ?>
                                <th data-i18n="table.headers.tenant">Tenant</th>
                                <?php endif; ?>
                                <th data-i18n="table.headers.image">Image</th>
                                <th data-i18n="table.headers.name">Name</th>
                                <th data-i18n="table.headers.sku">SKU</th>
                                <th data-i18n="table.headers.type">Type</th>
                                <th data-i18n="table.headers.price">Price</th>
                                <th data-i18n="table.headers.stock">Stock</th>
                                <th data-i18n="table.headers.status">Status</th>
                                <th data-i18n="table.headers.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <span data-i18n="pagination.showing">Showing</span>
                        <span id="paginationInfo">0-0 of 0</span>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>

            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">📦</div>
                <h3 data-i18n="table.empty.title">No Products Found</h3>
                <p data-i18n="table.empty.message">Start by adding your first product</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="if(window.Products)window.Products.add()">
                    <i class="fas fa-plus"></i>
                    <span data-i18n="table.empty.add_first">Add First Product</span>
                </button>
                <?php endif; ?>
            </div>

            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="messages.error.load_failed">Error Loading Data</h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary" data-i18n="products.retry">Retry</button>
            </div>
        </div>
    </div>

    <!-- Media Studio Modal -->
    <div id="prodMediaStudioModal"
         class="prd-modal-backdrop"
         role="dialog"
         aria-modal="true"
         aria-labelledby="prodMediaStudioTitle"
         style="display:none">
        <div class="prd-modal-panel prd-modal-panel--wide prd-modal-panel--studio">
            <div class="prd-modal-header">
                <h3 id="prodMediaStudioTitle" data-i18n="media_studio.title"><?= __t('media_studio.title', 'Media Studio') ?></h3>
                <button type="button"
                        class="btn-close-modal icon-btn"
                        id="prodMediaStudioClose"
                        data-modal="prodMediaStudioModal"
                        aria-label="<?= __t('accessibility.close', 'Close') ?>">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="prd-modal-body prd-modal-body--studio">
                <iframe id="prodMediaStudioFrame" class="media-studio-iframe" src="/admin/fragments/media_studio.php?embedded=1&tenant_id=<?= $tenantId ?>&lang=<?= $lang ?>"></iframe>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- CSV Import Modal -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div id="csvImportModal"
         class="prd-modal-backdrop"
         role="dialog"
         aria-modal="true"
         aria-labelledby="csvImportTitle"
         style="display:none;">
        <div class="prd-modal-panel csv-modal-content">
            <div class="prd-modal-header">
                <h3 id="csvImportTitle">
                    <i class="fas fa-file-csv"></i>
                    <span data-i18n="csv.title"><?= __t('csv.title', 'Import Products via CSV') ?></span>
                </h3>
                <button type="button"
                        id="csvImportClose"
                        class="btn-close-modal icon-btn"
                        data-modal="csvImportModal"
                        aria-label="<?= __t('accessibility.close', 'Close') ?>">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="prd-modal-body">

            <!-- Instructions -->
            <div class="info-hint-box csv-instructions">
                <p>
                    <i class="fas fa-info-circle"></i>
                    <span data-i18n="csv.instructions"><?= __t('csv.instructions', 'Upload a CSV file to bulk-import products with English translations. Each row = one product. Max recommended: 1000 rows per file.') ?></span>
                </p>
            </div>

            <!-- Download Sample -->
            <div class="csv-download-section">
                <button type="button" id="btnDownloadSample" class="btn btn-outline btn-full-width">
                    <i class="fas fa-download"></i>
                    <span data-i18n="csv.download_sample"><?= __t('csv.download_sample', 'Download Sample CSV Template') ?></span>
                </button>
            </div>

            <!-- File Input -->
            <div class="form-group csv-file-group">
                <label data-i18n="csv.choose_file"><?= __t('csv.choose_file', 'Select CSV File') ?></label>
                <input type="file" id="csvFileInput" accept=".csv,text/csv" class="form-control">
            </div>

            <!-- Preview Info -->
            <div id="csvPreviewInfo" class="csv-preview-info" style="display:none;">
                <span id="csvRowCount"></span>
            </div>

            <!-- Progress -->
            <div id="csvProgressArea" class="csv-progress-area" style="display:none;">
                <div class="csv-progress-header">
                    <span id="csvProgressLabel" class="csv-progress-label" data-i18n="csv.importing"><?= __t('csv.importing', 'Importing…') ?></span>
                    <span id="csvProgressPct" class="csv-progress-pct">0%</span>
                </div>
                <div class="csv-progress-track">
                    <div id="csvProgressBar" class="csv-progress-bar"></div>
                </div>
                <div id="csvProgressLog" class="csv-progress-log"></div>
            </div>

            <!-- Result Summary -->
            <div id="csvResultSummary" class="csv-result-summary" style="display:none;"></div>

            <!-- Actions -->
            <div class="csv-actions">
                <button type="button" id="csvImportCancel" class="btn btn-outline" data-i18n="csv.cancel"><?= __t('csv.cancel', 'Cancel') ?></button>
                <button type="button" id="csvImportStart" class="btn btn-primary" disabled>
                    <i class="fas fa-upload"></i>
                    <span data-i18n="csv.import"><?= __t('csv.import', 'Start Import') ?></span>
                </button>
            </div>
            </div><!-- /.prd-modal-body -->
        </div><!-- /.prd-modal-panel -->
    </div><!-- /#csvImportModal -->

</div><!-- /.page-container -->

<script>
window.PRODUCTS_CONFIG = {
    apiBase:           <?= json_encode($apiBase,                            JSON_UNESCAPED_SLASHES) ?>,
    apiUrl:            <?= json_encode($apiBase . '/products',              JSON_UNESCAPED_SLASHES) ?>,
    categoriesApi:     <?= json_encode($apiBase . '/categories',            JSON_UNESCAPED_SLASHES) ?>,
    brandsApi:         <?= json_encode($apiBase . '/brands',                JSON_UNESCAPED_SLASHES) ?>,
    productTypesApi:   <?= json_encode($apiBase . '/product_types',         JSON_UNESCAPED_SLASHES) ?>,
    attributesApi:     <?= json_encode($apiBase . '/product_attributes',    JSON_UNESCAPED_SLASHES) ?>,
    attributeValuesApi:<?= json_encode($apiBase . '/product_attribute_values', JSON_UNESCAPED_SLASHES) ?>,
    currenciesApi:     <?= json_encode($apiBase . '/currencies',            JSON_UNESCAPED_SLASHES) ?>,
    languagesApi:      <?= json_encode($apiBase . '/languages',             JSON_UNESCAPED_SLASHES) ?>,
    imagesApi:         <?= json_encode($apiBase . '/images',                JSON_UNESCAPED_SLASHES) ?>,
    tenantsApi:        <?= json_encode($apiBase . '/tenants',               JSON_UNESCAPED_SLASHES) ?>,
    csrfToken:         <?= json_encode($csrf) ?>,
    lang:              <?= json_encode($_prdSafeLang) ?>,
    dir:               <?= json_encode($dir) ?>,
    tenantId:          <?= (int) $tenantId ?>,
    userId:            <?= (int) $userId ?>,
    strings:           <?= json_encode($_prdStrings, JSON_UNESCAPED_UNICODE) ?>,
    canCreate:         <?= json_encode($canCreate) ?>,
    canEdit:           <?= json_encode($canEdit) ?>,
    canDelete:         <?= json_encode($canDelete) ?>,
    isSuperAdmin:      <?= json_encode(is_super_admin()) ?>,
    permissions: {
        canCreate:     <?= json_encode($canCreate) ?>,
        canEdit:       <?= json_encode($canEdit) ?>,
        canDelete:     <?= json_encode($canDelete) ?>,
        canDuplicate:  <?= json_encode($canDuplicate) ?>,
        canViewAll:    <?= json_encode($canViewAll) ?>,
        canViewOwn:    <?= json_encode($canViewOwn) ?>,
        canViewTenant: <?= json_encode($canViewTenant) ?>,
        canEditAll:    <?= json_encode($canEditAll) ?>,
        canEditOwn:    <?= json_encode($canEditOwn) ?>,
        canDeleteAll:  <?= json_encode($canDeleteAll) ?>,
        canDeleteOwn:  <?= json_encode($canDeleteOwn) ?>,
        isSuperAdmin:  <?= json_encode(is_super_admin()) ?>
    },
    itemsPerPage: 25
};
</script>
<script src="/admin/assets/js/pages/products.js?v=<?= assetVer('/admin/assets/js/pages/products.js') ?>"></script>

<?php if (!$isFragment) require_once __DIR__ . '/../includes/footer.php'; ?>