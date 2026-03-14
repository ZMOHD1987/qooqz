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
$user = admin_user();
$lang = admin_lang();
$dir = admin_dir();
$csrf = admin_csrf();
$tenantId = admin_tenant_id();

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
// DB-DRIVEN CSS VARS HELPER (Products)
// All settings, colors, fonts, cards, buttons from database only.
// ════════════════════════════════════════════════════════════
if (!function_exists('renderFragmentThemeVars')) {
    function renderFragmentThemeVars(array $theme): void {
        echo ':root {' . PHP_EOL;
        foreach ($theme['color_settings'] ?? [] as $c) {
            if (empty($c['setting_key']) || !isset($c['color_value'])) continue;
            $k = htmlspecialchars($c['setting_key'], ENT_QUOTES);
            $h = htmlspecialchars(str_replace('_', '-', $c['setting_key']), ENT_QUOTES);
            $v = htmlspecialchars($c['color_value'], ENT_QUOTES);
            echo "    --{$k}: {$v};" . PHP_EOL;
            if ($h !== $k) echo "    --{$h}: {$v};" . PHP_EOL;
        }
        foreach ($theme['font_settings'] ?? [] as $f) {
            if (empty($f['setting_key'])) continue;
            $sk = htmlspecialchars($f['setting_key'], ENT_QUOTES);
            $sh = htmlspecialchars(str_replace('_', '-', $f['setting_key']), ENT_QUOTES);
            if (!empty($f['font_family'])) {
                $ff = htmlspecialchars($f['font_family'], ENT_QUOTES);
                echo "    --{$sk}-family: {$ff};" . PHP_EOL;
                if ($sh !== $sk) echo "    --{$sh}-family: {$ff};" . PHP_EOL;
            }
            if (!empty($f['font_size'])) {
                $fs = htmlspecialchars($f['font_size'], ENT_QUOTES);
                echo "    --{$sk}-size: {$fs};" . PHP_EOL;
                if ($sh !== $sk) echo "    --{$sh}-size: {$fs};" . PHP_EOL;
            }
            if (!empty($f['font_weight'])) {
                $fw = htmlspecialchars($f['font_weight'], ENT_QUOTES);
                echo "    --{$sk}-weight: {$fw};" . PHP_EOL;
                if ($sh !== $sk) echo "    --{$sh}-weight: {$fw};" . PHP_EOL;
            }
        }
        foreach ($theme['design_settings'] ?? [] as $d) {
            if (empty($d['setting_key']) || !isset($d['setting_value'])) continue;
            $dk = htmlspecialchars($d['setting_key'], ENT_QUOTES);
            $dh = htmlspecialchars(str_replace('_', '-', $d['setting_key']), ENT_QUOTES);
            $dv = htmlspecialchars($d['setting_value'], ENT_QUOTES);
            echo "    --{$dk}: {$dv};" . PHP_EOL;
            if ($dh !== $dk) echo "    --{$dh}: {$dv};" . PHP_EOL;
        }
        foreach ($theme['button_styles'] ?? [] as $b) {
            if (empty($b['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$b['slug']));
            if (!empty($b['background_color'])) echo "    --btn-{$slug}-bg: " . htmlspecialchars($b['background_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($b['text_color']))       echo "    --btn-{$slug}-color: " . htmlspecialchars($b['text_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($b['border_color']))     echo "    --btn-{$slug}-border: " . htmlspecialchars($b['border_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($b['border_radius']))    echo "    --btn-{$slug}-radius: " . htmlspecialchars((string)$b['border_radius'], ENT_QUOTES) . 'px;' . PHP_EOL;
        }
        foreach ($theme['card_styles'] ?? [] as $cs) {
            if (empty($cs['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$cs['slug']));
            if (!empty($cs['background_color'])) echo "    --card-{$slug}-bg: " . htmlspecialchars($cs['background_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($cs['border_color']))     echo "    --card-{$slug}-border: " . htmlspecialchars($cs['border_color'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($cs['border_radius']))    echo "    --card-{$slug}-radius: " . htmlspecialchars((string)$cs['border_radius'], ENT_QUOTES) . 'px;' . PHP_EOL;
            if (!empty($cs['shadow_style']))     echo "    --card-{$slug}-shadow: " . htmlspecialchars($cs['shadow_style'], ENT_QUOTES) . ';' . PHP_EOL;
            if (!empty($cs['padding']))          echo "    --card-{$slug}-padding: " . htmlspecialchars($cs['padding'], ENT_QUOTES) . ';' . PHP_EOL;
        }
        echo '}' . PHP_EOL;
    }
}

?>
<!-- DB-driven CSS vars (all settings, colors, fonts, cards, buttons from database) -->
<style id="db-theme-vars-products">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
<?php if (!empty($GLOBALS['ADMIN_UI']['theme']['generated_css'])): ?>
<?= $GLOBALS['ADMIN_UI']['theme']['generated_css'] ?>
<?php endif; ?>
</style>
<!-- Structural layout CSS (uses only var() for all visual properties) -->
<link rel="stylesheet" href="/admin/assets/css/pages/products.css?v=<?= time() ?>">

<!-- Page Meta -->
<meta data-page="products"
      data-assets-css="/admin/assets/css/pages/products.css"
      data-i18n-files="/languages/Product/<?= rawurlencode($lang) ?>.json">

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
                    <button type="button" class="tab-btn" data-tab="pricing">
                        <i class="fas fa-tag"></i>
                        <span data-i18n="tabs.pricing"><?= __t('tabs.pricing', 'Pricing') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="inventory">
                        <i class="fas fa-boxes"></i>
                        <span data-i18n="tabs.inventory"><?= __t('tabs.inventory', 'Inventory') ?></span>
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
                    <button type="button" class="tab-btn" data-tab="categories">
                        <i class="fas fa-folder-tree"></i>
                        <span data-i18n="tabs.categories"><?= __t('tabs.categories', 'Categories') ?></span>
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
                            <label for="prodMainCategory" data-i18n="form.fields.main_category.label">
                                <?= __t('form.fields.main_category.label', 'Main Category') ?>
                            </label>
                            <select id="prodMainCategory" class="form-control">
                                <option value=""><?= __t('form.fields.main_category.select', 'Select main category') ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="prodSubCategory" data-i18n="form.fields.sub_category.label">
                                <?= __t('form.fields.sub_category.label', 'Sub Category') ?>
                            </label>
                            <select id="prodSubCategory" class="form-control">
                                <option value=""><?= __t('form.fields.sub_category.select', 'Select sub category') ?></option>
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
                    <div class="english-content-section" style="margin-top:28px; padding-top:20px; border-top:2px solid var(--primary-color,#3b82f6);">
                        <h4 style="margin-bottom:16px; color:var(--text-primary,#fff); display:flex; align-items:center; gap:10px;">
                            <span style="background:var(--primary-color,#3b82f6); color:#fff; padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:700;">EN</span>
                            English Content <span style="color:var(--text-secondary,#94a3b8); font-size:0.8rem; font-weight:400; margin-left:6px;">(Default Language — required)</span>
                        </h4>

                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label for="enProdName" class="required">Product Name (English)</label>
                                <input type="text" id="enProdName" name="en_name" class="form-control" required
                                       placeholder="Enter product name in English">
                                <div class="invalid-feedback">English product name is required</div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label for="enProdShortDesc">Short Description (English)</label>
                                <textarea id="enProdShortDesc" name="en_short_description" class="form-control" rows="2"
                                          placeholder="Brief product summary in English"></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label for="enProdDesc">Full Description (English)</label>
                                <textarea id="enProdDesc" name="en_description" class="form-control" rows="4"
                                          placeholder="Detailed product description in English"></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label for="enProdSpecs">Specifications (English)</label>
                                <textarea id="enProdSpecs" name="en_specifications" class="form-control" rows="3"
                                          placeholder="Technical specifications in English"></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label for="enMetaTitle">Meta Title (English)</label>
                                <input type="text" id="enMetaTitle" name="en_meta_title" class="form-control"
                                       placeholder="SEO meta title in English">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label for="enMetaDescription">Meta Description (English)</label>
                                <textarea id="enMetaDescription" name="en_meta_description" class="form-control" rows="2"
                                          placeholder="SEO meta description in English"></textarea>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex:1;">
                                <label for="enMetaKeywords">Meta Keywords (English)</label>
                                <input type="text" id="enMetaKeywords" name="en_meta_keywords" class="form-control"
                                       placeholder="keyword1, keyword2, keyword3">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Pricing -->
                <div class="tab-content" id="tab-pricing" style="display:none">
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
                </div>

                <!-- Tab: Inventory -->
                <div class="tab-content" id="tab-inventory" style="display:none">
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
                                <option value="in_stock" data-i18n="form.fields.stock_status.in_stock">In Stock</option>
                                <option value="out_of_stock" data-i18n="form.fields.stock_status.out_of_stock">Out of Stock</option>
                                <option value="on_backorder" data-i18n="form.fields.stock_status.on_backorder">On Backorder</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="prodManageStock" data-i18n="form.fields.manage_stock.label">
                                <?= __t('form.fields.manage_stock.label', 'Manage Stock') ?>
                            </label>
                            <select id="prodManageStock" name="manage_stock" class="form-control">
                                <option value="1" data-i18n="form.fields.manage_stock.yes">Yes</option>
                                <option value="0" data-i18n="form.fields.manage_stock.no">No</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="prodAllowBackorder" data-i18n="form.fields.allow_backorder.label">
                                <?= __t('form.fields.allow_backorder.label', 'Allow Backorder') ?>
                            </label>
                            <select id="prodAllowBackorder" name="allow_backorder" class="form-control">
                                <option value="0" data-i18n="form.fields.allow_backorder.no">No</option>
                                <option value="1" data-i18n="form.fields.allow_backorder.yes">Yes</option>
                            </select>
                        </div>
                    </div>

                    <!-- Physical Attributes (Weight/Dimensions) -->
                    <h4 style="margin-top: 30px; margin-bottom: 15px; color:var(--text-primary,#fff);" data-i18n="form.sections.physical">
                        <?= __t('form.sections.physical', 'Physical Attributes') ?>
                    </h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prodWeight" data-i18n="form.fields.weight.label">Weight</label>
                            <input type="number" id="prodWeight" name="weight" class="form-control" step="0.001" min="0">
                        </div>

                        <div class="form-group">
                            <label for="prodWeightUnit" data-i18n="form.fields.weight_unit.label">Weight Unit</label>
                            <select id="prodWeightUnit" name="weight_unit" class="form-control">
                                <option value="kg">kg</option>
                                <option value="g">g</option>
                                <option value="lb">lb</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="prodLength" data-i18n="form.fields.length.label">Length</label>
                            <input type="number" id="prodLength" name="length" class="form-control" step="0.01" min="0">
                        </div>

                        <div class="form-group">
                            <label for="prodWidth" data-i18n="form.fields.width.label">Width</label>
                            <input type="number" id="prodWidth" name="width" class="form-control" step="0.01" min="0">
                        </div>

                        <div class="form-group">
                            <label for="prodHeight" data-i18n="form.fields.height.label">Height</label>
                            <input type="number" id="prodHeight" name="height" class="form-control" step="0.01" min="0">
                        </div>

                        <div class="form-group">
                            <label for="prodDimensionUnit" data-i18n="form.fields.dimension_unit.label">Dimension Unit</label>
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
                    <div style="display:flex; gap:10px; margin-bottom:15px;">
                        <select id="attrSelect" class="form-control" style="flex:1;"></select>
                        <button type="button" id="btnAddAttribute" class="btn btn-primary" data-i18n="form.buttons.add_attribute">
                            <?= __t('form.buttons.add_attribute', 'Add Attribute') ?>
                        </button>
                    </div>
                    <div id="prodAttributesList"></div>
                </div>

                <!-- Tab: Variants -->
                <div class="tab-content" id="tab-variants" style="display:none">
                    <div style="margin-bottom:15px;">
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
                            <button type="button" id="prodSelectImageBtn" class="btn btn-secondary" style="width:100%; margin-bottom:15px;" data-i18n="common.select_image">
                                <?= __t('common.select_image', 'Select Images from Studio') ?>
                            </button>
                            <div id="prodImagesPreview" class="images-grid"></div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Categories -->
                <div class="tab-content" id="tab-categories" style="display:none">
                    <div class="form-group">
                        <label data-i18n="form.fields.categories.label">
                            <?= __t('form.fields.categories.label', 'Product Categories') ?>
                        </label>
                        <div id="prodCategoriesTree" class="categories-tree"></div>
                    </div>
                </div>

                <!-- Tab: Translations -->
                <div class="tab-content" id="tab-translations" style="display:none">
                    <div class="translations-section">
                        <h4 style="margin-bottom:12px; color:var(--text-primary,#fff); border-bottom:1px solid var(--border-color,#263044); padding-bottom:8px;">
                            <i class="fas fa-language"></i> Translations
                        </h4>
                        <div style="background:rgba(59,130,246,0.08); border:1px solid rgba(59,130,246,0.25); border-radius:8px; padding:12px; margin-bottom:16px; font-size:0.85rem; color:var(--text-secondary,#94a3b8);">
                            <i class="fas fa-info-circle" style="color:#3b82f6;"></i>
                            <strong style="color:var(--text-primary,#fff);">English</strong> translation fields are in the <strong style="color:var(--text-primary,#fff);">General tab</strong>. Use this tab to add translations for other languages (Arabic, French, etc.).
                        </div>
                        <div id="prodTranslations" class="translation-panels"></div>
                        <div class="form-group" style="margin-top:12px;">
                            <label for="prodLangSelect" data-i18n="form.translations.select_lang">Select Language</label>
                            <div style="display:flex; gap:8px; align-items:flex-end;">
                                <select id="prodLangSelect" class="form-control" style="flex:1;">
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

                <div class="filter-actions">
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
    <div id="resultsCount" class="results-count" style="padding:12px 16px; margin-bottom:12px; background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:8px; display:none;">
        <span style="color:var(--text-secondary,#94a3b8); font-size:0.9rem;">
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
    <div id="prodMediaStudioModal" class="modal" style="display:none">
        <div class="modal-content">
            <span class="close" id="prodMediaStudioClose">&times;</span>
            <iframe id="prodMediaStudioFrame" src="/admin/fragments/media_studio.php?embedded=1&tenant_id=<?= $tenantId ?>&lang=<?= $lang ?>" style="width:100%; height:75vh; min-height:400px; border:none; display:block;"></iframe>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- CSV Import Modal -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div id="csvImportModal" class="modal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.7); align-items:center; justify-content:center;">
        <div class="modal-content" style="background:var(--card-bg,#0d1b2e); border:1px solid var(--border-color,#263044); border-radius:12px; padding:28px; width:min(640px,95vw); max-height:85vh; overflow-y:auto; position:relative;">
            <!-- Header -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="color:var(--text-primary,#fff); margin:0;">
                    <i class="fas fa-file-csv" style="color:var(--primary-color,#3b82f6);"></i>
                    <span data-i18n="csv.title"><?= __t('csv.title', 'Import Products via CSV') ?></span>
                </h3>
                <button type="button" id="csvImportClose" style="background:none; border:none; color:var(--text-secondary,#94a3b8); font-size:1.4rem; cursor:pointer; padding:4px;">&times;</button>
            </div>

            <!-- Instructions -->
            <div style="background:rgba(59,130,246,0.08); border:1px solid rgba(59,130,246,0.25); border-radius:8px; padding:14px; margin-bottom:18px;">
                <p style="color:var(--text-secondary,#94a3b8); margin:0; font-size:0.88rem; line-height:1.6;">
                    <i class="fas fa-info-circle" style="color:#3b82f6;"></i>
                    <span data-i18n="csv.instructions"><?= __t('csv.instructions', 'Upload a CSV file to bulk-import products with English translations. Each row = one product. Max recommended: 1000 rows per file.') ?></span>
                </p>
            </div>

            <!-- Download Sample -->
            <div style="margin-bottom:18px;">
                <button type="button" id="btnDownloadSample" class="btn btn-outline" style="width:100%;">
                    <i class="fas fa-download"></i>
                    <span data-i18n="csv.download_sample"><?= __t('csv.download_sample', 'Download Sample CSV Template') ?></span>
                </button>
            </div>

            <!-- File Input -->
            <div class="form-group" style="margin-bottom:18px;">
                <label data-i18n="csv.choose_file" style="color:var(--text-primary,#fff); margin-bottom:8px; display:block;"><?= __t('csv.choose_file', 'Select CSV File') ?></label>
                <input type="file" id="csvFileInput" accept=".csv,text/csv" class="form-control"
                       style="color:var(--text-secondary,#94a3b8); padding:10px;">
            </div>

            <!-- Preview Info -->
            <div id="csvPreviewInfo" style="display:none; margin-bottom:16px; background:rgba(0,0,0,0.2); border-radius:8px; padding:12px;">
                <span id="csvRowCount" style="color:var(--text-primary,#fff); font-size:0.9rem;"></span>
            </div>

            <!-- Progress -->
            <div id="csvProgressArea" style="display:none; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <span id="csvProgressLabel" data-i18n="csv.importing" style="color:var(--text-secondary,#94a3b8); font-size:0.85rem;"><?= __t('csv.importing', 'Importing…') ?></span>
                    <span id="csvProgressPct" style="color:var(--text-primary,#fff); font-size:0.85rem; font-weight:600;">0%</span>
                </div>
                <div style="background:rgba(255,255,255,0.1); border-radius:20px; height:8px; overflow:hidden;">
                    <div id="csvProgressBar" style="background:var(--primary-color,#3b82f6); height:100%; width:0%; transition:width 0.3s ease; border-radius:20px;"></div>
                </div>
                <div id="csvProgressLog" style="margin-top:10px; max-height:150px; overflow-y:auto; font-size:0.78rem; color:var(--text-secondary,#94a3b8); font-family:monospace;"></div>
            </div>

            <!-- Result Summary -->
            <div id="csvResultSummary" style="display:none; margin-bottom:16px; padding:12px; border-radius:8px;"></div>

            <!-- Actions -->
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" id="csvImportCancel" class="btn btn-outline" data-i18n="csv.cancel"><?= __t('csv.cancel', 'Cancel') ?></button>
                <button type="button" id="csvImportStart" class="btn btn-primary" disabled>
                    <i class="fas fa-upload"></i>
                    <span data-i18n="csv.import"><?= __t('csv.import', 'Start Import') ?></span>
                </button>
            </div>
        </div>
    </div>

</div>

<!-- Expose client-side globals for the module -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE = window.APP_CONFIG.API_BASE || '<?= $apiBase ?>';
window.APP_CONFIG.TENANT_ID = window.APP_CONFIG.TENANT_ID || <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN = window.APP_CONFIG.CSRF_TOKEN || '<?= addslashes($csrf) ?>';
window.APP_CONFIG.USER_ID = window.APP_CONFIG.USER_ID || <?= admin_user_id() ?>;

window.USER_LANGUAGE = window.USER_LANGUAGE || '<?= addslashes($lang) ?>';
window.USER_DIRECTION = window.USER_DIRECTION || '<?= addslashes($dir) ?>';
window.CSRF_TOKEN = window.CSRF_TOKEN || '<?= addslashes($csrf) ?>';

// Inject ADMIN_UI with DB theme data (colors, fonts, cards, buttons, design settings)
if (!window.ADMIN_UI) {
    window.ADMIN_UI = <?= json_encode($GLOBALS['ADMIN_UI'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
}

// Page permissions available to JS
window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate' => $canCreate,
    'canEdit' => $canEdit,
    'canDelete' => $canDelete,
    'canDuplicate' => $canDuplicate,
    'canViewAll' => $canViewAll,
    'canViewOwn' => $canViewOwn,
    'canViewTenant' => $canViewTenant,
    'canEditAll' => $canEditAll,
    'canEditOwn' => $canEditOwn,
    'canDeleteAll' => $canDeleteAll,
    'canDeleteOwn' => $canDeleteOwn,
    'isSuperAdmin' => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<script type="text/javascript">
window.PRODUCTS_CONFIG = {
    apiUrl: '<?= $apiBase ?>/products',
    categoriesApi: '<?= $apiBase ?>/categories',
    brandsApi: '<?= $apiBase ?>/brands',
    productTypesApi: '<?= $apiBase ?>/product_types',
    attributesApi: '<?= $apiBase ?>/product_attributes',
    attributeValuesApi: '<?= $apiBase ?>/product_attribute_values',
    currenciesApi: '<?= $apiBase ?>/currencies',
    languagesApi: '<?= $apiBase ?>/languages',
    imagesApi: '<?= $apiBase ?>/images',
    tenantsApi: '<?= $apiBase ?>/tenants',
    csrfToken: '<?= addslashes($csrf) ?>',
    lang: '<?= addslashes($lang) ?>',
    itemsPerPage: 25
};
</script>

<!-- Translation loader (runs early) -->
<script type="text/javascript">
(function(){
    async function applyTranslations() {
        try {
            const lang = window.USER_LANGUAGE || '<?= $lang ?>';
            const url = `/languages/Product/${encodeURIComponent(lang)}.json`;
            console.log('[Products] Loading translations from', url);
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Translation fetch failed: ' + res.status);
            const data = await res.json();
            // Support both flat and nested (strings key) format
            const translations = data.strings || data;
            window.PRODUCTS_TRANSLATIONS = translations;
            // apply translations to elements with data-i18n
            const container = document.getElementById('productsPageContainer');
            if (!container) return;
            container.querySelectorAll('[data-i18n]').forEach(el => {
                const key = el.getAttribute('data-i18n');
                const txt = key.split('.').reduce((o,k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) {
                    if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) {
                        el.placeholder = txt;
                    } else {
                        el.textContent = txt;
                    }
                }
            });
            // placeholders
            container.querySelectorAll('[data-i18n-placeholder]').forEach(el=>{
                const key = el.getAttribute('data-i18n-placeholder');
                const txt = key.split('.').reduce((o,k) => (o && o[k] !== undefined) ? o[k] : null, translations);
                if (txt !== null && txt !== undefined) el.placeholder = txt;
            });
            console.log('[Products] Translations applied');
        } catch (err) {
            console.warn('[Products] Translation load/apply failed:', err);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyTranslations);
    } else {
        setTimeout(applyTranslations, 50);
    }
})();
</script>

<!-- Page Permissions JSON for scripts that prefer it in DOM -->
<script id="pagePermissions" type="application/json">
<?= json_encode([
    'canCreate' => $canCreate,
    'canEdit' => $canEdit,
    'canDelete' => $canDelete,
    'canDuplicate' => $canDuplicate,
    'canViewAll' => $canViewAll,
    'canViewOwn' => $canViewOwn,
    'canViewTenant' => $canViewTenant,
    'canEditAll' => $canEditAll,
    'canEditOwn' => $canEditOwn,
    'canDeleteAll' => $canDeleteAll,
    'canDeleteOwn' => $canDeleteOwn,
    'isSuperAdmin' => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>
</script>

<script id="PRODUCTS_INITIAL_PAYLOAD" type="application/json">
<?= json_encode(['items' => [], 'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0]]) ?>
</script>

<!-- Load AdminFramework + Page module when embedded; otherwise load normally -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/products.js?v=<?= time() ?>"></script>

<script>
(function(){
    console.log('[Products] Embedded mode - waiting for module...');
    var attempts = 0, maxAttempts = 50;
    var interval = setInterval(function(){
        attempts++;
        if (window.Products && typeof window.Products.init === 'function') {
            clearInterval(interval);
            console.log('[Products] Module ready - initializing (attempt ' + attempts + ')...');
            try {
                var maybePromise = window.Products.init();
                if (maybePromise && typeof maybePromise.then === 'function') {
                    maybePromise.then(function(){
                        console.log('[Products] ✓ Initialized successfully');
                    }).catch(function(e){
                        console.error('[Products] Init failed:', e);
                    });
                }
            } catch (e) {
                console.error('[Products] Init threw:', e);
            }
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[Products] Timeout waiting for module after ' + (maxAttempts * 100) + 'ms');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/pages/products.js?v=<?= time() ?>"></script>
<script>
// Standalone mode init
(function(){
    function tryInit() {
        if (window.Products && typeof window.Products.init === 'function') {
            window.Products.init().catch(function(e){ console.error('[Products] Init failed', e); });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
})();
</script>
<?php endif; ?>

<?php
// Load footer if standalone
if (!$isFragment) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>