<?php
declare(strict_types=1);

/**
 * /admin/fragments/themes.php
 * Theme Management - Rewritten to match Products pattern
 * 
 * ✅ Uses new permission system (role-based + resource-based)
 * ✅ Compatible with tenant_users table
 * ✅ Fragment/standalone mode support
 * ✅ All theme sub-entities: design_settings, color_settings, font_settings, 
 *    button_styles, card_styles, homepage_sections
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
$dir  = admin_dir();
$csrf = admin_csrf();
$tenantId = admin_tenant_id();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════
$isSuperAdmin = is_super_admin();
$canManage = $isSuperAdmin || can('manage_themes');

if (!$canManage) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to manage themes');
    }
}

$apiBase = '/api';
?>
<!-- Force load CSS if embedded -->
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/themes-system.css?v=<?= time() ?>">
<?php endif; ?>

<!-- Page Container -->
<div class="page-container" id="themesPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <div class="alerts-container" id="alertsContainer"></div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title">
                <i class="fas fa-palette"></i>
                Theme Management
            </h1>
            <p class="page-subtitle">Manage Themes, Design Settings, and Styling</p>
        </div>
        <div class="page-header-actions">
            <button class="btn btn-secondary btn-sm" id="btnRefreshThemes">
                <i class="fas fa-sync"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Main Tabs -->
    <div class="main-tabs">
        <button class="main-tab active" data-tab="themes">
            <i class="fas fa-palette"></i>
            <span>Themes</span>
        </button>
        <button class="main-tab" data-tab="design">
            <i class="fas fa-cog"></i>
            <span>Design Settings</span>
        </button>
        <button class="main-tab" data-tab="colors">
            <i class="fas fa-paint-brush"></i>
            <span>Colors</span>
        </button>
        <button class="main-tab" data-tab="fonts">
            <i class="fas fa-font"></i>
            <span>Fonts</span>
        </button>
        <button class="main-tab" data-tab="buttons">
            <i class="fas fa-mouse-pointer"></i>
            <span>Buttons</span>
        </button>
        <button class="main-tab" data-tab="cards">
            <i class="fas fa-square"></i>
            <span>Cards</span>
        </button>
        <button class="main-tab" data-tab="homepage">
            <i class="fas fa-home"></i>
            <span>Homepage</span>
        </button>
    </div>

    <!-- THEMES TAB -->
    <div class="tab-content active" id="tab-themes">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-palette"></i> Themes List
                </h3>
                <button class="btn btn-primary" id="btnAddTheme">
                    <i class="fas fa-plus"></i> Add Theme
                </button>
            </div>
            <div class="card-body">
                <div id="themesLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="themesContent" style="display:none;">
                    <div class="theme-grid" id="themesGrid"></div>
                </div>
                <div id="themesEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-palette"></i>
                    <h3>No Themes</h3>
                    <button class="btn btn-primary" id="btnAddThemeEmpty">
                        <i class="fas fa-plus"></i> Add First Theme
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- DESIGN SETTINGS TAB -->
    <div class="tab-content" id="tab-design">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-palette"></i> Select Theme</h3>
            </div>
            <div class="card-body">
                <div class="theme-selector" id="designThemeSelector"></div>
            </div>
        </div>
        <div class="card" id="designCard" style="display:none;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-cog"></i> Design Settings for <span id="designThemeName"></span>
                </h3>
                <button class="btn btn-success" id="btnSaveDesign">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
            <div class="card-body">
                <div id="designLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="designContent" style="display:none;">
                    <div class="settings-grid" id="designSettingsGrid"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- COLORS TAB -->
    <div class="tab-content" id="tab-colors">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-palette"></i> Select Theme</h3>
            </div>
            <div class="card-body">
                <div class="theme-selector" id="colorsThemeSelector"></div>
            </div>
        </div>
        <div class="card" id="colorsCard" style="display:none;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-paint-brush"></i> Color Settings for <span id="colorsThemeName"></span>
                </h3>
                <button class="btn btn-success" id="btnSaveColors">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
            <div class="card-body">
                <div id="colorsLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="colorsContent" style="display:none;">
                    <div class="color-settings-grid" id="colorSettingsGrid"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- FONTS TAB -->
    <div class="tab-content" id="tab-fonts">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-palette"></i> Select Theme</h3>
            </div>
            <div class="card-body">
                <div class="theme-selector" id="fontsThemeSelector"></div>
            </div>
        </div>
        <div class="card" id="fontsCard" style="display:none;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-font"></i> Font Settings for <span id="fontsThemeName"></span>
                </h3>
                <button class="btn btn-success" id="btnSaveFonts">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
            <div class="card-body">
                <div id="fontsLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="fontsContent" style="display:none;">
                    <div class="font-settings-grid" id="fontSettingsGrid"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- BUTTONS TAB -->
    <div class="tab-content" id="tab-buttons">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-mouse-pointer"></i> Button Styles
                </h3>
                <button class="btn btn-primary" id="btnAddButton">
                    <i class="fas fa-plus"></i> Add Button Style
                </button>
            </div>
            <div class="card-body">
                <div id="buttonsLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="buttonsContent" style="display:none;">
                    <div class="button-styles-grid" id="buttonStylesGrid"></div>
                </div>
                <div id="buttonsEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-mouse-pointer"></i>
                    <h3>No Button Styles</h3>
                    <button class="btn btn-primary" id="btnAddButtonEmpty">
                        <i class="fas fa-plus"></i> Add First Style
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- CARDS TAB -->
    <div class="tab-content" id="tab-cards">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-square"></i> Card Styles
                </h3>
                <button class="btn btn-primary" id="btnAddCard">
                    <i class="fas fa-plus"></i> Add Card Style
                </button>
            </div>
            <div class="card-body">
                <div id="cardsLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="cardsContent" style="display:none;">
                    <div class="card-styles-grid" id="cardStylesGrid"></div>
                </div>
                <div id="cardsEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-square"></i>
                    <h3>No Card Styles</h3>
                    <button class="btn btn-primary" id="btnAddCardEmpty">
                        <i class="fas fa-plus"></i> Add First Style
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- HOMEPAGE TAB -->
    <div class="tab-content" id="tab-homepage">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-home"></i> Homepage Sections
                </h3>
                <button class="btn btn-primary" id="btnAddSection">
                    <i class="fas fa-plus"></i> Add Section
                </button>
            </div>
            <div class="card-body">
                <div id="homepageLoading" class="loading">
                    <div class="spinner"></div>
                    <p>Loading...</p>
                </div>
                <div id="homepageContent" style="display:none;">
                    <div class="sections-list" id="sectionsList"></div>
                </div>
                <div id="homepageEmpty" class="empty-state" style="display:none;">
                    <i class="fas fa-home"></i>
                    <h3>No Homepage Sections</h3>
                    <button class="btn btn-primary" id="btnAddSectionEmpty">
                        <i class="fas fa-plus"></i> Add First Section
                    </button>
                </div>
            </div>
        </div>
    </div>

</div><!-- /page-container -->

<!-- THEME MODAL -->
<div class="modal" id="themeModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3 class="modal-title" id="themeModalTitle">Add Theme</h3>
            <button class="modal-close" id="btnCloseThemeModal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="themeForm" onsubmit="return false;">
                <input type="hidden" id="themeId">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" class="form-control" id="themeName" required>
                    </div>
                    <div class="form-group">
                        <label>Slug *</label>
                        <input type="text" class="form-control" id="themeSlug" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" id="themeDescription" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Version</label>
                        <input type="text" class="form-control" id="themeVersion" value="1.0.0">
                    </div>
                    <div class="form-group">
                        <label>Author</label>
                        <input type="text" class="form-control" id="themeAuthor">
                    </div>
                    <div class="form-group">
                        <label>Thumbnail URL</label>
                        <input type="url" class="form-control" id="themeThumbnailUrl">
                    </div>
                    <div class="form-group">
                        <label>Preview URL</label>
                        <input type="url" class="form-control" id="themePreviewUrl">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="themeIsActive"> Active
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="themeIsDefault"> Default Theme
                        </label>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="btnCancelTheme">Cancel</button>
            <button class="btn btn-primary" id="btnSaveTheme">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<!-- BUTTON MODAL -->
<div class="modal" id="buttonModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3 class="modal-title" id="buttonModalTitle">Add Button Style</h3>
            <button class="modal-close" id="btnCloseButtonModal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="buttonForm" onsubmit="return false;">
                <input type="hidden" id="buttonId">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" class="form-control" id="buttonName" required>
                    </div>
                    <div class="form-group">
                        <label>Slug *</label>
                        <input type="text" class="form-control" id="buttonSlug" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select class="form-control" id="buttonType">
                            <option value="primary">Primary</option>
                            <option value="secondary">Secondary</option>
                            <option value="success">Success</option>
                            <option value="danger">Danger</option>
                            <option value="warning">Warning</option>
                            <option value="info">Info</option>
                            <option value="outline">Outline</option>
                            <option value="link">Link</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Background Color</label>
                        <input type="color" class="form-control" id="buttonBgColor" value="#007bff">
                    </div>
                    <div class="form-group">
                        <label>Text Color</label>
                        <input type="color" class="form-control" id="buttonTextColor" value="#ffffff">
                    </div>
                    <div class="form-group">
                        <label>Border Color</label>
                        <input type="color" class="form-control" id="buttonBorderColor" value="#007bff">
                    </div>
                    <div class="form-group">
                        <label>Border Width (px)</label>
                        <input type="number" class="form-control" id="buttonBorderWidth" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Border Radius (px)</label>
                        <input type="number" class="form-control" id="buttonBorderRadius" min="0" value="4">
                    </div>
                    <div class="form-group">
                        <label>Padding</label>
                        <input type="text" class="form-control" id="buttonPadding" value="10px 20px">
                    </div>
                    <div class="form-group">
                        <label>Font Size</label>
                        <input type="text" class="form-control" id="buttonFontSize" value="14px">
                    </div>
                    <div class="form-group">
                        <label>Font Weight</label>
                        <input type="text" class="form-control" id="buttonFontWeight" value="normal">
                    </div>
                    <div class="form-group">
                        <label>Hover Background</label>
                        <input type="color" class="form-control" id="buttonHoverBgColor">
                    </div>
                    <div class="form-group">
                        <label>Hover Text Color</label>
                        <input type="color" class="form-control" id="buttonHoverTextColor">
                    </div>
                    <div class="form-group">
                        <label>Hover Border Color</label>
                        <input type="color" class="form-control" id="buttonHoverBorderColor">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="btnCancelButton">Cancel</button>
            <button class="btn btn-primary" id="btnSaveButton">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<!-- CARD MODAL -->
<div class="modal" id="cardModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h3 class="modal-title" id="cardModalTitle">Add Card Style</h3>
            <button class="modal-close" id="btnCloseCardModal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="cardForm" onsubmit="return false;">
                <input type="hidden" id="cardId">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" class="form-control" id="cardName" required>
                    </div>
                    <div class="form-group">
                        <label>Slug *</label>
                        <input type="text" class="form-control" id="cardSlug" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select class="form-control" id="cardType">
                            <option value="product">Product</option>
                            <option value="category">Category</option>
                            <option value="vendor">Vendor</option>
                            <option value="blog">Blog</option>
                            <option value="feature">Feature</option>
                            <option value="testimonial">Testimonial</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Background Color</label>
                        <input type="color" class="form-control" id="cardBgColor" value="#ffffff">
                    </div>
                    <div class="form-group">
                        <label>Border Color</label>
                        <input type="color" class="form-control" id="cardBorderColor" value="#e0e0e0">
                    </div>
                    <div class="form-group">
                        <label>Border Width (px)</label>
                        <input type="number" class="form-control" id="cardBorderWidth" min="0" value="1">
                    </div>
                    <div class="form-group">
                        <label>Border Radius (px)</label>
                        <input type="number" class="form-control" id="cardBorderRadius" min="0" value="8">
                    </div>
                    <div class="form-group">
                        <label>Shadow Style</label>
                        <select class="form-control" id="cardShadowStyle">
                            <option value="none">None</option>
                            <option value="small">Small</option>
                            <option value="medium">Medium</option>
                            <option value="large">Large</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Padding</label>
                        <input type="text" class="form-control" id="cardPadding" value="16px">
                    </div>
                    <div class="form-group">
                        <label>Hover Effect</label>
                        <select class="form-control" id="cardHoverEffect">
                            <option value="none">None</option>
                            <option value="lift">Lift</option>
                            <option value="zoom">Zoom</option>
                            <option value="shadow">Shadow</option>
                            <option value="border">Border</option>
                            <option value="brightness">Brightness</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Text Align</label>
                        <select class="form-control" id="cardTextAlign">
                            <option value="left">Left</option>
                            <option value="center">Center</option>
                            <option value="right">Right</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Image Aspect Ratio</label>
                        <input type="text" class="form-control" id="cardAspectRatio" value="1:1">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="btnCancelCard">Cancel</button>
            <button class="btn btn-primary" id="btnSaveCard">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<!-- SECTION MODAL -->
<div class="modal" id="sectionModal">
    <div class="modal-dialog modal-xl">
        <div class="modal-header">
            <h3 class="modal-title" id="sectionModalTitle">Add Homepage Section</h3>
            <button class="modal-close" id="btnCloseSectionModal">&times;</button>
        </div>
        <div class="modal-body">
            <form id="sectionForm" onsubmit="return false;">
                <input type="hidden" id="sectionId">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Type *</label>
                        <select class="form-control" id="sectionType" required>
                            <option value="slider">Slider</option>
                            <option value="categories">Categories</option>
                            <option value="featured_products">Featured Products</option>
                            <option value="new_products">New Products</option>
                            <option value="deals">Deals</option>
                            <option value="brands">Brands</option>
                            <option value="vendors">Vendors</option>
                            <option value="banners">Banners</option>
                            <option value="testimonials">Testimonials</option>
                            <option value="custom_html">Custom HTML</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" class="form-control" id="sectionTitle">
                    </div>
                    <div class="form-group">
                        <label>Subtitle</label>
                        <input type="text" class="form-control" id="sectionSubtitle">
                    </div>
                    <div class="form-group">
                        <label>Layout Type</label>
                        <select class="form-control" id="sectionLayoutType">
                            <option value="grid">Grid</option>
                            <option value="slider">Slider</option>
                            <option value="list">List</option>
                            <option value="carousel">Carousel</option>
                            <option value="masonry">Masonry</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Items Per Row</label>
                        <input type="number" class="form-control" id="sectionItemsPerRow" min="1" max="12" value="4">
                    </div>
                    <div class="form-group">
                        <label>Background Color</label>
                        <input type="color" class="form-control" id="sectionBgColor" value="#ffffff">
                    </div>
                    <div class="form-group">
                        <label>Text Color</label>
                        <input type="color" class="form-control" id="sectionTextColor" value="#000000">
                    </div>
                    <div class="form-group">
                        <label>Padding</label>
                        <input type="text" class="form-control" id="sectionPadding" value="40px 0">
                    </div>
                    <div class="form-group">
                        <label>Custom CSS</label>
                        <textarea class="form-control" id="sectionCustomCss" rows="4" placeholder="Additional CSS styles"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Custom HTML</label>
                        <textarea class="form-control" id="sectionCustomHtml" rows="4" placeholder="Custom HTML content"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Data Source</label>
                        <input type="text" class="form-control" id="sectionDataSource" placeholder="API endpoint or query">
                    </div>
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" class="form-control" id="sectionSortOrder" min="0" value="0">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="btnCancelSection">Cancel</button>
            <button class="btn btn-primary" id="btnSaveSection">
                <i class="fas fa-save"></i> Save
            </button>
        </div>
    </div>
</div>

<!-- Page Config -->
<script>
window.THEMES_CONFIG = {
    API_BASE: '<?= $apiBase ?>',
    TENANT_ID: <?= (int)$tenantId ?>,
    CSRF_TOKEN: '<?= htmlspecialchars($csrf) ?>'
};
</script>

<!-- Load scripts based on mode -->
<?php if ($isFragment): ?>
<script src="/admin/assets/js/themes-system.js?v=<?= time() ?>"></script>

<script>
(function(){
    console.log('[Themes] Embedded mode - waiting for module...');
    var attempts = 0, maxAttempts = 50;
    var interval = setInterval(function(){
        attempts++;
        if (window.ThemesApp && typeof window.ThemesApp.init === 'function') {
            clearInterval(interval);
            console.log('[Themes] Module ready - initializing (attempt ' + attempts + ')...');
            try {
                window.ThemesApp.init();
                console.log('[Themes] ✓ Initialized successfully');
            } catch (e) {
                console.error('[Themes] Init threw:', e);
            }
        } else if (attempts > maxAttempts) {
            clearInterval(interval);
            console.error('[Themes] Timeout waiting for module after ' + (maxAttempts * 100) + 'ms');
        }
    }, 100);
})();
</script>
<?php else: ?>
<script src="/admin/assets/js/themes-system.js?v=<?= time() ?>"></script>
<script>
(function(){
    function tryInit() {
        if (window.ThemesApp && typeof window.ThemesApp.init === 'function') {
            window.ThemesApp.init();
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
