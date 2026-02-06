<?php
declare(strict_types=1);

// Bootstrap Admin UI
$bootstrap = __DIR__ . '/../../api/bootstrap_admin_ui.php';
if (is_readable($bootstrap)) {
    try { require_once $bootstrap; } catch (Throwable $e) {}
}

// Fallback if not loaded
if (!isset($GLOBALS['ADMIN_UI'])) {
    $GLOBALS['ADMIN_UI'] = [
        'user' => [
            'id' => 1,
            'role_id' => 1,
            'permissions' => ['manage_products', 'edit_products', 'delete_products'],
            'roles' => ['super_admin']
        ],
        'lang' => 'ar',
        'direction' => 'rtl',
        'strings' => [],
        'theme' => [
            'colors_map' => [
                'primary' => '#FF0000',
                'secondary' => '#10B981',
                'text-primary' => '#000000',
                'text-secondary' => '#6b7280',
                'border' => '#e2e8f0',
                'error' => '#dc2626',
                'success' => '#10b981',
                'warning' => '#f59e0b'
            ],
            'buttons_map' => [
                'primary' => ['background_color' => '#333333'],
                'danger' => ['background_color' => '#ef4444']
            ],
            'designs' => ['products_per_page' => 25]
        ]
    ];
}

$ADMIN_UI_PAYLOAD = $ADMIN_UI_PAYLOAD ?? ($GLOBALS['ADMIN_UI'] ?? []);
$user = $ADMIN_UI_PAYLOAD['user'] ?? [];
$lang = $ADMIN_UI_PAYLOAD['lang'] ?? 'en';
$direction = $ADMIN_UI_PAYLOAD['direction'] ?? 'ltr';
$strings = $ADMIN_UI_PAYLOAD['strings'] ?? [];
$theme = $ADMIN_UI_PAYLOAD['theme'] ?? [];

// Permissions
$canManageProducts = in_array('manage_products', $user['permissions'] ?? []);
$canEditProducts = in_array('edit_products', $user['permissions'] ?? []);
$canDeleteProducts = in_array('delete_products', $user['permissions'] ?? []);
$isAdmin = ($user['role_id'] ?? 0) === 1;

// API Path
$apiPath = '/api/product';
$categoriesApi = '/api/categories';
$productMetaApi = '/api/product_meta';
$mediaStudioApi = '/api/media';

// Load Product Languages
$langFile = __DIR__ . '/../../languages/Product/' . $lang . '.json';
$productStrings = is_readable($langFile) ? json_decode(file_get_contents($langFile), true) : [];
$allStrings = array_merge($strings, $productStrings);

// Helper
function gs(string $key, array $allStrings): string {
    $keys = explode('.', $key);
    $current = $allStrings;
    foreach ($keys as $k) {
        if (!isset($current[$k])) return $key;
        $current = $current[$k];
    }
    return $current;
}

// CSRF
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);

// Theme Colors
$colors = $theme['colors_map'] ?? [];
$primaryColor = $colors['primary'] ?? '#3b82f6';
$secondaryColor = $colors['secondary'] ?? '#f8fafc';
$textPrimary = $colors['text-primary'] ?? '#000000';
$textSecondary = $colors['text-secondary'] ?? '#6b7280';
$borderColor = $colors['border'] ?? '#e2e8f0';
$errorColor = $colors['error'] ?? '#dc2626';
$successColor = $colors['success'] ?? '#10b981';
$warningColor = $colors['warning'] ?? '#f59e0b';

// Buttons
$buttons = $theme['buttons_map'] ?? [];

// Get button style
function getButtonStyle($type, $buttons): string {
    $btn = $buttons[$type] ?? [];
    $style = '';
    if (isset($btn['background_color'])) {
        $style .= 'background-color: ' . $btn['background_color'] . ';';
    }
    if (isset($btn['text_color'])) {
        $style .= 'color: ' . $btn['text_color'] . ';';
    }
    if (isset($btn['border_radius'])) {
        $style .= 'border-radius: ' . $btn['border_radius'] . 'px;';
    }
    if (isset($btn['padding'])) {
        $style .= 'padding: ' . $btn['padding'] . ';';
    }
    if (isset($btn['font_size'])) {
        $style .= 'font-size: ' . $btn['font_size'] . ';';
    }
    if (isset($btn['font_weight'])) {
        $style .= 'font-weight: ' . $btn['font_weight'] . ';';
    }
    if (isset($btn['border_color'])) {
        $style .= 'border: 1px solid ' . $btn['border_color'] . ';';
    } else {
        $style .= 'border: none;';
    }
    return $style;
}
?>

<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= htmlspecialchars($direction) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(gs('page_title', $allStrings)) ?> - <?= htmlspecialchars(gs('admin_panel', $allStrings)) ?></title>
    <link rel="stylesheet" href="/admin/assets/css/admin-theme.css">
    <style>
        :root {
            --primary: <?= $primaryColor ?>;
            --secondary: <?= $secondaryColor ?>;
            --text-primary: <?= $textPrimary ?>;
            --text-secondary: <?= $textSecondary ?>;
            --border: <?= $borderColor ?>;
            --error: <?= $errorColor ?>;
            --success: <?= $successColor ?>;
            --warning: <?= $warningColor ?>;
        }
        
        .admin-page {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
            background: var(--secondary);
            min-height: 100vh;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }
        
        .page-header h2 {
            color: var(--text-primary);
            margin: 0;
            font-size: 24px;
        }
        
        .status-notice {
            min-height: 22px;
            margin-bottom: 15px;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .status-notice.success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .status-notice.error {
            background-color: rgba(220, 38, 38, 0.1);
            color: var(--error);
            border: 1px solid rgba(220, 38, 38, 0.3);
        }
        
        .tools-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .search-input {
            padding: 10px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            width: 300px;
            background: white;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .btn.primary {
            <?= getButtonStyle('primary', $buttons) ?>
        }
        
        .btn.danger {
            <?= getButtonStyle('danger', $buttons) ?>
        }
        
        .btn.outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-primary);
        }
        
        .btn.small {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }
        
        .data-table th {
            background: #f8fafc;
            color: var(--text-primary);
            font-weight: 600;
            text-align: left;
            padding: 16px;
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }
        
        .data-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
        }
        
        .data-table tr:hover td {
            background: #f8fafc;
        }
        
        .form-section {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
            color: var(--text-primary);
            background: white;
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        
        .media-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .media-item {
            width: 80px;
            height: 80px;
            border-radius: 6px;
            overflow: hidden;
            position: relative;
            border: 1px solid var(--border);
        }
        
        .media-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .tr-lang-panel {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            background: #f8fafc;
        }
        
        .tr-lang-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        
        .variant-row {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 10px;
            background: #f8fafc;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        
        .attr-item {
            display: flex;
            gap: 10px;
            align-items: center;
            padding: 10px;
            background: #f8fafc;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        
        .category-tree {
            max-height: 300px;
            overflow-y: auto;
            padding: 15px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: white;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        .loading::after {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--border);
            border-top: 2px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .search-input {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div id="adminProducts" class="admin-page">
    <div class="page-header">
        <h2><?= htmlspecialchars(gs('page_title', $allStrings)) ?></h2>
        <?php if ($canManageProducts || $isAdmin): ?>
        <button id="productNewBtn" class="btn primary">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 0a1 1 0 0 1 1 1v6h6a1 1 0 1 1 0 2H9v6a1 1 0 1 1-2 0V9H1a1 1 0 0 1 0-2h6V1a1 1 0 0 1 1-1z"/>
            </svg>
            <?= htmlspecialchars(gs('create', $allStrings)) ?>
        </button>
        <?php endif; ?>
    </div>

    <div id="productsNotice" class="status-notice"></div>
    
    <div class="tools-bar">
        <input id="productSearch" type="search" 
               class="search-input"
               placeholder="<?= htmlspecialchars(gs('search_placeholder', $allStrings)) ?>">
        
        <button id="productRefresh" class="btn outline">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .908-.417A6 6 0 1 1 8 2v1z"/>
                <path d="M8 4.466V.534a.25.25 0 0 1 .41-.192l2.36 1.966c.12.1.12.284 0 .384L8.41 4.658A.25.25 0 0 1 8 4.466z"/>
            </svg>
            <?= htmlspecialchars(gs('refresh', $allStrings)) ?>
        </button>
        
        <?php if ($canManageProducts || $isAdmin): ?>
        <button id="productNewBtn2" class="btn primary">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                <path d="M8 0a1 1 0 0 1 1 1v6h6a1 1 0 1 1 0 2H9v6a1 1 0 1 1-2 0V9H1a1 1 0 0 1 0-2h6V1a1 1 0 0 1 1-1z"/>
            </svg>
            <?= htmlspecialchars(gs('create', $allStrings)) ?>
        </button>
        <?php endif; ?>
        
        <div style="margin-left: auto; display: flex; align-items: center; gap: 10px;">
            <span style="color: var(--text-secondary); font-size: 14px;">
                <?= htmlspecialchars(gs('total', $allStrings)) ?>:
            </span>
            <span id="productsCount" style="font-weight: 600; color: var(--primary);">0</span>
        </div>
    </div>
    
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 70px"><?= htmlspecialchars(gs('table.id', $allStrings)) ?></th>
                    <th><?= htmlspecialchars(gs('general.name', $allStrings)) ?></th>
                    <th style="width: 160px"><?= htmlspecialchars(gs('general.sku', $allStrings)) ?></th>
                    <th style="width: 120px"><?= htmlspecialchars(gs('general.type', $allStrings)) ?></th>
                    <th style="width: 100px; text-align: center"><?= htmlspecialchars(gs('inventory.stock_quantity', $allStrings)) ?></th>
                    <th style="width: 100px; text-align: center"><?= htmlspecialchars(gs('products.active', $allStrings)) ?></th>
                    <th style="width: 180px"><?= htmlspecialchars(gs('table.actions', $allStrings)) ?></th>
                </tr>
            </thead>
            <tbody id="productsTbody">
                <tr>
                    <td colspan="7" class="loading">
                        <?= htmlspecialchars(gs('loading', $allStrings)) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Product Form Section (Hidden by default) -->
    <div id="productFormWrap" class="form-section" style="display: none;">
        <div id="productErrors" style="display: none; color: var(--error); margin-bottom: 20px; padding: 15px; background: rgba(220, 38, 38, 0.1); border-radius: 6px;"></div>
        
        <form id="productForm" autocomplete="off" enctype="multipart/form-data">
            <input type="hidden" id="product_id" name="id" value="0">
            <input type="hidden" id="product_translations" name="translations" value="">
            <input type="hidden" id="product_attributes" name="attributes" value="">
            <input type="hidden" id="product_variants" name="variants" value="">
            <input type="hidden" id="product_categories_json" name="categories" value="">
            <input type="hidden" id="product_action" name="action" value="save">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            
            <div class="form-grid">
                <!-- Left Column -->
                <div>
                    <!-- General Information -->
                    <div class="form-group">
                        <h3 style="margin-bottom: 20px; color: var(--text-primary);"><?= htmlspecialchars(gs('general.general', $allStrings)) ?></h3>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('general.default_name', $allStrings)) ?></label>
                                <input id="product_name" name="name" type="text" class="form-input" required>
                            </div>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('general.sku', $allStrings)) ?></label>
                                <input id="product_sku" name="sku" type="text" class="form-input">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('general.slug', $allStrings)) ?></label>
                                <input id="product_slug" name="slug" type="text" class="form-input">
                            </div>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('general.barcode', $allStrings)) ?></label>
                                <input id="product_barcode" name="barcode" type="text" class="form-input">
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('general.type', $allStrings)) ?></label>
                                <select id="product_type" name="product_type" class="form-select">
                                    <option value="simple"><?= htmlspecialchars(gs('general.simple', $allStrings)) ?></option>
                                    <option value="variable"><?= htmlspecialchars(gs('general.variable', $allStrings)) ?></option>
                                    <option value="digital"><?= htmlspecialchars(gs('general.digital', $allStrings)) ?></option>
                                    <option value="bundle"><?= htmlspecialchars(gs('general.bundle', $allStrings)) ?></option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('general.brand', $allStrings)) ?></label>
                                <select id="product_brand_id" name="brand_id" class="form-select"></select>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('general.manufacturer', $allStrings)) ?></label>
                                <select id="product_manufacturer_id" name="manufacturer_id" class="form-select"></select>
                            </div>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('general.published_at', $allStrings)) ?></label>
                                <input id="product_published_at" name="published_at" type="datetime-local" class="form-input">
                            </div>
                        </div>
                        
                        <div>
                            <label class="form-label"><?= htmlspecialchars(gs('general.short_description', $allStrings)) ?></label>
                            <textarea id="product_description" name="description" class="form-textarea" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <!-- Pricing -->
                    <div class="form-group" style="margin-top: 30px;">
                        <h4 style="margin-bottom: 15px; color: var(--text-primary);"><?= htmlspecialchars(gs('pricing.pricing', $allStrings)) ?></h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('pricing.price', $allStrings)) ?></label>
                                <input id="product_price" name="price" type="text" class="form-input">
                            </div>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('pricing.compare_at_price', $allStrings)) ?></label>
                                <input id="product_compare_at_price" name="compare_at_price" type="text" class="form-input">
                            </div>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('pricing.cost_price', $allStrings)) ?></label>
                                <input id="product_cost_price" name="cost_price" type="text" class="form-input">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Inventory -->
                    <div class="form-group" style="margin-top: 30px;">
                        <h4 style="margin-bottom: 15px; color: var(--text-primary);"><?= htmlspecialchars(gs('inventory.inventory', $allStrings)) ?></h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('inventory.stock_quantity', $allStrings)) ?></label>
                                <input id="product_stock_quantity" name="stock_quantity" type="number" class="form-input" value="0">
                            </div>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('inventory.low_stock_threshold', $allStrings)) ?></label>
                                <input id="product_low_stock_threshold" name="low_stock_threshold" type="number" class="form-input" value="5">
                            </div>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('inventory.stock_status', $allStrings)) ?></label>
                                <select id="product_stock_status" name="stock_status" class="form-select">
                                    <option value="in_stock"><?= htmlspecialchars(gs('inventory.in_stock', $allStrings)) ?></option>
                                    <option value="out_of_stock"><?= htmlspecialchars(gs('inventory.out_of_stock', $allStrings)) ?></option>
                                    <option value="on_backorder"><?= htmlspecialchars(gs('inventory.on_backorder', $allStrings)) ?></option>
                                </select>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('inventory.manage_stock', $allStrings)) ?></label>
                                <select id="product_manage_stock" name="manage_stock" class="form-select">
                                    <option value="1"><?= htmlspecialchars(gs('general.yes', $allStrings)) ?></option>
                                    <option value="0"><?= htmlspecialchars(gs('general.no', $allStrings)) ?></option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('inventory.allow_backorder', $allStrings)) ?></label>
                                <select id="product_allow_backorder" name="allow_backorder" class="form-select">
                                    <option value="0"><?= htmlspecialchars(gs('general.no', $allStrings)) ?></option>
                                    <option value="1"><?= htmlspecialchars(gs('general.yes', $allStrings)) ?></option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('pricing.tax_rate', $allStrings)) ?></label>
                                <input id="product_tax_rate" name="tax_rate" type="text" class="form-input" value="15.00">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dimensions -->
                    <div class="form-group" style="margin-top: 30px;">
                        <h4 style="margin-bottom: 15px; color: var(--text-primary);"><?= htmlspecialchars(gs('dimensions.dimensions', $allStrings)) ?></h4>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('dimensions.weight', $allStrings)) ?> (kg)</label>
                                <input id="product_weight" name="weight" type="text" class="form-input">
                            </div>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('dimensions.length', $allStrings)) ?> (cm)</label>
                                <input id="product_length" name="length" type="text" class="form-input">
                            </div>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(gs('dimensions.width', $allStrings)) ?> (cm)</label>
                                <input id="product_width" name="width" type="text" class="form-input">
                            </div>
                        </div>
                        
                        <div>
                            <label class="form-label"><?= htmlspecialchars(gs('dimensions.height', $allStrings)) ?> (cm)</label>
                            <input id="product_height" name="height" type="text" class="form-input">
                        </div>
                    </div>
                    
                    <!-- Variants (Hidden by default) -->
                    <div id="variantsSection" class="form-group" style="margin-top: 30px; display: none;">
                        <h4 style="margin-bottom: 15px; color: var(--text-primary);"><?= htmlspecialchars(gs('variants.variants', $allStrings)) ?></h4>
                        
                        <div style="margin-bottom: 15px; padding: 15px; background: #f0f9ff; border-radius: 6px; border: 1px solid #bae6fd;">
                            <small style="color: #0369a1;"><?= htmlspecialchars(gs('variants.generate_from_attributes', $allStrings)) ?></small>
                            <div style="margin-top: 10px;">
                                <button id="generateVariantsBtn" type="button" class="btn small outline">
                                    <?= htmlspecialchars(gs('variants.generate_variants', $allStrings)) ?>
                                </button>
                            </div>
                        </div>
                        
                        <div id="product_variants_list"></div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- Media -->
                    <div class="form-group">
                        <h4 style="margin-bottom: 15px; color: var(--text-primary);"><?= htmlspecialchars(gs('media.images', $allStrings)) ?></h4>
                        
                        <input id="product_images_files" type="file" name="images[]" multiple accept="image/*" class="form-input" style="margin-bottom: 10px;">
                        
                        <button id="mediaStudioBtn" type="button" class="btn outline" style="width: 100%; margin-bottom: 15px;">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="margin-right: 8px;">
                                <path d="M4.502 9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/>
                                <path d="M14.002 13a2 2 0 0 1-2 2h-10a2 2 0 0 1-2-2V5A2 2 0 0 1 2 3a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v8a2 2 0 0 1-1.998 2zM14 2H4a1 1 0 0 0-1 1h9.002a2 2 0 0 1 2 2v7A1 1 0 0 0 15 11V3a1 1 0 0 0-1-1zM2.002 4a1 1 0 0 0-1 1v8l2.646-2.354a.5.5 0 0 1 .63-.062l2.66 1.773 3.71-3.71a.5.5 0 0 1 .577-.094l1.777 1.947V5a1 1 0 0 0-1-1h-10z"/>
                            </svg>
                            <?= htmlspecialchars(gs('media.select_from_studio', $allStrings)) ?>
                        </button>
                        
                        <div id="product_images_preview" class="media-preview"></div>
                    </div>
                    
                    <!-- Categories -->
                    <div class="form-group" style="margin-top: 30px;">
                        <h4 style="margin-bottom: 15px; color: var(--text-primary);"><?= htmlspecialchars(gs('categories.categories', $allStrings)) ?></h4>
                        
                        <div id="categoryTree" class="category-tree">
                            <ul id="categoryList" style="list-style: none; padding: 0; margin: 0;"></ul>
                        </div>
                        
                        <input type="hidden" id="product_category_primary" name="category_id" value="">
                        <input type="hidden" id="product_categories_hidden" name="categories" value="">
                        
                        <small style="display: block; margin-top: 10px; color: var(--text-secondary);">
                            <?= htmlspecialchars(gs('categories.hierarchy_info', $allStrings)) ?>
                        </small>
                    </div>
                    
                    <!-- Attributes -->
                    <div class="form-group" style="margin-top: 30px;">
                        <h4 style="margin-bottom: 15px; color: var(--text-primary);"><?= htmlspecialchars(gs('attributes.attributes', $allStrings)) ?></h4>
                        
                        <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                            <select id="attr_select" class="form-select" style="flex: 1;"></select>
                            <button id="attr_add_btn" type="button" class="btn outline">
                                <?= htmlspecialchars(gs('attributes.add_attribute', $allStrings)) ?>
                            </button>
                        </div>
                        
                        <div id="product_attributes_list"></div>
                    </div>
                    
                    <!-- Translations -->
                    <div class="form-group" style="margin-top: 30px;">
                        <h4 style="margin-bottom: 15px; color: var(--text-primary);"><?= htmlspecialchars(gs('translations.translations', $allStrings)) ?></h4>
                        
                        <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                            <button id="toggleTranslationsBtn" type="button" class="btn small outline">
                                <?= htmlspecialchars(gs('translations.toggle_translations', $allStrings)) ?>
                            </button>
                            <button id="fillFromDefaultBtn" type="button" class="btn small outline">
                                <?= htmlspecialchars(gs('translations.fill_from_default', $allStrings)) ?>
                            </button>
                            <button id="addLangBtn" type="button" class="btn small outline">
                                <?= htmlspecialchars(gs('translations.add_language', $allStrings)) ?>
                            </button>
                        </div>
                        
                        <small style="display: block; margin-bottom: 10px; color: var(--text-secondary);">
                            <?= htmlspecialchars(gs('translations.each_language_panel', $allStrings)) ?>
                        </small>
                        
                        <div id="product_translations_area" style="display: none; margin-top: 10px;">
                            <?php 
                            $availableLangs = ['en' => 'English', 'ar' => 'العربية'];
                            foreach ($availableLangs as $code => $name): 
                            ?>
                            <div class="tr-lang-panel" data-lang="<?= htmlspecialchars($code) ?>">
                                <div class="tr-lang-header">
                                    <strong><?= htmlspecialchars($name) ?> (<?= htmlspecialchars($code) ?>)</strong>
                                    <button type="button" class="btn small toggle-lang" data-lang="<?= htmlspecialchars($code) ?>">
                                        <?= htmlspecialchars(gs('translations.collapse', $allStrings)) ?>
                                    </button>
                                </div>
                                <div class="tr-lang-body">
                                    <div style="display: grid; gap: 10px;">
                                        <div>
                                            <label class="form-label"><?= htmlspecialchars(gs('general.name', $allStrings)) ?></label>
                                            <input class="tr-name form-input" data-lang="<?= htmlspecialchars($code) ?>">
                                        </div>
                                        <div>
                                            <label class="form-label"><?= htmlspecialchars(gs('translations.short_description', $allStrings)) ?></label>
                                            <input class="tr-short form-input" data-lang="<?= htmlspecialchars($code) ?>">
                                        </div>
                                        <div>
                                            <label class="form-label"><?= htmlspecialchars(gs('general.description', $allStrings)) ?></label>
                                            <textarea class="tr-desc form-textarea" data-lang="<?= htmlspecialchars($code) ?>" rows="3"></textarea>
                                        </div>
                                        <div>
                                            <label class="form-label"><?= htmlspecialchars(gs('translations.specifications', $allStrings)) ?></label>
                                            <textarea class="tr-spec form-textarea" data-lang="<?= htmlspecialchars($code) ?>" rows="2"></textarea>
                                        </div>
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                            <div>
                                                <label class="form-label"><?= htmlspecialchars(gs('translations.meta_title', $allStrings)) ?></label>
                                                <input class="tr-meta-title form-input" data-lang="<?= htmlspecialchars($code) ?>">
                                            </div>
                                            <div>
                                                <label class="form-label"><?= htmlspecialchars(gs('translations.meta_keywords', $allStrings)) ?></label>
                                                <input class="tr-meta-keys form-input" data-lang="<?= htmlspecialchars($code) ?>">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label"><?= htmlspecialchars(gs('translations.meta_description', $allStrings)) ?></label>
                                            <input class="tr-meta-desc form-input" data-lang="<?= htmlspecialchars($code) ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button id="productDeleteBtn" class="btn danger" style="display: none;">
                    <?= htmlspecialchars(gs('products.delete', $allStrings)) ?>
                </button>
                <button id="productCancelBtn" class="btn outline">
                    <?= htmlspecialchars(gs('products.cancel', $allStrings)) ?>
                </button>
                <button id="productSaveBtn" class="btn primary">
                    <?= htmlspecialchars(gs('products.save', $allStrings)) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
window.CSRF_TOKEN = "<?= $csrf ?>";
window.AVAILABLE_LANGUAGES = <?= json_encode([['code' => 'en', 'name' => 'English'], ['code' => 'ar', 'name' => 'العربية']], JSON_UNESCAPED_UNICODE) ?>;
window.CURRENT_USER = <?= json_encode($user, JSON_UNESCAPED_UNICODE) ?>;
window.TRANSLATIONS = <?= json_encode($allStrings, JSON_UNESCAPED_UNICODE) ?>;
window.PRODUCT_META_API = "<?= htmlspecialchars($productMetaApi) ?>";
window.CATEGORIES_API = "<?= htmlspecialchars($categoriesApi) ?>";
window.MEDIA_API = "<?= htmlspecialchars($mediaStudioApi) ?>";
window.API_BASE = "<?= htmlspecialchars($apiPath) ?>";
</script>
<script src="/admin/assets/js/admin_core.js"></script>
<script src="/admin/assets/js/pages/products.js" defer></script>
</body>
</html>