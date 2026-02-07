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
?>
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/themes-system.css?v=<?= time() ?>">
<?php endif; ?>

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
        systemSettings: '/api/system_settings'
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

        <!-- Loading -->
        <div id="themesLoading" class="loading-state" style="display:none">
            <div class="spinner"></div>
            <p data-i18n="theme_manager.loading">Loading themes...</p>
        </div>

        <!-- Themes Table -->
        <div id="themesTableContainer" style="display:none">
            <table class="data-table" id="themesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th data-i18n="theme_manager.form.fields.name.label">Name</th>
                        <th data-i18n="theme_manager.form.fields.slug.label">Slug</th>
                        <th data-i18n="theme_manager.form.fields.version.label">Version</th>
                        <th data-i18n="theme_manager.form.fields.status.label">Status</th>
                        <th data-i18n="theme_manager.form.fields.is_default">Default</th>
                        <th>Actions</th>
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
                        <label for="themeThumbnailUrl">Thumbnail URL</label>
                        <input type="text" id="themeThumbnailUrl" class="form-control" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label for="themePreviewUrl">Preview URL</label>
                        <input type="text" id="themePreviewUrl" class="form-control" placeholder="https://...">
                    </div>
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
                <h3 data-i18n="tabs.design">Design Settings</h3>
                <button class="btn btn-sm btn-primary" id="btnAddDesign">
                    <i class="fas fa-plus"></i> Add Setting
                </button>
            </div>
            <div id="designSettingsList" class="settings-list"></div>
            <div id="designForm" class="inline-form" style="display:none">
                <input type="hidden" id="designId">
                <div class="form-row">
                    <div class="form-group"><label>Key</label><input type="text" id="designKey" class="form-control"></div>
                    <div class="form-group"><label>Name</label><input type="text" id="designName" class="form-control"></div>
                    <div class="form-group"><label>Value</label><input type="text" id="designValue" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Type</label>
                        <select id="designType" class="form-control">
                            <option value="text">Text</option><option value="number">Number</option>
                            <option value="color">Color</option><option value="image">Image</option>
                            <option value="boolean">Boolean</option><option value="select">Select</option>
                            <option value="json">JSON</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select id="designCategory" class="form-control">
                            <option value="layout">Layout</option><option value="header">Header</option>
                            <option value="footer">Footer</option><option value="sidebar">Sidebar</option>
                            <option value="homepage">Homepage</option><option value="product">Product</option>
                            <option value="cart">Cart</option><option value="checkout">Checkout</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Active</label>
                        <select id="designIsActive" class="form-control"><option value="1">Yes</option><option value="0">No</option></select>
                    </div>
                    <div class="form-group"><label>Sort Order</label><input type="number" id="designSortOrder" class="form-control" value="0"></div>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveDesign">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelDesign">Cancel</button>
                </div>
            </div>
        </div>

        <!-- TAB: Color Settings -->
        <div class="tab-content" id="tab-colors" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="tabs.colors">Color Settings</h3>
                <button class="btn btn-sm btn-primary" id="btnAddColor">
                    <i class="fas fa-plus"></i> Add Color
                </button>
            </div>
            <div id="colorSettingsList" class="settings-list"></div>
            <div id="colorForm" class="inline-form" style="display:none">
                <input type="hidden" id="colorId">
                <div class="form-row">
                    <div class="form-group"><label>Key</label><input type="text" id="colorKey" class="form-control"></div>
                    <div class="form-group"><label>Name</label><input type="text" id="colorName" class="form-control"></div>
                    <div class="form-group"><label>Color</label><input type="color" id="colorValue" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select id="colorCategory" class="form-control">
                            <option value="primary">Primary</option><option value="secondary">Secondary</option>
                            <option value="accent">Accent</option><option value="background">Background</option>
                            <option value="text">Text</option><option value="border">Border</option>
                            <option value="status">Status</option><option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Active</label>
                        <select id="colorIsActive" class="form-control"><option value="1">Yes</option><option value="0">No</option></select>
                    </div>
                    <div class="form-group"><label>Sort Order</label><input type="number" id="colorSortOrder" class="form-control" value="0"></div>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveColor">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelColor">Cancel</button>
                </div>
            </div>
        </div>

        <!-- TAB: Font Settings -->
        <div class="tab-content" id="tab-fonts" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="tabs.fonts">Font Settings</h3>
                <button class="btn btn-sm btn-primary" id="btnAddFont">
                    <i class="fas fa-plus"></i> Add Font
                </button>
            </div>
            <div id="fontSettingsList" class="settings-list"></div>
            <div id="fontForm" class="inline-form" style="display:none">
                <input type="hidden" id="fontId">
                <div class="form-row">
                    <div class="form-group"><label>Key</label><input type="text" id="fontKey" class="form-control"></div>
                    <div class="form-group"><label>Name</label><input type="text" id="fontName" class="form-control"></div>
                    <div class="form-group"><label>Font Family</label><input type="text" id="fontFamily" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Size</label><input type="text" id="fontSize" class="form-control" placeholder="16px"></div>
                    <div class="form-group"><label>Weight</label><input type="text" id="fontWeight" class="form-control" placeholder="400"></div>
                    <div class="form-group"><label>Line Height</label><input type="text" id="fontLineHeight" class="form-control" placeholder="1.5"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select id="fontCategory" class="form-control">
                            <option value="heading">Heading</option><option value="body">Body</option>
                            <option value="button">Button</option><option value="navigation">Navigation</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Active</label>
                        <select id="fontIsActive" class="form-control"><option value="1">Yes</option><option value="0">No</option></select>
                    </div>
                    <div class="form-group"><label>Sort Order</label><input type="number" id="fontSortOrder" class="form-control" value="0"></div>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveFont">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelFont">Cancel</button>
                </div>
            </div>
        </div>

        <!-- TAB: Button Styles -->
        <div class="tab-content" id="tab-buttons" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="tabs.buttons">Button Styles</h3>
                <button class="btn btn-sm btn-primary" id="btnAddButton">
                    <i class="fas fa-plus"></i> Add Button Style
                </button>
            </div>
            <div id="buttonStylesList" class="settings-list"></div>
            <div id="buttonForm" class="inline-form" style="display:none">
                <input type="hidden" id="buttonId">
                <div class="form-row">
                    <div class="form-group"><label>Name</label><input type="text" id="buttonName" class="form-control"></div>
                    <div class="form-group"><label>Slug</label><input type="text" id="buttonSlug" class="form-control"></div>
                    <div class="form-group">
                        <label>Type</label>
                        <select id="buttonType" class="form-control">
                            <option value="primary">Primary</option><option value="secondary">Secondary</option>
                            <option value="success">Success</option><option value="danger">Danger</option>
                            <option value="warning">Warning</option><option value="info">Info</option>
                            <option value="outline">Outline</option><option value="link">Link</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>BG Color</label><input type="color" id="buttonBgColor" class="form-control" value="#007bff"></div>
                    <div class="form-group"><label>Text Color</label><input type="color" id="buttonTextColor" class="form-control" value="#ffffff"></div>
                    <div class="form-group"><label>Border Color</label><input type="color" id="buttonBorderColor" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Border Width</label><input type="number" id="buttonBorderWidth" class="form-control" value="0"></div>
                    <div class="form-group"><label>Border Radius</label><input type="number" id="buttonBorderRadius" class="form-control" value="4"></div>
                    <div class="form-group"><label>Padding</label><input type="text" id="buttonPadding" class="form-control" value="10px 20px"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Font Size</label><input type="text" id="buttonFontSize" class="form-control" value="14px"></div>
                    <div class="form-group"><label>Font Weight</label><input type="text" id="buttonFontWeight" class="form-control" value="normal"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Hover BG</label><input type="color" id="buttonHoverBg" class="form-control"></div>
                    <div class="form-group"><label>Hover Text</label><input type="color" id="buttonHoverText" class="form-control"></div>
                    <div class="form-group"><label>Hover Border</label><input type="color" id="buttonHoverBorder" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Active</label>
                        <select id="buttonIsActive" class="form-control"><option value="1">Yes</option><option value="0">No</option></select>
                    </div>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveButton">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelButton">Cancel</button>
                </div>
            </div>
        </div>

        <!-- TAB: Card Styles -->
        <div class="tab-content" id="tab-cards" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="tabs.cards">Card Styles</h3>
                <button class="btn btn-sm btn-primary" id="btnAddCard">
                    <i class="fas fa-plus"></i> Add Card Style
                </button>
            </div>
            <div id="cardStylesList" class="settings-list"></div>
            <div id="cardForm" class="inline-form" style="display:none">
                <input type="hidden" id="cardId">
                <div class="form-row">
                    <div class="form-group"><label>Name</label><input type="text" id="cardName" class="form-control"></div>
                    <div class="form-group"><label>Slug</label><input type="text" id="cardSlug" class="form-control"></div>
                    <div class="form-group">
                        <label>Type</label>
                        <select id="cardType" class="form-control">
                            <option value="product">Product</option><option value="category">Category</option>
                            <option value="vendor">Vendor</option><option value="blog">Blog</option>
                            <option value="feature">Feature</option><option value="testimonial">Testimonial</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>BG Color</label><input type="color" id="cardBgColor" class="form-control" value="#FFFFFF"></div>
                    <div class="form-group"><label>Border Color</label><input type="color" id="cardBorderColor" class="form-control" value="#E0E0E0"></div>
                    <div class="form-group"><label>Border Width</label><input type="number" id="cardBorderWidth" class="form-control" value="1"></div>
                    <div class="form-group"><label>Border Radius</label><input type="number" id="cardBorderRadius" class="form-control" value="8"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Shadow</label><input type="text" id="cardShadow" class="form-control" value="none" placeholder="0 2px 4px rgba(0,0,0,.1)"></div>
                    <div class="form-group"><label>Padding</label><input type="text" id="cardPadding" class="form-control" value="16px"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Hover Effect</label>
                        <select id="cardHoverEffect" class="form-control">
                            <option value="none">None</option><option value="lift">Lift</option>
                            <option value="zoom">Zoom</option><option value="shadow">Shadow</option>
                            <option value="border">Border</option><option value="brightness">Brightness</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Text Align</label>
                        <select id="cardTextAlign" class="form-control">
                            <option value="left">Left</option><option value="center">Center</option>
                            <option value="right">Right</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Image Ratio</label><input type="text" id="cardImageRatio" class="form-control" value="1:1"></div>
                    <div class="form-group">
                        <label>Active</label>
                        <select id="cardIsActive" class="form-control"><option value="1">Yes</option><option value="0">No</option></select>
                    </div>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveCard">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelCard">Cancel</button>
                </div>
            </div>
        </div>

        <!-- TAB: Homepage Sections -->
        <div class="tab-content" id="tab-homepage" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="tabs.homepage">Homepage Sections</h3>
                <button class="btn btn-sm btn-primary" id="btnAddSection">
                    <i class="fas fa-plus"></i> Add Section
                </button>
            </div>
            <div id="homepageSectionsList" class="settings-list"></div>
            <div id="sectionForm" class="inline-form" style="display:none">
                <input type="hidden" id="sectionId">
                <div class="form-row">
                    <div class="form-group">
                        <label>Section Type</label>
                        <select id="sectionType" class="form-control">
                            <option value="slider">Slider</option><option value="categories">Categories</option>
                            <option value="featured_products">Featured Products</option><option value="new_products">New Products</option>
                            <option value="deals">Deals</option><option value="brands">Brands</option>
                            <option value="vendors">Vendors</option><option value="banners">Banners</option>
                            <option value="testimonials">Testimonials</option><option value="custom_html">Custom HTML</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Title</label><input type="text" id="sectionTitle" class="form-control"></div>
                    <div class="form-group"><label>Subtitle</label><input type="text" id="sectionSubtitle" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Layout</label>
                        <select id="sectionLayout" class="form-control">
                            <option value="grid">Grid</option><option value="slider">Slider</option>
                            <option value="list">List</option><option value="carousel">Carousel</option>
                            <option value="masonry">Masonry</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Items/Row</label><input type="number" id="sectionItemsPerRow" class="form-control" value="4"></div>
                    <div class="form-group"><label>Sort Order</label><input type="number" id="sectionSortOrder" class="form-control" value="0"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>BG Color</label><input type="color" id="sectionBgColor" class="form-control" value="#FFFFFF"></div>
                    <div class="form-group"><label>Text Color</label><input type="color" id="sectionTextColor" class="form-control" value="#000000"></div>
                    <div class="form-group"><label>Padding</label><input type="text" id="sectionPadding" class="form-control" value="40px 0"></div>
                </div>
                <div class="form-group"><label>Data Source</label><input type="text" id="sectionDataSource" class="form-control" placeholder="e.g. /api/products?is_featured=1"></div>
                <div class="form-group"><label>Custom CSS</label><textarea id="sectionCustomCss" class="form-control" rows="2"></textarea></div>
                <div class="form-group"><label>Custom HTML</label><textarea id="sectionCustomHtml" class="form-control" rows="3"></textarea></div>
                <div class="form-group">
                    <label><input type="checkbox" id="sectionIsActive" checked> Active</label>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveSection">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelSection">Cancel</button>
                </div>
            </div>
        </div>

        <!-- TAB: System Settings -->
        <div class="tab-content" id="tab-system" style="display:none">
            <div class="settings-header">
                <h3 data-i18n="tabs.system">System Settings</h3>
                <button class="btn btn-sm btn-primary" id="btnAddSystem">
                    <i class="fas fa-plus"></i> Add Setting
                </button>
            </div>
            <div id="systemSettingsList" class="settings-list"></div>
            <div id="systemForm" class="inline-form" style="display:none">
                <input type="hidden" id="systemId">
                <div class="form-row">
                    <div class="form-group"><label>Key</label><input type="text" id="systemKey" class="form-control"></div>
                    <div class="form-group"><label>Category</label><input type="text" id="systemCategory" class="form-control"></div>
                    <div class="form-group">
                        <label>Type</label>
                        <select id="systemType" class="form-control">
                            <option value="text">Text</option><option value="number">Number</option>
                            <option value="boolean">Boolean</option><option value="json">JSON</option>
                            <option value="file">File</option><option value="email">Email</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Value</label><textarea id="systemValue" class="form-control" rows="2"></textarea></div>
                <div class="form-group"><label>Description</label><textarea id="systemDescription" class="form-control" rows="2"></textarea></div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Public</label>
                        <select id="systemIsPublic" class="form-control"><option value="0">No</option><option value="1">Yes</option></select>
                    </div>
                    <div class="form-group">
                        <label>Editable</label>
                        <select id="systemIsEditable" class="form-control"><option value="1">Yes</option><option value="0">No</option></select>
                    </div>
                </div>
                <div class="inline-form-actions">
                    <button class="btn btn-primary btn-sm" id="btnSaveSystem">Save</button>
                    <button class="btn btn-secondary btn-sm" id="btnCancelSystem">Cancel</button>
                </div>
            </div>
        </div>

    </div><!-- end themeFormView -->
</div><!-- end themes-page -->

<!-- ═══════════ INIT SCRIPTS ═══════════ -->
<script src="/admin/assets/js/themes-system.js?v=<?= time() ?>"></script>
<?php if ($isFragment): ?>
<script>
(function() {
    let attempts = 0;
    const maxAttempts = 50;
    function tryInit() {
        if (window.ThemesSystem && typeof window.ThemesSystem.init === 'function') {
            window.ThemesSystem.init();
            return;
        }
        if (++attempts < maxAttempts) {
            setTimeout(tryInit, 100);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryInit);
    } else {
        tryInit();
    }
})();
</script>
<?php else: ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.ThemesSystem) window.ThemesSystem.init();
});
</script>
<?php endif; ?>

<?php if (!$isFragment): ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>
