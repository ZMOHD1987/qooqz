<?php
declare(strict_types=1);

/**
 * /admin/fragments/themes.php
 * Theme Management - Products-pattern: List view → Form with tabs
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

if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
    header('Location: /admin/login.php');
    exit;
}

$user     = admin_user();
$lang     = admin_lang();
$dir      = admin_dir();
$csrf     = admin_csrf();
$tenantId = admin_tenant_id();

$isSuperAdmin = is_super_admin();
$canManage    = $isSuperAdmin || can('manage_themes');

if (!$canManage) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    http_response_code(403);
    die('Access denied');
}

// ════════════════════════════════════════════════════════════
// DB-DRIVEN CSS VARS HELPER (Themes)
// ════════════════════════════════════════════════════════════
if (!function_exists('renderFragmentThemeVars')) {
    function renderFragmentThemeVars(array $theme): void {
        $emitted = [];
        $lines   = [':root {'];

        $emit = function(string $k, string $v) use (&$emitted, &$lines): void {
            $ke = htmlspecialchars($k, ENT_QUOTES);
            $ve = htmlspecialchars($v, ENT_QUOTES);
            $lines[]           = "    --{$ke}: {$ve};";
            $emitted["--{$k}"] = $v;
        };

        foreach ($theme['color_settings'] ?? [] as $c) {
            if (empty($c['setting_key']) || !isset($c['color_value'])) continue;
            $k = $c['setting_key'];
            $h = str_replace('_', '-', $k);
            $v = $c['color_value'];
            $emit($k, $v);
            if ($h !== $k) $emit($h, $v);
        }
        foreach ($theme['font_settings'] ?? [] as $f) {
            if (empty($f['setting_key'])) continue;
            $sk = $f['setting_key'];
            $sh = str_replace('_', '-', $sk);
            if (!empty($f['font_family'])) {
                $emit("{$sk}-family", $f['font_family']);
                if ($sh !== $sk) $emit("{$sh}-family", $f['font_family']);
            }
            if (!empty($f['font_size'])) {
                $emit("{$sk}-size", $f['font_size']);
                if ($sh !== $sk) $emit("{$sh}-size", $f['font_size']);
            }
            if (!empty($f['font_weight'])) {
                $emit("{$sk}-weight", $f['font_weight']);
                if ($sh !== $sk) $emit("{$sh}-weight", $f['font_weight']);
            }
        }
        foreach ($theme['design_settings'] ?? [] as $d) {
            if (empty($d['setting_key']) || !isset($d['setting_value'])) continue;
            $dk = $d['setting_key'];
            $dh = str_replace('_', '-', $dk);
            $emit($dk, $d['setting_value']);
            if ($dh !== $dk) $emit($dh, $d['setting_value']);
        }
        foreach ($theme['button_styles'] ?? [] as $b) {
            if (empty($b['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$b['slug']));
            if (!empty($b['background_color'])) $emit("btn-{$slug}-bg",     $b['background_color']);
            if (!empty($b['text_color']))        $emit("btn-{$slug}-color",  $b['text_color']);
            if (!empty($b['border_color']))      $emit("btn-{$slug}-border", $b['border_color']);
            if (!empty($b['border_radius']))     $emit("btn-{$slug}-radius", $b['border_radius'] . 'px');
        }
        foreach ($theme['card_styles'] ?? [] as $cs) {
            if (empty($cs['slug'])) continue;
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$cs['slug']));
            if (!empty($cs['background_color'])) $emit("card-{$slug}-bg",     $cs['background_color']);
            if (!empty($cs['border_color']))      $emit("card-{$slug}-border", $cs['border_color']);
            if (!empty($cs['border_radius']))     $emit("card-{$slug}-radius", $cs['border_radius'] . 'px');
            if (!empty($cs['shadow_style']))      $emit("card-{$slug}-shadow", $cs['shadow_style']);
        }

        // Emit card_type-based aliases so that CSS selectors like --card-product-bg work
        foreach ($theme['card_styles'] ?? [] as $cs) {
            if (empty($cs['card_type'])) continue;
            $type = preg_replace('/[^a-z0-9_-]/', '-', strtolower((string)$cs['card_type']));
            if (!empty($cs['background_color'])) $emit("card-{$type}-bg",     $cs['background_color']);
            if (!empty($cs['border_color']))      $emit("card-{$type}-border", $cs['border_color']);
            if (!empty($cs['border_radius']))     $emit("card-{$type}-radius", $cs['border_radius'] . 'px');
            if (!empty($cs['shadow_style']))      $emit("card-{$type}-shadow", $cs['shadow_style']);
        }

        // Common alias defaults
        $bgSec = $emitted['--background-secondary'] ?? $emitted['--background_secondary'] ?? null;
        $aliasDefaults = [
            '--card-bg'       => $emitted['--card-bg']      ?? $bgSec ?? '#081127',
            '--input-bg'      => $emitted['--input-bg']     ?? $bgSec ?? '#0b1220',
            '--thead-bg'      => $emitted['--thead-bg']     ?? $bgSec ?? '#061021',
            '--danger-color'  => $emitted['--danger-color'] ?? $emitted['--error-color'] ?? '#ef4444',
            '--success-color' => $emitted['--success-color'] ?? '#22c55e',
        ];
        foreach ($aliasDefaults as $cssVar => $val) {
            if (!isset($emitted[$cssVar])) {
                $lines[]           = '    ' . htmlspecialchars($cssVar, ENT_QUOTES) . ': ' . htmlspecialchars($val, ENT_QUOTES) . ';';
                $emitted[$cssVar]  = $val;
            }
        }

        $lines[] = '}';
        echo implode(PHP_EOL, $lines) . PHP_EOL;
    }
}
?>
<!-- DB-driven CSS vars (all settings, colors, fonts from database) -->
<style id="db-theme-vars-themes">
<?php renderFragmentThemeVars($GLOBALS['ADMIN_UI']['theme'] ?? []); ?>
<?php if (!empty($GLOBALS['ADMIN_UI']['theme']['generated_css'])): ?>
<?= $GLOBALS['ADMIN_UI']['theme']['generated_css'] ?>
<?php endif; ?>
</style>

<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/themes-system.css">
<?php endif; ?>

<!-- Page Meta -->
<meta data-page="themes"
      data-assets-css="/admin/assets/css/themes-system.css"
      data-i18n-files="/languages/AdminUiTheme/<?= htmlspecialchars($lang) ?>.json">

<script>
window.THEMES_CONFIG = {
    TENANT_ID: <?= (int)$tenantId ?>,
    LANG: '<?= htmlspecialchars($lang) ?>',
    DIR: '<?= htmlspecialchars($dir) ?>',
    CSRF: '<?= htmlspecialchars($csrf) ?>',
    API: {
        themes: '/api/themes',
        designSettings: '/api/design_settings',
        colorSettings: '/api/color_settings',
        fontSettings: '/api/font_settings',
        buttonStyles: '/api/button_styles',
        cardStyles: '/api/card_styles',
        homepageSections: '/api/homepage_sections',
        systemSettings: '/api/system_settings',
        languages: '/api/languages',
        themeTranslations: '/api/theme_translations'
    }
};
</script>

<div class="themes-page" id="themesPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Alerts -->
    <div id="alertsContainer"></div>

    <!-- ═══════════ LIST VIEW ═══════════ -->
    <div id="themesListView">
        <div class="page-header">
            <div>
                <h1 data-i18n="theme_manager.title">Theme Management</h1>
                <p data-i18n="theme_manager.subtitle">Manage themes and styling</p>
            </div>
            <div class="page-header-actions">
                <button class="btn btn-primary" id="btnAddTheme">
                    <i class="fas fa-plus"></i>
                    <span data-i18n="theme_manager.add_new">Add New Theme</span>
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-bar">
            <input type="text" id="themeSearch" class="form-control"
                   data-i18n-placeholder="theme_manager.filters.search_placeholder"
                   placeholder="Search themes...">
            <select id="themeStatusFilter" class="form-control">
                <option value="" data-i18n="theme_manager.filters.status_options.all">All Status</option>
                <option value="1" data-i18n="theme_manager.status.active">Active</option>
                <option value="0" data-i18n="theme_manager.status.inactive">Inactive</option>
            </select>
        </div>

        <!-- Themes Table -->
        <div class="card table-card">
            <div class="card-body" style="padding:0">
                <!-- Loading — visible by default; JS hides it when data loads -->
                <div id="themesLoading" class="loading-state" style="display:flex">
                    <div class="spinner"></div>
                    <p data-i18n="theme_manager.loading">Loading themes...</p>
                </div>

                <div id="themesTableContainer" style="display:none">
                    <table class="data-table" id="themesTable">
                        <thead>
                            <tr>
                                <th data-i18n="theme_manager.table.id">ID</th>
                                <th data-i18n="theme_manager.form.fields.name.label">Name</th>
                                <th data-i18n="theme_manager.form.fields.slug.label">Slug</th>
                                <th data-i18n="theme_manager.form.fields.version.label">Version</th>
                                <th data-i18n="theme_manager.form.fields.status.label">Status</th>
                                <th data-i18n="theme_manager.form.fields.is_default">Default</th>
                                <th data-i18n="theme_manager.table.actions_label">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="themesTableBody"></tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div id="themesEmpty" class="empty-state" style="display:none">
                    <i class="fas fa-palette fa-3x"></i>
                    <h3 data-i18n="theme_manager.table.empty.title">No Themes Found</h3>
                    <p data-i18n="theme_manager.table.empty.message">Start by creating your first theme</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════ FORM VIEW (hidden by default) ═══════════ -->
    <div id="themeFormView" style="display:none">
        <div class="page-header">
            <div>
                <h1 id="formTitle" data-i18n="theme_manager.form.add_title">Add Theme</h1>
            </div>
            <div class="page-header-actions">
                <button class="btn btn-secondary" id="btnCancelForm">
                    <i class="fas fa-arrow-left"></i>
                    <span data-i18n="theme_manager.form.buttons.cancel">Cancel</span>
                </button>
                <button class="btn btn-primary" id="btnSaveTheme">
                    <i class="fas fa-save"></i>
                    <span data-i18n="theme_manager.form.buttons.save">Save</span>
                </button>
                <button class="btn btn-danger" id="btnDeleteTheme" style="display:none">
                    <i class="fas fa-trash"></i>
                    <span data-i18n="theme_manager.table.actions.delete">Delete</span>
                </button>
            </div>
        </div>

        <!-- Form Tabs -->
        <div class="form-tabs">
            <button class="form-tab active" data-tab="info">
                <i class="fas fa-info-circle"></i> <span data-i18n="tabs.info">Theme Info</span>
            </button>
            <button class="form-tab" data-tab="design">
                <i class="fas fa-cog"></i> <span data-i18n="tabs.design">Design</span>
            </button>
            <button class="form-tab" data-tab="colors">
                <i class="fas fa-paint-brush"></i> <span data-i18n="tabs.colors">Colors</span>
            </button>
            <button class="form-tab" data-tab="fonts">
                <i class="fas fa-font"></i> <span data-i18n="tabs.fonts">Fonts</span>
            </button>
            <button class="form-tab" data-tab="buttons">
                <i class="fas fa-mouse-pointer"></i> <span data-i18n="tabs.buttons">Buttons</span>
            </button>
            <button class="form-tab" data-tab="cards">
                <i class="fas fa-square"></i> <span data-i18n="tabs.cards">Cards</span>
            </button>
            <button class="form-tab" data-tab="homepage">
                <i class="fas fa-home"></i> <span data-i18n="tabs.homepage">Homepage</span>
            </button>
            <button class="form-tab" data-tab="system">
                <i class="fas fa-cogs"></i> <span data-i18n="tabs.system">System Settings</span>
            </button>
            <button class="form-tab" data-tab="translations">
                <i class="fas fa-language"></i> <span data-i18n="tabs.translations">Translations</span>
            </button>
        </div>

        <!-- TAB: Theme Info -->
        <div class="tab-content active" id="tab-info">
            <form id="themeForm">
                <input type="hidden" id="themeId" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="themeName" data-i18n="theme_manager.form.fields.name.label">Theme Name</label>
                        <input type="text" id="themeName" class="form-control" required
                               data-i18n-placeholder="theme_manager.form.fields.name.placeholder">
                    </div>
                    <div class="form-group">
                        <label for="themeSlug" data-i18n="theme_manager.form.fields.slug.label">Slug</label>
                        <input type="text" id="themeSlug" class="form-control" required
                               data-i18n-placeholder="theme_manager.form.fields.slug.placeholder">
                    </div>
                </div>
                <div class="form-group">
                    <label for="themeDescription" data-i18n="theme_manager.form.fields.description.label">Description</label>
                    <textarea id="themeDescription" class="form-control" rows="3"
                              data-i18n-placeholder="theme_manager.form.fields.description.placeholder"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="themeVersion" data-i18n="theme_manager.form.fields.version.label">Version</label>
                        <input type="text" id="themeVersion" class="form-control" value="1.0.0"
                               data-i18n-placeholder="theme_manager.form.fields.version.placeholder">
                    </div>
                    <div class="form-group">
                        <label for="themeAuthor" data-i18n="theme_manager.form.fields.author.label">Author</label>
                        <input type="text" id="themeAuthor" class="form-control"
                               data-i18n-placeholder="theme_manager.form.fields.author.placeholder">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="themeThumbnailUrl" data-i18n="theme_manager.form.fields.thumbnail_url.label">Thumbnail URL</label>
                        <div style="display:flex;gap:8px;align-items:flex-end;">
                            <input type="text" id="themeThumbnailUrl" class="form-control"
                                   data-i18n-placeholder="theme_manager.form.fields.thumbnail_url.placeholder">
                            <button type="button" class="btn btn-secondary btn-sm" id="btnSelectThumbnail" style="white-space:nowrap;">
                                <i class="fas fa-images"></i> <span data-i18n="theme_manager.form.fields.select_image">Select Image</span>
                            </button>
                        </div>
                        <div id="thumbnailPreviewWrap" style="margin-top:6px;display:none;">
                            <img id="thumbnailPreviewImg" src="" alt="" style="max-width:120px;max-height:80px;border-radius:6px;border:1px solid var(--border-color,#334155);">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="themePreviewUrl" data-i18n="theme_manager.form.fields.preview_url.label">Preview URL</label>
                        <div style="display:flex;gap:8px;align-items:flex-end;">
                            <input type="text" id="themePreviewUrl" class="form-control"
                                   data-i18n-placeholder="theme_manager.form.fields.preview_url.placeholder">
                            <button type="button" class="btn btn-secondary btn-sm" id="btnSelectPreview" style="white-space:nowrap;">
                                <i class="fas fa-images"></i> <span data-i18n="theme_manager.form.fields.select_image">Select Image</span>
                            </button>
                        </div>
                        <div id="previewUrlPreviewWrap" style="margin-top:6px;display:none;">
                            <img id="previewUrlPreviewImg" src="" alt="" style="max-width:120px;max-height:80px;border-radius:6px;border:1px solid var(--border-color,#334155);">
                        </div>
                    </div>
                </div>

                <!-- Inline Media Studio Panel -->
                <div id="themeMediaStudioPanel" style="display:none;margin-top:16px;border:1px solid var(--border-color,#334155);border-radius:8px;overflow:hidden;">
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--card-bg,#0d1b2e);border-bottom:1px solid var(--border-color,#334155);">
                        <strong data-i18n="theme_manager.form.media_studio.title"><i class="fas fa-images"></i> Media Studio</strong>
                        <button type="button" id="btnCloseThemeMediaStudio" class="btn btn-sm btn-secondary">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <iframe id="themeMediaStudioFrame"
                            src=""
                            style="width:100%;height:520px;border:none;display:block;"
                            allow="same-origin"></iframe>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label data-i18n="theme_manager.form.fields.status.label">Status</label>
                        <select id="themeIsActive" class="form-control">
                            <option value="1" data-i18n="theme_manager.status.active">Active</option>
                            <option value="0" data-i18n="theme_manager.status.inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="themeIsDefault">
                            <span data-i18n="theme_manager.form.fields.is_default">Set as default theme</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>

        <!-- TAB: Design Settings -->
        <div class="tab-content" id="tab-design" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="theme_manager_settings.design.title">Design Settings</h3>
                <button class="btn btn-sm btn-primary" id="btnAddDesign">
                    <i class="fas fa-plus"></i> <span data-i18n="theme_manager_settings.design.add">Add Setting</span>
                </button>
            </div>
            <div id="designSettingsList" class="settings-list"></div>
            <div id="designForm" class="inline-form" style="display:none">
                <input type="hidden" id="designId">
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.key">Key</label><input type="text" id="designKey" class="form-control"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.name">Name</label><input type="text" id="designName" class="form-control"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.value">Value</label><input type="text" id="designValue" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.type">Type</label>
                        <select id="designType" class="form-control">
                            <option value="text"    data-i18n="theme_manager_settings.types.text">Text</option>
                            <option value="number"  data-i18n="theme_manager_settings.types.number">Number</option>
                            <option value="color"   data-i18n="theme_manager_settings.types.color">Color</option>
                            <option value="image"   data-i18n="theme_manager_settings.types.image">Image</option>
                            <option value="boolean" data-i18n="theme_manager_settings.types.boolean">Boolean</option>
                            <option value="select"  data-i18n="theme_manager_settings.types.select">Select</option>
                            <option value="json"    data-i18n="theme_manager_settings.types.json">JSON</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.category">Category</label>
                        <select id="designCategory" class="form-control">
                            <option value="layout"   data-i18n="theme_manager_settings.categories.layout">Layout</option>
                            <option value="header"   data-i18n="theme_manager_settings.categories.header">Header</option>
                            <option value="footer"   data-i18n="theme_manager_settings.categories.footer">Footer</option>
                            <option value="sidebar"  data-i18n="theme_manager_settings.categories.sidebar">Sidebar</option>
                            <option value="homepage" data-i18n="theme_manager_settings.categories.homepage">Homepage</option>
                            <option value="product"  data-i18n="theme_manager_settings.categories.product">Product</option>
                            <option value="cart"     data-i18n="theme_manager_settings.categories.cart">Cart</option>
                            <option value="checkout" data-i18n="theme_manager_settings.categories.checkout">Checkout</option>
                            <option value="other"    data-i18n="theme_manager_settings.categories.other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.active">Active</label>
                        <select id="designIsActive" class="form-control">
                            <option value="1" data-i18n="theme_manager_settings.form.yes">Yes</option>
                            <option value="0" data-i18n="theme_manager_settings.form.no">No</option>
                        </select>
                    </div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.sort_order">Sort Order</label><input type="number" id="designSortOrder" class="form-control" value="0"></div>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveDesign" data-i18n="theme_manager_settings.form.save">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelDesign" data-i18n="theme_manager_settings.form.cancel">Cancel</button>
                </div>
            </div>
        </div>

        <!-- TAB: Color Settings -->
        <div class="tab-content" id="tab-colors" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="theme_manager_settings.colors.title">Color Settings</h3>
                <button class="btn btn-sm btn-primary" id="btnAddColor">
                    <i class="fas fa-plus"></i> <span data-i18n="theme_manager_settings.colors.add">Add Color</span>
                </button>
            </div>
            <div id="colorSettingsList" class="settings-list"></div>
            <div id="colorForm" class="inline-form" style="display:none">
                <input type="hidden" id="colorId">
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.key">Key</label><input type="text" id="colorKey" class="form-control"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.name">Name</label><input type="text" id="colorName" class="form-control"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.color">Color</label><input type="color" id="colorValue" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.category">Category</label>
                        <select id="colorCategory" class="form-control">
                            <option value="primary"    data-i18n="theme_manager_settings.categories.primary">Primary</option>
                            <option value="secondary"  data-i18n="theme_manager_settings.categories.secondary">Secondary</option>
                            <option value="accent"     data-i18n="theme_manager_settings.categories.accent">Accent</option>
                            <option value="background" data-i18n="theme_manager_settings.categories.background">Background</option>
                            <option value="text"       data-i18n="theme_manager_settings.categories.text">Text</option>
                            <option value="border"     data-i18n="theme_manager_settings.categories.border">Border</option>
                            <option value="status"     data-i18n="theme_manager_settings.categories.status">Status</option>
                            <option value="header"     data-i18n="theme_manager_settings.categories.header">Header</option>
                            <option value="footer"     data-i18n="theme_manager_settings.categories.footer">Footer</option>
                            <option value="other"      data-i18n="theme_manager_settings.categories.other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.active">Active</label>
                        <select id="colorIsActive" class="form-control">
                            <option value="1" data-i18n="theme_manager_settings.form.yes">Yes</option>
                            <option value="0" data-i18n="theme_manager_settings.form.no">No</option>
                        </select>
                    </div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.sort_order">Sort Order</label><input type="number" id="colorSortOrder" class="form-control" value="0"></div>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveColor" data-i18n="theme_manager_settings.form.save">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelColor" data-i18n="theme_manager_settings.form.cancel">Cancel</button>
                </div>
            </div>
        </div>

        <!-- TAB: Font Settings -->
        <div class="tab-content" id="tab-fonts" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="theme_manager_settings.fonts.title">Font Settings</h3>
                <button class="btn btn-sm btn-primary" id="btnAddFont">
                    <i class="fas fa-plus"></i> <span data-i18n="theme_manager_settings.fonts.add">Add Font</span>
                </button>
            </div>
            <div id="fontSettingsList" class="settings-list"></div>
            <div id="fontForm" class="inline-form" style="display:none">
                <input type="hidden" id="fontId">
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.key">Key</label><input type="text" id="fontKey" class="form-control"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.name">Name</label><input type="text" id="fontName" class="form-control"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.font_family">Font Family</label><input type="text" id="fontFamily" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.size">Size</label><input type="text" id="fontSize" class="form-control" placeholder="16px"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.weight">Weight</label><input type="text" id="fontWeight" class="form-control" placeholder="400"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.line_height">Line Height</label><input type="text" id="fontLineHeight" class="form-control" placeholder="1.5"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.category">Category</label>
                        <select id="fontCategory" class="form-control">
                            <option value="heading"    data-i18n="theme_manager_settings.categories.heading">Heading</option>
                            <option value="body"       data-i18n="theme_manager_settings.categories.body">Body</option>
                            <option value="button"     data-i18n="theme_manager_settings.categories.button">Button</option>
                            <option value="navigation" data-i18n="theme_manager_settings.categories.navigation">Navigation</option>
                            <option value="other"      data-i18n="theme_manager_settings.categories.other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.active">Active</label>
                        <select id="fontIsActive" class="form-control">
                            <option value="1" data-i18n="theme_manager_settings.form.yes">Yes</option>
                            <option value="0" data-i18n="theme_manager_settings.form.no">No</option>
                        </select>
                    </div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.sort_order">Sort Order</label><input type="number" id="fontSortOrder" class="form-control" value="0"></div>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveFont" data-i18n="theme_manager_settings.form.save">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelFont" data-i18n="theme_manager_settings.form.cancel">Cancel</button>
                </div>
            </div>
        </div>

        <!-- TAB: Button Styles -->
        <div class="tab-content" id="tab-buttons" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="theme_manager_settings.buttons.title">Button Styles</h3>
                <button class="btn btn-sm btn-primary" id="btnAddButton">
                    <i class="fas fa-plus"></i> <span data-i18n="theme_manager_settings.buttons.add">Add Button Style</span>
                </button>
            </div>
            <div id="buttonStylesList" class="settings-list"></div>
            <div id="buttonForm" class="inline-form" style="display:none">
                <input type="hidden" id="buttonId">
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.name">Name</label><input type="text" id="buttonName" class="form-control"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.slug">Slug</label><input type="text" id="buttonSlug" class="form-control"></div>
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.type">Type</label>
                        <select id="buttonType" class="form-control">
                            <option value="primary"   data-i18n="theme_manager_settings.button_types.primary">Primary</option>
                            <option value="secondary" data-i18n="theme_manager_settings.button_types.secondary">Secondary</option>
                            <option value="success"   data-i18n="theme_manager_settings.button_types.success">Success</option>
                            <option value="danger"    data-i18n="theme_manager_settings.button_types.danger">Danger</option>
                            <option value="warning"   data-i18n="theme_manager_settings.button_types.warning">Warning</option>
                            <option value="info"      data-i18n="theme_manager_settings.button_types.info">Info</option>
                            <option value="outline"   data-i18n="theme_manager_settings.button_types.outline">Outline</option>
                            <option value="link"      data-i18n="theme_manager_settings.button_types.link">Link</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.bg_color">BG Color</label><input type="color" id="buttonBgColor" class="form-control" value="#007bff"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.text_color">Text Color</label><input type="color" id="buttonTextColor" class="form-control" value="#ffffff"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.border_color">Border Color</label><input type="color" id="buttonBorderColor" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.border_width">Border Width</label><input type="number" id="buttonBorderWidth" class="form-control" value="0"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.border_radius">Border Radius</label><input type="number" id="buttonBorderRadius" class="form-control" value="4"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.padding">Padding</label><input type="text" id="buttonPadding" class="form-control" value="10px 20px"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.font_size">Font Size</label><input type="text" id="buttonFontSize" class="form-control" value="14px"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.font_weight">Font Weight</label><input type="text" id="buttonFontWeight" class="form-control" value="normal"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.hover_bg">Hover BG</label><input type="color" id="buttonHoverBg" class="form-control"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.hover_text">Hover Text</label><input type="color" id="buttonHoverText" class="form-control"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.hover_border">Hover Border</label><input type="color" id="buttonHoverBorder" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.active">Active</label>
                        <select id="buttonIsActive" class="form-control">
                            <option value="1" data-i18n="theme_manager_settings.form.yes">Yes</option>
                            <option value="0" data-i18n="theme_manager_settings.form.no">No</option>
                        </select>
                    </div>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveButton" data-i18n="theme_manager_settings.form.save">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelButton" data-i18n="theme_manager_settings.form.cancel">Cancel</button>
                </div>
            </div>
        </div>

        <!-- TAB: Card Styles -->
        <div class="tab-content" id="tab-cards" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="theme_manager_settings.cards.title">Card Styles</h3>
                <button class="btn btn-sm btn-primary" id="btnAddCard">
                    <i class="fas fa-plus"></i> <span data-i18n="theme_manager_settings.cards.add">Add Card Style</span>
                </button>
            </div>
            <div id="cardStylesList" class="settings-list"></div>
            <div id="cardForm" class="inline-form" style="display:none">
                <input type="hidden" id="cardId">
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.name">Name</label><input type="text" id="cardName" class="form-control"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.slug">Slug</label><input type="text" id="cardSlug" class="form-control"></div>
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.type">Type</label>
                        <select id="cardType" class="form-control">
                            <option value="product"      data-i18n="theme_manager_settings.card_types.product">Product</option>
                            <option value="category"     data-i18n="theme_manager_settings.card_types.category">Category</option>
                            <option value="entity"       data-i18n="theme_manager_settings.card_types.entity">Entity</option>
                            <option value="entities"     data-i18n="theme_manager_settings.card_types.entities">Entities</option>
                            <option value="tenant"       data-i18n="theme_manager_settings.card_types.tenant">Tenant</option>
                            <option value="tenants"      data-i18n="theme_manager_settings.card_types.tenants">Tenants</option>
                            <option value="vendor"       data-i18n="theme_manager_settings.card_types.vendor">Vendor</option>
                            <option value="blog"         data-i18n="theme_manager_settings.card_types.blog">Blog</option>
                            <option value="feature"      data-i18n="theme_manager_settings.card_types.feature">Feature</option>
                            <option value="testimonial"  data-i18n="theme_manager_settings.card_types.testimonial">Testimonial</option>
                            <option value="auction"      data-i18n="theme_manager_settings.card_types.auction">Auction</option>
                            <option value="notification" data-i18n="theme_manager_settings.card_types.notification">Notification</option>
                            <option value="discount"     data-i18n="theme_manager_settings.card_types.discount">Discount</option>
                            <option value="jobs"         data-i18n="theme_manager_settings.card_types.jobs">Jobs</option>
                            <option value="plan"         data-i18n="theme_manager_settings.card_types.plan">Plan</option>
                            <option value="other"        data-i18n="theme_manager_settings.card_types.other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.bg_color">BG Color</label><input type="color" id="cardBgColor" class="form-control" value="#FFFFFF"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.border_color">Border Color</label><input type="color" id="cardBorderColor" class="form-control" value="#E0E0E0"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.border_width">Border Width</label><input type="number" id="cardBorderWidth" class="form-control" value="1"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.border_radius">Border Radius</label><input type="number" id="cardBorderRadius" class="form-control" value="8"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.shadow">Shadow</label><input type="text" id="cardShadow" class="form-control" value="none" placeholder="0 2px 4px rgba(0,0,0,.1)"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.padding">Padding</label><input type="text" id="cardPadding" class="form-control" value="16px"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.hover_effect">Hover Effect</label>
                        <select id="cardHoverEffect" class="form-control">
                            <option value="none"       data-i18n="theme_manager_settings.hover_effects.none">None</option>
                            <option value="lift"       data-i18n="theme_manager_settings.hover_effects.lift">Lift</option>
                            <option value="zoom"       data-i18n="theme_manager_settings.hover_effects.zoom">Zoom</option>
                            <option value="shadow"     data-i18n="theme_manager_settings.hover_effects.shadow">Shadow</option>
                            <option value="border"     data-i18n="theme_manager_settings.hover_effects.border">Border</option>
                            <option value="brightness" data-i18n="theme_manager_settings.hover_effects.brightness">Brightness</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.text_align">Text Align</label>
                        <select id="cardTextAlign" class="form-control">
                            <option value="left"   data-i18n="theme_manager_settings.text_aligns.left">Left</option>
                            <option value="center" data-i18n="theme_manager_settings.text_aligns.center">Center</option>
                            <option value="right"  data-i18n="theme_manager_settings.text_aligns.right">Right</option>
                        </select>
                    </div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.image_ratio">Image Ratio</label><input type="text" id="cardImageRatio" class="form-control" value="1:1"></div>
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.active">Active</label>
                        <select id="cardIsActive" class="form-control">
                            <option value="1" data-i18n="theme_manager_settings.form.yes">Yes</option>
                            <option value="0" data-i18n="theme_manager_settings.form.no">No</option>
                        </select>
                    </div>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveCard" data-i18n="theme_manager_settings.form.save">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelCard" data-i18n="theme_manager_settings.form.cancel">Cancel</button>
                </div>
            </div>
        </div>

        <!-- TAB: Homepage Sections -->
        <div class="tab-content" id="tab-homepage" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="theme_manager_settings.homepage.title">Homepage Sections</h3>
                <button class="btn btn-sm btn-primary" id="btnAddSection">
                    <i class="fas fa-plus"></i> <span data-i18n="theme_manager_settings.homepage.add">Add Section</span>
                </button>
            </div>
            <div id="homepageSectionsList" class="settings-list"></div>
            <div id="sectionForm" class="inline-form" style="display:none">
                <input type="hidden" id="sectionId">
                <div class="form-row">
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.section_type">Section Type</label>
                        <select id="sectionType" class="form-control">
                            <option value="slider"            data-i18n="theme_manager_settings.section_types.slider">Slider</option>
                            <option value="categories"        data-i18n="theme_manager_settings.section_types.categories">Categories</option>
                            <option value="featured_products" data-i18n="theme_manager_settings.section_types.featured_products">Featured Products</option>
                            <option value="new_products"      data-i18n="theme_manager_settings.section_types.new_products">New Products</option>
                            <option value="deals"             data-i18n="theme_manager_settings.section_types.deals">Deals</option>
                            <option value="brands"            data-i18n="theme_manager_settings.section_types.brands">Brands</option>
                            <option value="vendors"           data-i18n="theme_manager_settings.section_types.vendors">Vendors</option>
                            <option value="banners"           data-i18n="theme_manager_settings.section_types.banners">Banners</option>
                            <option value="testimonials"      data-i18n="theme_manager_settings.section_types.testimonials">Testimonials</option>
                            <option value="custom_html"       data-i18n="theme_manager_settings.section_types.custom_html">Custom HTML</option>
                            <option value="other"             data-i18n="theme_manager_settings.section_types.other">Other</option>
                        </select>
                    </div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.title">Title</label><input type="text" id="sectionTitle" class="form-control"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.subtitle">Subtitle</label><input type="text" id="sectionSubtitle" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.layout">Layout</label>
                        <select id="sectionLayout" class="form-control">
                            <option value="grid"     data-i18n="theme_manager_settings.layouts.grid">Grid</option>
                            <option value="slider"   data-i18n="theme_manager_settings.layouts.slider">Slider</option>
                            <option value="list"     data-i18n="theme_manager_settings.layouts.list">List</option>
                            <option value="carousel" data-i18n="theme_manager_settings.layouts.carousel">Carousel</option>
                            <option value="masonry"  data-i18n="theme_manager_settings.layouts.masonry">Masonry</option>
                        </select>
                    </div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.items_per_row">Items/Row</label><input type="number" id="sectionItemsPerRow" class="form-control" value="4"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.sort_order">Sort Order</label><input type="number" id="sectionSortOrder" class="form-control" value="0"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.bg_color">BG Color</label><input type="color" id="sectionBgColor" class="form-control" value="#FFFFFF"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.text_color">Text Color</label><input type="color" id="sectionTextColor" class="form-control" value="#000000"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.padding">Padding</label><input type="text" id="sectionPadding" class="form-control" value="40px 0"></div>
                </div>
                <div class="form-group"><label data-i18n="theme_manager_settings.form.data_source">Data Source</label><input type="text" id="sectionDataSource" class="form-control" placeholder="e.g. /api/products?is_featured=1"></div>
                <div class="form-group"><label data-i18n="theme_manager_settings.form.custom_css">Custom CSS</label><textarea id="sectionCustomCss" class="form-control" rows="2"></textarea></div>
                <div class="form-group"><label data-i18n="theme_manager_settings.form.custom_html">Custom HTML</label><textarea id="sectionCustomHtml" class="form-control" rows="3"></textarea></div>
                <div class="form-group">
                    <label><input type="checkbox" id="sectionIsActive" checked> <span data-i18n="theme_manager_settings.form.active">Active</span></label>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveSection" data-i18n="theme_manager_settings.form.save">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelSection" data-i18n="theme_manager_settings.form.cancel">Cancel</button>
                </div>
            </div>
        </div>

        <!-- TAB: System Settings -->
        <div class="tab-content" id="tab-system" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="theme_manager_settings.system.title">System Settings</h3>
                <button class="btn btn-sm btn-primary" id="btnAddSystem">
                    <i class="fas fa-plus"></i> <span data-i18n="theme_manager_settings.system.add">Add Setting</span>
                </button>
            </div>
            <div id="systemSettingsList" class="settings-list"></div>
            <div id="systemForm" class="inline-form" style="display:none">
                <input type="hidden" id="systemId">
                <div class="form-row">
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.key">Key</label><input type="text" id="systemKey" class="form-control"></div>
                    <div class="form-group"><label data-i18n="theme_manager_settings.form.category">Category</label><input type="text" id="systemCategory" class="form-control"></div>
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.type">Type</label>
                        <select id="systemType" class="form-control">
                            <option value="text"    data-i18n="theme_manager_settings.types.text">Text</option>
                            <option value="number"  data-i18n="theme_manager_settings.types.number">Number</option>
                            <option value="boolean" data-i18n="theme_manager_settings.types.boolean">Boolean</option>
                            <option value="json"    data-i18n="theme_manager_settings.types.json">JSON</option>
                            <option value="file"    data-i18n="theme_manager_settings.types.file">File</option>
                            <option value="email"   data-i18n="theme_manager_settings.types.email">Email</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label data-i18n="theme_manager_settings.form.value">Value</label><textarea id="systemValue" class="form-control" rows="2"></textarea></div>
                <div class="form-group"><label data-i18n="theme_manager_settings.form.description">Description</label><textarea id="systemDescription" class="form-control" rows="2"></textarea></div>
                <div class="form-row">
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.public">Public</label>
                        <select id="systemIsPublic" class="form-control">
                            <option value="0" data-i18n="theme_manager_settings.form.no">No</option>
                            <option value="1" data-i18n="theme_manager_settings.form.yes">Yes</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label data-i18n="theme_manager_settings.form.editable">Editable</label>
                        <select id="systemIsEditable" class="form-control">
                            <option value="1" data-i18n="theme_manager_settings.form.yes">Yes</option>
                            <option value="0" data-i18n="theme_manager_settings.form.no">No</option>
                        </select>
                    </div>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveSystem" data-i18n="theme_manager_settings.form.save">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelSystem" data-i18n="theme_manager_settings.form.cancel">Cancel</button>
                </div>
            </div>
        </div>

        <!-- TAB: Translations -->
        <div class="tab-content" id="tab-translations" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="theme_manager_settings.translations.title">Translations</h3>
            </div>
            <div style="background:rgba(59,130,246,0.08); border:1px solid rgba(59,130,246,0.25); border-radius:8px; padding:12px; margin-bottom:16px; font-size:0.85rem; color:var(--text-secondary,#94a3b8);">
                <i class="fas fa-info-circle" style="color:#3b82f6;"></i>
                Add translated <strong style="color:var(--text-primary,#fff);">name</strong> and <strong style="color:var(--text-primary,#fff);">description</strong> for any language.
            </div>
            <div id="themeTranslationPanels" class="translation-panels"></div>
            <div class="form-group" style="margin-top:12px;">
                <label for="themeLangSelect" data-i18n="theme_manager_settings.translations.select_lang">Select Language</label>
                <div style="display:flex; gap:8px; align-items:flex-end;">
                    <select id="themeLangSelect" class="form-control" style="flex:1;">
                        <option value="" data-i18n="theme_manager_settings.translations.choose_lang">Choose language</option>
                    </select>
                    <button type="button" id="btnAddThemeTranslation" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <span data-i18n="theme_manager_settings.translations.add_btn">Add Translation</span>
                    </button>
                </div>
            </div>
        </div>

    </div><!-- end themeFormView -->

</div><!-- end themes-page -->

<!-- ═══════════ INIT SCRIPTS ═══════════ -->
<script src="/admin/assets/js/themes-system.js?v=<?= time() ?>"></script>

<?php if (!$isFragment): ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>